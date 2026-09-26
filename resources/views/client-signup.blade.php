@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title>Dukamkononi | Usajili Mteja</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #ffffff;
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
            padding: 0 25px;
            padding-top: 60px;
            padding-bottom: 40px;
            min-height: 100vh;
        }

        .content {
            width: 100%;
            max-width: 550px;
            margin: 0 auto;
        }

        /* Header styles */
        .header-container {
            text-align: center;
            margin-bottom: 40px;
        }

        .header {
            font-size: 28px;
            font-weight: 800;
            text-align: center;
            margin-bottom: 10px;
            color: #3498db;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sub-header {
            font-size: 16px;
            color: #7f8c8d;
            text-align: center;
        }

        /* Form styles */
        .form-container {
            width: 100%;
            margin-bottom: 30px;
        }

        .input-group {
            margin-bottom: 20px;
        }

        .label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            margin-left: 5px;
            display: block;
        }

        .input-field {
            width: 100%;
            height: 56px;
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 0 20px;
            font-size: 16px;
            font-family: 'Inter', sans-serif;
            border: 1px solid #e0e0e0;
            transition: all 0.2s ease;
            color: #2c3e50;
        }

        .input-field:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
            background-color: #ffffff;
        }

        .input-field::placeholder {
            color: #95a5a6;
        }

        /* Signup button */
        .signup-btn {
            width: 100%;
            height: 58px;
            background-color: #3498db;
            border-radius: 12px;
            justify-content: center;
            align-items: center;
            display: flex;
            margin-top: 10px;
            margin-bottom: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .signup-btn:active {
            transform: scale(0.97);
            opacity: 0.9;
        }

        .signup-btn.disabled {
            background-color: #85c1e9;
            opacity: 0.8;
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

        /* Terms text */
        .terms-container {
            text-align: center;
            margin-bottom: 25px;
        }

        .terms-text {
            font-size: 12px;
            color: #7f8c8d;
            line-height: 18px;
        }

        /* Back button */
        .back-btn {
            width: 100%;
            padding: 15px 0;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .back-btn:active {
            opacity: 0.7;
        }

        .back-btn-text {
            font-size: 16px;
            font-weight: 600;
            color: #3498db;
            text-decoration: none;
        }

        /* Footer info card */
        .footer-info {
            background-color: #f0f8ff;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
        }

        .footer-text {
            font-size: 16px;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .footer-bullet {
            font-size: 14px;
            color: #3498db;
            margin-left: 10px;
            margin-bottom: 5px;
            display: block;
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
                padding: 40px 20px;
                padding-top: 50px;
            }
            .header {
                font-size: 24px;
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
            background-color: #3498db;
            padding: 12px;
            border-radius: 40px;
            font-weight: 700;
            color: white;
            text-align: center;
            cursor: pointer;
        }
        .alert-btn-success {
            background-color: #27ae60;
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
            border-color: #3498db;
        }
        .otp-box.filled {
            border-color: #3498db;
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
<div class="keyboard-avoid">
    <div class="scroll-container">
        <div class="content">
            <!-- Header -->
            <div class="header-container">
                <h1 class="header">JISAJILI KAMA MTEJA</h1>
                <p class="sub-header">Unda akaunti yako ya Mteja</p>
            </div>

            <!-- Form Container -->
            <div class="form-container">
                <!-- Jina Kamili -->
                <div class="input-group">
                    <label class="label">Jina Kamili *</label>
                    <input type="text" class="input-field" id="full_name" placeholder="Andika jina lako kamili" autocomplete="name">
                </div>

                <!-- Barua Pepe -->
                <div class="input-group">
                    <label class="label">Barua Pepe *</label>
                    <input type="email" class="input-field" id="email" placeholder="example@email.com" autocomplete="email">
                </div>

                <!-- Namba ya Simu -->
                <div class="input-group">
                    <label class="label">Namba ya Simu (Si lazima)</label>
                    <input type="tel" class="input-field" id="phone" placeholder="07XXXXXXXX" autocomplete="tel">
                </div>

                <!-- Nenosiri -->
                <div class="input-group">
                    <label class="label">Nenosiri *</label>
                    <input type="password" class="input-field" id="password" placeholder="Andika nenosiri lako (herufi 6 au zaidi)" autocomplete="new-password">
                </div>

                <!-- Rudia Nenosiri -->
                <div class="input-group">
                    <label class="label">Rudia Nenosiri *</label>
                    <input type="password" class="input-field" id="confirmPassword" placeholder="Andika nenosiri tena" autocomplete="off">
                </div>

                <!-- Signup Button -->
                <div id="signupBtn" class="signup-btn">
                    <span id="signupBtnText" class="signup-btn-text">JISAJILI SASA</span>
                    <div id="loaderSpinner" style="display: none;" class="loader"></div>
                </div>

                <!-- Terms & Conditions -->
                <div class="terms-container">
                    <p class="terms-text">
                        Kwa kubonyeza "Jisajili Sasa", unakubali Sheria na Masharti yetu
                    </p>
                </div>

                <!-- Back to Login -->
                <div id="backBtn" class="back-btn">
                    <span class="back-btn-text">← Rudi kwenye Ukurasa wa Kuingia</span>
                </div>
            </div>

            <!-- Footer Info -->
            <div class="footer-info">
                <p class="footer-text">Akaunti ya Mteja inakuruhusu:</p>
                <span class="footer-bullet">• Kuangalia matangazo ya bidhaa</span>
                <span class="footer-bullet">• Kuwasiliana na wauzaji</span>
                <span class="footer-bullet">• Kupata huduma kwa urahisi</span>
            </div>
        </div>
    </div>
</div>

<script>
    // ------------------------------
    // DUKAMKONONI: ClientSignup (Mteja) web replica
    // OTP-based registration: initiate → verify-otp → register
    const API_BASE_URL = '';

    const fullNameInput = document.getElementById('full_name');
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

    // OTP Modal — 6 individual digit boxes with countdown
    const OTP_LENGTH = 6;
    const OTP_RESEND_COOLDOWN = 30;
    let otpDigits = [];
    let otpCountdown = OTP_RESEND_COOLDOWN;
    let otpCountdownTimer = null;

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
                        <div style="width:64px;height:64px;border-radius:32px;background:#3498db1a;display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px;">
                            <span style="font-size:34px;">✉️</span>
                        </div>
                    </div>
                    <div class="alert-title" style="font-size:20px;font-weight:bold;color:#2c3e50;margin-bottom:6px;text-align:center;">Uthibitisho wa Barua Pepe</div>
                    <div style="font-size:13px;color:#7f8c8d;text-align:center;line-height:19px;margin-bottom:22px;padding:0 6px;">Tumetuma msimbo wa tarakimu 6 kwenye barua pepe: <strong>${email}</strong></div>
                    <div class="otp-boxes" id="otpBoxes">${boxesHtml}</div>
                    <div class="otp-error-box" id="otpErrorBox">
                        <span class="otp-error-icon">⚠️</span>
                        <span class="otp-error-text" id="otpErrorText"></span>
                    </div>
                    <button id="otpVerifyBtn" class="alert-btn" style="width:100%;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;cursor:pointer;border:none;font-size:16px;font-weight:bold;color:white;background:#3498db;">
                        <span>✓</span> <span id="otpVerifyText">Hakiki Msimbo</span>
                        <span id="otpVerifyLoader" style="display:none;"><div class="loader" style="width:20px;height:20px;border-width:2px;"></div></span>
                    </button>
                    <div id="otpCreatingAccount" style="display:none;text-align:center;margin-bottom:14px;">
                        <div class="loader" style="margin:0 auto 8px;border-top-color:#3498db;"></div>
                        <div class="otp-creating-text">Inaunda akaunti...</div>
                    </div>
                    <div style="text-align:center;margin-bottom:14px;">
                        <span style="font-size:13px;color:#7f8c8d;">Hujapokea msimbo? </span>
                        <span id="otpResendBtn" style="font-size:13px;font-weight:700;color:#3498db;cursor:pointer;"></span>
                    </div>
                    <div style="text-align:center;">
                        <span id="otpCancelBtn" style="font-size:13px;color:#7f8c8d;cursor:pointer;display:inline-flex;align-items:center;gap:5px;text-decoration:underline;">← Badilisha barua pepe</span>
                    </div>
                </div>
            </div>
        `;
    }

    function showOtpResendState() {
        const resendBtn = document.getElementById('otpResendBtn');
        if (!resendBtn) return;
        if (otpCountdown > 0) {
            resendBtn.textContent = `Tuma tena (${otpCountdown}s)`;
            resendBtn.style.color = '#95a5a6';
            resendBtn.style.cursor = 'default';
        } else {
            resendBtn.textContent = 'Tuma msimbo tena';
            resendBtn.style.color = '#3498db';
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

    function setOtpError(msg) {
        const box = document.getElementById('otpErrorBox');
        const text = document.getElementById('otpErrorText');
        if (msg) {
            box.classList.add('show');
            text.textContent = msg;
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
        const el = document.getElementById('otpCreatingAccount');
        const btn = document.getElementById('otpVerifyBtn');
        if (creating) { el.style.display = 'block'; btn.style.display = 'none'; }
        else { el.style.display = 'none'; btn.style.display = 'flex'; }
    }

    function showAlert(title, message, onOkCallback = null, isSuccessWithNavigate = false) {
        const existingAlert = document.querySelector('.custom-alert');
        if (existingAlert) existingAlert.remove();
        const overlay = document.createElement('div');
        overlay.className = 'custom-alert';
        const alertBox = document.createElement('div');
        alertBox.className = 'alert-box';
        const titleEl = document.createElement('div');
        titleEl.className = 'alert-title';
        titleEl.innerText = title;
        const msgEl = document.createElement('div');
        msgEl.className = 'alert-message';
        msgEl.innerText = message;
        const okBtn = document.createElement('div');
        okBtn.className = 'alert-btn';
        okBtn.innerText = 'Sawa';
        alertBox.appendChild(titleEl);
        alertBox.appendChild(msgEl);
        alertBox.appendChild(okBtn);
        if (isSuccessWithNavigate) {
            okBtn.innerText = 'Ingia Sasa';
            okBtn.classList.add('alert-btn-success');
            okBtn.addEventListener('click', () => {
                overlay.remove();
                window.location.href = '/login?role=mteja';
                if (onOkCallback) onOkCallback();
            });
        } else {
            okBtn.addEventListener('click', () => {
                overlay.remove();
                if (onOkCallback) onOkCallback();
            });
        }
        overlay.appendChild(alertBox);
        document.body.appendChild(overlay);
    }

    function showNetworkError() {
        showAlert('Hitilafu ya Mtandao', 'Hakuna muunganisho wa mtandao. Tafadhali hakikisha umeunganishwa kwenye internet.');
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
            email: emailInput.value.trim(),
            phone: phoneInput.value.trim(),
            password: passwordInput.value,
            confirmPassword: confirmPasswordInput.value
        };
    }

    function validateForm(form) {
        if (!form.email || !form.password || !form.full_name) {
            showAlert('Hitilafu', 'Tafadhali jaza sehemu zote zinazohitajika');
            return false;
        }
        if (form.password !== form.confirmPassword) {
            showAlert('Hitilafu', 'Nenosiri hazifanani');
            return false;
        }
        if (form.password.length < 6) {
            showAlert('Hitilafu', 'Nenosiri lazima liwe na herufi 6 au zaidi');
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(form.email)) {
            showAlert('Hitilafu', 'Tafadhali ingiza barua pepe sahihi');
            return false;
        }
        return true;
    }

    function resetForm() {
        fullNameInput.value = '';
        emailInput.value = '';
        phoneInput.value = '';
        passwordInput.value = '';
        confirmPasswordInput.value = '';
    }

    async function handleSignup() {
        if (loading) return;
        const form = getFormValues();
        if (!validateForm(form)) return;

        setLoadingState(true);

        try {
            const response = await fetch(`${API_BASE_URL}/api/register/initiate`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({
                    email: form.email,
                    password: form.password,
                    role: 'customer',
                    full_name: form.full_name,
                    phone: form.phone || null
                })
            });
            const data = await response.json();

            if (response.ok && (data.success || data.requiresOtp)) {
                pendingEmail = form.email;
                formValues = form;
                setLoadingState(false);
                showOtpModal(form.email);
            } else {
                setLoadingState(false);
                showAlert('Hitilafu', data.error || 'Hitilafu imetokea wakati wa kujisajili');
            }
        } catch (error) {
            console.error('Signup error:', error);
            setLoadingState(false);
            if (error.message && (error.message.includes('Network request failed') || error.message.includes('fetch'))) {
                showNetworkError();
            } else {
                showAlert('Hitilafu', 'Hitilafu imetokea. Tafadhali jaribu tena.');
            }
        }
    }

    function showOtpModal(email) {
        const existing = document.querySelector('.custom-alert');
        if (existing) existing.remove();
        otpDigits = Array(OTP_LENGTH).fill('');
        otpCountdown = OTP_RESEND_COOLDOWN;
        const wrapper = document.createElement('div');
        wrapper.innerHTML = getOtpModalHtml(email);
        document.body.appendChild(wrapper.firstElementChild);
        for (let i = 0; i < OTP_LENGTH; i++) {
            const box = document.getElementById('otpBox' + i);
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
        startOtpCountdown();
        document.getElementById('otpVerifyBtn').addEventListener('click', () => handleVerifyOtp());
        document.getElementById('otpResendBtn').addEventListener('click', () => handleResendOtp(email));
        document.getElementById('otpCancelBtn').addEventListener('click', () => { if (otpCountdownTimer) clearInterval(otpCountdownTimer); document.getElementById('otpModal').remove(); });
        document.getElementById('otpCloseBtn').addEventListener('click', () => { if (otpCountdownTimer) clearInterval(otpCountdownTimer); document.getElementById('otpModal').remove(); });
    }

    async function handleVerifyOtp() {
        const code = getOtpCode();
        if (!code || code.length !== OTP_LENGTH) {
            setOtpError('Tafadhali weka msimbo wa tarakimu 6');
            return;
        }
        setOtpError(null);
        setOtpVerifying(true);
        try {
            const response = await fetch(`${API_BASE_URL}/api/register/verify-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email: pendingEmail, role: 'customer', otp: code })
            });
            const data = await response.json();
            if (response.ok && data.success && data.verified) {
                registrationToken = data.registrationToken;
                setOtpVerifying(false);
                setOtpCreating(true);
                await completeRegistration();
            } else {
                setOtpVerifying(false);
                setOtpError(data.error || 'Msimbo si sahihi');
            }
        } catch (error) {
            setOtpVerifying(false);
            setOtpError('Hitilafu ya mtandao');
        }
    }

    async function handleResendOtp(email) {
        if (otpCountdown > 0) return;
        const otpResendBtn = document.getElementById('otpResendBtn');
        otpResendBtn.textContent = 'Inatuma...';
        otpResendBtn.style.color = '#95a5a6';
        try {
            const response = await fetch(`${API_BASE_URL}/api/register/resend-otp`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, role: 'customer' })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                for (let i = 0; i < OTP_LENGTH; i++) {
                    otpDigits[i] = '';
                    const b = document.getElementById('otpBox' + i);
                    if (b) { b.value = ''; b.classList.remove('filled'); }
                }
                setOtpError(null);
                startOtpCountdown();
            } else {
                setOtpError(data.error || 'Imeshindikana');
                otpResendBtn.textContent = 'Tuma msimbo tena';
                otpResendBtn.style.color = '#3498db';
            }
        } catch (error) {
            setOtpError('Hitilafu ya mtandao');
            otpResendBtn.textContent = 'Tuma msimbo tena';
            otpResendBtn.style.color = '#3498db';
        }
    }

    async function completeRegistration() {
        setLoadingState(true);
        try {
            const response = await fetch(`${API_BASE_URL}/api/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ verificationToken: registrationToken, email: formValues.email, role: 'customer' })
            });
            const data = await response.json();
            setLoadingState(false);
            if (response.ok) {
                if (otpCountdownTimer) clearInterval(otpCountdownTimer);
                const modal = document.getElementById('otpModal');
                if (modal) modal.remove();
                resetForm();
                showAlert('Mafanikio!', 'Akaunti ya Mteja imeundwa kikamilifu! Sasa unaweza kuingia.', null, true);
            } else {
                showAlert('Hitilafu', data.error || 'Hitilafu imetokea wakati wa kujisajili');
            }
        } catch (error) {
            setLoadingState(false);
            showAlert('Hitilafu', 'Hitilafu imetokea. Tafadhali jaribu tena.');
        }
    }

    function goBackToLogin() {
        window.location.href = '/login?role=mteja';
    }

    signupBtn.addEventListener('click', handleSignup);
    backBtn.addEventListener('click', goBackToLogin);
    const inputs = [fullNameInput, emailInput, phoneInput, passwordInput, confirmPasswordInput];
    inputs.forEach(input => {
        if (input) input.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); handleSignup(); } });
    });
    if (fullNameInput) fullNameInput.focus();
    console.log('ClientSignup (Mteja) ready — OTP-based registration flow');
</script>
</body>
</html>
@endverbatim