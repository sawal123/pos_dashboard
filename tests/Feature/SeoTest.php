<?php

namespace Tests\Feature;

use Tests\TestCase;

class SeoTest extends TestCase
{
    public function test_public_homepage_exposes_seo_metadata_and_private_pages_are_not_indexable(): void
    {
        $response = $this->get(route('home'));

        $response->assertOk()
            ->assertSee('<meta name="description"', false)
            ->assertSee('<meta name="robots" content="noindex,nofollow">', false)
            ->assertSee('<meta property="og:type" content="website">', false)
            ->assertSee('<script type="application/ld+json">', false);
    }

    public function test_robots_and_sitemap_are_available(): void
    {
        $this->get(route('seo.robots'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('Sitemap: '.route('seo.sitemap'));

        config(['seo.indexable' => true]);

        $this->get(route('seo.sitemap'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee('<loc>'.e(route('home')).'</loc>', false);
    }
}
