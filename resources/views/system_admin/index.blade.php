@include('partials.dm-locale')
@verbatim
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title data-i18n="system_admin_index.page_title">DukaMkononi - System Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .redirect-container {
            text-align: center;
            padding: 40px;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 28px;
            max-width: 400px;
            margin: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .logo span {
            font-size: 48px;
            font-weight: bold;
            color: white;
        }

        .title {
            font-size: 28px;
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 12px;
        }

        .subtitle {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 24px;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #e0e0e0;
            border-top-color: #667eea;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            margin: 20px auto;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .message {
            color: #2c3e50;
            margin: 16px 0;
            font-size: 14px;
        }

        .redirect-note {
            font-size: 12px;
            color: #95a5a6;
            margin-top: 20px;
        }

        .manual-link {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }

        .manual-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }

        .manual-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
@endverbatim
@include('partials.dm-lang-widget')
@verbatim
    <div class="redirect-container">
        <div class="logo">
            <span data-i18n="system_admin_index.logo_letter">D</span>
        </div>
        <div class="title" data-i18n="system_admin_index.brand">DukaMkononi</div>
        <div class="subtitle" data-i18n="system_admin_index.portal_label">System Admin Portal</div>
        
        <div class="loading-spinner"></div>
        
        <div class="message" id="message" data-i18n="system_admin_index.redirecting_dashboard">Inaelekeza kwenye dashbodi...</div>
        
        <div class="redirect-note" data-i18n="system_admin_index.please_wait">
            Tafadhali subiri, unaelekezwa kwenye ukurasa wa dashbodi
        </div>
        
        <div class="manual-link">
            <a href="dashboard" id="manualLink" data-i18n="system_admin_index.manual_link">Bonyeza hapa ikiwa huelekezwi</a>
        </div>
    </div>

    <script>
        // ============================================
        // SYSTEM ADMIN INDEX - Redirect to Dashboard
        // Mirrors the mobile expo-router redirect
        // ============================================
        
        const API_BASE_URL = '';

        // Swahili fallback used when the language widget is not present (and
        // for script-built strings). Values mirror the sw catalog of the
        // system_admin_index section in locales.json.
        const SW = {
            page_title: 'DukaMkononi - System Admin',
            logo_letter: 'D',
            brand: 'DukaMkononi',
            portal_label: 'System Admin Portal',
            redirecting_dashboard: 'Inaelekeza kwenye dashbodi...',
            please_wait: 'Tafadhali subiri, unaelekezwa kwenye ukurasa wa dashbodi',
            manual_link: 'Bonyeza hapa ikiwa huelekezwi',
            no_user: 'Hakuna mtumiaji aliyeingia. Unaelekezwa kwenye ukurasa wa kuingia...',
            no_permission: 'Huna ruhusa ya kuingia kwenye eneo hili. Unaelekezwa nyumbani...',
            session_expired: 'Weka muda umeisha. Unaelekezwa kwenye ukurasa wa kuingia...',
            signed_in_ok: 'Umeingia kikamilifu! Unaelekezwa kwenye dashbodi...',
            data_error: 'Hitilafu katika data ya mtumiaji. Unaelekezwa kwenye ukurasa wa kuingia...',
        };

        function t(key, params) {
            const full = key.indexOf('system_admin_index.') === 0 ? key : 'system_admin_index.' + key;
            if (window.DM && typeof window.DM.t === 'function') {
                const hit = window.DM.t(full, params);
                if (hit !== full) return hit;
            }
            const bare = key.indexOf('.') > -1 ? key.split('.').pop() : key;
            let value = Object.prototype.hasOwnProperty.call(SW, bare) ? SW[bare] : key;
            if (params) {
                for (const k in params) value = String(value).split('{' + k + '}').join(String(params[k]));
            }
            return value;
        }

        // The current status message, so the language picker can repaint it.
        let currentMsgKey = 'redirecting_dashboard';
        function setMessage(key) {
            currentMsgKey = key;
            const el = document.getElementById('message');
            if (el) el.innerHTML = t(key);
        }

        // Check if user is logged in and is system admin
        async function checkAuthAndRedirect() {
            const token = localStorage.getItem('userToken');
            const userDataStr = localStorage.getItem('userData');
            
            if (!token || !userDataStr) {
                // No user logged in, redirect to login with admin role
                console.log('No user logged in, redirecting to login...');
                setTimeout(() => {
                    window.location.href = '/login?role=msimamizi';
                }, 1500);
                setMessage('no_user');
                return;
            }
            
            try {
                const userData = JSON.parse(userDataStr);
                const userEmail = userData.email || '';
                const isSystemAdmin = userEmail === "cosmavictorini1994@gmail.com";
                
                if (!isSystemAdmin && userData.role !== 'system_admin') {
                    // User is not system admin, redirect to home
                    console.log('User is not system admin, redirecting to home...');
                    setMessage('no_permission');
                    setTimeout(() => {
                        window.location.href = '/home';
                    }, 2000);
                    return;
                }
                
                // Verify token with backend
                try {
                    const response = await fetch(`${API_BASE_URL}/api/user/profile`, {
                        headers: {
                            'Authorization': `Bearer ${token}`,
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    if (!response.ok) {
                        // Token invalid, clear storage and redirect to login
                        console.log('Invalid token, clearing storage...');
                        localStorage.removeItem('userToken');
                        localStorage.removeItem('userData');
                        setMessage('session_expired');
                        setTimeout(() => {
                            window.location.href = '/login?role=msimamizi';
                        }, 2000);
                        return;
                    }
                } catch (error) {
                    console.log('Token verification failed, but proceeding to dashboard');
                }
                
                // User is authenticated and is system admin, redirect to dashboard
                console.log('Authenticated as system admin, redirecting to dashboard...');
                setMessage('signed_in_ok');
                
                setTimeout(() => {
                    window.location.href = 'dashboard';
                }, 1000);
                
            } catch (error) {
                console.error('Error parsing user data:', error);
                setMessage('data_error');
                setTimeout(() => {
                    window.location.href = '/login?role=msimamizi';
                }, 2000);
            }
        }
        
        // Also listen for manual link click to force redirect
        document.getElementById('manualLink').addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = 'dashboard';
        });
        
        // Follow the language: static markup is handled by data-i18n, but the
        // status message is set by script.
        if (window.DM && typeof window.DM.onChange === 'function') {
            window.DM.onChange(() => setMessage(currentMsgKey));
        }

        // Start the redirect process
        checkAuthAndRedirect();
    </script>
</body>
</html>
@endverbatim