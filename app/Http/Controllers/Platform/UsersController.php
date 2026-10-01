<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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
}
