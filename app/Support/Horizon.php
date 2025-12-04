<?php

namespace App\Support;

final class Horizon
{
    // String constants matching database values
    public const TWO_MIN = '2min';

    public const FIVE_MIN = '5min';

    public const FIFTEEN_MIN = '15min';

    public const ONE_HOUR = '1hour';

    public const ONE_DAY = '1day';

    public const ONE_WEEK = '1week';

    public const ONE_MONTH = '1month';

    public const THREE_MONTHS = '3month';

    public const SIX_MONTHS = '6month';

    public const ONE_YEAR = '1year';

    public const ALL = [
        self::TWO_MIN,
        self::FIVE_MIN,
        self::FIFTEEN_MIN,
        self::ONE_HOUR,
        self::ONE_DAY,
        self::ONE_WEEK,
        self::ONE_MONTH,
        self::THREE_MONTHS,
        self::SIX_MONTHS,
        self::ONE_YEAR,
    ];

    public const LABELS = [
        self::TWO_MIN => '2m',
        self::FIVE_MIN => '5m',
        self::FIFTEEN_MIN => '15m',
        self::ONE_HOUR => '1H',
        self::ONE_DAY => '1D',
        self::ONE_WEEK => '1W',
        self::ONE_MONTH => '1M',
        self::THREE_MONTHS => '3M',
        self::SIX_MONTHS => '6M',
        self::ONE_YEAR => '1Y',
    ];

    // Minutes for each horizon (for calculating target timestamps)
    public const MINUTES = [
        self::TWO_MIN => 2,
        self::FIVE_MIN => 5,
        self::FIFTEEN_MIN => 15,
        self::ONE_HOUR => 60,
        self::ONE_DAY => 1440,
        self::ONE_WEEK => 10080,
        self::ONE_MONTH => 43200,
        self::THREE_MONTHS => 129600,
        self::SIX_MONTHS => 259200,
        self::ONE_YEAR => 525600,
    ];

    public static function label(string $horizon): string
    {
        return self::LABELS[$horizon] ?? $horizon;
    }

    public static function minutes(string $horizon): int
    {
        return self::MINUTES[$horizon] ?? 0;
    }

    public static function options(): array
    {
        return array_map(
            fn ($value) => ['value' => $value, 'label' => self::LABELS[$value]],
            self::ALL
        );
    }
}
