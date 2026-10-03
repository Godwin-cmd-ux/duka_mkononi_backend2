@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="home.page_title">Dukamkononi | Karibu Dukani</title>
    <!-- Google Fonts & simple reset -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background-color: #ffffff;
            overflow-x: hidden;
        }

        /* Safe area simulation */
        .safe-area {
            background-color: #ffffff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Scroll container with native feel */
        .scroll-view {
            flex: 1;
            width: 100%;
        }

        .scroll-content {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 30px 20px 40px;
            min-height: 100vh;
        }

        /* Container */
        .container {
            flex: 1;
            width: 100%;
            max-width: 550px;   /* mobile comfort but desktop readable */
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* ---- Header animations (CSS driven but replicates RN Animated) ---- */
        .header-container {
            width: 100%;
            text-align: center;
            margin-bottom: 40px;
            margin-top: 20px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeSlideUp 0.9s cubic-bezier(0.2, 0.9, 0.4, 1.1) forwards;
        }

        .logo-container {
            margin-bottom: 25px;
        }

        .logo-circle {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            box-shadow: 0 10px 20px rgba(52, 152, 219, 0.3);
            transition: transform 0.2s ease;
        }

        .logo-circle svg {
            width: 52px;
            height: 52px;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .header {
            font-size: 32px;
            font-weight: 800;
            color: #2c3e50;
            letter-spacing: -0.3px;
            margin-top: 12px;
        }

        .sub-header {
            font-size: 16px;
            color: #7f8c8d;
            margin-top: 8px;
            font-weight: 500;
        }

        /* Buttons grid */
        .button-container {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
            margin-bottom: 30px;
        }

        .button-wrapper {
            width: 100%;
            max-width: 100%;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeSlideUp 0.5s cubic-bezier(0.2, 0.9, 0.4, 1) forwards;
        }

        /* Stagger children via delay */
        .button-wrapper:nth-child(1) { animation-delay: 0.05s; }
        .button-wrapper:nth-child(2) { animation-delay: 0.15s; }
        .button-wrapper:nth-child(3) { animation-delay: 0.25s; }
        /* WASHA button removed, so only 3 buttons now */

        .action-button {
            width: 100%;
            background-color: #3498db;
            border-radius: 20px;
            padding: 0 20px;
            height: 85px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.1);
            transition: transform 0.08s linear, box-shadow 0.2s;
            border: none;
            text-decoration: none;
        }

        .action-button:active {
            transform: scale(0.97);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .button-content {
            display: flex;
            align-items: center;
            width: 100%;
            gap: 15px;
        }

        .icon-bg {
            width: 50px;
            height: 50px;
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .button-text-group {
            flex: 1;
            text-align: left;
        }

        .button-main-text {
            font-size: 20px;
            font-weight: 800;
            color: white;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .button-desc {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.9);
            margin-top: 4px;
            font-weight: 500;
        }

        .chevron-icon {
            opacity: 0.8;
        }

        /* info card */
        .info-container {
            width: 100%;
            margin-bottom: 30px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeSlideUp 0.6s ease forwards;
            animation-delay: 0.2s;
        }

        .info-card {
            background-color: #f8f9fa;
            border-radius: 24px;
            padding: 25px 20px;
            text-align: center;
            border: 1px solid #eef2f6;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.02);
        }

        .info-icon {
            margin-bottom: 15px;
        }

        .info-title {
            font-size: 20px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .info-text {
            font-size: 15px;
            color: #5d6d7e;
            line-height: 1.5;
        }

        /* Warning card */
        .warning-container {
            width: 100%;
            background-color: #fff9e6;
            border-radius: 24px;
            padding: 20px 22px;
            border-left: 5px solid #f1c40f;
            margin-bottom: 28px;
            box-shadow: 0 6px 14px rgba(0, 0, 0, 0.03);
            opacity: 0;
            transform: translateY(30px);
            animation: fadeSlideUp 0.6s ease forwards;
            animation-delay: 0.3s;
        }

        .warning-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 16px;
            flex-wrap: wrap;
        }

        .warning-icon-circle {
            width: 40px;
            height: 40px;
            background-color: #f39c12;
            border-radius: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .warning-title {
            font-size: 16px;
            font-weight: 800;
            color: #b66d0d;
            letter-spacing: 0.5px;
        }

        .warning-text {
            font-size: 14px;
            color: #7d5d21;
            font-weight: 500;
            margin-bottom: 18px;
            line-height: 1.45;
        }

        .warning-points {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .point-row {
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .point-text {
            font-size: 13px;
            color: #7d5d21;
            line-height: 1.4;
            flex: 1;
        }

        /* Footer */
        .footer {
            width: 100%;
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eef2f5;
            opacity: 0;
            animation: fadeSlideUp 0.5s forwards;
            animation-delay: 0.4s;
        }

        .footer-text {
            font-size: 14px;
            font-weight: 500;
            color: #7f8c8d;
        }
        .footer-sub {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 5px;
        }

        /* ---- Language switcher (top right, mirrors the mobile app home screen) ---- */
        .lang-switcher-row {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-bottom: 10px;
        }
        .lang-switcher {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #f0f4f8;
            border: none;
            cursor: pointer;
            padding: 8px 14px;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            font-family: inherit;
            transition: background 0.2s ease, transform 0.08s ease;
        }
        .lang-switcher:hover { background: #e6edf5; }
        .lang-switcher:active { transform: scale(0.97); }
        .lang-switcher svg { display: block; color: #2c3e50; }
        .lang-switcher-label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0 2px;
            white-space: nowrap;
        }
        .lang-chevron { color: #7f8c8d !important; }

        /* ---- Language selector modal (bottom sheet, mirrors the mobile app) ---- */
        .lang-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: flex-end;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.25s ease, visibility 0.25s ease;
        }
        .lang-modal-overlay.open { opacity: 1; visibility: visible; }
        .lang-modal-sheet {
            width: 100%;
            max-width: 550px;
            background: #ffffff;
            border-top-left-radius: 28px;
            border-top-right-radius: 28px;
            padding: 12px 24px 34px;
            max-height: 75vh;
            display: flex;
            flex-direction: column;
            transform: translateY(100%);
            transition: transform 0.28s cubic-bezier(0.2, 0.9, 0.4, 1);
            box-shadow: 0 -5px 15px rgba(0, 0, 0, 0.1);
        }
        .lang-modal-overlay.open .lang-modal-sheet { transform: translateY(0); }
        .lang-modal-handle {
            width: 40px;
            height: 4px;
            border-radius: 2px;
            background: #e0e0e0;
            margin: 0 auto 16px;
        }
        .lang-modal-header { text-align: center; margin-bottom: 20px; }
        .lang-modal-icon {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            background: #e8f4fd;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            color: #3498db;
        }
        .lang-modal-title {
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 4px;
        }
        .lang-modal-subtitle { font-size: 14px; color: #7f8c8d; }
        .lang-modal-list {
            overflow-y: auto;
            margin-bottom: 20px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .lang-modal-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border-radius: 12px;
            background: #f8f9fa;
            border: 1.5px solid transparent;
            cursor: pointer;
            width: 100%;
            text-align: left;
            font-family: inherit;
        }
        .lang-modal-item.selected { background: #e8f4fd; border-color: #3498db; }
        .lang-modal-item-left { display: flex; align-items: center; }
        .lang-modal-flag { font-size: 30px; margin-right: 14px; line-height: 1; }
        .lang-modal-info { display: flex; flex-direction: column; }
        .lang-modal-name { font-size: 16px; font-weight: 600; color: #2c3e50; }
        .lang-modal-item.selected .lang-modal-name { color: #3498db; }
        .lang-modal-code { font-size: 12px; color: #95a5a6; font-weight: 500; margin-top: 2px; }
        .lang-modal-radio {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 2px solid #ccc;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .lang-modal-item.selected .lang-modal-radio { border-color: #3498db; }
        .lang-modal-radio span {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #3498db;
            display: none;
        }
        .lang-modal-item.selected .lang-modal-radio span { display: block; }
        .lang-modal-actions { display: flex; gap: 12px; }
        .lang-modal-cancel {
            flex: 1;
            height: 50px;
            border: none;
            border-radius: 12px;
            background: #f0f4f8;
            color: #7f8c8d;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            font-family: inherit;
        }
        .lang-modal-apply {
            flex: 1.5;
            height: 50px;
            border: none;
            border-radius: 12px;
            background: #3498db;
            color: #ffffff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            font-family: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 8px rgba(52, 152, 219, 0.3);
        }
        .lang-modal-apply:disabled {
            background: #85c1e9;
            box-shadow: none;
            cursor: default;
        }

        /* animations keyframes */
        @keyframes fadeSlideUp {
            0% {
                opacity: 0;
                transform: translateY(30px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* desktop friendly */
        @media (min-width: 768px) {
            .scroll-content {
                padding: 40px 30px;
            }
            .container {
                max-width: 620px;
            }
            .action-button {
                transition: transform 0.15s, box-shadow 0.2s;
            }
            .action-button:hover {
                transform: scale(1.01);
                box-shadow: 0 12px 24px rgba(0, 0, 0, 0.12);
            }
        }

        /* ensure no overflow */
        .warning-points, .button-content {
            width: 100%;
        }
        svg {
            display: block;
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget', ['dmLangHideButton' => true])
@verbatim
<div class="safe-area">
    <div class="scroll-view">
        <div class="scroll-content">
            <div class="container">

                <!-- Language switcher - top right, mirrors the mobile app home screen -->
                <div class="lang-switcher-row">
                    <button type="button" class="lang-switcher" id="homeLangBtn" aria-haspopup="dialog" aria-expanded="false">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="9"></circle>
                            <path d="M3 12H21"></path>
                            <path d="M12 3C14.6 5.6 14.6 18.4 12 21C9.4 18.4 9.4 5.6 12 3Z"></path>
                        </svg>
                        <span class="lang-switcher-label" id="homeLangLabel">SW</span>
                        <svg class="lang-chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="m6 9 6 6 6-6"></path>
                        </svg>
                    </button>
                </div>

                <!-- Header animated -->
                <div class="header-container">
                    <div class="logo-container">
                        <div class="logo-circle">
                            <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M3 9L12 3L21 9L12 15L3 9Z" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <path d="M5 10.5V16.5L12 21L19 16.5V10.5" stroke="white" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                                <path d="M12 15V21" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                                <path d="M9 7.5L15 11" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                                <circle cx="12" cy="12" r="1.5" fill="white" stroke="white" stroke-width="1"/>
                            </svg>
                        </div>
                    </div>
                    <h1 class="header" data-i18n="home.welcome_title">Karibu DUKANI</h1>
                    <p class="sub-header" data-i18n="home.choose_role">Chagua nafasi yako kuanza</p>
                </div>

                <!-- Buttons section - MTEJA, MUUZAJI, MSIMAMIZI only (WASHA removed) -->
                <div class="button-container">
                    <!-- MTEJA -->
                    <div class="button-wrapper">
                        <div class="action-button" data-role="mteja" style="background: linear-gradient(105deg, #3498db, #2980b9);">
                            <div class="button-content">
                                <div class="icon-bg">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M20 21V19C20 16.8 18.2 15 16 15H8C5.8 15 4 16.8 4 19V21" stroke="white" stroke-width="1.7" stroke-linecap="round"/>
                                        <circle cx="12" cy="7" r="4" stroke="white" stroke-width="1.7"/>
                                        <path d="M17 3.5L19 5.5L22 2.5" stroke="white" stroke-width="1.6" stroke-linecap="round"/>
                                    </svg>
                                </div>
                                <div class="button-text-group">
                                    <div class="button-main-text" data-i18n="home.role_customer">MTEJA</div>
                                    <div class="button-desc" data-i18n="home.desc_customer">Ninaomba huduma au bidhaa</div>
                                </div>
                                <div class="chevron-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 18L15 12L9 6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- MUUZAJI -->
                    <div class="button-wrapper">
                        <div class="action-button" data-role="muuzaji" style="background: linear-gradient(105deg, #2ecc71, #27ae60);">
                            <div class="button-content">
                                <div class="icon-bg">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M6.5 9L9 4H15L17.5 9" stroke="white" stroke-width="1.7" stroke-linecap="round"/>
                                        <path d="M3 9H21V19C21 20.1 20.1 21 19 21H5C3.9 21 3 20.1 3 19V9Z" stroke="white" stroke-width="1.7"/>
                                        <circle cx="9" cy="14" r="2" fill="white"/>
                                        <circle cx="15" cy="14" r="2" fill="white"/>
                                    </svg>
                                </div>
                                <div class="button-text-group">
                                    <div class="button-main-text" data-i18n="home.role_seller">MUUZAJI</div>
                                    <div class="button-desc" data-i18n="home.desc_seller">Ninauzia bidhaa na huduma</div>
                                </div>
                                <div class="chevron-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 18L15 12L9 6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- MSIMAMIZI -->
                    <div class="button-wrapper">
                        <div class="action-button" data-role="msimamizi" style="background: linear-gradient(105deg, #e74c3c, #c0392b);">
                            <div class="button-content">
                                <div class="icon-bg">
                                    <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M12 3L3 9L12 15L21 9L12 3Z" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
                                        <path d="M5 12L12 17.5L19 12" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
                                        <path d="M5 16L12 21.5L19 16" stroke="white" stroke-width="1.7" stroke-linejoin="round"/>
                                    </svg>
                                </div>
                                <div class="button-text-group">
                                    <div class="button-main-text" data-i18n="home.role_admin">MSIMAMIZI</div>
                                    <div class="button-desc" data-i18n="home.desc_admin">Ninafanya usimamizi wa duka</div>
                                </div>
                                <div class="chevron-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M9 18L15 12L9 6" stroke="white" stroke-width="2" stroke-linecap="round"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- WASHA BUTTON REMOVED -->
                </div>

                <!-- Info section -->
                <div class="info-container">
                    <div class="info-card">
                        <div class="info-icon">
                            <svg width="44" height="44" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22Z" stroke="#3498db" stroke-width="1.6"/>
                                <path d="M12 16V12M12 8H12.01" stroke="#3498db" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </div>
                        <h3 class="info-title" data-i18n="home.how_to_start">Jinsi ya Kuanza</h3>
                        <p class="info-text">
                            <span data-i18n="home.step_1">1. Chagua nafasi yako kutoka hapo juu</span><br>
                            <span data-i18n="home.step_2">2. Jisajili au ingia kwenye akaunti yako</span><br>
                            <span data-i18n="home.step_3">3. Anza kutumia huduma zetu</span>
                        </p>
                    </div>
                </div>

                <!-- Security Warning Section identical to mobile -->
                <div class="warning-container">
                    <div class="warning-header">
                        <div class="warning-icon-circle">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 3L3 9L12 15L21 9L12 3Z" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                                <path d="M5 12L12 17.5L19 12" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                                <path d="M5 16L12 21.5L19 16" stroke="white" stroke-width="1.5" stroke-linejoin="round"/>
                                <circle cx="12" cy="12" r="1.5" fill="white"/>
                            </svg>
                        </div>
                        <span class="warning-title" data-i18n="home.security_notice">ANGALIZO LA USALAMA</span>
                    </div>
                    <p class="warning-text" data-i18n="home.security_intro">
                        Tunakusihi kuhakiki biashara kabla hujafanya miamala ili kuepuka matapeli mtandaoni.
                    </p>
                    <div class="warning-points">
                        <div class="point-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="point-text" data-i18n="home.security_point_1">Hakikisha biashara imesajiliwa kikamilifu</span>
                        </div>
                        <div class="point-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="point-text" data-i18n="home.security_point_2">Thibitisha anwani na mawasiliano ya biashara</span>
                        </div>
                        <div class="point-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="point-text" data-i18n="home.security_point_3">Zingatia maoni ya wateja waliopita</span>
                        </div>
                        <div class="point-row">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M20 6L9 17L4 12" stroke="#27ae60" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            <span class="point-text" data-i18n="home.security_point_4">Epuka kutoa malipo bila kuhakiki</span>
                        </div>
                    </div>
                </div>

                <div class="footer">
                    <p class="footer-text" data-i18n="home.copyright">© 2025 Dukani App</p>
                    <p class="footer-sub" data-i18n="home.footer_tagline">Tunaendesha usalama wa juu kwa miamala yako</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Language selector modal (bottom sheet, mirrors the mobile app home screen) -->
<div class="lang-modal-overlay" id="homeLangModal" aria-hidden="true">
    <div class="lang-modal-sheet" role="dialog" aria-modal="true" aria-labelledby="homeLangTitle">
        <div class="lang-modal-handle"></div>
        <div class="lang-modal-header">
            <div class="lang-modal-icon">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="M3 12H21"></path>
                    <path d="M12 3C14.6 5.6 14.6 18.4 12 21C9.4 18.4 9.4 5.6 12 3Z"></path>
                </svg>
            </div>
            <h3 class="lang-modal-title" id="homeLangTitle" data-i18n="home.language_title">Badili Lugha</h3>
            <p class="lang-modal-subtitle" data-i18n="home.choose_role">Chagua nafasi yako kuanza</p>
        </div>
        <div class="lang-modal-list" id="homeLangList" role="radiogroup"></div>
        <div class="lang-modal-actions">
            <button type="button" class="lang-modal-cancel" id="homeLangCancel" data-i18n="home.language_close">Funga</button>
            <button type="button" class="lang-modal-apply" id="homeLangApply">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <circle cx="12" cy="12" r="9"></circle>
                    <path d="m8.5 12.5 2.5 2.5 4.5-5"></path>
                </svg>
                <span data-i18n="home.language_apply">Tuma</span>
            </button>
        </div>
    </div>
</div>

<script>
    // This script replicates the exact navigation and press animation logic
    // without any framework, preserving the RN behavior: 
    // - Press scale animation (visual feedback)
    // - Routing: navigate to login with role parameter

    // Helper function to simulate router.push with params
    function navigateToLogin(role) {
        window.location.href = `login?role=${encodeURIComponent(role)}`;
    }

    // Add button press animation (scale effect identical to react native)
    function animatePress(buttonElement, callback) {
        if (!buttonElement) return;
        buttonElement.style.transform = 'scale(0.96)';
        buttonElement.style.transition = 'transform 0.08s cubic-bezier(0.2, 0.9, 0.4, 1.1)';
        setTimeout(() => {
            buttonElement.style.transform = 'scale(1)';
            setTimeout(() => {
                if (callback) callback();
            }, 50);
        }, 90);
    }

    // attach event listeners to each action button
    const actionButtons = document.querySelectorAll('.action-button');
    actionButtons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const role = btn.getAttribute('data-role');
            btn.style.transition = 'transform 0.08s ease';
            
            // animate and navigate
            animatePress(btn, () => {
                navigateToLogin(role);
            });
        });
        
        // Optional touch feedback for desktop hover
        btn.addEventListener('touchstart', (e) => {
            btn.style.transform = 'scale(0.97)';
        });
        btn.addEventListener('touchend', () => {
            btn.style.transform = 'scale(1)';
        });
        btn.addEventListener('touchcancel', () => {
            btn.style.transform = 'scale(1)';
        });
    });

    console.log('Dukamkononi Web: home screen replicated from HomeScreen.tsx (WASHA button removed)');

    // ---- Language switcher: top-right button + bottom-sheet modal ----
    // Mirrors the mobile app home screen (Duka_mkononi/app/(tabs)/index.tsx).
    // It drives the DM runtime provided by partials.dm-lang-widget, which is
    // included in hidden mode on this page so only this button switches language.
    (function () {
        var btn = document.getElementById('homeLangBtn');
        var overlay = document.getElementById('homeLangModal');
        var list = document.getElementById('homeLangList');
        var label = document.getElementById('homeLangLabel');
        var cancel = document.getElementById('homeLangCancel');
        var apply = document.getElementById('homeLangApply');
        if (!btn || !overlay || !list) return;

        var selected = current();

        function dm() { return window.DM; }

        function current() {
            return (dm() && typeof dm().locale === 'function' && dm().locale()) || 'sw';
        }

        function langs() {
            return (dm() && typeof dm().languages === 'function' && dm().languages()) || [];
        }

        function findLang(code) {
            var all = langs();
            for (var i = 0; i < all.length; i++) {
                if (all[i].code === code) return all[i];
            }
            return null;
        }

        function paintLabel() {
            var lang = findLang(current());
            if (label) label.textContent = lang ? ((lang.flag || '') + ' ' + (lang.name || lang.code)) : current().toUpperCase();
        }

        function buildList() {
            list.innerHTML = '';
            var all = langs();
            if (!all.length) return;
            all.forEach(function (lang) {
                var item = document.createElement('button');
                item.type = 'button';
                item.className = 'lang-modal-item' + (lang.code === selected ? ' selected' : '');
                item.setAttribute('data-code', lang.code);
                item.setAttribute('role', 'radio');
                item.setAttribute('aria-checked', lang.code === selected ? 'true' : 'false');

                var left = document.createElement('span');
                left.className = 'lang-modal-item-left';
                var flag = document.createElement('span');
                flag.className = 'lang-modal-flag';
                flag.textContent = lang.flag || '';
                var info = document.createElement('span');
                info.className = 'lang-modal-info';
                var name = document.createElement('span');
                name.className = 'lang-modal-name';
                name.textContent = lang.name || lang.code;
                var code = document.createElement('span');
                code.className = 'lang-modal-code';
                code.textContent = String(lang.code).toUpperCase();
                info.appendChild(name);
                info.appendChild(code);
                left.appendChild(flag);
                left.appendChild(info);

                var radio = document.createElement('span');
                radio.className = 'lang-modal-radio';
                radio.appendChild(document.createElement('span'));

                item.appendChild(left);
                item.appendChild(radio);
                item.addEventListener('click', function () {
                    selected = lang.code;
                    buildList();
                    syncApply();
                });
                list.appendChild(item);
            });
        }

        function syncApply() {
            if (apply) apply.disabled = selected === current();
        }

        function openModal() {
            selected = current();
            buildList();
            syncApply();
            overlay.classList.add('open');
            overlay.setAttribute('aria-hidden', 'false');
            btn.setAttribute('aria-expanded', 'true');
        }

        function closeModal() {
            overlay.classList.remove('open');
            overlay.setAttribute('aria-hidden', 'true');
            btn.setAttribute('aria-expanded', 'false');
        }

        btn.addEventListener('click', openModal);
        if (cancel) cancel.addEventListener('click', closeModal);
        overlay.addEventListener('click', function (e) { if (e.target === overlay) closeModal(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });
        if (apply) {
            apply.addEventListener('click', function () {
                if (selected !== current() && dm() && typeof dm().setLocale === 'function') {
                    dm().setLocale(selected);
                }
                closeModal();
            });
        }

        paintLabel();
        if (dm() && typeof dm().onChange === 'function') {
            dm().onChange(function () {
                paintLabel();
                if (overlay.classList.contains('open')) { buildList(); syncApply(); }
            });
        }
    })();
</script>
</body>
</html>
@endverbatim