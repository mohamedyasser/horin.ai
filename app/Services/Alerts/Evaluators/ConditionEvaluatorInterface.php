<?php

namespace App\Services\Alerts\Evaluators;

interface ConditionEvaluatorInterface
{
    /**
     * Evaluate a single condition against current data
     */
    public function evaluateCondition(array $condition, array $currentData): EvaluationResult;
}
