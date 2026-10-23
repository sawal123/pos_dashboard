<!DOCTYPE html>
<html lang="id" class="scroll-smooth">
    <head>
        @include('partials.seo', [
            'title' => config('seo.title'),
            'description' => config('seo.description'),
            'canonical' => route('home'),
            'indexable' => config('seo.indexable'),
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => config('seo.site_name'),
                'url' => route('home'),
                'description' => config('seo.description'),
                'inLanguage' => 'id-ID',
            ],
        ])

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">

        <style>
            :root { --ink:#10253f; --muted:#69809a; --line:#dce8f2; --paper:#f6fbff; --lime:#c7f36b; --blue:#2d6df6; --navy:#0c1c31; --cyan:#8fe8ea; }
            * { box-sizing: border-box; }
            html { background: var(--paper); }
            body { margin: 0; color: var(--ink); background: var(--paper); font-family: 'DM Sans', system-ui, sans-serif; }
            a { color: inherit; text-decoration: none; }
            .font-display { font-family: 'Space Grotesk', system-ui, sans-serif; }
            .shell { width: min(1160px, calc(100% - 40px)); margin: 0 auto; }
            .eyebrow { display:inline-flex; align-items:center; gap:9px; color:var(--blue); font-size:11px; font-weight:700; letter-spacing:.16em; text-transform:uppercase; }
            .eyebrow::before { width:20px; height:2px; background:var(--blue); content:''; }
            .grid-paper { background-image:linear-gradient(rgba(76,117,159,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(76,117,159,.07) 1px,transparent 1px); background-size:32px 32px; }
            .hero-glow { background:radial-gradient(circle at 76% 27%,rgba(143,232,234,.32),transparent 25%),radial-gradient(circle at 13% 12%,rgba(199,243,107,.22),transparent 28%); }
            .nav-link { color:#67809a; font-size:14px; font-weight:600; transition:color .2s ease; }
            .nav-link:hover { color:var(--ink); }
            .button { display:inline-flex; align-items:center; justify-content:center; gap:10px; border-radius:999px; padding:13px 20px; font-size:14px; font-weight:700; transition:transform .2s ease,box-shadow .2s ease,background .2s ease; }
            .button:hover { transform:translateY(-2px); }
            .button-primary { color:var(--navy); background:var(--lime); box-shadow:0 12px 30px rgba(136,178,47,.24); }
            .button-primary:hover { box-shadow:0 16px 34px rgba(136,178,47,.36); }
            .button-light { color:white; background:rgba(255,255,255,.1); border:1px solid rgba(255,255,255,.18); }
            .button-light:hover { background:rgba(255,255,255,.17); }
            .hero { position:relative; overflow:hidden; color:white; background:var(--navy); }
            .hero::after { position:absolute; inset:auto 0 0; height:1px; background:rgba(255,255,255,.12); content:''; }
            .hero-nav { display:flex; align-items:center; justify-content:space-between; min-height:78px; position:relative; z-index:2; }
            .brand { display:inline-flex; align-items:center; gap:10px; font-family:'Space Grotesk',system-ui,sans-serif; font-size:20px; font-weight:700; letter-spacing:-.04em; }
            .brand-mark { display:grid; place-items:center; width:32px; height:32px; color:var(--navy); background:var(--lime); border-radius:10px 10px 10px 3px; font-size:17px; font-weight:700; }
            .hero-links { display:flex; align-items:center; gap:28px; }
            .hero-links .nav-link { color:rgba(255,255,255,.68); }
            .hero-links .nav-link:hover { color:white; }
            .hero-actions { display:flex; align-items:center; gap:12px; }
            .hero-content { display:grid; grid-template-columns:minmax(0,.86fr) minmax(480px,1.14fr); align-items:center; gap:62px; min-height:625px; padding:76px 0 110px; position:relative; z-index:1; }
            .hero-copy { max-width:560px; }
            .hero-copy h1 { margin:20px 0 22px; max-width:590px; color:white; font-family:'Space Grotesk',system-ui,sans-serif; font-size:clamp(44px,6vw,78px); font-weight:600; letter-spacing:-.075em; line-height:.99; }
            .hero-copy h1 span { color:var(--lime); }
            .hero-copy p { max-width:500px; margin:0; color:rgba(221,236,249,.7); font-size:17px; line-height:1.7; }
            .hero-copy .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:34px; }
            .hero-note { display:flex; align-items:center; gap:9px; margin-top:20px; color:rgba(221,236,249,.5); font-size:12px; }
            .hero-note svg { color:var(--lime); }
            .dashboard-stage { position:relative; padding:24px 0 24px 16px; }
            .dashboard-stage::before { position:absolute; top:3%; right:-12%; width:75%; height:82%; border:1px solid rgba(143,232,234,.26); border-radius:50%; transform:rotate(-18deg); content:''; }
            .dashboard-card { position:relative; z-index:1; overflow:hidden; border:1px solid rgba(255,255,255,.16); border-radius:20px; background:#f8fcff; box-shadow:0 34px 70px rgba(0,0,0,.28); transform:rotate(1.5deg); }
            .dashboard-topbar { display:flex; align-items:center; justify-content:space-between; padding:15px 18px; color:white; background:#122d4d; }
            .dashboard-topbar strong { font-family:'Space Grotesk',system-ui,sans-serif; font-size:13px; }
            .dashboard-topbar span { color:#a6c1dc; font-size:10px; }
            .dashboard-dots { display:flex; gap:5px; }
            .dashboard-dots i { display:block; width:6px; height:6px; border-radius:50%; background:#6d87a4; }
            .dashboard-body { display:grid; grid-template-columns:120px 1fr; min-height:365px; }
            .dashboard-sidebar { padding:20px 13px; background:#edf5fa; border-right:1px solid #dbe8f1; }
            .mini-logo { display:flex; align-items:center; gap:6px; margin:0 5px 28px; color:#163452; font-family:'Space Grotesk',system-ui,sans-serif; font-size:11px; font-weight:700; }
            .mini-logo b { display:grid; place-items:center; width:20px; height:20px; color:#10253f; background:var(--lime); border-radius:6px 6px 6px 2px; font-size:10px; }
            .mini-nav { display:grid; gap:9px; }
            .mini-nav span { padding:8px 9px; color:#7790a7; border-radius:7px; font-size:9px; font-weight:600; }
            .mini-nav span.active { color:#2359cc; background:#dbe8ff; }
            .dashboard-main { padding:22px; background:white; }
            .dashboard-heading { display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:18px; }
            .dashboard-heading small { display:block; margin-bottom:4px; color:#8aa0b5; font-size:9px; }
            .dashboard-heading strong { color:#143452; font-family:'Space Grotesk',system-ui,sans-serif; font-size:20px; letter-spacing:-.04em; }
            .dashboard-heading button { padding:7px 10px; color:#2359cc; background:#edf3ff; border:0; border-radius:7px; font-size:9px; font-weight:700; }
            .metric-row { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
            .metric { padding:13px; border:1px solid #e6eef5; border-radius:10px; }
            .metric label { display:block; margin-bottom:9px; color:#88a0b6; font-size:8px; font-weight:600; }
            .metric strong { display:block; color:#183957; font-family:'Space Grotesk',system-ui,sans-serif; font-size:17px; }
            .metric em { color:#4baf8d; font-size:8px; font-style:normal; font-weight:700; }
            .metric.blue { background:#edf4ff; border-color:#d6e5ff; }
            .metric.lime { background:#f4fbdf; border-color:#e4f1b9; }
            .chart-card { margin-top:11px; padding:15px; border:1px solid #e6eef5; border-radius:10px; }
            .chart-title { display:flex; justify-content:space-between; margin-bottom:8px; color:#54708b; font-size:9px; font-weight:700; }
            .chart-title span { color:#a2b4c4; font-weight:500; }
            .chart { display:flex; align-items:end; gap:7px; height:104px; padding:8px 3px 0; border-bottom:1px solid #edf2f6; background:repeating-linear-gradient(to bottom,transparent 0,transparent 25px,#edf2f6 26px); }
            .bar { flex:1; min-width:12px; border-radius:5px 5px 0 0; background:#b9d2ff; }
            .bar:nth-child(2),.bar:nth-child(5) { background:#83aefc; }
            .bar:nth-child(3),.bar:nth-child(7) { background:var(--blue); }
            .bar:nth-child(4) { background:#78d7d8; }
            .chart-labels { display:flex; justify-content:space-between; padding-top:7px; color:#a3b4c2; font-size:7px; }
            .float-tag { position:absolute; z-index:2; display:flex; align-items:center; gap:9px; padding:12px 14px; color:#fff; background:#163657; border:1px solid rgba(255,255,255,.14); border-radius:11px; box-shadow:0 15px 30px rgba(0,0,0,.18); font-size:11px; font-weight:700; }
            .float-tag svg { color:var(--lime); }
            .float-tag.top { top:6px; right:-25px; }
            .float-tag.bottom { bottom:8px; left:-10px; color:var(--navy); background:var(--lime); }
            .trust-strip { display:grid; grid-template-columns:repeat(3,1fr); gap:28px; padding:29px 0; border-bottom:1px solid var(--line); }
            .trust-item { display:flex; align-items:center; gap:13px; color:#506d88; font-size:13px; font-weight:600; }
            .trust-item svg { flex:0 0 auto; color:var(--blue); }
            .section { padding:115px 0; }
            .section-heading { max-width:640px; margin-bottom:50px; }
            .section-heading h2 { margin:17px 0 15px; color:var(--ink); font-family:'Space Grotesk',system-ui,sans-serif; font-size:clamp(34px,4vw,55px); font-weight:600; letter-spacing:-.065em; line-height:1.05; }
            .section-heading p { max-width:550px; margin:0; color:var(--muted); font-size:16px; line-height:1.7; }
            .features { display:grid; grid-template-columns:repeat(4,1fr); gap:15px; }
            .feature { min-height:260px; padding:25px; border:1px solid var(--line); border-radius:17px; background:rgba(255,255,255,.62); transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease; }
            .feature:hover { border-color:#b9d3ed; box-shadow:0 18px 38px rgba(26,86,138,.09); transform:translateY(-6px); }
            .feature-icon { display:grid; place-items:center; width:42px; height:42px; margin-bottom:50px; color:var(--navy); background:var(--lime); border-radius:12px 12px 12px 3px; }
            .feature:nth-child(2) .feature-icon { color:#2359cc; background:#dbe8ff; }
            .feature:nth-child(3) .feature-icon { color:#17857e; background:#d8f5f0; }
            .feature:nth-child(4) .feature-icon { color:#9b5c0f; background:#ffe5bd; }
            .feature h3 { margin:0 0 9px; font-family:'Space Grotesk',system-ui,sans-serif; font-size:19px; letter-spacing:-.04em; }
            .feature p { margin:0; color:var(--muted); font-size:13px; line-height:1.65; }
            .split-section { display:grid; grid-template-columns:.9fr 1.1fr; align-items:center; gap:85px; }
            .check-list { display:grid; gap:19px; margin:31px 0 0; padding:0; list-style:none; }
            .check-list li { display:flex; align-items:flex-start; gap:13px; color:#49657f; font-size:14px; line-height:1.55; }
            .check-list svg { flex:0 0 auto; margin-top:2px; color:#3288df; }
            .workflow { display:grid; grid-template-columns:repeat(3,1fr); gap:18px; }
            .workflow-card { position:relative; padding:28px 25px 25px; border-radius:16px; background:white; border:1px solid var(--line); }
            .workflow-card::after { position:absolute; top:39px; right:-27px; width:35px; height:1px; background:#b9cee0; content:''; }
            .workflow-card:last-child::after { display:none; }
            .step { display:grid; place-items:center; width:35px; height:35px; margin-bottom:35px; color:white; background:var(--blue); border-radius:10px; font-family:'Space Grotesk',system-ui,sans-serif; font-size:14px; font-weight:700; }
            .workflow-card:nth-child(2) .step { color:var(--navy); background:var(--lime); }
            .workflow-card:nth-child(3) .step { color:var(--navy); background:var(--cyan); }
            .workflow-card h3 { margin:0 0 9px; font-family:'Space Grotesk',system-ui,sans-serif; font-size:19px; letter-spacing:-.04em; }
            .workflow-card p { margin:0; color:var(--muted); font-size:13px; line-height:1.65; }
            .audience { display:grid; grid-template-columns:repeat(3,1fr); gap:15px; }
            .audience-card { min-height:178px; padding:26px; border-radius:17px; color:white; background:var(--navy); }
            .audience-card:nth-child(2) { color:var(--navy); background:var(--lime); }
            .audience-card:nth-child(3) { color:var(--navy); background:var(--cyan); }
            .audience-card span { display:inline-block; margin-bottom:42px; color:inherit; opacity:.58; font-size:11px; font-weight:700; letter-spacing:.14em; text-transform:uppercase; }
            .audience-card h3 { margin:0; font-family:'Space Grotesk',system-ui,sans-serif; font-size:23px; letter-spacing:-.05em; }
            .cta { overflow:hidden; position:relative; padding:74px 70px; color:white; background:var(--blue); border-radius:25px; }
            .cta::before,.cta::after { position:absolute; width:250px; height:250px; border:1px solid rgba(255,255,255,.18); border-radius:50%; content:''; }
            .cta::before { top:-150px; right:-45px; }
            .cta::after { right:80px; bottom:-195px; }
            .cta-content { position:relative; z-index:1; display:flex; align-items:end; justify-content:space-between; gap:35px; }
            .cta h2 { max-width:620px; margin:15px 0 0; color:white; font-family:'Space Grotesk',system-ui,sans-serif; font-size:clamp(34px,4vw,57px); line-height:1.02; letter-spacing:-.065em; }
            .cta .eyebrow { color:var(--lime); }
            .cta .eyebrow::before { background:var(--lime); }
            .footer { display:flex; align-items:center; justify-content:space-between; padding:32px 0 40px; color:#7790a7; font-size:12px; }
            .footer .brand { color:var(--ink); font-size:17px; }
            .footer-links { display:flex; gap:22px; }
            @media (max-width:920px) { .hero-content { grid-template-columns:1fr; gap:32px; padding-top:65px; } .hero-copy { max-width:700px; } .dashboard-stage { max-width:680px; margin:0 auto; width:100%; } .features { grid-template-columns:repeat(2,1fr); } .split-section { grid-template-columns:1fr; gap:45px; } }
            @media (max-width:680px) { .shell { width:min(100% - 28px,1160px); } .hero-links { display:none; } .hero-actions .button-light { display:none; } .hero-content { min-height:0; padding:55px 0 88px; } .hero-copy h1 { font-size:clamp(42px,14vw,64px); } .hero-copy p { font-size:15px; } .dashboard-stage { padding:15px 0 26px; transform:scale(.98); } .dashboard-body { grid-template-columns:86px 1fr; } .dashboard-sidebar { padding-left:7px; padding-right:7px; } .dashboard-main { padding:14px; } .metric-row { gap:6px; } .metric { padding:9px; } .metric strong { font-size:13px; } .float-tag.top { right:-4px; } .float-tag.bottom { left:0; } .trust-strip { grid-template-columns:1fr; gap:15px; padding:24px 0; } .section { padding:76px 0; } .section-heading { margin-bottom:32px; } .features,.workflow,.audience { grid-template-columns:1fr; } .feature { min-height:0; } .feature-icon { margin-bottom:32px; } .workflow-card::after { display:none; } .cta { padding:45px 28px; border-radius:18px; } .cta-content { align-items:flex-start; flex-direction:column; } .footer { align-items:flex-start; flex-direction:column; gap:20px; } }
        </style>
    </head>
    <body>
        <header class="hero hero-glow">
            <nav class="shell hero-nav" aria-label="Navigasi utama">
                <a href="{{ route('home') }}" class="brand" aria-label="NexaPOS beranda"><span class="brand-mark">N</span><span>NexaPOS</span></a>
                <div class="hero-links"><a class="nav-link" href="#fitur">Fitur</a><a class="nav-link" href="#cara-kerja">Cara kerja</a><a class="nav-link" href="#bisnis">Untuk bisnis</a></div>
                <div class="hero-actions">
                    @if (Route::has('login'))<a class="button button-light" href="{{ route('login') }}">Masuk</a>@endif
                    @if (Route::has('register'))<a class="button button-primary" href="{{ route('register') }}">Mulai sekarang <span aria-hidden="true">↗</span></a>@endif
                </div>
            </nav>

            <div class="shell hero-content">
                <div class="hero-copy">
                    <span class="eyebrow" style="color:var(--lime);">Satu pusat kendali bisnis</span>
                    <h1>Bisnis rapi. <span>Keputusan cepat.</span></h1>
                    <p>NexaPOS menyatukan penjualan, stok, outlet, laporan, dan operasional harian agar Anda bisa fokus mengembangkan bisnis—bukan mengejar data.</p>
                    <div class="actions">
                        @if (Route::has('register'))<a class="button button-primary" href="{{ route('register') }}">Bangun alur kerja Anda <span aria-hidden="true">↗</span></a>
                        @elseif (Route::has('login'))<a class="button button-primary" href="{{ route('login') }}">Masuk ke dashboard <span aria-hidden="true">↗</span></a>@endif
                        <a class="button button-light" href="#fitur">Lihat fitur</a>
                    </div>
                    <div class="hero-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>Dibuat untuk operasional bisnis Indonesia</div>
                </div>

                <div class="dashboard-stage" aria-label="Pratinjau dashboard NexaPOS">
                    <div class="float-tag top"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18"/></svg>Semua data dalam satu tempat</div>
                    <div class="dashboard-card">
                        <div class="dashboard-topbar"><div class="dashboard-dots"><i></i><i></i><i></i></div><strong>NexaPOS / Overview</strong><span>Hari ini, 24 Jun</span></div>
                        <div class="dashboard-body">
                            <aside class="dashboard-sidebar" aria-hidden="true"><div class="mini-logo"><b>N</b>NexaPOS</div><div class="mini-nav"><span class="active">Overview</span><span>Transaksi</span><span>Produk</span><span>Stok</span><span>Laporan</span></div></aside>
                            <div class="dashboard-main">
                                <div class="dashboard-heading"><div><small>Ringkasan operasional</small><strong>Selamat datang kembali</strong></div><button type="button">Export laporan</button></div>
                                <div class="metric-row"><div class="metric blue"><label>Penjualan hari ini</label><strong>Rp 8,4 jt</strong><em>↑ Terpantau</em></div><div class="metric lime"><label>Transaksi</label><strong>128</strong><em>Aktif</em></div><div class="metric"><label>Stok menipis</label><strong>06</strong><em>Perlu cek</em></div></div>
                                <div class="chart-card"><div class="chart-title">Tren penjualan <span>7 hari terakhir</span></div><div class="chart" aria-hidden="true"><i class="bar" style="height:34%"></i><i class="bar" style="height:48%"></i><i class="bar" style="height:42%"></i><i class="bar" style="height:66%"></i><i class="bar" style="height:57%"></i><i class="bar" style="height:78%"></i><i class="bar" style="height:93%"></i></div><div class="chart-labels"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div></div>
                            </div>
                        </div>
                    </div>
                    <div class="float-tag bottom"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7 10 17l-5-5"/></svg>Lebih siap mengambil keputusan</div>
                </div>
            </div>
        </header>

        <main>
            <section class="shell trust-strip" aria-label="Keunggulan NexaPOS">
                <div class="trust-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.7 8 9 4.6-.3 8-4 8-9V7l-8-4Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>Data bisnis lebih terarah</div>
                <div class="trust-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/></svg>Alur kerja mudah dipahami</div>
                <div class="trust-item"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v6h6"/></svg>Siap berkembang bersama bisnis</div>
            </section>

            <section id="fitur" class="shell section">
                <div class="section-heading"><span class="eyebrow">Yang Anda butuhkan setiap hari</span><h2>Satu sistem untuk pekerjaan yang tidak boleh terlewat.</h2><p>Mulai dari transaksi pertama hingga laporan akhir hari, NexaPOS membantu tim bekerja dengan konteks yang sama.</p></div>
                <div class="features">
                    <article class="feature"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2v20M18 2v20M2 6h20M2 18h20"/></svg></div><h3>Penjualan terpusat</h3><p>Pantau transaksi, pelanggan, shift, dan arus kas dari dashboard yang sama.</p></article>
                    <article class="feature"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"/><path d="M7 7h10M7 12h10M7 17h6"/></svg></div><h3>Stok lebih terkendali</h3><p>Kelola produk, kategori, pergerakan stok, dan tanda stok menipis dengan lebih jelas.</p></article>
                    <article class="feature"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h17"/><path d="m7 15 4-5 3 3 5-7"/></svg></div><h3>Laporan yang berbicara</h3><p>Ubah data operasional menjadi ringkasan yang membantu Anda menentukan langkah berikutnya.</p></article>
                    <article class="feature"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 16a4 4 0 1 0-10 0"/><circle cx="12" cy="8" r="3"/><path d="M4 20a8 8 0 0 1 16 0"/></svg></div><h3>Tim tetap selaras</h3><p>Atur peran, outlet, perangkat, dan akses agar setiap orang bekerja sesuai tanggung jawabnya.</p></article>
                </div>
            </section>

            <section class="section grid-paper">
                <div class="shell split-section">
                    <div><span class="eyebrow">Dibuat untuk bergerak cepat</span><h2 class="font-display" style="margin:17px 0 15px;font-size:clamp(34px,4vw,55px);line-height:1.05;letter-spacing:-.065em;">Dari meja kasir ke keputusan bisnis.</h2><p style="max-width:520px;margin:0;color:var(--muted);font-size:16px;line-height:1.7;">Tidak perlu berpindah-pindah tools untuk memahami apa yang sedang terjadi. NexaPOS merapikan aliran data supaya pemilik dan tim bisa bertindak lebih tenang.</p><ul class="check-list"><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Dashboard operasional yang mudah dipindai</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Hak akses dan konteks bisnis yang lebih aman</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Cloud sync untuk menjaga data tetap terhubung</span></li></ul></div>
                    <div class="workflow" id="cara-kerja"><article class="workflow-card"><div class="step">01</div><h3>Catat</h3><p>Transaksi dan aktivitas operasional tercatat dalam alur yang rapi.</p></article><article class="workflow-card"><div class="step">02</div><h3>Pahami</h3><p>Ringkasan dan laporan membantu membaca kondisi bisnis tanpa menebak.</p></article><article class="workflow-card"><div class="step">03</div><h3>Bertindak</h3><p>Ambil keputusan lebih cepat berdasarkan data yang relevan dan terbaru.</p></article></div>
                </div>
            </section>

            <section id="bisnis" class="shell section">
                <div class="section-heading"><span class="eyebrow">Satu fondasi, banyak kemungkinan</span><h2>Ritme bisnis Anda berbeda. Sistemnya tetap bisa mengikuti.</h2></div>
                <div class="audience"><article class="audience-card"><span>01 / Retail</span><h3>Kelola produk dan penjualan harian dengan lebih pasti.</h3></article><article class="audience-card"><span>02 / Laundry</span><h3>Pantau order, status pengerjaan, dan pelanggan dalam satu alur.</h3></article><article class="audience-card"><span>03 / F&amp;B &amp; jasa</span><h3>Bangun operasi yang siap tumbuh bersama tim Anda.</h3></article></div>
            </section>

            <section class="shell" style="padding-bottom:76px;"><div class="cta"><div class="cta-content"><div><span class="eyebrow">Mulai dari langkah pertama</span><h2>Jadikan operasional lebih ringan hari ini.</h2></div>@if (Route::has('register'))<a class="button button-primary" href="{{ route('register') }}">Buat akun NexaPOS <span aria-hidden="true">↗</span></a>@elseif (Route::has('login'))<a class="button button-primary" href="{{ route('login') }}">Masuk ke NexaPOS <span aria-hidden="true">↗</span></a>@endif</div></div></section>
        </main>

        <footer class="shell footer"><a href="{{ route('home') }}" class="brand"><span class="brand-mark">N</span><span>NexaPOS</span></a><div class="footer-links"><a href="#fitur">Fitur</a><a href="#cara-kerja">Cara kerja</a>@if (Route::has('login'))<a href="{{ route('login') }}">Masuk</a>@endif</div><span>&copy; {{ date('Y') }} NexaPOS</span></footer>
    </body>
</html>
