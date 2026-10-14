@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="mteja_nyingine.page_title">Nyingine - Dukamkononi Mteja</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background-color: #f8f9fa; }

        .mteja-layout { display: flex; min-height: 100vh; }
        .sidebar {
            width: 280px; background: #ffffff; border-right: 1px solid #ecf0f1;
            position: fixed; left: 0; top: 0; bottom: 0; z-index: 100;
            transition: transform 0.3s ease; display: flex; flex-direction: column;
            box-shadow: 2px 0 12px rgba(0,0,0,0.05);
        }
        .sidebar-header { padding: 30px 24px; border-bottom: 1px solid #ecf0f1; }
        .logo-area { display: flex; align-items: center; gap: 12px; }
        .logo-icon {
            width: 45px; height: 45px; background: linear-gradient(135deg, #667eea, #764ba2);
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
        .nav-item:hover { background-color: #ede7f6; }
        .nav-item.active { background-color: #ede7f6; color: #667eea; }
        .nav-icon { font-size: 22px; width: 26px; display: flex; align-items: center; justify-content: center; }
        .nav-label { font-size: 15px; font-weight: 600; }
        .sidebar-footer { padding: 20px 16px; border-top: 1px solid #ecf0f1; margin-top: auto; }
        .user-info { display: flex; align-items: center; gap: 12px; margin-bottom: 15px; }
        .user-avatar {
            width: 40px; height: 40px; border-radius: 50%; background: #ede7f6;
            display: flex; align-items: center; justify-content: center;
            color: #667eea; font-weight: 800; overflow: hidden;
        }
        .user-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .user-name { font-size: 14px; font-weight: 700; color: #2c3e50; }
        .user-role { font-size: 12px; color: #667eea; font-weight: 600; }
        .logout-btn {
            display: flex; align-items: center; gap: 8px; padding: 10px 14px;
            border-radius: 12px; color: #e74c3c; font-weight: 700; font-size: 14px;
            cursor: pointer; transition: background 0.2s;
        }
        .logout-btn:hover { background: #fdeaea; }

        .main-content { margin-left: 280px; flex: 1; padding: 34px 30px 60px; min-width: 0; max-width: 980px; }
        .page-head { margin-bottom: 18px; }
        .page-title { font-size: 24px; font-weight: 800; color: #2c3e50; display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .page-sub { font-size: 13.5px; color: #7f8c8d; margin-top: 4px; }
        .ai-chip {
            display: inline-flex; align-items: center; gap: 6px; font-size: 11px;
            font-weight: 800; letter-spacing: .04em; color: #667eea;
            background: #eef0ff; border: 1px solid #dcdfff; border-radius: 999px; padding: 4px 10px;
        }

        .ask-card {
            background: #fff; border: 1px solid #ecf0f1; border-radius: 18px;
            padding: 18px 18px 14px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 18px;
        }
        .ask-row { display: flex; gap: 10px; align-items: flex-start; }
        .ask-input {
            flex: 1; border: 1.5px solid #e3e6f0; border-radius: 14px; padding: 13px 14px;
            font-size: 14.5px; font-family: inherit; resize: none; min-height: 52px; max-height: 140px;
            outline: none; transition: border-color .15s;
        }
        .ask-input:focus { border-color: #667eea; }
        .ask-btn {
            background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; border: none;
            border-radius: 14px; padding: 0 22px; min-height: 52px; font-weight: 800;
            font-size: 14.5px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px;
        }
        .ask-btn:disabled { opacity: .6; cursor: not-allowed; }
        .ask-hint { font-size: 12px; color: #95a5a6; margin-top: 8px; }

        .state-box { text-align: center; padding: 46px 18px; color: #7f8c8d; }
        .state-box .state-title { font-size: 16px; font-weight: 700; color: #2c3e50; margin-top: 12px; }
        .state-box .state-text { font-size: 13.5px; margin-top: 6px; line-height: 1.55; }

        .answer-card {
            background: linear-gradient(135deg, #1e2a4a, #2c3e6f); color: #fff;
            border-radius: 18px; padding: 18px 20px; margin-bottom: 18px;
        }
        .answer-label { font-size: 11px; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; opacity: .75; margin-bottom: 6px; }
        .answer-text { font-size: 14.5px; line-height: 1.6; }
        .answer-note { font-size: 12px; opacity: .7; margin-top: 8px; }

        .order-card {
            background: #fff; border: 1px solid #ecf0f1; border-radius: 18px;
            padding: 16px 18px; margin-bottom: 14px;
        }
        .order-card.focused { border-color: #c3c9ff; box-shadow: 0 2px 12px rgba(102,126,234,.12); }
        .order-top { display: flex; align-items: center; justify-content: space-between; gap: 10px; flex-wrap: wrap; }
        .order-ref { font-weight: 800; color: #2c3e50; font-size: 14.5px; }
        .focused-tag {
            font-size: 10.5px; font-weight: 800; color: #667eea; background: #eef0ff;
            border-radius: 999px; padding: 3px 9px; margin-left: 8px;
        }
        .status-badge { font-size: 12px; font-weight: 800; padding: 4px 11px; border-radius: 999px; background: #ede7f6; color: #5b3fa8; }
        .order-meta { font-size: 13px; color: #7f8c8d; margin-top: 8px; line-height: 1.6; }
        .order-items { margin-top: 8px; font-size: 13px; color: #44546a; }
        .order-items div { padding: 3px 0; }
        .timeline { margin-top: 10px; border-top: 1px solid #f4f6f9; padding-top: 8px; }
        .timeline-row { display: flex; gap: 8px; font-size: 12.5px; color: #5d6d7e; padding: 3px 0; }
        .timeline-row b { color: #2c3e50; min-width: 110px; }

        .orders-title { font-size: 15px; font-weight: 800; color: #2c3e50; margin: 20px 0 10px; }
        .guide-card { background: #fff; border: 1px solid #ecf0f1; border-radius: 18px; padding: 16px 18px; margin-top: 18px; }
        .guide-title { font-size: 14px; font-weight: 800; color: #2c3e50; margin-bottom: 10px; }
        .guide-chips { display: flex; flex-wrap: wrap; gap: 8px; }
        .guide-chip { font-size: 12.5px; font-weight: 600; background: #f8f9fa; border: 1px solid #eceff3; color: #5d6d7e; border-radius: 999px; padding: 6px 12px; }

        .limitations { margin-top: 20px; font-size: 12.5px; color: #95a5a6; line-height: 1.6; }
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
            .ask-row { flex-direction: column; }
            .ask-btn { width: 100%; justify-content: center; min-height: 48px; }
        }
    </style>
</head>
@endverbatim
@verbatim
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@include('partials.toast')
@verbatim
    <div class="mobile-menu-toggle" id="mobileMenuToggle">☰</div>
    <div class="mteja-layout">
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo-area">
                    <div class="logo-icon" data-i18n="mteja_nyingine.logo_short">D</div>
                    <div class="logo-text">
                        <h2 data-i18n="mteja_nyingine.logo_brand">DukaMkononi</h2>
                        <p data-i18n="mteja_nyingine.logo_tagline">Mteja Portal</p>
                    </div>
                </div>
            </div>
            <div class="nav-items">
                <a href="biashara" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 9L4.5 3.5H19.5L21 9" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M4.5 9V20.5H19.5V9"/>
                            <path d="M3 9H21"/>
                            <path d="M9.5 20.5V14.5H14.5V20.5"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_nyingine.nav_business">Biashara</span>
                </a>
                <a href="matangazo" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M3 10V14M3 10L13 5V19L3 14" stroke-linejoin="round"/>
                            <path d="M13 7C15.5 7 17.5 9.2 17.5 12C17.5 14.8 15.5 17 13 17" stroke-linecap="round"/>
                            <path d="M16 16.5L17.5 20.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_nyingine.nav_ads">Matangazo</span>
                </a>
                <a href="profaili" class="nav-item">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21C4 17.134 7.582 14 12 14C16.418 14 20 17.134 20 21" stroke-linecap="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_nyingine.nav_profile">Profaili</span>
                </a>
                <a href="nyingine" class="nav-item active">
                    <div class="nav-icon">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M12 3L13.8 8.2L19 10L13.8 11.8L12 17L10.2 11.8L5 10L10.2 8.2L12 3Z" stroke-linejoin="round"/>
                            <path d="M18.5 15.5L19.3 17.7L21.5 18.5L19.3 19.3L18.5 21.5L17.7 19.3L15.5 18.5L17.7 17.7L18.5 15.5Z" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="nav-label" data-i18n="mteja_nyingine.nav_label">Nyingine</span>
                </a>
            </div>
            <div class="sidebar-footer">
                <div class="user-info"><div class="user-avatar" id="userAvatar">M</div><div class="user-details"><div class="user-name" id="userName" data-i18n="mteja_nyingine.user_label">Mteja</div><div class="user-role" data-i18n="mteja_nyingine.user_label">Mteja</div></div></div>
                <div class="logout-btn" id="logoutBtn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span data-i18n="mteja_nyingine.logout">Ondoka</span></div>
            </div>
        </aside>
        <main class="main-content" id="mainContent">
            <div class="page-head">
                <div class="page-title">
                    <span data-i18n="mteja_nyingine.title">Maswali ya Oda</span>
                    <span class="ai-chip"><i class="fa-solid fa-wand-magic-sparkles"></i> <span data-i18n="mteja_nyingine.ai_badge">AI</span></span>
                </div>
                <div class="page-sub" data-i18n="mteja_nyingine.subtitle">Uliza kuhusu oda yako kwa lugha yako</div>
            </div>

            <div class="ask-card">
                <div class="ask-row">
                    <textarea class="ask-input" id="askInput" rows="2" data-i18n="mteja_nyingine.ask_placeholder" data-i18n-attr="placeholder" placeholder="Mfano: Oda yangu iko wapi?"></textarea>
                    <button class="ask-btn" id="askBtn"><i class="fa-solid fa-paper-plane"></i> <span data-i18n="mteja_nyingine.ask_btn">Tuma</span></button>
                </div>
                <div class="ask-hint" data-i18n="mteja_nyingine.example_hint">Unaweza kuuliza kuhusu hali ya oda, muda na vifurushi.</div>
            </div>

            <div id="stateBox" class="state-box">
                <i class="fa-regular fa-comments" style="font-size:34px;color:#667eea;"></i>
                <div class="state-title" data-i18n="mteja_nyingine.empty_title">Bado hamjauliza</div>
                <div class="state-text" data-i18n="mteja_nyingine.empty_text">Andika swali hapo juu kujua hali ya oda yako.</div>
            </div>

            <div id="resultArea"></div>
            <div id="guideArea"></div>
            <div class="limitations" id="limitations" style="display:none;"></div>
        </main>
    </div>

    <script>
        const API_BASE_URL = '';

        const SW = {
            page_title: "Nyingine - Dukamkononi Mteja",
            logo_short: "D",
            logo_brand: "DukaMkononi",
            logo_tagline: "Mteja Portal",
            nav_business: "Biashara",
            nav_ads: "Matangazo",
            nav_profile: "Profaili",
            nav_label: "Nyingine",
            user_label: "Mteja",
            logout: "Ondoka",
            title: "Maswali ya Oda",
            subtitle: "Uliza kuhusu oda yako kwa lugha yako",
            ai_badge: "AI",
            ask_placeholder: "Mfano: Oda yangu iko wapi?",
            ask_btn: "Tuma",
            example_hint: "Unaweza kuuliza kuhusu hali ya oda, muda na vifurushi.",
            empty_title: "Bado hamjauliza",
            empty_text: "Andika swali hapo juu kujua hali ya oda yako.",
            loading: "Inatafuta oda zako...",
            answer_label: "Jibu",
            message_label: "Ujumbe",
            ai_note: "Hili ni jibu la kiotomatiki kutoka kwenye rekodi zako; AI haijawepo kwa sasa.",
            focused: "Oda inayojibiwa",
            orders_title: "Oda zako za hivi karibuni",
            guide_title: "Maana ya hali za oda",
            items_label: "Bidhaa:",
            timeline_label: "Historia:",
            total_label: "Jumla:",
            error_title: "Hitilafu",
            error_text: "Imeshindwa kupata jibu. Tafadhali jaribu tena.",
            sign_in_again: "Tafadhali ingia tena",
            limitations_title: "Maelezo:",
            generated: "Imetolewa {date}",
        };

        function t(key, params) {
            const full = key.indexOf('mteja_nyingine.') === 0 ? key : 'mteja_nyingine.' + key;
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

        function fmtMoney(value, currency) {
            const amount = Number(value || 0).toLocaleString('en-US', { maximumFractionDigits: 0 });
            return amount + ' ' + (currency || 'TZS');
        }

        let userToken = null;
        let asking = false;

        function showIdle(kind, titleKey, textKey) {
            const box = document.getElementById('stateBox');
            box.style.display = 'block';
            const icon = kind === 'loading'
                ? '<div class="loading-spinner dm-sc"></div>'
                : (kind === 'error'
                    ? '<i class="fa-solid fa-triangle-exclamation" style="font-size:34px;color:#e74c3c;"></i>'
                    : '<i class="fa-regular fa-comments" style="font-size:34px;color:#667eea;"></i>');
            box.innerHTML = icon +
                '<div class="state-title">' + escapeHtml(t(titleKey)) + '</div>' +
                '<div class="state-text">' + escapeHtml(t(textKey)) + '</div>';
        }

        function statusLabels(data) {
            const map = {};
            (data.status_guide || []).forEach(function (row) { map[row.status] = row.label; });
            return map;
        }

        function renderOrder(order, labels, focused) {
            const items = (order.items || []).map(function (item) {
                const name = item.product_name || item.name || '';
                return '<div>' + escapeHtml(name) + ' × ' + escapeHtml(String(item.quantity || 0)) +
                    ' — ' + escapeHtml(fmtMoney(item.line_total != null ? item.line_total : (item.unit_price || 0) * (item.quantity || 0), order.currency)) + '</div>';
            }).join('');
            const timeline = (order.timeline || []).map(function (entry) {
                return '<div class="timeline-row"><b>' + escapeHtml(labels[entry.status] || entry.status || '') + '</b>' +
                    '<span>' + escapeHtml(String(entry.at || '').slice(0, 16).replace('T', ' ')) + '</span>' +
                    (entry.note ? '<span>' + escapeHtml(entry.note) + '</span>' : '') + '</div>';
            }).join('');
            return '<div class="order-card' + (focused ? ' focused' : '') + '">' +
                '<div class="order-top">' +
                    '<div class="order-ref">' + escapeHtml(order.order_reference || '—') +
                        (focused ? ' <span class="focused-tag">' + escapeHtml(t('focused')) + '</span>' : '') + '</div>' +
                    '<span class="status-badge">' + escapeHtml(labels[order.status] || order.status || '') + '</span>' +
                '</div>' +
                '<div class="order-meta">' +
                    escapeHtml(String(order.order_date || '').slice(0, 10)) + ' · ' +
                    escapeHtml(t('total_label')) + ' <b>' + escapeHtml(fmtMoney(order.total_amount, order.currency)) + '</b>' +
                    (order.business_name ? ' · ' + escapeHtml(order.business_name) : '') +
                '</div>' +
                (items ? '<div class="order-items"><b>' + escapeHtml(t('items_label')) + '</b>' + items + '</div>' : '') +
                (timeline ? '<div class="timeline"><b class="order-meta">' + escapeHtml(t('timeline_label')) + '</b>' + timeline + '</div>' : '') +
            '</div>';
        }

        function renderResult(data) {
            document.getElementById('stateBox').style.display = 'none';
            const area = document.getElementById('resultArea');
            const labels = statusLabels(data);
            let html = '';

            const answer = data.answer || {};
            const message = data.message || '';
            if (answer.text || message) {
                const note = (answer.degraded && answer.code !== 'NO_ORDERS' && answer.code !== 'NO_ACCOUNT_PHONE' && answer.code !== 'REFERENCE_NOT_FOUND' && (answer.reason || t('ai_note')))
                    ? '<div class="answer-note">' + escapeHtml(answer.reason || t('ai_note')) + '</div>'
                    : '';
                html += '<div class="answer-card">' +
                    '<div class="answer-label">' + escapeHtml(t(message && !answer.text ? 'message_label' : 'answer_label')) + '</div>' +
                    '<div class="answer-text">' + escapeHtml(message || answer.text) + '</div>' + note +
                '</div>';
            } else {
                showIdle('error', 'error_title', 'error_text');
            }

            const orders = data.orders || [];
            if (orders.length) {
                html += '<div class="orders-title">' + escapeHtml(t('orders_title')) + '</div>';
                html += orders.map(function (order) {
                    return renderOrder(order, labels, order.order_reference && order.order_reference === data.focused_reference);
                }).join('');
            }

            area.innerHTML = html;

            const guideArea = document.getElementById('guideArea');
            if ((data.status_guide || []).length) {
                guideArea.innerHTML = '<div class="guide-card"><div class="guide-title">' + escapeHtml(t('guide_title')) + '</div>' +
                    '<div class="guide-chips">' + data.status_guide.map(function (row) {
                        return '<span class="guide-chip">' + escapeHtml(row.label) + '</span>';
                    }).join('') + '</div></div>';
            } else {
                guideArea.innerHTML = '';
            }

            const limitations = document.getElementById('limitations');
            if ((data.limitations || []).length) {
                limitations.style.display = 'block';
                let inner = '<b>' + escapeHtml(t('limitations_title')) + '</b> ' + data.limitations.map(escapeHtml).join('<br>');
                if (data.generated_at) {
                    inner += '<div style="margin-top:8px;">' + escapeHtml(t('generated', { date: String(data.generated_at).slice(0, 16).replace('T', ' ') })) + '</div>';
                }
                limitations.innerHTML = inner;
            } else {
                limitations.style.display = 'none';
            }
        }

        async function askQuestion() {
            if (asking || !userToken) return;
            const input = document.getElementById('askInput');
            const question = input.value.trim();
            if (!question) return;

            asking = true;
            document.getElementById('askBtn').disabled = true;
            showIdle('loading', 'empty_title', 'loading');
            document.getElementById('resultArea').innerHTML = '';

            try {
                const res = await fetch(API_BASE_URL + '/api/ai/orders/ask', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': 'Bearer ' + userToken },
                    body: JSON.stringify({ question: question, locale: currentLocale() })
                });
                if (res.status === 401) { window.location.href = '/login?role=mteja'; return; }
                const data = await res.json().catch(function () { return {}; });
                if (!res.ok && !data.answer) {
                    showIdle('error', 'error_title', 'error_text');
                    return;
                }
                renderResult(data);
            } catch (e) {
                showIdle('error', 'error_title', 'error_text');
            } finally {
                asking = false;
                document.getElementById('askBtn').disabled = false;
            }
        }

        function setupSidebar() {
            const toggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            if (toggle && sidebar) toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
            const logoutBtn = document.getElementById('logoutBtn');
            if (logoutBtn) logoutBtn.addEventListener('click', function () {
                localStorage.clear();
                window.location.href = '/login?role=mteja';
            });
        }

        function checkAuth() {
            let user = null;
            try { user = JSON.parse(localStorage.getItem('userData') || 'null'); } catch (e) { user = null; }
            if (!user || !localStorage.getItem('userToken')) {
                window.location.href = '/login?role=mteja';
                return false;
            }
            const role = user.role || '';
            if (role !== 'customer' && role !== 'client' && role !== 'mteja') {
                window.location.href = '/login?role=mteja';
                return false;
            }
            document.getElementById('userName').textContent = user.full_name || user.email?.split('@')[0] || t('user_label');
            document.getElementById('userAvatar').textContent = (user.full_name || user.email || 'M').charAt(0).toUpperCase();
            return true;
        }

        function init() {
            setupSidebar();
            if (!checkAuth()) return;
            userToken = localStorage.getItem('userToken');
            document.getElementById('askBtn').addEventListener('click', askQuestion);
            document.getElementById('askInput').addEventListener('keydown', function (e) {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); askQuestion(); }
            });
        }

        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(function () { /* static text handled by data-i18n */ });
        }

        init();
    </script>
</body>
</html>
@endverbatim
