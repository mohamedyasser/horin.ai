<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('markets', function () {
    return Inertia::render('Markets', [
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('markets');

Route::get('markets/{market}', function ($market) {
    // TODO: Replace with actual market data from database
    $mockMarket = [
        'id' => (int) $market,
        'code' => 'EGX',
        'name' => 'EGX',
        'full_name' => 'Egyptian Exchange',
        'country' => 'Egypt',
        'timezone' => 'Africa/Cairo',
        'trading_hours' => ['open' => '10:00', 'close' => '14:30'],
        'status' => 'closed',
        'prediction_count' => 156,
        'tv_link' => 'https://www.tradingview.com/markets/stocks-egypt/',
        'asset_count' => 220,
    ];

    return Inertia::render('markets/Show', [
        'market' => $mockMarket,
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('markets.show');

Route::get('assets/{asset}', function ($asset) {
    // TODO: Replace with actual asset data from database
    $mockAsset = [
        'id' => (int) $asset,
        'symbol' => 'COMI',
        'name' => 'Commercial International Bank',
        'market' => 'EGX',
        'sector' => 'Banking',
        'country' => 'Egypt',
        'asset_type' => 'stock',
        'currency' => 'EGP',
        'last_updated' => '2024-01-15T10:00:00Z',
        'price' => [
            'last' => 72.5,
            'last_close' => 71.2,
            'change_percent' => 1.83,
            'high' => 73.1,
            'low' => 71.0,
            'volume' => 1250000,
            'updated_at' => '2024-01-15T14:30:00Z',
        ],
        'predictions' => [
            [
                'id' => 1,
                'predicted_price' => 75.8,
                'horizon' => '1D',
                'confidence' => 82,
                'expected_gain_percent' => 4.55,
                'upper_bound' => 77.2,
                'lower_bound' => 74.4,
                'timestamp' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => 2,
                'predicted_price' => 79.5,
                'horizon' => '1W',
                'confidence' => 78,
                'expected_gain_percent' => 9.66,
                'upper_bound' => 82.1,
                'lower_bound' => 76.9,
                'timestamp' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => 3,
                'predicted_price' => 85.2,
                'horizon' => '1M',
                'confidence' => 87,
                'expected_gain_percent' => 17.52,
                'upper_bound' => 89.5,
                'lower_bound' => 80.9,
                'timestamp' => '2024-01-15T10:00:00Z',
            ],
            [
                'id' => 4,
                'predicted_price' => 92.0,
                'horizon' => '3M',
                'confidence' => 72,
                'expected_gain_percent' => 26.90,
                'upper_bound' => 98.5,
                'lower_bound' => 85.5,
                'timestamp' => '2024-01-15T10:00:00Z',
            ],
        ],
        'indicators' => [
            'rsi' => 58.5,
            'macd' => 1.25,
            'ema' => 71.8,
            'sma' => 70.5,
            'atr' => 1.85,
        ],
        'prediction_history' => [
            [
                'id' => 101,
                'predicted_price' => 84.5,
                'horizon' => '1M',
                'confidence' => 85,
                'expected_gain_percent' => 16.5,
                'timestamp' => '2024-01-14T10:00:00Z',
            ],
            [
                'id' => 102,
                'predicted_price' => 83.2,
                'horizon' => '1M',
                'confidence' => 84,
                'expected_gain_percent' => 15.2,
                'timestamp' => '2024-01-13T10:00:00Z',
            ],
            [
                'id' => 103,
                'predicted_price' => 82.8,
                'horizon' => '1M',
                'confidence' => 86,
                'expected_gain_percent' => 14.8,
                'timestamp' => '2024-01-12T10:00:00Z',
            ],
        ],
    ];

    return Inertia::render('assets/Show', [
        'asset' => $mockAsset,
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('assets.show');

Route::get('sectors', function () {
    return Inertia::render('Sectors', [
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('sectors');

Route::get('sectors/{sector}', function ($sector) {
    // TODO: Replace with actual sector data from database
    $mockSector = [
        'id' => (int) $sector,
        'code' => 'banking',
        'name' => 'Banking',
        'description' => 'Commercial and investment banks',
        'asset_count' => 45,
        'prediction_count' => 38,
        'markets' => [
            ['market' => 'EGX', 'count' => 12],
            ['market' => 'TASI', 'count' => 15],
            ['market' => 'ADX', 'count' => 8],
            ['market' => 'DFM', 'count' => 10],
        ],
        'avg_gain_percent' => 12.5,
        'updated_at' => '2024-01-15T10:00:00Z',
    ];

    return Inertia::render('sectors/Show', [
        'sector' => $mockSector,
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('sectors.show');

Route::get('predictions', function () {
    return Inertia::render('Predictions', [
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('predictions');

Route::get('search', function () {
    return Inertia::render('Search', [
        'canLogin' => Route::has('login'),
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('search');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__.'/settings.php';
