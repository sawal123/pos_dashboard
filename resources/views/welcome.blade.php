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
            .pricing-section { padding-top:0; }
            .pricing-grid { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:22px; max-width:1160px; margin:0 auto; }
            .pricing-card { display:flex; flex-direction:column; position:relative; padding:32px 28px; border:1px solid var(--line); border-radius:20px; background:white; box-shadow:0 16px 32px rgba(26,86,138,.06); }
            .pricing-card.featured { color:white; background:var(--navy); border-color:var(--navy); box-shadow:0 22px 42px rgba(12,28,49,.18); }
            .pricing-card .badge { display:inline-flex; width:fit-content; padding:6px 12px; color:#49657f; background:#edf4ff; border-radius:999px; font-size:10px; font-weight:700; letter-spacing:.08em; text-transform:uppercase; }
            .pricing-card.featured .badge { color:var(--navy); background:var(--lime); }
            .pricing-card h3 { margin:20px 0 7px; font-family:'Space Grotesk',system-ui,sans-serif; font-size:25px; letter-spacing:-.05em; }
            .pricing-card p { min-height:42px; margin:0; color:var(--muted); font-size:13px; line-height:1.6; }
            .pricing-card.featured p { color:rgba(221,236,249,.7); }
            .price-line { display:flex; align-items:baseline; gap:8px; margin:22px 0 20px; }
            .price-amount { font-family:'Space Grotesk',system-ui,sans-serif; font-size:clamp(28px,3.5vw,38px); font-weight:600; letter-spacing:-.06em; }
            .price-period { color:var(--muted); font-size:12px; font-weight:600; }
            .pricing-card.featured .price-period { color:rgba(221,236,249,.62); }
            .pricing-features { flex:1; display:grid; gap:11px; margin:0; padding:0; list-style:none; color:#49657f; font-size:13px; }
            .pricing-card.featured .pricing-features { color:rgba(221,236,249,.82); }
            .pricing-features li { display:flex; align-items:center; gap:9px; }
            .pricing-features svg { flex:0 0 auto; color:var(--blue); }
            .pricing-card.featured .pricing-features svg { color:var(--lime); }
            .pricing-card form { width:100%; margin:0; display:flex; }
            .pricing-card .pricing-btn { display:inline-flex; align-items:center; justify-content:center; width:100%; padding:14px 22px; border-radius:999px; font-size:14px; font-weight:700; font-family:inherit; text-decoration:none; transition:all .2s ease; margin-top:24px; cursor:pointer; box-sizing:border-box; border:none; line-height:1.2; text-align:center; }
            .pricing-card .pricing-btn:hover { transform:translateY(-2px); }
            .pricing-card .pricing-btn-free { color:var(--navy); background:#f1f5f9; border:1px solid #cbd5e1; }
            .pricing-card .pricing-btn-free:hover { background:#e2e8f0; color:var(--navy); }
            .pricing-card .pricing-btn-monthly { color:#ffffff; background:var(--blue); box-shadow:0 8px 20px rgba(35,89,204,.25); }
            .pricing-card .pricing-btn-monthly:hover { background:#1b47a8; color:#ffffff; box-shadow:0 10px 24px rgba(35,89,204,.35); }
            .pricing-card.featured .pricing-btn-yearly { color:var(--navy); background:var(--lime); box-shadow:0 10px 24px rgba(136,178,47,.3); }
            .pricing-card.featured .pricing-btn-yearly:hover { background:#a6db39; color:var(--navy); box-shadow:0 12px 28px rgba(136,178,47,.4); }
            .pricing-card .badge-active { color:#0369a1; background:#e0f2fe; border:1px solid #bae6fd; font-weight:700; }
            .pricing-card.featured .badge-active { color:#1e293b; background:#fef08a; border:1px solid #fde047; font-weight:800; }
            .pricing-card .pricing-btn-disabled { opacity:.6; cursor:default; background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; pointer-events:none; }
            .pricing-card .pricing-btn-disabled:hover { transform:none; }
            .pricing-card.active-plan-card { border-color:#3288df; }
            .pricing-card.featured.active-plan-card { border-color:var(--lime); }
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

            /* ==================== SMOOTH BOUNCE & FLOAT ANIMATIONS ==================== */
            @keyframes floatDashboardCard {
                0%, 100% {
                    transform: translate3d(0, 0, 0) rotate(1.5deg);
                    box-shadow: 0 34px 70px rgba(0, 0, 0, .28);
                }
                50% {
                    transform: translate3d(0, -12px, 0) rotate(0.6deg);
                    box-shadow: 0 46px 84px rgba(0, 0, 0, .36);
                }
            }

            @keyframes bounceFloatTop {
                0%, 100% {
                    transform: translate3d(0, 0, 0) scale(1);
                }
                45% {
                    transform: translate3d(0, -11px, 0) scale(1.02);
                }
                75% {
                    transform: translate3d(0, 3px, 0) scale(0.995);
                }
            }

            @keyframes bounceFloatBottom {
                0%, 100% {
                    transform: translate3d(0, 0, 0) scale(1);
                }
                50% {
                    transform: translate3d(0, -10px, 0) scale(1.025);
                }
                80% {
                    transform: translate3d(0, 2px, 0) scale(0.995);
                }
            }

            @keyframes orbitRingPulse {
                0%, 100% {
                    transform: rotate(-18deg) scale(1);
                    opacity: 0.85;
                }
                50% {
                    transform: rotate(-14deg) scale(1.04);
                    opacity: 1;
                }
            }

            @keyframes smoothIconBounce {
                0%, 100% {
                    transform: translate3d(0, 0, 0);
                }
                50% {
                    transform: translate3d(0, -6px, 0);
                }
            }

            @keyframes chartBarSpring {
                0% {
                    transform: scaleY(0.15);
                    opacity: 0.4;
                }
                65% {
                    transform: scaleY(1.06);
                    opacity: 1;
                }
                85% {
                    transform: scaleY(0.97);
                }
                100% {
                    transform: scaleY(1);
                    opacity: 1;
                }
            }

            @keyframes chartBarPulse {
                0%, 100% {
                    transform: scaleY(1);
                }
                50% {
                    transform: scaleY(1.06);
                }
            }

            @keyframes ctaOrbDrift {
                0%, 100% {
                    transform: translate3d(0, 0, 0) scale(1);
                }
                50% {
                    transform: translate3d(-14px, 10px, 0) scale(1.06);
                }
            }

            .dashboard-stage::before {
                animation: orbitRingPulse 7s ease-in-out infinite;
                will-change: transform, opacity;
            }

            .dashboard-card {
                animation: floatDashboardCard 5.6s cubic-bezier(0.37, 0, 0.63, 1) infinite;
                transition: transform 0.4s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.4s ease;
                will-change: transform;
            }

            .dashboard-stage:hover .dashboard-card {
                animation-play-state: paused;
                transform: translate3d(0, -8px, 0) rotate(0deg) scale(1.01);
            }

            .float-tag.top {
                animation: bounceFloatTop 4.2s cubic-bezier(0.34, 1.56, 0.64, 1) infinite;
                will-change: transform;
            }

            .float-tag.bottom {
                animation: bounceFloatBottom 4.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.9s infinite;
                will-change: transform;
            }

            .chart .bar {
                transform-origin: bottom center;
                animation:
                    chartBarSpring 1.1s cubic-bezier(0.34, 1.56, 0.64, 1) backwards,
                    chartBarPulse 3.6s ease-in-out 1.2s infinite;
            }
            .chart .bar:nth-child(1) { animation-delay: 0.10s, 1.3s; }
            .chart .bar:nth-child(2) { animation-delay: 0.18s, 1.6s; }
            .chart .bar:nth-child(3) { animation-delay: 0.26s, 1.9s; }
            .chart .bar:nth-child(4) { animation-delay: 0.34s, 2.2s; }
            .chart .bar:nth-child(5) { animation-delay: 0.42s, 2.5s; }
            .chart .bar:nth-child(6) { animation-delay: 0.50s, 2.8s; }
            .chart .bar:nth-child(7) { animation-delay: 0.58s, 3.1s; }

            .metric {
                transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
            }
            .metric:hover {
                transform: translateY(-4px) scale(1.02);
                box-shadow: 0 10px 20px rgba(20, 52, 82, 0.08);
            }

            .feature-icon,
            .workflow-card .step,
            .trust-item svg {
                animation: smoothIconBounce 3.8s ease-in-out infinite;
                transition: transform 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
                will-change: transform;
            }
            .feature:nth-child(2) .feature-icon,
            .workflow-card:nth-child(2) .step,
            .trust-item:nth-child(2) svg {
                animation-delay: 0.5s;
            }
            .feature:nth-child(3) .feature-icon,
            .workflow-card:nth-child(3) .step,
            .trust-item:nth-child(3) svg {
                animation-delay: 1s;
            }
            .feature:nth-child(4) .feature-icon {
                animation-delay: 1.5s;
            }

            .feature:hover .feature-icon,
            .workflow-card:hover .step {
                transform: translateY(-6px) scale(1.12) rotate(-4deg);
            }

            .workflow-card,
            .audience-card,
            .pricing-card {
                transition: transform 0.35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.35s ease, border-color 0.3s ease;
            }
            .workflow-card:hover,
            .audience-card:hover,
            .pricing-card:hover {
                transform: translateY(-7px);
                box-shadow: 0 22px 44px rgba(16, 37, 63, 0.12);
            }
            .pricing-card.featured:hover {
                box-shadow: 0 28px 54px rgba(12, 28, 49, 0.28);
            }

            .cta::before {
                animation: ctaOrbDrift 6.5s ease-in-out infinite;
            }
            .cta::after {
                animation: ctaOrbDrift 7.5s ease-in-out 1.2s infinite reverse;
            }

            /* ==================== SCROLL REVEAL EFFECTS ==================== */
            [data-reveal] {
                opacity: 0;
                will-change: opacity, transform;
                transition:
                    opacity 0.78s cubic-bezier(0.22, 1, 0.36, 1),
                    transform 0.78s cubic-bezier(0.34, 1.38, 0.64, 1);
                transition-delay: var(--reveal-delay, 0ms);
            }
            [data-reveal="up"] {
                transform: translate3d(0, 42px, 0);
            }
            [data-reveal="left"] {
                transform: translate3d(-42px, 0, 0);
            }
            [data-reveal="right"] {
                transform: translate3d(42px, 0, 0);
            }
            [data-reveal="zoom"] {
                transform: translate3d(0, 32px, 0) scale(0.92);
            }
            [data-reveal].is-revealed {
                opacity: 1;
                transform: translate3d(0, 0, 0) scale(1);
            }

            @media (prefers-reduced-motion: reduce) {
                .dashboard-stage::before,
                .dashboard-card,
                .float-tag.top,
                .float-tag.bottom,
                .chart .bar,
                .feature-icon,
                .workflow-card .step,
                .trust-item svg,
                .cta::before,
                .cta::after {
                    animation: none !important;
                }
                [data-reveal] {
                    opacity: 1 !important;
                    transform: none !important;
                    transition: none !important;
                }
            }
            @media (max-width:980px) { .pricing-grid { grid-template-columns:1fr; max-width:540px; margin:0 auto; } }
            @media (max-width:920px) { .hero-content { grid-template-columns:1fr; gap:32px; padding-top:65px; } .hero-copy { max-width:700px; } .dashboard-stage { max-width:680px; margin:0 auto; width:100%; } .features { grid-template-columns:repeat(2,1fr); } .split-section { grid-template-columns:1fr; gap:45px; } }
            @media (max-width:680px) { .shell { width:min(100% - 28px,1160px); } .hero-links { display:none; } .hero-actions .button-light { display:none; } .hero-content { min-height:0; padding:55px 0 88px; } .hero-copy h1 { font-size:clamp(42px,14vw,64px); } .hero-copy p { font-size:15px; } .dashboard-stage { padding:15px 0 26px; transform:scale(.98); } .dashboard-body { grid-template-columns:86px 1fr; } .dashboard-sidebar { padding-left:7px; padding-right:7px; } .dashboard-main { padding:14px; } .metric-row { gap:6px; } .metric { padding:9px; } .metric strong { font-size:13px; } .float-tag.top { right:-4px; } .float-tag.bottom { left:0; } .trust-strip { grid-template-columns:1fr; gap:15px; padding:24px 0; } .section { padding:76px 0; } .section-heading { margin-bottom:32px; } .features,.workflow,.audience,.pricing-grid { grid-template-columns:1fr; } .feature { min-height:0; } .feature-icon { margin-bottom:32px; } .workflow-card::after { display:none; } .cta { padding:45px 28px; border-radius:18px; } .cta-content { align-items:flex-start; flex-direction:column; } .footer { align-items:flex-start; flex-direction:column; gap:20px; } }
        </style>
    </head>
    <body>
        <header class="hero hero-glow">
            <nav class="shell hero-nav" aria-label="Navigasi utama">
                <a href="{{ route('home') }}" class="brand" aria-label="Net Kasir beranda"><x-app-logo-icon variant="white" width="34" height="34" /><span>Net Kasir</span></a>
                <div class="hero-links"><a class="nav-link" href="#fitur">Fitur</a><a class="nav-link" href="#harga">Harga</a><a class="nav-link" href="#cara-kerja">Cara kerja</a><a class="nav-link" href="#bisnis">Untuk bisnis</a></div>
                <div class="hero-actions">
                    @auth
                        <a class="button button-primary" href="{{ route('dashboard') }}">Dashboard <span aria-hidden="true">↗</span></a>
                    @else
                        @if (Route::has('login'))<a class="button button-light" href="{{ route('login') }}">Masuk</a>@endif
                        @if (Route::has('register'))<a class="button button-primary" href="{{ route('register') }}">Mulai sekarang <span aria-hidden="true">↗</span></a>@endif
                    @endauth
                </div>
            </nav>

            <div class="shell hero-content">
                <div class="hero-copy" data-reveal="left">
                    <span class="eyebrow" style="color:var(--lime);">Satu pusat kendali bisnis</span>
                    <h1>Bisnis rapi. <span>Keputusan cepat.</span></h1>
                    <p>Net Kasir menyatukan penjualan, stok, outlet, laporan, dan operasional harian agar Anda bisa fokus mengembangkan bisnis—bukan mengejar data.</p>
                    <div class="actions">
                        @auth
                            <a class="button button-primary" href="{{ route('dashboard') }}">Buka Dashboard <span aria-hidden="true">↗</span></a>
                        @else
                            @if (Route::has('register'))<a class="button button-primary" href="{{ route('register') }}">Bangun alur kerja Anda <span aria-hidden="true">↗</span></a>
                            @elseif (Route::has('login'))<a class="button button-primary" href="{{ route('login') }}">Masuk ke dashboard <span aria-hidden="true">↗</span></a>@endif
                        @endauth
                        <a class="button button-light" href="#fitur">Lihat fitur</a>
                    </div>
                    <div class="hero-note"><svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>Dibuat untuk operasional bisnis Indonesia</div>
                </div>

                <div class="dashboard-stage" aria-label="Pratinjau dashboard Net Kasir" data-reveal="zoom" data-reveal-delay="140">
                    <div class="float-tag top"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18M3 12h18"/></svg>Semua data dalam satu tempat</div>
                    <div class="dashboard-card">
                        <div class="dashboard-topbar"><div class="dashboard-dots"><i></i><i></i><i></i></div><strong>Net Kasir / Overview</strong><span>Hari ini, 24 Jun</span></div>
                        <div class="dashboard-body">
                            <aside class="dashboard-sidebar" aria-hidden="true"><div class="mini-logo"><x-app-logo-icon variant="blue" width="20" height="20" />Net Kasir</div><div class="mini-nav"><span class="active">Overview</span><span>Transaksi</span><span>Produk</span><span>Stok</span><span>Laporan</span></div></aside>
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
            <section class="shell trust-strip" aria-label="Keunggulan Net Kasir">
                <div class="trust-item" data-reveal="up" data-reveal-delay="0"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 4 7v5c0 5 3.4 8.7 8 9 4.6-.3 8-4 8-9V7l-8-4Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg>Data bisnis lebih terarah</div>
                <div class="trust-item" data-reveal="up" data-reveal-delay="100"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3v18M3 12h18"/><circle cx="12" cy="12" r="8"/></svg>Alur kerja mudah dipahami</div>
                <div class="trust-item" data-reveal="up" data-reveal-delay="200"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 4v6h6"/></svg>Siap berkembang bersama bisnis</div>
            </section>

            <section id="fitur" class="shell section">
                <div class="section-heading" data-reveal="up"><span class="eyebrow">Yang Anda butuhkan setiap hari</span><h2>Satu sistem untuk pekerjaan yang tidak boleh terlewat.</h2><p>Mulai dari transaksi pertama hingga laporan akhir hari, Net Kasir membantu tim bekerja dengan konteks yang sama.</p></div>
                <div class="features">
                    <article class="feature" data-reveal="zoom" data-reveal-delay="0"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 2v20M18 2v20M2 6h20M2 18h20"/></svg></div><h3>Penjualan terpusat</h3><p>Pantau transaksi, pelanggan, shift, dan arus kas dari dashboard yang sama.</p></article>
                    <article class="feature" data-reveal="zoom" data-reveal-delay="90"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h18v18H3z"/><path d="M7 7h10M7 12h10M7 17h6"/></svg></div><h3>Stok lebih terkendali</h3><p>Kelola produk, kategori, pergerakan stok, dan tanda stok menipis dengan lebih jelas.</p></article>
                    <article class="feature" data-reveal="zoom" data-reveal-delay="180"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19V5M4 19h17"/><path d="m7 15 4-5 3 3 5-7"/></svg></div><h3>Laporan yang berbicara</h3><p>Ubah data operasional menjadi ringkasan yang membantu Anda menentukan langkah berikutnya.</p></article>
                    <article class="feature" data-reveal="zoom" data-reveal-delay="270"><div class="feature-icon"><svg width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M17 16a4 4 0 1 0-10 0"/><circle cx="12" cy="8" r="3"/><path d="M4 20a8 8 0 0 1 16 0"/></svg></div><h3>Tim tetap selaras</h3><p>Atur peran, outlet, perangkat, dan akses agar setiap orang bekerja sesuai tanggung jawabnya.</p></article>
                </div>
            </section>

            <section class="section grid-paper">
                <div class="shell split-section">
                    <div data-reveal="left"><span class="eyebrow">Dibuat untuk bergerak cepat</span><h2 class="font-display" style="margin:17px 0 15px;font-size:clamp(34px,4vw,55px);line-height:1.05;letter-spacing:-.065em;">Dari meja kasir ke keputusan bisnis.</h2><p style="max-width:520px;margin:0;color:var(--muted);font-size:16px;line-height:1.7;">Tidak perlu berpindah-pindah tools untuk memahami apa yang sedang terjadi. Net Kasir merapikan aliran data supaya pemilik dan tim bisa bertindak lebih tenang.</p><ul class="check-list"><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Dashboard operasional yang mudah dipindai</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Hak akses dan konteks bisnis yang lebih aman</span></li><li><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg><span>Cloud sync untuk menjaga data tetap terhubung</span></li></ul></div>
                    <div class="workflow" id="cara-kerja"><article class="workflow-card" data-reveal="zoom" data-reveal-delay="0"><div class="step">01</div><h3>Catat</h3><p>Transaksi dan aktivitas operasional tercatat dalam alur yang rapi.</p></article><article class="workflow-card" data-reveal="zoom" data-reveal-delay="110"><div class="step">02</div><h3>Pahami</h3><p>Ringkasan dan laporan membantu membaca kondisi bisnis tanpa menebak.</p></article><article class="workflow-card" data-reveal="zoom" data-reveal-delay="220"><div class="step">03</div><h3>Bertindak</h3><p>Ambil keputusan lebih cepat berdasarkan data yang relevan dan terbaru.</p></article></div>
                </div>
            </section>

            <section id="bisnis" class="shell section">
                <div class="section-heading" data-reveal="up"><span class="eyebrow">Satu fondasi, banyak kemungkinan</span><h2>Ritme bisnis Anda berbeda. Sistemnya tetap bisa mengikuti.</h2></div>
                <div class="audience"><article class="audience-card" data-reveal="up" data-reveal-delay="0"><span>01 / Retail</span><h3>Kelola produk dan penjualan harian dengan lebih pasti.</h3></article><article class="audience-card" data-reveal="up" data-reveal-delay="110"><span>02 / Laundry</span><h3>Pantau order, status pengerjaan, dan pelanggan dalam satu alur.</h3></article><article class="audience-card" data-reveal="up" data-reveal-delay="220"><span>03 / F&amp;B &amp; jasa</span><h3>Bangun operasi yang siap tumbuh bersama tim Anda.</h3></article></div>
            </section>

            <section id="harga" class="shell section pricing-section">
                <div class="section-heading" data-reveal="up">
                    <span class="eyebrow">Paket yang sederhana</span>
                    <h2>Pilih paket yang paling pas untuk bisnis Anda.</h2>
                    <p>Mulai gratis secara offline atau aktifkan paket Cloud untuk sinkronisasi otomatis dan web dashboard.</p>
                </div>
                @if($errors->has('checkout'))
                    <div style="max-width:600px; margin:0 auto 24px; padding:12px 18px; background:#fef2f2; border:1px solid #fecaca; border-radius:12px; color:#991b1b; font-size:13px; text-align:center;">
                        {{ $errors->first('checkout') }}
                    </div>
                @endif
                <div class="pricing-grid">
                    @forelse($pricingCards as $card)
                        @php
                            $isCardFree = !empty($card['is_free']);
                            $isCardCloud = ($card['code'] ?? '') === \App\Models\Subscription::PLAN_CLOUD;
                            $isCurrentActivePlan = auth()->check() && (
                                ($hasCloudAccess && $isCardCloud) ||
                                (!$hasCloudAccess && $isCardFree)
                            );
                        @endphp
                        <article
                            class="pricing-card {{ $card['is_featured'] ? 'featured' : '' }} {{ $isCurrentActivePlan ? 'active-plan-card' : '' }}"
                            data-reveal="zoom"
                            data-reveal-delay="{{ $loop->index * 110 }}"
                        >
                            <div style="display:flex; justify-content:space-between; align-items:center; gap:8px;">
                                <span class="badge" @if(!empty($card['badge_style'])) style="{{ $card['badge_style'] }}" @endif>{{ $card['badge'] }}</span>
                                @if($isCurrentActivePlan)
                                    <span class="badge badge-active">✓ Paket Aktif</span>
                                @endif
                            </div>
                            <h3>{{ $card['name'] }}</h3>
                            <p>{{ $card['description'] }}</p>
                            <div class="price-line">
                                <span class="price-amount">{{ $card['price_formatted'] }}</span>
                                @if(!empty($card['price_period']))
                                    <span class="price-period">{{ $card['price_period'] }}</span>
                                @endif
                            </div>
                            <ul class="pricing-features">
                                @foreach($card['features'] as $feature)
                                    <li>
                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>
                                        <span>{!! $feature !!}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if(auth()->check())
                                @if($hasCloudAccess)
                                    {{-- Pengguna sudah berlangganan Paket Cloud Aktif --}}
                                    @if($isCardFree)
                                        <span class="{{ $card['btn_class'] }} pricing-btn-disabled">
                                            Paket Dasar
                                        </span>
                                    @else
                                        <a href="{{ route('dashboard') }}" class="{{ $card['btn_class'] }}">
                                            Buka Dashboard
                                        </a>
                                    @endif
                                @else
                                    {{-- Pengguna masih berada di Paket Free --}}
                                    @if($isCardFree)
                                        <a href="{{ route('dashboard') }}" class="{{ $card['btn_class'] }}">
                                            Buka Dashboard
                                        </a>
                                    @else
                                        <form method="POST" action="{{ route('subscriptions.checkout') }}">
                                            @csrf
                                            <input type="hidden" name="billing_period" value="{{ $card['period_key'] ?? 'monthly' }}">
                                            <button type="submit" class="{{ $card['btn_class'] }}">
                                                {{ $card['cta_label'] }}
                                            </button>
                                        </form>
                                    @endif
                                @endif
                            @else
                                {{-- Pengunjung Guest / Belum Login --}}
                                @if($isCardFree)
                                    <a
                                        href="{{ Route::has('register') ? route('register') : route('login') }}"
                                        class="{{ $card['btn_class'] }}"
                                    >
                                        {{ $card['cta_label'] }}
                                    </a>
                                @else
                                    <a
                                        href="{{ Route::has('register') ? route('register', ['plan' => $card['code'], 'period' => $card['period_key']]) : route('login') }}"
                                        class="{{ $card['btn_class'] }}"
                                    >
                                        {{ $card['cta_label'] }}
                                    </a>
                                @endif
                            @endif
                        </article>
                    @empty
                        <div style="grid-column: 1 / -1; text-align: center; padding: 48px 24px; border: 1px dashed var(--line); border-radius: 20px;">
                            <p style="color: var(--muted); margin: 0;">Belum ada paket langganan yang aktif saat ini.</p>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="shell" style="padding-bottom:76px;">
                <div class="cta" data-reveal="zoom">
                    <div class="cta-content">
                        <div>
                            <span class="eyebrow">Mulai dari langkah pertama</span>
                            <h2>Jadikan operasional lebih ringan hari ini.</h2>
                        </div>
                        @auth
                            <a class="button button-primary" href="{{ route('dashboard') }}">Buka Dashboard <span aria-hidden="true">↗</span></a>
                        @else
                            @if (Route::has('register'))
                                <a class="button button-primary" href="{{ route('register') }}">Buat akun Net Kasir <span aria-hidden="true">↗</span></a>
                            @elseif (Route::has('login'))
                                <a class="button button-primary" href="{{ route('login') }}">Masuk ke Net Kasir <span aria-hidden="true">↗</span></a>
                            @endif
                        @endauth
                    </div>
                </div>
            </section>
        </main>

        <footer class="shell footer" data-reveal="up">
            <a href="{{ route('home') }}" class="brand"><x-app-logo-icon variant="blue" width="32" height="32" /><span>Net Kasir</span></a>
            <div class="footer-links">
                <a href="#fitur">Fitur</a>
                <a href="#harga">Harga</a>
                <a href="#cara-kerja">Cara kerja</a>
                @auth
                    <a href="{{ route('dashboard') }}">Dashboard</a>
                @else
                    @if (Route::has('login'))<a href="{{ route('login') }}">Masuk</a>@endif
                @endauth
            </div>
            <span>&copy; {{ date('Y') }} Net Kasir</span>
        </footer>

        <script>
            (function () {
                const revealNodes = document.querySelectorAll('[data-reveal]');
                revealNodes.forEach((node) => {
                    const delay = Number(node.getAttribute('data-reveal-delay') || 0);
                    if (delay > 0) {
                        node.style.setProperty('--reveal-delay', delay + 'ms');
                    }
                });

                const reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (reduceMotion || typeof IntersectionObserver === 'undefined') {
                    revealNodes.forEach((node) => node.classList.add('is-revealed'));
                    return;
                }

                const observer = new IntersectionObserver(
                    (entries) => {
                        entries.forEach((entry) => {
                            if (entry.isIntersecting) {
                                entry.target.classList.add('is-revealed');
                                observer.unobserve(entry.target);
                            }
                        });
                    },
                    {
                        threshold: 0.14,
                        rootMargin: '0px 0px -48px 0px',
                    }
                );

                revealNodes.forEach((node) => observer.observe(node));
            })();
        </script>
    </body>
</html>
