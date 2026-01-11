<?php

namespace App\Events;

use App\Models\AlertNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AlertTriggered implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public AlertNotification $notification
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("user.{$this->notification->user_id}.alerts"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'alert.triggered';
    }

    public function broadcastWith(): array
    {
        $alert = $this->notification->alert;
        $asset = $alert?->asset;

        return [
            'id' => $this->notification->id,
            'type' => $this->notification->type,
            'priority' => $this->notification->priority,
            'title' => $this->notification->title,
            'title_ar' => $this->notification->title_ar,
            'body' => $this->notification->body,
            'body_ar' => $this->notification->body_ar,
            'data' => $this->notification->data,
            'asset' => $asset ? [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'name_ar' => $asset->name_ar,
            ] : null,
            'created_at' => $this->notification->created_at->toISOString(),
        ];
    }

    public function broadcastQueue(): string
    {
        return 'broadcasts';
    }
}
