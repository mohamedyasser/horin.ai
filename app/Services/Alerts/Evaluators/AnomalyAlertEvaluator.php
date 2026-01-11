<?php

namespace App\Services\Alerts\Evaluators;

class AnomalyAlertEvaluator implements ConditionEvaluatorInterface
{
    public function evaluateCondition(array $condition, array $currentData): EvaluationResult
    {
        $anomalies = $currentData['anomalies'] ?? [];
        $requiredTypes = $condition['anomaly_types'] ?? [];
        $minConfidence = $condition['min_confidence'] ?? 0.5;
        $requiredSeverity = $condition['severity'] ?? [];

        if (empty($anomalies)) {
            return EvaluationResult::notTriggered('No anomalies detected');
        }

        $matchingAnomalies = [];

        foreach ($anomalies as $anomaly) {
            // Check anomaly type
            $typeMatch = empty($requiredTypes) ||
                in_array($anomaly['type'] ?? '', $requiredTypes);

            // Check severity
            $severityMatch = empty($requiredSeverity) ||
                in_array($anomaly['severity'] ?? '', $requiredSeverity);

            // Check confidence
            $confidenceMatch = ($anomaly['confidence'] ?? 0) >= $minConfidence;

            if ($typeMatch && $severityMatch && $confidenceMatch) {
                $matchingAnomalies[] = $anomaly;
            }
        }

        if (empty($matchingAnomalies)) {
            return EvaluationResult::notTriggered('No matching anomalies found');
        }

        return EvaluationResult::triggered(
            value: $matchingAnomalies[0]['confidence'] ?? null,
            context: ['matching_anomalies' => $matchingAnomalies],
            reason: 'Anomaly conditions met'
        );
    }
}
