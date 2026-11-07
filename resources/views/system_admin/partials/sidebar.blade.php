@php
    // Which nav item is current. Each page passes its own key, so the sidebar
    // never has to guess from the URL (and stays correct for both
    // /system_admin/x and the folder-relative links the older pages used).
    $saActive = $saActive ?? '';
@endphp
<link rel="stylesheet" href="/css/system-admin.css?v={{ filemtime(public_path('css/system-admin.css')) }}">
<aside class="sa-sidebar" id="dmSidebar">
    <div class="sa-brand">
        <div class="sa-brand-badge">D</div>
        <div>
            <h2 data-i18n="system_admin_sidebar.logo_brand">DukaMkononi</h2>
            <p data-i18n="system_admin_sidebar.logo_tagline">System Admin</p>
        </div>
    </div>

    <nav class="sa-nav">
        <div class="sa-nav-label">Platform</div>

        <a class="sa-nav-item {{ $saActive === 'dashboard' ? 'active' : '' }}" href="/system_admin/dashboard">
            <span class="sa-nav-icon">▦</span>
            <span data-i18n="system_admin_sidebar.nav_dashboard">Dashbodi</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'users' ? 'active' : '' }}" href="/system_admin/users">
            <span class="sa-nav-icon">👥</span>
            <span>Watumiaji</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'businesses' ? 'active' : '' }}" href="/system_admin/businesses">
            <span class="sa-nav-icon">🏢</span>
            <span>Biashara</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'logs' ? 'active' : '' }}" href="/system_admin/logs">
            <span class="sa-nav-icon">☰</span>
            <span>Rekodi</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'inquiries' ? 'active' : '' }}" href="/system_admin/inquiries">
            <span class="sa-nav-icon">✉</span>
            <span data-i18n="system_admin_inquiries.nav_inquiries">Inquiries</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'certification' ? 'active' : '' }}" href="/system_admin/certification">
            <span class="sa-nav-icon">✔</span>
            <span data-i18n="certification_ai.nav_certification">Certification</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'analytics' ? 'active' : '' }}" href="/system_admin/analytics">
            <span class="sa-nav-icon">▤</span>
            <span data-i18n="admin_analytics.nav_analytics">Analytics</span>
        </a>

        <a class="sa-nav-item {{ $saActive === 'notify' ? 'active' : '' }}" href="/system_admin/notify">
            <span class="sa-nav-icon">◎</span>
            <span data-i18n="system_admin_sidebar.nav_notify">Notisi</span>
        </a>

        <div class="sa-nav-label">System</div>

        <a class="sa-nav-item {{ $saActive === 'backup' ? 'active' : '' }}" href="/system_admin/backup">
            <span class="sa-nav-icon">⬒</span>
            <span>Backups</span>
        </a>

        <a class="sa-nav-item" href="/msimamizi/index">
            <span class="sa-nav-icon">⌂</span>
            <span data-i18n="system_admin_sidebar.nav_home">Nyumbani</span>
        </a>
    </nav>

    <div class="sa-sidebar-foot">
        <div class="sa-user">
            <div class="sa-avatar" id="dmAvatar">A</div>
            <div>
                <b id="dmUserName">Super Admin</b>
                <span data-i18n="system_admin_inquiries.user_role">Super Admin</span>
            </div>
        </div>
        <button type="button" class="sa-logout" id="dmLogout">
            <span data-i18n="system_admin_inquiries.logout">Logout</span>
        </button>
    </div>
</aside>

<button type="button" class="sa-mobile-toggle" id="saMenuBtn" aria-label="Menu">☰</button>

<script>
    (function () {
        // Sidebar behaviour shared by every super-admin page: mobile toggle,
        // the signed-in user in the footer, and logout. Page scripts keep their
        // own handlers; everything here is idempotent so running twice is safe.
        var sidebar = document.getElementById('dmSidebar');
        var toggle = document.getElementById('saMenuBtn');

        if (toggle && sidebar) {
            toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
        }

        if (sidebar) {
            document.addEventListener('click', function (event) {
                if (window.innerWidth > 1024) return;
                if (!sidebar.classList.contains('open')) return;
                if (sidebar.contains(event.target)) return;
                if (toggle && toggle.contains(event.target)) return;
                sidebar.classList.remove('open');
            });
        }

        function currentUser() {
            try {
                var raw = localStorage.getItem('userData');
                return raw ? JSON.parse(raw) : null;
            } catch (e) {
                return null;
            }
        }

        var user = currentUser();
        if (user) {
            var name = user.full_name || (user.email ? user.email.split('@')[0] : null);
            var nameEl = document.getElementById('dmUserName');
            var avatarEl = document.getElementById('dmAvatar');

            if (nameEl && name) nameEl.textContent = name;
            if (avatarEl) {
                if (user.business_logo_url) {
                    avatarEl.innerHTML = '<img src="' + user.business_logo_url + '" alt="">';
                } else if (name) {
                    avatarEl.textContent = name.charAt(0).toUpperCase();
                }
            }
        }

        var logout = document.getElementById('dmLogout');
        if (logout) {
            logout.addEventListener('click', function () {
                if (!confirm('Una uhakika unataka kutoka?')) return;
                ['userToken', 'userData', 'userId', 'userEmail', 'userRole',
                 'userStatus', 'userName', 'userBusiness'].forEach(function (key) {
                    localStorage.removeItem(key);
                });
                document.cookie = 'dm_admin_token=; path=/; max-age=0';
                window.location.href = '/login?role=msimamizi';
            });
        }
    })();
</script>
