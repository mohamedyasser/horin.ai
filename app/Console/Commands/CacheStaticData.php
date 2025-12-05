<?php

namespace App\Console\Commands;

use App\Services\StaticDataCacheService;
use Illuminate\Console\Command;

class CacheStaticData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:static {--clear : Clear all static data caches instead of warming them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm or clear all static data caches (countries, markets, sectors, assets)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('clear')) {
            return $this->clearCache();
        }

        return $this->warmCache();
    }

    /**
     * Clear all static data caches.
     */
    private function clearCache(): int
    {
        $this->info('Clearing static data caches...');

        StaticDataCacheService::clearAll();

        $this->info('All static data caches cleared successfully.');

        return Command::SUCCESS;
    }

    /**
     * Warm all static data caches.
     */
    private function warmCache(): int
    {
        $this->info('Warming static data caches...');

        $this->output->write('  Caching countries... ');
        $countries = StaticDataCacheService::countries();
        $this->line("<info>{$countries->count()} cached</info>");

        $this->output->write('  Caching markets... ');
        $markets = StaticDataCacheService::markets();
        $this->line("<info>{$markets->count()} cached</info>");

        $this->output->write('  Caching sectors... ');
        $sectors = StaticDataCacheService::sectors();
        $this->line("<info>{$sectors->count()} cached</info>");

        $this->output->write('  Caching all assets... ');
        $assets = StaticDataCacheService::assets();
        $this->line("<info>{$assets->count()} cached</info>");

        $this->info('Warming market-specific asset caches...');
        foreach ($markets as $market) {
            $marketAssets = StaticDataCacheService::assetsByMarket($market->id);
            $this->line("  {$market->code}: <info>{$marketAssets->count()} assets</info>");
        }

        $this->info('Warming sector-specific asset caches...');
        foreach ($sectors as $sector) {
            $sectorAssets = StaticDataCacheService::assetsBySector($sector->id);
            $this->line("  {$sector->name_en}: <info>{$sectorAssets->count()} assets</info>");
        }

        $this->newLine();
        $this->info('All static data caches warmed successfully.');

        return Command::SUCCESS;
    }
}
