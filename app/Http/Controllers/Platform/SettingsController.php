<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\UpdatePlatformSettingRequest;
use App\Models\User;
use App\Services\Platform\PlatformSettings;
use App\Services\Subscription\Midtrans\MidtransGateway;
use App\Support\PlatformSettingDefinition;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        protected PlatformSettings $settings,
        protected MidtransGateway $midtransGateway
    ) {}

    /**
     * Display Platform Settings management interface.
     */
    public function index(): View
    {
        $allSettings = $this->settings->allManageable();

        $groupedSettings = [
            PlatformSettingDefinition::GROUP_CLOUD_DEVICES => [
                'label' => PlatformSettingDefinition::groupLabel(PlatformSettingDefinition::GROUP_CLOUD_DEVICES),
                'items' => array_filter($allSettings, fn ($s) => $s['group'] === PlatformSettingDefinition::GROUP_CLOUD_DEVICES),
            ],
            PlatformSettingDefinition::GROUP_ALERTS => [
                'label' => PlatformSettingDefinition::groupLabel(PlatformSettingDefinition::GROUP_ALERTS),
                'items' => array_filter($allSettings, fn ($s) => $s['group'] === PlatformSettingDefinition::GROUP_ALERTS),
            ],
            PlatformSettingDefinition::GROUP_FEATURES => [
                'label' => PlatformSettingDefinition::groupLabel(PlatformSettingDefinition::GROUP_FEATURES),
                'items' => array_filter($allSettings, fn ($s) => $s['group'] === PlatformSettingDefinition::GROUP_FEATURES),
            ],
        ];

        $isMidtransConfigured = false;
        try {
            $isMidtransConfigured = $this->midtransGateway->isConfigured();
        } catch (\Throwable) {
            // Keep safe boolean
        }

        return view('platform.settings.index', [
            'groupedSettings' => $groupedSettings,
            'isMidtransConfigured' => $isMidtransConfigured,
        ]);
    }

    /**
     * Atomically update a single whitelisted platform setting.
     */
    public function update(UpdatePlatformSettingRequest $request, string $setting): RedirectResponse
    {
        $definition = $request->definition();

        if ($definition === null) {
            abort(404, 'Pengaturan platform tidak ditemukan atau tidak diperbolehkan.');
        }

        $user = $request->user();

        if (! $user instanceof User) {
            abort(401);
        }

        $value = $request->settingValue();

        $this->settings->update($definition->key, $value, $user);

        return redirect()
            ->route('platform.settings.index')
            ->with('success', "Pengaturan '{$definition->label}' berhasil diperbarui.");
    }
}
