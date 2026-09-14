<?php
declare(strict_types=1);

namespace App\Tests\Mercure;

use Symfony\Component\Mercure\Update;

/**
 * Publisher callable of the test MockHub: drops the update (no network), the
 * TraceableHub decorator still records it for the profiler's Mercure collector.
 */
final class NullPublisher
{
    public function __invoke(Update $update): string
    {
        return '';
    }
}
