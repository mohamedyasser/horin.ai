<?php

namespace App\Services;

use App\Models\Alert;
use App\Models\Asset;
use App\Models\PortfolioAsset;
use Illuminate\Support\Collection;

class AlertScopeResolver
{
    /**
     * Resolve which assets an alert applies to.
     */
    public function resolveAssets(Alert $alert): Collection
    {
        return match ($alert->scope) {
            'single_asset' => collect([$alert->asset_id])->filter(),
            'watchlist' => $this->getWatchlistAssets($alert),
            'portfolio' => $this->getPortfolioAssets($alert),
            'sector' => $this->getSectorAssets($alert),
            'market' => $this->getMarketAssets(),
            default => collect(),
        };
    }

    /**
     * Check if an event matches an alert's scope.
     */
    public function matchesScope(Alert $alert, string $eventAssetId): bool
    {
        if ($alert->scope === 'single_asset') {
            return $alert->asset_id === $eventAssetId;
        }

        return $this->resolveAssets($alert)->contains($eventAssetId);
    }

    /**
     * Get entry price for portfolio alerts.
     */
    public function getEntryPrice(Alert $alert, string $assetId): ?float
    {
        if ($alert->scope !== 'portfolio') {
            return $alert->parameters['entry_price'] ?? null;
        }

        $holding = $this->getPortfolioHolding($alert, $assetId);

        return $holding?->average_cost;
    }

    /**
     * Get asset count for a scope (for UI display).
     */
    public function getAssetCount(Alert $alert): int
    {
        return $this->resolveAssets($alert)->count();
    }

    /**
     * Get assets in user's watchlist.
     */
    private function getWatchlistAssets(Alert $alert): Collection
    {
        return $alert->user
            ->wishlistAssets()
            ->pluck('assets.id');
    }

    /**
     * Get assets in user's portfolio with positive quantity.
     */
    private function getPortfolioAssets(Alert $alert): Collection
    {
        $portfolio = $alert->user->defaultPortfolio;

        if (! $portfolio) {
            return collect();
        }

        return PortfolioAsset::where('portfolio_id', $portfolio->id)
            ->where('quantity', '>', 0)
            ->pluck('asset_id');
    }

    /**
     * Get a specific portfolio holding.
     */
    private function getPortfolioHolding(Alert $alert, string $assetId): ?PortfolioAsset
    {
        $portfolio = $alert->user->defaultPortfolio;

        if (! $portfolio) {
            return null;
        }

        return PortfolioAsset::where('portfolio_id', $portfolio->id)
            ->where('asset_id', $assetId)
            ->first();
    }

    /**
     * Get all assets in a sector.
     */
    private function getSectorAssets(Alert $alert): Collection
    {
        $sectorId = $alert->parameters['sector_id'] ?? null;

        if (! $sectorId) {
            return collect();
        }

        return Asset::where('sector_id', $sectorId)
            ->whereHas('market', fn ($q) => $q->where('is_active', true))
            ->pluck('id');
    }

    /**
     * Get all active market assets.
     */
    private function getMarketAssets(): Collection
    {
        return Asset::whereHas('market', fn ($q) => $q->where('is_active', true))
            ->pluck('id');
    }
}
