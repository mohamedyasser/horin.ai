# Registration & Onboarding Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implement complete user registration with phone/email verification and mandatory onboarding wizard.

**Architecture:** Single-page registration form → two-step verification (phone OTP, then email) → 4-step onboarding wizard → dashboard. Three new middleware ensure users complete each step before proceeding.

**Tech Stack:** Laravel 12, Fortify, Inertia.js v2, Vue 3, Tailwind CSS v4

---

## Task 1: Database Migration for Phone Verification

**Files:**
- Create: `database/migrations/2025_12_16_000001_add_phone_verification_columns.php`

**Step 1: Create migration**

Run:
```bash
php artisan make:migration add_phone_verification_columns --table=users --no-interaction
```

**Step 2: Add migration content**

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
            // Make phone nullable for registration flow
            $table->string('phone')->nullable()->change();

            // Phone verification columns
            $table->string('phone_verification_code', 6)->nullable()->after('phone');
            $table->timestamp('phone_verification_expires_at')->nullable()->after('phone_verification_code');

            // Onboarding tracking
            $table->timestamp('onboarding_completed_at')->nullable()->after('phone_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone_verification_code',
                'phone_verification_expires_at',
                'onboarding_completed_at',
            ]);
        });
    }
};
```

**Step 3: Commit**

```bash
git add database/migrations/
git commit -m "feat: add phone verification and onboarding columns to users table"
```

---

## Task 2: Update User Model

**Files:**
- Modify: `app/Models/User.php`

**Step 1: Add fillable fields and helper methods**

Add to `$fillable` array:
```php
'phone_verification_code',
'phone_verification_expires_at',
'onboarding_completed_at',
```

Add to `casts()` method:
```php
'phone_verification_expires_at' => 'datetime',
'onboarding_completed_at' => 'datetime',
```

Add helper methods after existing methods:
```php
public function hasVerifiedPhone(): bool
{
    return $this->phone_verified_at !== null;
}

public function hasCompletedOnboarding(): bool
{
    return $this->onboarding_completed_at !== null;
}

public function markPhoneAsVerified(): bool
{
    return $this->forceFill([
        'phone_verified_at' => $this->freshTimestamp(),
        'phone_verification_code' => null,
        'phone_verification_expires_at' => null,
    ])->save();
}

public function markOnboardingAsComplete(): bool
{
    return $this->forceFill([
        'onboarding_completed_at' => $this->freshTimestamp(),
    ])->save();
}

public function generatePhoneVerificationCode(): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    $this->forceFill([
        'phone_verification_code' => $code,
        'phone_verification_expires_at' => now()->addMinutes(10),
    ])->save();

    return $code;
}

public function isPhoneVerificationCodeValid(string $code): bool
{
    return $this->phone_verification_code === $code
        && $this->phone_verification_expires_at
        && $this->phone_verification_expires_at->isFuture();
}
```

**Step 2: Commit**

```bash
git add app/Models/User.php
git commit -m "feat: add phone verification and onboarding methods to User model"
```

---

## Task 3: Create EnsurePhoneIsVerified Middleware

**Files:**
- Create: `app/Http/Middleware/EnsurePhoneIsVerified.php`

**Step 1: Create middleware**

Run:
```bash
php artisan make:middleware EnsurePhoneIsVerified --no-interaction
```

**Step 2: Implement middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePhoneIsVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasVerifiedPhone()) {
            return $request->expectsJson()
                ? abort(403, 'Your phone number is not verified.')
                : redirect()->route('verification.phone');
        }

        return $next($request);
    }
}
```

**Step 3: Register middleware alias in bootstrap/app.php**

Add to `withMiddleware` callback:
```php
$middleware->alias([
    'phone.verified' => \App\Http\Middleware\EnsurePhoneIsVerified::class,
]);
```

**Step 4: Commit**

```bash
git add app/Http/Middleware/EnsurePhoneIsVerified.php bootstrap/app.php
git commit -m "feat: add EnsurePhoneIsVerified middleware"
```

---

## Task 4: Create EnsureOnboardingComplete Middleware

**Files:**
- Create: `app/Http/Middleware/EnsureOnboardingComplete.php`

**Step 1: Create middleware**

Run:
```bash
php artisan make:middleware EnsureOnboardingComplete --no-interaction
```

**Step 2: Implement middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->hasCompletedOnboarding()) {
            return $request->expectsJson()
                ? abort(403, 'Please complete onboarding first.')
                : redirect()->route('onboarding');
        }

        return $next($request);
    }
}
```

**Step 3: Register middleware alias in bootstrap/app.php**

Add to existing aliases:
```php
'onboarding.complete' => \App\Http\Middleware\EnsureOnboardingComplete::class,
```

**Step 4: Commit**

```bash
git add app/Http/Middleware/EnsureOnboardingComplete.php bootstrap/app.php
git commit -m "feat: add EnsureOnboardingComplete middleware"
```

---

## Task 5: Create PhoneVerificationController

**Files:**
- Create: `app/Http/Controllers/Auth/PhoneVerificationController.php`
- Create: `app/Http/Requests/VerifyPhoneRequest.php`

**Step 1: Create controller**

Run:
```bash
php artisan make:controller Auth/PhoneVerificationController --no-interaction
```

**Step 2: Create form request**

Run:
```bash
php artisan make:request VerifyPhoneRequest --no-interaction
```

**Step 3: Implement VerifyPhoneRequest**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyPhoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:6'],
        ];
    }
}
```

**Step 4: Implement PhoneVerificationController**

```php
<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\VerifyPhoneRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return redirect()->intended(route('verification.notice'));
        }

        return Inertia::render('auth/VerifyPhone', [
            'phone' => $this->maskPhone($request->user()->phone),
        ]);
    }

    public function verify(VerifyPhoneRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->isPhoneVerificationCodeValid($request->code)) {
            return back()->withErrors([
                'code' => __('The verification code is invalid or has expired.'),
            ]);
        }

        $user->markPhoneAsVerified();

        return redirect()->route('verification.notice');
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        $code = $user->generatePhoneVerificationCode();

        // TODO: Send SMS with $code
        // SmsService::send($user->phone, "Your verification code is: {$code}");

        return back()->with('status', 'verification-code-sent');
    }

    private function maskPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $length = strlen($phone);
        if ($length <= 6) {
            return $phone;
        }

        return substr($phone, 0, 4) . str_repeat('*', $length - 6) . substr($phone, -2);
    }
}
```

**Step 5: Commit**

```bash
git add app/Http/Controllers/Auth/PhoneVerificationController.php app/Http/Requests/VerifyPhoneRequest.php
git commit -m "feat: add PhoneVerificationController with OTP verification"
```

---

## Task 6: Create OnboardingController

**Files:**
- Create: `app/Http/Controllers/OnboardingController.php`
- Create: `app/Http/Requests/OnboardingRequest.php`

**Step 1: Create controller**

Run:
```bash
php artisan make:controller OnboardingController --no-interaction
```

**Step 2: Create form request**

Run:
```bash
php artisan make:request OnboardingRequest --no-interaction
```

**Step 3: Implement OnboardingRequest**

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnboardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $step = $this->input('step', 1);

        return match ((int) $step) {
            1 => [
                'step' => ['required', 'integer', 'in:1'],
                'experience_level' => ['required', Rule::in(['beginner', 'intermediate', 'advanced'])],
                'risk_level' => ['required', Rule::in(['conservative', 'moderate', 'aggressive'])],
            ],
            2 => [
                'step' => ['required', 'integer', 'in:2'],
                'investment_goal' => ['required', Rule::in([
                    'capital_growth', 'fixed_income', 'risk_reduction', 'short_term_speculation',
                    'retirement_planning', 'wealth_preservation', 'passive_income',
                    'education_savings', 'home_purchase', 'emergency_fund',
                ])],
                'trading_style' => ['required', Rule::in(['day_trading', 'swing_trading', 'position_trading', 'scalping_trading'])],
            ],
            3 => [
                'step' => ['required', 'integer', 'in:3'],
                'country_id' => ['required', 'uuid', 'exists:countries,id'],
                'markets' => ['required', 'array', 'min:1'],
                'markets.*' => ['uuid', 'exists:markets,id'],
            ],
            4 => [
                'step' => ['required', 'integer', 'in:4'],
                'sectors' => ['required', 'array', 'min:1'],
                'sectors.*' => ['uuid', 'exists:sectors,id'],
            ],
            default => [],
        };
    }
}
```

**Step 4: Implement OnboardingController**

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\OnboardingRequest;
use App\Models\Country;
use App\Models\Market;
use App\Models\Sector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasCompletedOnboarding()) {
            return redirect()->route('dashboard');
        }

        $step = (int) $request->query('step', 1);
        $step = max(1, min(4, $step));

        return Inertia::render('Onboarding', [
            'step' => $step,
            'totalSteps' => 4,
            'countries' => fn () => $step === 3 ? Country::orderBy('name')->get(['id', 'name', 'code']) : [],
            'markets' => fn () => $step === 3 ? Market::orderBy('name')->get(['id', 'name', 'code']) : [],
            'sectors' => fn () => $step === 4 ? Sector::orderBy('name')->get(['id', 'name']) : [],
            'user' => [
                'experience_level' => $request->user()->experience_level,
                'risk_level' => $request->user()->risk_level,
                'investment_goal' => $request->user()->investment_goal,
                'trading_style' => $request->user()->trading_style,
                'country_id' => $request->user()->country_id,
                'markets' => $request->user()->markets->pluck('id'),
                'sectors' => $request->user()->sectors->pluck('id'),
            ],
        ]);
    }

    public function store(OnboardingRequest $request): RedirectResponse
    {
        $user = $request->user();
        $step = (int) $request->input('step');

        match ($step) {
            1 => $user->update([
                'experience_level' => $request->experience_level,
                'risk_level' => $request->risk_level,
            ]),
            2 => $user->update([
                'investment_goal' => $request->investment_goal,
                'trading_style' => $request->trading_style,
            ]),
            3 => $this->saveStep3($user, $request),
            4 => $this->saveStep4($user, $request),
        };

        if ($step >= 4) {
            $user->markOnboardingAsComplete();
            return redirect()->route('dashboard')->with('status', 'onboarding-complete');
        }

        return redirect()->route('onboarding', ['step' => $step + 1]);
    }

    private function saveStep3($user, OnboardingRequest $request): void
    {
        $user->update(['country_id' => $request->country_id]);
        $user->markets()->sync($request->markets);
    }

    private function saveStep4($user, OnboardingRequest $request): void
    {
        $user->sectors()->sync($request->sectors);
    }
}
```

**Step 5: Commit**

```bash
git add app/Http/Controllers/OnboardingController.php app/Http/Requests/OnboardingRequest.php
git commit -m "feat: add OnboardingController with 4-step wizard logic"
```

---

## Task 7: Update Registration Routes and CreateNewUser Action

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Actions/Fortify/CreateNewUser.php`

**Step 1: Update CreateNewUser to include phone**

```php
<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                Rule::unique(User::class),
            ],
            'password' => $this->passwordRules(),
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
        ]);

        // Generate initial phone verification code
        $code = $user->generatePhoneVerificationCode();

        // TODO: Send SMS with $code
        // SmsService::send($user->phone, "Your verification code is: {$code}");

        return $user;
    }
}
```

**Step 2: Add verification and onboarding routes to routes/web.php**

Add after existing routes:
```php
// Phone Verification Routes
Route::middleware('auth')->group(function () {
    Route::get('verify-phone', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'show'])
        ->name('verification.phone');
    Route::post('verify-phone', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'verify'])
        ->name('verification.phone.verify');
    Route::post('verify-phone/resend', [App\Http\Controllers\Auth\PhoneVerificationController::class, 'resend'])
        ->name('verification.phone.resend');
});

// Onboarding Routes
Route::middleware(['auth', 'verified', 'phone.verified'])->group(function () {
    Route::get('onboarding', [App\Http\Controllers\OnboardingController::class, 'show'])
        ->name('onboarding');
    Route::post('onboarding', [App\Http\Controllers\OnboardingController::class, 'store'])
        ->name('onboarding.store');
});
```

**Step 3: Update dashboard route to require onboarding**

Change the dashboard route middleware to include `onboarding.complete`:
```php
Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified', 'phone.verified', 'onboarding.complete'])->name('dashboard');
```

**Step 4: Commit**

```bash
git add routes/web.php app/Actions/Fortify/CreateNewUser.php
git commit -m "feat: add phone verification and onboarding routes"
```

---

## Task 8: Add i18n Translations

**Files:**
- Modify: `resources/js/i18n/en.json`
- Modify: `resources/js/i18n/ar.json`

**Step 1: Add English translations**

Add to `auth` section in en.json:
```json
"register": {
    "title": "Create an account",
    "description": "Enter your details below to create your account",
    "nameLabel": "Name",
    "namePlaceholder": "Full name",
    "emailLabel": "Email address",
    "emailPlaceholder": "email{'@'}example.com",
    "phoneLabel": "Phone number",
    "phonePlaceholder": "+20 10 1234 5678",
    "passwordLabel": "Password",
    "passwordPlaceholder": "Password",
    "confirmPasswordLabel": "Confirm password",
    "confirmPasswordPlaceholder": "Confirm password",
    "submitButton": "Create account",
    "hasAccount": "Already have an account?",
    "logIn": "Log in"
},
"verifyPhone": {
    "title": "Verify your phone",
    "description": "Enter the 6-digit code sent to {phone}",
    "codeLabel": "Verification code",
    "codePlaceholder": "000000",
    "submitButton": "Verify",
    "resendButton": "Resend code",
    "resendIn": "Resend in {seconds}s",
    "codeSent": "A new verification code has been sent."
},
"verifyEmail": {
    "title": "Verify your email",
    "description": "We've sent a verification link to {email}",
    "checkInbox": "Please check your inbox and click the verification link.",
    "resendButton": "Resend email",
    "resendIn": "Resend in {seconds}s"
}
```

Add new `onboarding` section:
```json
"onboarding": {
    "title": "Complete your profile",
    "step": "Step {current} of {total}",
    "step1": {
        "title": "Investment Profile",
        "description": "Tell us about your investment experience"
    },
    "step2": {
        "title": "Investment Goals",
        "description": "What are you looking to achieve?"
    },
    "step3": {
        "title": "Location & Markets",
        "description": "Where do you want to invest?"
    },
    "step4": {
        "title": "Sectors",
        "description": "Which sectors interest you?"
    },
    "experienceLevel": {
        "label": "Experience Level",
        "beginner": "Beginner",
        "beginnerDesc": "New to investing",
        "intermediate": "Intermediate",
        "intermediateDesc": "Some experience",
        "advanced": "Advanced",
        "advancedDesc": "Experienced investor"
    },
    "riskLevel": {
        "label": "Risk Tolerance",
        "conservative": "Conservative",
        "conservativeDesc": "Prefer stability",
        "moderate": "Moderate",
        "moderateDesc": "Balanced approach",
        "aggressive": "Aggressive",
        "aggressiveDesc": "Higher risk, higher reward"
    },
    "investmentGoal": {
        "label": "Investment Goal",
        "capital_growth": "Capital Growth",
        "fixed_income": "Fixed Income",
        "risk_reduction": "Risk Reduction",
        "short_term_speculation": "Short-term Trading",
        "retirement_planning": "Retirement",
        "wealth_preservation": "Wealth Preservation",
        "passive_income": "Passive Income",
        "education_savings": "Education Savings",
        "home_purchase": "Home Purchase",
        "emergency_fund": "Emergency Fund"
    },
    "tradingStyle": {
        "label": "Trading Style",
        "day_trading": "Day Trading",
        "swing_trading": "Swing Trading",
        "position_trading": "Position Trading",
        "scalping_trading": "Scalping"
    },
    "country": {
        "label": "Country",
        "placeholder": "Select your country"
    },
    "markets": {
        "label": "Preferred Markets",
        "description": "Select at least one market"
    },
    "sectors": {
        "label": "Preferred Sectors",
        "description": "Select at least one sector"
    },
    "buttons": {
        "back": "Back",
        "next": "Next",
        "complete": "Complete Setup"
    }
}
```

**Step 2: Add Arabic translations** (mirror structure with Arabic text)

**Step 3: Commit**

```bash
git add resources/js/i18n/en.json resources/js/i18n/ar.json
git commit -m "feat: add i18n translations for phone verification and onboarding"
```

---

## Task 9: Update Register.vue with Phone Field

**Files:**
- Modify: `resources/js/pages/auth/Register.vue`

**Step 1: Add phone input field after email field**

Add import for phone input component (if using a library) or use standard Input.

Add phone field after email field in template:
```vue
<div class="grid gap-2">
    <Label for="phone">{{ t('auth.register.phoneLabel') }}</Label>
    <Input
        id="phone"
        type="tel"
        required
        :tabindex="3"
        autocomplete="tel"
        name="phone"
        :placeholder="t('auth.register.phonePlaceholder')"
    />
    <InputError :message="errors.phone" />
</div>
```

Update tabindex for subsequent fields (password becomes 4, confirm becomes 5, etc.)

**Step 2: Commit**

```bash
git add resources/js/pages/auth/Register.vue
git commit -m "feat: add phone field to registration form"
```

---

## Task 10: Create VerifyPhone.vue Page

**Files:**
- Create: `resources/js/pages/auth/VerifyPhone.vue`

**Step 1: Create the component**

```vue
<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Form, Head, router } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const props = defineProps<{
    phone: string;
    status?: string;
}>();

const resendCooldown = ref(0);
let cooldownInterval: ReturnType<typeof setInterval> | null = null;

const startCooldown = () => {
    resendCooldown.value = 60;
    cooldownInterval = setInterval(() => {
        resendCooldown.value--;
        if (resendCooldown.value <= 0 && cooldownInterval) {
            clearInterval(cooldownInterval);
        }
    }, 1000);
};

const resendCode = () => {
    router.post('/verify-phone/resend', {}, {
        onSuccess: () => startCooldown(),
    });
};

onMounted(() => {
    startCooldown();
});

onUnmounted(() => {
    if (cooldownInterval) clearInterval(cooldownInterval);
});
</script>

<template>
    <AuthBase
        :title="t('auth.verifyPhone.title')"
        :description="t('auth.verifyPhone.description', { phone })"
    >
        <Head :title="t('auth.verifyPhone.title')" />

        <div
            v-if="status === 'verification-code-sent'"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ t('auth.verifyPhone.codeSent') }}
        </div>

        <Form
            action="/verify-phone"
            method="post"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="code">{{ t('auth.verifyPhone.codeLabel') }}</Label>
                    <Input
                        id="code"
                        type="text"
                        name="code"
                        required
                        autofocus
                        maxlength="6"
                        pattern="[0-9]{6}"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        :placeholder="t('auth.verifyPhone.codePlaceholder')"
                        class="text-center text-2xl tracking-widest"
                    />
                    <InputError :message="errors.code" />
                </div>

                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                >
                    <Spinner v-if="processing" />
                    {{ t('auth.verifyPhone.submitButton') }}
                </Button>

                <Button
                    type="button"
                    variant="ghost"
                    class="w-full"
                    :disabled="resendCooldown > 0"
                    @click="resendCode"
                >
                    {{ resendCooldown > 0
                        ? t('auth.verifyPhone.resendIn', { seconds: resendCooldown })
                        : t('auth.verifyPhone.resendButton')
                    }}
                </Button>
            </div>
        </Form>
    </AuthBase>
</template>
```

**Step 2: Commit**

```bash
git add resources/js/pages/auth/VerifyPhone.vue
git commit -m "feat: add VerifyPhone page for OTP verification"
```

---

## Task 11: Create Onboarding.vue Page

**Files:**
- Create: `resources/js/pages/Onboarding.vue`
- Create: `resources/js/components/onboarding/OnboardingStep1.vue`
- Create: `resources/js/components/onboarding/OnboardingStep2.vue`
- Create: `resources/js/components/onboarding/OnboardingStep3.vue`
- Create: `resources/js/components/onboarding/OnboardingStep4.vue`
- Create: `resources/js/components/SelectableCard.vue`

**Step 1: Create SelectableCard component**

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

const props = defineProps<{
    selected?: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{
    select: [];
}>();
</script>

<template>
    <button
        type="button"
        :disabled="disabled"
        :class="cn(
            'flex flex-col items-center gap-2 rounded-lg border-2 p-4 text-center transition-all',
            'hover:border-primary/50 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2',
            selected ? 'border-primary bg-primary/5' : 'border-border',
            disabled && 'opacity-50 cursor-not-allowed'
        )"
        @click="emit('select')"
    >
        <slot />
    </button>
</template>
```

**Step 2: Create main Onboarding.vue page**

```vue
<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { Button } from '@/components/ui/button';
import OnboardingStep1 from '@/components/onboarding/OnboardingStep1.vue';
import OnboardingStep2 from '@/components/onboarding/OnboardingStep2.vue';
import OnboardingStep3 from '@/components/onboarding/OnboardingStep3.vue';
import OnboardingStep4 from '@/components/onboarding/OnboardingStep4.vue';

const { t, locale } = useI18n();

const props = defineProps<{
    step: number;
    totalSteps: number;
    countries: Array<{ id: string; name: string; code: string }>;
    markets: Array<{ id: string; name: string; code: string }>;
    sectors: Array<{ id: string; name: string }>;
    user: {
        experience_level: string | null;
        risk_level: string | null;
        investment_goal: string | null;
        trading_style: string | null;
        country_id: string | null;
        markets: string[];
        sectors: string[];
    };
}>();

const formData = ref({
    experience_level: props.user.experience_level || '',
    risk_level: props.user.risk_level || '',
    investment_goal: props.user.investment_goal || '',
    trading_style: props.user.trading_style || '',
    country_id: props.user.country_id || '',
    markets: props.user.markets || [],
    sectors: props.user.sectors || [],
});

const processing = ref(false);
const errors = ref<Record<string, string>>({});

const currentDir = computed(() => locale.value === 'ar' ? 'rtl' : 'ltr');

const canProceed = computed(() => {
    switch (props.step) {
        case 1:
            return formData.value.experience_level && formData.value.risk_level;
        case 2:
            return formData.value.investment_goal && formData.value.trading_style;
        case 3:
            return formData.value.country_id && formData.value.markets.length > 0;
        case 4:
            return formData.value.sectors.length > 0;
        default:
            return false;
    }
});

const goBack = () => {
    if (props.step > 1) {
        router.get('/onboarding', { step: props.step - 1 });
    }
};

const submitStep = () => {
    processing.value = true;
    errors.value = {};

    const data: Record<string, unknown> = { step: props.step };

    switch (props.step) {
        case 1:
            data.experience_level = formData.value.experience_level;
            data.risk_level = formData.value.risk_level;
            break;
        case 2:
            data.investment_goal = formData.value.investment_goal;
            data.trading_style = formData.value.trading_style;
            break;
        case 3:
            data.country_id = formData.value.country_id;
            data.markets = formData.value.markets;
            break;
        case 4:
            data.sectors = formData.value.sectors;
            break;
    }

    router.post('/onboarding', data, {
        onFinish: () => {
            processing.value = false;
        },
        onError: (errs) => {
            errors.value = errs;
        },
    });
};
</script>

<template>
    <div
        class="min-h-svh bg-background p-6 md:p-10"
        :dir="currentDir"
        :lang="locale"
    >
        <Head :title="t('onboarding.title')" />

        <div class="mx-auto max-w-2xl">
            <!-- Progress -->
            <div class="mb-8">
                <div class="mb-2 text-sm text-muted-foreground">
                    {{ t('onboarding.step', { current: step, total: totalSteps }) }}
                </div>
                <div class="h-2 rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full bg-primary transition-all"
                        :style="{ width: `${(step / totalSteps) * 100}%` }"
                    />
                </div>
            </div>

            <!-- Step Content -->
            <div class="mb-8">
                <OnboardingStep1
                    v-if="step === 1"
                    v-model:experienceLevel="formData.experience_level"
                    v-model:riskLevel="formData.risk_level"
                    :errors="errors"
                />
                <OnboardingStep2
                    v-else-if="step === 2"
                    v-model:investmentGoal="formData.investment_goal"
                    v-model:tradingStyle="formData.trading_style"
                    :errors="errors"
                />
                <OnboardingStep3
                    v-else-if="step === 3"
                    v-model:countryId="formData.country_id"
                    v-model:markets="formData.markets"
                    :countries="countries"
                    :availableMarkets="markets"
                    :errors="errors"
                />
                <OnboardingStep4
                    v-else-if="step === 4"
                    v-model:sectors="formData.sectors"
                    :availableSectors="sectors"
                    :errors="errors"
                />
            </div>

            <!-- Navigation -->
            <div class="flex justify-between gap-4">
                <Button
                    v-if="step > 1"
                    type="button"
                    variant="outline"
                    @click="goBack"
                >
                    {{ t('onboarding.buttons.back') }}
                </Button>
                <div v-else />

                <Button
                    type="button"
                    :disabled="!canProceed || processing"
                    @click="submitStep"
                >
                    {{ step === totalSteps
                        ? t('onboarding.buttons.complete')
                        : t('onboarding.buttons.next')
                    }}
                </Button>
            </div>
        </div>
    </div>
</template>
```

**Step 3: Create step components (OnboardingStep1-4)**

Each step component will use SelectableCard for options. Implementation follows same pattern - using v-model for data binding and displaying appropriate options.

**Step 4: Commit**

```bash
git add resources/js/pages/Onboarding.vue resources/js/components/onboarding/ resources/js/components/SelectableCard.vue
git commit -m "feat: add Onboarding wizard with 4 steps"
```

---

## Task 12: Build and Test

**Step 1: Build frontend**

```bash
npm run build
```

**Step 2: Run migrations** (when database available)

```bash
php artisan migrate
```

**Step 3: Test the flow manually**

1. Go to `/register`
2. Fill form with name, email, phone, password
3. Should redirect to `/verify-phone`
4. Enter OTP code
5. Should redirect to `/verify-email` (or email verification page)
6. Click email verification link
7. Should redirect to `/onboarding`
8. Complete all 4 steps
9. Should redirect to `/dashboard`

**Step 4: Final commit**

```bash
git add .
git commit -m "feat: complete registration and onboarding implementation"
```

---

## Summary

**Files Created:**
- `database/migrations/2025_12_16_000001_add_phone_verification_columns.php`
- `app/Http/Middleware/EnsurePhoneIsVerified.php`
- `app/Http/Middleware/EnsureOnboardingComplete.php`
- `app/Http/Controllers/Auth/PhoneVerificationController.php`
- `app/Http/Controllers/OnboardingController.php`
- `app/Http/Requests/VerifyPhoneRequest.php`
- `app/Http/Requests/OnboardingRequest.php`
- `resources/js/pages/auth/VerifyPhone.vue`
- `resources/js/pages/Onboarding.vue`
- `resources/js/components/onboarding/OnboardingStep1-4.vue`
- `resources/js/components/SelectableCard.vue`

**Files Modified:**
- `app/Models/User.php`
- `app/Actions/Fortify/CreateNewUser.php`
- `routes/web.php`
- `bootstrap/app.php`
- `resources/js/pages/auth/Register.vue`
- `resources/js/i18n/en.json`
- `resources/js/i18n/ar.json`
