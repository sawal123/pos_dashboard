@php
    $seoTitle = $title ?? config('seo.title', config('app.name', 'Laravel'));
    $seoDescription = $description ?? config('seo.description');
    $seoCanonical = $canonical ?? null;
    $seoIndexable = (bool) ($indexable ?? false);
    $seoRobots = $seoIndexable ? 'index,follow' : 'noindex,nofollow';
    $seoImage = $image ?? config('seo.image');
    $seoImageUrl = $seoImage ? (str_starts_with($seoImage, 'http') ? $seoImage : asset(ltrim($seoImage, '/'))) : null;
@endphp

<title>{{ $seoTitle }}</title>
<meta name="description" content="{{ $seoDescription }}">
<meta name="keywords" content="{{ config('seo.keywords') }}">
<meta name="robots" content="{{ $seoRobots }}">
<meta name="googlebot" content="{{ $seoRobots }}">
<meta name="author" content="{{ config('seo.site_name') }}">
<meta name="theme-color" content="#4f46e5">

@if ($seoCanonical && $seoIndexable)
    <link rel="canonical" href="{{ $seoCanonical }}">
@endif

<meta property="og:locale" content="{{ config('seo.locale', 'id_ID') }}">
<meta property="og:type" content="{{ $ogType ?? 'website' }}">
<meta property="og:site_name" content="{{ config('seo.site_name') }}">
<meta property="og:title" content="{{ $seoTitle }}">
<meta property="og:description" content="{{ $seoDescription }}">
@if ($seoCanonical)
    <meta property="og:url" content="{{ $seoCanonical }}">
@endif
@if ($seoImageUrl)
    <meta property="og:image" content="{{ $seoImageUrl }}">
@endif

<meta name="twitter:card" content="{{ $seoImageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $seoTitle }}">
<meta name="twitter:description" content="{{ $seoDescription }}">
@if ($seoImageUrl)
    <meta name="twitter:image" content="{{ $seoImageUrl }}">
@endif

@if ($schema ?? null)
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
