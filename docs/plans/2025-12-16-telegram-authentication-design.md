# Telegram Authentication Design

## Problem

Phone verification via SMS costs $0.05-$0.15 per user. This design eliminates that cost by using Telegram for authentication and phone verification.

## Solution

Replace email/password authentication with Telegram Login. Users authenticate via Telegram widget, then verify their phone number by sharing it through a Telegram bot. Cost: $0.00.

## Architecture

```
┌─────────────┐       ┌─────────────┐       ┌─────────────┐
│  Frontend   │◄─────►│  Telegram   │◄─────►│  User's     │
│  (Vue)      │       │  Servers    │       │  Telegram   │
└──────┬──────┘       └──────┬──────┘       └─────────────┘
       │                     │
       ▼                     ▼
┌─────────────┐       ┌─────────────┐
│  Backend    │◄──────│  Bot API    │
│  (Laravel)  │webhook│  (phone)    │
└─────────────┘       └─────────────┘
```

## User Flows

### Registration & Login

1. User clicks "Continue with Telegram"
2. Telegram widget opens, user authorizes
3. Backend validates hash, creates/finds user by `telegram_id`
4. User logged in, redirected to phone verification (if needed)

### Phone Verification

1. User sees "Open Telegram to Verify" button
2. User opens chat with bot
3. Bot sends message with "Share Phone Number" button
4. User taps button, Telegram shares verified phone
5. Webhook receives phone, updates user record
6. Frontend detects verification via polling, redirects to onboarding

## Database Changes

### Add Columns

| Column | Type | Notes |
|--------|------|-------|
| `telegram_id` | bigint | Unique, indexed, primary auth identifier |
| `telegram_username` | string | Nullable |
| `telegram_photo_url` | string | Nullable |

### Modify Columns

| Column | Change |
|--------|--------|
| `email` | Make nullable (collect later) |

### Remove Columns

| Column | Reason |
|--------|--------|
| `password` | No password authentication |
| `phone_verification_code` | Bot provides verified phone |
| `phone_verification_expires_at` | No OTP flow |

## Files to Create

| File | Purpose |
|------|---------|
| `app/Http/Controllers/Auth/TelegramAuthController.php` | Handle Telegram callback |
| `app/Http/Controllers/Auth/TelegramWebhookController.php` | Receive phone from bot |
| `app/Services/TelegramHashValidator.php` | Validate widget hash |
| `config/telegram.php` | Bot configuration |
| `resources/js/pages/auth/TelegramAuth.vue` | Auth page with Telegram button |
| `database/migrations/*_add_telegram_columns_to_users.php` | Schema changes |
| `database/migrations/*_make_email_nullable_on_users.php` | Email optional |
| `database/migrations/*_remove_password_from_users.php` | Remove password |

## Files to Modify

| File | Changes |
|------|---------|
| `app/Models/User.php` | Add telegram fields, remove password cast |
| `app/Http/Middleware/EnsurePhoneIsVerified.php` | Simplify logic |
| `resources/js/pages/auth/VerifyPhone.vue` | Bot link instead of OTP |
| `routes/web.php` | Telegram auth routes |
| `config/fortify.php` | Disable password features |
| `.env` | Add bot token and username |

## Files to Delete

| File | Reason |
|------|--------|
| `resources/js/pages/auth/Login.vue` | Replaced by TelegramAuth |
| `resources/js/pages/auth/Register.vue` | Replaced by TelegramAuth |
| `resources/js/pages/auth/ForgotPassword.vue` | No passwords |
| `resources/js/pages/auth/ResetPassword.vue` | No passwords |
| `app/Http/Requests/VerifyPhoneRequest.php` | No OTP validation |
| `app/Actions/Fortify/CreateNewUser.php` | Telegram creates users |
| `app/Actions/Fortify/ResetUserPassword.php` | No passwords |
| `app/Actions/Fortify/UpdateUserPassword.php` | No passwords |

## Routes

### New Routes

```php
// Telegram Auth (guest)
Route::get('/auth/telegram', [TelegramAuthController::class, 'show'])
    ->name('auth.telegram');
Route::get('/auth/telegram/callback', [TelegramAuthController::class, 'callback'])
    ->name('auth.telegram.callback');

// Phone Verification (authenticated)
Route::middleware('auth')->group(function () {
    Route::get('/verify-phone', [PhoneVerificationController::class, 'show'])
        ->name('phone.verify');
    Route::get('/api/user/phone-status', [PhoneVerificationController::class, 'status'])
        ->name('phone.status');
});

// Telegram Webhook (validated by hash)
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');
```

### Remove Routes

All Fortify routes for login, register, password reset.

## Security

### Hash Validation

Telegram widget returns a hash computed from user data. Verify server-side:

```php
$secret_key = hash('sha256', $bot_token, true);
$computed = hash_hmac('sha256', $data_check_string, $secret_key);
$valid = hash_equals($computed, $received_hash);
```

### Replay Prevention

Check `auth_date` is within 24 hours.

### Phone Ownership

Verify `contact.user_id` matches `message.from.id` to ensure user shared their own phone, not a forwarded contact.

## Environment Variables

```env
TELEGRAM_BOT_TOKEN=123456789:ABCdefGHIjklMNOpqrsTUVwxyz
TELEGRAM_BOT_USERNAME=HorinBot
```

## Dependencies

```bash
composer require telegram-bot-sdk/telegram-bot-sdk
```

## Setup Steps

1. Create bot via @BotFather
2. Get token and username
3. Run `/setdomain` in BotFather to link your domain
4. Run `php artisan telegram:webhook:setup`

## Cost Savings

| Volume | SMS Cost | Telegram Cost | Savings |
|--------|----------|---------------|---------|
| 1,000/month | $50-150 | $0 | $50-150 |
| 10,000/month | $500-1,500 | $0 | $500-1,500 |
