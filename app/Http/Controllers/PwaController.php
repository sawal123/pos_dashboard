<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PwaController extends Controller
{
    /**
     * Serve the PWA Web App Manifest with correct MIME type.
     */
    public function manifest(): Response
    {
        $manifestPath = public_path('manifest.webmanifest');

        if (! file_exists($manifestPath)) {
            $manifestPath = public_path('manifest.json');
        }

        $content = file_exists($manifestPath)
            ? file_get_contents($manifestPath)
            : '{}';

        return response($content, 200, [
            'Content-Type' => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Display the offline fallback view.
     */
    public function offline(): View
    {
        return view('offline');
    }

    /**
     * Serve the Service Worker file with appropriate headers if routed through Laravel.
     */
    public function serviceWorker(): BinaryFileResponse|Response
    {
        $swPath = public_path('sw.js');

        if (! file_exists($swPath)) {
            return response('// Service worker not found', 404, [
                'Content-Type' => 'application/javascript; charset=utf-8',
            ]);
        }

        return response()->file($swPath, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Service-Worker-Allowed' => '/',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }
}
