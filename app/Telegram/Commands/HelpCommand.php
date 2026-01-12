<?php

namespace App\Telegram\Commands;

use App\Models\User;
use App\Telegram\Services\DefaultKeyboardBuilder;
use WeStacks\TeleBot\Foundation\CommandHandler;

class HelpCommand extends CommandHandler
{
    protected static function aliases(): array
    {
        return ['/help'];
    }

    protected static function description(?string $locale = null): string
    {
        return 'Show available commands and help';
    }

    public function handle(): mixed
    {
        $chatId = $this->update->message->chat->id;
        $telegramId = (string) $this->update->message->from->id;

        $user = User::where('telegram_id', $telegramId)->first();
        $locale = $user?->language ?? 'en';

        $helpText = $locale === 'ar' ? $this->getHelpTextAr() : $this->getHelpTextEn();

        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $helpText,
            'parse_mode' => 'Markdown',
            'reply_markup' => DefaultKeyboardBuilder::forUser($user, $locale),
        ]);

        return null;
    }

    private function getHelpTextEn(): string
    {
        return <<<'MSG'
❓ *Horin Bot Help*
━━━━━━━━━━━━━━━━━━

*Available Commands:*

📋 /alerts - View your active alerts
⚙️ /settings - View notification settings
🌐 /language - Change language
❓ /help - Show this help message

*Alert Actions:*
When you receive an alert, you can:
• Tap "View Stock" to see details
• Tap "Snooze" to pause the alert
• Tap "Acknowledge" to confirm receipt

*Need more help?*
Visit our app for full features.
MSG;
    }

    private function getHelpTextAr(): string
    {
        return <<<'MSG'
❓ *مساعدة بوت حورين*
━━━━━━━━━━━━━━━━━━

*الأوامر المتاحة:*

📋 /alerts - عرض تنبيهاتك النشطة
⚙️ /settings - عرض إعدادات الإشعارات
🌐 /language - تغيير اللغة
❓ /help - عرض رسالة المساعدة

*إجراءات التنبيه:*
عند استلام تنبيه، يمكنك:
• الضغط على "عرض السهم" للتفاصيل
• الضغط على "تأجيل" لإيقاف التنبيه مؤقتاً
• الضغط على "تأكيد" لتأكيد الاستلام

*تحتاج مساعدة أكثر؟*
قم بزيارة التطبيق للمزيد.
MSG;
    }
}
