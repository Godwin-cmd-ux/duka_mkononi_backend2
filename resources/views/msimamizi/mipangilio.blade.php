@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="msimamizi_mipangilio.page_title_mipangilio">Mipangilio - Dukamkononi Msimamizi</title>
    <link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, sans-serif;
            background: var(--dm-bg, #f8fafc);
            color: var(--dm-ink, #1e293b);
            min-height: 100vh;
        }

        .settings-shell { display: flex; min-height: 100vh; }

        /* ---------------- Sidebar ---------------- */
        .settings-side {
            width: 268px;
            flex: 0 0 268px;
            background: var(--dm-card, #fff);
            border-right: 1px solid var(--dm-line, #e2e8f0);
            padding: 26px 18px;
            position: sticky;
            top: 0;
            height: 100vh;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .brand { display: flex; align-items: center; gap: 11px; padding: 0 6px; }
        .brand-icon {
            width: 42px; height: 42px; border-radius: 12px;
            background: linear-gradient(135deg, var(--dm-primary, #1e3a8a), #3498db);
            color: #fff; font-weight: 800; font-size: 19px;
            display: grid; place-items: center;
        }
        .brand-name { font-weight: 800; font-size: 16px; letter-spacing: -.2px; }
        .brand-sub { font-size: 11px; color: var(--dm-muted, #64748b); margin-top: 1px; }

        .side-label {
            font-size: 10px; font-weight: 800; text-transform: uppercase;
            letter-spacing: .9px; color: var(--dm-muted, #64748b);
            padding: 0 8px;
        }

        .section-nav { display: flex; flex-direction: column; gap: 5px; }

        .section-btn {
            display: flex; align-items: center; gap: 11px;
            width: 100%; border: none; background: transparent;
            padding: 11px 12px; border-radius: 11px;
            font-family: inherit; font-size: 13.5px; font-weight: 600;
            color: var(--dm-ink, #1e293b); cursor: pointer; text-align: left;
            transition: background .16s ease, color .16s ease;
        }
        .section-btn:hover { background: var(--dm-hover, #f1f5f9); }
        .section-btn.active {
            background: var(--dm-tint, #dbeafe);
            color: var(--dm-tint-ink, #1e40af);
        }
        .section-btn .nav-ico { font-size: 16px; line-height: 1; width: 20px; text-align: center; }

        .side-spacer { flex: 1; }

        .back-link {
            display: flex; align-items: center; gap: 9px;
            padding: 11px 12px; border-radius: 11px; text-decoration: none;
            font-size: 13px; font-weight: 600; color: var(--dm-muted, #64748b);
            border: 1px solid var(--dm-line, #e2e8f0);
            transition: background .16s ease;
        }
        .back-link:hover { background: var(--dm-hover, #f1f5f9); }

        /* ---------------- Main ---------------- */
        .settings-main { flex: 1; min-width: 0; padding: 30px clamp(16px, 4vw, 46px) 80px; }

        .page-head { max-width: 940px; margin: 0 auto 24px; }
        .page-title { font-size: clamp(23px, 3vw, 30px); font-weight: 800; letter-spacing: -.5px; }
        .page-sub { margin-top: 6px; font-size: 13.5px; color: var(--dm-muted, #64748b); max-width: 640px; line-height: 1.55; }

        .section { display: none; max-width: 940px; margin: 0 auto; animation: fadeIn .18s ease; }
        .section.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: none; } }

        .card {
            background: var(--dm-card, #fff);
            border: 1px solid var(--dm-line, #e2e8f0);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 16px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }
        .card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 14px; margin-bottom: 18px; }
        .card-title { font-size: 15.5px; font-weight: 800; }
        .card-desc { font-size: 12.5px; color: var(--dm-muted, #64748b); margin-top: 4px; line-height: 1.5; max-width: 560px; }

        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; }
        .grid .full { grid-column: 1 / -1; }

        .field label { display: block; font-size: 11.5px; font-weight: 700; margin-bottom: 6px; letter-spacing: .15px; }
        .field input, .field select, .field textarea {
            width: 100%; font-family: inherit; font-size: 13.5px;
            padding: 11px 12px; border-radius: 10px;
            border: 1px solid var(--dm-line, #e2e8f0);
            background: var(--dm-input-bg, #fff); color: var(--dm-ink, #1e293b);
            outline: none; transition: border-color .16s ease, box-shadow .16s ease;
        }
        .field input:focus, .field select:focus, .field textarea:focus {
            border-color: var(--dm-primary, #1e3a8a);
            box-shadow: 0 0 0 3px var(--dm-focus-soft, rgba(30, 58, 138, .14));
        }
        .field input[readonly] { background: var(--dm-bg, #f8fafc); color: var(--dm-muted, #64748b); cursor: not-allowed; }
        .field textarea { resize: vertical; min-height: 84px; }
        .hint { font-size: 11.5px; color: var(--dm-muted, #64748b); margin-top: 5px; line-height: 1.45; }

        .actions { display: flex; align-items: center; gap: 10px; margin-top: 18px; flex-wrap: wrap; }

        .btn {
            border: 1px solid transparent; border-radius: 10px;
            padding: 10px 17px; font-family: inherit; font-size: 13px; font-weight: 700;
            cursor: pointer; transition: transform .12s ease, box-shadow .16s ease, background .16s ease;
        }
        .btn:active { transform: translateY(1px); }
        .btn:disabled { opacity: .55; cursor: not-allowed; }
        .btn-primary { background: var(--dm-primary, #1e3a8a); color: #fff; box-shadow: 0 6px 14px var(--dm-btn-glow, rgba(30, 58, 138, .22)); }
        .btn-primary:hover { background: var(--dm-primary-dark, #172554); }
        .btn-ghost { background: transparent; border-color: var(--dm-line, #e2e8f0); color: var(--dm-ink, #1e293b); }
        .btn-ghost:hover { background: var(--dm-hover, #f1f5f9); }
        .btn-danger { background: #fef2f2; color: var(--dm-danger, #dc2626); border-color: #fecaca; }
        .btn-danger:hover { background: #fee2e2; }

        /* ---------------- Toggle rows ---------------- */
        .toggle-row {
            display: flex; align-items: flex-start; justify-content: space-between;
            gap: 18px; padding: 16px 0; border-top: 1px solid var(--dm-line, #e2e8f0);
        }
        .toggle-row:first-of-type { border-top: none; padding-top: 2px; }
        .toggle-copy { min-width: 0; }
        .toggle-title { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .toggle-desc { font-size: 12px; color: var(--dm-muted, #64748b); margin-top: 4px; line-height: 1.5; }
        .badge {
            font-size: 9.5px; font-weight: 800; text-transform: uppercase; letter-spacing: .6px;
            background: var(--dm-tint, #dbeafe); color: var(--dm-tint-ink, #1e40af);
            padding: 3px 7px; border-radius: 999px;
        }
        .badge.soon { background: #fff7ed; color: #b45309; }

        .switch { position: relative; display: inline-block; width: 46px; height: 26px; flex: 0 0 46px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .switch .slider {
            position: absolute; inset: 0; cursor: pointer; border-radius: 999px;
            background: #cbd5e1; transition: background .18s ease;
        }
        .switch .slider::before {
            content: ''; position: absolute; height: 20px; width: 20px; left: 3px; top: 3px;
            background: #fff; border-radius: 50%; transition: transform .18s ease;
            box-shadow: 0 1px 3px rgba(15, 23, 42, .25);
        }
        .switch input:checked + .slider { background: var(--dm-success, #059669); }
        .switch input:checked + .slider::before { transform: translateX(20px); }
        .switch input:focus-visible + .slider { box-shadow: 0 0 0 3px var(--dm-focus-soft, rgba(30, 58, 138, .14)); }
        .switch input:disabled + .slider { opacity: .5; cursor: not-allowed; }

        /* ---------------- Branches ---------------- */
        .branch-list { display: flex; flex-direction: column; gap: 10px; }
        .branch-item {
            display: flex; align-items: center; justify-content: space-between;
            gap: 14px; padding: 14px; border-radius: 12px;
            border: 1px solid var(--dm-line, #e2e8f0); background: var(--dm-bg, #f8fafc);
        }
        .branch-name { font-size: 13.5px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .branch-meta { font-size: 11.5px; color: var(--dm-muted, #64748b); margin-top: 3px; }
        .branch-actions { display: flex; gap: 7px; flex: 0 0 auto; }
        .status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--dm-success, #059669); }
        .status-dot.off { background: #94a3b8; }
        .empty { font-size: 12.5px; color: var(--dm-muted, #64748b); padding: 14px; text-align: center; }

        .pref-value {
            font-size: 12.5px; font-weight: 700; padding: 8px 12px;
            border-radius: 9px; background: var(--dm-bg, #f8fafc);
            border: 1px solid var(--dm-line, #e2e8f0);
        }

        /* ---------------- Modal ---------------- */
        .modal-overlay {
            position: fixed; inset: 0; background: rgba(15, 23, 42, .5);
            display: none; align-items: center; justify-content: center;
            z-index: 1000; padding: 18px;
        }
        .modal-overlay.open { display: flex; }
        .modal {
            width: 100%; max-width: 470px; background: var(--dm-card, #fff);
            border-radius: 18px; padding: 22px; max-height: 90vh; overflow: auto;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .3);
        }
        .modal-title { font-size: 16px; font-weight: 800; margin-bottom: 16px; }

        /* ---------------- Toast ---------------- */
        .toast {
            position: fixed; right: 20px; bottom: 20px; z-index: 1200;
            background: #0f172a; color: #fff; padding: 13px 17px;
            border-radius: 12px; font-size: 13px; font-weight: 600;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .32);
            opacity: 0; transform: translateY(10px); pointer-events: none;
            transition: opacity .2s ease, transform .2s ease; max-width: calc(100vw - 40px);
        }
        .toast.show { opacity: 1; transform: none; }
        .toast.error { background: var(--dm-danger, #dc2626); }

        .skeleton { height: 12px; border-radius: 6px; background: linear-gradient(90deg, #eef2f7, #e2e8f0, #eef2f7); background-size: 200% 100%; animation: shimmer 1.2s infinite; }
        @keyframes shimmer { to { background-position: -200% 0; } }

        /* ---------------- Responsive ---------------- */
        @media (max-width: 900px) {
            .settings-shell { flex-direction: column; }
            .settings-side {
                width: 100%; flex: none; height: auto; position: sticky; top: 0; z-index: 50;
                padding: 12px 14px; border-right: none; border-bottom: 1px solid var(--dm-line, #e2e8f0);
                flex-direction: row; align-items: center; gap: 12px; overflow-x: auto;
            }
            .brand { flex: 0 0 auto; }
            .brand-text { display: none; }
            .brand-icon { width: 36px; height: 36px; font-size: 16px; }
            .side-label, .side-spacer, .back-link span { display: none; }
            .section-nav { flex-direction: row; gap: 6px; flex: 0 0 auto; }
            .section-btn { white-space: nowrap; padding: 9px 12px; font-size: 12.5px; }
            .section-btn .nav-ico { width: auto; }
            .back-link { padding: 9px 11px; margin-left: auto; flex: 0 0 auto; }
            .settings-main { padding: 20px 14px 70px; }
            .switch { width: 44px; height: 25px; flex-basis: 44px; }
            .switch .slider::before { height: 19px; width: 19px; }
            .switch input:checked + .slider::before { transform: translateX(19px); }
        }

        @media (max-width: 620px) {
            .grid { grid-template-columns: 1fr; }
            .card { padding: 17px; border-radius: 14px; }
            .branch-item { flex-direction: column; align-items: stretch; }
            .branch-actions { justify-content: flex-end; }
            .toggle-row { gap: 12px; }
            .toast { left: 14px; right: 14px; bottom: 14px; max-width: none; }
        }
    </style>
</head>
<body>
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
<div class="settings-shell">
    <aside class="settings-side">
        <div class="brand">
            <div class="brand-icon">D</div>
            <div class="brand-text">
                <div class="brand-name">DukaMkononi</div>
                <div class="brand-sub" data-i18n="msimamizi_mipangilio.brand_sub">Mipangilio ya Msimamizi</div>
            </div>
        </div>

        <div class="side-label" data-i18n="msimamizi_mipangilio.section_label">Sehemu</div>
        <nav class="section-nav" aria-label="Sehemu za mipangilio" data-i18n="msimamizi_mipangilio.aria_settings_sections" data-i18n-attr="aria-label">
            <button class="section-btn active" data-section="user" type="button">
                <span class="nav-ico">👤</span><span data-i18n="msimamizi_mipangilio.nav_user">Taarifa Zako</span>
            </button>
            <button class="section-btn" data-section="business" type="button">
                <span class="nav-ico">🏪</span><span data-i18n="msimamizi_mipangilio.nav_business">Biashara na Matawi</span>
            </button>
            <button class="section-btn" data-section="other" type="button">
                <span class="nav-ico">⚙️</span><span data-i18n="msimamizi_mipangilio.nav_other">Mipangilio Mingine</span>
            </button>
        </nav>

        <div class="side-spacer"></div>

        <a class="back-link" href="/msimamizi">
            <span aria-hidden="true">←</span><span data-i18n="msimamizi_mipangilio.back_dashboard">Rudi Dashibodi</span>
        </a>
    </aside>

    <main class="settings-main">
        <div class="page-head">
            <h1 class="page-title" data-i18n="msimamizi_mipangilio.page_title">Mipangilio</h1>
            <p class="page-sub" data-i18n="msimamizi_mipangilio.page_sub">Simamia taarifa zako, biashara yako, matawi, na huduma za hiari mahali pamoja.</p>
        </div>

        <!-- ============ USER ============ -->
        <section class="section active" id="section-user" aria-labelledby="user-heading">
            <form class="card" id="userForm" autocomplete="off">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="user-heading" data-i18n="msimamizi_mipangilio.user_title">Taarifa Zako</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.user_desc">Taarifa hizi zinahusiana na akaunti yako binafsi. Jukumu, hali ya akaunti na biashara uliyonayo haziwezi kubadilishwa hapa.</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field full">
                        <label for="userEmail" data-i18n="msimamizi_mipangilio.label_email">Barua pepe</label>
                        <input id="userEmail" type="email" readonly aria-describedby="emailHint">
                        <div class="hint" id="emailHint" data-i18n="msimamizi_mipangilio.hint_email">Barua pepe ni kitambulisho chako cha kuingia; haiwezi kubadilishwa hapa.</div>
                    </div>
                    <div class="field">
                        <label for="userFullName" data-i18n="msimamizi_mipangilio.label_full_name">Jina kamili</label>
                        <input id="userFullName" type="text" maxlength="120" required>
                    </div>
                    <div class="field">
                        <label for="userPhone" data-i18n="msimamizi_mipangilio.label_phone">Namba ya simu</label>
                        <input id="userPhone" type="tel" maxlength="30" inputmode="tel" placeholder="Mfano: 0757 000 000" data-i18n="msimamizi_mipangilio.placeholder_phone_example" data-i18n-attr="placeholder">
                    </div>
                    <div class="field">
                        <label for="userRole" data-i18n="msimamizi_mipangilio.label_role">Jukumu</label>
                        <input id="userRole" type="text" readonly>
                    </div>
                    <div class="field">
                        <label for="userBranch" data-i18n="msimamizi_mipangilio.label_branch">Tawi</label>
                        <input id="userBranch" type="text" readonly>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" id="saveUserBtn" type="submit" data-i18n="msimamizi_mipangilio.btn_save_user">Hifadhi Taarifa Zako</button>
                </div>
            </form>

            <form class="card" id="passwordForm" autocomplete="off">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" data-i18n="msimamizi_mipangilio.password_title">Badilisha Nenosiri</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.password_desc">Nenosiri la sasa linathibitishwa kabla ya nenosiri jipya kuhifadhiwa, kwa mfumo ule ule wa usalama unaotumika kwenye dashibodi.</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field full">
                        <label for="currentPassword" data-i18n="msimamizi_mipangilio.current_password">Nenosiri la sasa</label>
                        <input id="currentPassword" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="field">
                        <label for="newPassword" data-i18n="msimamizi_mipangilio.new_password">Nenosiri jipya</label>
                        <input id="newPassword" type="password" autocomplete="new-password" minlength="6" required>
                    </div>
                    <div class="field">
                        <label for="confirmPassword" data-i18n="msimamizi_mipangilio.confirm_password">Thibitisha nenosiri jipya</label>
                        <input id="confirmPassword" type="password" autocomplete="new-password" minlength="6" required>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" id="savePasswordBtn" type="submit" data-i18n="msimamizi_mipangilio.btn_change_password">Badilisha Nenosiri</button>
                </div>
            </form>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" data-i18n="msimamizi_mipangilio.preferences_title">Mapendeleo Yako</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.preferences_desc">Lugha na mandhari ni mapendeleo yako binafsi, si ya biashara. Yanatumia mifumo iliyopo ya lugha na mandhari.</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="languageSelect" data-i18n="msimamizi_mipangilio.label_language">Lugha</label>
                        <select id="languageSelect"></select>
                    </div>
                    <div class="field">
                        <label for="themeSelect" data-i18n="msimamizi_mipangilio.label_theme">Mandhari</label>
                        <select id="themeSelect"></select>
                        <div class="hint" data-i18n="msimamizi_mipangilio.hint_theme">Mandhari hii inatumika kwenye kifaa hiki na huhifadhiwa kwenye akaunti yako.</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- ============ BUSINESS ============ -->
        <section class="section" id="section-business" aria-labelledby="business-heading">
            <form class="card" id="businessForm" autocomplete="off">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="business-heading" data-i18n="msimamizi_mipangilio.business_title">Taarifa za Biashara</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.business_desc">Mabadiliko hapa hayabadili kitambulisho cha biashara. Wauzaji, bidhaa, mauzo, ripoti na matawi yote yanabaki wameunganishwa na biashara hii.</div>
                    </div>
                </div>

                <div class="grid">
                    <div class="field">
                        <label for="businessName" data-i18n="msimamizi_mipangilio.label_business_name">Jina la biashara</label>
                        <input id="businessName" type="text" maxlength="150" required>
                    </div>
                    <div class="field">
                        <label for="businessType" data-i18n="msimamizi_mipangilio.label_business_type">Aina ya biashara</label>
                        <input id="businessType" type="text" maxlength="100" placeholder="Mfano: Duka la jumla" data-i18n="msimamizi_mipangilio.placeholder_business_type_example" data-i18n-attr="placeholder">
                    </div>
                    <div class="field full">
                        <label for="businessLocation" data-i18n="msimamizi_mipangilio.label_business_location">Eneo</label>
                        <input id="businessLocation" type="text" maxlength="255" placeholder="Mfano: Kinondoni, Dar es Salaam" data-i18n="msimamizi_mipangilio.placeholder_location_example" data-i18n-attr="placeholder">
                    </div>
                    <div class="field full">
                        <label for="businessDescription" data-i18n="msimamizi_mipangilio.label_business_description">Maelezo</label>
                        <textarea id="businessDescription" maxlength="1000" placeholder="Maelezo mafupi yanayoonekana kwa wateja na wauzaji." data-i18n="msimamizi_mipangilio.placeholder_business_description" data-i18n-attr="placeholder"></textarea>
                    </div>
                    <div class="field full">
                        <label for="businessLogo" data-i18n="msimamizi_mipangilio.label_business_logo">URL ya nembo</label>
                        <input id="businessLogo" type="text" maxlength="2048" placeholder="https://...">
                        <div class="hint" data-i18n="msimamizi_mipangilio.hint_logo">Bandika kiungo cha picha ya nembo. Acha wazi kama huna nembo.</div>
                    </div>
                    <div class="field">
                        <label for="businessLatitude" data-i18n="msimamizi_mipangilio.label_latitude">Latitudo (hiari)</label>
                        <input id="businessLatitude" type="number" step="any" min="-90" max="90">
                    </div>
                    <div class="field">
                        <label for="businessLongitude" data-i18n="msimamizi_mipangilio.label_longitude">Longitudo (hiari)</label>
                        <input id="businessLongitude" type="number" step="any" min="-180" max="180">
                    </div>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" id="saveBusinessBtn" type="submit" data-i18n="msimamizi_mipangilio.btn_save_business">Hifadhi Taarifa za Biashara</button>
                </div>
            </form>

            <div class="card" id="branchCard">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" data-i18n="msimamizi_mipangilio.branches_title">Matawi</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.branches_desc">Ongeza au hariri matawi ya biashara hii. Kuzima tawi hakuondoi wauzaji, bidhaa, mauzo wala ripoti; unaweza kulirudisha baadaye.</div>
                    </div>
                    <button class="btn btn-primary" id="addBranchBtn" type="button" data-i18n="msimamizi_mipangilio.btn_add_branch">+ Ongeza Tawi</button>
                </div>

                <div class="branch-list" id="branchList"></div>
                <div class="empty" id="branchEmpty" style="display:none;" data-i18n="msimamizi_mipangilio.branches_empty">Hakuna matawi bado.</div>

                <div class="hint" id="branchStructureNote" style="display:none;margin-top:14px;" data-i18n="msimamizi_mipangilio.branch_structure_note">
                    Biashara hii inafanya kazi kwenye eneo moja. Usimamizi wa matawi unapatikana kwa biashara zilizosajiliwa na matawi.
                </div>
            </div>
        </section>

        <!-- ============ OTHER ============ -->
        <section class="section" id="section-other" aria-labelledby="other-heading">
            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" id="other-heading" data-i18n="msimamizi_mipangilio.optional_services_title">Huduma za Hiari</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.optional_services_desc">Washa au zima huduma kwa biashara yako yote. Kipengele kilichozimwa hakionyeshwi kwa wauzaji na maombi yake yanakataliwa na seva. Data iliyopo haifutwi kamwe.</div>
                    </div>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_fridge_title">Unataka kutumia kipengele cha Friji/Freezer?</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_fridge_desc">Kikwashwa, wauzaji wanaweza kusimamia stock iliyo ndani ya friji na kuhamisha stock kati ya friji na nje. Kikizimwa, kichupo cha Friji hufichwa na stock ya ndani haitumiki kwenye mauzo; stock, historia na tahadhari zote zinabaki salama.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="fridge_enabled" aria-label="Washa kipengele cha Friji" data-i18n="msimamizi_mipangilio.aria_fridge_feature" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_scanner_title">Unataka kutumia kipengele cha Scanner?</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_scanner_desc">Huwasha uwanja wa barcode kwa msimamizi kupokea stock na kwa muuzaji kuchagua bidhaa wakati wa kuuza au kuhamisha stock. Scanner haiongezi stock yenyewe; msimamizi pekee anayeruhusiwa kupokea stock.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="scanner_enabled" aria-label="Washa kipengele cha Scanner" data-i18n="msimamizi_mipangilio.aria_scanner_feature" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_services_title">Unataka kutumia Huduma (Services)?</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_services_desc">Kikwashwa, unaweza kuunda huduma na wauzaji wanaweza kuziuza. Kikizimwa, uundaji wa huduma mpya na mauzo ya huduma huzuiwa, lakini huduma za zamani na ripoti zake zinabaki.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="services_enabled" aria-label="Washa kipengele cha Huduma" data-i18n="msimamizi_mipangilio.aria_services_feature" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_product_mgmt_title">Muuzaji anaweza kusimamia bidhaa (stock)?</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_product_mgmt_desc">Kikwashwa, muuzaji anaweza kufungua ukurasa wa Bidhaa Mpya, kusajili bidhaa, kupokea stock na kubadilisha bei/stock. Kikizimwa (chaguo-msingi), ni msimamizi pekee anayeweza kusimamia bidhaa; muuzaji anauza stock iliyopo tu.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="product_management_enabled" aria-label="Muuzaji anaweza kusimamia bidhaa" data-i18n="msimamizi_mipangilio.aria_product_mgmt_feature" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>
            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" data-i18n="msimamizi_mipangilio.stock_alerts_title">Tahadhari za Stock</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.stock_alerts_desc">Chagua aina ya tahadhari unazotaka kupokea. Kuzima tahadhari hakuachi kufuatilia stock; kunazuia tu arifa mpya za aina hiyo.</div>
                    </div>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_low_stock_title">Stock ndogo ya biashara</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_low_stock_desc">Arifa wakati stock ya nje ya friji au stock ya biashara inafikia kiwango cha chini. Ufuatiliaji unaendelea hata ikizimwa.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="notify_low_stock" aria-label="Washa tahadhari za stock ndogo" data-i18n="msimamizi_mipangilio.aria_low_stock_alert" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>

                <div class="toggle-row">
                    <div class="toggle-copy">
                        <div class="toggle-title" data-i18n="msimamizi_mipangilio.toggle_fridge_stock_title">Stock ndogo ndani ya friji</div>
                        <div class="toggle-desc" data-i18n="msimamizi_mipangilio.toggle_fridge_stock_desc">Arifa wakati stock iliyo ndani ya friji inafikia kiwango chake cha chini.</div>
                    </div>
                    <label class="switch">
                        <input type="checkbox" data-feature="notify_fridge_stock" aria-label="Washa tahadhari za stock ya friji" data-i18n="msimamizi_mipangilio.aria_fridge_stock_alert" data-i18n-attr="aria-label">
                        <span class="slider"></span>
                    </label>
                </div>

            </div>

            <div class="card">
                <div class="card-head">
                    <div>
                        <h2 class="card-title" data-i18n="msimamizi_mipangilio.business_structure_title">Muundo wa Biashara</h2>
                        <div class="card-desc" data-i18n="msimamizi_mipangilio.business_structure_desc">Chagua kama biashara inafanya kazi kwenye eneo moja au kupitia matawi. Mabadiliko yanahamisha records zilizopo; hazifutwi.</div>
                    </div>
                    <span class="badge" id="structureBadge">—</span>
                </div>

                <div class="grid">
                    <div class="field full">
                        <label data-i18n="msimamizi_mipangilio.structure_current">Hali ya sasa</label>
                        <div class="pref-value" id="structureValue">—</div>
                    </div>
                </div>

                <div class="actions">
                    <button class="btn btn-primary" id="toBranchfulBtn" type="button" style="display:none;" data-i18n="msimamizi_mipangilio.btn_to_branchful">Badilisha kuwa biashara ya matawi</button>
                    <button class="btn btn-danger" id="toBranchlessBtn" type="button" style="display:none;" data-i18n="msimamizi_mipangilio.btn_to_branchless">Badilisha kuwa biashara ya eneo moja</button>
                </div>
            </div>
        </section>
    </main>
</div>

<!-- Structure conversion modals -->
<div class="modal-overlay" id="toBranchfulModal" role="dialog" aria-modal="true" aria-labelledby="toBranchfulTitle">
    <div class="modal">
        <div class="modal-title" id="toBranchfulTitle" data-i18n="msimamizi_mipangilio.btn_to_branchful">Badilisha kuwa biashara ya matawi</div>
        <p class="hint" style="margin-bottom:14px;" data-i18n="msimamizi_mipangilio.modal_branchful_desc">Biashara yako itaanza kufanya kazi kupitia matawi. Unahitaji kuandikisha tawi la kwanza ili kuendelea. Bidhaa, mauzo, huduma na ripoti zilizopo zitahamishiwa tawini hili.</p>
        <form id="toBranchfulForm" autocomplete="off">
            <div class="grid">
                <div class="field full">
                    <label for="firstBranchName" data-i18n="msimamizi_mipangilio.label_first_branch_name">Jina la tawi la kwanza</label>
                    <input id="firstBranchName" type="text" maxlength="120" required minlength="2" placeholder="Mfano: Tawi Kuu" data-i18n="msimamizi_mipangilio.placeholder_first_branch_name" data-i18n-attr="placeholder">
                </div>
                <div class="field full">
                    <label for="firstBranchLocation" data-i18n="msimamizi_mipangilio.label_first_branch_location">Eneo la tawi</label>
                    <input id="firstBranchLocation" type="text" maxlength="255" placeholder="Mfano: Kinondoni, Dar es Salaam" data-i18n="msimamizi_mipangilio.placeholder_location_example" data-i18n-attr="placeholder">
                </div>
                <div class="field">
                    <label for="firstBranchLatitude" data-i18n="msimamizi_mipangilio.label_latitude">Latitudo (hiari)</label>
                    <input id="firstBranchLatitude" type="number" step="any" min="-90" max="90">
                </div>
                <div class="field">
                    <label for="firstBranchLongitude" data-i18n="msimamizi_mipangilio.label_longitude">Longitudo (hiari)</label>
                    <input id="firstBranchLongitude" type="number" step="any" min="-90" max="90">
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="confirmToBranchful" type="submit" data-i18n="msimamizi_mipangilio.btn_confirm_continue">Thibitisha na uendelee</button>
                <button class="btn btn-ghost" type="button" data-close-structure="toBranchfulModal" data-i18n="msimamizi_mipangilio.btn_cancel">Ghairi</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="toBranchlessModal" role="dialog" aria-modal="true" aria-labelledby="toBranchlessTitle">
    <div class="modal">
        <div class="modal-title" id="toBranchlessTitle" data-i18n="msimamizi_mipangilio.btn_to_branchless">Badilisha kuwa biashara ya eneo moja</div>
        <p class="hint" style="margin-bottom:14px;" data-i18n="msimamizi_mipangilio.modal_branchless_desc">Biashara itaacha kufanya kazi kwa matawi na kuunganishwa kuwa biashara moja. Historia ya mauzo, malipo, bidhaa, stock na ripoti inabaki. Bidhaa yenye jina/SKU sawa kwenye matawi zaidi ya moja itazuia mabadiliko haya hadi itakaposahihishwa.</p>
        <form id="toBranchlessForm" autocomplete="off">
            <div class="grid">
                <div class="field full">
                    <label for="unifiedBusinessName" data-i18n="msimamizi_mipangilio.label_unified_business_name">Jina la biashara</label>
                    <input id="unifiedBusinessName" type="text" maxlength="150" required minlength="2">
                </div>
                <div class="field full">
                    <label for="unifiedBusinessLocation" data-i18n="msimamizi_mipangilio.label_unified_business_location">Eneo la biashara</label>
                    <input id="unifiedBusinessLocation" type="text" maxlength="255">
                </div>
                <div class="field full">
                    <label for="unifiedBusinessDescription" data-i18n="msimamizi_mipangilio.label_unified_business_description">Maelezo ya biashara</label>
                    <textarea id="unifiedBusinessDescription" maxlength="1000"></textarea>
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-danger" id="confirmToBranchless" type="submit" data-i18n="msimamizi_mipangilio.btn_confirm_changes">Thibitisha mabadiliko</button>
                <button class="btn btn-ghost" type="button" data-close-structure="toBranchlessModal" data-i18n="msimamizi_mipangilio.btn_cancel">Ghairi</button>
            </div>
        </form>
    </div>
</div>

<!-- Branch modal -->
<div class="modal-overlay" id="branchModal" role="dialog" aria-modal="true" aria-labelledby="branchModalTitle">
    <div class="modal">
        <div class="modal-title" id="branchModalTitle" data-i18n="msimamizi_mipangilio.modal_branch_title">Ongeza Tawi</div>
        <form id="branchForm" autocomplete="off">
            <input type="hidden" id="branchId">
            <div class="grid">
                <div class="field full">
                    <label for="branchName" data-i18n="msimamizi_mipangilio.label_branch_name">Jina la tawi</label>
                    <input id="branchName" type="text" maxlength="120" required placeholder="Mfano: Tawi la Mlimani" data-i18n="msimamizi_mipangilio.placeholder_branch_name_example" data-i18n-attr="placeholder">
                </div>
                <div class="field full">
                    <label for="branchLocation" data-i18n="msimamizi_mipangilio.label_branch_location">Eneo</label>
                    <input id="branchLocation" type="text" maxlength="255" placeholder="Mfano: Mlimani City" data-i18n="msimamizi_mipangilio.placeholder_branch_location_example" data-i18n-attr="placeholder">
                </div>
                <div class="field">
                    <label for="branchLatitude" data-i18n="msimamizi_mipangilio.label_latitude">Latitudo (hiari)</label>
                    <input id="branchLatitude" type="number" step="any" min="-90" max="90">
                </div>
                <div class="field">
                    <label for="branchLongitude" data-i18n="msimamizi_mipangilio.label_longitude">Longitudo (hiari)</label>
                    <input id="branchLongitude" type="number" step="any" min="-180" max="180">
                </div>
            </div>
            <div class="actions">
                <button class="btn btn-primary" id="saveBranchBtn" type="submit" data-i18n="msimamizi_mipangilio.btn_save_branch">Hifadhi Tawi</button>
                <button class="btn btn-ghost" id="cancelBranchBtn" type="button" data-i18n="msimamizi_mipangilio.btn_cancel">Ghairi</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

@include('partials.dm-theme-widget', ['dmThemePlacement' => 'bottom'])

<script>
    (function () {
        'use strict';

        const API = '';
        const token = localStorage.getItem('userToken');

        if (!token) {
            window.location.href = '/login?role=msimamizi';
            return;
        }

        const state = {
            data: null,
            settings: {},
            branches: [],
            themeNames: {
                'white-professional': 'White Professional',
                'royal-champagne-cream': 'Royal Champagne Cream',
                'midnight-royal-gold': 'Midnight Royal Gold',
                'emerald-bazaar': 'Emerald Bazaar',
                'oceanic-sapphire': 'Oceanic Sapphire'
            }
        };

        const $ = (id) => document.getElementById(id);

        function tr(key, params, fallback) {
            try {
                if (window.DM && typeof window.DM.t === 'function') {
                    const v = params ? window.DM.t(key, params) : window.DM.t(key);
                    if (v && v !== key) return v;
                }
            } catch (e) {}
            let value = (fallback != null) ? fallback : key;
            if (params) {
                Object.keys(params).forEach(function (name) {
                    value = String(value).split('{' + name + '}').join(params[name] == null ? '' : params[name]);
                });
            }
            return value;
        }

        function authHeaders(json) {
            const headers = { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token };
            if (json) headers['Content-Type'] = 'application/json';
            return headers;
        }

        async function api(path, options) {
            const opts = Object.assign({ headers: authHeaders(true) }, options || {});
            const response = await fetch(API + path, opts);
            let body = null;
            try { body = await response.json(); } catch (e) { body = null; }

            if (response.status === 401) {
                localStorage.removeItem('userToken');
                window.location.href = '/login?role=msimamizi';
                throw new Error('Session expired');
            }

            if (!response.ok) {
                const error = new Error((body && (body.error || body.message)) || ('HTTP ' + response.status));
                error.body = body;
                error.status = response.status;
                throw error;
            }

            return body || {};
        }

        let toastTimer = null;
        function toast(message, isError) {
            const el = $('toast');
            el.textContent = message;
            el.className = 'toast show' + (isError ? ' error' : '');
            clearTimeout(toastTimer);
            toastTimer = setTimeout(() => { el.className = 'toast'; }, 4200);
        }

        function setBusy(button, busy, label) {
            if (!button) return;
            if (busy) {
                button.dataset.label = button.textContent;
                button.disabled = true;
                button.textContent = label || tr('msimamizi_mipangilio.busy_saving', null, 'Inahifadhi...');
            } else {
                button.disabled = false;
                button.textContent = button.dataset.label || button.textContent;
            }
        }

        function showSection(name) {
            document.querySelectorAll('.section').forEach((el) => {
                el.classList.toggle('active', el.id === 'section-' + name);
            });
            document.querySelectorAll('.section-btn').forEach((el) => {
                el.classList.toggle('active', el.dataset.section === name);
            });
            try { history.replaceState(null, '', '#' + name); } catch (e) {}
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        document.querySelectorAll('.section-btn').forEach((btn) => {
            btn.addEventListener('click', () => showSection(btn.dataset.section));
        });

        function applySettings(settings) {
            state.settings = settings || {};
            document.querySelectorAll('[data-feature]').forEach((input) => {
                const key = input.dataset.feature;
                if (Object.prototype.hasOwnProperty.call(state.settings, key)) {
                    input.checked = !!state.settings[key];
                }
            });
        }

        function populate(data) {
            state.data = data;
            state.branches = Array.isArray(data.branches) ? data.branches : [];

            const user = data.user || {};
            const business = data.business || {};

            $('userEmail').value = user.email || '';
            $('userFullName').value = user.full_name || '';
            $('userPhone').value = user.phone || '';
            $('userRole').value = user.role === 'admin' ? tr('msimamizi_mipangilio.role_admin', null, 'Msimamizi') : (user.role || '');
            $('userBranch').value = user.branch_name || (data.business && data.business.business_structure === 'branch' ? tr('msimamizi_mipangilio.branch_all', null, 'Matawi yote') : tr('msimamizi_mipangilio.branch_na', null, 'Haifai'));

            $('businessName').value = business.business_name || '';
            $('businessType').value = business.business_type || '';
            $('businessLocation').value = business.business_location || '';
            $('businessDescription').value = business.business_description || '';
            $('businessLogo').value = business.business_logo_url || '';
            $('businessLatitude').value = business.business_latitude != null ? business.business_latitude : '';
            $('businessLongitude').value = business.business_longitude != null ? business.business_longitude : '';

            const structure = business.business_structure || 'non_branch';
            const isBranchBusiness = structure === 'branch';
            $('branchCard').style.display = isBranchBusiness ? '' : 'none';
            $('branchStructureNote').style.display = isBranchBusiness ? 'none' : '';

            $('structureValue').textContent = isBranchBusiness ? tr('msimamizi_mipangilio.structure_branchful') : tr('msimamizi_mipangilio.structure_branchless');
            $('structureBadge').textContent = isBranchBusiness ? tr('msimamizi_mipangilio.badge_branches') : tr('msimamizi_mipangilio.badge_single');
            $('toBranchfulBtn').style.display = isBranchBusiness ? 'none' : '';
            $('toBranchlessBtn').style.display = isBranchBusiness ? '' : 'none';

            // Pre-fill the unified identity form with what is already stored.
            $('unifiedBusinessName').value = business.business_name || '';
            $('unifiedBusinessLocation').value = business.business_location || '';
            $('unifiedBusinessDescription').value = business.business_description || '';

            const options = data.options || {};
            const langSelect = $('languageSelect');
            langSelect.innerHTML = '';
            const names = { sw: 'Kiswahili', en: 'English', fr: 'Français', hi: 'हिन्दी', es: 'Español', ur: 'اردو', de: 'Deutsch', zh: '中文' };
            (options.languages || []).forEach((code) => {
                const opt = document.createElement('option');
                opt.value = code;
                opt.textContent = names[code] || code;
                if (code === (user.language || 'sw')) opt.selected = true;
                langSelect.appendChild(opt);
            });

            const themeSelect = $('themeSelect');
            themeSelect.innerHTML = '';
            const currentTheme = user.preferred_theme || 'white-professional';
            (options.themes || []).forEach((id) => {
                const opt = document.createElement('option');
                opt.value = id;
                opt.textContent = state.themeNames[id] || id;
                if (id === currentTheme) opt.selected = true;
                themeSelect.appendChild(opt);
            });

            applySettings(data.settings || data.features);
            renderBranches();
        }

        function renderBranches() {
            const list = $('branchList');
            list.innerHTML = '';

            if (!state.branches.length) {
                $('branchEmpty').style.display = '';
                return;
            }
            $('branchEmpty').style.display = 'none';

            state.branches.forEach((branch) => {
                const item = document.createElement('div');
                item.className = 'branch-item';

                const info = document.createElement('div');
                const name = document.createElement('div');
                name.className = 'branch-name';
                const dot = document.createElement('span');
                dot.className = 'status-dot' + (branch.status === 'inactive' ? ' off' : '');
                name.appendChild(dot);
                name.appendChild(document.createTextNode(branch.branch_name || tr('msimamizi_mipangilio.label_branch', null, 'Tawi')));
                if (branch.status === 'inactive') {
                    const badge = document.createElement('span');
                    badge.className = 'badge';
                    badge.style.background = '#f1f5f9';
                    badge.style.color = '#64748b';
                    badge.textContent = tr('msimamizi_mipangilio.branch_inactive');
                    name.appendChild(badge);
                }

                const meta = document.createElement('div');
                meta.className = 'branch-meta';
                meta.textContent = branch.location || tr('msimamizi_mipangilio.branch_no_location');

                info.appendChild(name);
                info.appendChild(meta);

                const actions = document.createElement('div');
                actions.className = 'branch-actions';

                const edit = document.createElement('button');
                edit.type = 'button';
                edit.className = 'btn btn-ghost';
                edit.textContent = tr('msimamizi_mipangilio.branch_edit');
                edit.addEventListener('click', () => openBranchModal(branch));

                actions.appendChild(edit);

                if (branch.status !== 'inactive') {
                    const off = document.createElement('button');
                    off.type = 'button';
                    off.className = 'btn btn-danger';
                    off.textContent = tr('msimamizi_mipangilio.branch_deactivate');
                    off.addEventListener('click', () => deactivateBranch(branch));
                    actions.appendChild(off);
                } else {
                    const on = document.createElement('button');
                    on.type = 'button';
                    on.className = 'btn btn-ghost';
                    on.textContent = tr('msimamizi_mipangilio.branch_reactivate');
                    on.addEventListener('click', () => reactivateBranch(branch));
                    actions.appendChild(on);
                }

                item.appendChild(info);
                item.appendChild(actions);
                list.appendChild(item);
            });
        }

        async function load() {
            try {
                setBusy($('saveUserBtn'), true, tr('msimamizi_mipangilio.busy_loading', null, 'Inapakia...'));
                const data = await api('/api/settings');
                populate(data);
                toast(tr('msimamizi_mipangilio.toast_loaded'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_load_error'), true);
            } finally {
                setBusy($('saveUserBtn'), false);
            }
        }

        $('userForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = $('saveUserBtn');
            setBusy(button, true);
            try {
                const body = {
                    full_name: $('userFullName').value.trim(),
                    phone: $('userPhone').value.trim()
                };
                const result = await api('/api/user/profile', { method: 'PUT', body: JSON.stringify(body) });
                if (result.user) {
                    const stored = JSON.parse(localStorage.getItem('userData') || '{}');
                    stored.full_name = result.user.full_name;
                    stored.phone = result.user.phone;
                    localStorage.setItem('userData', JSON.stringify(stored));
                }
                toast(tr('msimamizi_mipangilio.toast_user_saved'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            } finally {
                setBusy(button, false);
            }
        });

        $('passwordForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = $('savePasswordBtn');

            if ($('newPassword').value !== $('confirmPassword').value) {
                toast(tr('msimamizi_mipangilio.toast_password_mismatch'), true);
                return;
            }

            setBusy(button, true);
            try {
                await api('/api/user/password', {
                    method: 'PUT',
                    body: JSON.stringify({
                        current_password: $('currentPassword').value,
                        new_password: $('newPassword').value,
                        confirm_password: $('confirmPassword').value
                    })
                });

                $('passwordForm').reset();
                toast(tr('msimamizi_mipangilio.toast_password_changed'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            } finally {
                setBusy(button, false);
            }
        });

        $('themeSelect').addEventListener('change', async (event) => {
            const theme = event.target.value;

            try {
                await api('/api/user/theme', { method: 'PUT', body: JSON.stringify({ theme }) });

                // Apply with the same values/dm-theme.js uses, so the change is
                // visible immediately and survives this page without a reload.
                document.documentElement.setAttribute('data-theme', theme);
                try { localStorage.setItem('dukamkononi_theme', theme); } catch (e) {}

                const themeWidget = window.DMTheme;
                if (themeWidget && typeof themeWidget.apply === 'function') {
                    themeWidget.apply(theme);
                }

                toast(tr('msimamizi_mipangilio.toast_theme_saved'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            }
        });

        $('businessForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = $('saveBusinessBtn');
            setBusy(button, true);
            try {
                const body = {
                    business_name: $('businessName').value.trim(),
                    business_type: $('businessType').value.trim(),
                    business_location: $('businessLocation').value.trim(),
                    business_description: $('businessDescription').value.trim(),
                    business_logo_url: $('businessLogo').value.trim()
                };
                const lat = $('businessLatitude').value;
                const lng = $('businessLongitude').value;
                if (lat !== '') body.business_latitude = lat;
                if (lng !== '') body.business_longitude = lng;

                await api('/api/user/profile', { method: 'PUT', body: JSON.stringify(body) });

                const stored = JSON.parse(localStorage.getItem('userData') || '{}');
                stored.business_name = body.business_name;
                stored.businessName = body.business_name;
                stored.business_location = body.business_location;
                localStorage.setItem('userData', JSON.stringify(stored));

                toast(tr('msimamizi_mipangilio.toast_business_saved'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            } finally {
                setBusy(button, false);
            }
        });

        document.querySelectorAll('[data-feature]').forEach((input) => {
            input.addEventListener('change', async () => {
                const key = input.dataset.feature;
                const value = input.checked;

                if (!value && key === 'fridge_enabled') {
                    const ok = window.confirm(tr('msimamizi_mipangilio.confirm_disable_fridge'));
                    if (!ok) {
                        input.checked = true;
                        return;
                    }
                }

                input.disabled = true;
                try {
                    const payload = {};
                    payload[key] = value;
                    const result = await api('/api/settings/features', { method: 'PUT', body: JSON.stringify(payload) });
                    applySettings(result.settings || result.features);
                    toast(tr('msimamizi_mipangilio.toast_feature_saved'));
                } catch (error) {
                    input.checked = !value;
                    toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
                } finally {
                    input.disabled = false;
                }
            });
        });

        $('languageSelect').addEventListener('change', async (event) => {
            const language = event.target.value;
            try {
                await api('/api/user/language', { method: 'PUT', body: JSON.stringify({ language }) });
                const stored = JSON.parse(localStorage.getItem('userData') || '{}');
                stored.language = language;
                localStorage.setItem('userData', JSON.stringify(stored));
                toast(tr('msimamizi_mipangilio.toast_language_saved'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            }
        });

        function applyConvertedSession(result) {
            if (!result || !result.token) return false;

            localStorage.setItem('userToken', result.token);
            try {
                const stored = JSON.parse(localStorage.getItem('userData') || '{}');
                stored.business_structure = result.business_structure;
                if (result.business_name) {
                    stored.business_name = result.business_name;
                    stored.businessName = result.business_name;
                }
                localStorage.setItem('userData', JSON.stringify(stored));
            } catch (e) {}

            return true;
        }

        $('toBranchfulBtn').addEventListener('click', () => {
            $('firstBranchName').value = ($('businessName').value || '') + ' - ' + tr('msimamizi_mipangilio.branch_main', null, 'Tawi Kuu');
            $('firstBranchLocation').value = $('businessLocation').value || '';
            $('toBranchfulModal').classList.add('open');
            setTimeout(() => $('firstBranchName').focus(), 30);
        });

        $('toBranchlessBtn').addEventListener('click', () => {
            $('toBranchlessModal').classList.add('open');
        });

        document.querySelectorAll('[data-close-structure]').forEach((button) => {
            button.addEventListener('click', () => {
                $(button.dataset.closeStructure).classList.remove('open');
            });
        });

        $('toBranchfulForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = $('confirmToBranchful');
            setBusy(button, true, tr('msimamizi_mipangilio.busy_converting', null, 'Inabadilisha...'));

            try {
                const payload = {
                    branch_name: $('firstBranchName').value.trim(),
                    location: $('firstBranchLocation').value.trim()
                };
                if ($('firstBranchLatitude').value !== '') payload.latitude = $('firstBranchLatitude').value;
                if ($('firstBranchLongitude').value !== '') payload.longitude = $('firstBranchLongitude').value;

                const result = await api('/api/settings/business-structure/branchful', {
                    method: 'POST',
                    body: JSON.stringify(payload)
                });

                applyConvertedSession(result);
                toast(tr('msimamizi_mipangilio.toast_structure_branchful'));
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.confirm_structure_error'), true);
                setBusy(button, false);
            }
        });

        $('toBranchlessForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const button = $('confirmToBranchless');
            setBusy(button, true, tr('msimamizi_mipangilio.busy_converting', null, 'Inabadilisha...'));

            try {
                const result = await api('/api/settings/business-structure/branchless', {
                    method: 'POST',
                    body: JSON.stringify({
                        business_name: $('unifiedBusinessName').value.trim(),
                        business_location: $('unifiedBusinessLocation').value.trim(),
                        business_description: $('unifiedBusinessDescription').value.trim()
                    })
                });

                applyConvertedSession(result);
                toast(tr('msimamizi_mipangilio.toast_structure_branchless'));
                setTimeout(() => window.location.reload(), 700);
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_branchless_error', null, 'Imeshindikana kuunganisha biashara'), true);
                setBusy(button, false);
            }
        });

        function openBranchModal(branch) {
            $('branchModalTitle').textContent = branch ? (tr('msimamizi_mipangilio.branch_edit') + ' ' + tr('msimamizi_mipangilio.label_branch')) : tr('msimamizi_mipangilio.modal_branch_title');
            $('branchId').value = branch ? branch.id : '';
            $('branchName').value = branch ? (branch.branch_name || '') : '';
            $('branchLocation').value = branch ? (branch.location || '') : '';
            $('branchLatitude').value = branch && branch.latitude != null ? branch.latitude : '';
            $('branchLongitude').value = branch && branch.longitude != null ? branch.longitude : '';
            $('branchModal').classList.add('open');
            setTimeout(() => $('branchName').focus(), 30);
        }

        function closeBranchModal() {
            $('branchModal').classList.remove('open');
        }

        $('addBranchBtn').addEventListener('click', () => openBranchModal(null));
        $('cancelBranchBtn').addEventListener('click', closeBranchModal);
        $('branchModal').addEventListener('click', (event) => {
            if (event.target === $('branchModal')) closeBranchModal();
        });

        $('branchForm').addEventListener('submit', async (event) => {
            event.preventDefault();
            const id = $('branchId').value;
            const button = $('saveBranchBtn');
            setBusy(button, true);
            try {
                const body = {
                    branch_name: $('branchName').value.trim(),
                    location: $('branchLocation').value.trim()
                };
                if ($('branchLatitude').value !== '') body.latitude = $('branchLatitude').value;
                if ($('branchLongitude').value !== '') body.longitude = $('branchLongitude').value;

                const result = await api(id ? '/api/branches/' + encodeURIComponent(id) : '/api/branches', {
                    method: id ? 'PUT' : 'POST',
                    body: JSON.stringify(body)
                });

                if (result.branch) {
                    const index = state.branches.findIndex((b) => b.id === result.branch.id);
                    if (index >= 0) state.branches[index] = result.branch;
                    else state.branches.push(result.branch);
                    state.branches.sort((a, b) => String(a.branch_name).localeCompare(String(b.branch_name)));
                }

                renderBranches();
                closeBranchModal();
                toast(id ? tr('msimamizi_mipangilio.toast_branch_saved') : tr('msimamizi_mipangilio.toast_branch_added'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_save_error'), true);
            } finally {
                setBusy(button, false);
            }
        });

        async function deactivateBranch(branch) {
            const ok = window.confirm(tr('msimamizi_mipangilio.confirm_deactivate_branch', { name: branch.branch_name }, 'Kuzima tawi "{name}"? Wauzaji wake hawataweza kuingia hadi ulirudishe. Data yote inabaki.'));
            if (!ok) return;

            try {
                const result = await api('/api/branches/' + encodeURIComponent(branch.id), { method: 'DELETE' });
                if (result.branch) {
                    const index = state.branches.findIndex((b) => b.id === branch.id);
                    if (index >= 0) state.branches[index] = result.branch;
                }
                renderBranches();
                toast(tr('msimamizi_mipangilio.toast_branch_deactivated'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_branch_toggle_error'), true);
            }
        }

        async function reactivateBranch(branch) {
            try {
                const result = await api('/api/branches/' + encodeURIComponent(branch.id), {
                    method: 'PUT',
                    body: JSON.stringify({ status: 'active' })
                });
                if (result.branch) {
                    const index = state.branches.findIndex((b) => b.id === branch.id);
                    if (index >= 0) state.branches[index] = result.branch;
                }
                renderBranches();
                toast(tr('msimamizi_mipangilio.toast_branch_restored'));
            } catch (error) {
                toast(error.message || tr('msimamizi_mipangilio.toast_branch_toggle_error'), true);
            }
        }

        const initial = (window.location.hash || '').replace('#', '');
        if (['user', 'business', 'other'].includes(initial)) showSection(initial);

        load();
    })();
</script>
</body>
