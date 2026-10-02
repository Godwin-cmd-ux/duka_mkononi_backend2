{{--
    Shared <head> for every public website page (landing, Shop, Track Orders,
    Advertisements, Businesses). Emits the fonts, the favicon, the viewport and
    one consistent design system so every public page looks like one product.

    Usage:  @include('partials.dm-locale')   first
            @include('partials.site-head', ['titleKey' => 'site_home.page_title'])
    `titleKey` / `metaKey` are translation keys; data-i18n fills them at runtime.
--}}
@php
    $titleKey = $titleKey ?? 'site_home.page_title';
    $metaKey = $metaKey ?? 'site_home.meta_description';
    $titleFallback = $titleFallback ?? 'DukaMkononi';
@endphp
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#1e3a8a">
    <meta name="description" content="" data-i18n="{{ $metaKey }}" data-i18n-attr="content">
    <title data-i18n="{{ $titleKey }}">{{ $titleFallback }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="icon" href="/favicon.ico" type="image/x-icon">
    <style>
        :root {
            --dm-navy: #0f172a;
            --dm-ink: #1e293b;
            --dm-muted: #64748b;
            --dm-line: #e2e8f0;
            --dm-bg: #f8fafc;
            --dm-card: #ffffff;
            --dm-primary: #1e3a8a;
            --dm-primary-dark: #172554;
            --dm-accent: #f59e0b;
            --dm-danger: #dc2626;
            --dm-success: #059669;
            --dm-radius: 14px;
            --dm-radius-sm: 10px;
            --dm-shadow: 0 1px 2px rgba(15, 23, 42, .06), 0 8px 24px rgba(15, 23, 42, .06);
            --dm-shadow-sm: 0 1px 2px rgba(15, 23, 42, .08);
            --dm-max: 1200px;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--dm-bg);
            color: var(--dm-ink);
            line-height: 1.6;
            overflow-x: hidden;
        }
        img { max-width: 100%; display: block; }
        a { color: inherit; text-decoration: none; }
        button { font-family: inherit; }
        ::selection { background: rgba(30, 58, 138, .18); }
        :focus-visible { outline: 3px solid rgba(30, 58, 138, .45); outline-offset: 2px; border-radius: 6px; }

        .dm-container { width: 100%; max-width: var(--dm-max); margin: 0 auto; padding: 0 20px; }
        .dm-section { padding: 64px 0; }
        .dm-section--tight { padding: 40px 0; }
        .dm-section-head { max-width: 720px; margin: 0 auto 36px; text-align: center; }
        .dm-eyebrow {
            display: inline-block; font-size: 12px; font-weight: 700; letter-spacing: .12em;
            text-transform: uppercase; color: var(--dm-primary); margin-bottom: 10px;
        }
        .dm-h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: clamp(30px, 5vw, 52px); line-height: 1.12; font-weight: 800; color: var(--dm-navy); letter-spacing: -.02em; }
        .dm-h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: clamp(24px, 3.4vw, 36px); line-height: 1.2; font-weight: 800; color: var(--dm-navy); letter-spacing: -.01em; }
        .dm-h3 { font-size: 18px; font-weight: 700; color: var(--dm-navy); }
        .dm-lead { font-size: 17px; color: var(--dm-muted); }
        .dm-muted { color: var(--dm-muted); }
        .dm-wrap-anywhere { overflow-wrap: anywhere; word-break: break-word; }

        /* Buttons */
        .dm-btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            padding: 12px 20px; border-radius: 999px; border: 1px solid transparent;
            font-size: 15px; font-weight: 600; cursor: pointer; line-height: 1.2;
            transition: transform .16s ease, box-shadow .16s ease, background .16s ease, color .16s ease;
            min-height: 46px; white-space: nowrap;
        }
        .dm-btn svg { width: 18px; height: 18px; flex: 0 0 auto; }
        .dm-btn--primary { background: var(--dm-primary); color: #fff; box-shadow: 0 6px 16px rgba(30, 58, 138, .22); }
        .dm-btn--primary:hover { background: var(--dm-primary-dark); transform: translateY(-1px); }
        .dm-btn--ghost { background: #fff; color: var(--dm-navy); border-color: var(--dm-line); }
        .dm-btn--ghost:hover { border-color: var(--dm-primary); color: var(--dm-primary); }
        .dm-btn--accent { background: var(--dm-accent); color: #1f2937; }
        .dm-btn--accent:hover { transform: translateY(-1px); }
        .dm-btn--danger { background: var(--dm-danger); color: #fff; }
        .dm-btn--sm { padding: 8px 14px; min-height: 38px; font-size: 13.5px; }
        .dm-btn--block { width: 100%; }
        .dm-btn[disabled] { opacity: .55; cursor: not-allowed; transform: none !important; }

        /* Cards */
        .dm-card { background: var(--dm-card); border: 1px solid var(--dm-line); border-radius: var(--dm-radius); box-shadow: var(--dm-shadow-sm); }
        .dm-grid { display: grid; gap: 20px; }
        .dm-grid--products { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); }
        .dm-grid--business { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
        .dm-grid--accounts { grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }

        /* Badges */
        .dm-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
        .dm-badge--ok { background: #ecfdf5; color: #047857; }
        .dm-badge--warn { background: #fffbeb; color: #b45309; }
        .dm-badge--info { background: #eff6ff; color: #1d4ed8; }
        .dm-badge--muted { background: #f1f5f9; color: var(--dm-muted); }
        .dm-badge--danger { background: #fef2f2; color: #b91c1c; }

        /* Forms */
        .dm-field { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
        .dm-label { font-size: 13.5px; font-weight: 600; color: var(--dm-ink); }
        .dm-input, .dm-select, .dm-textarea {
            width: 100%; padding: 12px 14px; border: 1px solid var(--dm-line); border-radius: var(--dm-radius-sm);
            font-size: 15px; background: #fff; color: var(--dm-ink); min-height: 46px;
            transition: border-color .15s ease, box-shadow .15s ease;
        }
        .dm-input:focus, .dm-select:focus, .dm-textarea:focus { outline: none; border-color: var(--dm-primary); box-shadow: 0 0 0 3px rgba(30, 58, 138, .14); }
        .dm-textarea { min-height: 130px; resize: vertical; }
        .dm-error { color: var(--dm-danger); font-size: 13px; }
        .dm-hint { color: var(--dm-muted); font-size: 12.5px; }

        /* Alerts */
        .dm-alert { padding: 12px 16px; border-radius: var(--dm-radius-sm); font-size: 14.5px; border: 1px solid transparent; margin-bottom: 14px; }
        .dm-alert--ok { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
        .dm-alert--err { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
        .dm-alert--info { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }

        /* Empty state */
        .dm-empty { text-align: center; padding: 48px 20px; color: var(--dm-muted); }
        .dm-empty svg { width: 44px; height: 44px; margin: 0 auto 14px; color: #cbd5e1; }
        .dm-empty h3 { margin-bottom: 6px; color: var(--dm-ink); }
        .dm-skeleton { background: linear-gradient(90deg, #eef2f7 25%, #e2e8f0 37%, #eef2f7 63%); background-size: 400% 100%; animation: dmShimmer 1.4s infinite; border-radius: 8px; }
        @keyframes dmShimmer { 0% { background-position: 100% 0; } 100% { background-position: -100% 0; } }

        /* Horizontal scroll tables (mobile) */
        .dm-table-wrap { width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dm-table { width: 100%; border-collapse: collapse; font-size: 14px; min-width: 640px; }
        .dm-table th, .dm-table td { padding: 10px 12px; text-align: left; border-bottom: 1px solid var(--dm-line); }
        .dm-table th { font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: var(--dm-muted); }

        /* Footer */
        .dm-footer { background: var(--dm-navy); color: #cbd5e1; padding: 48px 0 24px; margin-top: 40px; }
        .dm-footer a { color: #cbd5e1; }
        .dm-footer a:hover { color: #fff; }
        .dm-footer-grid { display: grid; gap: 28px; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); }
        .dm-footer h4 { color: #fff; font-size: 15px; margin-bottom: 12px; }
        .dm-footer ul { list-style: none; display: grid; gap: 8px; font-size: 14px; }
        .dm-footer-bottom { border-top: 1px solid rgba(255, 255, 255, .12); margin-top: 32px; padding-top: 18px; font-size: 13px; display: flex; flex-wrap: wrap; gap: 10px; justify-content: space-between; }

        @media (max-width: 640px) {
            .dm-section { padding: 44px 0; }
            .dm-container { padding: 0 16px; }
        }
    </style>
</head>
