<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncPullRequest;
use App\Http\Requests\Api\SyncPushRequest;
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
            (string) $request->input('device_identifier')
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
            (string) $request->input('device_identifier')
        );

        return $service->pull(
            $context,
            (int) $request->input('after', 0),
            (int) $request->input('limit', 100)
        );
    }
}
