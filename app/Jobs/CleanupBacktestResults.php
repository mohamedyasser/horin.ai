<?php

namespace App\Jobs;

use App\Models\AlertBacktestResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupBacktestResults implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 7
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);
        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $deleted = AlertBacktestResult::where('completed_at', '<', $cutoff)
                ->limit($batchSize)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        Log::info("Cleaned up {$totalDeleted} backtest result records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
