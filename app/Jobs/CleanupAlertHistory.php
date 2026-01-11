<?php

namespace App\Jobs;

use App\Models\AlertHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupAlertHistory implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 90
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);
        $totalDeleted = 0;
        $batchSize = 1000;

        do {
            $deleted = AlertHistory::where('triggered_at', '<', $cutoff)
                ->limit($batchSize)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        Log::info("Cleaned up {$totalDeleted} alert history records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
