/**
 * InvenAI — UI Enhancements
 * Counter animations, 3D tilt, scroll reveal, toasts, dark mode, clock, search
 */

// ── Live Clock ──────────────────────────────────────────────────────────
(function () {
    const el = document.getElementById('navbar-clock');
    if (!el) return;
    function tick() {
        const now = new Date();
        el.textContent = now.toLocaleTimeString('en-US', { hour12: false });
    }
    tick();
    setInterval(tick, 1000);
})();

// ── Dark Mode Toggle ────────────────────────────────────────────────────
(function () {
    const btn  = document.getElementById('darkModeToggle');
    const root = document.documentElement;
    const LIGHT_VARS = {
        '--bg-base':       '#f1f5f9',
        '--bg-surface':    '#e2e8f0',
        '--bg-card':       '#ffffff',
        '--bg-elevated':   '#f8fafc',
        '--bg-hover':      '#e2e8f0',
        '--text-primary':  '#0f172a',
        '--text-secondary':'#334155',
        '--text-muted':    '#64748b',
        '--text-disabled': '#94a3b8',
        '--border-subtle': 'rgba(99,102,241,0.1)',
        '--border-default':'rgba(99,102,241,0.2)',
        '--glass-bg':      'rgba(248,250,252,0.85)',
        '--grad-sidebar':  'linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%)',
    };
    let isLight = localStorage.getItem('theme') === 'light';

    function applyTheme() {
        if (isLight) {
            Object.entries(LIGHT_VARS).forEach(([k, v]) => root.style.setProperty(k, v));
            if (btn) btn.innerHTML = '<i class="bi bi-moon-fill"></i>';
        } else {
            Object.entries(LIGHT_VARS).forEach(([k]) => root.style.removeProperty(k));
            if (btn) btn.innerHTML = '<i class="bi bi-sun-fill"></i>';
        }
        localStorage.setItem('theme', isLight ? 'light' : 'dark');
    }

    applyTheme();

    if (btn) {
        btn.addEventListener('click', () => {
            isLight = !isLight;
            applyTheme();
            InvenAI.toast(isLight ? 'Switched to Light Mode' : 'Switched to Dark Mode', 'info');
        });
    }
})();

// ── Toast System ─────────────────────────────────────────────────────────
window.InvenAI = window.InvenAI || {};
(function () {
    let container = document.getElementById('toastContainerCustom');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainerCustom';
        container.className = 'toast-container-custom';
        document.body.appendChild(container);
    }

    const ICONS = {
        success: 'bi-check-circle-fill',
        danger:  'bi-exclamation-circle-fill',
        warning: 'bi-exclamation-triangle-fill',
        info:    'bi-info-circle-fill',
    };

    window.InvenAI.toast = function (message, type = 'info', duration = 4000) {
        const toast = document.createElement('div');
        toast.className = `toast-item toast-${type}`;
        toast.innerHTML = `
            <i class="bi ${ICONS[type] || ICONS.info} toast-icon"></i>
            <div class="toast-body"><div class="toast-msg">${message}</div></div>
            <button class="toast-close" onclick="this.closest('.toast-item').remove()">
                <i class="bi bi-x"></i>
            </button>
        `;
        container.appendChild(toast);
        setTimeout(() => {
            toast.classList.add('leaving');
            setTimeout(() => toast.remove(), 300);
        }, duration);
        return toast;
    };
})();

// ── Count-Up Animation ───────────────────────────────────────────────────
(function () {
    function countUp(el) {
        const target = parseFloat(el.dataset.count || el.textContent.replace(/[^0-9.]/g, ''));
        const prefix = el.dataset.prefix || '';
        const suffix = el.dataset.suffix || '';
        const decimals = el.dataset.decimals ? parseInt(el.dataset.decimals) : (String(target).includes('.') ? 2 : 0);
        if (isNaN(target)) return;

        const duration = 1400;
        const start    = performance.now();

        function update(now) {
            const elapsed  = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // Ease out quart
            const ease = 1 - Math.pow(1 - progress, 4);
            const current = target * ease;
            el.textContent = prefix + current.toFixed(decimals).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + suffix;
            if (progress < 1) requestAnimationFrame(update);
        }
        requestAnimationFrame(update);
    }

    function initCounters() {
        document.querySelectorAll('[data-count-up]').forEach(el => {
            // Store original value
            el.dataset.count = el.dataset.count || el.textContent.replace(/[^0-9.]/g, '');
            countUp(el);
        });
    }

    // Run after page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCounters);
    } else {
        initCounters();
    }

    window.InvenAI.countUp = countUp;
})();

// ── 3D Tilt Effect on Stat Cards ─────────────────────────────────────────
(function () {
    function initTilt(cards) {
        cards.forEach(card => {
            card.addEventListener('mousemove', function (e) {
                const rect   = card.getBoundingClientRect();
                const cx     = rect.left + rect.width  / 2;
                const cy     = rect.top  + rect.height / 2;
                const dx     = (e.clientX - cx) / (rect.width  / 2);
                const dy     = (e.clientY - cy) / (rect.height / 2);
                const tiltX  = dy * -10;
                const tiltY  = dx * 10;
                card.style.transform = `perspective(600px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) translateZ(4px)`;
                card.style.transition = 'transform 0.1s ease';
            });
            card.addEventListener('mouseleave', function () {
                card.style.transform = '';
                card.style.transition = 'transform 0.4s cubic-bezier(0.34,1.56,0.64,1)';
            });
        });
    }

    function setup() {
        initTilt(document.querySelectorAll('.stat-card'));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();

// ── Scroll Reveal ────────────────────────────────────────────────────────
(function () {
    function setup() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach((entry, i) => {
                if (entry.isIntersecting) {
                    setTimeout(() => {
                        entry.target.classList.add('visible');
                    }, i * 80);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });

        document.querySelectorAll('.reveal').forEach(el => observer.observe(el));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();

// ── Global Search ────────────────────────────────────────────────────────
(function () {
    const PAGES = [
        { name: 'Dashboard',        icon: 'bi-speedometer2',     url: 'dashboard.php' },
        { name: 'Products',         icon: 'bi-box-seam',         url: 'products.php' },
        { name: 'Categories',       icon: 'bi-tags',             url: 'categories.php' },
        { name: 'Inventory',        icon: 'bi-clipboard-data',   url: 'inventory.php' },
        { name: 'Invoices',         icon: 'bi-receipt',          url: 'invoices.php' },
        { name: 'Create Invoice',   icon: 'bi-file-earmark-plus',url: 'create_invoice.php' },
        { name: 'Reservations',     icon: 'bi-calendar-check',   url: 'reservations.php' },
        { name: 'QR Codes',         icon: 'bi-qr-code',         url: 'qr_codes.php' },
        { name: 'AI Forecast',      icon: 'bi-graph-up-arrow',   url: 'forecast.php' },
        { name: 'Reports',          icon: 'bi-bar-chart-line',   url: 'reports.php' },
        { name: 'Notifications',    icon: 'bi-bell',             url: 'notifications.php' },
        { name: 'User Management',  icon: 'bi-people',           url: 'user_management.php' },
    ];

    function setup() {
        const input   = document.getElementById('globalSearch');
        const overlay = document.getElementById('searchOverlay');
        if (!input || !overlay) return;

        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            if (!q) { overlay.style.display = 'none'; return; }

            const results = PAGES.filter(p => p.name.toLowerCase().includes(q));
            if (!results.length) {
                overlay.innerHTML = `<div style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.8rem;"><i class="bi bi-search"></i> No results for "${q}"</div>`;
            } else {
                overlay.innerHTML = results.map(r => `
                    <a href="${r.url}" style="display:flex;align-items:center;gap:0.6rem;padding:0.65rem 1rem;color:var(--text-secondary);text-decoration:none;border-bottom:1px solid var(--border-subtle);transition:background 0.15s;font-size:0.875rem;"
                       onmouseover="this.style.background='rgba(99,102,241,0.1)';this.style.color='var(--text-primary)';"
                       onmouseout="this.style.background='';this.style.color='var(--text-secondary)';">
                        <i class="bi ${r.icon}" style="color:var(--accent-primary);width:16px;text-align:center;"></i>
                        ${r.name}
                    </a>
                `).join('');
            }
            overlay.style.display = 'block';
        });

        input.addEventListener('focus', function () {
            if (this.value.trim()) overlay.style.display = 'block';
        });

        document.addEventListener('click', function (e) {
            if (!overlay.contains(e.target) && e.target !== input) {
                overlay.style.display = 'none';
            }
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { overlay.style.display = 'none'; this.blur(); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setup);
    } else {
        setup();
    }
})();

// ── Keyboard Shortcut: / to focus search ─────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
        e.preventDefault();
        const s = document.getElementById('globalSearch');
        if (s) s.focus();
    }
});
