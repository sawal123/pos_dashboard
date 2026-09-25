<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Services\Dashboard\DashboardUsersData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function __construct(
        protected DashboardUsersData $usersData
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

        return view('users.index', $data);
    }

    public function detail(Request $request, int $userId): JsonResponse
    {
        $currentBusiness = $this->authorizeOwnerAccess($request);

        $data = $this->usersData->detail($currentBusiness, $userId);

        return response()->json($data);
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
