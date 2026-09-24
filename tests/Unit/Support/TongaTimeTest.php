<?php

namespace Tests\Unit\Support;

use App\Support\TongaTime;
use PHPUnit\Framework\TestCase;

class TongaTimeTest extends TestCase
{
    public function test_it_converts_tonga_local_times_to_utc_without_daylight_saving_adjustments(): void
    {
        $this->assertSame(
            '2026-10-07 11:00:00',
            TongaTime::toUtc('2026-10-08 00:00')->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            '2026-10-11 10:59:00',
            TongaTime::toUtc('2026-10-11 23:59')->format('Y-m-d H:i:s'),
        );
    }
}
