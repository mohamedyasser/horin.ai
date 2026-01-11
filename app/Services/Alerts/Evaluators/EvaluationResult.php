<?php

namespace App\Services\Alerts\Evaluators;

class EvaluationResult
{
    public function __construct(
        public readonly bool $triggered,
        public readonly mixed $value = null,
        public readonly array $context = [],
        public readonly ?string $reason = null
    ) {}

    public static function triggered(
        mixed $value = null,
        array $context = [],
        ?string $reason = null
    ): self {
        return new self(true, $value, $context, $reason);
    }

    public static function notTriggered(?string $reason = null): self
    {
        return new self(false, null, [], $reason);
    }
}
