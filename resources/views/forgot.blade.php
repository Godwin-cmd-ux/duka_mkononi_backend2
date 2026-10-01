@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="forgot.page_title">Dukamkononi | Sahau Nenosiri</title>
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
            padding: 50px 20px 30px;
            min-height: 100vh;
        }

        .content {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
        }

        /* Header styles */
        .header-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 0 10px;
        }

        .back-btn-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background-color: #f1f2f6;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .back-btn-icon:active {
            opacity: 0.7;
            transform: scale(0.95);
        }

        .header {
            font-size: 20px;
            font-weight: 800;
            text-align: center;
            flex: 1;
        }

        .role-badge {
            padding: 6px 14px;
            border-radius: 30px;
            background-color: #f1f2f6;
            font-size: 12px;
            font-weight: 600;
        }

        /* Step Indicator */
        .step-indicator {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
            padding: 0 10px;
        }

        .step-item {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .step-circle {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border-width: 2px;
            border-style: solid;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 8px;
            transition: all 0.2s;
        }

        .step-number {
            font-size: 14px;
            font-weight: 700;
        }

        .step-label {
            font-size: 10px;
            font-weight: 600;
            text-align: center;
        }

        .step-line {
            position: absolute;
            top: 18px;
            right: -50%;
            width: 100%;
            height: 2px;
            z-index: 0;
        }

        .step-line.visible {
            background-color: #ddd;
        }

        /* Form Container */
        .form-container {
            background-color: white;
            border-radius: 24px;
            padding: 28px 24px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .icon-container {
            text-align: center;
            margin-bottom: 20px;
        }

        .success-icon-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .success-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border-width: 3px;
            border-style: solid;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }

        .instruction-text {
            font-size: 16px;
            text-align: center;
            color: #2c3e50;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .email-text {
            font-size: 15px;
            font-weight: 700;
            text-align: center;
            color: #e74c3c;
            margin-bottom: 25px;
            word-break: break-all;
            padding: 0 10px;
        }

        .sub-instruction {
            font-size: 14px;
            text-align: center;
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .input-group {
            display: flex;
            align-items: center;
            background-color: #f8f9fa;
            border-radius: 14px;
            border: 1px solid #e0e0e0;
            padding: 0 16px;
            margin-bottom: 16px;
        }

        .input-icon {
            margin-right: 10px;
            display: flex;
            align-items: center;
        }

        .input-field {
            flex: 1;
            height: 50px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            background: transparent;
            border: none;
            outline: none;
            color: #2c3e50;
        }

        .input-field::placeholder {
            color: #95a5a6;
        }

        .action-btn {
            width: 100%;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            border-radius: 14px;
            border: none;
            margin-top: 10px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.2s;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .action-btn:active {
            transform: scale(0.97);
            opacity: 0.9;
        }

        .action-btn.disabled {
            opacity: 0.6;
            transform: none;
            cursor: not-allowed;
        }

        .resend-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin: 15px 0;
        }

        .resend-text {
            font-size: 14px;
            color: #7f8c8d;
        }

        .resend-link {
            font-size: 14px;
            font-weight: 600;
            text-decoration: underline;
            cursor: pointer;
        }

        .secondary-btn {
            width: 100%;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            border-radius: 14px;
            border: 1px solid #ddd;
            background: transparent;
            cursor: pointer;
            transition: all 0.2s;
        }

        .secondary-btn:active {
            background-color: #f5f5f5;
        }

        .success-box {
            background-color: #e8f6ef;
            border-radius: 14px;
            padding: 20px;
            text-align: center;
            border: 2px solid #2ecc71;
            margin-bottom: 25px;
        }

        .success-title {
            font-size: 20px;
            font-weight: 800;
            color: #27ae60;
            margin-bottom: 10px;
        }

        .success-message {
            font-size: 14px;
            color: #2c3e50;
            line-height: 1.5;
        }

        .help-box {
            background-color: #fff8e1;
            border-left: 4px solid #ff9800;
            border-radius: 14px;
            padding: 15px;
            margin-top: 10px;
        }

        .help-header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .help-title {
            font-size: 14px;
            font-weight: 700;
            color: #ff9800;
        }

        .help-point {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
        }

        .help-text {
            font-size: 13px;
            color: #ff9800;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }

        .footer-text {
            font-size: 12px;
            color: #95a5a6;
        }

        .loader {
            display: inline-block;
            width: 22px;
            height: 22px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.7s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Custom Alert */
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
        }
        .alert-box {
            background: white;
            border-radius: 28px;
            width: 85%;
            max-width: 320px;
            padding: 24px 20px 20px;
            text-align: center;
        }
        .alert-title {
            font-size: 20px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .alert-message {
            font-size: 14px;
            color: #5d6d7e;
            margin-bottom: 24px;
            line-height: 1.4;
        }
        .alert-btn {
            padding: 12px;
            border-radius: 40px;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            color: white;
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
            <!-- Header -->
            <div class="header-container">
                <div id="backBtnIcon" class="back-btn-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M15 18L9 12L15 6" stroke="#2c3e50" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </div>
                <h1 id="headerTitle" class="header" data-i18n="forgot.header_title">SAHIUSHA NENOSIRI</h1>
                <div id="roleBadge" class="role-badge">MTEJA</div>
            </div>

            <!-- Step Indicator -->
            <div class="step-indicator" id="stepIndicator"></div>

            <!-- Form Container (dynamic) -->
            <div id="formContainer" class="form-container"></div>

            <!-- Help Box (shown only on step 2) -->
            <div id="helpBox" style="display: none;" class="help-box"></div>

            <!-- Footer -->
            <div class="footer">
                <p class="footer-text" data-i18n="forgot.footer_copyright">DukaMkononi © 2025</p>
                <p class="footer-text" data-i18n="forgot.footer_service">Huduma ya Kubadilisha Nenosiri</p>
            </div>
        </div>
    </div>
</div>

<script>
    // ------------------------------
    // DUKAMKONONI: ForgotPasswordScreen web replica
    // Multi-step password reset: email -> code -> new password -> success
    const API_BASE_URL = '';

    // The locale the visitor is browsing in. window.DM is set up synchronously
    // by the language partial, but fall back to the attribute the server
    // rendered so this never depends on locales.json.
    function activeLocale() {
        if (window.DM) return window.DM.locale();
        return document.documentElement.getAttribute('data-dm-locale') || 'sw';
    }

    // Text this page builds in JS (step labels, alerts, validation) comes from
    // locales.json via the shared DM runtime. Until it has loaded we keep the
    // Swahili source string, so a failed locales.json can never blank the page.
    const SW = {
        role_customer: 'MTEJA', role_seller: 'MUUZAJI', role_admin: 'MSIMAMIZI', role_user: 'MTUMIAJI',
        label_email: 'Barua Pepe', label_code: 'Msimbo', label_password: 'Nenosiri', label_done: 'Tayari',
        instruction_email: 'Weka barua pepe yako ili kupokea msimbo wa kubadilisha nenosiri',
        btn_send_code: '📤 Tuma Msimbo',
        help_title: 'Maelekezo:',
        help_expire: 'Msimbo utaisha muda wake ndani ya dakika 15',
        help_spam: 'Angalia folder ya spam iwapo hupokei barua pepe',
        help_digits: 'Msimbo ni namba 6 (kama: 123456)',
        instruction_code_sent: 'Tumeutumia msimbo wa tarakimu 6 kwenye barua pepe:',
        instruction_code_enter: 'Weka msimbo hapa chini:',
        placeholder_code: 'Msimbo (6 tarakimu)',
        btn_verify_code: '✓ Hakiki Msimbo',
        resend_question: 'Hukupokei msimbo?',
        resend: 'Tuma tena',
        resend_countdown: 'Tuma tena ({mm}:{ss})',
        btn_change_email: '← Badilisha Barua Pepe',
        instruction_new_password: 'Weka nenosiri jipya lako',
        placeholder_new_password: 'Nenosiri Jipya (angalau herufi 6)',
        placeholder_confirm_password: 'Rudia Nenosiri Jipya',
        btn_change_password: '🔄 Badilisha Nenosiri',
        success_title: 'Nenosiri Limebadilishwa!',
        success_message: 'Nenosiri lako limebadilishwa kikamilifu. Unaweza kuingia sasa kwa nenosiri jipya.',
        btn_sign_in_now: '🔐 Ingia Sasa',
        btn_reset_again: '⟳ Badilisha Nenosiri Tena',
        alert_ok: 'Sawa',
        alert_error: 'Hitilafu',
        alert_success: 'Mafanikio!',
        alert_wait: 'Subiri',
        err_email_required: 'Tafadhali jaza barua pepe yako',
        err_email_invalid: 'Tafadhali andika barua pepe sahihi',
        err_no_connection: 'Haikuweza kuunganishwa na server',
        err_code_sent: 'Msimbo umepelekwa kwenye barua pepe yako',
        err_generic: 'Hitilafu imetokea',
        err_request_failed: 'Hitilafu wakati wa kutuma ombi',
        err_code_required: 'Tafadhali jaza msimbo wa tarakimu 6',
        err_code_verified: 'Msimbo umehakikiwa kikamilifu!',
        err_code_incorrect: 'Msimbo si sahihi',
        err_code_incorrect_expired: 'Msimbo si sahihi au umeisha muda',
        err_password_required: 'Tafadhali jaza nenosiri jipya na uthibitishaji',
        err_password_short: 'Nenosiri lazima liwe na herufi 6 au zaidi',
        err_password_mismatch: 'Nenosiri jipya na uthibitishaji havifanani',
        err_password_changed: 'Nenosiri limebadilishwa kikamilifu!',
        err_change_failed: 'Imeshindikana kubadilisha nenosiri',
        err_session_expired: 'Muda wa kubadilisha nenosiri umeisha. Tafadhali anza upya.',
        err_resend_wait: 'Unaweza kutuma tena msimbo baada ya sekunde {countdown}'
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

    // Get role from URL params (default: mteja)
    const urlParams = new URLSearchParams(window.location.search);
    let role = urlParams.get('role') || 'mteja';

    // Role mapping for backend
    function getBackendRole(frontendRole) {
        const map = { 'mteja': 'client', 'muuzaji': 'seller', 'msimamizi': 'admin' };
        return map[frontendRole] || frontendRole;
    }

    function getRoleColor() {
        const colors = { 'mteja': '#3498db', 'muuzaji': '#2ecc71', 'msimamizi': '#e74c3c' };
        return colors[role] || '#2c3e50';
    }

    function getRoleTitle() {
        const keys = { 'mteja': 'forgot.role_customer', 'muuzaji': 'forgot.role_seller', 'msimamizi': 'forgot.role_admin' };
        return t(keys[role] || 'forgot.role_user');
    }

    // Update header colors
    function updateTheme() {
        const color = getRoleColor();
        document.getElementById('headerTitle').style.color = color;
        const badge = document.getElementById('roleBadge');
        badge.style.color = color;
        badge.textContent = getRoleTitle();
        
        // Also apply dynamic styles to step circles and buttons later in render
    }

    // State
    let step = 1; // 1: email, 2: code, 3: new password, 4: success
    let loading = false;
    let countdown = 0;
    let countdownInterval = null;
    
    // Form data
    let email = '';
    let resetCode = '';
    let newPassword = '';
    let confirmPassword = '';
    let verificationToken = '';

    // Helper: Show alert
    function showAlert(title, message, onOk = null) {
        const existing = document.querySelector('.custom-alert');
        if (existing) existing.remove();
        const overlay = document.createElement('div');
        overlay.className = 'custom-alert';
        const box = document.createElement('div');
        box.className = 'alert-box';
        const titleEl = document.createElement('div');
        titleEl.className = 'alert-title';
        titleEl.innerText = title;
        const msgEl = document.createElement('div');
        msgEl.className = 'alert-message';
        msgEl.innerText = message;
        const btn = document.createElement('div');
        btn.className = 'alert-btn';
        btn.innerText = t('forgot.alert_ok');
        btn.style.backgroundColor = getRoleColor();
        btn.onclick = () => {
            overlay.remove();
            if (onOk) onOk();
        };
        box.appendChild(titleEl);
        box.appendChild(msgEl);
        box.appendChild(btn);
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }

    function setLoading(loadingState) {
        loading = loadingState;
        renderForm(); // re-render to show spinner
    }

    // API: Test connection
    async function testAPIConnection() {
        try {
            const res = await fetch(`${API_BASE_URL}/api/test`, { method: 'GET', headers: { 'Accept': 'application/json' } });
            return res.ok;
        } catch { return false; }
    }

    // Step 1: Request reset code
    async function handleRequestResetCode() {
        const emailInput = document.getElementById('emailInput');
        if (!emailInput) return;
        const sanitizedEmail = emailInput.value.trim();
        if (!sanitizedEmail) {
            showAlert(t('forgot.alert_error'), t('forgot.err_email_required'));
            return;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(sanitizedEmail)) {
            showAlert(t('forgot.alert_error'), t('forgot.err_email_invalid'));
            return;
        }
        
        setLoading(true);
        try {
            const connected = await testAPIConnection();
            if (!connected) throw new Error(t('forgot.err_no_connection'));
            
            const backendRole = getBackendRole(role);
            const response = await fetch(`${API_BASE_URL}/api/password-reset/request`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email: sanitizedEmail, role: backendRole })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                email = sanitizedEmail;
                step = 2;
                if (countdownInterval) clearInterval(countdownInterval);
                countdown = 60;
                startCountdown();
                showAlert(t('forgot.alert_success'), data.message || t('forgot.err_code_sent'));
                renderForm();
            } else {
                throw new Error(data.error || t('forgot.err_generic'));
            }
        } catch (err) {
            showAlert(t('forgot.alert_error'), err.message || t('forgot.err_request_failed'));
        } finally {
            setLoading(false);
        }
    }

    // Step 2: Verify code
    async function handleVerifyResetCode() {
        const codeInput = document.getElementById('resetCodeInput');
        if (!codeInput) return;
        const code = codeInput.value.trim();
        if (!code || code.length !== 6) {
            showAlert(t('forgot.alert_error'), t('forgot.err_code_required'));
            return;
        }
        setLoading(true);
        try {
            const backendRole = getBackendRole(role);
            const response = await fetch(`${API_BASE_URL}/api/password-reset/verify-code`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, role: backendRole, resetCode: code })
            });
            const data = await response.json();
            if (response.ok && data.success && data.verified) {
                verificationToken = data.verificationToken;
                step = 3;
                renderForm();
                showAlert(t('forgot.alert_success'), t('forgot.err_code_verified'));
            } else {
                throw new Error(data.error || t('forgot.err_code_incorrect'));
            }
        } catch (err) {
            showAlert(t('forgot.alert_error'), err.message || t('forgot.err_code_incorrect_expired'));
        } finally {
            setLoading(false);
        }
    }

    // Step 3: Set new password
    async function handleSetNewPassword() {
        const pass1 = document.getElementById('newPasswordInput')?.value || '';
        const pass2 = document.getElementById('confirmPasswordInput')?.value || '';
        if (!pass1 || !pass2) {
            showAlert(t('forgot.alert_error'), t('forgot.err_password_required'));
            return;
        }
        if (pass1.length < 6) {
            showAlert(t('forgot.alert_error'), t('forgot.err_password_short'));
            return;
        }
        if (pass1 !== pass2) {
            showAlert(t('forgot.alert_error'), t('forgot.err_password_mismatch'));
            return;
        }
        setLoading(true);
        try {
            const response = await fetch(`${API_BASE_URL}/api/password-reset/confirm`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ verificationToken, newPassword: pass1, confirmPassword: pass2 })
            });
            const data = await response.json();
            if (response.ok && data.success && data.passwordChanged) {
                step = 4;
                renderForm();
                showAlert(t('forgot.alert_success'), t('forgot.err_password_changed'));
            } else {
                throw new Error(data.error || t('forgot.err_change_failed'));
            }
        } catch (err) {
            // The server signals an expired reset session with its own Swahili
            // message, so this check stays on the raw API text rather than the
            // translated copy.
            if (err.message.includes('imeisha')) {
                step = 1;
                renderForm();
                showAlert(t('forgot.alert_error'), t('forgot.err_session_expired'));
            } else {
                showAlert(t('forgot.alert_error'), err.message);
            }
        } finally {
            setLoading(false);
        }
    }

    function handleResendCode() {
        if (countdown > 0) {
            showAlert(t('forgot.alert_wait'), t('forgot.err_resend_wait', { countdown }));
            return;
        }
        handleRequestResetCode();
    }

    function startCountdown() {
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(() => {
            if (countdown > 0) {
                countdown--;
                renderForm(); // update resend button text
            } else {
                clearInterval(countdownInterval);
            }
        }, 1000);
    }

    function handleBackToLogin() {
        window.location.href = `/login?role=${role}`;
    }

    function handleResetProcess() {
        step = 1;
        email = '';
        resetCode = '';
        newPassword = '';
        confirmPassword = '';
        verificationToken = '';
        countdown = 0;
        if (countdownInterval) clearInterval(countdownInterval);
        renderForm();
    }

    // Render dynamic content based on step
    function renderForm() {
        const formContainer = document.getElementById('formContainer');
        const helpBox = document.getElementById('helpBox');
        const stepIndicatorDiv = document.getElementById('stepIndicator');
        const color = getRoleColor();
        
        // Render step indicator
        const steps = [
            { num: 1, label: t('forgot.label_email'), active: step >= 1 },
            { num: 2, label: t('forgot.label_code'), active: step >= 2 },
            { num: 3, label: t('forgot.label_password'), active: step >= 3 },
            { num: 4, label: t('forgot.label_done'), active: step >= 4 }
        ];
        stepIndicatorDiv.innerHTML = steps.map((s, idx) => `
            <div class="step-item" style="position: relative;">
                <div class="step-circle" style="background: ${s.active ? color : '#ecf0f1'}; border-color: ${s.active ? color : '#bdc3c7'};">
                    <span class="step-number" style="color: ${s.active ? 'white' : '#7f8c8d'}">${s.num}</span>
                </div>
                <span class="step-label" style="color: ${s.active ? color : '#95a5a6'}">${s.label}</span>
                ${idx < steps.length - 1 ? `<div class="step-line ${step >= idx + 2 ? 'visible' : ''}" style="background: ${step >= idx + 2 ? color : '#ecf0f1'}"></div>` : ''}
            </div>
        `).join('');
        
        // Step content
        if (step === 1) {
            helpBox.style.display = 'none';
            formContainer.innerHTML = `
                <div class="icon-container">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="1.5">
                        <path d="M21 12C21 16.97 16.97 21 12 21C7.03 21 3 16.97 3 12C3 7.03 7.03 3 12 3" stroke="${color}" stroke-linecap="round"/>
                        <path d="M12 7V12L15 15" stroke="${color}" stroke-linecap="round"/>
                        <circle cx="12" cy="12" r="9" stroke="${color}"/>
                    </svg>
                </div>
                <p class="instruction-text">${t('forgot.instruction_email')}</p>
                <div class="input-group">
                    <div class="input-icon">📧</div>
                    <input type="email" id="emailInput" class="input-field" placeholder="${t('forgot.label_email')}" autocomplete="email">
                </div>
                <button id="requestBtn" class="action-btn" style="background: ${color}">${loading ? '<div class="loader"></div>' : t('forgot.btn_send_code')}</button>
            `;
            document.getElementById('requestBtn')?.addEventListener('click', handleRequestResetCode);
            const emailField = document.getElementById('emailInput');
            if (emailField) emailField.value = email;
        } 
        else if (step === 2) {
            helpBox.style.display = 'block';
            helpBox.innerHTML = `
                <div class="help-header">
                    <span>ℹ️</span>
                    <span class="help-title">${t('forgot.help_title')}</span>
                </div>
                <div class="help-point"><span>⏱️</span><span class="help-text">${t('forgot.help_expire')}</span></div>
                <div class="help-point"><span>⚠️</span><span class="help-text">${t('forgot.help_spam')}</span></div>
                <div class="help-point"><span>🔢</span><span class="help-text">${t('forgot.help_digits')}</span></div>
            `;
            formContainer.innerHTML = `
                <div class="icon-container">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="1.5">
                        <path d="M4 4H20C21.1 4 22 4.9 22 6V18C22 19.1 21.1 20 20 20H4C2.9 20 2 19.1 2 18V6C2 4.9 2.9 4 4 4Z" stroke="${color}"/>
                        <path d="M22 6L12 13L2 6" stroke="${color}"/>
                    </svg>
                </div>
                <p class="instruction-text">${t('forgot.instruction_code_sent')}</p>
                <p class="email-text">${email}</p>
                <p class="sub-instruction">${t('forgot.instruction_code_enter')}</p>
                <div class="input-group">
                    <div class="input-icon">🔒</div>
                    <input type="text" id="resetCodeInput" class="input-field" placeholder="${t('forgot.placeholder_code')}" maxlength="6" pattern="[0-9]*" inputmode="numeric">
                </div>
                <button id="verifyBtn" class="action-btn" style="background: ${color}">${loading ? '<div class="loader"></div>' : t('forgot.btn_verify_code')}</button>
                <div class="resend-container">
                    <span class="resend-text">${t('forgot.resend_question')}</span>
                    <span id="resendLink" class="resend-link" style="color: ${color}">${countdown > 0 ? t('forgot.resend_countdown', { mm: Math.floor(countdown / 60), ss: (countdown % 60).toString().padStart(2, '0') }) : t('forgot.resend')}</span>
                </div>
                <button id="changeEmailBtn" class="secondary-btn">${t('forgot.btn_change_email')}</button>
            `;
            document.getElementById('verifyBtn')?.addEventListener('click', handleVerifyResetCode);
            document.getElementById('resendLink')?.addEventListener('click', handleResendCode);
            document.getElementById('changeEmailBtn')?.addEventListener('click', () => { step = 1; renderForm(); });
            const codeField = document.getElementById('resetCodeInput');
            if (codeField) {
                codeField.value = resetCode;
                codeField.addEventListener('input', (e) => { resetCode = e.target.value.replace(/[^0-9]/g, '').slice(0,6); e.target.value = resetCode; });
            }
        }
        else if (step === 3) {
            helpBox.style.display = 'none';
            formContainer.innerHTML = `
                <div class="icon-container">
                    <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="1.5">
                        <path d="M12 2C8.13 2 5 5.13 5 9V12C3.9 12 3 12.9 3 14V20C3 21.1 3.9 22 5 22H19C20.1 22 21 21.1 21 20V14C21 12.9 20.1 12 19 12V9C19 5.13 15.87 2 12 2Z" stroke="${color}"/>
                        <path d="M12 17V15" stroke="${color}" stroke-linecap="round"/>
                    </svg>
                </div>
                <p class="instruction-text">${t('forgot.instruction_new_password')}</p>
                <div class="input-group">
                    <div class="input-icon">🔑</div>
                    <input type="password" id="newPasswordInput" class="input-field" placeholder="${t('forgot.placeholder_new_password')}">
                </div>
                <div class="input-group">
                    <div class="input-icon">🔑</div>
                    <input type="password" id="confirmPasswordInput" class="input-field" placeholder="${t('forgot.placeholder_confirm_password')}">
                </div>
                <button id="changePasswordBtn" class="action-btn" style="background: ${color}">${loading ? '<div class="loader"></div>' : t('forgot.btn_change_password')}</button>
            `;
            document.getElementById('changePasswordBtn')?.addEventListener('click', handleSetNewPassword);
        }
        else if (step === 4) {
            helpBox.style.display = 'none';
            formContainer.innerHTML = `
                <div class="success-icon-container">
                    <div class="success-circle" style="border-color: ${color}">
                        <svg width="50" height="50" viewBox="0 0 24 24" fill="none" stroke="${color}" stroke-width="3"><path d="M20 6L9 17L4 12" stroke="${color}"/></svg>
                    </div>
                </div>
                <div class="success-box">
                    <div class="success-title">${t('forgot.success_title')}</div>
                    <div class="success-message">${t('forgot.success_message')}</div>
                </div>
                <button id="loginNowBtn" class="action-btn" style="background: ${color}">${t('forgot.btn_sign_in_now')}</button>
                <button id="resetAgainBtn" class="secondary-btn">${t('forgot.btn_reset_again')}</button>
            `;
            document.getElementById('loginNowBtn')?.addEventListener('click', handleBackToLogin);
            document.getElementById('resetAgainBtn')?.addEventListener('click', handleResetProcess);
        }
        
        // disable buttons if loading
        if (loading) {
            const btns = document.querySelectorAll('.action-btn');
            btns.forEach(btn => btn.classList.add('disabled'));
        }
        
        updateTheme();
    }
    
    // Init
    updateTheme();
    renderForm();

    // The whole form is built by renderForm(), so a language switch has to
    // rebuild it for the step labels, instructions, placeholders and buttons to
    // appear in the new language. The email and code fields are re-populated
    // from state by renderForm() itself, so nothing the visitor typed is lost.
    if (window.DM) window.DM.onChange(() => renderForm());

    // Back button handler
    document.getElementById('backBtnIcon')?.addEventListener('click', handleBackToLogin);
</script>
</body>
</html>
@endverbatim