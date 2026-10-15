@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_nyingine.page_title">Nyingine - Dukamkononi Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        .msimamizi-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: white; border-right: 1px solid #ecf0f1;
            position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
            transition: transform 0.3s; display: flex; flex-direction: column;
            box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px; font-weight: bold;
        }
        .logo-text h2 { font-size: 18px; font-weight: 800; color: #2c3e50; }
        .logo-text p { font-size: 12px; color: #7f8c8d; }
        .nav-items { flex: 1; padding: 0 16px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 14px 18px;
            margin-bottom: 8px; border-radius: 14px; text-decoration: none;
            color: #5d6d7e; font-weight: 500; transition: all 0.2s;
        }
        .nav-item:hover, .nav-item.active { background-color: #fdeaea; color: #e74c3c; }
        .nav-icon { font-size: 20px; width: 24px; }
        .nav-label { font-size: 15px; font-weight: 600; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #fdeaea;
            display: flex; align-items: center; justify-content: center;
            color: #e74c3c; font-weight: 800; overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #e74c3c; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 8px; padding: 10px 14px;
            border-radius: 12px; color: #e74c3c; font-weight: 700; font-size: 14px;
            cursor: pointer; transition: background 0.2s;
        }
        .logout-btn:hover { background: #fdeaea; }

        .main-content { margin-left: 280px; flex: 1; padding: 34px 30px 60px; min-width: 0; }
        .page-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 20px; }
        .page-title { font-size: 24px; font-weight: 800; color: #2c3e50; }
        .page-sub { font-size: 13.5px; color: #7f8c8d; margin-top: 4px; }
        .ai-chip {
            display: inline-flex; align-items: center; gap: 6px; font-size: 11px;
            font-weight: 800; letter-spacing: .04em; color: #7c5cbf;
            background: #f3eeff; border: 1px solid #e3d9ff; border-radius: 999px; padding: 4px 10px;
        }
        .refresh-btn {
            background: #e74c3c; color: #fff; border: none; border-radius: 12px;
            padding: 10px 18px; font-weight: 700; font-size: 14px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .refresh-btn:disabled { opacity: .6; cursor: not-allowed; }

        .stats-row { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .stat-chip {
            background: #fff; border: 1px solid #ecf0f1; border-radius: 14px;
            padding: 10px 16px; font-size: 13px; color: #5d6d7e; font-weight: 600;
        }
        .stat-chip b { color: #2c3e50; font-size: 16px; margin-left: 6px; }

        .summary-card {
            background: linear-gradient(135deg, #1e2a4a, #2c3e6f);
            color: #fff; border-radius: 18px; padding: 18px 20px; margin-bottom: 22px;
        }
        .summary-card .summary-label { font-size: 11px; font-weight: 800; letter-spacing: .08em; opacity: .75; text-transform: uppercase; margin-bottom: 6px; }
        .summary-card p { font-size: 14.5px; line-height: 1.55; }
        .summary-card .summary-note { font-size: 12px; opacity: .7; margin-top: 8px; }

        .state-box { text-align: center; padding: 54px 20px; color: #7f8c8d; }
        .state-box .state-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin-top: 14px; }
        .state-box .state-text { font-size: 13.5px; margin-top: 6px; }
        /* Size for the SmartCart loading animation. Without a width/height the
           cart has no box to draw in and the loader is invisible. */
        .loading-spinner { width: 44px; height: 44px; margin: 0 auto; }
        .retry-btn {
            margin-top: 16px; background: #fff; border: 1px solid #dfe4ea; color: #2c3e50;
            border-radius: 12px; padding: 10px 20px; font-weight: 700; cursor: pointer;
        }

        .alert-card {
            background: #fff; border: 1px solid #ecf0f1; border-radius: 18px;
            padding: 18px 20px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .alert-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .alert-title { font-size: 15.5px; font-weight: 800; color: #2c3e50; }
        .severity {
            font-size: 11px; font-weight: 800; border-radius: 999px; padding: 4px 10px; color: #fff;
        }
        .severity.high { background: #e74c3c; }
        .severity.medium { background: #f39c12; }
        .severity.low { background: #3498db; }
        .alert-detail { font-size: 14px; color: #44546a; line-height: 1.55; margin-top: 8px; }
        .evidence { margin-top: 10px; display: flex; flex-direction: column; gap: 4px; }
        .evidence-label { font-size: 11px; font-weight: 800; color: #95a5a6; text-transform: uppercase; letter-spacing: .05em; }
        .evidence-row { font-size: 12.5px; color: #5d6d7e; background: #f8f9fa; border-radius: 8px; padding: 6px 10px; }
        .verify {
            margin-top: 10px; font-size: 13px; color: #7f8c8d; line-height: 1.5;
            border-left: 3px solid #f39c12; padding-left: 10px;
        }
        .alert-actions { margin-top: 14px; display: flex; justify-content: flex-end; }
        .dismiss-btn {
            background: #eafaf1; color: #27ae60; border: 1px solid #c8f0dc;
            border-radius: 12px; padding: 9px 16px; font-weight: 700; font-size: 13.5px; cursor: pointer;
        }
        .dismiss-btn:hover { background: #d6f5e3; }

        .history-card { background: #fff; border: 1px solid #ecf0f1; border-radius: 18px; padding: 18px 20px; margin-top: 26px; }
        .history-title { font-size: 15px; font-weight: 800; color: #2c3e50; margin-bottom: 10px; }
        .history-row {
            display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap;
            font-size: 13px; color: #5d6d7e; padding: 8px 0; border-top: 1px solid #f4f6f9;
        }
        .history-row:first-of-type { border-top: none; }
        .history-subject { font-weight: 700; color: #2c3e50; }
        .history-empty { font-size: 13px; color: #95a5a6; }

        .limitations { margin-top: 22px; font-size: 12.5px; color: #95a5a6; line-height: 1.6; }
        .limitations b { color: #7f8c8d; }

        /* Team Activity Digest (capability #17) */
        .act-section { border-top: 1px solid #ecf0f1; margin-top: 34px; padding-top: 24px; }
        .act-chips { display: flex; gap: 8px; flex-wrap: wrap; margin: 12px 0 16px; }
        .act-chip {
            font-size: 12px; font-weight: 700; color: #5d6d7e; background: #fff;
            border: 1px solid #ecf0f1; border-radius: 999px; padding: 6px 13px; cursor: pointer;
        }
        .act-chip.active { background: #f3eeff; border-color: #e3d9ff; color: #7c5cbf; }
        .act-panel {
            background: #fff; border: 1px solid #ecf0f1; border-radius: 18px;
            padding: 16px 18px; margin-bottom: 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);
        }
        .act-panel-title { font-size: 13px; font-weight: 800; color: #2c3e50; margin-bottom: 10px; }
        .act-bars { display: flex; align-items: flex-end; gap: 4px; height: 78px; }
        .act-bar-col { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 4px; min-width: 0; }
        .act-bar { width: 100%; max-width: 26px; background: #7c5cbf; border-radius: 5px 5px 0 0; }
        .act-bar-day { font-size: 9.5px; color: #95a5a6; }
        .act-list-row { display: flex; justify-content: space-between; gap: 10px; font-size: 13px; color: #5d6d7e; padding: 6px 0; border-top: 1px solid #f4f6f9; }
        .act-list-row:first-child { border-top: none; }
        .act-list-name { font-weight: 700; color: #2c3e50; }
        .act-list-value { font-weight: 700; color: #2c3e50; }
        .act-event { border-top: 1px solid #f4f6f9; padding: 9px 0; }
        .act-event:first-child { border-top: none; }
        .act-event-top { display: flex; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .act-event-action { font-size: 13px; font-weight: 700; color: #2c3e50; }
        .act-event-meta { font-size: 11.5px; color: #95a5a6; }
        .act-tag { font-size: 10.5px; font-weight: 800; border-radius: 999px; padding: 3px 9px; background: #fdeaea; color: #e74c3c; }
        .act-refs { font-size: 11.5px; color: #7f8c8d; margin-top: 4px; }
        .act-silent { background: #fff8e1; border: 1px solid #ffe9a8; border-radius: 12px; padding: 10px 13px; font-size: 12.5px; color: #8d6e00; margin-top: 10px; }
        .act-compare { font-size: 12.5px; font-weight: 700; margin-bottom: 10px; }

        .mobile-menu-toggle {
            display: none; position: fixed; top: 14px; left: 14px; z-index: 120;
            background: #fff; border: 1px solid #ecf0f1; border-radius: 10px;
            width: 42px; height: 42px; font-size: 18px; cursor: pointer;
            align-items: center; justify-content: center; box-shadow: 0 2px 8px rgba(0,0,0,.08);
        }

        @media (max-width: 900px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 70px 16px 50px; }
            .mobile-menu-toggle { display: flex; }
        }
    </style>
</head>
@endverbatim
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">☰</div>
    <div class="msimamizi-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header"><div class="logo-area"><div class="logo-icon">D</div><div class="logo-text"><h2 data-i18n="msimamizi_nyingine.brand_name">DukaMkononi</h2><p data-i18n="msimamizi_nyingine.portal_name">Msimamizi Portal</p></div></div></div>
            <div class="nav-items">
                <a href="index" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-house"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_home">Nyumbani</span></a>
                <a href="ripoti" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-simple"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_reports">Ripoti</span></a>
                <a href="preview" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-calendar-days"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_review">Rejea</span></a>
                <a href="bidhaa-mpya" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-circle-plus"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_new_product">Bidhaa Mpya</span></a>
                <a href="tangaza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-bullhorn"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_advertise">Tangaza</span></a>
                <a href="nyingine" class="nav-item active"><div class="nav-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div><span class="nav-label" data-i18n="msimamizi_nyingine.nav_label">Nyingine</span></a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName" data-i18n="msimamizi_nyingine.user_admin">Msimamizi</div><div class="user-role" data-i18n="msimamizi_nyingine.user_admin">Msimamizi</div></div></div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="msimamizi_nyingine.logout">Ondoka</span></div>
            </div>
        </aside>
        <main class="main-content" id="mainContent">
            <div class="page-head">
                <div>
                    <div class="page-title"><span data-i18n="msimamizi_nyingine.title">Nyingine</span> <span class="ai-chip"><i class="fa-solid fa-wand-magic-sparkles"></i> <span data-i18n="msimamizi_nyingine.ai_badge">AI</span></span></div>
                    <div class="page-sub" data-i18n="msimamizi_nyingine.subtitle">Dalili za uchunguzi za biashara yako</div>
                </div>
                <button class="refresh-btn" id="refreshBtn"><i class="fa-solid fa-rotate-right"></i> <span data-i18n="msimamizi_nyingine.refresh">Sasisha</span></button>
            </div>

            <div class="stats-row" id="statsRow" style="display:none;">
                <div class="stat-chip"><span data-i18n="msimamizi_nyingine.stat_detected">Zilizopimwa</span><b id="statDetected">0</b></div>
                <div class="stat-chip"><span data-i18n="msimamizi_nyingine.stat_open">Zilizobaki</span><b id="statOpen">0</b></div>
                <div class="stat-chip"><span data-i18n="msimamizi_nyingine.stat_dismissed">Zilizokaguliwa</span><b id="statDismissed">0</b></div>
            </div>

            <div class="summary-card" id="summaryCard" style="display:none;">
                <div class="summary-label"><span data-i18n="msimamizi_nyingine.section_summary">Muhtasari wa uchunguzi</span></div>
                <p id="summaryText"></p>
                <div class="summary-note" id="summaryNote" style="display:none;"></div>
            </div>

            <div id="stateBox" class="state-box">
                <div class="loading-spinner dm-sc"></div>
                <div class="state-text" data-i18n="msimamizi_nyingine.loading">Inapakua dalili...</div>
            </div>

            <div id="alertsList"></div>

            <div class="history-card" id="historyCard" style="display:none;">
                <div class="history-title" data-i18n="msimamizi_nyingine.history_title">Kumbukumbu za ukaguzi</div>
                <div id="historyList"></div>
            </div>

            <div class="limitations" id="limitations" style="display:none;"></div>

            <section class="act-section" id="activitySection">
                <div class="page-title" style="font-size:19px;">
                    <i class="fa-solid fa-users"></i>
                    <span data-i18n="msimamizi_nyingine.act_title">Shughuli za Timu</span>
                    <span class="ai-chip"><i class="fa-solid fa-wand-magic-sparkles"></i> <span data-i18n="msimamizi_nyingine.ai_badge">AI</span></span>
                </div>
                <div class="page-sub" data-i18n="msimamizi_nyingine.act_subtitle">Matukio yaliyorekodiwa na wanachama wa biashara yako</div>
                <div class="act-chips" id="actChips"></div>
                <div id="actBody"></div>
            </section>
        </main>
    </div>

    <script>
        const API_BASE_URL = '';

        const SW = {
            page_title: "Nyingine - Dukamkononi Msimamizi",
            brand_name: "DukaMkononi",
            portal_name: "Msimamizi Portal",
            nav_home: "Nyumbani",
            nav_reports: "Ripoti",
            nav_review: "Rejea",
            nav_new_product: "Bidhaa Mpya",
            nav_advertise: "Tangaza",
            nav_label: "Nyingine",
            user_admin: "Msimamizi",
            logout: "Ondoka",
            loading: "Inapakua dalili...",
            title: "Nyingine",
            subtitle: "Dalili za uchunguzi za biashara yako",
            ai_badge: "AI",
            refresh: "Sasisha",
            section_summary: "Muhtasari wa uchunguzi",
            stat_detected: "Zilizopimwa",
            stat_open: "Zilizobaki",
            stat_dismissed: "Zilizokaguliwa",
            empty_title: "Hakuna dalili",
            empty_text: "Hakuna dalili za uchunguzi kwa kipindi hiki. Endelea kufuatilia kila wiki.",
            error_title: "Hitilafu",
            error_text: "Imeshindwa kupata dalili za uchunguzi.",
            retry: "Jaribu tena",
            sign_in_again: "Tafadhali ingia tena",
            severity_high: "Juu",
            severity_medium: "Kati",
            severity_low: "Chini",
            evidence: "Ushahidi",
            verify: "Thibitisha",
            dismiss_btn: "Nimeukagua",
            dismiss_confirm: "Umeukagua dalili hii? Itaondolewa kwenye orodha ya kufuatilia.",
            history_title: "Kumbukumbu za ukaguzi",
            history_empty: "Bado hakuna ukaguzi uliorekodiwa.",
            reason_label: "Sababu",
            no_reason: "Hakuna sababu",
            generated: "Imetolewa {date}",
            limitations_title: "Maelezo:",
            ai_note: "Muhtasari huu umeandikwa na AI kulingana na dalili zilizopimwa.",
            act_title: "Shughuli za Timu",
            act_subtitle: "Matukio yaliyorekodiwa na wanachama wa biashara yako",
            act_period_today: "Leo",
            act_period_yesterday: "Jana",
            act_period_this_week: "Wiki hii",
            act_period_last_week: "Wiki iliyopita",
            act_period_last_7_days: "Siku 7 zilizopita",
            act_loading: "Inatengeneza muhtasari wa shughuli...",
            act_error: "Imeshindwa kupata muhtasari wa shughuli.",
            act_empty_title: "Hakuna matukio",
            act_empty_text: "Hakuna matukio yaliyorekodiwa kwa kipindi hiki.",
            act_stat_events: "Matukio",
            act_stat_actors: "Wanachama hai",
            act_stat_failed: "Yaliyoshindikana",
            act_stat_members: "Wanachama wote",
            act_categories: "Kwa aina ya shughuli",
            act_members: "Shughuli kwa mwanachama",
            act_noteworthy: "Matukio muhimu",
            act_noteworthy_none: "Hakuna matukio muhimu kwa kipindi hiki.",
            act_days: "Kwa siku",
            act_silent: "Hawana matukio yaliyorekodiwa",
            act_silent_none: "Kila mwanachama ana matukio yaliyorekodiwa.",
            act_reword_note: "Maandishi yaliyoandikwa upya na AI kutoka matukio yale yale yaliyorekodiwa.",
            act_prev: "Kipindi kilichopita",
            act_dir_up: "Zaidi ya kipindi kilichopita",
            act_dir_down: "Pungufu kuliko kipindi kilichopita",
            act_dir_flat: "Sawa na kipindi kilichopita",
            act_dir_from_zero: "Kipindi kilichopita hakikuwa na matukio",
            act_failed_tag: "Imeshindwa",
            act_refs: "Kumbukumbu",
            act_sources: "Vyanzo",
        };

        function t(key, params) {
            const full = key.indexOf('msimamizi_nyingine.') === 0 ? key : 'msimamizi_nyingine.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            const bare = key.indexOf('.') > -1 ? key.split('.').pop() : key;
            let value = Object.prototype.hasOwnProperty.call(SW, bare) ? SW[bare] : key;
            if (params) {
                Object.keys(params).forEach(p => {
                    value = value.split('{' + p + '}').join(params[p] == null ? '' : params[p]);
                });
            }
            return value;
        }

        function escapeHtml(value) {
            return String(value == null ? '' : value)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function currentLocale() {
            if (window.DM && typeof window.DM.locale === 'function') return window.DM.locale();
            return 'sw';
        }

        let userToken = null;
        let loading = false;
        let lastData = null;

        function showState(kind) {
            const box = document.getElementById('stateBox');
            if (kind === 'loading') {
                box.style.display = 'block';
                box.innerHTML = '<div class="loading-spinner dm-sc"></div><div class="state-text">' + escapeHtml(t('loading')) + '</div>';
            } else if (kind === 'empty') {
                box.style.display = 'block';
                box.innerHTML = '<i class="fa-solid fa-shield-halved" style="font-size:34px;color:#27ae60;"></i>' +
                    '<div class="state-title">' + escapeHtml(t('empty_title')) + '</div>' +
                    '<div class="state-text">' + escapeHtml(t('empty_text')) + '</div>';
            } else if (kind === 'error') {
                box.style.display = 'block';
                box.innerHTML = '<i class="fa-solid fa-triangle-exclamation" style="font-size:34px;color:#e74c3c;"></i>' +
                    '<div class="state-title">' + escapeHtml(t('error_title')) + '</div>' +
                    '<div class="state-text">' + escapeHtml(t('error_text')) + '</div>' +
                    '<button class="retry-btn" onclick="window.loadAnomalies()">' + escapeHtml(t('retry')) + '</button>';
            } else {
                box.style.display = 'none';
            }
        }

        function renderAlerts(data) {
            const list = document.getElementById('alertsList');
            const alerts = (data && data.alerts) || [];

            document.getElementById('statsRow').style.display = 'flex';
            document.getElementById('statDetected').textContent = data.detected_count || 0;
            document.getElementById('statOpen').textContent = data.open_count || 0;
            document.getElementById('statDismissed').textContent = data.dismissed_count || 0;

            const summaryCard = document.getElementById('summaryCard');
            const summaryText = document.getElementById('summaryText');
            const summaryNote = document.getElementById('summaryNote');
            if (data.summary) {
                summaryCard.style.display = 'block';
                summaryText.textContent = data.summary;
                const degraded = data.ai && data.ai.degraded;
                summaryNote.style.display = degraded ? 'block' : 'none';
                summaryNote.textContent = degraded ? (data.ai.reason || t('ai_note')) : '';
            } else {
                summaryCard.style.display = 'none';
            }

            const limitations = document.getElementById('limitations');
            if (data.limitations && data.limitations.length) {
                limitations.style.display = 'block';
                limitations.innerHTML = '<b>' + escapeHtml(t('limitations_title')) + '</b> ' +
                    data.limitations.map(escapeHtml).join('<br>');
            } else {
                limitations.style.display = 'none';
            }

            if (!alerts.length) {
                list.innerHTML = '';
                showState('empty');
            } else {
                showState('none');
                list.innerHTML = alerts.map(function (alert) {
                    const severityLabel = t('severity_' + alert.severity);
                    const evidence = (alert.evidence || []).map(function (ev) {
                        const bits = [];
                        if (ev.ref) bits.push(escapeHtml(ev.ref));
                        if (ev.date) bits.push(escapeHtml(ev.date));
                        if (ev.value !== undefined && ev.value !== null) bits.push(escapeHtml(String(ev.value)));
                        return '<div class="evidence-row">' + bits.join(' · ') + '</div>';
                    }).join('');
                    const evidenceBlock = evidence
                        ? '<div class="evidence"><div class="evidence-label">' + escapeHtml(t('evidence')) + '</div>' + evidence + '</div>'
                        : '';
                    const verifyBlock = alert.verify_steps
                        ? '<div class="verify"><b>' + escapeHtml(t('verify')) + ':</b> ' + escapeHtml(alert.verify_steps) + '</div>'
                        : '';
                    return '<div class="alert-card" data-key="' + escapeHtml(alert.key) + '">' +
                        '<div class="alert-top">' +
                            '<div class="alert-title">' + escapeHtml(alert.title) + '</div>' +
                            '<span class="severity ' + escapeHtml(alert.severity) + '">' + escapeHtml(severityLabel) + '</span>' +
                        '</div>' +
                        '<div class="alert-detail">' + escapeHtml(alert.detail) + '</div>' +
                        evidenceBlock + verifyBlock +
                        '<div class="alert-actions">' +
                            '<button class="dismiss-btn" data-dismiss="' + escapeHtml(alert.key) + '">' +
                                '<i class="fa-solid fa-check"></i> ' + escapeHtml(t('dismiss_btn')) +
                            '</button>' +
                        '</div>' +
                    '</div>';
                }).join('');

                Array.prototype.forEach.call(list.querySelectorAll('[data-dismiss]'), function (btn) {
                    btn.addEventListener('click', function () { dismissAlert(btn.getAttribute('data-dismiss')); });
                });
            }

            const historyCard = document.getElementById('historyCard');
            const historyList = document.getElementById('historyList');
            const history = (data && data.history) || [];
            if (history.length) {
                historyCard.style.display = 'block';
                historyList.innerHTML = history.map(function (row) {
                    return '<div class="history-row">' +
                        '<span class="history-subject">' + escapeHtml(row.subject || row.key || '') + '</span>' +
                        '<span>' + escapeHtml(row.reason || t('no_reason')) + '</span>' +
                        '<span>' + escapeHtml(String(row.at || '').slice(0, 16).replace('T', ' ')) + '</span>' +
                    '</div>';
                }).join('');
            } else {
                historyCard.style.display = 'none';
            }

            const generated = document.getElementById('limitations');
            if (data.generated_at) {
                generated.innerHTML += '<div style="margin-top:8px;">' + escapeHtml(t('generated', { date: String(data.generated_at).slice(0, 16).replace('T', ' ') })) + '</div>';
            }
        }

        async function loadAnomalies() {
            if (!userToken) return;
            loading = true;
            document.getElementById('refreshBtn').disabled = true;
            showState('loading');
            try {
                const res = await fetch(API_BASE_URL + '/api/ai/anomalies?locale=' + encodeURIComponent(currentLocale()), {
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + userToken }
                });
                if (res.status === 401) { window.location.replace('../login?role=msimamizi'); return; }
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok) { showState('error'); return; }
                lastData = data;
                renderAlerts(data);
            } catch (e) {
                showState('error');
            } finally {
                loading = false;
                document.getElementById('refreshBtn').disabled = false;
            }
        }

        async function dismissAlert(key) {
            if (!userToken || !key) return;
            if (!window.confirm(t('dismiss_confirm'))) return;
            try {
                const res = await fetch(API_BASE_URL + '/api/ai/anomalies/dismiss', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + userToken },
                    body: JSON.stringify({ key: key, locale: currentLocale() })
                });
                if (res.status === 401) { window.location.replace('../login?role=msimamizi'); return; }
                if (res.ok) await loadAnomalies();
            } catch (e) { /* transient network failure: keep the card and let them retry */ }
        }

        /* ================= TEAM ACTIVITY DIGEST (capability #17) =================
         * GET /api/ai/activity/digest reads this business's OWN activity log
         * rows (`user_logs`) for the requested daily/weekly window. Every count,
         * member and timestamp is computed server-side; the model may only REWORD
         * the same figures, so the prose can never disagree with the numbers.
         * The digest never judges staff - the counts are recorded events only.
         */
        const ACT_PERIODS = ['today', 'yesterday', 'this_week', 'last_week', 'last_7_days'];
        let actPeriod = 'this_week';
        let actData = null;
        let actError = false;
        let actLoading = false;

        function actShortTime(value) { return String(value || '').slice(0, 16).replace('T', ' '); }

        function actDirColor(direction) {
            return direction === 'up' ? '#27ae60' : direction === 'down' ? '#e67e22' : '#7c5cbf';
        }

        function actDirLabel(direction) {
            const key = ['up', 'down', 'from_zero'].indexOf(direction) > -1 ? direction : 'flat';
            return t('act_dir_' + key);
        }

        window.setActPeriod = function (period) {
            if (ACT_PERIODS.indexOf(period) === -1 || actPeriod === period) return;
            actPeriod = period;
            window.loadActivity();
        };

        window.loadActivity = async function () {
            if (!userToken || actLoading) return;
            actLoading = true;
            actError = false;
            renderActivity();
            try {
                const url = API_BASE_URL + '/api/ai/activity/digest?period=' + encodeURIComponent(actPeriod) +
                    '&locale=' + encodeURIComponent(currentLocale());
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + userToken } });
                if (res.status === 401) { window.location.replace('../login?role=msimamizi'); return; }
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok || !data || !data.success) { actData = null; actError = true; }
                else { actData = data; actError = false; }
            } catch (e) {
                actData = null;
                actError = true;
            } finally {
                actLoading = false;
                renderActivity();
            }
        };

        function actChipsHtml() {
            return ACT_PERIODS.map(function (p) {
                return '<div class="act-chip' + (actPeriod === p ? ' active' : '') + '" data-period="' + p + '">' +
                    escapeHtml(t('act_period_' + p)) + '</div>';
            }).join('');
        }

        function actBarsHtml(series, emptyText) {
            if (!series || !series.length) return '<div class="history-empty">' + escapeHtml(emptyText) + '</div>';
            const counts = series.map(function (d) { return Number(d.count) || 0; });
            const max = Math.max.apply(null, counts) || 1;
            return '<div class="act-bars">' + series.map(function (d) {
                const count = Number(d.count) || 0;
                const height = Math.max(3, Math.round((count / max) * 58));
                return '<div class="act-bar-col" title="' + escapeHtml(String(d.date || '') + ' - ' + count) + '">' +
                    '<div class="act-bar" style="height:' + height + 'px;' + (count ? '' : 'background:#dfe4ea;') + '"></div>' +
                    '<div class="act-bar-day">' + escapeHtml(String(d.date || '').slice(5)) + '</div>' +
                '</div>';
            }).join('') + '</div>';
        }

        function renderActivity() {
            const chips = document.getElementById('actChips');
            const body = document.getElementById('actBody');
            if (!chips || !body) return;

            chips.innerHTML = actChipsHtml();
            Array.prototype.forEach.call(chips.querySelectorAll('[data-period]'), function (chip) {
                chip.addEventListener('click', function () { window.setActPeriod(chip.getAttribute('data-period')); });
            });

            if (actLoading) {
                body.innerHTML = '<div class="state-box"><div class="loading-spinner dm-sc"></div><div class="state-text">' +
                    escapeHtml(t('act_loading')) + '</div></div>';
                return;
            }

            if (actError || !actData) {
                body.innerHTML = '<div class="state-box"><i class="fa-solid fa-triangle-exclamation" style="font-size:32px;color:#e74c3c;"></i>' +
                    '<div class="state-title">' + escapeHtml(t('error_title')) + '</div>' +
                    '<div class="state-text">' + escapeHtml(t('act_error')) + '</div>' +
                    '<button class="retry-btn" onclick="window.loadActivity()">' + escapeHtml(t('retry')) + '</button></div>';
                return;
            }

            const d = actData;
            const totals = d.totals || {};
            const comparison = d.comparison || {};
            const events = Number(totals.events) || 0;

            let html = '';

            /* Counts - recorded events, never a performance score. */
            html += '<div class="stats-row">' +
                '<div class="stat-chip"><span>' + escapeHtml(t('act_stat_events')) + '</span><b>' + events + '</b></div>' +
                '<div class="stat-chip"><span>' + escapeHtml(t('act_stat_actors')) + '</span><b>' + (Number(totals.actors) || 0) + '</b></div>' +
                '<div class="stat-chip"><span>' + escapeHtml(t('act_stat_failed')) + '</span><b>' + (Number(totals.failed) || 0) + '</b></div>' +
                '<div class="stat-chip"><span>' + escapeHtml(t('act_stat_members')) + '</span><b>' + (Number(totals.members) || 0) + '</b></div>' +
            '</div>';

            /* The paragraph: the model's reword when it passed the number gate,
               otherwise the deterministic lines. Either way: the same figures. */
            const prose = (d.ai && !d.ai.degraded && d.digest_text) ? d.digest_text : (d.narrative || []).join(' ');
            html += '<div class="summary-card"><div class="summary-label">' + escapeHtml(t('act_title')) + '</div>' +
                '<p>' + escapeHtml(prose) + '</p>' +
                '<div class="act-compare" style="color:' + actDirColor(comparison.direction) + ';margin-top:8px;">' +
                    escapeHtml(actDirLabel(comparison.direction)) +
                    ' (' + escapeHtml(t('act_prev')) + ': ' + (Number(comparison.previous_events) || 0) + ')' +
                '</div>' +
                '<div class="summary-note">' + escapeHtml(t('act_reword_note')) + '</div>' +
            '</div>';

            if (events === 0) {
                html += '<div class="act-panel"><div class="state-box" style="padding:26px 12px;">' +
                    '<i class="fa-solid fa-clock" style="font-size:30px;color:#95a5a6;"></i>' +
                    '<div class="state-title">' + escapeHtml(t('act_empty_title')) + '</div>' +
                    '<div class="state-text">' + escapeHtml(t('act_empty_text')) + '</div></div></div>';
            }

            html += '<div class="act-panel"><div class="act-panel-title">' + escapeHtml(t('act_days')) + '</div>' +
                actBarsHtml(d.day_series || [], t('act_empty_text')) + '</div>';

            const categories = d.categories || [];
            if (categories.length) {
                html += '<div class="act-panel"><div class="act-panel-title">' + escapeHtml(t('act_categories')) + '</div>' +
                    categories.map(function (row) {
                        const failed = Number(row.failed) || 0;
                        return '<div class="act-list-row"><span class="act-list-name">' + escapeHtml(row.label) + '</span>' +
                            '<span class="act-list-value">' + (Number(row.count) || 0) +
                            (failed ? ' / ' + failed + ' ' + escapeHtml(t('act_failed_tag')) : '') + '</span></div>';
                    }).join('') + '</div>';
            }

            const members = (d.members || []).filter(function (m) { return Number(m.count) > 0; });
            if (members.length) {
                html += '<div class="act-panel"><div class="act-panel-title">' + escapeHtml(t('act_members')) + '</div>' +
                    members.map(function (m) {
                        return '<div class="act-list-row"><span class="act-list-name">' + escapeHtml(m.name) + '</span>' +
                            '<span class="act-list-value">' + (Number(m.count) || 0) +
                            (m.last_at ? ' <span class="act-event-meta">' + escapeHtml(actShortTime(m.last_at)) + '</span>' : '') + '</span></div>';
                    }).join('') + '</div>';
            }

            /* Missing logging coverage stays visible. */
            const silent = d.silent_members || [];
            html += '<div class="act-silent"><b>' + escapeHtml(t('act_silent')) + ':</b> ' +
                (silent.length ? silent.map(escapeHtml).join(', ') : escapeHtml(t('act_silent_none'))) + '</div>';

            const noteworthy = d.noteworthy || [];
            html += '<div class="act-panel" style="margin-top:14px;"><div class="act-panel-title">' + escapeHtml(t('act_noteworthy')) + '</div>' +
                (noteworthy.length ? noteworthy.map(function (ev) {
                    const failed = String(ev.status || '') !== 'success';
                    const refs = (ev.refs || []).map(function (r) {
                        return escapeHtml(String(r.field)) + ': ' + escapeHtml(String(r.value));
                    }).join(' | ');
                    return '<div class="act-event"><div class="act-event-top">' +
                        '<span class="act-event-action">' + escapeHtml(ev.action) + '</span>' +
                        (failed ? '<span class="act-tag">' + escapeHtml(t('act_failed_tag')) + '</span>' : '<span class="act-tag" style="background:#f3eeff;color:#7c5cbf;">' + escapeHtml(ev.category_label || '') + '</span>') +
                    '</div>' +
                    '<div class="act-event-meta">' + escapeHtml(ev.actor_name || '') + ' - ' + escapeHtml(actShortTime(ev.at)) + '</div>' +
                    (refs ? '<div class="act-refs"><b>' + escapeHtml(t('act_refs')) + ':</b> ' + refs + '</div>' : '') +
                    '</div>';
                }).join('') : '<div class="history-empty">' + escapeHtml(t('act_noteworthy_none')) + '</div>') + '</div>';

            const sources = d.sources || [];
            const limitations = d.limitations || [];
            html += '<div class="limitations"><b>' + escapeHtml(t('limitations_title')) + '</b> ' +
                limitations.map(escapeHtml).join('<br>') +
                (sources.length ? '<div style="margin-top:6px;"><b>' + escapeHtml(t('act_sources')) + ':</b> ' + sources.map(escapeHtml).join(' ') + '</div>' : '') +
                (d.generated_at ? '<div style="margin-top:6px;">' + escapeHtml(t('generated', { date: actShortTime(d.generated_at) })) + '</div>' : '') +
            '</div>';

            body.innerHTML = html;
        }

        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) logoutBtn.addEventListener('click', function () {
                localStorage.removeItem('userToken');
                localStorage.removeItem('userData');
                window.location.href = '../login?role=msimamizi';
            });
        }

        async function init() {
            setupSidebar();
            userToken = localStorage.getItem('userToken');
            const userStr = localStorage.getItem('userData');
            if (!userToken || !userStr) { window.location.replace('../login?role=msimamizi'); return; }
            try {
                const user = JSON.parse(userStr);
                document.getElementById('userName').textContent = user.full_name || user.email?.split('@')[0] || t('user_admin');
                const initial = (user.full_name || user.email || 'M').charAt(0).toUpperCase();
                document.getElementById('userAvatar').textContent = initial;
            } catch (e) { /* keep the static fallback */ }
            document.getElementById('refreshBtn').addEventListener('click', function () { loadAnomalies(); });
            await loadAnomalies();
            await window.loadActivity();
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { if (!loading && lastData) loadAnomalies(); });
            window.DM.onChange(function () { renderActivity(); if (!actLoading && actData) window.loadActivity(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim
