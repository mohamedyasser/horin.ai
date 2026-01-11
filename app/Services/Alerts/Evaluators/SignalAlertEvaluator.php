<?php

namespace App\Services\Alerts\Evaluators;

class SignalAlertEvaluator implements ConditionEvaluatorInterface
{
    public function evaluateCondition(array $condition, array $currentData): EvaluationResult
    {
        $signals = $currentData['signals'] ?? [];
        $requiredIndicators = $condition['indicators'] ?? [];
        $requiredSignalTypes = $condition['signal_types'] ?? [];
        $minStrength = $condition['min_strength'] ?? 0.5;
        $anyOrAll = $condition['any_or_all'] ?? 'any';

        if (empty($signals)) {
            return EvaluationResult::notTriggered('No signals available');
        }

        $matchingSignals = [];

        foreach ($signals as $signal) {
            $indicatorMatch = empty($requiredIndicators) ||
                in_array($signal['indicator'] ?? '', $requiredIndicators);

            $signalTypeMatch = empty($requiredSignalTypes) ||
                in_array($signal['signal_type'] ?? '', $requiredSignalTypes);

            $strengthMatch = ($signal['strength'] ?? 0) >= $minStrength;

            if ($indicatorMatch && $signalTypeMatch && $strengthMatch) {
                $matchingSignals[] = $signal;
            }
        }

        if (empty($matchingSignals)) {
            return EvaluationResult::notTriggered('No matching signals found');
        }

        if ($anyOrAll === 'all') {
            // Check if all required indicators have matching signals
            $matchedIndicators = array_unique(array_column($matchingSignals, 'indicator'));
            if (count($matchedIndicators) < count($requiredIndicators)) {
                return EvaluationResult::notTriggered('Not all required indicators matched');
            }
        }

        return EvaluationResult::triggered(
            value: $matchingSignals[0]['strength'] ?? null,
            context: ['matching_signals' => $matchingSignals],
            reason: 'Signal conditions met'
        );
    }
}
