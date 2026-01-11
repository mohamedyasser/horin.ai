<?php

namespace App\Telegram\Handlers;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\UpdateHandler;

class ContactHandler extends UpdateHandler
{
    public function trigger(): bool
    {
        return isset($this->update->message?->contact);
    }

    public function handle(): mixed
    {
        $message = $this->update->message;
        $contact = $message->contact;
        $chatId = $message->chat->id;
        $telegramId = (string) $message->from->id;

        // Verify the contact belongs to the sender
        if ((string) $contact->user_id !== $telegramId) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please share your own phone number.',
            ]);

            return null;
        }

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            $this->sendMessage([
                'chat_id' => $chatId,
                'text' => 'Please login through the Horin app first.',
            ]);

            return null;
        }

        // Normalize phone number (ensure + prefix)
        $phone = $contact->phone_number;
        if (! str_starts_with($phone, '+')) {
            $phone = '+'.$phone;
        }

        // Update user with verified phone
        $user->update([
            'phone' => $phone,
            'phone_verified_at' => now(),
        ]);

        Log::info('Phone verified via Telegram', [
            'user_id' => $user->id,
            'phone' => $phone,
        ]);

        $locale = $user->language ?? 'en';

        if ($locale === 'ar') {
            $text = "✅ تم التحقق من رقم الهاتف بنجاح!\n\nأنت جاهز الآن لتلقي تنبيهات الأسهم.\n\nاستخدم /help لعرض الأوامر المتاحة.";
        } else {
            $text = "✅ Phone verified successfully!\n\nYou're all set to receive stock alerts.\n\nUse /help to see available commands.";
        }

        // Remove the reply keyboard and send confirmation
        $this->sendMessage([
            'chat_id' => $chatId,
            'text' => $text,
            'reply_markup' => [
                'remove_keyboard' => true,
            ],
        ]);

        return null;
    }
}
