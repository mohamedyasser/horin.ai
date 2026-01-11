<?php

namespace App\Services\Alerts\Evaluators;

class PredictionAlertEvaluator implements ConditionEvaluatorInterface
{
    public function evaluateCondition(array $condition, array $currentData): EvaluationResult
    {
        $predictions = $currentData['predictions'] ?? [];
        $requiredDirection = $condition['direction'] ?? null;
        $minConfidence = $condition['min_confidence'] ?? 0.5;
        $predictionType = $condition['prediction_type'] ?? null;
        $horizon = $condition['horizon'] ?? null;

        if (empty($predictions)) {
            return EvaluationResult::notTriggered('No predictions available');
        }

        foreach ($predictions as $prediction) {
            // Check prediction type if specified
            if ($predictionType && ($prediction['type'] ?? null) !== $predictionType) {
                continue;
            }

            // Check horizon if specified
            if ($horizon && ($prediction['horizon'] ?? null) !== $horizon) {
                continue;
            }

            // Check direction
            $directionMatch = ! $requiredDirection ||
                ($prediction['direction'] ?? null) === $requiredDirection;

            // Check confidence
            $confidenceMatch = ($prediction['confidence'] ?? 0) >= $minConfidence;

            if ($directionMatch && $confidenceMatch) {
                return EvaluationResult::triggered(
                    value: $prediction['confidence'] ?? null,
                    context: ['prediction' => $prediction],
                    reason: 'Prediction conditions met'
                );
            }
        }

        return EvaluationResult::notTriggered('No matching predictions found');
    }
}
