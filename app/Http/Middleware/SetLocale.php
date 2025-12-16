<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Supported locales.
     */
    protected array $supportedLocales = ['ar', 'en'];

    /**
     * Default locale.
     */
    protected string $defaultLocale = 'ar';

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');

        // If no locale in route, check user's language preference
        if (! $locale) {
            $userLocale = $request->user()?->language;
            $locale = $userLocale && in_array($userLocale, $this->supportedLocales)
                ? $userLocale
                : $this->defaultLocale;
            App::setLocale($locale);

            return $next($request);
        }

        // Validate locale - only ar or en allowed
        if (! in_array($locale, $this->supportedLocales)) {
            abort(404);
        }

        // Set the application locale
        App::setLocale($locale);

        return $next($request);
    }
}
