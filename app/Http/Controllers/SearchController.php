<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): Response
    {
        $query = trim($request->input('q', ''));

        if (empty($query)) {
            return Inertia::render('Search', [
                'query' => '',
                'results' => ['data' => [], 'meta' => PaginationHelper::empty()],
                'totalCount' => 0,
            ]);
        }

        return Inertia::render('Search', [
            'query' => $query,
            'results' => Inertia::defer(fn () => $this->searchAssets($query, $request)),
            'totalCount' => Inertia::defer(fn () => $this->countAssets($query)),
        ]);
    }

    private function searchAssets(string $query, Request $request): array
    {
        $page = max(1, (int) $request->input('page', 1));
        $offset = ($page - 1) * self::PER_PAGE;

        // Try advanced search function first (if FTS is set up)
        if ($this->hasAdvancedSearch()) {
            return $this->searchWithFunction($query, $page, $offset);
        }

        // Fallback to basic search
        return $this->searchWithIlike($query);
    }

    /**
     * Advanced search using PostgreSQL function with FTS, prefix matching, and highlighting
     */
    private function searchWithFunction(string $query, int $page, int $offset): array
    {
        $totalCount = $this->countAssets($query);

        $results = DB::select(
            'SELECT * FROM search_assets(?, ?, ?)',
            [$query, self::PER_PAGE, $offset]
        );

        // Load related data
        $assetIds = collect($results)->pluck('id')->toArray();
        $assets = Asset::whereIn('id', $assetIds)
            ->with(['market', 'sector', 'cachedPrice'])
            ->get()
            ->keyBy('id');

        // Map results with highlighting
        $data = collect($results)->map(function ($row) use ($assets) {
            $asset = $assets[$row->id] ?? null;

            return [
                'id' => $row->id,
                'symbol' => $row->symbol,
                'name' => app()->getLocale() === 'ar' ? $row->name_ar : $row->name_en,
                'highlightedName' => app()->getLocale() === 'ar'
                    ? ($row->headline_ar ?: $row->name_ar)
                    : ($row->headline_en ?: $row->name_en),
                'rank' => $row->rank,
                'market' => $asset?->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $asset->market->name,
                ] : null,
                'sector' => $asset?->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $asset->sector->name,
                ] : null,
                'latestPrice' => $asset?->cachedPrice ? [
                    'last' => $asset->cachedPrice->price,
                    'pcp' => $asset->cachedPrice->percent_change,
                    'freshness' => $asset->cachedPrice->freshness,
                ] : null,
            ];
        })->toArray();

        // Build pagination meta
        $lastPage = (int) ceil($totalCount / self::PER_PAGE);

        return [
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'last_page' => $lastPage,
                'per_page' => self::PER_PAGE,
                'total' => $totalCount,
                'from' => $totalCount > 0 ? $offset + 1 : null,
                'to' => $totalCount > 0 ? min($offset + self::PER_PAGE, $totalCount) : null,
            ],
        ];
    }

    /**
     * Fallback search using ILIKE (when FTS functions don't exist)
     */
    private function searchWithIlike(string $query): array
    {
        $assets = Asset::where(function ($q) use ($query) {
            $q->where('symbol', 'ILIKE', $query.'%')
                ->orWhere('name_en', 'ILIKE', '%'.$query.'%')
                ->orWhere('name_ar', 'ILIKE', '%'.$query.'%');
        })
            ->with(['market', 'sector', 'cachedPrice'])
            ->orderByRaw('CASE WHEN upper(symbol) = upper(?) THEN 0 ELSE 1 END', [$query])
            ->orderByRaw('CASE WHEN symbol ILIKE ? THEN 0 ELSE 1 END', [$query.'%'])
            ->orderBy('symbol')
            ->paginate(self::PER_PAGE);

        return [
            'data' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'highlightedName' => $this->highlightMatch($asset->name, $query),
                'market' => $asset->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $asset->market->name,
                ] : null,
                'sector' => $asset->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $asset->sector->name,
                ] : null,
                'latestPrice' => $asset->cachedPrice ? [
                    'last' => $asset->cachedPrice->price,
                    'pcp' => $asset->cachedPrice->percent_change,
                    'freshness' => $asset->cachedPrice->freshness,
                ] : null,
            ])->toArray(),
            'meta' => PaginationHelper::meta($assets),
        ];
    }

    private function countAssets(string $query): int
    {
        // Try count function first
        if ($this->hasAdvancedSearch()) {
            $result = DB::selectOne('SELECT count_search_assets(?) as count', [$query]);

            return (int) $result->count;
        }

        // Fallback to basic count
        return Asset::where(function ($q) use ($query) {
            $q->where('symbol', 'ILIKE', $query.'%')
                ->orWhere('name_en', 'ILIKE', '%'.$query.'%')
                ->orWhere('name_ar', 'ILIKE', '%'.$query.'%');
        })->count();
    }

    /**
     * Check if advanced search functions exist
     */
    private function hasAdvancedSearch(): bool
    {
        static $hasFunction = null;

        if ($hasFunction === null) {
            try {
                $result = DB::selectOne("
                    SELECT EXISTS (
                        SELECT 1 FROM pg_proc
                        WHERE proname = 'search_assets'
                    ) as exists
                ");
                $hasFunction = (bool) $result->exists;
            } catch (\Exception $e) {
                $hasFunction = false;
            }
        }

        return $hasFunction;
    }

    /**
     * Simple highlighting for ILIKE fallback
     */
    private function highlightMatch(string $text, string $query): string
    {
        if (empty($query)) {
            return $text;
        }

        return preg_replace(
            '/('.preg_quote($query, '/').')/iu',
            '<mark>$1</mark>',
            $text
        ) ?? $text;
    }
}
