<?php

namespace App\Observers;

use App\Services\StaticDataCacheService;
use Illuminate\Database\Eloquent\Model;

class StaticDataObserver
{
    /**
     * Handle the "created" event.
     */
    public function created(Model $model): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "updated" event.
     */
    public function updated(Model $model): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "deleted" event.
     */
    public function deleted(Model $model): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "restored" event.
     */
    public function restored(Model $model): void
    {
        $this->clearCache();
    }

    /**
     * Handle the "force deleted" event.
     */
    public function forceDeleted(Model $model): void
    {
        $this->clearCache();
    }

    /**
     * Clear all static data caches.
     */
    private function clearCache(): void
    {
        StaticDataCacheService::clearAll();
    }
}
