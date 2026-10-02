<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PREM-D03 — Cloud backup upload contract.
 *
 * `payload` is the exact serialized local-backup snapshot string; the server
 * stores those exact bytes. Size and checksum are re-verified server-side, so
 * the declared `size_bytes`/`checksum_sha256` are never trusted on their own.
 */
class StoreCloudBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'business_id' => ['required', 'integer'],
            'device_identifier' => ['required', 'string', 'max:100'],
            'schema_version' => ['required', 'integer', 'min:1'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'checksum_sha256' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/i'],
            'size_bytes' => ['required', 'integer', 'min:1'],
            'payload' => ['required', 'string'],
            'idempotency_key' => ['nullable', 'string', 'max:191'],
        ];
    }
}
