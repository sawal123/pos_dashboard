<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\DevicePairingException;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardUsersData;
use App\Services\Devices\DevicePairingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(
        protected DashboardUsersData $usersData,
        protected DevicePairingService $pairing,
    ) {}

    public function index(Request $request): View
    {
        $currentBusiness = $this->authorizeOwnerAccess($request);

        $filters = $request->only([
            'q',
            'role',
            'verification',
            'page',
        ]);

        $validator = Validator::make($filters, [
            'q' => ['nullable', 'string', 'max:100'],
            'role' => ['nullable', 'string', 'max:50'],
            'verification' => ['nullable', 'string', 'max:20'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        if ($validator->fails()) {
            foreach (array_keys($validator->failed()) as $invalidField) {
                unset($filters[$invalidField]);
            }
        }

        $data = $this->usersData->get($currentBusiness, $filters);
        $data['invitations'] = $this->usersData->invitations($currentBusiness);
        $data['invitationSummary'] = $this->usersData->invitationSummary($currentBusiness);
        $data['outlets'] = $currentBusiness->outlets()->where('status', 'active')->orderByDesc('is_primary')->orderBy('id')->get();

        return view('users.index', $data);
    }

    public function detail(Request $request, int $userId): JsonResponse
    {
        $currentBusiness = $this->authorizeOwnerAccess($request);

        $data = $this->usersData->detail($currentBusiness, $userId);

        return response()->json($data);
    }

    /**
     * Generate a 4-digit device pairing / verification code for a specific cashier member.
     */
    public function generateDeviceCode(Request $request, int $userId): JsonResponse|RedirectResponse
    {
        $currentBusiness = $this->authorizeOwnerAccess($request);

        $member = $currentBusiness->users()->where('users.id', $userId)->first();
        if (! $member) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Anggota tidak ditemukan pada bisnis ini.'], 404);
            }
            return back()->withErrors(['device_code' => 'Anggota tidak ditemukan pada bisnis ini.']);
        }

        $pivot = $member->pivot;
        $outletId = $request->input('outlet_id') ?: ($pivot->outlet_id ?? null);

        if (! $outletId) {
            $outletId = $currentBusiness->outlets()->where('status', 'active')->orderByDesc('is_primary')->value('id');
        }

        if (! $outletId) {
            if ($request->wantsJson()) {
                return response()->json(['message' => 'Outlet aktif belum tersedia pada bisnis ini.'], 422);
            }
            return back()->withErrors(['device_code' => 'Outlet aktif belum tersedia pada bisnis ini.']);
        }

        try {
            $result = $this->pairing->issue($currentBusiness, (int) $outletId, $request->user());
            $outletName = $result['pairing']->outlet?->name ?? 'Outlet';

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Kode verifikasi perangkat kasir berhasil dibuat.',
                    'code' => $result['code'],
                    'user_id' => $member->id,
                    'user_name' => $member->name,
                    'outlet_id' => (int) $outletId,
                    'outlet_name' => $outletName,
                    'expires_at' => $result['pairing']->expires_at->toIso8601String(),
                    'ttl_minutes' => \App\Models\DevicePairingCode::TTL_MINUTES,
                ]);
            }

            return back()->with('status', 'Kode verifikasi perangkat kasir dibuat.')
                ->with('pairing_code', [
                    'id' => $result['pairing']->id,
                    'code' => $result['code'],
                    'user_name' => $member->name,
                    'outlet' => $outletName,
                    'expires_at' => $result['pairing']->expires_at->toIso8601String(),
                ]);
        } catch (DevicePairingException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], $e->status);
            }
            return back()->withErrors(['device_code' => $e->getMessage()]);
        }
    }

    /**
     * Resolve the current business from the shared dashboard context and require
     * the authenticated user to own it. The business id is never read from the
     * request, so it cannot be forged through a query parameter.
     */
    private function authorizeOwnerAccess(Request $request): Business
    {
        /** @var Business|null $currentBusiness */
        $currentBusiness = $request->attributes->get('dashboard_business');

        if ($currentBusiness === null) {
            abort(403);
        }

        Gate::forUser($request->user())->authorize('viewMembers', $currentBusiness);

        return $currentBusiness;
    }
}
