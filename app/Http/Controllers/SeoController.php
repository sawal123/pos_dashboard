<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

final class SeoController extends Controller
{
    public function robots(): Response
    {
        $lines = ['User-agent: *'];

        if (! config('seo.indexable')) {
            $lines[] = 'Disallow: /';
        } else {
            $lines = array_merge($lines, [
                'Allow: /',
                'Disallow: /dashboard',
                'Disallow: /transactions',
                'Disallow: /products',
                'Disallow: /stock',
                'Disallow: /cash',
                'Disallow: /shifts',
                'Disallow: /customers',
                'Disallow: /laundry-orders',
                'Disallow: /outlets',
                'Disallow: /users',
                'Disallow: /reports',
                'Disallow: /devices',
                'Disallow: /sync',
                'Disallow: /subscription',
                'Disallow: /business-settings',
                'Disallow: /platform',
                'Disallow: /api/',
                'Disallow: /login',
                'Disallow: /register',
                'Disallow: /forgot-password',
                'Disallow: /reset-password',
                'Disallow: /invitations/',
            ]);
        }

        $lines[] = 'Sitemap: '.route('seo.sitemap');
        $lines[] = '';

        return response(implode("\n", $lines), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    public function sitemap(): Response
    {
        $urls = config('seo.indexable') ? [
            [
                'loc' => route('home'),
                'changefreq' => 'weekly',
                'priority' => '1.0',
            ],
        ] : [];

        return response()->view('seo.sitemap', compact('urls'), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
