<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\LatestAssetPrice;
use Illuminate\Support\Facades\Cache;

class AlertMatcher
{
    public function __construct(
        private AlertScopeResolver $scopeResolver
    ) {}

    /**
     * Evaluate a price-based alert.
     */
    public function evaluatePriceAlert(Alert $alert, LatestAssetPrice $price): object
    {
        return match ($alert->trigger_type) {
            'target_price' => $this->evaluateTargetPrice($alert, $price),
            'breakout' => $this->evaluateBreakout($alert, $price),
            'zone' => $this->evaluateZone($alert, $price),
            'daily_change' => $this->evaluateDailyChange($alert, $price),
            '52week' => $this->evaluate52Week($alert, $price),
            'gap' => $this->evaluateGap($alert, $price),
            'entry_return' => $this->evaluateEntryReturn($alert, $price),
            default => $this->noTrigger(),
        };
    }

    /**
     * Evaluate a price alert from real-time signal data (not LatestAssetPrice).
     * Used by ProcessIntelligenceAlerts when receiving price_updates channel.
     */
    public function evaluatePriceAlertFromSignal(
        Alert $alert,
        float $currentPrice,
        ?float $previousPrice,
        array $signalData
    ): object {
        return match ($alert->trigger_type) {
            'target_price' => $this->evaluateTargetPriceFromSignal($alert, $currentPrice, $signalData),
            'breakout' => $this->evaluateBreakoutFromSignal($alert, $currentPrice, $signalData),
            'zone' => $this->evaluateZoneFromSignal($alert, $currentPrice, $previousPrice, $signalData),
            'daily_change' => $this->evaluateDailyChangeFromSignal($alert, $currentPrice, $previousPrice, $signalData),
            default => $this->noTrigger(),
        };
    }

    private function evaluateTargetPriceFromSignal(Alert $alert, float $currentPrice, array $signalData): object
    {
        $params = $alert->parameters;
        $target = $params['target_price'];
        $direction = $params['direction'] ?? $alert->direction ?? 'above';

        $triggered = match ($direction) {
            'above' => $currentPrice >= $target,
            'below' => $currentPrice <= $target,
            'both' => $currentPrice >= $target || $currentPrice <= $target,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'target' => $target,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'change_percent' => $signalData['changePercent'] ?? $signalData['change_percent'] ?? null,
            ],
        ];
    }

    private function evaluateBreakoutFromSignal(Alert $alert, float $currentPrice, array $signalData): object
    {
        $params = $alert->parameters;
        $level = $params['level'];
        $direction = $params['direction'] ?? 'above';

        $triggered = match ($direction) {
            'above' => $currentPrice > $level,
            'below' => $currentPrice < $level,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'level' => $level,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'volume' => $signalData['volume'] ?? null,
            ],
        ];
    }

    private function evaluateZoneFromSignal(
        Alert $alert,
        float $currentPrice,
        ?float $previousPrice,
        array $signalData
    ): object {
        $params = $alert->parameters;
        $zoneLow = $params['zone_low'];
        $zoneHigh = $params['zone_high'];
        $triggerOn = $params['trigger_on'] ?? 'enter';

        $isInZone = $currentPrice >= $zoneLow && $currentPrice <= $zoneHigh;

        // Use previous price for enter/exit detection
        $wasInZone = $previousPrice !== null
            ? ($previousPrice >= $zoneLow && $previousPrice <= $zoneHigh)
            : null;

        $triggered = match ($triggerOn) {
            'enter' => $isInZone && ($wasInZone === false || $wasInZone === null),
            'exit' => ! $isInZone && ($wasInZone === true),
            'both' => ($isInZone && $wasInZone === false) || (! $isInZone && $wasInZone === true),
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'zone_low' => $zoneLow,
                'zone_high' => $zoneHigh,
                'is_in_zone' => $isInZone,
                'was_in_zone' => $wasInZone,
                'trigger_on' => $triggerOn,
                'event' => $isInZone ? 'entered' : 'exited',
            ],
        ];
    }

    private function evaluateDailyChangeFromSignal(
        Alert $alert,
        float $currentPrice,
        ?float $previousPrice,
        array $signalData
    ): object {
        $params = $alert->parameters;
        $threshold = $params['threshold_percent'];
        $direction = $params['direction'] ?? 'both';

        // Try to get change percent from signal data or calculate it
        $changePercent = $signalData['changePercent']
            ?? $signalData['change_percent']
            ?? null;

        if ($changePercent === null && $previousPrice !== null && $previousPrice > 0) {
            $changePercent = (($currentPrice - $previousPrice) / $previousPrice) * 100;
        }

        if ($changePercent === null) {
            return $this->noTrigger();
        }

        $absChange = abs($changePercent);
        $triggered = $absChange >= $threshold;

        if ($triggered && $direction !== 'both') {
            $triggered = match ($direction) {
                'up' => $changePercent > 0,
                'down' => $changePercent < 0,
                default => true,
            };
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $changePercent,
            'context' => [
                'threshold' => $threshold,
                'direction' => $direction,
                'actual_change' => $changePercent,
                'current_price' => $currentPrice,
                'previous_price' => $previousPrice,
            ],
        ];
    }

    /**
     * Evaluate an intelligence-based alert.
     */
    public function evaluateIntelligenceAlert(Alert $alert, array $signalData): object
    {
        return match ($alert->trigger_type) {
            'signal' => $this->evaluateSignal($alert, $signalData),
            'anomaly' => $this->evaluateAnomaly($alert, $signalData),
            'pattern' => $this->evaluatePattern($alert, $signalData),
            'recommendation' => $this->evaluateRecommendation($alert, $signalData),
            'prediction' => $this->evaluatePrediction($alert, $signalData),
            'compound_intelligence' => $this->evaluateCompound($alert, $signalData),
            default => $this->noTrigger(),
        };
    }

    private function evaluateTargetPrice(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $target = $params['target_price'];
        $direction = $params['direction'] ?? $alert->direction ?? 'above';
        $currentPrice = $price->price;

        $triggered = match ($direction) {
            'above' => $currentPrice >= $target,
            'below' => $currentPrice <= $target,
            'both' => $currentPrice >= $target || $currentPrice <= $target,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'target' => $target,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'change_percent' => $price->change_percent,
            ],
        ];
    }

    private function evaluateBreakout(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $level = $params['level'];
        $direction = $params['direction'] ?? 'above';
        $currentPrice = $price->price;

        $triggered = match ($direction) {
            'above' => $currentPrice > $level,
            'below' => $currentPrice < $level,
            default => false,
        };

        // Optional: Check confirmation (sustained above/below)
        if ($triggered && ($params['confirmation'] ?? null) === 'sustained') {
            // Would need historical tick data to confirm
            // For now, trust the current price
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'level' => $level,
                'direction' => $direction,
                'current_price' => $currentPrice,
                'volume' => $price->volume,
            ],
        ];
    }

    private function evaluateZone(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $zoneLow = $params['zone_low'];
        $zoneHigh = $params['zone_high'];
        $triggerOn = $params['trigger_on'] ?? 'enter';
        $currentPrice = $price->price;

        $isInZone = $currentPrice >= $zoneLow && $currentPrice <= $zoneHigh;

        // Track zone state using cache to detect enter/exit transitions
        $cacheKey = "alert:{$alert->id}:zone_state";
        $wasInZone = Cache::get($cacheKey);

        // Determine if state changed
        $stateChanged = $wasInZone !== null && $wasInZone !== $isInZone;
        $entering = $isInZone && $wasInZone === false;
        $exiting = ! $isInZone && $wasInZone === true;

        $triggered = match ($triggerOn) {
            'enter' => $entering || ($wasInZone === null && $isInZone),
            'exit' => $exiting,
            'both' => $entering || $exiting,
            default => false,
        };

        // Update cache with current state (TTL: 24 hours)
        Cache::put($cacheKey, $isInZone, 86400);

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'zone_low' => $zoneLow,
                'zone_high' => $zoneHigh,
                'is_in_zone' => $isInZone,
                'was_in_zone' => $wasInZone,
                'trigger_on' => $triggerOn,
                'event' => $entering ? 'entered' : ($exiting ? 'exited' : 'unchanged'),
            ],
        ];
    }

    private function evaluateDailyChange(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $threshold = $params['threshold_percent'];
        $direction = $params['direction'] ?? 'both';
        $changePercent = abs($price->change_percent);

        $triggered = $changePercent >= $threshold;

        if ($triggered && $direction !== 'both') {
            $triggered = match ($direction) {
                'up' => $price->change_percent > 0,
                'down' => $price->change_percent < 0,
                default => true,
            };
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $price->change_percent,
            'context' => [
                'threshold' => $threshold,
                'direction' => $direction,
                'actual_change' => $price->change_percent,
                'current_price' => $price->price,
                'open_price' => $price->open,
            ],
        ];
    }

    private function evaluate52Week(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $type = $params['type'] ?? 'high';

        // Would need 52-week high/low data from asset or separate table
        $high52w = $price->high_52w ?? null;
        $low52w = $price->low_52w ?? null;
        $currentPrice = $price->price;

        $triggered = match ($type) {
            'high' => $high52w && $currentPrice >= $high52w,
            'low' => $low52w && $currentPrice <= $low52w,
            'both' => ($high52w && $currentPrice >= $high52w) || ($low52w && $currentPrice <= $low52w),
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'type' => $type,
                'high_52w' => $high52w,
                'low_52w' => $low52w,
                'current_price' => $currentPrice,
            ],
        ];
    }

    private function evaluateGap(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $threshold = $params['gap_threshold_percent'];
        $direction = $params['direction'] ?? 'both';

        $gapPercent = (($price->open - $price->prev_close) / $price->prev_close) * 100;
        $absGap = abs($gapPercent);

        $triggered = $absGap >= $threshold;

        if ($triggered && $direction !== 'both') {
            $triggered = match ($direction) {
                'up' => $gapPercent > 0,
                'down' => $gapPercent < 0,
                default => true,
            };
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $gapPercent,
            'context' => [
                'threshold' => $threshold,
                'direction' => $direction,
                'gap_percent' => $gapPercent,
                'open' => $price->open,
                'prev_close' => $price->prev_close,
            ],
        ];
    }

    private function evaluateEntryReturn(Alert $alert, LatestAssetPrice $price): object
    {
        $params = $alert->parameters;
        $entryPrice = $params['entry_price'] ?? $this->scopeResolver->getEntryPrice($alert, $price->asset_id);
        $tolerance = $params['tolerance_percent'] ?? 0.5;

        if (! $entryPrice) {
            return $this->noTrigger();
        }

        $currentPrice = $price->price;
        $diffPercent = abs(($currentPrice - $entryPrice) / $entryPrice) * 100;

        $triggered = $diffPercent <= $tolerance;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => [
                'entry_price' => $entryPrice,
                'tolerance' => $tolerance,
                'current_price' => $currentPrice,
                'diff_percent' => $diffPercent,
            ],
        ];
    }

    private function evaluateSignal(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredIndicators = $params['indicators'] ?? [];
        $requiredSignalTypes = $params['signal_types'] ?? [];
        $minStrength = $params['min_strength'] ?? 0.7;

        $signal = $signalData['original_signal'] ?? $signalData;
        $indicator = $signal['indicator'] ?? '';
        $signalType = $signal['signal_type'] ?? '';
        $strength = $signal['strength'] ?? 0;

        // Check indicator match
        $indicatorMatch = empty($requiredIndicators) || in_array($indicator, $requiredIndicators);

        // Check signal type match
        $signalTypeMatch = empty($requiredSignalTypes) || in_array($signalType, $requiredSignalTypes);

        // Check strength
        $strengthMatch = $strength >= $minStrength;

        $triggered = $indicatorMatch && $signalTypeMatch && $strengthMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $strength,
            'context' => [
                'indicator' => $indicator,
                'signal_type' => $signalType,
                'strength' => $strength,
                'confidence' => $signal['confidence'] ?? null,
                'price' => $signal['price'] ?? null,
            ],
        ];
    }

    private function evaluateAnomaly(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredTypes = $params['anomaly_types'] ?? [];
        $minConfidence = $params['min_confidence'] ?? 0.8;
        $severityLevels = $params['severity'] ?? ['high', 'critical'];

        $anomalyTypes = $signalData['types'] ?? [];
        $score = $signalData['score'] ?? 0;

        // Check type match
        $typeMatch = empty($requiredTypes) || count(array_intersect($requiredTypes, $anomalyTypes)) > 0;

        // Check confidence/score
        $scoreMatch = $score >= $minConfidence;

        // Determine severity
        $severity = match (true) {
            $score >= 0.9 => 'critical',
            $score >= 0.7 => 'high',
            $score >= 0.5 => 'medium',
            default => 'low',
        };
        $severityMatch = in_array($severity, $severityLevels);

        $triggered = $typeMatch && $scoreMatch && $severityMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $score,
            'context' => [
                'anomaly_types' => $anomalyTypes,
                'score' => $score,
                'severity' => $severity,
                'reasons' => $signalData['reasons'] ?? [],
                'price' => $signalData['price'] ?? null,
            ],
        ];
    }

    private function evaluatePattern(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $requiredPatterns = $params['patterns'] ?? [];
        $minConfidence = $params['min_confidence'] ?? 0.7;
        $patternStatus = $params['pattern_status'] ?? 'confirmed';

        $patterns = $signalData['patterns'] ?? [];

        foreach ($patterns as $pattern) {
            $typeMatch = empty($requiredPatterns) || in_array($pattern['type'], $requiredPatterns);
            $confidenceMatch = ($pattern['confidence'] ?? 0) >= $minConfidence;
            $statusMatch = $patternStatus === 'forming' || ($pattern['metadata']['breakout_confirmed'] ?? false);

            if ($typeMatch && $confidenceMatch && $statusMatch) {
                return (object) [
                    'triggered' => true,
                    'triggerValue' => $pattern['confidence'],
                    'context' => [
                        'pattern_type' => $pattern['type'],
                        'confidence' => $pattern['confidence'],
                        'support' => $pattern['support'] ?? null,
                        'resistance' => $pattern['resistance'] ?? null,
                        'target' => $pattern['target'] ?? null,
                    ],
                ];
            }
        }

        return $this->noTrigger();
    }

    private function evaluateRecommendation(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $triggerOn = $params['trigger_on'] ?? 'change';
        $targetRecs = $params['recommendations'] ?? ['strong_buy', 'buy'];
        $minScore = $params['min_score'] ?? 0.75;

        $action = strtolower($signalData['action'] ?? '');
        $score = $signalData['score'] ?? 0;
        $previousAction = strtolower($signalData['previous_action'] ?? '');

        $actionMatch = in_array($action, array_map('strtolower', $targetRecs));
        $scoreMatch = $score >= $minScore;
        $isChange = $action !== $previousAction;

        $triggered = match ($triggerOn) {
            'change' => $actionMatch && $scoreMatch && $isChange,
            'any' => $actionMatch && $scoreMatch,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $score,
            'context' => [
                'action' => $action,
                'previous_action' => $previousAction,
                'score' => $score,
                'is_upgrade' => $this->isUpgrade($previousAction, $action),
            ],
        ];
    }

    private function evaluatePrediction(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $direction = $params['direction'] ?? 'up';
        $minConfidence = $params['min_confidence'] ?? 0.75;

        $predictedDirection = $signalData['direction'] ?? '';
        $confidence = $signalData['confidence'] ?? 0;

        $directionMatch = $direction === 'both' || $predictedDirection === $direction;
        $confidenceMatch = $confidence >= $minConfidence;

        $triggered = $directionMatch && $confidenceMatch;

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $confidence,
            'context' => [
                'predicted_direction' => $predictedDirection,
                'confidence' => $confidence,
                'horizon' => $signalData['horizon'] ?? null,
                'expected_change' => $signalData['expected_change'] ?? null,
            ],
        ];
    }

    /**
     * Evaluate a compound alert with AND/OR logic for multiple conditions.
     *
     * Parameters structure:
     * - logic: 'and' | 'or' (default: 'and')
     * - conditions: array of conditions, each with:
     *   - type: 'price' | 'signal' | 'anomaly' | 'pattern' | 'recommendation' | 'prediction'
     *   - parameters: condition-specific parameters
     */
    private function evaluateCompound(Alert $alert, array $signalData): object
    {
        $params = $alert->parameters;
        $logic = $params['logic'] ?? 'and';
        $conditions = $params['conditions'] ?? [];

        if (empty($conditions)) {
            return $this->noTrigger();
        }

        $results = [];
        $allTriggered = true;
        $anyTriggered = false;

        foreach ($conditions as $index => $condition) {
            $conditionType = $condition['type'] ?? 'signal';
            $conditionParams = $condition['parameters'] ?? [];

            $result = $this->evaluateSingleCondition($conditionType, $conditionParams, $signalData);
            $results[$index] = [
                'type' => $conditionType,
                'triggered' => $result->triggered,
                'value' => $result->triggerValue,
                'context' => $result->context,
            ];

            if ($result->triggered) {
                $anyTriggered = true;
            } else {
                $allTriggered = false;
            }
        }

        $triggered = match ($logic) {
            'and' => $allTriggered,
            'or' => $anyTriggered,
            default => false,
        };

        // Determine overall trigger value (use first triggered condition's value)
        $triggerValue = null;
        foreach ($results as $result) {
            if ($result['triggered'] && $result['value'] !== null) {
                $triggerValue = $result['value'];
                break;
            }
        }

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $triggerValue,
            'context' => [
                'logic' => $logic,
                'conditions_count' => count($conditions),
                'triggered_count' => count(array_filter($results, fn ($r) => $r['triggered'])),
                'condition_results' => $results,
            ],
        ];
    }

    /**
     * Evaluate a single condition within a compound alert.
     */
    private function evaluateSingleCondition(string $type, array $params, array $signalData): object
    {
        return match ($type) {
            'signal' => $this->evaluateSignalCondition($params, $signalData),
            'anomaly' => $this->evaluateAnomalyCondition($params, $signalData),
            'pattern' => $this->evaluatePatternCondition($params, $signalData),
            'recommendation' => $this->evaluateRecommendationCondition($params, $signalData),
            'prediction' => $this->evaluatePredictionCondition($params, $signalData),
            'price_threshold' => $this->evaluatePriceThresholdCondition($params, $signalData),
            default => $this->noTrigger(),
        };
    }

    private function evaluateSignalCondition(array $params, array $signalData): object
    {
        $requiredIndicators = $params['indicators'] ?? [];
        $requiredSignalTypes = $params['signal_types'] ?? [];
        $minStrength = $params['min_strength'] ?? 0.7;

        $signal = $signalData['original_signal'] ?? $signalData;
        $indicator = $signal['indicator'] ?? '';
        $signalType = $signal['signal_type'] ?? '';
        $strength = $signal['strength'] ?? 0;

        $indicatorMatch = empty($requiredIndicators) || in_array($indicator, $requiredIndicators);
        $signalTypeMatch = empty($requiredSignalTypes) || in_array($signalType, $requiredSignalTypes);
        $strengthMatch = $strength >= $minStrength;

        return (object) [
            'triggered' => $indicatorMatch && $signalTypeMatch && $strengthMatch,
            'triggerValue' => $strength,
            'context' => ['indicator' => $indicator, 'signal_type' => $signalType, 'strength' => $strength],
        ];
    }

    private function evaluateAnomalyCondition(array $params, array $signalData): object
    {
        $requiredTypes = $params['anomaly_types'] ?? [];
        $minScore = $params['min_score'] ?? 0.8;

        $anomalyTypes = $signalData['types'] ?? [];
        $score = $signalData['score'] ?? 0;

        $typeMatch = empty($requiredTypes) || count(array_intersect($requiredTypes, $anomalyTypes)) > 0;
        $scoreMatch = $score >= $minScore;

        return (object) [
            'triggered' => $typeMatch && $scoreMatch,
            'triggerValue' => $score,
            'context' => ['anomaly_types' => $anomalyTypes, 'score' => $score],
        ];
    }

    private function evaluatePatternCondition(array $params, array $signalData): object
    {
        $requiredPatterns = $params['patterns'] ?? [];
        $minConfidence = $params['min_confidence'] ?? 0.7;

        $patterns = $signalData['patterns'] ?? [];

        foreach ($patterns as $pattern) {
            $typeMatch = empty($requiredPatterns) || in_array($pattern['type'], $requiredPatterns);
            $confidenceMatch = ($pattern['confidence'] ?? 0) >= $minConfidence;

            if ($typeMatch && $confidenceMatch) {
                return (object) [
                    'triggered' => true,
                    'triggerValue' => $pattern['confidence'],
                    'context' => ['pattern_type' => $pattern['type'], 'confidence' => $pattern['confidence']],
                ];
            }
        }

        return $this->noTrigger();
    }

    private function evaluateRecommendationCondition(array $params, array $signalData): object
    {
        $targetRecs = $params['recommendations'] ?? ['strong_buy', 'buy'];
        $minScore = $params['min_score'] ?? 0.75;

        $action = strtolower($signalData['action'] ?? '');
        $score = $signalData['score'] ?? 0;

        $actionMatch = in_array($action, array_map('strtolower', $targetRecs));
        $scoreMatch = $score >= $minScore;

        return (object) [
            'triggered' => $actionMatch && $scoreMatch,
            'triggerValue' => $score,
            'context' => ['action' => $action, 'score' => $score],
        ];
    }

    private function evaluatePredictionCondition(array $params, array $signalData): object
    {
        $direction = $params['direction'] ?? 'up';
        $minConfidence = $params['min_confidence'] ?? 0.75;

        $predictedDirection = $signalData['direction'] ?? '';
        $confidence = $signalData['confidence'] ?? 0;

        $directionMatch = $direction === 'both' || $predictedDirection === $direction;
        $confidenceMatch = $confidence >= $minConfidence;

        return (object) [
            'triggered' => $directionMatch && $confidenceMatch,
            'triggerValue' => $confidence,
            'context' => ['predicted_direction' => $predictedDirection, 'confidence' => $confidence],
        ];
    }

    private function evaluatePriceThresholdCondition(array $params, array $signalData): object
    {
        $threshold = $params['threshold'] ?? null;
        $direction = $params['direction'] ?? 'above';

        $currentPrice = $signalData['last'] ?? $signalData['price'] ?? null;

        if ($threshold === null || $currentPrice === null) {
            return $this->noTrigger();
        }

        $triggered = match ($direction) {
            'above' => $currentPrice >= $threshold,
            'below' => $currentPrice <= $threshold,
            default => false,
        };

        return (object) [
            'triggered' => $triggered,
            'triggerValue' => $currentPrice,
            'context' => ['threshold' => $threshold, 'direction' => $direction, 'current_price' => $currentPrice],
        ];
    }

    private function noTrigger(): object
    {
        return (object) [
            'triggered' => false,
            'triggerValue' => null,
            'context' => [],
        ];
    }

    private function isUpgrade(string $from, string $to): bool
    {
        $rankings = ['strong_sell' => 1, 'sell' => 2, 'hold' => 3, 'buy' => 4, 'strong_buy' => 5];

        return ($rankings[$to] ?? 0) > ($rankings[$from] ?? 0);
    }
}
