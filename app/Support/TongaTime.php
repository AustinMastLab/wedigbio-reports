<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class TongaTime
{
    public static function toUtc(mixed $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value)->setTimezone('Pacific/Tongatapu')->utc();
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return CarbonImmutable::parse($value, 'Pacific/Tongatapu')->utc();
    }
}
