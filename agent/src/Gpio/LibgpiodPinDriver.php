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
    private ?CData $request = null;

    public function __construct(
        private readonly string $chipPath,
        private readonly string $consumer,
        private readonly string $library,
    )
    {
    }

    public function __destruct()
    {
        if (null !== $this->request) {
            $this->ffi->gpiod_line_request_release($this->request);
        }

        if (null !== $this->chip) {
            $this->ffi->gpiod_chip_close($this->chip);
        }
    }

    /**
     * Requests every GPIO line as an output, initialised to INACTIVE.
     */
    public function setup(int ...$pins): void
    {
        if ([] === $pins) {
            throw new \InvalidArgumentException('At least one GPIO pin is required.');
        }

        foreach ($pins as $pin) {
            if ($pin < 0) {
                throw new \InvalidArgumentException('GPIO pin must be positive or zero.');
            }
        }

        $ffi = $this->ffi();
        $settings = $this->requirePointer($ffi->gpiod_line_settings_new(), 'Unable to allocate libgpiod line settings');
        $lineConfig = null;
        $requestConfig = null;

        try {
            $this->assertResult(
                $ffi->gpiod_line_settings_set_direction($settings, $ffi->GPIOD_LINE_DIRECTION_OUTPUT),
                'Unable to configure GPIO lines as output',
            );
            $this->assertResult(
                $ffi->gpiod_line_settings_set_output_value($settings, $ffi->GPIOD_LINE_VALUE_INACTIVE),
                'Unable to configure GPIO lines initial value',
            );

            $count = count($pins);
            $offsets = $ffi->new("unsigned int[$count]");
            foreach ($pins as $i => $pin) {
                $offsets[$i] = $pin;
            }

            $lineConfig = $this->requirePointer($ffi->gpiod_line_config_new(), 'Unable to allocate libgpiod line config');
            $this->assertResult(
                $ffi->gpiod_line_config_add_line_settings($lineConfig, $offsets, $count, $settings),
                'Unable to attach GPIO line settings',
            );

            $requestConfig = $this->requirePointer($ffi->gpiod_request_config_new(), 'Unable to allocate libgpiod request config');
            $ffi->gpiod_request_config_set_consumer($requestConfig, $this->consumer);

            $this->request = $this->requirePointer(
                $ffi->gpiod_chip_request_lines($this->chip(), $requestConfig, $lineConfig),
                'Unable to request GPIO lines on chip ' . $this->chipPath,
            );
        } finally {
            if (null !== $requestConfig) {
                $ffi->gpiod_request_config_free($requestConfig);
            }
            if (null !== $lineConfig) {
                $ffi->gpiod_line_config_free($lineConfig);
            }
            $ffi->gpiod_line_settings_free($settings);
        }
    }

    public function setLineValue(int $pin, bool $active): void
    {
        if (null === $this->request) {
            throw new \LogicException('GPIO driver must be set up before setting line values.');
        }

        $ffi = $this->ffi();

        $this->assertResult(
            $ffi->gpiod_line_request_set_value(
                $this->request,
                $pin,
                $active ? $ffi->GPIOD_LINE_VALUE_ACTIVE : $ffi->GPIOD_LINE_VALUE_INACTIVE,
            ),
            'Unable to set GPIO line ' . $pin . ' on chip ' . $this->chipPath,
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

    private function headerDefinition(): string
    {
        $header = file_get_contents(__DIR__ . '/libgpiod.h');
        if (false === $header) {
            throw new \RuntimeException('Unable to read libgpiod FFI header definition.');
        }

        return $header;
    }

    private function assertResult(int $result, string $message): void
    {
        if (-1 === $result) {
            throw new \RuntimeException($message . '.');
        }
    }

    private function requirePointer(mixed $pointer, string $message): CData
    {
        if (null === $pointer || FFI::isNull($pointer)) {
            throw new \RuntimeException($message . '.');
        }

        return $pointer;
    }
}
