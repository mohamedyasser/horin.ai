<?php

namespace App\Jobs;

use App\Models\AlertNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $retentionDays = 30
    ) {}

    public function handle(): void
    {
        $cutoff = now()->subDays($this->retentionDays);
        $totalDeleted = 0;
        $batchSize = 1000;

        // Only delete read or failed notifications in batches
        do {
            $deleted = AlertNotification::where('created_at', '<', $cutoff)
                ->whereIn('status', ['read', 'failed'])
                ->limit($batchSize)
                ->delete();
            $totalDeleted += $deleted;
        } while ($deleted > 0);

        Log::info("Cleaned up {$totalDeleted} notification records", [
            'retention_days' => $this->retentionDays,
            'cutoff_date' => $cutoff->toDateString(),
        ]);
    }
}
