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

        // Use a shared closure to avoid duplicate search calls
        $searchResults = null;
        $getResults = function () use (&$searchResults, $query, $request) {
            if ($searchResults === null) {
                $searchResults = $this->searchAssets($query, $request);
            }

            return $searchResults;
        };

        return Inertia::render('Search', [
            'query' => $query,
            'results' => Inertia::defer(fn () => $getResults()),
            'totalCount' => Inertia::defer(fn () => $getResults()['meta']['total'] ?? 0),
        ]);
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, mixed>}
     */
    private function searchAssets(string $query, Request $request): array
    {
        $locale = app()->getLocale();
        $page = max(1, (int) $request->input('page', 1));

        $results = Asset::search($query)
            ->query(fn ($query) => $query->with(['market', 'sector']))
            ->paginate(self::PER_PAGE, 'page', $page);

        // Fetch fresh prices for display (hybrid approach)
        $invIds = collect($results->items())->pluck('inv_id')->filter()->toArray();
        $prices = LatestAssetPrice::whereIn('pid', $invIds)->get()->keyBy('pid');

        $data = collect($results->items())->map(function ($asset) use ($locale, $prices) {
            $price = $prices[$asset->inv_id] ?? null;

            return [
                'id' => $asset->id,
                'symbol' => $asset->symbol,
                'name' => $locale === 'ar' ? $asset->name_ar : $asset->name_en,
                'market' => $asset->market ? [
                    'id' => $asset->market->id,
                    'code' => $asset->market->code,
                    'name' => $locale === 'ar' ? $asset->market->name_ar : $asset->market->name_en,
                ] : null,
                'sector' => $asset->sector ? [
                    'id' => $asset->sector->id,
                    'name' => $locale === 'ar' ? $asset->sector->name_ar : $asset->sector->name_en,
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
}
