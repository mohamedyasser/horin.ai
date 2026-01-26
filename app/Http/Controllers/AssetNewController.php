<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsFilterRequest;
use App\Models\AssetNew;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use App\Support\PaginationHelper;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class AssetNewController extends Controller
{
    public function index(string $locale, NewsFilterRequest $request): Response
    {
        $filters = $request->validated();
        $perPage = $filters['per_page'] ?? 12;

        // Get featured news (highest score)
        $featured = AssetNew::query()
            ->where('is_rewritten', true)
            ->whereNotNull('image_url')
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->with(['asset', 'market', 'sector', 'country'])
            ->first();

        // Get paginated news with filters
        $newsQuery = $this->buildFilteredQuery($filters);

        // Exclude featured from the main list
        if ($featured) {
            $newsQuery->where('id', '!=', $featured->id);
        }

        $news = $newsQuery->paginate($perPage);

        return Inertia::render('news/Index', [
            'featured' => $featured ? $this->formatNewsItem($featured) : null,
            'news' => [
                'data' => $news->map(fn ($item) => $this->formatNewsListItem($item))->values()->toArray(),
                'meta' => PaginationHelper::meta($news),
            ],
            'filters' => $filters,
            'filterOptions' => Inertia::defer(fn () => $this->getFilterOptions()),
        ]);
    }

    public function show(string $locale, AssetNew $assetNew): Response
    {
        $assetNew->load(['asset', 'market', 'sector', 'country']);

        // Get related news (same asset, market, or sector)
        $relatedNews = AssetNew::query()
            ->where('id', '!=', $assetNew->id)
            ->where('is_rewritten', true)
            ->whereNotNull('image_url')
            ->where(function (Builder $query) use ($assetNew) {
                if ($assetNew->asset_id) {
                    $query->orWhere('asset_id', $assetNew->asset_id);
                }
                if ($assetNew->market_id) {
                    $query->orWhere('market_id', $assetNew->market_id);
                }
                if ($assetNew->sector_id) {
                    $query->orWhere('sector_id', $assetNew->sector_id);
                }
            })
            ->orderByDesc('created_at')
            ->limit(5)
            ->with(['asset', 'market'])
            ->get();

        // Get similar news (same category or sentiment)
        $similarNews = AssetNew::query()
            ->where('id', '!=', $assetNew->id)
            ->where('is_rewritten', true)
            ->whereNotNull('image_url')
            ->whereNotIn('id', $relatedNews->pluck('id'))
            ->where(function (Builder $query) use ($assetNew) {
                if ($assetNew->category) {
                    $query->orWhere('category', $assetNew->category);
                }
                if ($assetNew->sentiment) {
                    $query->orWhere('sentiment', $assetNew->sentiment);
                }
            })
            ->orderByDesc('created_at')
            ->limit(4)
            ->with(['asset', 'market'])
            ->get();

        return Inertia::render('news/Show', [
            'news' => $this->formatNewsItem($assetNew),
            'relatedNews' => Inertia::defer(fn () => $relatedNews->map(fn ($item) => $this->formatNewsListItem($item))->values()->toArray()),
            'similarNews' => Inertia::defer(fn () => $similarNews->map(fn ($item) => $this->formatNewsListItem($item))->values()->toArray()),
        ]);
    }

    private function buildFilteredQuery(array $filters): Builder
    {
        $query = AssetNew::query()
            ->where('is_rewritten', true)
            ->whereNotNull('image_url')
            ->with(['asset', 'market']);

        // Text search
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                    ->orWhere('description', 'ilike', "%{$search}%")
                    ->orWhere('content', 'ilike', "%{$search}%");
            });
        }

        // Sentiment filter
        if (! empty($filters['sentiment'])) {
            $query->where('sentiment', $filters['sentiment']);
        }

        // Category filter
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        // Action filter
        if (! empty($filters['action'])) {
            $query->where('action', $filters['action']);
        }

        // Market filter
        if (! empty($filters['market_id'])) {
            $query->where('market_id', $filters['market_id']);
        }

        // Sector filter
        if (! empty($filters['sector_id'])) {
            $query->where('sector_id', $filters['sector_id']);
        }

        // Country filter
        if (! empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        // Asset filter
        if (! empty($filters['asset_id'])) {
            $query->where('asset_id', $filters['asset_id']);
        }

        // Score range filter
        if (! empty($filters['score_min'])) {
            $query->where('score', '>=', $filters['score_min']);
        }
        if (! empty($filters['score_max'])) {
            $query->where('score', '<=', $filters['score_max']);
        }

        // Date range filter
        if (! empty($filters['date_from'])) {
            $query->whereDate('date', '>=', $filters['date_from']);
        }
        if (! empty($filters['date_to'])) {
            $query->whereDate('date', '<=', $filters['date_to']);
        }

        return $query->orderByDesc('created_at');
    }

    private function getFilterOptions(): array
    {
        return [
            'markets' => Market::query()
                ->whereHas('assetNews')
                ->orderBy('code')
                ->get()
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'code' => $m->code,
                    'name' => $m->name,
                ])
                ->toArray(),
            'sectors' => Sector::query()
                ->whereHas('assetNews')
                ->get()
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'name' => $s->name,
                ])
                ->sortBy('name')
                ->values()
                ->toArray(),
            'countries' => Country::query()
                ->whereHas('assetNews')
                ->get()
                ->map(fn ($c) => [
                    'id' => $c->id,
                    'code' => $c->code,
                    'name' => $c->name,
                ])
                ->sortBy('name')
                ->values()
                ->toArray(),
            'categories' => AssetNew::query()
                ->where('is_rewritten', true)
                ->whereNotNull('category')
                ->distinct()
                ->pluck('category')
                ->sort()
                ->values()
                ->toArray(),
        ];
    }

    private function formatNewsListItem(AssetNew $news): array
    {
        $cdnUrl = config('services.cdn.news');

        return [
            'id' => $news->id,
            'title' => $news->title,
            'slug' => $news->slug,
            'description' => \Illuminate\Support\Str::limit($news->description, 200),
            'image_url' => $news->image_url ? "{$cdnUrl}/{$news->image_url}" : null,
            'score' => $news->score,
            'sentiment' => $news->sentiment,
            'action' => $news->action,
            'category' => $news->category,
            'date' => $news->date?->toISOString(),
            'created_at' => $news->created_at->toISOString(),
            'asset' => $news->relationLoaded('asset') && $news->asset ? [
                'id' => $news->asset->id,
                'symbol' => $news->asset->symbol,
                'name' => $news->asset->name,
            ] : null,
            'market' => $news->relationLoaded('market') && $news->market ? [
                'id' => $news->market->id,
                'code' => $news->market->code,
            ] : null,
            'bookmarked' => false,
        ];
    }

    private function formatNewsItem(AssetNew $news): array
    {
        $cdnUrl = config('services.cdn.news');

        return [
            'id' => $news->id,
            'title' => $news->title,
            'slug' => $news->slug,
            'description' => $news->description,
            'content' => $news->content,
            'image_url' => $news->image_url ? "{$cdnUrl}/{$news->image_url}" : null,
            'score' => $news->score,
            'sentiment' => $news->sentiment,
            'action' => $news->action,
            'category' => $news->category,
            'date' => $news->date?->toISOString(),
            'created_at' => $news->created_at->toISOString(),
            'risks' => $news->risks ?? [],
            'opportunities' => $news->opportunities ?? [],
            'affected_sectors' => $news->affected_sectors ?? [],
            'meta_tags' => $news->meta_tags ?? [],
            'meta_description' => $news->meta_description,
            'asset' => $news->relationLoaded('asset') && $news->asset ? [
                'id' => $news->asset->id,
                'symbol' => $news->asset->symbol,
                'name' => $news->asset->name,
                'name_ar' => $news->asset->name_ar,
            ] : null,
            'market' => $news->relationLoaded('market') && $news->market ? [
                'id' => $news->market->id,
                'code' => $news->market->code,
                'name' => $news->market->name,
            ] : null,
            'sector' => $news->relationLoaded('sector') && $news->sector ? [
                'id' => $news->sector->id,
                'name' => $news->sector->name,
            ] : null,
            'country' => $news->relationLoaded('country') && $news->country ? [
                'id' => $news->country->id,
                'name' => $news->country->name,
                'code' => $news->country->code,
            ] : null,
            'bookmarked' => false,
            'user_rating' => null,
            'ratings_summary' => [
                'helpful_count' => 0,
                'not_helpful_count' => 0,
            ],
            'comments_count' => 0,
        ];
    }
}
