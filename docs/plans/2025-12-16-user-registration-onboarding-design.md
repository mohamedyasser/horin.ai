# User Registration & Onboarding Design

## Overview

Complete user registration flow with phone/email verification and mandatory onboarding wizard for collecting investment preferences.

## User Flow

```
Register → Phone OTP → Email Verify → Onboarding (4 steps) → Dashboard
```

## 1. Registration Page (`/register`)

Single-page form collecting all required fields:

| Field | Type | Validation |
|-------|------|------------|
| name | text | required, max 255 |
| email | email | required, unique |
| phone | tel | required, unique, with country code |
| password | password | required, min 8, confirmed |
| password_confirmation | password | required |

**Phone Input:** Country code selector defaulting based on user's locale. Stores full international format (e.g., +201012345678).

**On Submit:** Creates user account, sends OTP to phone, redirects to `/verify-phone`.

## 2. Phone Verification (`/verify-phone`)

- Displays masked phone number (e.g., +20 10** *** **45)
- 6-digit OTP input with auto-focus progression
- "Resend OTP" button with 60-second cooldown timer
- OTP expires after 10 minutes
- On success: Sets `phone_verified_at`, redirects to `/verify-email`

## 3. Email Verification (`/verify-email`)

- Message: "We sent a verification link to your email"
- Shows masked email (e.g., m***@example.com)
- "Resend email" button with cooldown
- Polls server (or websocket) to detect verification
- On success: Redirects to `/onboarding`

## 4. Onboarding Wizard (`/onboarding`)

Mandatory 4-step wizard. Cannot be skipped.

### Step 1: Investment Profile
- **Experience level:** Beginner / Intermediate / Advanced (single select cards)
- **Risk level:** Conservative / Moderate / Aggressive (single select cards)

### Step 2: Investment Goals
- **Investment goal:** Single select from options:
  - Capital Growth, Fixed Income, Risk Reduction, Short-term Speculation
  - Retirement Planning, Wealth Preservation, Passive Income
  - Education Savings, Home Purchase, Emergency Fund
- **Trading style:** Day Trading / Swing Trading / Position Trading / Scalping

### Step 3: Location & Markets
- **Country:** Searchable dropdown
- **Preferred markets:** Multi-select checkboxes with flags
  - Egypt, Saudi Arabia, UAE, Kuwait, Qatar, Bahrain
  - Minimum 1 required

### Step 4: Sectors
- **Preferred sectors:** Multi-select card grid with icons
  - Banking, Technology, Real Estate, Healthcare, Energy, etc.
  - Minimum 1 required

**Wizard UI:**
- Progress indicator: "Step X of 4"
- Back / Next navigation buttons
- Final step: "Complete Setup" button
- On completion: Sets `onboarding_completed_at`, redirects to dashboard

## 5. Database Changes

### Migration: Modify Users Table
```php
// Make phone nullable (for account creation before verification)
$table->string('phone')->unique()->nullable()->change();

// Add verification columns
$table->string('phone_verification_code', 6)->nullable();
$table->timestamp('phone_verification_expires_at')->nullable();
$table->timestamp('onboarding_completed_at')->nullable();
```

## 6. Routes

```php
// Registration (Fortify handles POST /register)
// Customize RegisteredUserController to redirect to verify-phone

// Phone Verification
Route::middleware('auth')->group(function () {
    Route::get('verify-phone', [PhoneVerificationController::class, 'show'])
        ->name('verification.phone');
    Route::post('verify-phone', [PhoneVerificationController::class, 'verify'])
        ->name('verification.phone.verify');
    Route::post('verify-phone/resend', [PhoneVerificationController::class, 'resend'])
        ->name('verification.phone.resend');
});

// Email Verification (Fortify handles this)
// Customize to require phone verification first

// Onboarding
Route::middleware(['auth', 'verified', 'phone.verified'])->group(function () {
    Route::get('onboarding', [OnboardingController::class, 'show'])
        ->name('onboarding');
    Route::post('onboarding', [OnboardingController::class, 'store'])
        ->name('onboarding.store');
});
```

## 7. Middleware

### EnsurePhoneIsVerified
- Check: `auth()->user()->phone_verified_at !== null`
- Redirect: `/verify-phone`
- Apply to: email verification, onboarding, dashboard routes

### EnsureOnboardingComplete
- Check: `auth()->user()->onboarding_completed_at !== null`
- Redirect: `/onboarding`
- Apply to: dashboard and all authenticated app routes

### Route Protection Chain
```
/verify-phone    → auth
/verify-email    → auth + phone.verified
/onboarding      → auth + verified + phone.verified
/{locale}/dashboard → auth + verified + phone.verified + onboarding.complete
```

## 8. Profile Settings Integration

Add to existing settings page (`/settings/profile`):

### Investment Profile Section
- Experience level dropdown
- Risk level dropdown
- Investment goal dropdown
- Trading style dropdown

### Market Preferences Section
- Country dropdown
- Preferred markets multi-select
- Preferred sectors multi-select

Same validation as onboarding (minimum 1 market, 1 sector required).

## 9. Frontend Components

### New Pages (Vue/Inertia)
- `pages/auth/VerifyPhone.vue`
- `pages/auth/VerifyEmail.vue` (customize existing)
- `pages/Onboarding.vue`

### New Components
- `PhoneInput.vue` - Phone input with country code selector
- `OtpInput.vue` - 6-digit OTP input with auto-focus
- `OnboardingWizard.vue` - Multi-step wizard wrapper
- `SelectableCard.vue` - Card for single/multi select options
- `CardGrid.vue` - Responsive grid for selectable cards

## 10. Translations

Add i18n keys for both `ar.json` and `en.json`:

```
auth.register.phoneLabel
auth.register.phonePlaceholder
auth.verifyPhone.title
auth.verifyPhone.description
auth.verifyPhone.otpLabel
auth.verifyPhone.resendButton
auth.verifyPhone.resendIn
auth.verifyEmail.title
auth.verifyEmail.description
auth.verifyEmail.checkInbox
auth.verifyEmail.resendButton
onboarding.title
onboarding.step1.title (Investment Profile)
onboarding.step2.title (Investment Goals)
onboarding.step3.title (Location & Markets)
onboarding.step4.title (Sectors)
onboarding.experienceLevel.*
onboarding.riskLevel.*
onboarding.investmentGoal.*
onboarding.tradingStyle.*
onboarding.markets.*
onboarding.sectors.*
onboarding.buttons.back
onboarding.buttons.next
onboarding.buttons.complete
settings.investmentProfile.*
settings.marketPreferences.*
```

## 11. SMS Provider

Phone OTP requires an SMS provider. Options:
- Twilio
- Vonage (Nexmo)
- Local providers for MENA region

Configuration via environment variables:
```
SMS_DRIVER=twilio
TWILIO_SID=xxx
TWILIO_TOKEN=xxx
TWILIO_FROM=+1234567890
```
