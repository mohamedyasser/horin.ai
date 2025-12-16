# Telegram Authentication Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace email/password authentication with Telegram Login and bot-based phone verification.

**Architecture:** Users authenticate via Telegram widget. Backend validates hash, creates/authenticates user by telegram_id. Phone verification happens through Telegram bot sharing contact.

**Tech Stack:** Laravel 12, Inertia v2, Vue 3, telegram-bot-sdk, Tailwind v4

---

## Task 1: Install Telegram Bot SDK

**Files:**
- Modify: `composer.json`

**Step 1: Install the package**

Run:
```bash
composer require telegram-bot-sdk/telegram-bot-sdk
```

**Step 2: Verify installation**

Run:
```bash
composer show telegram-bot-sdk/telegram-bot-sdk
```

Expected: Package version displayed

**Step 3: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add telegram-bot-sdk dependency"
```

---

## Task 2: Create Telegram Configuration

**Files:**
- Create: `config/telegram.php`
- Modify: `.env.example`

**Step 1: Create config file**

Create `config/telegram.php`:

```php
<?php

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
    'auth_timeout' => 86400, // 24 hours
];
```

**Step 2: Add environment variables to .env.example**

Add to `.env.example`:

```env
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
```

**Step 3: Commit**

```bash
git add config/telegram.php .env.example
git commit -m "feat: add telegram configuration"
```

---

## Task 3: Create Migration - Add Telegram Columns

**Files:**
- Create: `database/migrations/xxxx_add_telegram_columns_to_users_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration add_telegram_columns_to_users_table --no-interaction
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->bigInteger('telegram_id')->unique()->nullable()->after('name');
            $table->string('telegram_username')->nullable()->after('telegram_id');
            $table->string('telegram_photo_url')->nullable()->after('telegram_username');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telegram_id', 'telegram_username', 'telegram_photo_url']);
        });
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_add_telegram_columns_to_users_table.php
git commit -m "feat: add telegram columns to users table"
```

---

## Task 4: Create Migration - Make Email Nullable

**Files:**
- Create: `database/migrations/xxxx_make_email_nullable_on_users_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration make_email_nullable_on_users_table --no-interaction
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_make_email_nullable_on_users_table.php
git commit -m "feat: make email nullable for telegram-only auth"
```

---

## Task 5: Create Migration - Remove Password Columns

**Files:**
- Create: `database/migrations/xxxx_remove_password_columns_from_users_table.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration remove_password_columns_from_users_table --no-interaction
```

**Step 2: Write migration content**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'phone_verification_code',
                'phone_verification_expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password');
            $table->string('phone_verification_code')->nullable();
            $table->timestamp('phone_verification_expires_at')->nullable();
        });
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/*_remove_password_columns_from_users_table.php
git commit -m "feat: remove password and otp columns from users table"
```

---

## Task 6: Create TelegramHashValidator Service

**Files:**
- Create: `app/Services/TelegramHashValidator.php`

**Step 1: Create the service class**

Run:
```bash
php artisan make:class Services/TelegramHashValidator --no-interaction
```

**Step 2: Write service content**

```php
<?php

namespace App\Services;

class TelegramHashValidator
{
    /**
     * Validate Telegram Login widget data.
     *
     * @param  array<string, mixed>  $data
     */
    public function validate(array $data): bool
    {
        if (! isset($data['hash'], $data['auth_date'])) {
            return false;
        }

        // Check auth_date is not too old
        $authTimeout = config('telegram.auth_timeout', 86400);
        if ((time() - (int) $data['auth_date']) > $authTimeout) {
            return false;
        }

        $hash = $data['hash'];
        unset($data['hash']);

        // Build data-check-string
        ksort($data);
        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        // Create secret key from bot token
        $secretKey = hash('sha256', config('telegram.bot_token'), true);

        // Compute hash
        $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        return hash_equals($computedHash, $hash);
    }
}
```

**Step 3: Commit**

```bash
git add app/Services/TelegramHashValidator.php
git commit -m "feat: add TelegramHashValidator service"
```

---

## Task 7: Create TelegramAuthController

**Files:**
- Create: `app/Http/Controllers/Auth/TelegramAuthController.php`

**Step 1: Create controller**

Run:
```bash
php artisan make:controller Auth/TelegramAuthController --no-interaction
```

**Step 2: Write controller content**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TelegramHashValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAuthController extends Controller
{
    public function __construct(
        private TelegramHashValidator $hashValidator
    ) {}

    /**
     * Show the Telegram auth page.
     */
    public function show(): Response
    {
        return Inertia::render('auth/TelegramAuth', [
            'botUsername' => config('telegram.bot_username'),
        ]);
    }

    /**
     * Handle Telegram auth callback.
     */
    public function callback(Request $request): RedirectResponse
    {
        $data = $request->only([
            'id', 'first_name', 'last_name', 'username',
            'photo_url', 'auth_date', 'hash',
        ]);

        if (! $this->hashValidator->validate($data)) {
            return redirect()->route('auth.telegram')
                ->withErrors(['telegram' => __('auth.telegram.invalid')]);
        }

        $user = User::updateOrCreate(
            ['telegram_id' => $data['id']],
            [
                'name' => trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? '')),
                'telegram_username' => $data['username'] ?? null,
                'telegram_photo_url' => $data['photo_url'] ?? null,
            ]
        );

        Auth::login($user, remember: true);

        if (! $user->hasVerifiedPhone()) {
            $this->sendPhoneVerificationRequest($user);

            return redirect()->route('verification.phone');
        }

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Send phone verification request via Telegram bot.
     */
    private function sendPhoneVerificationRequest(User $user): void
    {
        if (! $user->telegram_id) {
            return;
        }

        $telegram = app('telegram.bot');

        $telegram->sendMessage([
            'chat_id' => $user->telegram_id,
            'text' => __('auth.telegram.verify_phone_message'),
            'reply_markup' => json_encode([
                'keyboard' => [[
                    [
                        'text' => __('auth.telegram.share_phone_button'),
                        'request_contact' => true,
                    ],
                ]],
                'resize_keyboard' => true,
                'one_time_keyboard' => true,
            ]),
        ]);
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Auth/TelegramAuthController.php
git commit -m "feat: add TelegramAuthController"
```

---

## Task 8: Create TelegramWebhookController

**Files:**
- Create: `app/Http/Controllers/Auth/TelegramWebhookController.php`

**Step 1: Create controller**

Run:
```bash
php artisan make:controller Auth/TelegramWebhookController --no-interaction
```

**Step 2: Write controller content**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    /**
     * Handle incoming Telegram webhook.
     */
    public function handle(Request $request): JsonResponse
    {
        $update = $request->all();

        // Handle contact sharing (phone verification)
        if (isset($update['message']['contact'])) {
            $this->handleContact($update);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Handle contact message (phone number sharing).
     */
    private function handleContact(array $update): void
    {
        $message = $update['message'];
        $contact = $message['contact'];
        $fromUserId = $message['from']['id'];

        // Verify the contact belongs to the sender (not a forwarded contact)
        if (($contact['user_id'] ?? null) !== $fromUserId) {
            $this->sendMessage($fromUserId, __('auth.telegram.phone_must_be_yours'));

            return;
        }

        $user = User::where('telegram_id', $fromUserId)->first();

        if (! $user) {
            $this->sendMessage($fromUserId, __('auth.telegram.user_not_found'));

            return;
        }

        // Update user with verified phone
        $user->update([
            'phone' => $this->normalizePhone($contact['phone_number']),
            'phone_verified_at' => now(),
        ]);

        // Send confirmation and remove keyboard
        $this->sendMessage(
            $fromUserId,
            __('auth.telegram.phone_verified'),
            ['remove_keyboard' => true]
        );
    }

    /**
     * Send message via Telegram bot.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     */
    private function sendMessage(int $chatId, string $text, ?array $replyMarkup = null): void
    {
        $telegram = app('telegram.bot');

        $params = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup) {
            $params['reply_markup'] = json_encode($replyMarkup);
        }

        $telegram->sendMessage($params);
    }

    /**
     * Normalize phone number format.
     */
    private function normalizePhone(string $phone): string
    {
        // Ensure phone starts with +
        if (! str_starts_with($phone, '+')) {
            $phone = '+' . $phone;
        }

        return $phone;
    }
}
```

**Step 3: Commit**

```bash
git add app/Http/Controllers/Auth/TelegramWebhookController.php
git commit -m "feat: add TelegramWebhookController for phone verification"
```

---

## Task 9: Update User Model

**Files:**
- Modify: `app/Models/User.php`

**Step 1: Update fillable and casts**

In `app/Models/User.php`, update the `$fillable` array:

```php
protected $fillable = [
    'name',
    'telegram_id',
    'telegram_username',
    'telegram_photo_url',
    'email',
    'language',
    'country_id',
    'experience_level',
    'theme',
    'investment_goal',
    'risk_level',
    'trading_style',
    'phone',
    'onboarding_completed_at',
];
```

**Step 2: Remove password-related methods**

Remove these methods:
- `generatePhoneVerificationCode()`
- `isPhoneVerificationCodeValid()`

Remove from `casts()`:
- `'password' => 'hashed'`
- `'phone_verification_expires_at' => 'datetime'`

**Step 3: Commit**

```bash
git add app/Models/User.php
git commit -m "feat: update User model for telegram auth"
```

---

## Task 10: Update PhoneVerificationController

**Files:**
- Modify: `app/Http/Controllers/Auth/PhoneVerificationController.php`

**Step 1: Simplify controller**

Replace content with:

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    /**
     * Show the phone verification page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return redirect()->intended(route('verification.notice'));
        }

        return Inertia::render('auth/VerifyPhone', [
            'botUsername' => config('telegram.bot_username'),
            'telegramId' => $request->user()->telegram_id,
        ]);
    }

    /**
     * Check phone verification status (for polling).
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json([
            'verified' => $request->user()->hasVerifiedPhone(),
        ]);
    }

    /**
     * Resend phone verification request via Telegram bot.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        if ($user->telegram_id) {
            $telegram = app('telegram.bot');

            $telegram->sendMessage([
                'chat_id' => $user->telegram_id,
                'text' => __('auth.telegram.verify_phone_message'),
                'reply_markup' => json_encode([
                    'keyboard' => [[
                        [
                            'text' => __('auth.telegram.share_phone_button'),
                            'request_contact' => true,
                        ],
                    ]],
                    'resize_keyboard' => true,
                    'one_time_keyboard' => true,
                ]),
            ]);
        }

        return back()->with('status', 'verification-request-sent');
    }
}
```

**Step 2: Commit**

```bash
git add app/Http/Controllers/Auth/PhoneVerificationController.php
git commit -m "refactor: simplify PhoneVerificationController for telegram"
```

---

## Task 11: Update Routes

**Files:**
- Modify: `routes/web.php`

**Step 1: Add Telegram auth routes**

Add before the phone verification routes:

```php
use App\Http\Controllers\Auth\TelegramAuthController;
use App\Http\Controllers\Auth\TelegramWebhookController;

// Telegram Auth Routes (guest only)
Route::middleware('guest')->group(function () {
    Route::get('auth/telegram', [TelegramAuthController::class, 'show'])
        ->name('auth.telegram');
    Route::get('auth/telegram/callback', [TelegramAuthController::class, 'callback'])
        ->name('auth.telegram.callback');
});

// Telegram Webhook (no auth, validated by Telegram)
Route::post('telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook')
    ->withoutMiddleware(['web']);
```

**Step 2: Update phone verification routes**

Replace the existing phone verification routes:

```php
// Phone Verification Routes
Route::middleware('auth')->group(function () {
    Route::get('verify-phone', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'show'])
        ->name('verification.phone');
    Route::get('api/user/phone-status', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'status'])
        ->name('phone.status');
    Route::post('verify-phone/resend', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'resend'])
        ->name('verification.phone.resend');
});
```

**Step 3: Commit**

```bash
git add routes/web.php
git commit -m "feat: add telegram auth routes"
```

---

## Task 12: Update Fortify Configuration

**Files:**
- Modify: `config/fortify.php`

**Step 1: Disable password features**

Update features array:

```php
'features' => [
    // Features::registration(),     // Disabled - using Telegram
    // Features::resetPasswords(),   // Disabled - no passwords
    Features::emailVerification(),   // Keep for optional email
    // Features::twoFactorAuthentication([...]), // Disabled - Telegram is 2FA
],
```

**Step 2: Disable views**

```php
'views' => false,
```

**Step 3: Commit**

```bash
git add config/fortify.php
git commit -m "feat: disable Fortify password features"
```

---

## Task 13: Create TelegramAuth.vue Page

**Files:**
- Create: `resources/js/pages/auth/TelegramAuth.vue`

**Step 1: Create the Vue component**

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    botUsername: string;
    errors?: {
        telegram?: string;
    };
}>();

const widgetLoaded = ref(false);

const onTelegramAuth = (user: Record<string, unknown>) => {
    const params = new URLSearchParams(user as Record<string, string>);
    window.location.href = `/auth/telegram/callback?${params.toString()}`;
};

onMounted(() => {
    // Expose callback to global scope for Telegram widget
    (window as unknown as Record<string, unknown>).onTelegramAuth = onTelegramAuth;

    // Load Telegram widget script
    const script = document.createElement('script');
    script.src = 'https://telegram.org/js/telegram-widget.js?22';
    script.setAttribute('data-telegram-login', props.botUsername);
    script.setAttribute('data-size', 'large');
    script.setAttribute('data-radius', '8');
    script.setAttribute('data-request-access', 'write');
    script.setAttribute('data-onauth', 'onTelegramAuth(user)');
    script.async = true;
    script.onload = () => {
        widgetLoaded.value = true;
    };

    document.getElementById('telegram-widget-container')?.appendChild(script);
});
</script>

<template>
    <AuthBase
        :title="t('auth.telegram.title')"
        :description="t('auth.telegram.description')"
    >
        <Head :title="t('auth.telegram.title')" />

        <div
            v-if="errors?.telegram"
            class="mb-4 text-center text-sm font-medium text-red-600"
        >
            {{ errors.telegram }}
        </div>

        <div class="flex flex-col items-center gap-6">
            <div
                id="telegram-widget-container"
                class="flex min-h-[48px] items-center justify-center"
            >
                <div
                    v-if="!widgetLoaded"
                    class="h-12 w-48 animate-pulse rounded-lg bg-muted"
                />
            </div>

            <p class="text-center text-sm text-muted-foreground">
                {{ t('auth.telegram.terms') }}
            </p>
        </div>
    </AuthBase>
</template>
```

**Step 2: Commit**

```bash
git add resources/js/pages/auth/TelegramAuth.vue
git commit -m "feat: add TelegramAuth.vue page"
```

---

## Task 14: Update VerifyPhone.vue Page

**Files:**
- Modify: `resources/js/pages/auth/VerifyPhone.vue`

**Step 1: Replace with Telegram-based verification**

```vue
<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    botUsername: string;
    telegramId: number;
    status?: string;
}>();

const checking = ref(false);
const resending = ref(false);
let pollInterval: ReturnType<typeof setInterval> | null = null;

const openTelegram = () => {
    window.open(`https://t.me/${props.botUsername}`, '_blank');
};

const checkStatus = async () => {
    checking.value = true;
    try {
        const response = await fetch('/api/user/phone-status');
        const data = await response.json();
        if (data.verified) {
            router.visit('/onboarding');
        }
    } finally {
        checking.value = false;
    }
};

const resendRequest = () => {
    resending.value = true;
    router.post('/verify-phone/resend', {}, {
        onFinish: () => {
            resending.value = false;
        },
    });
};

onMounted(() => {
    // Poll every 3 seconds
    pollInterval = setInterval(checkStatus, 3000);
});

onUnmounted(() => {
    if (pollInterval) {
        clearInterval(pollInterval);
    }
});
</script>

<template>
    <AuthLayout
        :title="t('auth.verifyPhone.title')"
        :description="t('auth.verifyPhone.telegramDescription')"
    >
        <Head :title="t('auth.verifyPhone.title')" />

        <div
            v-if="status === 'verification-request-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ t('auth.verifyPhone.requestSent') }}
        </div>

        <div class="flex flex-col items-center gap-6">
            <Button
                size="lg"
                class="w-full gap-2"
                @click="openTelegram"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.161c-.18 1.897-.962 6.502-1.359 8.627-.168.9-.5 1.201-.82 1.23-.697.064-1.226-.461-1.901-.903-1.056-.692-1.653-1.123-2.678-1.799-1.185-.781-.417-1.21.258-1.911.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.139-5.062 3.345-.479.329-.913.489-1.302.481-.428-.009-1.252-.242-1.865-.442-.751-.244-1.349-.374-1.297-.789.027-.216.324-.437.893-.663 3.498-1.524 5.831-2.529 6.998-3.015 3.333-1.386 4.025-1.627 4.477-1.635.099-.002.321.023.465.141.121.099.155.232.17.324.015.092.033.301.019.465z"/>
                </svg>
                {{ t('auth.verifyPhone.openTelegram') }}
            </Button>

            <div class="flex items-center gap-2 text-sm text-muted-foreground">
                <Spinner v-if="checking" class="h-4 w-4" />
                <span>{{ t('auth.verifyPhone.waitingForVerification') }}</span>
            </div>

            <div class="w-full rounded-lg border border-border bg-muted/50 p-4">
                <h3 class="mb-2 font-medium">{{ t('auth.verifyPhone.howItWorks') }}</h3>
                <ol class="list-inside list-decimal space-y-1 text-sm text-muted-foreground">
                    <li>{{ t('auth.verifyPhone.step1') }}</li>
                    <li>{{ t('auth.verifyPhone.step2') }}</li>
                    <li>{{ t('auth.verifyPhone.step3') }}</li>
                    <li>{{ t('auth.verifyPhone.step4') }}</li>
                </ol>
            </div>

            <button
                type="button"
                class="text-sm text-muted-foreground underline underline-offset-4 hover:text-foreground"
                :disabled="resending"
                @click="resendRequest"
            >
                <Spinner v-if="resending" class="mr-2 inline h-3 w-3" />
                {{ t('auth.verifyPhone.resendRequest') }}
            </button>
        </div>
    </AuthLayout>
</template>
```

**Step 2: Commit**

```bash
git add resources/js/pages/auth/VerifyPhone.vue
git commit -m "refactor: update VerifyPhone.vue for telegram verification"
```

---

## Task 15: Add Translations

**Files:**
- Modify: `resources/js/i18n/en.json`
- Modify: `resources/js/i18n/ar.json`

**Step 1: Add English translations**

Add to `en.json` under `auth`:

```json
"telegram": {
    "title": "Welcome to Horin",
    "description": "Sign in with your Telegram account to continue",
    "terms": "By continuing, you agree to our Terms of Service and Privacy Policy",
    "invalid": "Invalid Telegram authentication. Please try again.",
    "verify_phone_message": "Welcome to Horin! Please share your phone number to complete verification.",
    "share_phone_button": "📱 Share Phone Number",
    "phone_verified": "✅ Phone verified! You can now return to the app.",
    "phone_must_be_yours": "Please share your own phone number, not a contact.",
    "user_not_found": "User not found. Please sign in first."
},
"verifyPhone": {
    "telegramDescription": "Verify your phone number through Telegram",
    "openTelegram": "Open Telegram to Verify",
    "waitingForVerification": "Waiting for verification...",
    "requestSent": "Verification request sent to Telegram",
    "resendRequest": "Resend verification request",
    "howItWorks": "How it works",
    "step1": "Tap the button above to open Telegram",
    "step2": "Open the chat with our bot",
    "step3": "Tap \"Share Phone Number\"",
    "step4": "You'll be redirected automatically"
}
```

**Step 2: Add Arabic translations**

Add to `ar.json` under `auth`:

```json
"telegram": {
    "title": "مرحباً بك في حورين",
    "description": "سجّل الدخول بحساب تيليجرام للمتابعة",
    "terms": "بالمتابعة، أنت توافق على شروط الخدمة وسياسة الخصوصية",
    "invalid": "فشل التحقق من تيليجرام. يرجى المحاولة مرة أخرى.",
    "verify_phone_message": "مرحباً بك في حورين! يرجى مشاركة رقم هاتفك لإتمام التحقق.",
    "share_phone_button": "📱 مشاركة رقم الهاتف",
    "phone_verified": "✅ تم التحقق من الهاتف! يمكنك الآن العودة للتطبيق.",
    "phone_must_be_yours": "يرجى مشاركة رقم هاتفك الخاص، وليس جهة اتصال.",
    "user_not_found": "المستخدم غير موجود. يرجى تسجيل الدخول أولاً."
},
"verifyPhone": {
    "telegramDescription": "تحقق من رقم هاتفك عبر تيليجرام",
    "openTelegram": "افتح تيليجرام للتحقق",
    "waitingForVerification": "في انتظار التحقق...",
    "requestSent": "تم إرسال طلب التحقق إلى تيليجرام",
    "resendRequest": "إعادة إرسال طلب التحقق",
    "howItWorks": "كيف يعمل",
    "step1": "اضغط على الزر أعلاه لفتح تيليجرام",
    "step2": "افتح المحادثة مع البوت",
    "step3": "اضغط على \"مشاركة رقم الهاتف\"",
    "step4": "ستتم إعادة توجيهك تلقائياً"
}
```

**Step 3: Commit**

```bash
git add resources/js/i18n/en.json resources/js/i18n/ar.json
git commit -m "feat: add telegram auth translations"
```

---

## Task 16: Delete Old Auth Pages

**Files:**
- Delete: `resources/js/pages/auth/Login.vue`
- Delete: `resources/js/pages/auth/Register.vue`
- Delete: `resources/js/pages/auth/ForgotPassword.vue`
- Delete: `resources/js/pages/auth/ResetPassword.vue`
- Delete: `resources/js/pages/auth/ConfirmPassword.vue`

**Step 1: Delete files**

Run:
```bash
rm resources/js/pages/auth/Login.vue
rm resources/js/pages/auth/Register.vue
rm resources/js/pages/auth/ForgotPassword.vue
rm resources/js/pages/auth/ResetPassword.vue
rm resources/js/pages/auth/ConfirmPassword.vue
```

**Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove old password-based auth pages"
```

---

## Task 17: Delete Old Fortify Actions

**Files:**
- Delete: `app/Actions/Fortify/CreateNewUser.php`
- Delete: `app/Actions/Fortify/ResetUserPassword.php`
- Delete: `app/Actions/Fortify/PasswordValidationRules.php`
- Delete: `app/Http/Requests/VerifyPhoneRequest.php`

**Step 1: Delete files**

Run:
```bash
rm app/Actions/Fortify/CreateNewUser.php
rm app/Actions/Fortify/ResetUserPassword.php
rm app/Actions/Fortify/PasswordValidationRules.php
rm app/Http/Requests/VerifyPhoneRequest.php
```

**Step 2: Commit**

```bash
git add -A
git commit -m "chore: remove old Fortify actions and requests"
```

---

## Task 18: Update FortifyServiceProvider

**Files:**
- Modify: `app/Providers/FortifyServiceProvider.php`

**Step 1: Remove password-related view registrations**

Remove calls to:
- `Fortify::loginView()`
- `Fortify::registerView()`
- `Fortify::requestPasswordResetLinkView()`
- `Fortify::resetPasswordView()`
- `Fortify::confirmPasswordView()`

Keep only:
- `Fortify::verifyEmailView()` (if used)
- `Fortify::twoFactorChallengeView()` (if keeping 2FA)

**Step 2: Commit**

```bash
git add app/Providers/FortifyServiceProvider.php
git commit -m "refactor: remove password views from FortifyServiceProvider"
```

---

## Task 19: Register Telegram Service Provider

**Files:**
- Modify: `bootstrap/providers.php` or create service provider

**Step 1: Check telegram-bot-sdk auto-discovery**

The package should auto-register. If not, add to `bootstrap/providers.php`:

```php
Telegram\Bot\Laravel\TelegramServiceProvider::class,
```

**Step 2: Publish config if needed**

Run:
```bash
php artisan vendor:publish --provider="Telegram\Bot\Laravel\TelegramServiceProvider"
```

**Step 3: Commit if changes made**

```bash
git add -A
git commit -m "chore: configure telegram service provider"
```

---

## Task 20: Update Middleware Redirects

**Files:**
- Modify: `bootstrap/app.php`

**Step 1: Update guest redirect**

Ensure unauthenticated users redirect to Telegram auth:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->redirectGuestsTo('/auth/telegram');
    // ... rest of middleware config
})
```

**Step 2: Commit**

```bash
git add bootstrap/app.php
git commit -m "feat: redirect guests to telegram auth"
```

---

## Task 21: Build Frontend and Verify

**Step 1: Build frontend**

Run:
```bash
npm run build
```

**Step 2: Generate Wayfinder routes**

Run:
```bash
php artisan wayfinder:generate
```

**Step 3: Run Pint**

Run:
```bash
vendor/bin/pint
```

**Step 4: Commit any formatting changes**

```bash
git add -A
git commit -m "chore: format code with pint"
```

---

## Task 22: Final Verification

**Step 1: Check route list**

Run:
```bash
php artisan route:list --path=telegram
php artisan route:list --path=verify-phone
```

Expected: See telegram auth and webhook routes

**Step 2: Verify config loads**

Run:
```bash
php artisan config:show telegram
```

Expected: Telegram config values displayed

**Step 3: Create final commit**

```bash
git add -A
git commit -m "feat: complete telegram authentication implementation"
```

---

## Post-Implementation Setup

After code is deployed:

1. Create Telegram bot via @BotFather
2. Get bot token and username
3. Run `/setdomain` in BotFather to link your domain
4. Add credentials to `.env`:
   ```env
   TELEGRAM_BOT_TOKEN=your-bot-token
   TELEGRAM_BOT_USERNAME=YourBotUsername
   TELEGRAM_WEBHOOK_SECRET=random-secret
   ```
5. Set webhook URL:
   ```bash
   php artisan telegram:webhook --setup
   ```
