<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers(), 'confirmed'],
        ]);

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

        return response()->json([
            'data' => [
                'user' => $user->load('profile'),
                'message' => 'Account created.',
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        if (! auth()->guard('web')->attempt(
            ['email' => $validated['email'], 'password' => $validated['password']],
            $validated['remember'] ?? false
        )) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'data' => [
                'user' => $request->user()->load('profile'),
                'message' => 'Login successful.',
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        auth()->guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(null, 204);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load('profile');

        return response()->json([
            'data' => array_merge(
                $user->toArray(),
                ['gravatar_url' => $this->gravatarUrl($user)]
            ),
        ]);
    }

    private function gravatarUrl(User $user): string
    {
        return 'https://www.gravatar.com/avatar/'
            .md5(strtolower(trim((string) $user->email)))
            .'?d=404&s=96';
    }
}
