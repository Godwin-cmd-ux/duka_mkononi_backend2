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
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { if (!loading && lastData) loadAnomalies(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim
