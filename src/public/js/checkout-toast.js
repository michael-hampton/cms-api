(function () {
    const LEVELS = new Set(['info', 'success', 'warning', 'error']);
    const DEFAULT_DURATION = 5000;

    function container() {
        return document.getElementById('toast-container');
    }

    function dismiss(toast) {
        if (!toast || toast.dataset.dismissing === 'true') return;

        toast.dataset.dismissing = 'true';
        toast.classList.add('checkout-toast--leaving');
        toast.addEventListener('animationend', () => toast.remove(), {once: true});
        window.setTimeout(() => toast.remove(), 200);
    }

    window.showToast = function (message, options = {}) {
        const host = container();
        if (!host || !message) return null;

        const config = typeof options === 'string' ? {level: options} : options;
        const level = LEVELS.has(config.level) ? config.level : 'info';
        const needsDismiss = config.needs_dismiss === true;
        const timesOut = config.times_out === true;
        const duration = Number.isFinite(config.duration) && config.duration > 0
            ? config.duration
            : DEFAULT_DURATION;

        const toast = document.createElement('div');
        toast.className = `checkout-toast checkout-toast--${level}`;
        toast.setAttribute('role', level === 'error' ? 'alert' : 'status');

        const text = document.createElement('div');
        text.className = 'checkout-toast__message';
        text.textContent = String(message);
        toast.appendChild(text);

        if (needsDismiss) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'checkout-toast__dismiss';
            button.setAttribute('aria-label', 'Dismiss notification');
            button.textContent = '×';
            button.addEventListener('click', () => dismiss(toast));
            toast.appendChild(button);
        }

        host.appendChild(toast);

        if (timesOut && !needsDismiss) {
            window.setTimeout(() => dismiss(toast), duration);
        }

        return toast;
    };
})();
