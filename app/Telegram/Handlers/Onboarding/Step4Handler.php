<?php

namespace App\Telegram\Handlers\Onboarding;

use App\Models\Sector;
use App\Models\User;
use App\Telegram\Services\OnboardingKeyboardBuilder;
use Illuminate\Support\Facades\Log;
use WeStacks\TeleBot\Foundation\CallbackHandler;

/**
 * Handler for Step 4: Sectors selection.
 *
 * Callback patterns:
 * - ob:sector:toggle:{id} - Toggle sector selection
 * - ob:sector:page:{n} - Change sectors pagination page
 * - ob:step4:back - Go back to step 3
 * - ob:complete - Complete onboarding
 */
class Step4Handler extends CallbackHandler
{
    protected string $match = '/^ob:(sector|step4|complete)/';

    public function handle(): mixed
    {
        $callbackQuery = $this->update->callback_query;
        $data = $callbackQuery->data;
        $telegramId = (string) $callbackQuery->from->id;

        $user = User::where('telegram_id', $telegramId)->first();

        if (! $user) {
            return $this->answerWithError('User not found. Please /start first.');
        }

        $parts = explode(':', $data);
        $action = $parts[1];
        $value = $parts[2] ?? null;
        $extra = $parts[3] ?? null;

        $locale = $user->language ?? 'en';
        $builder = new OnboardingKeyboardBuilder;

        return match ($action) {
            'sector' => $this->handleSector($user, $value, $extra, $locale, $builder, $callbackQuery),
            'step4' => $this->handleNavigation($user, $value, $locale, $builder, $callbackQuery),
            'complete' => $this->handleComplete($user, $locale, $builder, $callbackQuery),
            default => $this->answerWithError('Invalid action'),
        };
    }

    private function handleSector(User $user, ?string $value, ?string $extra, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        // Handle pagination
        if ($value === 'page' && $extra !== null) {
            $page = (int) $extra;

            return $this->updateSectorsKeyboard($user, $locale, $builder, $callbackQuery, $page);
        }

        // Handle sector toggle
        if ($value === 'toggle' && $extra !== null) {
            $sectorId = $extra;
            $sector = Sector::find($sectorId);

            if (! $sector) {
                return $this->answerWithError('Sector not found');
            }

            // Toggle sector selection
            $isSelected = $user->sectors()->where('sectors.id', $sectorId)->exists();

            if ($isSelected) {
                $user->sectors()->detach($sectorId);
                $action = 'removed';
            } else {
                $user->sectors()->attach($sectorId);
                $action = 'added';
            }

            Log::info("Onboarding: Sector {$action} via Telegram", [
                'user_id' => $user->id,
                'sector_id' => $sectorId,
            ]);

            // Update keyboard
            $this->updateSectorsKeyboard($user, $locale, $builder, $callbackQuery);

            $sectorName = $locale === 'ar' ? ($sector->name_ar ?: $sector->name_en) : $sector->name_en;
            $this->answerCallbackQuery([
                'text' => $action === 'added'
                    ? ($locale === 'ar' ? "✅ تمت إضافة: {$sectorName}" : "✅ Added: {$sectorName}")
                    : ($locale === 'ar' ? "❌ تمت إزالة: {$sectorName}" : "❌ Removed: {$sectorName}"),
                'show_alert' => false,
            ]);

            return null;
        }

        return $this->answerWithError('Invalid sector action');
    }

    private function handleNavigation(User $user, ?string $direction, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        if ($direction !== 'back') {
            return $this->answerWithError('Invalid navigation');
        }

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Go back to step 3b (markets)
        $selectedMarketIds = $user->markets()->pluck('markets.id')->toArray();

        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getStepMessage('3b', $locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep3MarketsKeyboard($locale, $user->country_id, $selectedMarketIds),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function handleComplete(User $user, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery): mixed
    {
        // Validate all steps are complete
        if (! $user->experience_level || ! $user->risk_level) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى إكمال الخطوة 1 أولا'
                    : 'Please complete step 1 first'
            );
        }

        if (! $user->investment_goal || ! $user->trading_style) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى إكمال الخطوة 2 أولا'
                    : 'Please complete step 2 first'
            );
        }

        if (! $user->country_id || $user->markets()->count() === 0) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى إكمال الخطوة 3 أولا'
                    : 'Please complete step 3 first'
            );
        }

        if ($user->sectors()->count() === 0) {
            return $this->answerWithError(
                $locale === 'ar'
                    ? 'يرجى اختيار قطاع واحد على الأقل'
                    : 'Please select at least one sector'
            );
        }

        // Mark onboarding as complete
        $user->markOnboardingAsComplete();

        Log::info('Onboarding completed via Telegram', [
            'user_id' => $user->id,
        ]);

        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Show completion message
        $this->editMessageText([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $builder->getCompletionMessage($locale),
            'parse_mode' => 'Markdown',
            'reply_markup' => [
                'inline_keyboard' => $builder->buildCompletionKeyboard($locale),
            ],
        ]);

        $this->answerCallbackQuery([
            'text' => $locale === 'ar' ? '🎉 مبروك!' : '🎉 Congratulations!',
            'show_alert' => false,
        ]);

        return null;
    }

    private function updateSectorsKeyboard(User $user, string $locale, OnboardingKeyboardBuilder $builder, object $callbackQuery, int $page = 1): mixed
    {
        $chatId = $callbackQuery->message->chat->id;
        $messageId = $callbackQuery->message->message_id;

        // Refresh user data
        $user->refresh();

        $selectedSectorIds = $user->sectors()->pluck('sectors.id')->toArray();

        $this->editMessageReplyMarkup([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => [
                'inline_keyboard' => $builder->buildStep4Keyboard($locale, $selectedSectorIds, $page),
            ],
        ]);

        $this->answerCallbackQuery(['text' => '']);

        return null;
    }

    private function answerWithError(string $message): null
    {
        $this->answerCallbackQuery([
            'text' => $message,
            'show_alert' => true,
        ]);

        return null;
    }
}
