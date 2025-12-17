<?php

use App\Http\Controllers\AssetController;
use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\TelegramWebhookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MarketController;
use App\Http\Controllers\PredictionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SectorController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Sitemap routes (must be before locale prefix)
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-static.xml', [SitemapController::class, 'static'])->name('sitemap.static');
Route::get('/sitemap-markets.xml', [SitemapController::class, 'markets'])->name('sitemap.markets');
Route::get('/sitemap-sectors.xml', [SitemapController::class, 'sectors'])->name('sitemap.sectors');
Route::get('/sitemap-assets.xml', [SitemapController::class, 'assets'])->name('sitemap.assets');

// Redirect root to default locale (ar)
Route::get('/', function () {
    return redirect('/ar');
});

// Dashboard redirect - named 'dashboard' for Fortify/Laravel compatibility
Route::get('/dashboard', function () {
    $locale = app()->getLocale() ?: 'ar';

    return redirect("/{$locale}/dashboard");
})->middleware(['auth'])->name('dashboard');

// Telegram Auth Routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('auth/telegram', [TelegramAuthController::class, 'show'])
        ->name('auth.telegram');
    Route::get('auth/telegram/callback', [TelegramAuthController::class, 'callback'])
        ->name('auth.telegram.callback');
});

// Telegram Webhook (no auth, validated by Telegram)
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->withoutMiddleware(['web']);

// Phone Verification Routes
Route::middleware('auth')->group(function () {
    Route::get('verify-phone', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'show'])
        ->name('verification.phone');
    Route::post('verify-phone/resend', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'resend'])
        ->name('verification.phone.resend');
    Route::get('api/user/phone-status', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'status'])
        ->name('api.user.phone-status');
});

// Onboarding Routes
Route::middleware(['auth', 'verified', 'phone.verified'])->group(function () {
    Route::get('onboarding', [App\Http\Controllers\OnboardingController::class, 'show'])
        ->name('onboarding');
    Route::post('onboarding', [App\Http\Controllers\OnboardingController::class, 'store'])
        ->name('onboarding.store');
});

// Localized routes - prefix with locale (ar/en)
Route::prefix('{locale}')
    ->where(['locale' => 'ar|en'])
    ->group(function () {
        // Home page
        Route::get('/', HomeController::class)->name('home');

        // Markets
        Route::get('markets', [MarketController::class, 'index'])->name('markets');
        Route::get('markets/{market}', [MarketController::class, 'show'])->name('markets.show');

        // Sectors
        Route::get('sectors', [SectorController::class, 'index'])->name('sectors');
        Route::get('sectors/{sector}', [SectorController::class, 'show'])->name('sectors.show');

        // Predictions
        Route::get('predictions', [PredictionController::class, 'index'])->name('predictions');

        // Search
        Route::get('search', [SearchController::class, 'index'])->name('search');

        // Assets
        Route::get('assets/{asset}', [AssetController::class, 'show'])->name('assets.show');

        // Info pages
        Route::get('about', function () {
            return Inertia::render('About');
        })->name('about');

        Route::get('faq', function () {
            return Inertia::render('Faq');
        })->name('faq');

        Route::get('methodology', function () {
            return Inertia::render('Methodology');
        })->name('methodology');

        Route::get('privacy', function () {
            return Inertia::render('Privacy');
        })->name('privacy');

        Route::get('terms', function () {
            return Inertia::render('Terms');
        })->name('terms');

        Route::get('contact', function () {
            return Inertia::render('Contact');
        })->name('contact');

        // Dashboard (authenticated)
        Route::get('dashboard', function () {
            return Inertia::render('Dashboard');
        })->middleware(['auth', 'verified', 'onboarding.complete'])->name('dashboard.localized');
    });

require __DIR__.'/settings.php';
