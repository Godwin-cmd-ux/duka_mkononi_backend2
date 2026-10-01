@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="admin_signup.page_title">Dukamkononi | Usajili Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
        }

        /* KeyboardAvoidingView equivalent - just full view */
        .keyboard-avoid {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .scroll-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
            min-height: 100vh;
        }

        .content {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
        }

        /* Header styles */
        .header {
            font-size: 24px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 8px;
            color: #e74c3c;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .subtitle {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 32px;
            text-align: center;
        }

        /* Form container */
        .form-container {
            display: flex;
            flex-direction: column;
            gap: 16px;
            width: 100%;
            margin-bottom: 20px;
        }

        /* Input fields match RN styles: white background, border, shadow */
        .input-field {
            width: 100%;
            height: 50px;
            background-color: white;
            border-radius: 12px;
            padding: 0 16px;
            font-size: 16px;
            font-family: 'Inter', monospace;
            border: 1px solid #e0e0e0;
            transition: all 0.2s ease;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }

        .input-field:focus {
            outline: none;
            border-color: #e74c3c;
            box-shadow: 0 0 0 3px rgba(231, 76, 60, 0.1);
        }

        /* Buttons */
        .signup-btn {
            width: 100%;
            height: 60px;
            background-color: #e74c3c;
            border-radius: 12px;
            justify-content: center;
            align-items: center;
            display: flex;
            margin-top: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .signup-btn:active {
            transform: scale(0.97);
            opacity: 0.9;
        }

        .signup-btn.disabled {
            opacity: 0.7;
            transform: none;
            cursor: not-allowed;
        }

        .signup-btn-text {
            font-size: 18px;
            font-weight: 800;
            color: white;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .back-btn {
            width: 100%;
            height: 50px;
            background-color: transparent;
            border: 2px solid #3498db;
            border-radius: 12px;
            justify-content: center;
            align-items: center;
            display: flex;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .back-btn:active {
            transform: scale(0.97);
            background-color: rgba(52, 152, 219, 0.05);
        }

        .back-btn-text {
            font-size: 16px;
            font-weight: 700;
            color: #3498db;
            text-transform: uppercase;
        }

        /* Info card (like the RN infoContainer) */
        .info-card {
            margin-top: 28px;
            padding: 18px 20px;
            background-color: #fdeaea;
            border-radius: 16px;
            width: 100%;
            border-left: 4px solid #e74c3c;
        }

        .info-title {
            font-size: 14px;
            font-weight: 800;
            color: #e74c3c;
            margin-bottom: 12px;
        }

        .info-point {
            font-size: 12px;
            color: #c0392b;
            margin-bottom: 8px;
            line-height: 1.45;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }

        .info-point::before {
            content: "•";
            font-weight: bold;
            font-size: 14px;
            margin-right: 4px;
        }

        .loader {
            display: inline-block;
            width: 24px;
            height: 24px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Utility & responsiveness */
        @media (max-width: 550px) {
            .scroll-container {
                padding: 30px 16px;
            }
            .header {
                font-size: 22px;
            }
        }

        /* Alert modal styling (uses custom modal but keeps browser native fallback) */
        .custom-alert {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(2px);
        }
        .alert-box {
            background: white;
            border-radius: 28px;
            width: 85%;
            max-width: 320px;
            padding: 24px 20px 20px;
            text-align: center;
            box-shadow: 0 20px 35px rgba(0,0,0,0.2);
        }
        .alert-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #2c3e50;
        }
        .alert-message {
            font-size: 15px;
            color: #5d6d7e;
            margin-bottom: 24px;
            line-height: 1.4;
        }
        .alert-btn {
            background-color: #e74c3c;
            padding: 12px;
            border-radius: 40px;
            font-weight: 700;
            color: white;
            text-align: center;
            cursor: pointer;
        }
        .alert-btn-secondary {
            background-color: #3498db;
            margin-top: 10px;
        }

        /* OTP 6-digit boxes */
        .otp-boxes {
            display: flex;
            gap: 8px;
            justify-content: center;
            margin-bottom: 18px;
        }
        .otp-box {
            width: 46px;
            height: 56px;
            border-radius: 12px;
            border: 1.5px solid #d5dbe1;
            background: #f8fafc;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            color: #2c3e50;
            font-family: 'Inter', monospace;
            transition: border-color 0.2s;
        }
        .otp-box:focus {
            outline: none;
            border-color: #e74c3c;
        }
        .otp-box.filled {
            border-color: #e74c3c;
        }
        .otp-box.error {
            border-color: #e74c3c;
        }
        .otp-error-box {
            display: none;
            align-items: center;
            background: #fdecea;
            border-radius: 10px;
            padding: 8px 12px;
            margin-bottom: 14px;
        }
        .otp-error-box.show {
            display: flex;
        }
        .otp-error-icon {
            margin-right: 6px;
            font-size: 16px;
        }
        .otp-error-text {
            font-size: 13px;
            color: #c0392b;
            font-weight: 500;
            flex: 1;
        }
        .otp-close-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            padding: 6px;
            border-radius: 18px;
            background: #f4f6f8;
            border: none;
            cursor: pointer;
            font-size: 18px;
            color: #95a5a6;
        }
        .otp-creating-text {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 6px;
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@verbatim
<div class="keyboard-avoid">
    <div class="scroll-container">
        <div class="content">
            <h1 class="header" data-i18n="admin_signup.header_register_admin">JISAJILI KAMA MSIMAMIZI</h1>
            <p class="subtitle" data-i18n="admin_signup.required_hint">* Inamaanisha sehemu inayohitajika</p>

            <div class="form-container">
                <input type="text" class="input-field" id="full_name" placeholder="Jina Kamili *" data-i18n="admin_signup.label_full_name" data-i18n-attr="placeholder" autocomplete="name">
                <input type="text" class="input-field" id="business_name" placeholder="Jina la Biashara *" data-i18n="admin_signup.label_business_name" data-i18n-attr="placeholder" autocomplete="organization">
                <input type="text" class="input-field" id="business_location" placeholder="Mahali pa Biashara *" data-i18n="admin_signup.label_business_location" data-i18n-attr="placeholder" autocomplete="address-line1">
                <select class="input-field" id="business_type">
                    <option value="" data-i18n="admin_signup.label_business_type">Aina ya Biashara *</option>
                    <option value="spare_parts" data-i18n="admin_signup.business_type_spare_parts">Sehemu za Gari</option>
                    <option value="motorcycle_spares" data-i18n="admin_signup.business_type_motorcycle_spares">Spea za Pikipiki</option>
                    <option value="supermarket" data-i18n="admin_signup.business_type_supermarket">Supermarket</option>
                    <option value="pharmacy" data-i18n="admin_signup.business_type_pharmacy">Duka la Dawa</option>
                    <option value="electronics" data-i18n="admin_signup.business_type_electronics">Elektroniki</option>
                    <option value="clothing" data-i18n="admin_signup.business_type_clothing">Mavazi</option>
                    <option value="hardware" data-i18n="admin_signup.business_type_hardware">Vifaa Vinene (Hardware)</option>
                    <option value="cosmetics" data-i18n="admin_signup.business_type_cosmetics">Vipodozi</option>
                    <option value="perfume" data-i18n="admin_signup.business_type_perfume">Manukato</option>
                    <option value="restaurant" data-i18n="admin_signup.business_type_restaurant">Mgahawa</option>
                    <option value="furniture" data-i18n="admin_signup.business_type_furniture">Samani</option>
                    <option value="stationery" data-i18n="admin_signup.business_type_stationery">Vifaa vya Ofisi na Shule</option>
                    <option value="mobile_accessories" data-i18n="admin_signup.business_type_mobile_accessories">Vifaa vya Simu</option>
                    <option value="computer_shop" data-i18n="admin_signup.business_type_computer_shop">Duka la Kompyuta</option>
                    <option value="phone_shop" data-i18n="admin_signup.business_type_phone_shop">Duka la Simu</option>
                    <option value="agriculture" data-i18n="admin_signup.business_type_agriculture">Kilimo</option>
                    <option value="construction_materials" data-i18n="admin_signup.business_type_construction_materials">Vifaa vya Ujenzi</option>
                    <option value="beauty_salon" data-i18n="admin_signup.business_type_beauty_salon">Saluni ya Urembo</option>
                    <option value="barbershop" data-i18n="admin_signup.business_type_barbershop">Kinyozi</option>
                    <option value="auto_repair" data-i18n="admin_signup.business_type_auto_repair">Karakana ya Magari</option>
                    <option value="general_retail" data-i18n="admin_signup.business_type_general_retail">Rejareja ya Jumla</option>
                    <option value="wholesale" data-i18n="admin_signup.business_type_wholesale">Jumla (Wholesale)</option>
                    <option value="other" data-i18n="admin_signup.business_type_other">Nyingine</option>
                </select>
                <textarea class="input-field" id="business_description" placeholder="Maelezo mafupi ya biashara (mfano: Tunauza sehemu za magari ya Toyota na Nissan...)" data-i18n="admin_signup.label_business_description" data-i18n-attr="placeholder" style="height:72px;padding:14px 16px;resize:vertical;font-family:'Inter',sans-serif;"></textarea>
                <input type="email" class="input-field" id="email" placeholder="Barua Pepe *" data-i18n="admin_signup.label_email" data-i18n-attr="placeholder" autocomplete="email">
                <input type="tel" class="input-field" id="phone" placeholder="Namba ya Simu" data-i18n="admin_signup.label_phone" data-i18n-attr="placeholder" autocomplete="tel">
                <input type="password" class="input-field" id="adminCode" placeholder="Admin Code *" data-i18n="admin_signup.label_admin_code" data-i18n-attr="placeholder">
                <input type="password" class="input-field" id="password" placeholder="Nenosiri *" data-i18n="admin_signup.label_password" data-i18n-attr="placeholder" autocomplete="new-password">
                <input type="password" class="input-field" id="confirmPassword" placeholder="Rudia Nenosiri *" data-i18n="admin_signup.label_confirm_password" data-i18n-attr="placeholder" autocomplete="off">
            </div>

            <div id="signupBtn" class="signup-btn">
                <span id="signupBtnText" class="signup-btn-text" data-i18n="admin_signup.btn_register_now">JISAJILI SASA</span>
                <div id="loaderSpinner" style="display: none;" class="loader"></div>
            </div>

            <div id="backBtn" class="back-btn">
                <span class="back-btn-text" data-i18n="admin_signup.btn_go_back">RUDI NYUMA</span>
            </div>

            <div class="info-card">
                <div class="info-title" data-i18n="admin_signup.info_title">🔑 Maelezo muhimu kwa Msimamizi:</div>
                <div class="info-point" data-i18n="admin_signup.info_point_unique_name">Jina la biashara lazima liwe la kipekee na halijasajiliwa</div>
                <div class="info-point" data-i18n="admin_signup.info_point_full_authority">Utakuwa na mamlaka kamili ya kuidhinisha wateja na wauzaji</div>
                <div class="info-point" data-i18n="admin_signup.info_point_immediate_use">Unaweza kuanza kutumia mfumo mara moja baada ya kujisajili</div>
                <div class="info-point" data-i18n="admin_signup.info_point_admin_code">Admin Code: ADMIN2024</div>
            </div>
        </div>
    </div>
</div>

<script>
    // ------------------------------
    // DUKAMKONONI: AdminSignup web replica
    // OTP-based registration flow: initiate → verify-otp → register
    const API_BASE_URL = '';

    // The locale the visitor is browsing in. window.DM is set up synchronously
    // by the language partial, but fall back to the attribute the server
    // rendered so this never depends on locales.json.
    function activeLocale() {
        if (window.DM) return window.DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    // This page builds the OTP modal and every alert in JS, so almost none of its
    // text exists in the markup that data-i18n can reach. Those strings come
    // from locales.json through the shared DM runtime. Until it has loaded we
    // keep the Swahili source string, so a failed locales.json request can never
    // blank the modal or fall back to raw key names.
    const SW = {
        otp_title: 'Uthibitisho wa Barua Pepe',
        otp_sent_to: 'Tumetuma msimbo wa tarakimu 6 kwenye barua pepe: {email}',
        otp_verify: 'Hakiki Msimbo',
        otp_creating_account: 'Inaunda akaunti...',
        otp_no_code_received: 'Hujapokea msimbo? ',
        otp_resend_countdown: 'Tuma tena ({seconds}s)',
        otp_resend_code: 'Tuma msimbo tena',
        otp_change_email: '← Badilisha barua pepe',
        otp_err_code_required: 'Tafadhali weka msimbo wa tarakimu 6',
        otp_err_code_incorrect: 'Msimbo si sahihi',
        otp_err_network: 'Hitilafu ya mtandao',
        otp_sending: 'Inatuma...',
        otp_failed: 'Imeshindikana',
        alert_ok: 'Sawa',
        alert_sign_in_now: 'Ingia Sasa',
        alert_network_error: 'Hitilafu ya Mtandao',
        err_no_internet: 'Hakuna muunganisho wa mtandao. Tafadhali hakikisha umeunganishwa kwenye internet.',
        err_required_fields: 'Tafadhali jaza sehemu zote required',
        err_email_invalid: 'Tafadhali ingiza barua pepe sahihi',
        err_passwords_dont_match: 'Nenosiri hazifanani',
        err_password_short: 'Nenosiri lazima liwe na herufi 6 au zaidi',
        err_admin_code_incorrect: 'Admin code si sahihi',
        alert_error: 'Hitilafu',
        err_signup_failed: 'Hitilafu imetokea wakati wa kujisajili',
        err_try_again: 'Hitilafu imetokea. Tafadhali jaribu tena.',
        otp_success_title: 'Mafanikio',
        otp_success_message: 'Akaunti ya Msimamizi imeundwa kikamilifu! Sasa unaweza kuingia na kuanza kusimamia biashara yako.'
    };

    function t(key, params) {
        if (window.DM) return window.DM.t(key, params);
        // The catalog key carries the section prefix that DM.t walks, while the
        // fallback dict above is keyed by bare name. Drop the prefix so the
        // no-runtime path still shows the Swahili source string.
        const dot = key.indexOf('.');
        let value = SW[key];
        if (value === undefined && dot > -1) value = SW[key.slice(dot + 1)];
        if (value === undefined) return key;
        if (params) {
            Object.keys(params).forEach(name => {
                value = value.split('{' + name + '}').join(String(params[name]));
            });
        }
        return value;
    }

    // DOM elements
    const fullNameInput = document.getElementById('full_name');
    const businessNameInput = document.getElementById('business_name');
    const businessLocationInput = document.getElementById('business_location');
    const businessTypeInput = document.getElementById('business_type');
    const businessDescInput = document.getElementById('business_description');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
    const adminCodeInput = document.getElementById('adminCode');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirmPassword');
    const signupBtn = document.getElementById('signupBtn');
    const signupBtnTextSpan = document.getElementById('signupBtnText');
    const loaderSpinner = document.getElementById('loaderSpinner');
    const backBtn = document.getElementById('backBtn');

    let loading = false;
    let formValues = null;
    let registrationToken = null;
    let pendingEmail = '';

    // OTP Modal HTML — 6 individual digit boxes with countdown
    const OTP_LENGTH = 6;
    const OTP_RESEND_COOLDOWN = 30;
    let otpDigits = [];
    let otpCountdown = OTP_RESEND_COOLDOWN;
    let otpCountdownTimer = null;

    // Language-switch bookkeeping: the resend label and the error line are
    // rewritten by JS as the flow progresses, so their last text has to be kept
    // in a variable to be re-rendered when the visitor changes language.
    let otpResendBusy = false;
    let activeOtpError = null;
    let otpVerifying = false;
    let otpCreating = false;

    function getOtpModalHtml(email) {
        let boxesHtml = '';
        for (let i = 0; i < OTP_LENGTH; i++) {
            boxesHtml += `<input type="text" class="otp-box" id="otpBox${i}" maxlength="2" inputmode="numeric" pattern="[0-9]*" placeholder="•">`;
        }
        return `
            <div id="otpModal" class="custom-alert" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.55);display:flex;align-items:center;justify-content:center;z-index:1000;backdrop-filter:blur(2px);padding:24px;">
                <div style="background:white;border-radius:24px;width:100%;max-width:400px;padding:20px 24px 26px;align-items:center;position:relative;box-shadow:0 12px 24px rgba(0,0,0,0.18);">
                    <button class="otp-close-btn" id="otpCloseBtn">✕</button>
                    <div style="text-align:center;margin-bottom:14px;">
                        <div style="width:64px;height:64px;border-radius:32px;background:#e74c3c1a;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                            <span style="font-size:34px;">✉️</span>
                        </div>
                    </div>
                    <div class="alert-title" style="font-size:20px;font-weight:bold;color:#2c3e50;margin-bottom:6px;text-align:center;">${t('admin_signup.otp_title')}</div>
                    <div id="otpSentTo" style="font-size:13px;color:#7f8c8d;text-align:center;line-height:19px;margin-bottom:22px;padding:0 6px;"></div>
                    <div class="otp-boxes" id="otpBoxes">${boxesHtml}</div>
                    <div class="otp-error-box" id="otpErrorBox">
                        <span class="otp-error-icon">⚠️</span>
                        <span class="otp-error-text" id="otpErrorText"></span>
                    </div>
                    <button id="otpVerifyBtn" class="alert-btn" style="width:100%;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;cursor:pointer;border:none;font-size:16px;font-weight:bold;color:white;background:#e74c3c;">
                        <span>✓</span> <span id="otpVerifyText">${t('admin_signup.otp_verify')}</span>
                        <span id="otpVerifyLoader" style="display:none;"><div class="loader" style="width:20px;height:20px;border-width:2px;"></div></span>
                    </button>
                    <div id="otpCreatingAccount" style="display:none;text-align:center;margin-bottom:14px;">
                        <div class="loader" style="margin:0 auto 8px;border-top-color:#e74c3c;"></div>
                        <div class="otp-creating-text" id="otpCreatingText">${t('admin_signup.otp_creating_account')}</div>
                    </div>
                    <div style="text-align:center;margin-bottom:14px;">
                        <span id="otpNoCodeText" style="font-size:13px;color:#7f8c8d;">${t('admin_signup.otp_no_code_received')}</span>
                        <span id="otpResendBtn" style="font-size:13px;font-weight:700;color:#e74c3c;cursor:pointer;"></span>
                    </div>
                    <div style="text-align:center;">
                        <span id="otpCancelBtn" style="font-size:13px;color:#7f8c8d;cursor:pointer;display:inline-flex;align-items:center;gap:5px;text-decoration:underline;">${t('admin_signup.otp_change_email')}</span>
                    </div>
                </div>
            </div>
        `;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // The email address is shown in bold so it stays readable against a long
    // sentence in languages that put the value in the middle rather than at the
    // end. Escape first: the address comes from a form field.
    function renderOtpSentTo(email) {
        const el = document.getElementById('otpSentTo');
        if (!el) return;
        const safeEmail = escapeHtml(email);
        el.innerHTML = escapeHtml(t('admin_signup.otp_sent_to', { email: email }))
            .split(safeEmail)
            .join('<strong>' + safeEmail + '</strong>');
    }

    // Retranslate the parts of the open OTP modal that data-i18n cannot reach.
    // The modal's own labels are baked into the markup by getOtpModalHtml(), so
    // a language switch has to rebuild the whole thing; renderOtpModal() reads
    // the digits, countdown, error and spinner state back out of the variables
    // below, so nothing the visitor typed or typed progress is lost.
    function renderOtpModal() {
        const current = document.getElementById('otpModal');
        if (!current) return;
        current.remove();
        buildOtpModal();
    }

    function showOtpResendState() {
        const resendBtn = document.getElementById('otpResendBtn');
        if (!resendBtn) return;
        if (otpResendBusy) {
            resendBtn.textContent = t('admin_signup.otp_sending');
            resendBtn.style.color = '#95a5a6';
            resendBtn.style.cursor = 'default';
            return;
        }
        if (otpCountdown > 0) {
            resendBtn.textContent = t('admin_signup.otp_resend_countdown', { seconds: otpCountdown });
            resendBtn.style.color = '#95a5a6';
            resendBtn.style.cursor = 'default';
        } else {
            resendBtn.textContent = t('admin_signup.otp_resend_code');
            resendBtn.style.color = '#e74c3c';
            resendBtn.style.cursor = 'pointer';
        }
    }

    function startOtpCountdown() {
        otpCountdown = OTP_RESEND_COOLDOWN;
        showOtpResendState();
        if (otpCountdownTimer) clearInterval(otpCountdownTimer);
        otpCountdownTimer = setInterval(() => {
            otpCountdown--;
            showOtpResendState();
            if (otpCountdown <= 0) {
                clearInterval(otpCountdownTimer);
                otpCountdownTimer = null;
            }
        }, 1000);
    }

    // msg is either a { key, params } object for this page's own copy or a plain
    // string that came back from the API, which only ever sends Swahili. The
    // source is remembered so a language switch can re-render the line in the
    // new language without ever translating a server message.
    function resolveOtpError(msg) {
        if (!msg) return null;
        if (typeof msg === 'string') return msg;
        return t(msg.key, msg.params);
    }

    function setOtpError(msg) {
        const box = document.getElementById('otpErrorBox');
        const text = document.getElementById('otpErrorText');
        if (!box || !text) return;
        activeOtpError = msg || null;
        const value = resolveOtpError(msg);
        if (value) {
            box.classList.add('show');
            text.textContent = value;
            // Mark boxes as error
            for (let i = 0; i < OTP_LENGTH; i++) {
                const b = document.getElementById('otpBox' + i);
                if (b) b.classList.add('error');
            }
        } else {
            box.classList.remove('show');
            for (let i = 0; i < OTP_LENGTH; i++) {
                const b = document.getElementById('otpBox' + i);
                if (b) b.classList.remove('error');
            }
        }
    }

    function getOtpCode() {
        return otpDigits.join('');
    }

    function setOtpVerifying(verifying) {
        otpVerifying = verifying;
        const btn = document.getElementById('otpVerifyBtn');
        const text = document.getElementById('otpVerifyText');
        const loader = document.getElementById('otpVerifyLoader');
        if (verifying) {
            text.style.display = 'none';
            loader.style.display = 'inline-block';
            btn.disabled = true;
        } else {
            text.style.display = 'inline';
            loader.style.display = 'none';
            btn.disabled = false;
        }
    }

    function setOtpCreating(creating) {
        otpCreating = creating;
        const el = document.getElementById('otpCreatingAccount');
        const btn = document.getElementById('otpVerifyBtn');
        if (creating) {
            el.style.display = 'block';
            btn.style.display = 'none';
        } else {
            el.style.display = 'none';
            btn.style.display = 'flex';
        }
    }

    // An alert is built from plain strings, so it cannot be reached by data-i18n.
    // Keep the key/param source next to the rendered text: if the visitor changes
    // language while an alert is open, only alerts raised from this page's own
    // keys are re-rendered. Messages that came back from the API are shown
    // verbatim, because the server only ever sends those in Swahili.
    let alertState = null;

    function showAlert(title, message, onOkCallback = null, successWithNavigate = false) {
        const existingAlert = document.querySelector('.custom-alert');
        if (existingAlert) existingAlert.remove();

        alertState = {
            titleKey: title && title.key,
            titleParams: title && title.params,
            messageKey: message && message.key,
            messageParams: message && message.params
        };
        const titleText = typeof title === 'string' ? title : t(title.key, title.params);
        const messageText = typeof message === 'string' ? message : t(message.key, message.params);

        const overlay = document.createElement('div');
        overlay.className = 'custom-alert';
        
        const alertBox = document.createElement('div');
        alertBox.className = 'alert-box';
        
        const titleEl = document.createElement('div');
        titleEl.className = 'alert-title';
        titleEl.innerText = titleText;
        
        const msgEl = document.createElement('div');
        msgEl.className = 'alert-message';
        msgEl.innerText = messageText;
        
        const okBtn = document.createElement('div');
        okBtn.className = 'alert-btn';
        okBtn.innerText = t('admin_signup.alert_ok');
        
        alertBox.appendChild(titleEl);
        alertBox.appendChild(msgEl);
        alertBox.appendChild(okBtn);
        
        if (successWithNavigate) {
            const loginBtn = document.createElement('div');
            loginBtn.className = 'alert-btn alert-btn-secondary';
            loginBtn.innerText = t('admin_signup.alert_sign_in_now');
            loginBtn.style.marginTop = '10px';
            loginBtn.style.backgroundColor = '#2ecc71';
            alertBox.appendChild(loginBtn);
            
            loginBtn.addEventListener('click', () => {
                overlay.remove();
                alertState = null;
                window.location.href = '/login?role=msimamizi';
            });
        }
        
        okBtn.addEventListener('click', () => {
            overlay.remove();
            alertState = null;
            if (onOkCallback) onOkCallback();
        });
        
        overlay.appendChild(alertBox);
        document.body.appendChild(overlay);
    }

    // Re-render the visible alert's own text after a language switch. Buttons
    // stay wired to the callbacks they were bound to when the alert was opened.
    function renderAlertStrings() {
        if (!alertState) return;
        const overlay = document.querySelector('.custom-alert');
        if (!overlay) return;
        const titleEl = overlay.querySelector('.alert-title');
        const msgEl = overlay.querySelector('.alert-message');
        const okBtn = overlay.querySelector('.alert-btn');
        const loginBtn = overlay.querySelector('.alert-btn-secondary');
        if (titleEl && alertState.titleKey) titleEl.innerText = t(alertState.titleKey, alertState.titleParams);
        if (msgEl && alertState.messageKey) msgEl.innerText = t(alertState.messageKey, alertState.messageParams);
        if (okBtn) okBtn.innerText = t('admin_signup.alert_ok');
        if (loginBtn) loginBtn.innerText = t('admin_signup.alert_sign_in_now');
    }

    function showNetworkError() {
        showAlert({ key: 'admin_signup.alert_network_error' }, { key: 'admin_signup.err_no_internet' });
    }

    function setLoadingState(isLoading) {
        loading = isLoading;
        if (isLoading) {
            signupBtn.classList.add('disabled');
            signupBtnTextSpan.style.display = 'none';
            loaderSpinner.style.display = 'inline-block';
        } else {
            signupBtn.classList.remove('disabled');
            signupBtnTextSpan.style.display = 'inline-block';
            loaderSpinner.style.display = 'none';
        }
    }

    function getFormValues() {
        return {
            full_name: fullNameInput.value.trim(),
            business_name: businessNameInput.value.trim(),
            business_location: businessLocationInput.value.trim(),
            business_type: businessTypeInput.value,
            business_description: (businessDescInput.value || '').trim(),
            email: emailInput.value.trim(),
            phone: phoneInput.value.trim(),
            adminCode: adminCodeInput.value.trim(),
            password: passwordInput.value,
            confirmPassword: confirmPasswordInput.value
        };
    }

    function validateForm(form) {
        if (!form.email || !form.password || !form.full_name || !form.business_name || !form.business_location || !form.adminCode || !form.business_type) {
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_required_fields' });
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(form.email)) {
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_email_invalid' });
            return false;
        }
        if (form.password !== form.confirmPassword) {
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_passwords_dont_match' });
            return false;
        }
        if (form.password.length < 6) {
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_password_short' });
            return false;
        }
        if (form.adminCode !== 'ADMIN2024') {
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_admin_code_incorrect' });
            return false;
        }
        return true;
    }

    // Step 1: Initiate registration (send OTP)
    async function handleSignup() {
        if (loading) return;

        const form = getFormValues();
        if (!validateForm(form)) return;

        formValues = form;
        setLoadingState(true);

        try {
            const response = await fetch(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: form.email,
                    password: form.password,
                    role: 'admin',
                    full_name: form.full_name,
                    phone: form.phone || '',
                    business_name: form.business_name,
                    business_location: form.business_location,
                    business_type: form.business_type,
                    business_description: form.business_description || ''
                }),
            });

            const data = await response.json();

            if (response.ok && (data.success || data.requiresOtp)) {
                pendingEmail = form.email;
                setLoadingState(false);
                showOtpModal(form.email);
            } else {
                setLoadingState(false);
                // data.error is the server's own Swahili message, so it is shown
                // as-is; the fallback below is this page's own translated copy.
                showAlert({ key: 'admin_signup.alert_error' }, data.error || { key: 'admin_signup.err_signup_failed' });
            }
        } catch (error) {
            console.error('Signup error:', error);
            setLoadingState(false);
            if (error.message && (error.message.includes('Network request failed') || error.message.includes('fetch'))) {
                showNetworkError();
            } else {
                showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_try_again' });
            }
        }
    }

    // Show OTP verification modal
    function showOtpModal(email) {
        const existing = document.querySelector('.custom-alert');
        if (existing) existing.remove();

        otpDigits = Array(OTP_LENGTH).fill('');
        otpCountdown = OTP_RESEND_COOLDOWN;
        otpResendBusy = false;
        activeOtpError = null;
        alertState = null;
        otpVerifying = false;
        otpCreating = false;
        pendingEmail = email;

        buildOtpModal();

        // Start resend countdown
        startOtpCountdown();
    }

    // Build the modal DOM from scratch and re-apply the live state onto it.
    // Called when the modal first opens and again whenever the visitor changes
    // language, because its labels live in markup rather than in data-i18n.
    function buildOtpModal() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = getOtpModalHtml(pendingEmail);
        document.body.appendChild(wrapper.firstElementChild);

        // The sentence carrying the email address is assembled in JS (the address
        // is bolded inside it), so data-i18n cannot fill it.
        renderOtpSentTo(pendingEmail);

        // Set up 6 individual digit boxes
        for (let i = 0; i < OTP_LENGTH; i++) {
            const box = document.getElementById('otpBox' + i);
            if (box.value !== otpDigits[i]) box.value = otpDigits[i] || '';
            box.classList.toggle('filled', !!otpDigits[i]);
            box.addEventListener('input', (e) => {
                const val = e.target.value.replace(/[^0-9]/g, '');
                otpDigits[i] = val ? val.slice(-1) : '';
                box.value = otpDigits[i];
                if (otpDigits[i]) {
                    box.classList.add('filled');
                    if (i < OTP_LENGTH - 1) {
                        document.getElementById('otpBox' + (i + 1)).focus();
                    }
                } else {
                    box.classList.remove('filled');
                }
                setOtpError(null); // clear error on input
            });
            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !otpDigits[i] && i > 0) {
                    otpDigits[i - 1] = '';
                    document.getElementById('otpBox' + (i - 1)).value = '';
                    document.getElementById('otpBox' + (i - 1)).classList.remove('filled');
                    document.getElementById('otpBox' + (i - 1)).focus();
                }
            });
            box.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') handleVerifyOtp();
            });
        }

        // Auto-submit when all 6 digits entered
        const checkAutoSubmit = () => {
            const code = otpDigits.join('');
            if (code.length === OTP_LENGTH && otpDigits.every(d => d !== '')) {
                handleVerifyOtp();
            }
        };
        for (let i = 0; i < OTP_LENGTH; i++) {
            document.getElementById('otpBox' + i).addEventListener('input', checkAutoSubmit);
        }

        // Focus first box
        setTimeout(() => {
            const firstBox = document.getElementById('otpBox0');
            if (firstBox) firstBox.focus();
        }, 350);

        document.getElementById('otpVerifyBtn').addEventListener('click', () => handleVerifyOtp());
        document.getElementById('otpResendBtn').addEventListener('click', () => handleResendOtp(pendingEmail));
        document.getElementById('otpCancelBtn').addEventListener('click', () => {
            if (otpCountdownTimer) clearInterval(otpCountdownTimer);
            document.getElementById('otpModal').remove();
        });
        document.getElementById('otpCloseBtn').addEventListener('click', () => {
            if (otpCountdownTimer) clearInterval(otpCountdownTimer);
            document.getElementById('otpModal').remove();
        });

        // Re-apply the in-progress states a rebuild would otherwise drop.
        showOtpResendState();
        if (activeOtpError) setOtpError(activeOtpError);
        setOtpVerifying(otpVerifying);
        setOtpCreating(otpCreating);
    }

    // Nothing on this page is rebuilt from a render function, so the switch has
    // to re-apply the two pieces of JS-built text that can be on screen: an open
    // OTP modal and an open alert. The static form, header and info card are
    // handled by the shared runtime's data-i18n pass.
    if (window.DM) {
        window.DM.onChange(() => {
            renderOtpModal();
            renderAlertStrings();
        });
    }

    // Step 2: Verify OTP
    async function handleVerifyOtp() {
        const code = getOtpCode();

        if (!code || code.length !== OTP_LENGTH) {
            setOtpError({ key: 'admin_signup.otp_err_code_required' });
            return;
        }

        setOtpError(null);
        setOtpVerifying(true);

        try {
            const response = await fetch(`${API_BASE_URL}/api/register/verify-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: pendingEmail, role: 'admin', otp: code }),
            });

            const data = await response.json();

            if (response.ok && data.success && data.verified) {
                registrationToken = data.registrationToken;
                setOtpVerifying(false);
                setOtpCreating(true);
                await completeRegistration();
            } else {
                setOtpVerifying(false);
                // As above, data.error stays in the server's Swahili.
                setOtpError(data.error || { key: 'admin_signup.otp_err_code_incorrect' });
            }
        } catch (error) {
            setOtpVerifying(false);
            setOtpError({ key: 'admin_signup.otp_err_network' });
        }
    }

    // Resend OTP
    async function handleResendOtp(email) {
        if (otpCountdown > 0) return;
        otpResendBusy = true;
        showOtpResendState();

        try {
            const response = await fetch(`${API_BASE_URL}/api/register/resend-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, role: 'admin' }),
            });

            const data = await response.json();
            if (response.ok && data.success) {
                // Clear current boxes
                for (let i = 0; i < OTP_LENGTH; i++) {
                    otpDigits[i] = '';
                    const b = document.getElementById('otpBox' + i);
                    if (b) { b.value = ''; b.classList.remove('filled'); }
                }
                setOtpError(null);
                otpResendBusy = false;
                startOtpCountdown();
            } else {
                otpResendBusy = false;
                setOtpError(data.error || { key: 'admin_signup.otp_failed' });
                showOtpResendState();
            }
        } catch (error) {
            otpResendBusy = false;
            setOtpError({ key: 'admin_signup.otp_err_network' });
            showOtpResendState();
        }
    }

    // Step 3: Complete registration with token
    async function completeRegistration() {
        setLoadingState(true);

        try {
            const response = await fetch(`${API_BASE_URL}/api/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    verificationToken: registrationToken,
                    email: formValues.email,
                    role: 'admin'
                }),
            });

            const data = await response.json();

            if (response.ok) {
                setLoadingState(false);
                if (otpCountdownTimer) clearInterval(otpCountdownTimer);
                const modal = document.getElementById('otpModal');
                if (modal) modal.remove();
                showAlert({ key: 'admin_signup.otp_success_title' }, { key: 'admin_signup.otp_success_message' }, null, true);
            } else {
                setLoadingState(false);
                showAlert({ key: 'admin_signup.alert_error' }, data.error || { key: 'admin_signup.err_signup_failed' });
            }
        } catch (error) {
            setLoadingState(false);
            showAlert({ key: 'admin_signup.alert_error' }, { key: 'admin_signup.err_try_again' });
        }
    }

    function goBack() {
        if (document.referrer && (document.referrer.includes(window.location.host) || window.location.pathname !== '/')) {
            window.history.back();
        } else {
            window.location.href = '/home';
        }
    }

    signupBtn.addEventListener('click', handleSignup);
    backBtn.addEventListener('click', goBack);

    const inputs = [fullNameInput, businessNameInput, businessLocationInput, emailInput, phoneInput, adminCodeInput, passwordInput, confirmPasswordInput];
    inputs.forEach(input => {
        if (input) {
            input.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleSignup();
                }
            });
        }
    });

    console.log('AdminSignup ready — OTP-based registration flow');
</script>
</body>
</html>
@endverbatim