@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_afya.page_title">Afya ya Biashara - Dukamkononi Muuzaji</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        .muuzaji-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background-color: #ffffff; border-right: 1px solid #ecf0f1;
            display: flex; flex-direction: column; position: fixed; left: 0; top: 0; bottom: 0;
            z-index: 100; transition: transform 0.3s ease; box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; margin-bottom: 20px; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #2ecc71, #27ae60);
            border-radius: 12px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 24px; font-weight: bold;
        }
        .logo-text h2 { font-size: 18px; font-weight: 800; color: #2c3e50; }
        .logo-text p { font-size: 12px; color: #7f8c8d; }
        .nav-items { flex: 1; padding: 0 16px; overflow-y: auto; }
        .nav-item {
            display: flex; align-items: center; gap: 14px; padding: 12px 16px; margin-bottom: 6px;
            border-radius: 14px; cursor: pointer; transition: all 0.2s ease; color: #5d6d7e;
            font-weight: 500; text-decoration: none;
        }
        .nav-item:hover { background-color: #e8f8f0; }
        .nav-item.active { background-color: #e8f8f0; color: #2ecc71; }
        .nav-icon { font-size: 20px; width: 26px; }
        .nav-label { font-size: 15px; font-weight: 600; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 45px; height: 45px; background-color: #e8f8f0; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: #2ecc71; font-weight: bold; font-size: 18px;
        }
        .user-details { flex: 1; }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #2ecc71; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 10px; padding: 12px 16px; background-color: #f8f9fa;
            border-radius: 12px; cursor: pointer; color: #e74c3c; font-weight: 600; font-size: 14px;
        }
        .logout-btn:hover { background-color: #fdeaea; }

        .main-content { flex: 1; margin-left: 280px; min-height: 100vh; background-color: #f8f9fa; padding: 20px 24px 40px; }
        .mobile-menu-toggle {
            display: none; position: fixed; top: 16px; left: 16px; z-index: 200; background: white;
            padding: 12px; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); cursor: pointer;
        }
        .page-container { max-width: 900px; margin: 0 auto; width: 100%; }

        .header { background: white; padding: 16px 20px; border-radius: 16px; margin-bottom: 16px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
        .title { font-size: 20px; font-weight: 700; color: #2c3e50; }
        .subtitle { font-size: 13px; color: #95a5a6; margin-top: 4px; }
        .refresh-btn {
            background: #2ecc71; color: white; border: none; padding: 10px 18px; border-radius: 12px;
            font-weight: 700; cursor: pointer; font-size: 14px; white-space: nowrap;
        }
        .refresh-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .card { background: white; border-radius: 18px; padding: 20px; margin-bottom: 16px; }
        .card-title { font-size: 15px; font-weight: 700; color: #2c3e50; margin-bottom: 12px; }

        .score-card { display: flex; align-items: center; gap: 22px; }
        .score-ring {
            width: 110px; height: 110px; border-radius: 50%; display: flex; flex-direction: column;
            align-items: center; justify-content: center; color: white; flex-shrink: 0;
        }
        .score-value { font-size: 34px; font-weight: 800; line-height: 1; }
        .score-max { font-size: 13px; opacity: 0.9; }
        .score-meta { flex: 1; }
        .band-label { font-size: 20px; font-weight: 800; color: #2c3e50; margin-bottom: 4px; }
        .score-summary { font-size: 14px; color: #5d6d7e; line-height: 1.55; }
        .period-text { font-size: 12px; color: #95a5a6; margin-top: 8px; }

        .component { border-bottom: 1px solid #f4f6f7; padding: 12px 0; }
        .component:last-child { border-bottom: none; }
        .component-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; gap: 10px; }
        .component-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .component-status { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; }
        .status-strong { background: #e8f8f0; color: #1e8449; }
        .status-steady { background: #eef6fb; color: #2874a6; }
        .status-watch { background: #fef5e7; color: #b9770e; }
        .status-attention { background: #fdeaea; color: #c0392b; }
        .component-track { height: 8px; background: #ecf0f1; border-radius: 4px; overflow: hidden; margin-bottom: 8px; }
        .component-fill { height: 8px; border-radius: 4px; }
        .component-evidence { font-size: 12.5px; color: #7f8c8d; line-height: 1.5; }

        .change-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid #f4f6f7; }
        .change-row:last-child { border-bottom: none; }
        .change-icon { width: 28px; text-align: center; font-size: 16px; }
        .change-text { flex: 1; font-size: 14px; color: #5d6d7e; }

        .action { display: flex; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f4f6f7; }
        .action:last-child { border-bottom: none; }
        .action-num {
            width: 28px; height: 28px; border-radius: 50%; background: #e8f8f0; color: #1e8449;
            display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 13px; flex-shrink: 0;
        }
        .action-title { font-size: 14px; font-weight: 700; color: #2c3e50; margin-bottom: 3px; }
        .action-body { font-size: 13px; color: #5d6d7e; line-height: 1.55; }

        .ai-card { background: #e8f8f0; border-radius: 16px; padding: 16px; margin-bottom: 16px; }
        .ai-card-title { font-size: 13px; font-weight: 700; color: #1e8449; margin-bottom: 6px; }
        .ai-card-text { font-size: 14px; color: #1e8449; line-height: 1.55; }

        .history-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #f4f6f7; gap: 12px; }
        .history-row:last-child { border-bottom: none; }
        .history-period { font-size: 13px; color: #5d6d7e; }
        .history-score { font-size: 13px; font-weight: 700; color: #2c3e50; }
        .history-badge { font-size: 11px; font-weight: 700; padding: 3px 10px; border-radius: 20px; background: #f4f6f7; color: #5d6d7e; }

        .list-row { display: flex; gap: 8px; margin-bottom: 6px; }
        .list-bullet { font-weight: 700; color: #95a5a6; }
        .list-text { flex: 1; font-size: 13px; color: #5d6d7e; line-height: 1.55; }

        .loading-container { text-align: center; padding: 50px 20px; }
        .loading-spinner {
            width: 50px; height: 50px; border: 3px solid #e0e0e0; border-top-color: #2ecc71;
            border-radius: 50%; animation: spin 0.8s linear infinite; margin: 0 auto 16px;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: #7f8c8d; font-size: 14px; }
        .empty-state { text-align: center; padding: 50px 20px; background: white; border-radius: 20px; }
        .empty-icon { font-size: 46px; color: #bdc3c7; margin-bottom: 14px; }
        .empty-title { font-size: 18px; font-weight: 700; color: #2c3e50; margin-bottom: 8px; }
        .empty-text { font-size: 14px; color: #7f8c8d; line-height: 1.5; }
        .notice { background: #fef5e7; color: #b9770e; border-radius: 14px; padding: 12px 16px; font-size: 13px; margin-bottom: 16px; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .score-card { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.toast')
@include('partials.photo-viewer')
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#2ecc71" stroke-width="2">
            <path d="M3 12H21M3 6H21M3 18H21"/>
        </svg>
    </div>

    <div class="muuzaji-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="muuzaji_matumizi.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="muuzaji_matumizi.logo_brand">DukaMkononi</h2>
                        <p data-i18n="muuzaji_matumizi.logo_tagline">Muuzaji Portal</p>
                    </div>
                </div>
            </div>

            <div class="nav-items">
                <a href="profaili" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-user" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_profile">Profaili</span></a>
                <a href="mauzo" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-money-bill-1" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_sales">Mauzo</span></a>
                <a href="matumizi" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-chart-line" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_expenses">Matumizi</span></a>
                <a href="uza" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-cart-shopping" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_matumizi.nav_sell">Uza</span></a>
                <a href="orders" class="nav-item"><div class="nav-icon"><i class="fa-solid fa-box" aria-hidden="true"></i></div><span class="nav-label" data-i18n="muuzaji_orders.nav_orders">Oda</span></a>
                <a href="huduma-nyingine" class="nav-item active"><div class="nav-icon">🧩</div><span class="nav-label" data-i18n="muuzaji_huduma.nav_other">Huduma Nyingine</span></a>
            </div>

            <div class="sidebar-footer">
                <div class="user-info">
                    <div class="user-avatar" id="userAvatar">M</div>
                    <div class="user-details">
                        <div class="user-name" id="userName">Muuzaji</div>
                        <div class="user-role" data-i18n="muuzaji_matumizi.nav_seller">Muuzaji</div>
                    </div>
                </div>
                <div class="logout-btn" id="logoutBtn">
                    <i class="fa-solid fa-right-from-bracket" aria-hidden="true"></i>
                    <span data-i18n="muuzaji_matumizi.btn_logout">Ondoka</span>
                </div>
            </div>
        </aside>

        <main class="main-content">
            <div class="page-container" id="healthContent">
                <div class="header">
                    <div>
                        <div class="title" data-i18n="muuzaji_afya.title">Afya ya Biashara Yako</div>
                        <div class="subtitle" data-i18n="muuzaji_afya.subtitle">Muhtasari wa kila wiki wa utendaji wa duka lako</div>
                    </div>
                    <button class="refresh-btn" id="refreshBtn" data-i18n="muuzaji_afya.btn_refresh">Sasisha</button>
                </div>
                <div id="healthResults"></div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // AFYA YA BIASHARA (Business Health Score) - Muuzaji Web
        // Backed by GET/POST /api/ai/reports/weekly + GET .../history
        // ============================================

        const API_BASE_URL = '';

        const SW = {
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            err_load_failed: 'Imeshindwa kupata ripoti ya afya.',
            err_network: 'Hitilafu ya mtandao. Angalia muunganisho wako.',
            err_auth: 'Tafadhali ingia tena.',
            loading: 'Inatafuta ripoti...',
            generating: 'Inatengeneza ripoti...',
            empty_title: 'Hakuna ripoti bado',
            empty_hint: 'Bofya "Sasisha" kutengeneza muhtasari wa wiki hii.',
            score_label: 'Alama ya afya',
            period_label: 'Kipindi',
            components_title: 'Vipimo',
            changes_title: 'Mabadiliko muhimu',
            actions_title: 'Vitendo vinavyopendekezwa',
            history_title: 'Historia ya wiki',
            limitations_title: 'Maelezo ya ziada',
            ai_title: 'Maoni ya AI',
            no_history: 'Hakuna historia bado.',
            insufficient_title: 'Data haitoshi',
            store_warning: 'Ripoti haikuhifadhiwa kwenye historia kwa sasa.',
            coverage_label: 'Uwiano wa data'
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_afya.') === 0 ? key : 'muuzaji_afya.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            const bare = key.indexOf('.') > -1 ? key.split('.').pop() : key;
            let value = Object.prototype.hasOwnProperty.call(SW, bare) ? SW[bare] : key;
            if (params) {
                for (const k in params) value = String(value).split('{' + k + '}').join(String(params[k]));
            }
            return value;
        }

        let digest = null;
        let history = [];
        let loading = false;
        let generating = false;
        let loadError = null;

        const BAND_COLORS = {
            strong: '#2ecc71',
            steady: '#3498db',
            watch: '#f39c12',
            attention: '#e74c3c',
            insufficient_evidence: '#95a5a6'
        };

        function getCurrentUser() {
            const token = localStorage.getItem('userToken');
            const userData = localStorage.getItem('userData');
            if (token && userData) { try { return JSON.parse(userData); } catch (e) { return null; } }
            return null;
        }

        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str).replace(/[&<>"]/g, function (m) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
            });
        }

        function updateSidebarUser() {
            const user = getCurrentUser();
            if (!user) return;
            const displayName = user.full_name || user.business_name || (user.email ? user.email.split('@')[0] : 'Muuzaji');
            const nameEl = document.getElementById('userName');
            if (nameEl) nameEl.innerHTML = escapeHtml(displayName);
            const avatarEl = document.getElementById('userAvatar');
            if (avatarEl) {
                if (user.business_logo_url) {
                    avatarEl.innerHTML = `<img src="${escapeHtml(user.business_logo_url)}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;pointer-events:none;" alt="">`;
                } else {
                    avatarEl.innerHTML = escapeHtml(displayName.charAt(0).toUpperCase());
                }
            }
        }

        function checkAuth() {
            const user = getCurrentUser();
            if (!user) { window.location.href = '/login?role=muuzaji'; return false; }
            const role = user.role || '';
            if (role !== 'seller' && role !== 'muuzaji') {
                showToast(t('err_no_permission'), 'error');
                window.location.href = '/home';
                return false;
            }
            return true;
        }

        function currentLocale() {
            if (window.DM && typeof window.DM.locale === 'function') {
                const code = window.DM.locale();
                if (code) return code;
            }
            return 'sw';
        }

        function statusClass(status) {
            return 'status-' + (['strong', 'steady', 'watch', 'attention'].indexOf(status) > -1 ? status : 'steady');
        }

        async function load() {
            const token = localStorage.getItem('userToken');
            if (!token) { showToast(t('err_auth'), 'error'); return; }

            loading = true;
            loadError = null;
            render();

            try {
                const headers = { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' };
                const [weeklyRes, historyRes] = await Promise.all([
                    fetch(`${API_BASE_URL}/api/ai/reports/weekly`, { headers }),
                    fetch(`${API_BASE_URL}/api/ai/reports/weekly/history?limit=12`, { headers })
                ]);

                if (!weeklyRes.ok) {
                    loadError = t('err_load_failed');
                } else {
                    digest = await weeklyRes.json();
                }
                if (historyRes.ok) {
                    const payload = await historyRes.json();
                    history = (payload && payload.digests) ? payload.digests : [];
                }
            } catch (error) {
                console.error('Error loading health digest:', error);
                loadError = t('err_network');
            } finally {
                loading = false;
                render();
            }
        }

        async function refresh() {
            const token = localStorage.getItem('userToken');
            if (!token) { showToast(t('err_auth'), 'error'); return; }

            generating = true;
            loadError = null;
            render();

            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/reports/weekly`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ locale: currentLocale(), refresh: true })
                });
                if (!res.ok) {
                    loadError = t('err_load_failed');
                } else {
                    digest = await res.json();
                }
            } catch (error) {
                console.error('Error refreshing health digest:', error);
                loadError = t('err_network');
            } finally {
                generating = false;
                render();
            }
        }

        function renderScore() {
            if (!digest) return '';
            const color = BAND_COLORS[digest.band] || '#95a5a6';
            const scoreText = digest.sufficient && digest.score !== null ? escapeHtml(Math.round(digest.score)) : '–';

            let html = '<div class="card"><div class="score-card">';
            html += `<div class="score-ring" style="background:${color};"><div class="score-value">${scoreText}</div><div class="score-max">/ 100</div></div>`;
            html += '<div class="score-meta">';
            html += `<div class="band-label">${escapeHtml(digest.band_label || '')}</div>`;
            html += `<div class="score-summary">${escapeHtml(digest.summary_text || '')}</div>`;
            html += `<div class="period-text">${escapeHtml(t('period_label'))}: ${escapeHtml(digest.period ? digest.period.start : '')} – ${escapeHtml(digest.period ? digest.period.end : '')}</div>`;
            html += '</div></div></div>';
            return html;
        }

        function renderComponents() {
            if (!digest || !digest.components || !digest.components.length) return '';
            let html = `<div class="card"><div class="card-title">${escapeHtml(t('components_title'))}</div>`;
            digest.components.forEach(function (c) {
                const color = BAND_COLORS[c.status] || '#95a5a6';
                const pct = Math.max(4, Math.round((c.score / 100) * 100));
                html += '<div class="component">';
                html += `<div class="component-head"><span class="component-name">${escapeHtml(c.label)}</span><span class="component-status ${statusClass(c.status)}">${escapeHtml(c.status_label)}</span></div>`;
                html += `<div class="component-track"><div class="component-fill" style="width:${pct}%;background:${color};"></div></div>`;
                html += `<div class="component-evidence">${escapeHtml(c.evidence)}</div>`;
                html += '</div>';
            });
            html += '</div>';
            return html;
        }

        function renderChanges() {
            if (!digest || !digest.changes || !digest.changes.length) return '';
            const icons = { up: '▲', down: '▼', flat: '■' };
            const colors = { up: '#2ecc71', down: '#e74c3c', flat: '#95a5a6' };
            let html = `<div class="card"><div class="card-title">${escapeHtml(t('changes_title'))}</div>`;
            digest.changes.forEach(function (c) {
                html += `<div class="change-row"><div class="change-icon" style="color:${colors[c.direction] || '#95a5a6'}">${icons[c.direction] || '■'}</div><div class="change-text">${escapeHtml(c.text)}</div></div>`;
            });
            html += '</div>';
            return html;
        }

        function renderActions() {
            if (!digest || !digest.actions || !digest.actions.length) return '';
            let html = `<div class="card"><div class="card-title">${escapeHtml(t('actions_title'))}</div>`;
            digest.actions.forEach(function (a, i) {
                html += `<div class="action"><div class="action-num">${i + 1}</div><div><div class="action-title">${escapeHtml(a.title)}</div><div class="action-body">${escapeHtml(a.body)}</div></div></div>`;
            });
            html += '</div>';
            return html;
        }

        function renderHistory() {
            let html = `<div class="card"><div class="card-title">${escapeHtml(t('history_title'))}</div>`;
            if (!history.length) {
                html += `<div class="component-evidence">${escapeHtml(t('no_history'))}</div>`;
            } else {
                history.forEach(function (h) {
                    const scoreText = h.sufficient && h.score !== null ? Math.round(h.score) : '–';
                    html += `<div class="history-row"><span class="history-period">${escapeHtml(h.period_start)} – ${escapeHtml(h.period_end)}</span>`;
                    html += `<span class="history-badge">${escapeHtml(h.band_label || '')}</span>`;
                    html += `<span class="history-score">${scoreText}/100</span></div>`;
                });
            }
            html += '</div>';
            return html;
        }

        function render() {
            const container = document.getElementById('healthResults');
            if (!container) return;
            const btn = document.getElementById('refreshBtn');

            if (btn) {
                btn.disabled = loading || generating;
                btn.innerHTML = (loading || generating) ? escapeHtml(t('generating')) : escapeHtml(t('btn_refresh'));
            }

            if (loading) {
                container.innerHTML = `<div class="loading-container"><div class="loading-spinner"></div><div class="loading-text">${escapeHtml(t('loading'))}</div></div>`;
                return;
            }
            if (loadError) {
                container.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="empty-title">${escapeHtml(loadError)}</div></div>`;
                return;
            }
            if (!digest) {
                container.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-heart-pulse"></i></div><div class="empty-title">${escapeHtml(t('empty_title'))}</div><div class="empty-text">${escapeHtml(t('empty_hint'))}</div></div>`;
                return;
            }

            let html = '';
            if (digest.stored === false) {
                html += `<div class="notice">${escapeHtml(t('store_warning'))}</div>`;
            }
            html += renderScore();
            if (!digest.sufficient) {
                html += `<div class="card"><div class="card-title">${escapeHtml(t('insufficient_title'))}</div><div class="component-evidence">${escapeHtml(digest.band_interpretation || '')}</div></div>`;
            }
            if (digest.ai && digest.ai.interpretation) {
                html += `<div class="ai-card"><div class="ai-card-title"><i class="fa-solid fa-wand-magic-sparkles"></i> ${escapeHtml(t('ai_title'))}</div><div class="ai-card-text">${escapeHtml(digest.ai.interpretation)}</div></div>`;
            }
            html += renderComponents();
            html += renderChanges();
            html += renderActions();
            html += renderHistory();
            if (digest.limitations && digest.limitations.length) {
                html += `<div class="card"><div class="card-title">${escapeHtml(t('limitations_title'))}</div>` +
                    digest.limitations.map(item => `<div class="list-row"><span class="list-bullet">&bull;</span><span class="list-text">${escapeHtml(item)}</span></div>`).join('') + '</div>';
            }
            container.innerHTML = html;
        }

        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) logoutBtn.addEventListener('click', function () { localStorage.clear(); window.location.href = '/login?role=muuzaji'; });
            document.addEventListener('click', function (e) {
                if (window.innerWidth <= 768) {
                    const bar = document.getElementById('sidebar');
                    const btn = document.getElementById('mobileMenuToggle');
                    if (bar && bar.classList.contains('open') && !bar.contains(e.target) && btn && !btn.contains(e.target)) bar.classList.remove('open');
                }
            });
        }

        function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            render();
            load();

            const btn = document.getElementById('refreshBtn');
            if (btn) btn.addEventListener('click', refresh);
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { render(); updateSidebarUser(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim
