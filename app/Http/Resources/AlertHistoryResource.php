<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AlertHistoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alert_id' => $this->alert_id,
            'alert' => $this->whenLoaded('alert', fn () => [
                'type' => $this->alert->type,
                'trigger_type' => $this->alert->trigger_type,
                'parameters' => $this->alert->parameters,
            ]),
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
                'name_ar' => $this->asset->name_ar,
            ]),
            'triggered_at' => $this->triggered_at->toISOString(),
            'trigger_value' => $this->trigger_value,
            'trigger_context' => $this->trigger_context,
            'notification_sent' => $this->notification_sent,
            'acknowledged_at' => $this->acknowledged_at?->toISOString(),
            'escalation_level' => $this->escalation_level,
        ];
    }
}
