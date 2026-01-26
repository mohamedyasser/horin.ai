<?php

namespace App\Http\Controllers;

use App\Http\Requests\NewsFilterRequest;
use App\Http\Resources\AssetNewCollectionResource;
use App\Http\Resources\AssetNewResource;
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
            'featured' => $featured ? (new AssetNewResource($featured))->resolve() : null,
            'news' => [
                'data' => AssetNewCollectionResource::collection($news)->resolve(),
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
            'news' => new AssetNewResource($assetNew),
            'relatedNews' => Inertia::defer(fn () => AssetNewCollectionResource::collection($relatedNews)->resolve()),
            'similarNews' => Inertia::defer(fn () => AssetNewCollectionResource::collection($similarNews)->resolve()),
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
}
