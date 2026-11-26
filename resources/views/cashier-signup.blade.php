@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
<link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="cashier_signup.page_title">Dukamkononi | Usajili Muuzaji</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--pg-page-soft, #f8f9fa); overflow-x: hidden; }
        .keyboard-avoid { min-height: 100vh; display: flex; flex-direction: column; }
        .scroll-container { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; min-height: 100vh; }
        .content { width: 100%; max-width: 550px; margin: 0 auto; }
        .header { font-size: 24px; font-weight: 800; text-align: center; margin-bottom: 8px; color: #2ecc71; text-transform: uppercase; letter-spacing: .5px; }
        .subtitle { font-size: 12px; color: var(--pg-muted, #7f8c8d); margin-bottom: 24px; text-align: center; }
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #e5e7eb; color: #7f8c8d; font-size: 12px; font-weight: 800; }
        .step-dot.active { background: #2ecc71; color: white; }
        .step-dot.complete { background: #27ae60; color: white; }
        .step-line { width: 44px; height: 2px; background: #e5e7eb; }
        .step-line.complete { background: #27ae60; }
        .step-title { font-size: 19px; font-weight: 800; color: var(--pg-text, #2c3e50); margin-bottom: 6px; }
        .step-description { font-size: 13px; line-height: 1.5; color: var(--pg-muted, #7f8c8d); margin-bottom: 20px; }
        .form-container { display: flex; flex-direction: column; gap: 16px; width: 100%; margin-bottom: 20px; }
        .input-field { width: 100%; height: 50px; background: var(--pg-field, white); border-radius: 12px; padding: 0 16px; font-size: 16px; font-family: 'Inter', monospace; border: 1px solid var(--pg-line, #e0e0e0); transition: all .2s ease; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .input-field:focus { outline: none; border-color: #2ecc71; box-shadow: 0 0 0 3px var(--pg-glow-green, rgba(46,204,113,.1)); }
        .signup-btn { width: 100%; min-height: 60px; background: #2ecc71; border-radius: 12px; display: flex; justify-content: center; align-items: center; margin-top: 8px; cursor: pointer; border: none; box-shadow: 0 4px 8px rgba(0,0,0,.1); color: white; font-size: 17px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .signup-btn:active { transform: scale(.97); opacity: .9; }
        .signup-btn:disabled { opacity: .55; cursor: not-allowed; transform: none; }
        .back-btn { width: 100%; min-height: 50px; background: transparent; border: 2px solid #3498db; border-radius: 12px; display: flex; justify-content: center; align-items: center; cursor: pointer; margin-top: 8px; color: #3498db; font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .text-btn { border: none; background: transparent; color: #27ae60; font-weight: 700; cursor: pointer; padding: 6px; }
        .info-card { margin-top: 28px; padding: 18px 20px; background: #e8f6ef; border-radius: 16px; border-left: 4px solid #2ecc71; }
        .info-title { font-size: 14px; font-weight: 800; color: #27ae60; margin-bottom: 12px; }
        .info-point { font-size: 12px; color: #1e8449; margin-bottom: 8px; line-height: 1.45; }
        .info-point::before { content: "•"; font-weight: bold; margin-right: 6px; }
        .invitation-card { display: none; background: #edf9f2; border: 1px solid #b7e4c8; border-radius: 14px; padding: 16px; margin-top: 14px; }
        .invitation-card.show { display: block; }
        .invitation-card h3 { font-size: 16px; color: #1e8449; margin-bottom: 10px; }
        .invitation-row { display: grid; grid-template-columns: 105px 1fr; gap: 10px; padding: 7px 0; border-top: 1px solid #d5f0df; font-size: 13px; line-height: 1.45; }
        .invitation-row span:first-child { color: #5d7d68; font-weight: 600; }
        .invitation-row span:last-child { color: #234b34; overflow-wrap: anywhere; }
        .notice { display: none; background: #fff4e5; border: 1px solid #ffd59a; color: #9a5b00; border-radius: 10px; padding: 12px; font-size: 13px; line-height: 1.45; margin-bottom: 14px; }
        .notice.show { display: block; }
        .lookup-status { min-height: 20px; margin-top: 8px; font-size: 12px; color: #7f8c8d; }
        .otp-boxes { display: flex; gap: 8px; justify-content: center; margin: 20px 0; }
        .otp-box { width: 46px; height: 56px; border-radius: 12px; border: 1.5px solid #d5dbe1; background: #f8fafc; text-align: center; font-size: 22px; font-weight: 700; color: #2c3e50; }
        .otp-box:focus { outline: none; border-color: #2ecc71; }
        .otp-box.filled, .otp-box.error { border-color: #e74c3c; }
        .resend-row { text-align: center; font-size: 13px; color: #7f8c8d; margin-bottom: 18px; }
        .custom-alert { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); padding: 20px; }
        .alert-box { background: var(--pg-surface, white); border-radius: 24px; width: 100%; max-width: 340px; padding: 24px 20px; text-align: center; box-shadow: 0 20px 35px rgba(0,0,0,.2); }
        .alert-title { font-size: 20px; font-weight: 800; margin-bottom: 12px; color: #2c3e50; }
        .alert-message { font-size: 14px; color: #5d6d7e; margin-bottom: 22px; line-height: 1.5; white-space: pre-wrap; }
        .alert-btn { background: #2ecc71; padding: 12px; border-radius: 12px; font-weight: 700; color: white; cursor: pointer; }
        .alert-btn.secondary { background: #3498db; margin-top: 10px; }
        @media (max-width: 550px) { .scroll-container { padding: 28px 16px; } .header { font-size: 21px; } .invitation-row { grid-template-columns: 90px 1fr; } .otp-box { width: 42px; height: 52px; } }
    </style>
</head>
<body class="dm-public">
@endverbatim
@include('partials.dm-lang-widget')
@include('partials.dm-theme-widget')
@verbatim
<div class="keyboard-avoid">
    <div class="scroll-container">
        <div class="content">
            <h1 class="header" data-i18n="cashier_signup.header_register_seller">JISAJILI KAMA MUUZAJI</h1>
            <p class="subtitle" data-i18n="cashier_signup.required_hint">* Inamaanisha sehemu inayohitajika</p>
            <div class="step-indicator" aria-label="Hatua za usajili">
                <span class="step-dot active" data-step-dot="1">1</span><span class="step-line" data-step-line="1"></span>
                <span class="step-dot" data-step-dot="2">2</span><span class="step-line" data-step-line="2"></span>
                <span class="step-dot" data-step-dot="3">3</span>
            </div>

            <section data-step="1">
                <h2 class="step-title">Msimbo wa Mwaliko</h2>
                <p class="step-description">Pata msimbo kutoka kwa msimamizi wa biashara. Biashara na tawi vitatatuliwa kiotomatiki.</p>
                <div class="form-container">
                    <input type="text" class="input-field" id="invitationCode" maxlength="20" placeholder="Mfano: DMABCD2345" autocomplete="one-time-code" style="text-transform:uppercase;letter-spacing:1px;">
                    <div id="lookupStatus" class="lookup-status" aria-live="polite"></div>
                </div>
                <div id="invitationCard" class="invitation-card" aria-live="polite"></div>
                <button type="button" id="confirmInviteBtn" class="signup-btn" disabled>Thibitisha na Endelea</button>
                <button type="button" id="changeInviteBtn" class="text-btn" style="display:none;width:100%;margin-top:8px;">Badilisha Msimbo</button>
            </section>

            <section data-step="2" hidden>
                <h2 class="step-title">Taarifa za Muuzaji</h2>
                <p class="step-description">Weka taarifa zako binafsi. Akaunti itasubiri uthibitisho wa msimamizi.</p>
                <div class="form-container">
                    <input type="text" class="input-field" id="full_name" maxlength="120" placeholder="Jina Kamili *" data-i18n="cashier_signup.label_full_name" data-i18n-attr="placeholder" autocomplete="name">
                    <input type="email" class="input-field" id="email" placeholder="Barua Pepe *" data-i18n="cashier_signup.label_email" data-i18n-attr="placeholder" autocomplete="email">
                    <input type="tel" class="input-field" id="phone" maxlength="30" placeholder="Namba ya Simu" data-i18n="cashier_signup.label_phone" data-i18n-attr="placeholder" autocomplete="tel">
                    <input type="password" class="input-field" id="password" placeholder="Nenosiri *" data-i18n="cashier_signup.label_password" data-i18n-attr="placeholder" autocomplete="new-password">
                    <input type="password" class="input-field" id="confirmPassword" placeholder="Rudia Nenosiri *" data-i18n="cashier_signup.label_confirm_password" data-i18n-attr="placeholder" autocomplete="off">
                </div>
                <button type="button" id="continuePersonalBtn" class="signup-btn">Endelea</button>
            </section>

            <section data-step="3" hidden>
                <h2 class="step-title">Thibitisha Barua Pepe</h2>
                <p class="step-description">Weka msimbo wa tarakimu 6 uliotumwa kwa <strong id="pendingEmail"></strong>, kisha bonyeza Maliza.</p>
                <div id="emailWarning" class="notice" role="alert"></div>
                <div class="otp-boxes" id="otpBoxes"></div>
                <div class="resend-row"><span>Hujapokea msimbo? </span><button type="button" id="resendBtn" class="text-btn">Tuma msimbo tena</button></div>
                <button type="button" id="finishBtn" class="signup-btn">Maliza Usajili</button>
            </section>

            <button type="button" id="backBtn" class="back-btn">Rudi Nyuma</button>
            <div class="info-card">
                <div class="info-title" data-i18n="cashier_signup.info_title">Maelezo muhimu kwa Muuzaji:</div>
                <div class="info-point">Omba msimbo wa mwaliko kutoka kwa msimamizi wa biashara</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_approval">Akaunti yako itahitaji uthibitisho wa msimamizi kabla ya kuingia</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_notify">Utapokea taarifa ukiapidhinishwa</div>
                <div class="info-point" data-i18n="cashier_signup.info_point_after_approval">Baada ya kuidhinishwa, utaweza kuongeza bidhaa na kufanya mauzo</div>
            </div>
        </div>
    </div>
</div>

<script>
    const API_BASE_URL = '';
    const OTP_LENGTH = 6;
    const OTP_RESEND_COOLDOWN = 30;
    const $ = id => document.getElementById(id);
    const SW = {
        alert_ok:'Sawa', alert_network_error:'Hitilafu ya Mtandao', alert_error:'Hitilafu',
        err_no_internet:'Hakuna muunganisho wa mtandao. Tafadhali hakikisha umeunganishwa kwenye internet.',
        err_try_again:'Hitilafu imetokea. Tafadhali jaribu tena.', err_required_fields:'Tafadhali jaza sehemu zote zinazohitajika.',
        err_email_invalid:'Tafadhali ingiza barua pepe sahihi.', err_passwords_dont_match:'Nenosiri hazifanani.',
        err_password_short:'Nenosiri lazima liwe na herufi 6 au zaidi.', err_code_required:'Tafadhali weka msimbo wa tarakimu 6.',
        err_code_incorrect:'Msimko si sahihi.', otp_sending:'Inatuma...', otp_resend_countdown:'Tuma tena ({seconds}s)',
        otp_resend_code:'Tuma msimbo tena', otp_success_title:'Mafanikio',
        otp_success_message:'Akaunti ya Muuzaji imeundwa kikamilifu! Ombi lako linasubiri uthibitisho wa msimamizi.'
    };
    function t(key, params) {
        if (window.DM) return window.DM.t(key, params);
        let value = SW[key] || SW[key.slice(key.indexOf('.') + 1)] || key;
        if (params) Object.keys(params).forEach(name => { value = value.split('{' + name + '}').join(String(params[name])); });
        return value;
    }
    let step = 1, loading = false, otpBusy = false, invitation = null, formValues = null, registrationToken = null;
    let otpDigits = Array(OTP_LENGTH).fill(''), otpCountdown = 0, otpTimer = null, lookupTimer = null, lookupSequence = 0;
    const fields = {full_name:$('full_name'), email:$('email'), phone:$('phone'), password:$('password'), confirmPassword:$('confirmPassword')};

    function escapeHtml(value) { return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    function normalizeCode(value) { return String(value || '').toUpperCase().replace(/[^A-Z0-9]/g, ''); }
    function showAlert(title, message, onOk = null, success = false) {
        document.querySelectorAll('.custom-alert').forEach(el => el.remove());
        const overlay = document.createElement('div'); overlay.className = 'custom-alert';
        const box = document.createElement('div'); box.className = 'alert-box';
        const titleText = typeof title === 'string' ? title : t(title.key, title.params);
        const messageText = typeof message === 'string' ? message : t(message.key, message.params);
        box.innerHTML = '<div class="alert-title"></div><div class="alert-message"></div><div class="alert-btn"></div>';
        box.querySelector('.alert-title').textContent = titleText; box.querySelector('.alert-message').textContent = messageText;
        const ok = box.querySelector('.alert-btn'); ok.textContent = t('cashier_signup.alert_ok');
        ok.onclick = () => { overlay.remove(); if (onOk) onOk(); };
        if (success) {
            const login = document.createElement('div'); login.className = 'alert-btn secondary'; login.textContent = 'Nenda Kwenye Kuingia';
            login.onclick = () => { window.location.href = '/login?role=seller'; }; box.appendChild(login);
        }
        overlay.appendChild(box); document.body.appendChild(overlay);
    }
    function showNetworkError() { showAlert({key:'cashier_signup.alert_network_error'}, {key:'cashier_signup.err_no_internet'}); }
    function errorMessage(data, fallback) { return data.error || t(fallback); }
    async function postJson(url, body) {
        const response = await fetch(API_BASE_URL + url, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
        let data = {}; try { data = await response.json(); } catch (e) {}
        return {response, data};
    }
    function setButtonLoading(button, isLoading, text) { button.disabled = isLoading; button.textContent = isLoading ? 'Tafadhali subiri...' : text; }
    function goToStep(next) {
        step = next;
        document.querySelectorAll('[data-step]').forEach(section => { section.hidden = Number(section.dataset.step) !== step; });
        document.querySelectorAll('[data-step-dot]').forEach(dot => { const n = Number(dot.dataset.stepDot); dot.classList.toggle('active', n === step); dot.classList.toggle('complete', n < step); });
        document.querySelectorAll('[data-step-line]').forEach(line => line.classList.toggle('complete', Number(line.dataset.stepLine) < step));
        $('backBtn').textContent = step === 1 ? 'Rudi Nyuma' : '← Nyuma';
        if (step === 3) { $('pendingEmail').textContent = formValues.email; buildOtpBoxes(); showResendState(); setTimeout(() => $('otpBox0')?.focus(), 100); }
        window.scrollTo({top:0, behavior:'smooth'});
    }
    function clearInvitation(message = '') {
        invitation = null; $('invitationCard').classList.remove('show'); $('invitationCard').innerHTML = '';
        $('confirmInviteBtn').disabled = true; $('changeInviteBtn').style.display = 'none'; $('lookupStatus').textContent = message;
    }
    function renderInvitation(data) {
        invitation = data;
        const business = data.business || {}, branch = data.branch;
        const expiry = data.invitation?.expires_at ? new Date(data.invitation.expires_at).toLocaleDateString() : 'Hakuna muda maalum';
        $('invitationCard').innerHTML = '<h3>Biashara Imepatikana</h3>'
            + '<div class="invitation-row"><span>Biashara</span><span>' + escapeHtml(business.business_name) + '</span></div>'
            + '<div class="invitation-row"><span>Mahali</span><span>' + escapeHtml(business.business_location || '—') + '</span></div>'
            + '<div class="invitation-row"><span>Tawi</span><span>' + escapeHtml(branch ? branch.branch_name + (branch.location ? ' — ' + branch.location : '') : 'Biashara bila matawi') + '</span></div>'
            + '<div class="invitation-row"><span>Mwisho</span><span>' + escapeHtml(expiry) + '</span></div>';
        $('invitationCard').classList.add('show'); $('lookupStatus').textContent = 'Thibitisha kwamba hii ni biashara unayojiunga nayo.';
        $('confirmInviteBtn').disabled = false; $('changeInviteBtn').style.display = 'block';
    }
    async function lookupInvitation() {
        const code = normalizeCode($('invitationCode').value);
        $('invitationCode').value = code;
        if (code.length !== 10 || !code.startsWith('DM')) { clearInvitation(code ? 'Msimbo wa mwaliko unaanza na DM na una tarakimu/herufi 8.' : ''); return; }
        const sequence = ++lookupSequence;
        $('lookupStatus').textContent = 'Inatafuta taarifa za mwaliko...'; $('confirmInviteBtn').disabled = true;
        try {
            const response = await fetch(API_BASE_URL + '/api/seller-registration/invitations/' + encodeURIComponent(code));
            const data = await response.json();
            if (sequence !== lookupSequence) return;
            if (response.ok && data.success) renderInvitation(data);
            else { clearInvitation(); showAlert({key:'cashier_signup.alert_error'}, errorMessage(data, 'cashier_signup.err_try_again')); }
        } catch (e) { if (sequence === lookupSequence) { clearInvitation(); showNetworkError(); } }
    }
    $('invitationCode').addEventListener('input', () => {
        lookupSequence++;
        const code = normalizeCode($('invitationCode').value); $('invitationCode').value = code; clearInvitation();
        clearTimeout(lookupTimer); if (code.length === 10) lookupTimer = setTimeout(lookupInvitation, 450);
    });
    $('invitationCode').addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); lookupInvitation(); } });
    $('changeInviteBtn').addEventListener('click', () => { clearTimeout(lookupTimer); lookupSequence++; $('invitationCode').value = ''; clearInvitation(); $('invitationCode').focus(); });
    $('confirmInviteBtn').addEventListener('click', () => { if (invitation && !loading) goToStep(2); });

    function getPersonalValues() { return {full_name:fields.full_name.value.trim(), email:fields.email.value.trim(), phone:fields.phone.value.trim(), password:fields.password.value, confirm_password:fields.confirmPassword.value}; }
    function validatePersonalLocal(form) {
        if (!form.full_name || !form.email || !form.password || !form.confirm_password) { showAlert({key:'cashier_signup.alert_error'}, {key:'cashier_signup.err_required_fields'}); return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { showAlert({key:'cashier_signup.alert_error'}, {key:'cashier_signup.err_email_invalid'}); return false; }
        if (form.password !== form.confirm_password) { showAlert({key:'cashier_signup.alert_error'}, {key:'cashier_signup.err_passwords_dont_match'}); return false; }
        if (form.password.length < 6) { showAlert({key:'cashier_signup.alert_error'}, {key:'cashier_signup.err_password_short'}); return false; }
        return true;
    }
    async function continuePersonal() {
        if (loading || !invitation) return;
        const personal = getPersonalValues(); if (!validatePersonalLocal(personal)) return;
        formValues = {role:'seller', invitation_code:invitation.invitation.code, ...personal};
        setButtonLoading($('continuePersonalBtn'), true, 'Endelea'); loading = true;
        try {
            const check = await postJson('/api/register/precheck', formValues);
            if (!check.response.ok || !check.data.success) {
                if (check.data.step === 'invitation') goToStep(1);
                showAlert({key:'cashier_signup.alert_error'}, errorMessage(check.data, 'cashier_signup.err_try_again')); return;
            }
            const result = await postJson('/api/register/initiate', formValues);
            if (!result.response.ok || !(result.data.success || result.data.requiresOtp)) { showAlert({key:'cashier_signup.alert_error'}, errorMessage(result.data, 'cashier_signup.err_signup_failed')); return; }
            otpDigits = Array(OTP_LENGTH).fill(''); $('emailWarning').classList.toggle('show', result.data.email_sent === false);
            $('emailWarning').textContent = result.data.warning || 'Msimko haukuweza kutumwa. Tumia “Tuma msimbo tena” kujaribu tena.';
            goToStep(3); startOtpCountdown();
        } catch (e) { showNetworkError(); }
        finally { loading = false; setButtonLoading($('continuePersonalBtn'), false, 'Endelea'); }
    }

    function buildOtpBoxes() {
        $('otpBoxes').innerHTML = Array.from({length:OTP_LENGTH}, (_, i) => '<input class="otp-box" id="otpBox' + i + '" maxlength="1" inputmode="numeric" pattern="[0-9]*" aria-label="Tarakimu ' + (i + 1) + '">').join('');
        for (let i = 0; i < OTP_LENGTH; i++) {
            const box = $('otpBox' + i); box.value = otpDigits[i]; box.classList.toggle('filled', !!otpDigits[i]);
            box.addEventListener('input', () => { const digit = box.value.replace(/[^0-9]/g, '').slice(-1); otpDigits[i] = digit; box.value = digit; box.classList.toggle('filled', !!digit); box.classList.remove('error'); if (digit && i < OTP_LENGTH - 1) $('otpBox' + (i + 1)).focus(); });
            box.addEventListener('paste', event => { const code = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, OTP_LENGTH); if (code.length > 1) { event.preventDefault(); otpDigits = code.split(''); buildOtpBoxes(); $('otpBox' + Math.min(code.length, OTP_LENGTH - 1)).focus(); } });
            box.addEventListener('keydown', event => { if (event.key === 'Backspace' && !otpDigits[i] && i > 0) { otpDigits[i - 1] = ''; $('otpBox' + (i - 1)).value = ''; $('otpBox' + (i - 1)).classList.remove('filled'); $('otpBox' + (i - 1)).focus(); } if (event.key === 'Enter') finishRegistration(); });
        }
    }
    function showResendState() { $('resendBtn').disabled = otpBusy || otpCountdown > 0; $('resendBtn').textContent = otpBusy ? t('cashier_signup.otp_sending') : (otpCountdown > 0 ? t('cashier_signup.otp_resend_countdown', {seconds:otpCountdown}) : t('cashier_signup.otp_resend_code')); }
    function startOtpCountdown() { otpCountdown = OTP_RESEND_COOLDOWN; if (otpTimer) clearInterval(otpTimer); showResendState(); otpTimer = setInterval(() => { otpCountdown--; showResendState(); if (otpCountdown <= 0) { clearInterval(otpTimer); otpTimer = null; } }, 1000); }
    async function resendOtp() {
        if (otpBusy || otpCountdown > 0 || !formValues) return; otpBusy = true; showResendState();
        try {
            const {response, data} = await postJson('/api/register/resend-otp', {email:formValues.email, role:'seller'});
            if (response.ok && data.success) { otpDigits = Array(OTP_LENGTH).fill(''); buildOtpBoxes(); $('emailWarning').classList.toggle('show', data.email_sent === false); $('emailWarning').textContent = data.warning || ''; startOtpCountdown(); }
            else showAlert({key:'cashier_signup.alert_error'}, errorMessage(data, 'cashier_signup.err_try_again'));
        } catch (e) { showNetworkError(); }
        finally { otpBusy = false; showResendState(); }
    }
    async function finishRegistration() {
        if (otpBusy || !formValues) return; const code = otpDigits.join('');
        if (code.length !== OTP_LENGTH) { showAlert({key:'cashier_signup.alert_error'}, {key:'cashier_signup.err_code_required'}); return; }
        otpBusy = true; setButtonLoading($('finishBtn'), true, 'Maliza Usajili'); $('resendBtn').disabled = true;
        try {
            const verified = await postJson('/api/register/verify-otp', {email:formValues.email, role:'seller', otp:code});
            if (!verified.response.ok || !verified.data.success || !verified.data.verified) { showAlert({key:'cashier_signup.alert_error'}, errorMessage(verified.data, 'cashier_signup.err_code_incorrect')); return; }
            registrationToken = verified.data.registrationToken;
            const created = await postJson('/api/register', {verificationToken:registrationToken, email:formValues.email, role:'seller'});
            if (!created.response.ok) { showAlert({key:'cashier_signup.alert_error'}, errorMessage(created.data, 'cashier_signup.err_signup_failed')); return; }
            if (otpTimer) clearInterval(otpTimer);
            showAlert({key:'cashier_signup.otp_success_title'}, {key:'cashier_signup.otp_success_message'}, null, true);
        } catch (e) { showNetworkError(); }
        finally { otpBusy = false; setButtonLoading($('finishBtn'), false, 'Maliza Usajili'); showResendState(); }
    }

    $('continuePersonalBtn').addEventListener('click', continuePersonal); $('resendBtn').addEventListener('click', resendOtp); $('finishBtn').addEventListener('click', finishRegistration);
    $('backBtn').addEventListener('click', () => { if (loading || otpBusy) return; if (step === 1) { if (document.referrer && document.referrer.includes(window.location.host)) window.history.back(); else window.location.href = '/home'; } else { if (step === 3 && otpTimer) clearInterval(otpTimer); goToStep(step - 1); } });
    document.addEventListener('keydown', event => { if (event.key === 'Enter' && step === 2 && event.target.tagName !== 'BUTTON') { event.preventDefault(); continuePersonal(); } });
    if (window.DM) window.DM.onChange(showResendState);
    goToStep(1);
</script>
</body>
</html>
@endverbatim
