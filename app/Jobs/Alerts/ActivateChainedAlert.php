<?php

namespace App\Jobs\Alerts;

use App\Models\Alert;
use App\Models\AlertChain;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ActivateChainedAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Alert $alertToActivate,
        private AlertChain $chain
    ) {}

    public function handle(): void
    {
        // Verify alert is still in chained status
        if ($this->alertToActivate->status !== 'chained') {
            Log::info('Chained alert no longer in chained status, skipping activation', [
                'alert_id' => $this->alertToActivate->id,
                'current_status' => $this->alertToActivate->status,
            ]);

            return;
        }

        // Verify chain is still active
        if (! $this->chain->is_active) {
            Log::info('Alert chain is no longer active, skipping activation', [
                'chain_id' => $this->chain->id,
                'alert_id' => $this->alertToActivate->id,
            ]);

            return;
        }

        // Calculate expiration if configured
        $expiresAt = null;
        if ($this->chain->expires_after_minutes) {
            $expiresAt = now()->addMinutes($this->chain->expires_after_minutes);
        }

        // Activate the alert
        $this->alertToActivate->update([
            'status' => 'active',
            'chain_from_id' => $this->chain->trigger_alert_id,
            'expires_at' => $expiresAt,
        ]);

        // Update chain execution tracking
        $this->chain->update([
            'last_executed_at' => now(),
            'execution_count' => $this->chain->execution_count + 1,
        ]);

        Log::info('Chained alert activated', [
            'alert_id' => $this->alertToActivate->id,
            'chain_id' => $this->chain->id,
            'triggered_by' => $this->chain->trigger_alert_id,
            'expires_at' => $expiresAt,
        ]);
    }
}
