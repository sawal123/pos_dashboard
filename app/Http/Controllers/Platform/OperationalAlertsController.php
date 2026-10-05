<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformOperationalAlerts;
use App\Support\PlatformOperationalAlertType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class OperationalAlertsController extends Controller
{
    /**
     * Display a listing of in-app operational alerts computed strictly on request.
     */
    public function index(Request $request, PlatformOperationalAlerts $alertsService): View
    {
        $data = $alertsService->get();
        $alerts = $data['alerts'];

        $q = trim((string) $request->input('q', ''));
        $severity = $request->input('severity');
        $type = $request->input('type');

        // Filter by Severity
        if ($severity && in_array($severity, [PlatformOperationalAlertType::SEVERITY_CRITICAL, PlatformOperationalAlertType::SEVERITY_WARNING, PlatformOperationalAlertType::SEVERITY_INFO], true)) {
            $alerts = array_filter($alerts, fn (array $item): bool => $item['severity'] === $severity);
        } else {
            $severity = null;
        }

        // Filter by Type
        if ($type && array_key_exists($type, PlatformOperationalAlertType::types())) {
            $alerts = array_filter($alerts, fn (array $item): bool => $item['type'] === $type);
        } else {
            $type = null;
        }

        // Search Keyword
        if ($q !== '') {
            $search = strtolower($q);
            $alerts = array_filter($alerts, function (array $item) use ($search): bool {
                $haystack = strtolower(implode(' ', [
                    $item['business_name'] ?? '',
                    $item['title'] ?? '',
                    $item['target_label'] ?? '',
                    $item['target_id'] ?? '',
                    $item['description'] ?? '',
                ]));

                return str_contains($haystack, $search);
            });
        }

        return view('platform.alerts.index', [
            'alerts' => array_values($alerts),
            'summary' => $data['summary'],
            'definitions' => $data['definitions'],
            'limitations' => $data['limitations'],
            'filters' => [
                'q' => $q,
                'severity' => $severity,
                'type' => $type,
            ],
            'severities' => PlatformOperationalAlertType::severities(),
            'types' => PlatformOperationalAlertType::types(),
        ]);
    }
}
