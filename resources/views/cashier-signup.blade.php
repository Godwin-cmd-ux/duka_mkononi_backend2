@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="cashier_signup.page_title">Dukamkononi | Usajili Muuzaji</title>
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
            color: #2ecc71;
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
            border-color: #2ecc71;
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.1);
        }

        /* Buttons */
        .signup-btn {
            width: 100%;
            height: 60px;
            background-color: #2ecc71;
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

        /* Info card - seller specific */
        .info-card {
            margin-top: 28px;
            padding: 18px 20px;
            background-color: #e8f6ef;
            border-radius: 16px;
            width: 100%;
            border-left: 4px solid #2ecc71;
        }

        .info-title {
            font-size: 14px;
            font-weight: 800;
            color: #27ae60;
            margin-bottom: 12px;
        }

        .info-point {
            font-size: 12px;
            color: #1e8449;
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

        @media (max-width: 550px) {
            .scroll-container {
                padding: 30px 16px;
            }
            .header {
                font-size: 22px;
            }
        }

        /* Custom Alert Modal (replaces RN Alert) */
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
            max-width: 340px;
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
            background-color: #2ecc71;
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
            border-color: #2ecc71;
        }
        .otp-box.filled {
            border-color: #2ecc71;
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
            <h1 class="header" data-i18n="cashier_signup.header_register_seller">JISAJILI KAMA MUUZAJI</h1>
            <p class="subtitle" data-i18n="cashier_signup.required_hint">* Inamaanisha sehemu inayohitajika</p>

            <div class="form-container">
                <input type="text" class="input-field" id="full_name" placeholder="Jina Kamili *" autocomplete="name" data-i18n="cashier_signup.label_full_name" data-i18n-attr="placeholder">
                <input type="text" class="input-field" id="business_name" placeholder="Jina la Biashara *" autocomplete="organization" data-i18n="cashier_signup.label_business_name" data-i18n-attr="placeholder">
                <input type="text" class="input-field" id="business_location" placeholder="Mahali pa Biashara *" autocomplete="address-line1" data-i18n="cashier_signup.label_business_location" data-i18n-attr="placeholder">
                <input type="email" class="input-field" id="email" placeholder="Barua Pepe *" autocomplete="email" data-i18n="cashier_signup.label_email" data-i18n-attr="placeholder">
                <input type="tel" class="input-field" id="phone" placeholder="Namba ya Simu" autocomplete="tel" data-i18n="cashier_signup.label_phone" data-i18n-attr="placeholder">
                <input type="password" class="input-field" id="password" placeholder="Nenosiri *" autocomplete="new-password" data-i18n="cashier_signup.label_password" data-i18n-attr="placeholder">
                <input type="password" class="input-field" id="confirmPassword" placeholder="Rudia Nenosiri *" autocomplete="off" data-i18n="cashier_signup.label_confirm_password" data-i18n-attr="placeholder">
            </div>

            <div id="signupBtn" class="signup-btn">
                <span id="signupBtnText" class="signup-btn-text" data-i18n="cashier_signup.btn_register_now">JISAJILI SASA</span>
                <div id="loaderSpinner" style="display: none;" class="loader"></div>
            </div>

            <div id="backBtn" class="back-btn">
                <span class="back-btn-text" data-i18n="cashier_signup.btn_go_back">RUDI NYUMA</span>
            </div>

            <div class="info-card">
                <div class="info-title" data-i18n="cashier_signup.info_title">🔒 Maelezo muhimu kwa Muuzaji:</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_name_exists">Jina la biashara lazima liwe tayari kwenye mfumo (imesajiliwa na msimamizi)</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_verified_admin">Biashara lazima iwe na msimamizi aliyethibitishwa</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_approval">Akaunti yako itahitaji uthibitisho wa msimamizi kabla ya kuingia</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_notify">Utapokea taarifa ukiapidhinishwa</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_name_case">Hakikisha umeweka jina sahihi la biashara (herufi kubwa/ndogo)</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_after_approval">Baada ya kuidhinishwa, utaweza kuongeza bidhaa na kufanya mauzo</div>
            </div>
        </div>
    </div>
</div>

<script>
    // ------------------------------
    // DUKAMKONONI: CashierSignup (Muuzaji) web replica
    // OTP-based registration: initiate → verify-otp → register
    const API_BASE_URL = '';

    // The locale the visitor is browsing in. window.DM is set up synchronously
    // by the language partial, but fall back to the attribute the server
    // rendered so this never depends on locales.json.
    function activeLocale() {
        if (window.DM) return window.DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    // The OTP modal and every alert on this page are built in JS, so almost none
    // of its text exists in markup that data-i18n can reach. Those strings come
    // from locales.json through the shared DM runtime. Until it has loaded we
    // keep the Swahili source string, so a failed locales.json request can never
    // blank the modal or fall back to raw key names.
    const SW = {
        "page_title": "Dukamkononi | Usajili Muuzaji",
        "header_register_seller": "JISAJILI KAMA MUUZAJI",
        "required_hint": "* Inamaanisha sehemu inayohitajika",
        "label_full_name": "Jina Kamili *",
        "label_business_name": "Jina la Biashara *",
        "label_business_location": "Mahali pa Biashara *",
        "label_email": "Barua Pepe *",
        "label_phone": "Namba ya Simu",
        "label_password": "Nenosiri *",
        "label_confirm_password": "Rudia Nenosiri *",
        "btn_register_now": "JISAJILI SASA",
        "btn_go_back": "RUDI NYUMA",
        "info_title": "🔒 Maelezo muhimu kwa Muuzaji:",
        "info_point_name_exists": "Jina la biashara lazima liwe tayari kwenye mfumo (imesajiliwa na msimamizi)",
        "info_point_verified_admin": "Biashara lazima iwe na msimamizi aliyethibitishwa",
        "info_point_approval": "Akaunti yako itahitaji uthibitisho wa msimamizi kabla ya kuingia",
        "info_point_notify": "Utapokea taarifa ukiapidhinishwa",
        "info_point_name_case": "Hakikisha umeweka jina sahihi la biashara (herufi kubwa/ndogo)",
        "info_point_after_approval": "Baada ya kuidhinishwa, utaweza kuongeza bidhaa na kufanya mauzo",
        "alert_ok": "Sawa",
        "alert_network_error": "Hitilafu ya Mtandao",
        "err_no_internet": "Hakuna muunganisho wa mtandao. Tafadhali hakikisha umeunganishwa kwenye internet.",
        "err_required_fields": "Tafadhali jaza sehemu zote required",
        "err_email_invalid": "Tafadhali ingiza barua pepe sahihi",
        "err_passwords_dont_match": "Nenosiri hazifanani",
        "err_password_short": "Nenosiri lazima liwe na herufi 6 au zaidi",
        "alert_error": "Hitilafu",
        "err_business_verify_failed": "Hitilafu katika ukaguzi wa biashara",
        "err_business_not_found_title": "Biashara Haipo",
        "err_business_not_found": "Biashara \"{business_name}\" haipo kwenye mfumo. Tafadhali jisajili kama msimamizi kwanza.",
        "err_no_admin_title": "Biashara Haina Msimamizi",
        "err_no_admin": "Biashara \"{business_name}\" inapatikana lakini haina msimamizi aliyethibitishwa.",
        "err_signup_failed": "Hitilafu imetokea wakati wa kujisajili",
        "err_try_again": "Hitilafu imetokea. Tafadhali jaribu tena.",
        "otp_title": "Uthibitisho wa Barua Pepe",
        "otp_sent_to": "Tumetuma msimbo wa tarakimu 6 kwenye barua pepe: {email}",
        "otp_verify": "Hakiki Msimbo",
        "otp_creating_account": "Inaunda akaunti...",
        "otp_no_code_received": "Hujapokea msimbo? ",
        "otp_resend_countdown": "Tuma tena ({seconds}s)",
        "otp_resend_code": "Tuma msimbo tena",
        "otp_change_email": "← Badilisha barua pepe",
        "otp_err_code_required": "Tafadhali weka msimbo wa tarakimu 6",
        "otp_err_code_incorrect": "Msimbo si sahihi",
        "otp_err_network": "Hitilafu ya mtandao",
        "otp_sending": "Inatuma...",
        "otp_failed": "Imeshindikana",
        "otp_success_title": "Mafanikio",
        "otp_success_message": "Akaunti ya Muuzaji imeundwa kikamilifu! Umewasilisha ombi lako. Tafadhali subiri uthibitisho wa msimamizi kabla ya kuingia.",
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

    const fullNameInput = document.getElementById('full_name');
    const businessNameInput = document.getElementById('business_name');
    const businessLocationInput = document.getElementById('business_location');
    const emailInput = document.getElementById('email');
    const phoneInput = document.getElementById('phone');
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

    // Language-switch bookkeeping. The resend label, the error line, the
    // spinners and the modal's own markup are all rewritten by JS as the flow
    // progresses, so their last state has to be kept to be re-rendered when the
    // visitor changes language.
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
                        <div style="width:64px;height:64px;border-radius:32px;background:#2ecc711a;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                            <span style="font-size:34px;">✉️</span>
                        </div>
                    </div>
                    <div class="alert-title" style="font-size:20px;font-weight:bold;color:#2c3e50;margin-bottom:6px;text-align:center;">${t('cashier_signup.otp_title')}</div>
                    <div style="font-size:13px;color:#7f8c8d;text-align:center;line-height:19px;margin-bottom:22px;padding:0 6px;" id="otpSentTo" style="font-size:13px;color:#7f8c8d;text-align:center;line-height:19px;margin-bottom:22px;padding:0 6px;"></div>
                    <div class="otp-boxes" id="otpBoxes">${boxesHtml}</div>
                    <div class="otp-error-box" id="otpErrorBox">
                        <span class="otp-error-icon">⚠️</span>
                        <span class="otp-error-text" id="otpErrorText"></span>
                    </div>
                    <button id="otpVerifyBtn" class="alert-btn" style="width:100%;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;cursor:pointer;border:none;font-size:16px;font-weight:bold;color:white;background:#2ecc71;">
                        <span>✓</span> <span id="otpVerifyText">${t('cashier_signup.otp_verify')}</span>
                        <span id="otpVerifyLoader" style="display:none;"><div class="loader" style="width:20px;height:20px;border-width:2px;"></div></span>
                    </button>
                    <div id="otpCreatingAccount" style="display:none;text-align:center;margin-bottom:14px;">
                        <div class="loader" style="margin:0 auto 8px;border-top-color:#2ecc71;"></div>
                        <div class="otp-creating-text" id="otpCreatingText">${t('cashier_signup.otp_creating_account')}</div>
                    </div>
                    <div style="text-align:center;margin-bottom:14px;">
                        <span id="otpNoCodeText" style="font-size:13px;color:#7f8c8d;">${t('cashier_signup.otp_no_code_received')}</span>
                        <span id="otpResendBtn" style="font-size:13px;font-weight:700;color:#2ecc71;cursor:pointer;"></span>
                    </div>
                    <div style="text-align:center;">
                        <span id="otpCancelBtn" style="font-size:13px;color:#7f8c8d;cursor:pointer;display:inline-flex;align-items:center;gap:5px;text-decoration:underline;">${t('cashier_signup.otp_change_email')}</span>
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
        el.innerHTML = escapeHtml(t('cashier_signup.otp_sent_to', { email: email }))
            .split(safeEmail)
            .join('<strong>' + safeEmail + '</strong>');
    }

    function showOtpResendState() {
        const resendBtn = document.getElementById('otpResendBtn');
        if (!resendBtn) return;
        if (otpResendBusy) {
            resendBtn.textContent = t('cashier_signup.otp_sending');
            resendBtn.style.color = '#95a5a6';
            resendBtn.style.cursor = 'default';
            return;
        }
        if (otpCountdown > 0) {
            resendBtn.textContent = t('cashier_signup.otp_resend_countdown', { seconds: otpCountdown });
            resendBtn.style.color = '#95a5a6';
            resendBtn.style.cursor = 'default';
        } else {
            resendBtn.textContent = t('cashier_signup.otp_resend_code');
            resendBtn.style.color = '#2ecc71';
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

    function getOtpCode() { return otpDigits.join(''); }

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
        if (creating) { el.style.display = 'block'; btn.style.display = 'none'; }
        else { el.style.display = 'none'; btn.style.display = 'flex'; }
    }

    // An alert is built from plain strings, so it cannot be reached by
    // data-i18n. Keep the key/param source next to the rendered text: if the
    // visitor changes language while an alert is open, only alerts raised from
    // this page's own keys are re-rendered. Messages that came back from the API
    // are shown verbatim, because the server only ever sends those in Swahili.
    let alertState = null;

    function showAlert(title, message, onOkCallback = null, isSuccessWithBack = false) {
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
        okBtn.innerText = t('cashier_signup.alert_ok');
        alertBox.appendChild(titleEl);
        alertBox.appendChild(msgEl);
        alertBox.appendChild(okBtn);

        if (isSuccessWithBack) {
            okBtn.addEventListener('click', () => {
                overlay.remove();
                alertState = null;
                if (document.referrer && document.referrer.includes(window.location.host)) {
                    window.history.back();
                } else {
                    window.location.href = '/home';
                }
                if (onOkCallback) onOkCallback();
            });
        } else {
            okBtn.addEventListener('click', () => {
                overlay.remove();
                alertState = null;
                if (onOkCallback) onOkCallback();
            });
        }
        overlay.appendChild(alertBox);
        document.body.appendChild(overlay);
    }

    // Re-render the visible alert's own text after a language switch. The button
    // keeps the handler it was bound to when the alert was opened.
    function renderAlertStrings() {
        if (!alertState) return;
        const overlay = document.querySelector('.custom-alert');
        if (!overlay) return;
        const titleEl = overlay.querySelector('.alert-title');
        const msgEl = overlay.querySelector('.alert-message');
        const okBtn = overlay.querySelector('.alert-btn');
        if (titleEl && alertState.titleKey) titleEl.innerText = t(alertState.titleKey, alertState.titleParams);
        if (msgEl && alertState.messageKey) msgEl.innerText = t(alertState.messageKey, alertState.messageParams);
        if (okBtn) okBtn.innerText = t('cashier_signup.alert_ok');
    }

    function showNetworkError() {
        showAlert({ key: 'cashier_signup.alert_network_error' }, { key: 'cashier_signup.err_no_internet' });
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
            email: emailInput.value.trim(),
            phone: phoneInput.value.trim(),
            password: passwordInput.value,
            confirmPassword: confirmPasswordInput.value
        };
    }

    function validateForm(form) {
        if (!form.email || !form.password || !form.full_name || !form.business_name || !form.business_location) {
            showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_required_fields' });
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(form.email)) {
            showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_email_invalid' });
            return false;
        }
        if (form.password !== form.confirmPassword) {
            showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_passwords_dont_match' });
            return false;
        }
        if (form.password.length < 6) {
            showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_password_short' });
            return false;
        }
        return true;
    }

    async function handleSignup() {
        if (loading) return;
        const form = getFormValues();
        if (!validateForm(form)) return;

        setLoadingState(true);

        try {
            // Step 1: Check if business exists and has admin
            const checkResponse = await fetch(`${API_BASE_URL}/api/check-business`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ business_name: form.business_name.trim() })
            });
            const checkData = await checkResponse.json();

            if (!checkResponse.ok) {
                setLoadingState(false);
                showAlert({ key: 'cashier_signup.alert_error' }, checkData.error || { key: 'cashier_signup.err_business_verify_failed' });
                return;
            }
            if (!checkData.exists) {
                setLoadingState(false);
                showAlert({ key: 'cashier_signup.err_business_not_found_title' },
                    { key: 'cashier_signup.err_business_not_found', params: { business_name: form.business_name } });
                return;
            }
            if (!checkData.hasAdmin) {
                setLoadingState(false);
                showAlert({ key: 'cashier_signup.err_no_admin_title' },
                    { key: 'cashier_signup.err_no_admin', params: { business_name: form.business_name } });
                return;
            }

            // Step 2: Initiate registration (send OTP)
            const initiateResponse = await fetch(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    email: form.email,
                    password: form.password,
                    role: 'seller',
                    full_name: form.full_name,
                    phone: form.phone || '',
                    business_name: form.business_name,
                    business_location: form.business_location
                })
            });
            const initiateData = await initiateResponse.json();

            if (initiateResponse.ok && (initiateData.success || initiateData.requiresOtp)) {
                pendingEmail = form.email;
                formValues = form;
                setLoadingState(false);
                showOtpModal(form.email);
            } else {
                setLoadingState(false);
                showAlert({ key: 'cashier_signup.alert_error' }, initiateData.error || { key: 'cashier_signup.err_signup_failed' });
            }
        } catch (error) {
            console.error('Signup error:', error);
            setLoadingState(false);
            if (error.message && (error.message.includes('Network request failed') || error.message.includes('fetch'))) {
                showNetworkError();
            } else {
                showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_try_again' });
            }
        }
    }

    // The modal's own labels are baked into the markup that getOtpModalHtml()
    // returns, so data-i18n cannot reach them. A language switch therefore has
    // to rebuild the whole modal. renderOtpModal() reads the digits, countdown,
    // error and spinner state back out of the variables above, so nothing the
    // visitor typed and no progress is lost.
    function renderOtpModal() {
        const current = document.getElementById('otpModal');
        if (!current) return;
        current.remove();
        buildOtpModal();
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

        // Start the resend countdown. It is started here and not inside
        // buildOtpModal(), because a rebuild happens on every language switch and
        // must not hand the visitor a fresh 30 seconds.
        startOtpCountdown();
    }

    // Build the modal DOM from scratch and re-apply the live state onto it.
    // Called when the modal first opens and again on every language switch.
    function buildOtpModal() {
        const wrapper = document.createElement('div');
        wrapper.innerHTML = getOtpModalHtml(pendingEmail);
        document.body.appendChild(wrapper.firstElementChild);

        // The sentence carrying the email address is assembled in JS (the address
        // is bolded inside it), so data-i18n cannot fill it.
        renderOtpSentTo(pendingEmail);
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
                    if (i < OTP_LENGTH - 1) document.getElementById('otpBox' + (i + 1)).focus();
                } else { box.classList.remove('filled'); }
                setOtpError(null);
            });
            box.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !otpDigits[i] && i > 0) {
                    otpDigits[i - 1] = '';
                    document.getElementById('otpBox' + (i - 1)).value = '';
                    document.getElementById('otpBox' + (i - 1)).classList.remove('filled');
                    document.getElementById('otpBox' + (i - 1)).focus();
                }
            });
            box.addEventListener('keypress', (e) => { if (e.key === 'Enter') handleVerifyOtp(); });
        }
        const checkAutoSubmit = () => {
            if (otpDigits.join('').length === OTP_LENGTH && otpDigits.every(d => d !== '')) handleVerifyOtp();
        };
        for (let i = 0; i < OTP_LENGTH; i++) document.getElementById('otpBox' + i).addEventListener('input', checkAutoSubmit);
        setTimeout(() => { const f = document.getElementById('otpBox0'); if (f) f.focus(); }, 350);
        // Re-apply the in-progress states a rebuild would otherwise drop.
        showOtpResendState();
        if (activeOtpError) setOtpError(activeOtpError);
        setOtpVerifying(otpVerifying);
        setOtpCreating(otpCreating);
        document.getElementById('otpVerifyBtn').addEventListener('click', () => handleVerifyOtp());
        document.getElementById('otpResendBtn').addEventListener('click', () => handleResendOtp(pendingEmail));
        document.getElementById('otpCancelBtn').addEventListener('click', () => { if (otpCountdownTimer) clearInterval(otpCountdownTimer); document.getElementById('otpModal').remove(); });
        document.getElementById('otpCloseBtn').addEventListener('click', () => { if (otpCountdownTimer) clearInterval(otpCountdownTimer); document.getElementById('otpModal').remove(); });
    }

    async function handleVerifyOtp() {
        const code = getOtpCode();
        const otpErrorBox = document.getElementById('otpErrorBox');
        if (!code || code.length !== OTP_LENGTH) {
            setOtpError({ key: 'cashier_signup.otp_err_code_required' });
            return;
        }
        setOtpError(null);
        setOtpVerifying(true);
        try {
            const response = await fetch(`${API_BASE_URL}/api/register/verify-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: pendingEmail, role: 'seller', otp: code })
            });
            const data = await response.json();
            if (response.ok && data.success && data.verified) {
                registrationToken = data.registrationToken;
                setOtpVerifying(false);
                setOtpCreating(true);
                await completeRegistration();
            } else {
                setOtpVerifying(false);
                setOtpError(data.error || { key: 'cashier_signup.otp_err_code_incorrect' });
            }
        } catch (error) {
            setOtpVerifying(false);
            setOtpError({ key: 'cashier_signup.otp_err_network' });
        }
    }

    async function handleResendOtp(email) {
        if (otpCountdown > 0) return;
        const otpResendBtn = document.getElementById('otpResendBtn');
        otpResendBusy = true;
        showOtpResendState();
        try {
            const response = await fetch(`${API_BASE_URL}/api/register/resend-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, role: 'seller' })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                for (let i = 0; i < OTP_LENGTH; i++) {
                    otpDigits[i] = '';
                    const b = document.getElementById('otpBox' + i);
                    if (b) { b.value = ''; b.classList.remove('filled'); }
                }
                setOtpError(null);
                otpResendBusy = false;
                startOtpCountdown();
            } else {
                setOtpError(data.error || { key: 'cashier_signup.otp_failed' });
                otpResendBusy = false;
                showOtpResendState();
            }
        } catch (error) {
            setOtpError({ key: 'cashier_signup.otp_err_network' });
            otpResendBusy = false;
            showOtpResendState();
        }
    }

    async function completeRegistration() {
        setLoadingState(true);
        try {
            const response = await fetch(`${API_BASE_URL}/api/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ verificationToken: registrationToken, email: formValues.email, role: 'seller' })
            });
            const data = await response.json();
            setLoadingState(false);
            if (response.ok) {
                if (otpCountdownTimer) clearInterval(otpCountdownTimer);
                const modal = document.getElementById('otpModal');
                if (modal) modal.remove();
                showAlert({ key: 'cashier_signup.otp_success_title' }, { key: 'cashier_signup.otp_success_message' }, null, true);
            } else {
                showAlert({ key: 'cashier_signup.alert_error' }, data.error || { key: 'cashier_signup.err_signup_failed' });
            }
        } catch (error) {
            setLoadingState(false);
            showAlert({ key: 'cashier_signup.alert_error' }, { key: 'cashier_signup.err_try_again' });
        }
    }

    function goBack() {
        if (document.referrer && document.referrer.includes(window.location.host)) {
            window.history.back();
        } else {
            window.location.href = '/home';
        }
    }

    signupBtn.addEventListener('click', handleSignup);

    // The static form, header and info card are handled by the shared runtime's
    // data-i18n pass. The OTP modal and any open alert are built in JS, so they
    // have to be re-rendered here or they would keep the previous language.
    if (window.DM) {
        window.DM.onChange(() => {
            renderOtpModal();
            renderAlertStrings();
        });
    }
    backBtn.addEventListener('click', goBack);
    const inputs = [fullNameInput, businessNameInput, businessLocationInput, emailInput, phoneInput, passwordInput, confirmPasswordInput];
    inputs.forEach(input => {
        if (input) input.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); handleSignup(); } });
    });
    console.log('CashierSignup (Muuzaji) ready — OTP-based registration flow');
</script>
</body>
</html>
@endverbatim