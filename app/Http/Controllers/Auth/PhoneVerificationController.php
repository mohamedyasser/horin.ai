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
    /**
     * Show the phone verification page.
     */
    public function show(Request $request): Response|RedirectResponse
    {
        if ($request->user()->hasVerifiedPhone()) {
            return redirect()->intended(route('verification.notice'));
        }

        return Inertia::render('auth/VerifyPhone', [
            'phone' => $this->maskPhone($request->user()->phone),
        ]);
    }

    /**
     * Verify the phone number with the provided OTP code.
     */
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

    /**
     * Resend the phone verification code.
     */
    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedPhone()) {
            return redirect()->route('verification.notice');
        }

        $user->generatePhoneVerificationCode();

        // TODO: Send SMS with the code
        // SmsService::send($user->phone, "Your verification code is: {$code}");

        return back()->with('status', 'verification-code-sent');
    }

    /**
     * Mask the phone number for display.
     */
    private function maskPhone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        $length = strlen($phone);
        if ($length <= 6) {
            return $phone;
        }

        return substr($phone, 0, 4).str_repeat('*', $length - 6).substr($phone, -2);
    }
}
