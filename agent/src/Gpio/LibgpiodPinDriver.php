<?php
declare(strict_types=1);

namespace App\Gpio;

use FFI\Exception;

final class LibgpiodPinDriver
{
    private const string CDEF = <<<'CDEF'
struct gpiod_chip;
struct gpiod_line_config;
struct gpiod_line_request;
struct gpiod_line_settings;
struct gpiod_request_config;

enum gpiod_line_value {
    GPIOD_LINE_VALUE_ERROR = -1,
    GPIOD_LINE_VALUE_INACTIVE = 0,
    GPIOD_LINE_VALUE_ACTIVE = 1,
};

enum gpiod_line_direction {
    GPIOD_LINE_DIRECTION_AS_IS = 1,
    GPIOD_LINE_DIRECTION_INPUT,
    GPIOD_LINE_DIRECTION_OUTPUT,
};

struct gpiod_chip *gpiod_chip_open(const char *path);
void gpiod_chip_close(struct gpiod_chip *chip);

struct gpiod_line_settings *gpiod_line_settings_new(void);
void gpiod_line_settings_free(struct gpiod_line_settings *settings);
int gpiod_line_settings_set_direction(struct gpiod_line_settings *settings, enum gpiod_line_direction direction);
int gpiod_line_settings_set_output_value(struct gpiod_line_settings *settings, enum gpiod_line_value value);

struct gpiod_line_config *gpiod_line_config_new(void);
void gpiod_line_config_free(struct gpiod_line_config *config);
int gpiod_line_config_add_line_settings(struct gpiod_line_config *config, const unsigned int *offsets, size_t num_offsets, struct gpiod_line_settings *settings);

struct gpiod_request_config *gpiod_request_config_new(void);
void gpiod_request_config_free(struct gpiod_request_config *config);
void gpiod_request_config_set_consumer(struct gpiod_request_config *config, const char *consumer);

struct gpiod_line_request *gpiod_chip_request_lines(struct gpiod_chip *chip, struct gpiod_request_config *req_cfg, struct gpiod_line_config *line_cfg);
void gpiod_line_request_release(struct gpiod_line_request *request);
int gpiod_line_request_set_value(struct gpiod_line_request *request, unsigned int offset, enum gpiod_line_value value);
CDEF;

    private ?\FFI $ffi = null;
    private ?\FFI\CData $chip = null;

    /** @var array<int, \FFI\CData> */
    private array $requests = [];

    public function __construct(
        private readonly string $chipPath,
        private readonly string $consumer,
        private readonly string $library,
    )
    {
    }

    public function __destruct()
    {
        if (null === $this->ffi) {
            return;
        }

        foreach ($this->requests as $request) {
            if (!\FFI::isNull($request)) {
                $this->ffi->gpiod_line_request_release($request);
            }
        }

        if (null !== $this->chip && !\FFI::isNull($this->chip)) {
            $this->ffi->gpiod_chip_close($this->chip);
        }
    }

    public function setLineValue(int $offset, bool $active): void
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('GPIO offset must be positive or zero.');
        }

        $ffi = $this->ffi();
        $request = $this->getOrCreateRequest($offset, $this->toLineValue($active));

        $this->assertResult(
            $ffi->gpiod_line_request_set_value($request, $offset, $this->toLineValue($active)),
            'Unable to set GPIO line ' . $offset . ' on chip ' . $this->chipPath,
        );
    }

    private function ffi(): \FFI
    {
        if (null !== $this->ffi) {
            return $this->ffi;
        }

        try {
            $this->ffi = \FFI::cdef(self::CDEF, $this->library);
        } catch (Exception $e) {
            throw new \RuntimeException(
                'Unable to load libgpiod through PHP FFI from ' . $this->library . '.',
                previous: $e,
            );
        }

        return $this->ffi;
    }

    private function chip(): \FFI\CData
    {
        if (null !== $this->chip) {
            return $this->chip;
        }

        $chip = $this->ffi()->gpiod_chip_open($this->chipPath);
        if ($this->isNullPointer($chip)) {
            throw $this->runtimeError('Unable to open GPIO chip ' . $this->chipPath);
        }

        $this->chip = $chip;

        return $chip;
    }

    private function getOrCreateRequest(int $offset, int $initialValue): \FFI\CData
    {
        if (isset($this->requests[$offset])) {
            return $this->requests[$offset];
        }

        $ffi = $this->ffi();
        $settings = $ffi->gpiod_line_settings_new();
        if ($this->isNullPointer($settings)) {
            throw $this->runtimeError('Unable to allocate libgpiod line settings');
        }

        $lineConfig = null;
        $requestConfig = null;

        try {
            $this->assertResult(
                $ffi->gpiod_line_settings_set_direction($settings, $ffi->GPIOD_LINE_DIRECTION_OUTPUT),
                'Unable to configure GPIO line ' . $offset . ' as output',
            );
            $this->assertResult(
                $ffi->gpiod_line_settings_set_output_value($settings, $initialValue),
                'Unable to configure GPIO line ' . $offset . ' initial value',
            );

            $lineConfig = $ffi->gpiod_line_config_new();
            if ($this->isNullPointer($lineConfig)) {
                throw $this->runtimeError('Unable to allocate libgpiod line config');
            }

            $offsets = $ffi->new('unsigned int[1]');
            $offsets[0] = $offset;

            $this->assertResult(
                $ffi->gpiod_line_config_add_line_settings($lineConfig, $offsets, 1, $settings),
                'Unable to attach GPIO line settings for line ' . $offset,
            );

            $requestConfig = $ffi->gpiod_request_config_new();
            if ($this->isNullPointer($requestConfig)) {
                throw $this->runtimeError('Unable to allocate libgpiod request config');
            }

            $ffi->gpiod_request_config_set_consumer($requestConfig, $this->consumer);

            $request = $ffi->gpiod_chip_request_lines($this->chip(), $requestConfig, $lineConfig);
            if ($this->isNullPointer($request)) {
                throw $this->runtimeError(
                    'Unable to request GPIO line ' . $offset . ' on chip ' . $this->chipPath,
                );
            }
        } finally {
            if (!$this->isNullPointer($requestConfig)) {
                $ffi->gpiod_request_config_free($requestConfig);
            }

            if (!$this->isNullPointer($lineConfig)) {
                $ffi->gpiod_line_config_free($lineConfig);
            }

            $ffi->gpiod_line_settings_free($settings);
        }

        $this->requests[$offset] = $request;

        return $request;
    }

    private function toLineValue(bool $active): int
    {
        $ffi = $this->ffi();

        return $active ? $ffi->GPIOD_LINE_VALUE_ACTIVE : $ffi->GPIOD_LINE_VALUE_INACTIVE;
    }

    private function assertResult(int $result, string $message): void
    {
        if (-1 === $result) {
            throw $this->runtimeError($message);
        }
    }

    private function isNullPointer(mixed $pointer): bool
    {
        return null === $pointer || ($pointer instanceof \FFI\CData && \FFI::isNull($pointer));
    }

    private function runtimeError(string $message): \RuntimeException
    {
        return new \RuntimeException($message . '.');
    }
}
