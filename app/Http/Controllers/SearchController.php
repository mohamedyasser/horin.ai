<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SearchController extends Controller
{
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
            'results' => Inertia::lazy(fn () => $this->searchAssets($query)),
            'totalCount' => Inertia::lazy(fn () => $this->countAssets($query)),
        ]);
    }

    private function searchAssets(string $query): array
    {
        $assets = Asset::where(function ($q) use ($query) {
            $q->whereRaw("fts @@ websearch_to_tsquery('simple', ?)", [$query])
                ->orWhere('symbol', 'ILIKE', $query.'%');
        })
            ->with(['market', 'sector', 'latestPrice'])
            ->paginate(10);

        return [
            'data' => $assets->map(fn ($asset) => [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $asset->name,
                'market' => $asset->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $asset->market->name,
                ] : null,
                'sector' => $asset->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $asset->sector->name,
                ] : null,
                'latestPrice' => $asset->latestPrice ? [
                    'last' => (float) $asset->latestPrice->last,
                    'pcp' => $asset->latestPrice->pcp,
                ] : null,
            ])->toArray(),
            'meta' => PaginationHelper::meta($assets),
        ];
    }

    private function countAssets(string $query): int
    {
        return Asset::where(function ($q) use ($query) {
            $q->whereRaw("fts @@ websearch_to_tsquery('simple', ?)", [$query])
                ->orWhere('symbol', 'ILIKE', $query.'%');
        })->count();
    }
}
