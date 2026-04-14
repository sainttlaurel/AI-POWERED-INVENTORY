// ── Sidebar Toggle (mobile) ───────────────────────────────────────────────
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
    document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
}

function closeSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;
    sidebar.classList.remove('show');
    if (overlay) overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Close sidebar when ESC is pressed
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeSidebar();
});

// ── Page fade-in ─────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    const main = document.querySelector('main');
    if (main) main.classList.add('fade-in');
});

// ── Checkbox helpers ──────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function () {
    // Select-all
    const selectAll = document.getElementById('select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            document.querySelectorAll('.product-checkbox').forEach((cb, i) => {
                setTimeout(() => {
                    cb.checked = selectAll.checked;
                    cb.dispatchEvent(new Event('change'));
                }, i * 40);
            });
        });
    }

    // Individual product checkbox visual feedback
    document.querySelectorAll('.product-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
            this.style.transform = this.checked ? 'scale(1.1)' : 'scale(1)';
            setTimeout(() => { this.style.transform = ''; }, 250);
        });
    });
});

// ── Ripple effect on buttons ──────────────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.btn');
    if (!btn || btn.closest('.chatbot-widget-modern')) return;
    const rect   = btn.getBoundingClientRect();
    const ripple = document.createElement('span');
    const size   = Math.max(rect.width, rect.height);
    ripple.style.cssText = `
        position:absolute; border-radius:50%; pointer-events:none;
        width:${size}px; height:${size}px;
        left:${e.clientX - rect.left - size/2}px;
        top:${e.clientY - rect.top - size/2}px;
        background:rgba(255,255,255,0.25);
        transform:scale(0); animation:rippleEffect 0.5s ease-out forwards;
    `;
    if (getComputedStyle(btn).position === 'static') btn.style.position = 'relative';
    btn.style.overflow = 'hidden';
    btn.appendChild(ripple);
    setTimeout(() => ripple.remove(), 500);
});

// Ripple keyframes
const rippleStyle = document.createElement('style');
rippleStyle.textContent = `@keyframes rippleEffect{0%{transform:scale(0);opacity:1}100%{transform:scale(2.5);opacity:0}}`;
document.head.appendChild(rippleStyle);