<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AssetNewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'content' => $this->content,
            'image_url' => $this->image_url ? Storage::url($this->image_url) : null,
            'score' => $this->score,
            'sentiment' => $this->sentiment,
            'action' => $this->action,
            'category' => $this->category,
            'date' => $this->date?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'risks' => $this->risks ?? [],
            'opportunities' => $this->opportunities ?? [],
            'affected_sectors' => $this->affected_sectors ?? [],
            'meta_tags' => $this->meta_tags ?? [],
            'meta_description' => $this->meta_description,
            'asset' => $this->whenLoaded('asset', fn () => [
                'id' => $this->asset->id,
                'symbol' => $this->asset->symbol,
                'name' => $this->asset->name,
                'name_ar' => $this->asset->name_ar,
            ]),
            'market' => $this->whenLoaded('market', fn () => [
                'id' => $this->market->id,
                'code' => $this->market->code,
                'name' => $this->market->name,
            ]),
            'sector' => $this->whenLoaded('sector', fn () => [
                'id' => $this->sector->id,
                'name' => $this->sector->name,
            ]),
            'country' => $this->whenLoaded('country', fn () => [
                'id' => $this->country->id,
                'name' => $this->country->name,
                'code' => $this->country->code,
            ]),
            'bookmarked' => $user ? $this->bookmarks()->where('user_id', $user->id)->exists() : false,
            'user_rating' => $this->when($user, function () use ($user) {
                $rating = $this->ratings()->where('user_id', $user->id)->first();

                return $rating?->helpful;
            }),
            'ratings_summary' => [
                'helpful_count' => $this->ratings()->where('helpful', true)->count(),
                'not_helpful_count' => $this->ratings()->where('helpful', false)->count(),
            ],
            'comments_count' => $this->comments()->count(),
        ];
    }
}
