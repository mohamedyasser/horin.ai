<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
                'name_ar' => $this->asset->name_ar,
                'last_price' => $this->asset->cachedPrice?->price,
            ]),
            'template' => $this->whenLoaded('template', fn () => [
                'id' => $this->template->id,
                'name' => $this->template->name,
            ]),
            'type' => $this->type,
            'trigger_type' => $this->trigger_type,
            'scope' => $this->scope,
            'direction' => $this->direction,
            'condition_logic' => $this->condition_logic,
            'parameters' => $this->parameters,
            'status' => $this->status,
            'priority' => $this->priority,
            'is_recurring' => $this->is_recurring,
            'cooldown_minutes' => $this->cooldown_minutes,
            'max_triggers' => $this->max_triggers,
            'triggered_count' => $this->triggered_count,
            'delivery_config' => $this->delivery_config,
            'escalation_config' => $this->escalation_config,
            'snoozed_until' => $this->snoozed_until?->toISOString(),
            'is_snoozed' => $this->isSnoozed(),
            'last_triggered_at' => $this->last_triggered_at?->toISOString(),
            'expires_at' => $this->expires_at?->toISOString(),
            'market_hours_only' => $this->market_hours_only,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'history' => AlertHistoryResource::collection($this->whenLoaded('history')),
        ];
    }
}
