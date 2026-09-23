<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'display_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'organisation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'onboarding_dismissed' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        $profile = $user->profile()->firstOrCreate(
            ['user_id' => $user->id],
            ['display_name' => $user->name, 'currency' => config('okyema.currency.code')],
        );

        $dismissed = $validated['onboarding_dismissed'] ?? null;
        unset($validated['onboarding_dismissed']);

        $profile->fill($validated);

        // The first-use tour remembers being finished here, so "never show me
        // this again" follows the user to every device.
        if ($dismissed !== null) {
            $profile->onboarding_dismissed_at = $dismissed ? now() : null;
        }

        $profile->save();

        return response()->json(['data' => $profile->fresh()]);
    }
}
