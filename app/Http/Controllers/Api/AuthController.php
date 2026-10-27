<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Outlet;
use App\Models\PendingRegistration;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Register a new user, default business, outlet, and issue personal access token.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'business_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = DB::transaction(function () use ($validated) {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_login_enabled' => true,
            ]);

            // Mark email as verified for mobile registration
            $user->email_verified_at = Carbon::now();
            $user->save();

            $rawBusinessName = ! empty($validated['business_name'])
                ? trim($validated['business_name'])
                : ($validated['name'].' Store');

            $slugBase = Str::slug($rawBusinessName);
            if (empty($slugBase)) {
                $slugBase = 'toko';
            }
            $slug = $slugBase.'-'.Str::lower(Str::random(6));

            $business = Business::create([
                'name' => $rawBusinessName,
                'slug' => $slug,
                'status' => 'active',
                'business_type' => 'retail',
            ]);

            $user->businesses()->attach($business->id, [
                'role' => Business::ROLE_OWNER,
            ]);

            Outlet::create([
                'business_id' => $business->id,
                'name' => 'Outlet Utama',
                'code' => 'OUT-1',
                'status' => 'active',
            ]);

            Subscription::create([
                'business_id' => $business->id,
                'plan' => Subscription::PLAN_FREE,
                'status' => Subscription::STATUS_ACTIVE,
            ]);

            return $user;
        });

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return response()->json([
            'message' => 'Registrasi berhasil.',
            'data' => [
                'token_type' => 'Bearer',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ], 201);
    }

    /**
     * Authenticate user credentials and issue personal access token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email address is not verified.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        $pendingRegistration = PendingRegistration::query()
            ->where('user_id', $user->id)
            ->first();

        if ($pendingRegistration !== null && ! $pendingRegistration->isProvisioned()) {
            return response()->json([
                'message' => 'Email address is not verified.',
                'code' => 'EMAIL_NOT_VERIFIED',
            ], 403);
        }

        if ($user->two_factor_confirmed_at !== null) {
            return response()->json([
                'message' => 'Two-factor authentication is required.',
                'code' => 'TWO_FACTOR_REQUIRED',
            ], 403);
        }

        $token = $user->createToken('mobile-api', ['mobile'])->plainTextToken;

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    /**
     * Get the authenticated user's profile.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    /**
     * Revoke the user's current access token.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }
}
