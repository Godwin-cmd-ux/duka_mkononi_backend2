@include('partials.dm-locale')
<link rel="stylesheet" href="/css/smartcart-loader.css">
<link rel="stylesheet" href="/css/themes.css?v={{ filemtime(public_path('css/themes.css')) }}">
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, user-scalable=yes">
    <title data-i18n="admin_signup.page_title">Dukamkononi | Usajili Msimamizi</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--pg-page-soft, #f8f9fa); overflow-x: hidden; }
        .keyboard-avoid { min-height: 100vh; display: flex; flex-direction: column; }
        .scroll-container { flex: 1; display: flex; flex-direction: column; justify-content: center; align-items: center; padding: 40px 20px; min-height: 100vh; }
        .content { width: 100%; max-width: 550px; margin: 0 auto; }
        .header { font-size: 24px; font-weight: 800; text-align: center; margin-bottom: 8px; color: #e74c3c; text-transform: uppercase; letter-spacing: .5px; }
        .subtitle { font-size: 12px; color: var(--pg-muted, #7f8c8d); margin-bottom: 24px; text-align: center; }
        .step-indicator { display: flex; align-items: center; justify-content: center; gap: 8px; margin-bottom: 24px; }
        .step-dot { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: #e5e7eb; color: #7f8c8d; font-size: 12px; font-weight: 800; }
        .step-dot.active { background: #e74c3c; color: white; }
        .step-dot.complete { background: #2ecc71; color: white; }
        .step-line { width: 34px; height: 2px; background: #e5e7eb; }
        .step-line.complete { background: #2ecc71; }
        .step-title { font-size: 19px; font-weight: 800; color: var(--pg-text, #2c3e50); margin-bottom: 6px; }
        .step-description { font-size: 13px; line-height: 1.5; color: var(--pg-muted, #7f8c8d); margin-bottom: 20px; }
        .form-container { display: flex; flex-direction: column; gap: 16px; width: 100%; margin-bottom: 20px; }
        .input-field { width: 100%; height: 50px; background: var(--pg-field, white); border-radius: 12px; padding: 0 16px; font-size: 16px; font-family: 'Inter', monospace; border: 1px solid var(--pg-line, #e0e0e0); transition: all .2s ease; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
        .input-field:focus { outline: none; border-color: var(--pg-cta-red, #e74c3c); box-shadow: 0 0 0 3px var(--pg-glow-red, rgba(231,76,60,.1)); }
        textarea.input-field { height: 72px; padding: 14px 16px; resize: vertical; font-family: 'Inter', sans-serif; }
        .signup-btn { width: 100%; min-height: 60px; background: var(--pg-cta-red, #e74c3c); border-radius: 12px; justify-content: center; align-items: center; display: flex; margin-top: 8px; cursor: pointer; transition: all .2s ease; border: none; box-shadow: 0 4px 8px rgba(0,0,0,.1); color: white; font-size: 17px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; }
        .signup-btn:active { transform: scale(.97); opacity: .9; }
        .signup-btn:disabled { opacity: .65; cursor: not-allowed; transform: none; }
        .back-btn { width: 100%; min-height: 50px; background: transparent; border: 2px solid var(--pg-cta-blue, #3498db); border-radius: 12px; display: flex; justify-content: center; align-items: center; cursor: pointer; margin-top: 8px; color: var(--pg-cta-blue, #3498db); font-size: 15px; font-weight: 700; text-transform: uppercase; }
        .text-btn { border: none; background: transparent; color: var(--pg-cta-red, #e74c3c); font-weight: 700; cursor: pointer; padding: 6px; }
        .info-card { margin-top: 28px; padding: 18px 20px; background: #fdeaea; border-radius: 16px; width: 100%; border-left: 4px solid #e74c3c; }
        .info-title { font-size: 14px; font-weight: 800; color: #e74c3c; margin-bottom: 12px; }
        .info-point { font-size: 12px; color: #c0392b; margin-bottom: 8px; line-height: 1.45; }
        .info-point::before { content: "•"; font-weight: bold; margin-right: 6px; }
        .loader { display: inline-block; width: 22px; height: 22px; border: 3px solid rgba(255,255,255,.3); border-radius: 50%; border-top-color: white; animation: spin .8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .review-card { background: var(--pg-surface, white); border: 1px solid var(--pg-line, #e0e0e0); border-radius: 14px; padding: 16px; margin-bottom: 12px; }
        .review-heading { display: flex; justify-content: space-between; align-items: center; gap: 10px; margin-bottom: 12px; }
        .review-heading strong { font-size: 15px; color: var(--pg-text, #2c3e50); }
        .review-row { display: grid; grid-template-columns: 120px 1fr; gap: 10px; padding: 7px 0; border-top: 1px solid var(--pg-line, #eef0f2); font-size: 13px; line-height: 1.45; }
        .review-label { color: var(--pg-muted, #7f8c8d); font-weight: 600; }
        .review-value { color: var(--pg-text, #2c3e50); overflow-wrap: anywhere; white-space: pre-wrap; }
        .notice { display: none; background: #fff4e5; border: 1px solid #ffd59a; color: #9a5b00; border-radius: 10px; padding: 12px; font-size: 13px; line-height: 1.45; margin-bottom: 14px; }
        .notice.show { display: block; }
        .otp-boxes { display: flex; gap: 8px; justify-content: center; margin: 20px 0; }
        .otp-box { width: 46px; height: 56px; border-radius: 12px; border: 1.5px solid var(--pg-line-3, #d5dbe1); background: var(--pg-soft-cool, #f8fafc); text-align: center; font-size: 22px; font-weight: 700; color: var(--pg-text, #2c3e50); }
        .otp-box:focus { outline: none; border-color: #e74c3c; }
        .otp-box.filled, .otp-box.error { border-color: #e74c3c; }
        .resend-row { text-align: center; font-size: 13px; color: var(--pg-muted, #7f8c8d); margin-bottom: 18px; }
        .custom-alert { position: fixed; inset: 0; background: rgba(0,0,0,.5); display: flex; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(2px); padding: 20px; }
        .alert-box { background: var(--pg-surface, white); border-radius: 24px; width: 100%; max-width: 340px; padding: 24px 20px; text-align: center; box-shadow: 0 20px 35px rgba(0,0,0,.2); }
        .alert-title { font-size: 20px; font-weight: 800; margin-bottom: 12px; color: var(--pg-text, #2c3e50); }
        .alert-message { font-size: 14px; color: var(--pg-desc, #5d6d7e); margin-bottom: 22px; line-height: 1.5; white-space: pre-wrap; }
        .alert-btn { background: #e74c3c; padding: 12px; border-radius: 12px; font-weight: 700; color: white; cursor: pointer; }
        .alert-btn.secondary { background: #2ecc71; margin-top: 10px; }
        @media (max-width: 550px) { .scroll-container { padding: 28px 16px; } .header { font-size: 21px; } .review-row { grid-template-columns: 100px 1fr; } .otp-box { width: 42px; height: 52px; } }
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
            <h1 class="header" data-i18n="admin_signup.header_register_admin">JISAJILI KAMA MSIMAMIZI</h1>
            <p class="subtitle" data-i18n="admin_signup.required_hint">* Inamaanisha sehemu inayohitajika</p>
            <div class="step-indicator" aria-label="Hatua za usajili">
                <span class="step-dot active" data-step-dot="1">1</span><span class="step-line" data-step-line="1"></span>
                <span class="step-dot" data-step-dot="2">2</span><span class="step-line" data-step-line="2"></span>
                <span class="step-dot" data-step-dot="3">3</span><span class="step-line" data-step-line="3"></span>
                <span class="step-dot" data-step-dot="4">4</span>
            </div>

            <section data-step="1">
                <h2 class="step-title">Taarifa za Biashara</h2>
                <p class="step-description">Anza na taarifa za biashara yako. Jina la biashara litakaguliwa kabla ya kuendelea.</p>
                <div class="form-container">
                    <input type="text" class="input-field" id="business_name" maxlength="150" placeholder="Jina la Biashara *" data-i18n="admin_signup.label_business_name" data-i18n-attr="placeholder" autocomplete="organization">
                    <input type="text" class="input-field" id="business_location" maxlength="255" placeholder="Mahali pa Biashara *" data-i18n="admin_signup.label_business_location" data-i18n-attr="placeholder" autocomplete="address-line1">
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
                    <textarea class="input-field" id="business_description" maxlength="1000" placeholder="Maelezo mafupi ya biashara" data-i18n="admin_signup.label_business_description" data-i18n-attr="placeholder"></textarea>
                    <select class="input-field" id="business_structure">
                        <option value="non_branch">Biashara Bila Matawi</option>
                        <option value="branch">Biashara Yenye Matawi</option>
                    </select>
                    <div id="branchSection" style="display:none;width:100%;background:#eef4fa;border-radius:14px;padding:14px;">
                        <div style="font-weight:700;color:#2c3e50;font-size:14px;margin-bottom:4px;">MATAWI YA BIASHARA *</div>
                        <div style="font-size:12px;color:#7a8ba0;margin-bottom:10px;">Ongeza tawi moja au zaidi. Kila tawi linahitaji jina na eneo lake.</div>
                        <div id="branchList"></div>
                        <button type="button" id="addBranchBtn" style="width:100%;padding:10px;border:2px dashed #3498db;background:transparent;color:#3498db;border-radius:10px;font-weight:700;cursor:pointer;">+ Ongeza Tawi</button>
                    </div>
                </div>
                <button type="button" id="continueBtn" class="signup-btn">Endelea</button>
            </section>

            <section data-step="2" hidden>
                <h2 class="step-title">Taarifa za Msimamizi</h2>
                <p class="step-description">Weka taarifa zako binafsi. Barua pepe itatumika kupokea msimbo wa uthibitisho.</p>
                <div class="form-container">
                    <input type="text" class="input-field" id="full_name" maxlength="120" placeholder="Jina Kamili *" data-i18n="admin_signup.label_full_name" data-i18n-attr="placeholder" autocomplete="name">
                    <input type="email" class="input-field" id="email" placeholder="Barua Pepe *" data-i18n="admin_signup.label_email" data-i18n-attr="placeholder" autocomplete="email">
                    <input type="tel" class="input-field" id="phone" maxlength="30" placeholder="Namba ya Simu" data-i18n="admin_signup.label_phone" data-i18n-attr="placeholder" autocomplete="tel">
                    <input type="password" class="input-field" id="password" placeholder="Nenosiri *" data-i18n="admin_signup.label_password" data-i18n-attr="placeholder" autocomplete="new-password">
                    <input type="password" class="input-field" id="confirmPassword" placeholder="Rudia Nenosiri *" data-i18n="admin_signup.label_confirm_password" data-i18n-attr="placeholder" autocomplete="off">
                </div>
                <button type="button" id="previewBtn" class="signup-btn">Hakiki</button>
            </section>

            <section data-step="3" hidden>
                <h2 class="step-title">Hakiki Taarifa Zako</h2>
                <p class="step-description">Angalia taarifa zote. Unaweza kurudi kuzihariri kabla ya kuomba msimbo wa uthibitisho.</p>
                <div id="reviewContent"></div>
                <button type="button" id="verifyBtn" class="signup-btn">Thibitisha na Endelea</button>
            </section>

            <section data-step="4" hidden>
                <h2 class="step-title">Thibitisha Barua Pepe</h2>
                <p class="step-description">Weka msimbo wa tarakimu 6 uliotumwa kwa <strong id="pendingEmail"></strong>, kisha bonyeza Maliza.</p>
                <div id="emailWarning" class="notice" role="alert"></div>
                <div class="otp-boxes" id="otpBoxes"></div>
                <div class="resend-row"><span id="resendText">Hujapokea msimbo? </span><button type="button" id="resendBtn" class="text-btn">Tuma msimbo tena</button></div>
                <button type="button" id="finishBtn" class="signup-btn">Maliza Usajili</button>
            </section>

            <button type="button" id="backBtn" class="back-btn">Rudi Nyuma</button>
            <div class="info-card">
                <div class="info-title" data-i18n="admin_signup.info_title">Maelezo muhimu kwa Msimamizi:</div>
                <div class="info-point" data-i18n="admin_signup.info_point_unique_name">Jina la biashara lazima liwe la kipekee na halijasajiliwa</div>
                <div class="info-point" data-i18n="admin_signup.info_point_full_authority">Utakuwa na mamlaka kamili ya kuidhinisha wateja na wauzaji</div>
                <div class="info-point" data-i18n="admin_signup.info_point_immediate_use">Unaweza kuanza kutumia mfumo mara moja baada ya kujisajili</div>
            </div>
        </div>
    </div>
</div>

<script>
    const API_BASE_URL = '';
    const OTP_LENGTH = 6;
    const OTP_RESEND_COOLDOWN = 30;
    const SW = {
        alert_ok: 'Sawa', alert_sign_in_now: 'Ingia Sasa', alert_network_error: 'Hitilafu ya Mtandao',
        err_no_internet: 'Hakuna muunganisho wa mtandao. Tafadhali hakikisha umeunganishwa kwenye internet.',
        alert_error: 'Hitilafu', err_try_again: 'Hitilafu imetokea. Tafadhali jaribu tena.',
        err_required_fields: 'Tafadhali jaza sehemu zote zinazohitajika.',
        err_email_invalid: 'Tafadhali ingiza barua pepe sahihi.', err_passwords_dont_match: 'Nenosiri hazifanani.',
        err_password_short: 'Nenosiri lazima liwe na herufi 6 au zaidi.', err_code_required: 'Tafadhali weka msimbo wa tarakimu 6.',
        err_code_incorrect: 'Msimbo si sahihi.', otp_sending: 'Inatuma...', otp_resend_countdown: 'Tuma tena ({seconds}s)',
        otp_resend_code: 'Tuma msimbo tena', otp_success_title: 'Mafanikio',
        otp_success_message: 'Akaunti ya Msimamizi imeundwa kikamilifu! Sasa unaweza kuingia na kuanza kusimamia biashara yako.'
    };
    function t(key, params) {
        if (window.DM) return window.DM.t(key, params);
        let value = SW[key] || SW[key.slice(key.indexOf('.') + 1)] || key;
        if (params) Object.keys(params).forEach(name => { value = value.split('{' + name + '}').join(String(params[name])); });
        return value;
    }
    const $ = id => document.getElementById(id);
    const inputs = {
        full_name: $('full_name'), business_name: $('business_name'), business_location: $('business_location'),
        business_type: $('business_type'), business_description: $('business_description'), business_structure: $('business_structure'),
        email: $('email'), phone: $('phone'), password: $('password'), confirmPassword: $('confirmPassword')
    };
    let step = 1;
    let loading = false;
    let formValues = null;
    let registrationToken = null;
    let branchRows = [];
    let otpDigits = Array(OTP_LENGTH).fill('');
    let otpCountdown = 0;
    let otpTimer = null;
    let otpBusy = false;
    let alertState = null;

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function businessLabel(value) {
        const option = Array.from(inputs.business_type.options).find(item => item.value === value);
        return option ? option.textContent.trim() : value;
    }
    function showAlert(title, message, onOk = null, success = false) {
        document.querySelectorAll('.custom-alert').forEach(el => el.remove());
        alertState = { title, message };
        const overlay = document.createElement('div');
        overlay.className = 'custom-alert';
        const box = document.createElement('div');
        box.className = 'alert-box';
        const titleText = typeof title === 'string' ? title : t(title.key, title.params);
        const messageText = typeof message === 'string' ? message : t(message.key, message.params);
        box.innerHTML = '<div class="alert-title"></div><div class="alert-message"></div><div class="alert-btn"></div>';
        box.querySelector('.alert-title').textContent = titleText;
        box.querySelector('.alert-message').textContent = messageText;
        const ok = box.querySelector('.alert-btn');
        ok.textContent = t('admin_signup.alert_ok');
        ok.onclick = () => { overlay.remove(); alertState = null; if (onOk) onOk(); };
        if (success) {
            const login = document.createElement('div');
            login.className = 'alert-btn secondary';
            login.textContent = t('admin_signup.alert_sign_in_now');
            login.onclick = () => { window.location.href = '/login?role=msimamizi'; };
            box.appendChild(login);
        }
        overlay.appendChild(box);
        document.body.appendChild(overlay);
    }
    function showNetworkError() { showAlert({key:'admin_signup.alert_network_error'}, {key:'admin_signup.err_no_internet'}); }
    function errorMessage(data, fallback) { return data.error || t(fallback); }
    async function postJson(url, body) {
        const response = await fetch(API_BASE_URL + url, { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify(body) });
        let data = {};
        try { data = await response.json(); } catch (e) {}
        return { response, data };
    }
    function setButtonLoading(button, isLoading, text) {
        button.disabled = isLoading;
        button.textContent = isLoading ? 'Tafadhali subiri...' : text;
    }
    function renderStep() {
        document.querySelectorAll('[data-step]').forEach(section => { section.hidden = Number(section.dataset.step) !== step; });
        document.querySelectorAll('[data-step-dot]').forEach(dot => {
            const number = Number(dot.dataset.stepDot);
            dot.classList.toggle('active', number === step);
            dot.classList.toggle('complete', number < step);
        });
        document.querySelectorAll('[data-step-line]').forEach(line => line.classList.toggle('complete', Number(line.dataset.stepLine) < step));
        $('backBtn').textContent = step === 1 ? 'Rudi Nyuma' : '← Nyuma';
        if (step === 3) renderReview();
        if (step === 4) {
            $('pendingEmail').textContent = formValues.email;
            buildOtpBoxes();
            showResendState();
            setTimeout(() => $('otpBox0')?.focus(), 100);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    function goToStep(next) {
        step = next;
        renderStep();
    }

    function newBranchRow() { return { branch_name: '', location: '', latitude: null, longitude: null, locating: false }; }
    function syncBranchSection() {
        const visible = inputs.business_structure.value === 'branch';
        $('branchSection').style.display = visible ? 'block' : 'none';
        if (visible && branchRows.length === 0) { branchRows = [newBranchRow()]; renderBranches(); }
    }
    function renderBranches() {
        $('branchList').innerHTML = branchRows.map((branch, index) => {
            const coords = branch.latitude !== null && branch.longitude !== null
                ? '<div style="font-size:11px;color:#27ae60;margin-top:4px;">' + Number(branch.latitude).toFixed(5) + ', ' + Number(branch.longitude).toFixed(5) + '</div>' : '';
            return '<div data-branch-index="' + index + '" style="background:white;border:1px solid #dbe7f3;border-radius:12px;padding:10px;margin-bottom:8px;">'
                + '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;"><strong style="font-size:12px;color:#34495e;">Tawi ' + (index + 1) + '</strong>'
                + (branchRows.length > 1 ? '<button type="button" data-remove-branch="' + index + '" style="border:none;background:transparent;color:#e74c3c;font-weight:700;cursor:pointer;">Ondoa</button>' : '') + '</div>'
                + '<input type="text" maxlength="150" data-branch-field="branch_name" placeholder="Jina la Tawi *" value="' + escapeHtml(branch.branch_name) + '" style="width:100%;padding:9px 11px;border:1px solid #ddd;border-radius:9px;margin-bottom:6px;font-size:14px;">'
                + '<input type="text" maxlength="255" data-branch-field="location" placeholder="Mahali pa Tawi *" value="' + escapeHtml(branch.location) + '" style="width:100%;padding:9px 11px;border:1px solid #ddd;border-radius:9px;margin-bottom:6px;font-size:14px;">'
                + '<button type="button" data-track-branch="' + index + '" style="width:100%;padding:9px;background:#e67e22;color:white;border:none;border-radius:9px;font-weight:700;font-size:12px;cursor:pointer;">'
                + (branch.locating ? 'Inatafuta eneo...' : (branch.latitude !== null ? 'Badili Eneo la Tawi' : 'Pata Eneo la Tawi (GPS)')) + '</button>' + coords + '</div>';
        }).join('');
    }
    $('branchList').addEventListener('input', event => {
        const field = event.target.dataset.branchField;
        if (!field) return;
        branchRows[Number(event.target.closest('[data-branch-index]').dataset.branchIndex)][field] = event.target.value;
    });
    $('branchList').addEventListener('click', async event => {
        const remove = event.target.closest('[data-remove-branch]');
        if (remove) { branchRows.splice(Number(remove.dataset.removeBranch), 1); renderBranches(); return; }
        const track = event.target.closest('[data-track-branch]');
        if (track) await trackBranchLocation(Number(track.dataset.trackBranch));
    });
    async function trackBranchLocation(index) {
        if (!navigator.geolocation) { showAlert({key:'admin_signup.alert_error'}, 'GPS haipatikani kwenye kifaa chako.'); return; }
        branchRows[index].locating = true; renderBranches();
        try {
            const position = await new Promise((resolve, reject) => navigator.geolocation.getCurrentPosition(resolve, reject, {enableHighAccuracy:true, timeout:15000}));
            const lat = position.coords.latitude, lng = position.coords.longitude;
            let areaName = '';
            try {
                const response = await fetch('https://nominatim.openstreetmap.org/reverse?format=json&lat=' + lat + '&lon=' + lng);
                if (response.ok) { const data = await response.json(); areaName = String(data.display_name || '').slice(0, 255); }
            } catch (e) {}
            if (!confirm('Tumia eneo hili kwa tawi hili?\n\n' + (areaName || lat.toFixed(5) + ', ' + lng.toFixed(5)))) { branchRows[index].locating = false; renderBranches(); return; }
            branchRows[index].latitude = lat; branchRows[index].longitude = lng;
            if (areaName) branchRows[index].location = areaName;
            branchRows[index].locating = false; renderBranches();
        } catch (e) {
            branchRows[index].locating = false; renderBranches();
            showAlert({key:'admin_signup.alert_error'}, 'Imeshindikana kupata eneo la tawi. Hakikisha ruhusa ya GPS imetolewa.');
        }
    }
    inputs.business_structure.addEventListener('change', syncBranchSection);
    $('addBranchBtn').addEventListener('click', () => { branchRows.push(newBranchRow()); renderBranches(); });
    syncBranchSection();

    function getBusinessValues() {
        return {
            business_name: inputs.business_name.value.trim(), business_location: inputs.business_location.value.trim(),
            business_type: inputs.business_type.value, business_description: inputs.business_description.value.trim(),
            business_structure: inputs.business_structure.value,
            branches: inputs.business_structure.value === 'branch' ? branchRows.map(branch => ({
                branch_name: branch.branch_name.trim(), location: branch.location.trim(), latitude: branch.latitude, longitude: branch.longitude
            })) : []
        };
    }
    function getPersonalValues() {
        return {
            full_name: inputs.full_name.value.trim(), email: inputs.email.value.trim(), phone: inputs.phone.value.trim(),
            password: inputs.password.value, confirm_password: inputs.confirmPassword.value
        };
    }
    function getFormValues() { return { role: 'admin', ...getBusinessValues(), ...getPersonalValues() }; }
    function validatePersonalLocal(form) {
        if (!form.full_name || !form.email || !form.password || !form.confirm_password) { showAlert({key:'admin_signup.alert_error'}, {key:'admin_signup.err_required_fields'}); return false; }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) { showAlert({key:'admin_signup.alert_error'}, {key:'admin_signup.err_email_invalid'}); return false; }
        if (form.password !== form.confirm_password) { showAlert({key:'admin_signup.alert_error'}, {key:'admin_signup.err_passwords_dont_match'}); return false; }
        if (form.password.length < 6) { showAlert({key:'admin_signup.alert_error'}, {key:'admin_signup.err_password_short'}); return false; }
        return true;
    }
    async function continueBusiness() {
        if (loading) return;
        const business = getBusinessValues();
        setButtonLoading($('continueBtn'), true, 'Endelea'); loading = true;
        try {
            const {response, data} = await postJson('/api/register/check-business', business);
            if (response.ok && data.success) goToStep(2);
            else showAlert({key:'admin_signup.alert_error'}, errorMessage(data, 'admin_signup.err_try_again'));
        } catch (e) { showNetworkError(); }
        finally { loading = false; setButtonLoading($('continueBtn'), false, 'Endelea'); }
    }
    async function previewPersonal() {
        if (loading) return;
        const personal = getPersonalValues();
        if (!validatePersonalLocal(personal)) return;
        setButtonLoading($('previewBtn'), true, 'Hakiki'); loading = true;
        try {
            const {response, data} = await postJson('/api/register/check-personal', {role:'admin', ...personal});
            if (response.ok && data.success) { formValues = {...getBusinessValues(), ...personal, role:'admin'}; goToStep(3); }
            else showAlert({key:'admin_signup.alert_error'}, errorMessage(data, 'admin_signup.err_try_again'));
        } catch (e) { showNetworkError(); }
        finally { loading = false; setButtonLoading($('previewBtn'), false, 'Hakiki'); }
    }
    function reviewCard(title, editStep, rows) {
        return '<div class="review-card"><div class="review-heading"><strong>' + escapeHtml(title) + '</strong><button type="button" class="text-btn" data-edit-step="' + editStep + '">Hariri</button></div>'
            + rows.map(row => '<div class="review-row"><span class="review-label">' + escapeHtml(row[0]) + '</span><span class="review-value">' + escapeHtml(row[1] || '—') + '</span></div>').join('') + '</div>';
    }
    function renderReview() {
        if (!formValues) formValues = getFormValues();
        const branches = formValues.branches.length ? formValues.branches.map((branch, i) => (i + 1) + '. ' + branch.branch_name + ' — ' + branch.location
            + (branch.latitude !== null ? ' (' + Number(branch.latitude).toFixed(5) + ', ' + Number(branch.longitude).toFixed(5) + ')' : '')).join('\n') : 'Hakuna matawi';
        $('reviewContent').innerHTML = reviewCard('Taarifa za Biashara', 1, [
            ['Jina la Biashara', formValues.business_name], ['Mahali', formValues.business_location],
            ['Aina ya Biashara', businessLabel(formValues.business_type)], ['Maelezo', formValues.business_description],
            ['Muundo', formValues.business_structure === 'branch' ? 'Biashara Yenye Matawi' : 'Biashara Bila Matawi'], ['Matawi', branches]
        ]) + reviewCard('Taarifa za Msimamizi', 2, [
            ['Jina Kamili', formValues.full_name], ['Barua Pepe', formValues.email], ['Namba ya Simu', formValues.phone], ['Nenosiri', '••••••']
        ]);
        $('reviewContent').querySelectorAll('[data-edit-step]').forEach(button => button.addEventListener('click', () => goToStep(Number(button.dataset.editStep))));
    }
    async function verifyAndSendOtp() {
        if (loading) return;
        formValues = getFormValues();
        if (!validatePersonalLocal(formValues)) { goToStep(2); return; }
        setButtonLoading($('verifyBtn'), true, 'Thibitisha na Endelea'); loading = true;
        try {
            const check = await postJson('/api/register/precheck', formValues);
            if (!check.response.ok || !check.data.success) {
                const target = check.data.step === 'business' ? 1 : (check.data.step === 'personal' ? 2 : 3);
                goToStep(target);
                showAlert({key:'admin_signup.alert_error'}, errorMessage(check.data, 'admin_signup.err_try_again'));
                return;
            }
            const result = await postJson('/api/register/initiate', formValues);
            if (!result.response.ok || !(result.data.success || result.data.requiresOtp)) {
                showAlert({key:'admin_signup.alert_error'}, errorMessage(result.data, 'admin_signup.err_try_again'));
                return;
            }
            otpDigits = Array(OTP_LENGTH).fill('');
            $('emailWarning').classList.toggle('show', result.data.email_sent === false);
            $('emailWarning').textContent = result.data.warning || 'Msimbo haukuweza kutumwa. Tumia “Tuma msimbo tena” kujaribu tena.';
            goToStep(4);
            startOtpCountdown();
        } catch (e) { showNetworkError(); }
        finally { loading = false; setButtonLoading($('verifyBtn'), false, 'Thibitisha na Endelea'); }
    }

    function buildOtpBoxes() {
        $('otpBoxes').innerHTML = Array.from({length:OTP_LENGTH}, (_, i) => '<input class="otp-box" id="otpBox' + i + '" maxlength="1" inputmode="numeric" pattern="[0-9]*" aria-label="Tarakimu ' + (i + 1) + '">').join('');
        for (let i = 0; i < OTP_LENGTH; i++) {
            const box = $('otpBox' + i);
            box.value = otpDigits[i];
            box.classList.toggle('filled', !!otpDigits[i]);
            box.addEventListener('input', () => {
                const digit = box.value.replace(/[^0-9]/g, '').slice(-1);
                otpDigits[i] = digit; box.value = digit; box.classList.toggle('filled', !!digit); box.classList.remove('error');
                if (digit && i < OTP_LENGTH - 1) $('otpBox' + (i + 1)).focus();
            });
            box.addEventListener('paste', event => {
                const code = (event.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '').slice(0, OTP_LENGTH);
                if (code.length > 1) { event.preventDefault(); otpDigits = code.split(''); buildOtpBoxes(); $('otpBox' + Math.min(code.length, OTP_LENGTH - 1)).focus(); }
            });
            box.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && !otpDigits[i] && i > 0) { otpDigits[i - 1] = ''; $('otpBox' + (i - 1)).value = ''; $('otpBox' + (i - 1)).classList.remove('filled'); $('otpBox' + (i - 1)).focus(); }
                if (event.key === 'Enter') finishRegistration();
            });
        }
    }
    function startOtpCountdown() {
        otpCountdown = OTP_RESEND_COOLDOWN;
        if (otpTimer) clearInterval(otpTimer);
        showResendState();
        otpTimer = setInterval(() => { otpCountdown--; showResendState(); if (otpCountdown <= 0) { clearInterval(otpTimer); otpTimer = null; } }, 1000);
    }
    function showResendState() {
        $('resendBtn').disabled = otpBusy || otpCountdown > 0;
        $('resendBtn').textContent = otpBusy ? t('admin_signup.otp_sending') : (otpCountdown > 0 ? t('admin_signup.otp_resend_countdown', {seconds:otpCountdown}) : t('admin_signup.otp_resend_code'));
    }
    async function resendOtp() {
        if (otpBusy || otpCountdown > 0 || !formValues) return;
        otpBusy = true; showResendState();
        try {
            const {response, data} = await postJson('/api/register/resend-otp', {email:formValues.email, role:'admin'});
            if (response.ok && data.success) {
                otpDigits = Array(OTP_LENGTH).fill(''); buildOtpBoxes();
                $('emailWarning').classList.toggle('show', data.email_sent === false);
                $('emailWarning').textContent = data.warning || '';
                startOtpCountdown();
            } else showAlert({key:'admin_signup.alert_error'}, errorMessage(data, 'admin_signup.err_try_again'));
        } catch (e) { showNetworkError(); }
        finally { otpBusy = false; showResendState(); }
    }
    async function finishRegistration() {
        if (otpBusy) return;
        const code = otpDigits.join('');
        if (code.length !== OTP_LENGTH) { showAlert({key:'admin_signup.alert_error'}, {key:'admin_signup.err_code_required'}); return; }
        otpBusy = true; setButtonLoading($('finishBtn'), true, 'Maliza Usajili'); $('resendBtn').disabled = true;
        try {
            const verified = await postJson('/api/register/verify-otp', {email:formValues.email, role:'admin', otp:code});
            if (!verified.response.ok || !verified.data.success || !verified.data.verified) {
                showAlert({key:'admin_signup.alert_error'}, errorMessage(verified.data, 'admin_signup.err_code_incorrect'));
                return;
            }
            registrationToken = verified.data.registrationToken;
            const created = await postJson('/api/register', {verificationToken:registrationToken, email:formValues.email, role:'admin'});
            if (!created.response.ok) { showAlert({key:'admin_signup.alert_error'}, errorMessage(created.data, 'admin_signup.err_try_again')); return; }
            if (otpTimer) clearInterval(otpTimer);
            showAlert({key:'admin_signup.otp_success_title'}, {key:'admin_signup.otp_success_message'}, null, true);
        } catch (e) { showNetworkError(); }
        finally { otpBusy = false; setButtonLoading($('finishBtn'), false, 'Maliza Usajili'); showResendState(); }
    }

    $('continueBtn').addEventListener('click', continueBusiness);
    $('previewBtn').addEventListener('click', previewPersonal);
    $('verifyBtn').addEventListener('click', verifyAndSendOtp);
    $('finishBtn').addEventListener('click', finishRegistration);
    $('resendBtn').addEventListener('click', resendOtp);
    $('backBtn').addEventListener('click', () => {
        if (loading || otpBusy) return;
        if (step === 1) {
            if (document.referrer && document.referrer.includes(window.location.host)) window.history.back(); else window.location.href = '/home';
        } else {
            if (step === 4 && otpTimer) clearInterval(otpTimer);
            goToStep(step - 1);
        }
    });
    document.addEventListener('keydown', event => {
        if (event.key !== 'Enter' || event.target.tagName === 'TEXTAREA' || event.target.tagName === 'BUTTON') return;
        if (step === 1) continueBusiness(); else if (step === 2) previewPersonal();
    });
    if (window.DM) window.DM.onChange(showResendState);
    renderStep();
</script>
</body>
</html>
@endverbatim
