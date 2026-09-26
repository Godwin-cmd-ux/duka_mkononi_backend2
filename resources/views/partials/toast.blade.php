@verbatim
<!-- ===== Shared toast notification component (self-contained) ===== -->
<style>
    .toast-container {
        position: fixed;
        top: 16px;
        right: 16px;
        z-index: 99999;
        display: flex;
        flex-direction: column;
        gap: 10px;
        width: min(380px, calc(100vw - 32px));
        pointer-events: none;
    }

    .toast-item {
        pointer-events: auto;
        display: flex;
        align-items: flex-start;
        gap: 12px;
        background: #ffffff;
        border-radius: 12px;
        padding: 14px 14px 14px 16px;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.14), 0 2px 6px rgba(15, 23, 42, 0.08);
        border-left: 4px solid #3b82f6;
        animation: toastIn 0.32s cubic-bezier(0.21, 1.02, 0.73, 1) forwards;
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    }

    .toast-item.toast-out {
        animation: toastOut 0.28s ease-in forwards;
    }

    .toast-icon {
        flex-shrink: 0;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-top: 1px;
    }

    .toast-icon svg {
        width: 15px;
        height: 15px;
    }

    .toast-msg {
        flex: 1;
        font-size: 14px;
        line-height: 1.45;
        color: #1e293b;
        font-weight: 500;
        white-space: pre-line;
        word-break: break-word;
        padding-top: 4px;
    }

    .toast-close {
        flex-shrink: 0;
        background: transparent;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        font-size: 16px;
        line-height: 1;
        padding: 6px 4px;
        margin: -2px -2px 0 0;
        border-radius: 6px;
        transition: color 0.15s ease, background 0.15s ease;
    }

    .toast-close:hover {
        color: #475569;
        background: #f1f5f9;
    }

    /* Type accents */
    .toast-item.toast-success { border-left-color: #22c55e; }
    .toast-item.toast-success .toast-icon { background: #dcfce7; color: #16a34a; }

    .toast-item.toast-error { border-left-color: #ef4444; }
    .toast-item.toast-error .toast-icon { background: #fee2e2; color: #dc2626; }

    .toast-item.toast-warning { border-left-color: #f59e0b; }
    .toast-item.toast-warning .toast-icon { background: #fef3c7; color: #d97706; }

    .toast-item.toast-info { border-left-color: #3b82f6; }
    .toast-item.toast-info .toast-icon { background: #dbeafe; color: #2563eb; }

    @keyframes toastIn {
        from { transform: translateX(115%); opacity: 0; }
        to   { transform: translateX(0);     opacity: 1; }
    }

    @keyframes toastOut {
        from { transform: translateX(0);   opacity: 1; }
        to   { transform: translateX(115%); opacity: 0; }
    }

    @media (max-width: 480px) {
        .toast-container {
            top: 12px;
            left: 12px;
            right: 12px;
            width: auto;
        }
    }
</style>
<div class="toast-container" id="toastContainer" aria-live="polite"></div>
<script>
    (function () {
        if (window.showToast) return; // register once, even if re-injected

        var ICONS = {
            success: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
            error: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>',
            warning: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg>',
            info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>'
        };

        var DURATIONS = { success: 3000, info: 3500, warning: 4500, error: 5500 };
        var MAX_VISIBLE = 4;

        function removeToast(el) {
            if (!el || el.dataset.leaving) return;
            el.dataset.leaving = '1';
            el.classList.add('toast-out');
            setTimeout(function () { el.remove(); }, 280);
        }

        window.showToast = function (message, type, duration) {
            type = type || 'info';
            var container = document.getElementById('toastContainer');
            if (!container) return;

            // Keep the stack short: drop the oldest toast beyond the limit
            var existing = container.querySelectorAll('.toast-item:not(.toast-out)');
            if (existing.length >= MAX_VISIBLE) removeToast(existing[0]);

            var el = document.createElement('div');
            el.className = 'toast-item toast-' + type;
            el.setAttribute('role', 'status');

            var iconWrap = document.createElement('div');
            iconWrap.className = 'toast-icon';
            iconWrap.innerHTML = ICONS[type] || ICONS.info;

            var msg = document.createElement('div');
            msg.className = 'toast-msg';
            msg.textContent = message || '';

            var close = document.createElement('button');
            close.className = 'toast-close';
            close.setAttribute('aria-label', 'Funga');
            close.innerHTML = '&times;';
            close.onclick = function () { removeToast(el); };

            el.appendChild(iconWrap);
            el.appendChild(msg);
            el.appendChild(close);
            container.appendChild(el);

            var ms = typeof duration === 'number' ? duration : (DURATIONS[type] || 3500);
            setTimeout(function () { removeToast(el); }, ms);

            return el;
        };
    })();
</script>
@endverbatim
