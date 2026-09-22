<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceContextService;
use App\Support\GoogleRedirectUri;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin(): string
    {
        return $this->render('login');
    }

    public function showRegister(): string
    {
        return $this->render('register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = strtolower(trim($credentials['email']));

        if (! auth()->attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

        $validated['email'] = strtolower(trim($validated['email']));

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            Profile::create([
                'user_id' => $user->id,
                'display_name' => $validated['name'],
                'currency' => config('okyema.currency.code'),
            ]);

            app(WorkspaceContextService::class)->seedDefaultsFor($user);

            return $user;
        });

        auth()->login($user);
        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    public function logout(Request $request): RedirectResponse
    {
        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect('/login')->withErrors([
                'email' => 'Google sign-in is not configured. Please sign in with your email and password.',
            ]);
        }

        return $this->googleDriver()->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        if (! $this->googleConfigured()) {
            return redirect('/login')->withErrors([
                'email' => 'Google sign-in is not configured. Please sign in with your email and password.',
            ]);
        }

        try {
            $googleUser = $this->fetchGoogleUser();
        } catch (\Throwable $e) {
            report($e);

            return redirect('/login')->withErrors([
                'email' => 'Google sign-in failed. Please try again, or sign in with your email and password.',
            ]);
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));

        if ($email === '') {
            return redirect('/login')->withErrors([
                'email' => 'Google did not share an email address, so we cannot sign you in. Use your email and password instead.',
            ]);
        }

        // Google sign-in links to an existing account by email; it must never
        // change that account's password.
        $user = User::firstOrNew(['email' => $email]);
        $user->google_id = $googleUser->getId();
        $user->name = $user->name ?: ($googleUser->getName() ?? $email);

        if (! $user->exists) {
            $user->password = Hash::make(bin2hex(random_bytes(16)));
        }

        $user->save();

        $this->ensureProfile($user);
        $this->ensureWorkspaces($user);

        auth()->login($user);

        return redirect('/');
    }

    private function fetchGoogleUser(): \Laravel\Socialite\Contracts\User
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return $this->googleDriver()->user();
            } catch (\Throwable $e) {
                if ($attempts >= 2 || ! $this->transientOAuthFailure($e)) {
                    throw $e;
                }

                usleep(300000);
            }
        }
    }

    private function transientOAuthFailure(\Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        foreach (['curl error', 'timed out', 'timeout', 'connection reset', 'temporarily unavailable', '502', '503', '504', '429'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function googleDriver(): Provider
    {
        return Socialite::driver('google')->with([
            'redirect_uri' => GoogleRedirectUri::resolve(),
        ]);
    }

    private function googleConfigured(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }

    private function ensureProfile(User $user): void
    {
        if ($user->profile()->exists()) {
            return;
        }

        $user->profile()->create([
            'display_name' => $user->name,
            'currency' => config('okyema.currency.code'),
        ]);
    }

    private function ensureWorkspaces(User $user): void
    {
        if ($user->memberships()->exists()) {
            return;
        }

        app(WorkspaceContextService::class)->seedDefaultsFor($user);
    }

    private function render(string $view): string
    {
        $base = rtrim(request()->getBasePath(), '/');
        $csrf = csrf_token();
        $errors = session('errors', new ViewErrorBag);
        $old = old();

        $__path = resource_path("views/{$view}.php");
        extract(['base' => $base, 'csrf' => $csrf, 'errors' => $errors, 'old' => $old]);
        ob_start();
        include $__path;

        return ob_get_clean();
    }
}
