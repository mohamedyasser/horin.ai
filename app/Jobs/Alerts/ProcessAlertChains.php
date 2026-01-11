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

class ProcessAlertChains implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private Alert $triggeredAlert
    ) {}

    public function handle(): void
    {
        // Find chains where this alert is the trigger
        $chains = AlertChain::where('trigger_alert_id', $this->triggeredAlert->id)
            ->where('is_active', true)
            ->get();

        if ($chains->isEmpty()) {
            return;
        }

        Log::debug('Processing alert chains', [
            'triggered_alert_id' => $this->triggeredAlert->id,
            'chains_count' => $chains->count(),
        ]);

        foreach ($chains as $chain) {
            $this->activateChainedAlert($chain);
        }
    }

    private function activateChainedAlert(AlertChain $chain): void
    {
        $alertToActivate = Alert::find($chain->activate_alert_id);

        if (! $alertToActivate || $alertToActivate->status !== 'chained') {
            Log::debug('Skipping chain activation', [
                'chain_id' => $chain->id,
                'reason' => ! $alertToActivate ? 'alert_not_found' : 'not_chained_status',
            ]);

            return;
        }

        // Apply delay if configured
        if ($chain->delay_minutes > 0) {
            ActivateChainedAlert::dispatch($alertToActivate, $chain)
                ->delay(now()->addMinutes($chain->delay_minutes));

            Log::info('Scheduled chained alert activation', [
                'alert_id' => $alertToActivate->id,
                'delay_minutes' => $chain->delay_minutes,
            ]);
        } else {
            $alertToActivate->update([
                'status' => 'active',
                'chain_from_id' => $this->triggeredAlert->id,
                'expires_at' => $chain->expires_after_minutes
                    ? now()->addMinutes($chain->expires_after_minutes)
                    : null,
            ]);

            Log::info('Activated chained alert immediately', [
                'alert_id' => $alertToActivate->id,
                'triggered_by' => $this->triggeredAlert->id,
            ]);
        }
    }
}
