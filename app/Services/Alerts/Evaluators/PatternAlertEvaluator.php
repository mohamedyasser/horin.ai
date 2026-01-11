<?php

namespace App\Services\Alerts\Evaluators;

class PatternAlertEvaluator implements ConditionEvaluatorInterface
{
    public function evaluateCondition(array $condition, array $currentData): EvaluationResult
    {
        $patterns = $currentData['patterns'] ?? [];
        $requiredPatterns = $condition['patterns'] ?? [];
        $requiredStatus = $condition['pattern_status'] ?? 'confirmed';
        $minConfidence = $condition['min_confidence'] ?? 0.5;

        if (empty($patterns)) {
            return EvaluationResult::notTriggered('No patterns available');
        }

        $matchingPatterns = [];

        foreach ($patterns as $pattern) {
            // Check if pattern type matches
            $patternMatch = empty($requiredPatterns) ||
                in_array($pattern['pattern_type'] ?? '', $requiredPatterns);

            // Check status
            $statusMatch = ($pattern['status'] ?? '') === $requiredStatus;

            // Check confidence
            $confidenceMatch = ($pattern['confidence'] ?? 0) >= $minConfidence;

            if ($patternMatch && $statusMatch && $confidenceMatch) {
                $matchingPatterns[] = $pattern;
            }
        }

        if (empty($matchingPatterns)) {
            return EvaluationResult::notTriggered('No matching patterns found');
        }

        return EvaluationResult::triggered(
            value: $matchingPatterns[0]['confidence'] ?? null,
            context: ['matching_patterns' => $matchingPatterns],
            reason: 'Pattern conditions met'
        );
    }
}
