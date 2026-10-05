<?php

declare(strict_types=1);

namespace App\Http\Requests\Platform;

use App\Support\PlatformSettingDefinition;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingRequest extends FormRequest
{
    protected ?PlatformSettingDefinition $resolvedDefinition = null;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        if ($this->user()?->isPlatformAdmin() !== true) {
            return false;
        }

        if ($this->definition() === null) {
            abort(404, 'Pengaturan platform tidak ditemukan atau tidak diperbolehkan.');
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $definition = $this->definition();

        if ($definition === null) {
            return [];
        }

        if ($definition->type === PlatformSettingDefinition::TYPE_INTEGER) {
            $rules = ['required', 'integer'];

            if ($definition->min !== null) {
                $rules[] = 'min:'.$definition->min;
            }

            if ($definition->max !== null) {
                $rules[] = 'max:'.$definition->max;
            }

            return [
                'value' => $rules,
            ];
        }

        if ($definition->type === PlatformSettingDefinition::TYPE_BOOLEAN) {
            return [
                'value' => ['required', 'boolean'],
            ];
        }

        return [
            'value' => ['required'],
        ];
    }

    /**
     * Custom validation messages in Indonesian.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $def = $this->definition();
        $label = $def ? $def->label : 'Pengaturan';

        return [
            'value.required' => "Nilai untuk {$label} wajib diisi.",
            'value.integer' => "Nilai untuk {$label} harus berupa angka bulat.",
            'value.min' => "Nilai untuk {$label} minimal :min.",
            'value.max' => "Nilai untuk {$label} maksimal :max.",
            'value.boolean' => "Nilai untuk {$label} harus berupa status aktif atau nonaktif.",
        ];
    }

    /**
     * Retrieve the resolved definition matching the route slug parameter.
     */
    public function definition(): ?PlatformSettingDefinition
    {
        if ($this->resolvedDefinition !== null) {
            return $this->resolvedDefinition;
        }

        $param = (string) $this->route('setting');

        // Look up by slug first, then fallback to key
        $this->resolvedDefinition = PlatformSettingDefinition::findBySlug($param)
            ?? PlatformSettingDefinition::findByKey($param);

        return $this->resolvedDefinition;
    }

    /**
     * Get typed and validated setting value.
     */
    public function settingValue(): mixed
    {
        $def = $this->definition();
        $raw = $this->input('value');

        if ($def?->type === PlatformSettingDefinition::TYPE_INTEGER) {
            return (int) $raw;
        }

        if ($def?->type === PlatformSettingDefinition::TYPE_BOOLEAN) {
            return filter_var($raw, FILTER_VALIDATE_BOOLEAN);
        }

        return $raw;
    }
}
