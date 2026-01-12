<?php

namespace App\Jobs\Alerts;

use App\Models\AlertHistory;
use App\Models\AlertNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $digestType = 'daily' // 'daily' or 'weekly'
    ) {}

    public function handle(): void
    {
        $users = User::whereHas('alertPreferences', function ($q) {
            $q->where('digest_enabled', true);
        })->get();

        $generatedCount = 0;

        foreach ($users as $user) {
            if ($this->generateUserDigest($user)) {
                $generatedCount++;
            }
        }

        Log::info('Digest generation completed', [
            'type' => $this->digestType,
            'users_checked' => $users->count(),
            'digests_generated' => $generatedCount,
        ]);
    }

    private function generateUserDigest(User $user): bool
    {
        $period = $this->digestType === 'daily'
            ? now()->subDay()
            : now()->subWeek();

        $history = AlertHistory::where('user_id', $user->id)
            ->where('triggered_at', '>=', $period)
            ->with(['alert.asset'])
            ->orderBy('triggered_at', 'desc')
            ->get();

        if ($history->isEmpty()) {
            return false; // No alerts to digest
        }

        // Group by asset
        $byAsset = $history->groupBy(fn ($h) => $h->alert->asset_id);

        // Build digest content
        $locale = $user->language ?? 'en';
        $content = $this->buildDigestContent($byAsset, $locale);

        // Create notification record
        $notification = AlertNotification::create([
            'idempotency_key' => "digest:{$this->digestType}:{$user->id}:".now()->format('Y-m-d'),
            'user_id' => $user->id,
            'alert_id' => null,
            'alert_history_id' => null,
            'type' => "digest.{$this->digestType}",
            'channel' => 'telegram',
            'priority' => 'low',
            'title' => $this->getDigestTitle($locale),
            'title_ar' => $this->getDigestTitle('ar'),
            'body' => $content,
            'body_ar' => $this->buildDigestContent($byAsset, 'ar'),
            'data' => [
                'digest_type' => $this->digestType,
                'alerts_count' => $history->count(),
                'assets_count' => $byAsset->count(),
            ],
            'status' => 'pending',
        ]);

        // Send digest
        $this->sendDigest($user, $notification, $content);

        $notification->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        Log::info('Digest generated', [
            'user_id' => $user->id,
            'type' => $this->digestType,
            'alerts_count' => $history->count(),
        ]);

        return true;
    }

    private function getDigestTitle(string $locale): string
    {
        if ($this->digestType === 'daily') {
            return $locale === 'ar' ? '📊 ملخص التنبيهات اليومي' : '📊 Daily Alert Summary';
        }

        return $locale === 'ar' ? '📊 ملخص التنبيهات الأسبوعي' : '📊 Weekly Alert Summary';
    }

    private function buildDigestContent($byAsset, string $locale): string
    {
        $lines = [];

        $title = $this->getDigestTitle($locale);
        $lines[] = "*{$title}*";
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        $totalAlerts = 0;

        foreach ($byAsset as $assetId => $alerts) {
            $asset = $alerts->first()->alert->asset;

            if (! $asset) {
                continue;
            }

            $assetName = $locale === 'ar' ? ($asset->name_ar ?? $asset->name) : $asset->name;
            $totalAlerts += $alerts->count();

            $lines[] = '';
            $lines[] = "*{$asset->symbol}* - {$assetName}";
            $lines[] = ($locale === 'ar' ? 'عدد التنبيهات: ' : 'Alerts: ').$alerts->count();

            foreach ($alerts->take(3) as $alertHistory) {
                $time = $alertHistory->triggered_at->format('M d H:i');
                $type = $alertHistory->alert->trigger_type;
                $lines[] = "  • {$type} @ {$time}";
            }

            if ($alerts->count() > 3) {
                $remaining = $alerts->count() - 3;
                $lines[] = $locale === 'ar'
                    ? "  ... و{$remaining} المزيد"
                    : "  ... and {$remaining} more";
            }
        }

        $lines[] = '';
        $lines[] = '━━━━━━━━━━━━━━━━━━';

        $summaryLine = $locale === 'ar'
            ? "إجمالي: {$totalAlerts} تنبيه في ".$byAsset->count().' أسهم'
            : "Total: {$totalAlerts} alerts across ".$byAsset->count().' assets';
        $lines[] = $summaryLine;

        $periodLabel = $this->digestType === 'daily'
            ? ($locale === 'ar' ? 'آخر 24 ساعة' : 'Last 24 hours')
            : ($locale === 'ar' ? 'آخر 7 أيام' : 'Last 7 days');
        $lines[] = "📅 {$periodLabel}";

        return implode("\n", $lines);
    }

    private function sendDigest(User $user, AlertNotification $notification, string $content): void
    {
        if (! $user->telegram_id) {
            Log::info('User has no Telegram ID for digest', ['user_id' => $user->id]);

            return;
        }

        try {
            $telegram = app(\App\Services\TelegramBotService::class);
            $keyboard = $this->buildDigestKeyboard($user->language ?? 'en');
            $telegram->sendMessageWithKeyboard($user->telegram_id, $content, $keyboard);

            Log::info('Telegram digest sent', [
                'user_id' => $user->id,
                'telegram_id' => $user->telegram_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram digest', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildDigestKeyboard(string $locale): array
    {
        $viewAllLabel = $locale === 'ar' ? 'عرض كل التنبيهات' : 'View All Alerts';
        $manageLabel = $locale === 'ar' ? 'إدارة التنبيهات' : 'Manage Alerts';

        return [
            [
                [
                    'text' => "📋 {$viewAllLabel}",
                    'url' => config('app.url').'/alerts/history',
                ],
            ],
            [
                [
                    'text' => "⚙️ {$manageLabel}",
                    'url' => config('app.url').'/alerts',
                ],
            ],
        ];
    }
}
