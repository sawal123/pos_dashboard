<?php

namespace App\Services\Membership;

use App\Models\Business;
use App\Models\MembershipAuditLog;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Writes append-only membership audit entries.
 *
 * Only the whitelisted metadata keys are persisted, so an invitation token, a
 * password or any other secret can never reach the audit trail.
 */
class MembershipAuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Business $business,
        ?User $actor,
        string $action,
        ?User $targetUser = null,
        ?string $targetEmail = null,
        array $metadata = [],
    ): MembershipAuditLog {
        $safeMetadata = array_intersect_key(
            $metadata,
            array_flip(MembershipAuditLog::SAFE_METADATA_KEYS),
        );

        return MembershipAuditLog::create([
            'business_id' => $business->id,
            'actor_id' => $actor?->id,
            'target_user_id' => $targetUser?->id,
            'target_email' => $targetEmail !== null ? Str::lower(trim($targetEmail)) : null,
            'action' => $action,
            'metadata' => $safeMetadata === [] ? null : $safeMetadata,
            'created_at' => now(),
        ]);
    }
}
