<?php

namespace App\Notifications;

use App\Models\Alert;
use App\Models\AlertHistory;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AlertTriggeredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Alert $alert,
        public AlertHistory $history,
        public array $triggerData
    ) {
        $this->queue = 'notifications';
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $locale = $notifiable->language ?? 'en';
        $asset = $this->alert->asset;
        $assetName = $locale === 'ar' ? ($asset->name_ar ?? $asset->name) : $asset->name;

        $subject = $locale === 'ar'
            ? "🔔 تنبيه: {$asset->symbol}"
            : "🔔 Alert: {$asset->symbol}";

        $triggerValue = $this->triggerData['trigger_value'] ?? null;
        $currentPrice = $this->triggerData['current_price'] ?? null;

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($locale === 'ar' ? 'مرحباً!' : 'Hello!')
            ->line($this->getMainLine($locale, $asset->symbol, $assetName));

        if ($currentPrice) {
            $message->line($locale === 'ar'
                ? "السعر الحالي: {$currentPrice} ج.م"
                : "Current Price: {$currentPrice} EGP");
        }

        if ($triggerValue && $triggerValue !== $currentPrice) {
            $message->line($locale === 'ar'
                ? "قيمة التفعيل: {$triggerValue}"
                : "Trigger Value: {$triggerValue}");
        }

        $message->action(
            $locale === 'ar' ? 'عرض السهم' : 'View Stock',
            url("/assets/{$asset->id}")
        );

        $message->line($locale === 'ar'
            ? 'شكراً لاستخدامك كيرا!'
            : 'Thank you for using Kira!');

        return $message;
    }

    private function getMainLine(string $locale, string $symbol, string $assetName): string
    {
        $triggerType = $this->alert->trigger_type;

        $messages = [
            'target_price' => [
                'en' => "Your target price alert for {$symbol} - {$assetName} has been triggered.",
                'ar' => "تم تفعيل تنبيه السعر المستهدف للسهم {$symbol} - {$assetName}.",
            ],
            'breakout' => [
                'en' => "A breakout has been detected for {$symbol} - {$assetName}.",
                'ar' => "تم اكتشاف اختراق للسهم {$symbol} - {$assetName}.",
            ],
            'daily_change' => [
                'en' => "{$symbol} - {$assetName} has moved significantly today.",
                'ar' => "تحرك السهم {$symbol} - {$assetName} بشكل ملحوظ اليوم.",
            ],
            'signal' => [
                'en' => "A new signal has been detected for {$symbol} - {$assetName}.",
                'ar' => "تم اكتشاف إشارة جديدة للسهم {$symbol} - {$assetName}.",
            ],
            'anomaly' => [
                'en' => "An anomaly has been detected for {$symbol} - {$assetName}.",
                'ar' => "تم اكتشاف شذوذ في السهم {$symbol} - {$assetName}.",
            ],
            'pattern' => [
                'en' => "A chart pattern has been detected for {$symbol} - {$assetName}.",
                'ar' => "تم اكتشاف نمط في الرسم البياني للسهم {$symbol} - {$assetName}.",
            ],
            'recommendation' => [
                'en' => "A recommendation update for {$symbol} - {$assetName}.",
                'ar' => "تحديث توصية للسهم {$symbol} - {$assetName}.",
            ],
        ];

        return $messages[$triggerType][$locale]
            ?? ($locale === 'ar'
                ? "تم تفعيل التنبيه الخاص بك للسهم {$symbol} - {$assetName}."
                : "Your alert for {$symbol} - {$assetName} has been triggered.");
    }

    public function toArray($notifiable): array
    {
        return [
            'alert_id' => $this->alert->id,
            'history_id' => $this->history->id,
            'trigger_data' => $this->triggerData,
        ];
    }
}
