@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="muuzaji_uliza.page_title">Uliza Biashara - Dukamkononi Muuzaji</title>
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

        .header { background: white; padding: 16px 20px; border-radius: 16px; margin-bottom: 16px; }
        .title { font-size: 20px; font-weight: 700; color: #2c3e50; }

        .ask-bar { display: flex; gap: 10px; margin-bottom: 16px; }
        .ask-input {
            flex: 1; padding: 14px 16px; border: 1px solid #dfe4e6; border-radius: 14px; font-size: 15px; font-family: inherit;
        }
        .ask-btn {
            background: #2ecc71; color: white; border: none; padding: 0 22px; border-radius: 14px;
            font-weight: 700; cursor: pointer; font-size: 15px;
        }
        .ask-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        .card { background: white; border-radius: 18px; padding: 20px; margin-bottom: 16px; }
        .card-title { font-size: 15px; font-weight: 700; color: #2c3e50; margin-bottom: 10px; }
        .answer-card { background: #f4f9ff; }
        .answer-text { font-size: 16px; font-weight: 600; color: #2c3e50; line-height: 1.5; }
        .ai-card { background: #e8f8f0; border-radius: 16px; padding: 16px; margin-bottom: 16px; }
        .ai-card-title { font-size: 13px; font-weight: 700; color: #1e8449; margin-bottom: 6px; }
        .ai-card-text { font-size: 14px; color: #1e8449; line-height: 1.55; }

        .meta-row { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 12px; }
        .meta-text { font-size: 12px; color: #95a5a6; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th, table.data td { text-align: left; padding: 8px 10px; font-size: 13px; border-bottom: 1px solid #f0f0f0; }
        table.data th { color: #2c3e50; font-weight: 700; border-bottom: 1px solid #ecf0f1; }
        table.data td { color: #5d6d7e; }

        .bar-row { display: flex; align-items: center; gap: 10px; margin-bottom: 8px; }
        .bar-label { width: 120px; font-size: 12px; color: #5d6d7e; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .bar-track { flex: 1; height: 12px; background: #ecf0f1; border-radius: 6px; overflow: hidden; }
        .bar-fill { height: 12px; background: #2ecc71; border-radius: 6px; }
        .bar-value { width: 120px; font-size: 12px; color: #2c3e50; text-align: right; }

        .examples-title { font-size: 13px; font-weight: 600; color: #7f8c8d; margin-bottom: 10px; }
        .chip {
            display: inline-flex; align-items: center; gap: 8px; background: white; border: 1px solid #ecf0f1;
            border-radius: 20px; padding: 8px 14px; margin: 0 8px 8px 0; cursor: pointer; font-size: 13px; color: #2c3e50;
        }
        .chip:hover { background: #e8f8f0; border-color: #c8f0da; }

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
        .empty-text { font-size: 14px; color: #7f8c8d; }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.open { transform: translateX(0); }
            .main-content { margin-left: 0; padding: 20px 16px; padding-top: 70px; }
            .mobile-menu-toggle { display: block; }
            .bar-label { width: 80px; }
            .bar-value { width: 90px; }
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
            <div class="page-container" id="reportContent">
                <div class="header"><div class="title" data-i18n="muuzaji_uliza.title">Uliza Kuhusu Biashara Yako</div></div>
                <div class="ask-bar">
                    <input type="text" id="questionInput" class="ask-input" data-i18n="muuzaji_uliza.placeholder" data-i18n-attr="placeholder" placeholder="Uliza swali kuhusu biashara yako...">
                    <button class="ask-btn" id="askBtn" data-i18n="muuzaji_uliza.btn_ask">Uliza</button>
                </div>
                <div id="reportResults"></div>
            </div>
        </main>
    </div>

    <script>
        // ============================================
        // ULIZA BIASHARA (Natural-Language Reporting) - Muuzaji Web
        // Backed by POST /api/ai/report/ask
        // ============================================

        const API_BASE_URL = '';

        const SW = {
            err_no_permission: 'Huna ruhusa ya kuingia kwenye eneo la Muuzaji.',
            err_load_failed: 'Imeshindwa kujibu swali lako.',
            err_network: 'Hitilafu ya mtandao. Angalia muunganisho wako.',
            err_auth: 'Tafadhali ingia tena.',
            err_question: 'Tafadhali andika swali kwanza.',
            loading: 'Inatafuta jibu...',
            empty_title: 'Uliza swali lako la kwanza',
            empty_hint: 'Majibu yanatumia data ya duka lako pekee.',
            examples_title: 'Jaribu kuuliza',
            answer_title: 'Jibu',
            ai_ready: 'Maoni ya AI',
            ai_degraded_title: 'Maoni ya AI hayapatikani',
            table_title: 'Maelezo',
            chart_title: 'Chati',
            limitations_title: 'Maelezo ya ziada',
            range_label: 'Kipindi',
            unknown_title: 'Sikuelewa',
            metric_revenue: 'Mauzo',
            metric_profit: 'Faida',
            metric_units: 'Idadi',
            col_name: 'Jina', col_units: 'Idadi', col_revenue: 'Mauzo', col_profit: 'Faida',
            col_category: 'Aina', col_amount: 'Kiasi', col_total_purchases: 'Jumla ya manunuzi', col_purchases: 'Manunuzi'
        };

        function t(key, params) {
            const full = key.indexOf('muuzaji_uliza.') === 0 ? key : 'muuzaji_uliza.' + key;
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

        const EXAMPLE_KEYS = ['example_1', 'example_2', 'example_3', 'example_4', 'example_5', 'example_6'];
        const COLUMN_KEY = {
            name: 'col_name', units: 'col_units', revenue: 'col_revenue', profit: 'col_profit',
            category: 'col_category', amount: 'col_amount', total_purchases: 'col_total_purchases', purchases_count: 'col_purchases'
        };
        const MONEY_COLUMNS = ['amount', 'revenue', 'profit', 'total_purchases'];

        let report = null;
        let loading = false;
        let loadError = null;

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

        function formatMoney(amount) {
            if (typeof amount !== 'number' || !isFinite(amount)) return '-';
            const currency = (report && report.currency) || 'TZS';
            return currency + ' ' + amount.toLocaleString('en-TZ', { maximumFractionDigits: 0 });
        }

        function formatCell(column, value) {
            if (value === null || value === undefined || value === '') return '-';
            if (MONEY_COLUMNS.indexOf(column) > -1) {
                return typeof value === 'number' ? formatMoney(value) : String(value);
            }
            return String(value);
        }

        async function ask(rawQuestion) {
            const input = document.getElementById('questionInput');
            const question = (rawQuestion !== undefined ? rawQuestion : (input ? input.value : '')).trim();
            if (question === '') { showToast(t('err_question'), 'warning'); return; }
            if (input) input.value = question;

            const token = localStorage.getItem('userToken');
            if (!token) { showToast(t('err_auth'), 'error'); return; }

            loading = true;
            loadError = null;
            render();

            try {
                const res = await fetch(`${API_BASE_URL}/api/ai/report/ask`, {
                    method: 'POST',
                    headers: { 'Authorization': `Bearer ${token}`, 'Content-Type': 'application/json' },
                    body: JSON.stringify({ question: question, locale: currentLocale() })
                });
                if (!res.ok) {
                    loadError = t('err_load_failed');
                } else {
                    report = await res.json();
                }
            } catch (error) {
                console.error('Error asking report:', error);
                loadError = t('err_network');
            } finally {
                loading = false;
                render();
            }
        }

        function renderUnderstanding() {
            const u = report.understanding;
            if (!u || u.tool === null) {
                return `<div class="card" style="background:#fef5e7;"><div class="card-title" style="color:#b9770e;">${escapeHtml(t('unknown_title'))}</div><div>${escapeHtml(report.answer.summary_text)}</div></div>`;
            }
            let html = '<div class="meta-row">';
            if (u.range) html += `<span class="meta-text">${escapeHtml(t('range_label'))}: ${escapeHtml(u.range.from)} – ${escapeHtml(u.range.to)}</span>`;
            if (u.metric) html += `<span class="meta-text">${escapeHtml(t('metric_' + u.metric))}</span>`;
            html += '</div>';
            return html;
        }

        function renderTable() {
            if (!report.table || !report.table.rows || report.table.rows.length === 0) return '';
            const head = report.table.columns.map(c => `<th>${escapeHtml(t(COLUMN_KEY[c] || 'col_name'))}</th>`).join('');
            const body = report.table.rows.map(row => '<tr>' + row.map((cell, i) =>
                `<td>${escapeHtml(formatCell(report.table.columns[i], cell))}</td>`).join('') + '</tr>').join('');
            return `<div class="card"><div class="card-title">${escapeHtml(t('table_title'))}</div><table class="data"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
        }

        function renderChart() {
            if (!report.chart || !report.chart.values || report.chart.values.length === 0) return '';
            const max = Math.max.apply(null, report.chart.values.map(v => Math.abs(v)).concat([1]));
            const rows = report.chart.labels.map((label, i) => {
                const value = report.chart.values[i] || 0;
                const pct = Math.max(4, Math.round((Math.abs(value) / max) * 100));
                const shown = (report.chart.metric === 'expenses' || report.chart.metric === 'revenue') ? formatMoney(value) : String(value);
                return `<div class="bar-row"><div class="bar-label" title="${escapeHtml(label)}">${escapeHtml(label)}</div><div class="bar-track"><div class="bar-fill" style="width:${pct}%;"></div></div><div class="bar-value">${escapeHtml(shown)}</div></div>`;
            }).join('');
            return `<div class="card"><div class="card-title">${escapeHtml(t('chart_title'))}</div>${rows}</div>`;
        }

        function renderExamples() {
            return `<div style="margin-top:8px;"><div class="examples-title">${escapeHtml(t('examples_title'))}</div>` +
                EXAMPLE_KEYS.map(k => `<span class="chip" data-example="${escapeHtml(t(k))}"><i class="fa-solid fa-comment-dots"></i> ${escapeHtml(t(k))}</span>`).join('') +
                '</div>';
        }

        function render() {
            const container = document.getElementById('reportResults');
            if (!container) return;

            if (loading) {
                container.innerHTML = `<div class="loading-container"><div class="loading-spinner"></div><div class="loading-text">${escapeHtml(t('loading'))}</div></div>`;
                return;
            }
            if (loadError) {
                container.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div class="empty-title">${escapeHtml(loadError)}</div></div>${renderExamples()}`;
                return;
            }
            if (!report) {
                container.innerHTML = `<div class="empty-state"><div class="empty-icon"><i class="fa-solid fa-comments"></i></div><div class="empty-title">${escapeHtml(t('empty_title'))}</div><div class="empty-text">${escapeHtml(t('empty_hint'))}</div></div>${renderExamples()}`;
                return;
            }

            let html = renderUnderstanding();
            html += `<div class="card answer-card"><div class="card-title">${escapeHtml(t('answer_title'))}</div><div class="answer-text">${escapeHtml(report.answer.summary_text)}</div></div>`;
            if (report.ai && report.ai.explanation) {
                html += `<div class="ai-card"><div class="ai-card-title"><i class="fa-solid fa-wand-magic-sparkles"></i> ${escapeHtml(t('ai_ready'))}</div><div class="ai-card-text">${escapeHtml(report.ai.explanation)}</div></div>`;
            }
            html += renderChart();
            html += renderTable();
            if (report.limitations && report.limitations.length) {
                html += `<div style="margin-bottom:16px;"><div class="card-title">${escapeHtml(t('limitations_title'))}</div>` +
                    report.limitations.map(item => `<div class="list-row"><span class="list-bullet">&bull;</span><span class="list-text">${escapeHtml(item)}</span></div>`).join('') + '</div>';
            }
            html += renderExamples();
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

        window.ask = ask;

        function init() {
            if (!checkAuth()) return;
            updateSidebarUser();
            setupSidebar();
            render();

            document.getElementById('askBtn').addEventListener('click', function () { ask(); });
            document.getElementById('questionInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') ask();
            });
            document.addEventListener('click', function (e) {
                const chip = e.target.closest ? e.target.closest('.chip[data-example]') : null;
                if (chip) ask(chip.getAttribute('data-example'));
            });
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { render(); updateSidebarUser(); });
        }

        init();
    </script>
</body>
</html>
@endverbatim
