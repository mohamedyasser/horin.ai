<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

class AssetNewCollectionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $cdnUrl = config('services.cdn.news');

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => Str::limit($this->description, 200),
            'image_url' => $this->image_url ? "{$cdnUrl}/{$this->image_url}" : null,
            'score' => $this->score,
            'sentiment' => $this->sentiment,
            'action' => $this->action,
            'category' => $this->category,
            'date' => $this->date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
            ]),
            'market' => $this->whenLoaded('market', fn () => [
                'id' => $this->market->id,
                'code' => $this->market->code,
            ]),
            'bookmarked' => $user ? $this->bookmarks()->where('user_id', $user->id)->exists() : false,
        ];
    }
}
