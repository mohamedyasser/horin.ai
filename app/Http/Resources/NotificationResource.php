<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $locale = $request->user()?->language ?? 'en';

        return [
            'id' => $this->id,
            'type' => $this->type,
            'channel' => $this->channel,
            'priority' => $this->priority,
            'title' => $locale === 'ar' ? $this->title_ar : $this->title,
            'body' => $locale === 'ar' ? $this->body_ar : $this->body,
            'data' => $this->data,
            'status' => $this->status,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
