<?php

declare(strict_types=1);

namespace Nuewire\Logs\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Throwable;

trait InteractsWithLogsPage
{
    private function ensureAuthorized(string $permission): void
    {
        $authorization = (array) config('nuewire.logs.authorization', []);
        $guard = trim((string) ($authorization['guard'] ?? ''));
        $auth = $guard === '' ? Auth::guard() : Auth::guard($guard);

        if ((bool) ($authorization['require_authenticated_user'] ?? true) && ! $auth->check()) {
            abort(403);
        }

        $user = $auth->user();

        if (app()->bound('nuewire.acl.enabled')) {
            if ($user === null || ! method_exists($user, 'can')) {
                abort(403);
            }

            try {
                abort_unless($user->can($permission), 403);
            } catch (Throwable) {
                abort(403);
            }
        }

        $gate = trim((string) ($authorization['gate'] ?? ''));

        if ($gate !== '') {
            if ($user === null || Gate::forUser($user)->denies($gate)) {
                abort(403);
            }
        }
    }

    private function resolveLocale(?string $locale): string
    {
        $supported = array_values(array_filter((array) config('nuewire.logs.supported_locales', ['id', 'en']), 'is_string'));
        $candidate = $locale;

        if ($candidate === null && (bool) config('nuewire.logs.remember_locale', true) && app()->bound('session')) {
            try {
                $candidate = session()->get((string) config('nuewire.logs.locale_session_key', 'nuewire.logs.locale'));
            } catch (Throwable) {
                $candidate = null;
            }
        }

        $candidate = strtolower(trim((string) $candidate));

        if (in_array($candidate, $supported, true)) {
            return $candidate;
        }

        $fallback = strtolower(trim((string) config('nuewire.logs.locale', 'id')));

        return in_array($fallback, $supported, true) ? $fallback : ($supported[0] ?? 'id');
    }

    private function rememberLocale(string $locale): void
    {
        if (! (bool) config('nuewire.logs.remember_locale', true)) {
            return;
        }

        try {
            session()->put((string) config('nuewire.logs.locale_session_key', 'nuewire.logs.locale'), $locale);
        } catch (Throwable) {
            // Session persistence is optional.
        }
    }

    /** @return array<string, string> */
    private function localeOptions(): array
    {
        $labels = ['id' => 'Indonesia', 'en' => 'English'];
        $options = [];

        foreach ((array) config('nuewire.logs.supported_locales', ['id', 'en']) as $locale) {
            if (is_string($locale) && $locale !== '') {
                $options[$locale] = $labels[$locale] ?? strtoupper($locale);
            }
        }

        return $options;
    }
}
