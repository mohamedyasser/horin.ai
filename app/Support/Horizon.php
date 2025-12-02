<?php

namespace App\Support;

final class Horizon
{
    public const ONE_DAY = 1440;

    public const ONE_WEEK = 10080;

    public const ONE_MONTH = 43200;

    public const THREE_MONTHS = 129600;

    public const ALL = [
        self::ONE_DAY,
        self::ONE_WEEK,
        self::ONE_MONTH,
        self::THREE_MONTHS,
    ];

    public const LABELS = [
        self::ONE_DAY => '1D',
        self::ONE_WEEK => '1W',
        self::ONE_MONTH => '1M',
        self::THREE_MONTHS => '3M',
    ];

    public static function label(int $minutes): string
    {
        return self::LABELS[$minutes] ?? "{$minutes}m";
    }

    public static function options(): array
    {
        return array_map(
            fn ($value) => ['value' => $value, 'label' => self::LABELS[$value]],
            self::ALL
        );
    }
}
