<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\LatestAssetPrice;
use App\Support\PaginationHelper;
use Illuminate\Http\Request;
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

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function searchAssets(string $query, Request $request): array
    {
        $locale = app()->getLocale();
        $page = max(1, (int) $request->input('page', 1));

        $results = Asset::search($query)->paginate(self::PER_PAGE, 'page', $page);

        // Fetch fresh prices for display (hybrid approach)
        $invIds = collect($results->items())->pluck('inv_id')->filter()->toArray();
        $prices = LatestAssetPrice::whereIn('pid', $invIds)->get()->keyBy('pid');

        $data = collect($results->items())->map(function ($asset) use ($locale, $prices) {
            $price = $prices[$asset->inv_id] ?? null;

            return [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $locale === 'ar' ? $asset->name_ar : $asset->name_en,
                'market' => $asset->market_id ? [
                    'id' => $asset->market_id,
                    'code' => $asset->market_code,
                    'name' => $locale === 'ar' ? $asset->market_name_ar : $asset->market_name_en,
                ] : null,
                'sector' => $asset->sector_id ? [
                    'id' => $asset->sector_id,
                    'name' => $locale === 'ar' ? $asset->sector_name_ar : $asset->sector_name_en,
                ] : null,
                'latestPrice' => $price ? [
                    'last' => $price->price,
                    'pcp' => $price->percent_change,
                ] : null,
            ];
        })->toArray();

        return [
            'data' => $data,
            'meta' => PaginationHelper::meta($results),
        ];
    }

    private function countAssets(string $query): int
    {
        return Asset::search($query)->count();
    }
}
