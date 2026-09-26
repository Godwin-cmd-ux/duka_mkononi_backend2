@verbatim
<!DOCTYPE html>
<html lang="sw">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title>Dukamkononi | Usajili Msimamizi</title>
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
<div class="keyboard-avoid">
    <div class="scroll-container">
        <div class="content">
            <h1 class="header">JISAJILI KAMA MSIMAMIZI</h1>
            <p class="subtitle">* Inamaanisha sehemu inayohitajika</p>

            <div class="form-container">
                <input type="text" class="input-field" id="full_name" placeholder="Jina Kamili *" autocomplete="name">
                <input type="text" class="input-field" id="business_name" placeholder="Jina la Biashara *" autocomplete="organization">
                <input type="text" class="input-field" id="business_location" placeholder="Mahali pa Biashara *" autocomplete="address-line1">
                <select class="input-field" id="business_type">
                    <option value="">Aina ya Biashara *</option>
                    <option value="spare_parts">Sehemu za Gari</option>
                    <option value="motorcycle_spares">Spea za Pikipiki</option>
                    <option value="supermarket">Supermarket</option>
                    <option value="pharmacy">Duka la Dawa</option>
                    <option value="electronics">Elektroniki</option>
                    <option value="clothing">Mavazi</option>
                    <option value="hardware">Vifaa Vinene (Hardware)</option>
                    <option value="cosmetics">Vipodozi</option>
                    <option value="perfume">Manukato</option>
                    <option value="restaurant">Mgahawa</option>
                    <option value="furniture">Samani</option>
                    <option value="stationery">Vifaa vya Ofisi na Shule</option>
                    <option value="mobile_accessories">Vifaa vya Simu</option>
                    <option value="computer_shop">Duka la Kompyuta</option>
                    <option value="phone_shop">Duka la Simu</option>
                    <option value="agriculture">Kilimo</option>
                    <option value="construction_materials">Vifaa vya Ujenzi</option>
                    <option value="beauty_salon">Saluni ya Urembo</option>
                    <option value="barbershop">Kinyozi</option>
                    <option value="auto_repair">Karakana ya Magari</option>
                    <option value="general_retail">Rejareja ya Jumla</option>
                    <option value="wholesale">Jumla (Wholesale)</option>
                    <option value="other">Nyingine</option>
                </select>
                <textarea class="input-field" id="business_description" placeholder="Maelezo mafupi ya biashara (mfano: Tunauza sehemu za magari ya Toyota na Nissan...)" style="height:72px;padding:14px 16px;resize:vertical;font-family:'Inter',sans-serif;"></textarea>
                <input type="email" class="input-field" id="email" placeholder="Barua Pepe *" autocomplete="email">
                <input type="tel" class="input-field" id="phone" placeholder="Namba ya Simu" autocomplete="tel">
                <input type="password" class="input-field" id="adminCode" placeholder="Admin Code *">
                <input type="password" class="input-field" id="password" placeholder="Nenosiri *" autocomplete="new-password">
                <input type="password" class="input-field" id="confirmPassword" placeholder="Rudia Nenosiri *" autocomplete="off">
            </div>

            <div id="signupBtn" class="signup-btn">
                <span id="signupBtnText" class="signup-btn-text">JISAJILI SASA</span>
                <div id="loaderSpinner" style="display: none;" class="loader"></div>
            </div>

            <div id="backBtn" class="back-btn">
                <span class="back-btn-text">RUDI NYUMA</span>
            </div>

            <div class="info-card">
                <div class="info-title">🔑 Maelezo muhimu kwa Msimamizi:</div>
                <div class="info-point">Jina la biashara lazima liwe la kipekee na halijasajiliwa</div>
                <div class="info-point">Utakuwa na mamlaka kamili ya kuidhinisha wateja na wauzaji</div>
                <div class="info-point">Unaweza kuanza kutumia mfumo mara moja baada ya kujisajili</div>
                <div class="info-point">Admin Code: ADMIN2024</div>
            </div>
        </div>
    </div>
</div>

<script>
    // ------------------------------
    // DUKAMKONONI: AdminSignup web replica
    // OTP-based registration flow: initiate → verify-otp → register
    const API_BASE_URL = '';

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
                    <div class="alert-title" style="font-size:20px;font-weight:bold;color:#2c3e50;margin-bottom:6px;text-align:center;">Uthibitisho wa Barua Pepe</div>
                    <div style="font-size:13px;color:#7f8c8d;text-align:center;line-height:19px;margin-bottom:22px;padding:0 6px;">Tumetuma msimbo wa tarakimu 6 kwenye barua pepe: <strong>${email}</strong></div>
                    <div class="otp-boxes" id="otpBoxes">${boxesHtml}</div>
                    <div class="otp-error-box" id="otpErrorBox">
                        <span class="otp-error-icon">⚠️</span>
                        <span class="otp-error-text" id="otpErrorText"></span>
                    </div>
                    <button id="otpVerifyBtn" class="alert-btn" style="width:100%;height:54px;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px;cursor:pointer;border:none;font-size:16px;font-weight:bold;color:white;background:#e74c3c;">
                        <span>✓</span> <span id="otpVerifyText">Hakiki Msimbo</span>
                        <span id="otpVerifyLoader" style="display:none;"><div class="loader" style="width:20px;height:20px;border-width:2px;"></div></span>
                    </button>
                    <div id="otpCreatingAccount" style="display:none;text-align:center;margin-bottom:14px;">
                        <div class="loader" style="margin:0 auto 8px;border-top-color:#e74c3c;"></div>
                        <div class="otp-creating-text">Inaunda akaunti...</div>
                    </div>
                    <div style="text-align:center;margin-bottom:14px;">
                        <span style="font-size:13px;color:#7f8c8d;">Hujapokea msimbo? </span>
                        <span id="otpResendBtn" style="font-size:13px;font-weight:700;color:#e74c3c;cursor:pointer;"></span>
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

    function setOtpError(msg) {
        const box = document.getElementById('otpErrorBox');
        const text = document.getElementById('otpErrorText');
        if (msg) {
            box.classList.add('show');
            text.textContent = msg;
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
        if (creating) {
            el.style.display = 'block';
            btn.style.display = 'none';
        } else {
            el.style.display = 'none';
            btn.style.display = 'flex';
        }
    }

    function showAlert(title, message, onOkCallback = null, successWithNavigate = false) {
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
        
        if (successWithNavigate) {
            const loginBtn = document.createElement('div');
            loginBtn.className = 'alert-btn alert-btn-secondary';
            loginBtn.innerText = 'Ingia Sasa';
            loginBtn.style.marginTop = '10px';
            loginBtn.style.backgroundColor = '#2ecc71';
            alertBox.appendChild(loginBtn);
            
            loginBtn.addEventListener('click', () => {
                overlay.remove();
                window.location.href = '/login?role=msimamizi';
            });
        }
        
        okBtn.addEventListener('click', () => {
            overlay.remove();
            if (onOkCallback) onOkCallback();
        });
        
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
            showAlert('Hitilafu', 'Tafadhali jaza sehemu zote required');
            return false;
        }
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(form.email)) {
            showAlert('Hitilafu', 'Tafadhali ingiza barua pepe sahihi');
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
        if (form.adminCode !== 'ADMIN2024') {
            showAlert('Hitilafu', 'Admin code si sahihi');
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

    // Show OTP verification modal
    function showOtpModal(email) {
        const existing = document.querySelector('.custom-alert');
        if (existing) existing.remove();

        otpDigits = Array(OTP_LENGTH).fill('');
        otpCountdown = OTP_RESEND_COOLDOWN;

        const wrapper = document.createElement('div');
        wrapper.innerHTML = getOtpModalHtml(email);
        document.body.appendChild(wrapper.firstElementChild);

        // Set up 6 individual digit boxes
        for (let i = 0; i < OTP_LENGTH; i++) {
            const box = document.getElementById('otpBox' + i);
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

        // Start resend countdown
        startOtpCountdown();

        document.getElementById('otpVerifyBtn').addEventListener('click', () => handleVerifyOtp());
        document.getElementById('otpResendBtn').addEventListener('click', () => handleResendOtp(email));
        document.getElementById('otpCancelBtn').addEventListener('click', () => {
            if (otpCountdownTimer) clearInterval(otpCountdownTimer);
            document.getElementById('otpModal').remove();
        });
        document.getElementById('otpCloseBtn').addEventListener('click', () => {
            if (otpCountdownTimer) clearInterval(otpCountdownTimer);
            document.getElementById('otpModal').remove();
        });
    }

    // Step 2: Verify OTP
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
                setOtpError(data.error || 'Msimbo si sahihi');
            }
        } catch (error) {
            setOtpVerifying(false);
            setOtpError('Hitilafu ya mtandao');
        }
    }

    // Resend OTP
    async function handleResendOtp(email) {
        if (otpCountdown > 0) return;
        const otpResendBtn = document.getElementById('otpResendBtn');
        otpResendBtn.textContent = 'Inatuma...';
        otpResendBtn.style.color = '#95a5a6';

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
                startOtpCountdown();
            } else {
                setOtpError(data.error || 'Imeshindikana');
                otpResendBtn.textContent = 'Tuma msimbo tena';
                otpResendBtn.style.color = '#e74c3c';
            }
        } catch (error) {
            setOtpError('Hitilafu ya mtandao');
            otpResendBtn.textContent = 'Tuma msimbo tena';
            otpResendBtn.style.color = '#e74c3c';
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
                showAlert('Mafanikio', 'Akaunti ya Msimamizi imeundwa kikamilifu! Sasa unaweza kuingia na kuanza kusimamia biashara yako.', null, true);
            } else {
                setLoadingState(false);
                showAlert('Hitilafu', data.error || 'Hitilafu imetokea wakati wa kujisajili');
            }
        } catch (error) {
            setLoadingState(false);
            showAlert('Hitilafu', 'Hitilafu imetokea. Tafadhali jaribu tena.');
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