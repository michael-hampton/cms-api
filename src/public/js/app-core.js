const UI = {
    /**
     * Create a DOM element with optional props and children.
     * Children can be strings (text nodes), Elements, or null/undefined (skipped).
     */
    el(tag, props = {}, children = []) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(props)) {
            if (k === 'className') el.className = v;
            else if (k === 'style' && typeof v === 'object') Object.assign(el.style, v);
            else if (k.startsWith('on') && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
            else if (v !== null && v !== undefined) el.setAttribute(k, v);
        }
        for (const child of children) {
            if (child == null) continue;
            el.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
        }
        return el;
    },

    /** Safely set text on an existing element */
    text(el, str) {
        el.textContent = str ?? '';
    },

    /** Build a document fragment from an array of elements */
    fragment(children) {
        const frag = document.createDocumentFragment();
        for (const child of children) {
            if (child == null) continue;
            frag.appendChild(child instanceof Node ? child : document.createTextNode(String(child)));
        }
        return frag;
    },

    /** Replace element's children safely */
    render(root, children) {
        root.innerHTML = '';
        root.appendChild(this.fragment(Array.isArray(children) ? children : [children]));
    },

    /** Show toast — used by every page */
    toast(message, type = 'info', duration = 4000) {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = this.el('div', {id: 'toastContainer', className: 'toast-container'});
            document.body.appendChild(container);
        }
        const icons = {success: '✓', error: '✕', info: 'ℹ'};
        const toast = this.el('div', {className: `toast ${type}`}, [
            this.el('span', {className: 'toast-icon'}, [icons[type] ?? 'ℹ']),
            this.el('span', {}, [message]),
            this.el('button', {className: 'toast-close', onclick: () => toast.remove()}, ['×']),
        ]);
        container.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease forwards';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    },

    setFields(map) {
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementsByName(id)[0] || document.getElementById(id);
            if (!el) return;
            if ('value' in el) el.value = val ?? '';
            else el.textContent = val ?? '';
        });
    },

    setChecks(map) {
        Object.entries(map).forEach(([name, bool]) => {
            const el = document.getElementsByName(name)[0];
            if (el) el.checked = !!bool;
        });
    },

    setHtml(id, html) {
        const el = document.getElementById(id);
        if (el) el.innerHTML = html;
    },
    setTxt(id, txt) {
        const el = document.getElementById(id);
        if (el) el.textContent = txt;
    },

    setBind(key, val, isHtml = false) {
        const el = document.querySelector(`[data-bind="${key}"]`);
        if (!el) return;
        if (isHtml) el.innerHTML = val;
        else el.textContent = val;
    },

    formatDate(str, includeTime = false) {
        if (!str) return 'Never';
        const opt = {year: 'numeric', month: 'long', day: 'numeric'};
        if (includeTime) {
            opt.hour = '2-digit';
            opt.minute = '2-digit';
        }
        return new Date(str).toLocaleDateString('en-GB', opt);
    },

    bindData(source) {
        if (!source) return;
        Object.entries(source).forEach(([key, val]) => {
            // Find by ID, Name, or Data-Attribute
            const elements = document.querySelectorAll(`[name="${key}"], #${key}, [data-bind="${key}"]`);
            elements.forEach(el => {
                if (el.type === 'checkbox') el.checked = !!val;
                else if ('value' in el) el.value = val ?? '';
                else el.textContent = val ?? '';
            });
        });
    },

    rawEl(htmlString, wrapperTag = 'span') {
        const template = document.createElement(wrapperTag);
        template.innerHTML = htmlString.trim();

        // Return the actual node (e.g., the <svg>)
        // instead of the wrapper <span>
        return template.firstChild;
    },

    /** "2 hours ago" style */
    timeAgo(str) {
        if (!str) return '';
        const raw = (str?.date ?? str).replace(' ', 'T');
        const diff = Math.floor((Date.now() - new Date(raw).getTime()) / 1000);
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d ago`;
        return UI.formatDate(str);
    },

    /** Escape for the rare case you must set innerHTML (avoid where possible) */
    esc(str) {
        if (str == null) return '';
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    },

    statusBadge(status) {
        const map = {
            completed: ['#d1fae5', '#065f46'],
            active: ['#d1fae5', '#065f46'],
            pending: ['#fef3c7', '#92400e'],
            expired: ['#fee2e2', '#991b1b'],
            cancelled: ['#fee2e2', '#991b1b'],
        };
        const [bg, fg] = map[String(status).toLowerCase()] ?? ['#f3f4f6', '#4b5563'];
        return UI.el('span', {
            className: `status-badge ${String(status).toLowerCase()}`,
            style: {background: bg, color: fg},
        }, [String(status).charAt(0).toUpperCase() + String(status).slice(1)]);
    },

    emptyState({icon = '📭', title = 'Nothing here', body = '', action = null} = {}) {
        return UI.el('div', {className: 'empty-state'}, [
            UI.el('div', {className: 'empty-state-icon'}, [icon]),
            UI.el('h2', {}, [title]),
            body ? UI.el('p', {style: {color: 'var(--text-secondary)', marginTop: '0.5rem'}}, [body]) : null,
            action,
        ]);
    },
};

/** Tiny fetch wrapper — throws on non-ok or success:false */
async function api(url, opts = {}) {
    const res = await fetch(url, {
        headers: {'Content-Type': 'application/json', ...(opts.headers ?? {})},
        ...opts,
    });
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const json = await res.json();
    if (json.success === false) throw new Error(json.message ?? 'Request failed');
    return json;
}

window.UI = UI