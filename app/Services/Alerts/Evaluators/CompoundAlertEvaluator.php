<?php

namespace App\Services\Alerts\Evaluators;

use App\Models\Alert;
use Illuminate\Support\Collection;

class CompoundAlertEvaluator
{
    /**
     * @var array<string, ConditionEvaluatorInterface>
     */
    private array $evaluators = [];

    public function __construct(
        private readonly SignalAlertEvaluator $signalEvaluator,
        private readonly PredictionAlertEvaluator $predictionEvaluator,
        private readonly PatternAlertEvaluator $patternEvaluator,
        private readonly AnomalyAlertEvaluator $anomalyEvaluator
    ) {
        $this->evaluators = [
            'signal' => $this->signalEvaluator,
            'prediction' => $this->predictionEvaluator,
            'pattern' => $this->patternEvaluator,
            'anomaly' => $this->anomalyEvaluator,
        ];
    }

    public function evaluate(Alert $alert, array $currentData): EvaluationResult
    {
        $conditions = $alert->parameters['conditions'] ?? [];
        $logic = $alert->condition_logic;

        if (empty($conditions)) {
            return EvaluationResult::notTriggered('No conditions defined');
        }

        $results = $this->evaluateConditions($conditions, $currentData);

        return match ($logic) {
            'and' => $this->evaluateAnd($results),
            'or' => $this->evaluateOr($results),
            default => EvaluationResult::notTriggered('Unknown logic'),
        };
    }

    /**
     * @return Collection<int, array{condition_id: string|null, type: string, triggered: bool, value: mixed, reason: string|null}>
     */
    private function evaluateConditions(array $conditions, array $currentData): Collection
    {
        return collect($conditions)->map(function ($condition) use ($currentData) {
            $type = $condition['type'];
            $evaluator = $this->evaluators[$type] ?? null;

            if (! $evaluator) {
                return [
                    'condition_id' => $condition['id'] ?? null,
                    'type' => $type,
                    'triggered' => false,
                    'value' => null,
                    'reason' => 'Unknown condition type',
                ];
            }

            $result = $evaluator->evaluateCondition($condition, $currentData);

            return [
                'condition_id' => $condition['id'] ?? null,
                'type' => $type,
                'triggered' => $result->triggered,
                'value' => $result->value,
                'reason' => $result->reason,
            ];
        });
    }

    private function evaluateAnd(Collection $results): EvaluationResult
    {
        $allTriggered = $results->every('triggered');

        if ($allTriggered) {
            return EvaluationResult::triggered(
                value: null,
                context: ['conditions' => $results->toArray()],
                reason: 'All conditions met'
            );
        }

        $failed = $results->firstWhere('triggered', false);

        return EvaluationResult::notTriggered(
            "Condition {$failed['type']} not met: {$failed['reason']}"
        );
    }

    private function evaluateOr(Collection $results): EvaluationResult
    {
        $anyTriggered = $results->contains('triggered', true);

        if ($anyTriggered) {
            $triggered = $results->filter(fn ($r) => $r['triggered']);

            return EvaluationResult::triggered(
                value: null,
                context: ['triggered_conditions' => $triggered->toArray()],
                reason: 'One or more conditions met'
            );
        }

        return EvaluationResult::notTriggered('No conditions met');
    }
}
