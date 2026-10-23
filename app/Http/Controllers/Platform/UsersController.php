<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platform\PlatformAuditLogger;
use App\Support\PlatformAuditAction;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UsersController extends Controller
{
    /**
     * Display a listing of platform users with search, filtering, and pagination.
     */
    public function index(Request $request): View
    {
        $query = User::query()
            ->withCount('businesses')
            ->with(['businesses' => function ($bQuery) {
                $bQuery->select('businesses.id', 'businesses.name', 'businesses.slug', 'businesses.status')
                    ->withPivot('role');
            }]);

        // Search by user name, email, or associated business name
        $search = trim((string) $request->input('q', ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhereHas('businesses', function ($bQuery) use ($search) {
                        $bQuery->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Filter by user account type
        $type = $request->input('type');
        if ($type === 'platform_admin') {
            $query->where('is_platform_admin', true);
        } elseif ($type === 'business_user') {
            $query->where('is_platform_admin', false)->whereHas('businesses');
        } elseif ($type === 'unconnected') {
            $query->where('is_platform_admin', false)->whereDoesntHave('businesses');
        }

        // Filter by email verification status
        $verification = $request->input('verification');
        if ($verification === 'verified') {
            $query->whereNotNull('email_verified_at');
        } elseif ($verification === 'unverified') {
            $query->whereNull('email_verified_at');
        }

        $users = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('platform.users.index', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'type' => $type,
                'verification' => $verification,
            ],
        ]);
    }

    /**
     * Display the specified user detail with business memberships.
     */
    public function show(User $user): View
    {
        $user->load([
            'businesses' => function ($query) {
                $query->select('businesses.id', 'businesses.name', 'businesses.slug', 'businesses.status', 'businesses.business_type')
                    ->withPivot('role', 'created_at')
                    ->orderBy('name');
            },
        ]);

        $user->loadCount('businesses');

        return view('platform.users.show', [
            'user' => $user,
        ]);
    }

    /**
     * Update platform-managed account fields.
     */
    public function update(
        Request $request,
        User $user,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'is_platform_admin' => ['required', 'boolean'],
        ]);

        if ($user->is($request->user()) && ! (bool) $validated['is_platform_admin']) {
            return back()->with('error', 'Akun Platform Admin yang sedang digunakan tidak boleh menurunkan hak akses dirinya sendiri.');
        }

        if ($user->is_platform_admin && ! (bool) $validated['is_platform_admin']
            && User::query()->where('is_platform_admin', true)->count() <= 1) {
            return back()->with('error', 'Platform harus memiliki minimal satu Platform Admin.');
        }

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'is_platform_admin' => $user->is_platform_admin,
        ];

        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'is_platform_admin' => (bool) $validated['is_platform_admin'],
            'email_verified_at' => $user->email === $validated['email'] ? $user->email_verified_at : null,
        ])->save();

        /** @var User $actor */
        $actor = $request->user();
        $auditLogger->record(
            actor: $actor,
            action: PlatformAuditAction::USER_UPDATED,
            targetType: PlatformAuditAction::TARGET_USER,
            targetId: $user->id,
            targetLabel: $user->name,
            businessId: null,
            before: $before,
            after: [
                'name' => $user->name,
                'email' => $user->email,
                'is_platform_admin' => $user->is_platform_admin,
            ],
        );

        return redirect()->route('platform.users.show', $user)
            ->with('status', 'Data pengguna berhasil diperbarui.');
    }

    /**
     * Delete an unassigned user. Business memberships are blocked to prevent
     * accidentally orphaning a business owner or staff history.
     */
    public function destroy(
        Request $request,
        User $user,
        PlatformAuditLogger $auditLogger,
    ): RedirectResponse {
        if ($user->is($request->user())) {
            return back()->with('error', 'Akun Platform Admin yang sedang digunakan tidak boleh dihapus.');
        }

        if ($user->businesses()->exists()) {
            return back()->with('error', 'Pengguna masih terhubung ke bisnis. Lepaskan semua keanggotaan terlebih dahulu.');
        }

        if ($user->is_platform_admin && User::query()->where('is_platform_admin', true)->count() <= 1) {
            return back()->with('error', 'Platform harus memiliki minimal satu Platform Admin.');
        }

        $before = [
            'name' => $user->name,
            'email' => $user->email,
            'is_platform_admin' => $user->is_platform_admin,
        ];
        $userId = $user->id;
        $userName = $user->name;

        /** @var User $actor */
        $actor = $request->user();
        DB::transaction(function () use ($user, $auditLogger, $actor, $userId, $userName, $before): void {
            $auditLogger->record(
                actor: $actor,
                action: PlatformAuditAction::USER_DELETED,
                targetType: PlatformAuditAction::TARGET_USER,
                targetId: $userId,
                targetLabel: $userName,
                businessId: null,
                before: $before,
                after: null,
                metadata: ['destructive_action' => true],
            );
            $user->delete();
        });

        return redirect()->route('platform.users.index')
            ->with('status', "Pengguna '{$userName}' berhasil dihapus.");
    }
}
