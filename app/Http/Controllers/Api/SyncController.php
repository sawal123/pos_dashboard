<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncPullRequest;
use App\Http\Requests\Api\SyncPushRequest;
use App\Http\Requests\Api\SyncRequestStatusRequest;
use App\Models\SyncRequest;
use App\Services\Authorization\BusinessPermission;
use App\Services\Sync\SyncContextResolver;
use App\Services\Sync\SyncPullService;
use App\Services\Sync\SyncPushService;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    /**
     * Handle incoming sync push request.
     */
    public function push(
        SyncPushRequest $request,
        SyncContextResolver $resolver,
        SyncPushService $service
    ): JsonResponse {
        $context = $resolver->resolve(
            $request,
            (int) $request->input('business_id'),
            (string) $request->input('device_identifier'),
            BusinessPermission::SYNC_PUSH,
        );

        return $service->process($context, $request->validated());
    }

    /**
     * Handle incoming sync pull request.
     */
    public function pull(
        SyncPullRequest $request,
        SyncContextResolver $resolver,
        SyncPullService $service
    ): JsonResponse {
        $context = $resolver->resolve(
            $request,
            (int) $request->input('business_id'),
            (string) $request->input('device_identifier'),
            BusinessPermission::SYNC_PULL,
        );

        return $service->pull(
            $context,
            (int) $request->input('after', 0),
            (int) $request->input('limit', 100)
        );
    }

    /**
     * INT-03 — read-only status of a previously pushed sync request.
     *
     * Answers whether the server has already committed the request, scoped to
     * the authenticated device. It never returns the request payload, and a
     * `not_found` result is not proof that an in-flight request will not commit.
     *
     * Authorization intentionally uses {@see BusinessPermission::SYNC_PULL} (held
     * by owner, member and cashier) rather than the push permission, so a role
     * change after a lost response cannot hide a committed request behind a 403.
     * Read-only: it never mutates sync state.
     */
    public function requestStatus(
        SyncRequestStatusRequest $request,
        SyncContextResolver $resolver,
    ): JsonResponse {
        $context = $resolver->resolve(
            $request,
            (int) $request->input('business_id'),
            (string) $request->input('device_identifier'),
            BusinessPermission::SYNC_PULL,
        );

        $requestId = (string) $request->input('request_id');

        // Scoped to the resolved device, so a request id belonging to another
        // device or business can never be distinguished from a missing one.
        $committed = SyncRequest::query()
            ->where('business_id', $context['business']->id)
            ->where('device_id', $context['device']->id)
            ->where('request_id', $requestId)
            ->first();

        return response()->json([
            'data' => [
                'request_id' => $requestId,
                'status' => $committed instanceof SyncRequest ? 'committed' : 'not_found',
                'processed_at' => $committed?->processed_at?->toIso8601String(),
            ],
        ]);
    }
}
