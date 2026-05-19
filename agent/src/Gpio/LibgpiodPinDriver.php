<?php
declare(strict_types=1);

namespace App\Gpio;

use FFI;
use FFI\CData;
use FFI\Exception as FfiException;

final class LibgpiodPinDriver
{
    private ?FFI $ffi = null;
    private ?CData $chip = null;

    /**
     * @var array<int, CData>
     */
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

        $this->releaseRequests();
        $this->closeChip();
    }

    public function setLineValue(int $offset, bool $active): void
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('GPIO offset must be positive or zero.');
        }

        $ffi = $this->ffi();
        $value = $this->toLineValue($active);
        $request = $this->getOrCreateRequest($offset, $value);

        $this->assertResult(
            $ffi->gpiod_line_request_set_value($request, $offset, $value),
            'Unable to set GPIO line ' . $offset . ' on chip ' . $this->chipPath,
        );
    }

    private function ffi(): FFI
    {
        if (null !== $this->ffi) {
            return $this->ffi;
        }

        try {
            $this->ffi = FFI::cdef($this->headerDefinition(), $this->library);
        } catch (FfiException $e) {
            throw new \RuntimeException(
                'Unable to load libgpiod through PHP FFI from ' . $this->library . '.',
                previous: $e,
            );
        }

        return $this->ffi;
    }

    private function chip(): CData
    {
        if (null !== $this->chip) {
            return $this->chip;
        }

        $this->chip = $this->requirePointer(
            $this->ffi()->gpiod_chip_open($this->chipPath),
            'Unable to open GPIO chip ' . $this->chipPath,
        );

        return $this->chip;
    }

    private function getOrCreateRequest(int $offset, int $initialValue): CData
    {
        if (isset($this->requests[$offset])) {
            return $this->requests[$offset];
        }

        $this->requests[$offset] = $this->createRequest($offset, $initialValue);

        return $this->requests[$offset];
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

    private function headerDefinition(): string
    {
        $header = file_get_contents(__DIR__ . '/libgpiod.h');
        if (false === $header) {
            throw new \RuntimeException('Unable to read libgpiod FFI header definition.');
        }

        return $header;
    }

    private function createRequest(int $offset, int $initialValue): CData
    {
        $ffi = $this->ffi();
        $settings = $this->createLineSettings($ffi, $offset, $initialValue);
        $lineConfig = null;
        $requestConfig = null;

        try {
            $lineConfig = $this->createLineConfig($ffi, $offset, $settings);
            $requestConfig = $this->createRequestConfig($ffi);

            return $this->requirePointer(
                $ffi->gpiod_chip_request_lines($this->chip(), $requestConfig, $lineConfig),
                'Unable to request GPIO line ' . $offset . ' on chip ' . $this->chipPath,
            );
        } finally {
            $this->freeRequestConfig($requestConfig);
            $this->freeLineConfig($lineConfig);
            $ffi->gpiod_line_settings_free($settings);
        }
    }

    private function createLineSettings(FFI $ffi, int $offset, int $initialValue): CData
    {
        $settings = $this->requirePointer(
            $ffi->gpiod_line_settings_new(),
            'Unable to allocate libgpiod line settings',
        );

        $this->assertResult(
            $ffi->gpiod_line_settings_set_direction($settings, $ffi->GPIOD_LINE_DIRECTION_OUTPUT),
            'Unable to configure GPIO line ' . $offset . ' as output',
        );
        $this->assertResult(
            $ffi->gpiod_line_settings_set_output_value($settings, $initialValue),
            'Unable to configure GPIO line ' . $offset . ' initial value',
        );

        return $settings;
    }

    private function createLineConfig(FFI $ffi, int $offset, CData $settings): CData
    {
        $lineConfig = $this->requirePointer(
            $ffi->gpiod_line_config_new(),
            'Unable to allocate libgpiod line config',
        );

        $offsets = $ffi->new('unsigned int[1]');
        $offsets[0] = $offset;

        $this->assertResult(
            $ffi->gpiod_line_config_add_line_settings($lineConfig, $offsets, 1, $settings),
            'Unable to attach GPIO line settings for line ' . $offset,
        );

        return $lineConfig;
    }

    private function createRequestConfig(FFI $ffi): CData
    {
        $requestConfig = $this->requirePointer(
            $ffi->gpiod_request_config_new(),
            'Unable to allocate libgpiod request config',
        );

        $ffi->gpiod_request_config_set_consumer($requestConfig, $this->consumer);

        return $requestConfig;
    }

    private function releaseRequests(): void
    {
        foreach ($this->requests as $request) {
            if ($this->isNullPointer($request)) {
                continue;
            }

            $this->ffi->gpiod_line_request_release($request);
        }
    }

    private function closeChip(): void
    {
        if ($this->isNullPointer($this->chip)) {
            return;
        }

        $this->ffi->gpiod_chip_close($this->chip);
    }

    private function freeRequestConfig(mixed $requestConfig): void
    {
        if ($this->isNullPointer($requestConfig)) {
            return;
        }

        $this->ffi->gpiod_request_config_free($requestConfig);
    }

    private function freeLineConfig(mixed $lineConfig): void
    {
        if ($this->isNullPointer($lineConfig)) {
            return;
        }

        $this->ffi->gpiod_line_config_free($lineConfig);
    }

    private function requirePointer(mixed $pointer, string $message): CData
    {
        if ($this->isNullPointer($pointer)) {
            throw $this->runtimeError($message);
        }

        return $pointer;
    }

    private function isNullPointer(mixed $pointer): bool
    {
        return null === $pointer || ($pointer instanceof CData && FFI::isNull($pointer));
    }

    private function runtimeError(string $message): \RuntimeException
    {
        return new \RuntimeException($message . '.');
    }
}
