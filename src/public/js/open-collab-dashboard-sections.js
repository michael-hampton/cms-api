(() => {
    const main = { id: 'main', title: 'Dashboard' };
    const hidden = { id: 'hidden', title: 'Hidden', hidden: true };

    const html = value => String(value ?? '').replace(/[&<>"']/g, char => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    }[char]));

    const api = () => `/api/${window.DASHBOARD_SITE || ''}/open-collab/dashboard/widgets`;

    const headers = () => ({
        'Content-Type': 'application/json',
        Accept: 'application/json',
        Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}`,
    });

    let widgets = [];
    let customSections = [];
    let drag = null;
    let patched = false;

    async function load() {
        const response = await fetch(api(), {
            headers: headers(),
            credentials: 'same-origin',
        });

        if (!response.ok) {
            return;
        }

        const json = await response.json();
        const manifest = json.widgets || json.data?.widgets || [];

        widgets = manifest.map((widget, index) => ({
            ...widget,
            enabled: widget.enabled !== false,
            position: Number.isFinite(+widget.position) ? +widget.position : index,
            settings: widget.settings && typeof widget.settings === 'object' ? widget.settings : {},
        })).sort((a, b) => a.position - b.position);

        const holder = widgets.find(widget => Array.isArray(widget.settings.__dashboard_sections));

        customSections = (holder?.settings.__dashboard_sections || [])
            .filter(section => section?.id && section?.title)
            .map(section => ({ id: String(section.id), title: String(section.title) }));
    }

    function sectionOf(widget) {
        if (!widget.enabled) {
            return hidden;
        }

        const saved = widget.settings.dashboard_section;

        if (!saved?.id || saved.id === hidden.id) {
            return main;
        }

        return customSections.find(section => section.id === saved.id) || {
            id: saved.id,
            title: saved.title || 'Section',
        };
    }

    function sections(showHidden = true) {
        const map = new Map([[main.id, main]]);

        customSections.forEach(section => map.set(section.id, section));

        widgets.forEach(widget => {
            const section = sectionOf(widget);

            if (section.id !== hidden.id) {
                map.set(section.id, section);
            }
        });

        if (showHidden) {
            map.set(hidden.id, hidden);
        }

        return [...map.values()];
    }

    function items(sectionId) {
        return widgets
            .filter(widget => sectionOf(widget).id === sectionId)
            .sort((a, b) => a.position - b.position);
    }

    function assign(widget, sectionId) {
        widget.enabled = sectionId !== hidden.id;
        widget.settings = { ...(widget.settings || {}) };

        if (sectionId === main.id) {
            delete widget.settings.dashboard_section;
            return;
        }

        const section = sectionId === hidden.id
            ? hidden
            : customSections.find(section => section.id === sectionId);

        widget.settings.dashboard_section = {
            id: section.id,
            title: section.title,
        };
    }

    async function persistCurrent() {
        const responses = await Promise.all(widgets.map(widget => fetch(`${api()}/${encodeURIComponent(widget.key)}/settings`, {
            method: 'PUT',
            headers: headers(),
            credentials: 'same-origin',
            body: JSON.stringify({
                enabled: widget.enabled,
                position: widget.position,
                settings: widget.settings || {},
            }),
        })));

        const failed = responses.find(response => !response.ok);

        if (failed) {
            throw new Error(`Could not save dashboard layout: HTTP ${failed.status}`);
        }
    }

    async function saveAll() {
        if (widgets[0]) {
            widgets[0].settings = {
                ...(widgets[0].settings || {}),
                __dashboard_sections: customSections.map(({ id, title }) => ({ id, title })),
            };
        }

        widgets = sections(true)
            .flatMap(section => items(section.id))
            .map((widget, position) => ({ ...widget, position }));

        await persistCurrent();
        await load();
        renderDashboard();
    }

    function ensureWidgetElement(widget, createdKeys) {
        let element = document.querySelector(`[data-widget-key="${cssEscape(widget.key)}"]`);

        if (element) {
            return element;
        }

        element = document.createElement('div');
        element.id = `widget-${widget.key}`;
        element.className = 'oc-widget';
        element.dataset.widgetKey = widget.key;
        element.setAttribute('aria-label', widget.title || widget.key);
        element.innerHTML = `
            <div class="oc-widget__skeleton">
                <div class="oc-card">
                    <div class="oc-card__header">
                        <span class="oc-card__title">${html(widget.title || widget.key)}</span>
                    </div>
                    <div class="oc-card__body oc-widget__loading">
                        <div class="oc-skeleton-line"></div>
                        <div class="oc-skeleton-line oc-skeleton-line--short"></div>
                    </div>
                </div>
            </div>
        `;

        createdKeys.push(widget.key);

        return element;
    }

    function renderDashboard() {
        const root = document.getElementById('dashboard-widget-grid');

        if (!root || !widgets.length) {
            return;
        }

        const createdKeys = [];
        const visibleWidgets = widgets.filter(widget => widget.enabled);
        const elements = new Map();

        visibleWidgets.forEach(widget => {
            elements.set(widget.key, ensureWidgetElement(widget, createdKeys));
        });

        root.classList.remove('oc-widget-grid');
        root.classList.add('oc-dashboard-sections');
        root.innerHTML = '';

        sections(false).forEach(section => {
            const visible = items(section.id).filter(widget => widget.enabled);

            if (!visible.length) {
                return;
            }

            const wrapper = document.createElement('section');
            wrapper.className = 'oc-dashboard-section';
            wrapper.dataset.sectionId = section.id;
            wrapper.innerHTML = `
                <div class="oc-dashboard-section__header">
                    <h2 class="oc-dashboard-section__title">${html(section.title)}</h2>
                </div>
                <div class="oc-dashboard-section__grid"></div>
            `;

            const grid = wrapper.querySelector('.oc-dashboard-section__grid');

            visible.forEach(widget => {
                const element = elements.get(widget.key);

                if (element) {
                    grid.appendChild(element);
                }
            });

            root.appendChild(wrapper);
        });

        if (createdKeys.length && window.DashboardWidgetManager) {
            window.DashboardWidgetManager.init?.();
            createdKeys.forEach(key => window.DashboardWidgetManager.refresh?.(key));
        }
    }

    function getCleanManagerList() {
        const oldList = document.getElementById('oc-wm-list');

        if (!oldList) {
            return null;
        }

        const newList = oldList.cloneNode(false);

        newList.id = oldList.id;
        newList.className = oldList.className;
        newList.setAttribute('aria-label', oldList.getAttribute('aria-label') || 'Widget list');
        newList.style.display = 'block';
        newList.style.flex = '1';
        newList.style.overflowY = 'auto';
        newList.style.padding = '12px 16px';
        newList.style.margin = '0';
        newList.style.listStyle = 'none';

        oldList.replaceWith(newList);

        return newList;
    }

    function renderManager() {
        const list = getCleanManagerList();

        if (!list) {
            return;
        }

        list.innerHTML = `
            <li class="oc-wm-toolbar">
                <span>Sections organise visible widgets. Hidden is the disabled shelf.</span>
                <button type="button" class="oc-wm-mini-btn" data-act="add">+ Section</button>
            </li>
            ${sections(true).map(renderSection).join('')}
        `;

        list.addEventListener('click', onClick);
        list.addEventListener('pointerdown', startDrag);
    }

    function renderSection(section) {
        const sectionItems = items(section.id);
        const countLabel = `${sectionItems.length} ${sectionItems.length === 1 ? 'widget' : 'widgets'}`;
        const isSystemSection = section.id === main.id || section.id === hidden.id;

        const controls = isSystemSection
            ? ''
            : `
                <span class="oc-wm-section__actions">
                    <button type="button" class="oc-wm-mini-btn" data-act="rename" data-section="${html(section.id)}">Rename</button>
                    <button type="button" class="oc-wm-mini-btn oc-wm-mini-btn--danger" data-act="remove" data-section="${html(section.id)}">Remove</button>
                </span>
            `;

        const rows = sectionItems.map(renderRow).join('')
            || '<div class="oc-wm-section__empty">Drop widgets here.</div>';

        return `
            <li class="oc-wm-section ${section.hidden ? 'oc-wm-section--hidden' : ''}" data-section="${html(section.id)}">
                <div class="oc-wm-section__header">
                    <div>
                        <span class="oc-wm-section__title">${html(section.title)}</span>
                        <div class="oc-wm-section__hint">
                            ${section.hidden ? 'Widgets here are disabled and hidden outside edit mode.' : 'Drop widgets inside this bordered section.'}
                        </div>
                    </div>
                    <div class="oc-wm-section__header-actions">
                        <span class="oc-wm-section__count">${countLabel}</span>
                        ${controls}
                    </div>
                </div>
                <div class="oc-wm-section__body" data-drop-section="${html(section.id)}">
                    ${rows}
                </div>
            </li>
        `;
    }

    function renderRow(widget) {
        const isHidden = !widget.enabled;

        return `
            <div class="oc-wm-row ${isHidden ? 'oc-wm-row--hidden' : ''}" data-key="${html(widget.key)}">
                <span class="oc-wm-row__handle" aria-hidden="true">⋮⋮</span>
                <span class="oc-wm-row__title">${html(widget.title)}</span>
                ${isHidden ? '<span class="oc-wm-row__meta">Hidden</span>' : ''}
                <button type="button" class="oc-wm-mini-btn" data-act="toggle" data-key="${html(widget.key)}">
                    ${isHidden ? 'Show' : 'Hide'}
                </button>
            </div>
        `;
    }

    function renderRowElement(widget) {
        const template = document.createElement('template');
        template.innerHTML = renderRow(widget).trim();

        return template.content.firstElementChild;
    }

    async function onClick(event) {
        const button = event.target.closest('[data-act]');

        if (!button) {
            return;
        }

        const action = button.dataset.act;

        try {
            if (action === 'add') {
                const title = prompt('Section heading');

                if (title?.trim()) {
                    customSections.push({
                        id: `section_${Date.now()}`,
                        title: title.trim(),
                    });
                }
            }

            if (action === 'rename') {
                const section = customSections.find(section => section.id === button.dataset.section);
                const title = section ? prompt('Rename section', section.title) : null;

                if (title?.trim()) {
                    section.title = title.trim();

                    widgets.forEach(widget => {
                        if (widget.settings.dashboard_section?.id === section.id) {
                            widget.settings.dashboard_section.title = section.title;
                        }
                    });
                }
            }

            if (action === 'remove') {
                const sectionId = button.dataset.section;
                const section = customSections.find(section => section.id === sectionId);

                if (!section) {
                    return;
                }

                if (!confirm(`Remove "${section.title}"? Widgets in this section will be moved back to Dashboard.`)) {
                    return;
                }

                widgets.forEach(widget => {
                    if (sectionOf(widget).id === sectionId) {
                        assign(widget, main.id);
                    }
                });

                customSections = customSections.filter(section => section.id !== sectionId);
            }

            if (action === 'toggle') {
                const widget = widgets.find(widget => widget.key === button.dataset.key);

                if (widget) {
                    assign(widget, widget.enabled ? hidden.id : main.id);
                }
            }

            await saveAll();
            renderManager();
        } catch (error) {
            console.error('[DashboardSections]', error);
            alert(error.message || 'Could not save dashboard layout.');
        }
    }

    function startDrag(event) {
        const row = event.target.closest('.oc-wm-row');

        if (!row || event.target.closest('button')) {
            return;
        }

        event.preventDefault();

        const rect = row.getBoundingClientRect();
        const placeholder = document.createElement('div');
        placeholder.className = 'oc-wm-row oc-wm-row--placeholder';
        placeholder.style.height = `${rect.height}px`;

        const ghost = row.cloneNode(true);
        ghost.classList.add('oc-wm-row--dragging');
        ghost.style.position = 'fixed';
        ghost.style.left = `${rect.left}px`;
        ghost.style.top = `${rect.top}px`;
        ghost.style.width = `${rect.width}px`;
        ghost.style.zIndex = '2000';
        ghost.style.pointerEvents = 'none';
        ghost.style.boxShadow = '0 12px 30px rgba(0,0,0,.18)';
        ghost.style.transform = 'scale(1.02)';

        row.replaceWith(placeholder);
        document.body.appendChild(ghost);

        drag = {
            key: row.dataset.key,
            placeholder,
            ghost,
            offsetX: event.clientX - rect.left,
            offsetY: event.clientY - rect.top,
        };

        document.addEventListener('pointermove', moveDrag);
        document.addEventListener('pointerup', finishDrag, { once: true });
        document.addEventListener('pointercancel', cancelDrag, { once: true });
    }

    function moveDrag(event) {
        if (!drag) {
            return;
        }

        drag.ghost.style.left = `${event.clientX - drag.offsetX}px`;
        drag.ghost.style.top = `${event.clientY - drag.offsetY}px`;

        const targetBody = getDropBodyAt(event.clientX, event.clientY);

        if (!targetBody) {
            return;
        }

        document
            .querySelectorAll('.oc-wm-drop-active')
            .forEach(element => element.classList.remove('oc-wm-drop-active'));

        targetBody.classList.add('oc-wm-drop-active');

        const rows = directRows(targetBody).filter(row => row !== drag.placeholder);
        const before = rows.find(row => {
            const rect = row.getBoundingClientRect();
            return event.clientY < rect.top + rect.height / 2;
        });

        if (before) {
            targetBody.insertBefore(drag.placeholder, before);
        } else {
            targetBody.appendChild(drag.placeholder);
        }
    }

    async function finishDrag() {
        if (!drag) {
            return;
        }

        const targetBody = drag.placeholder.closest('[data-drop-section]');
        const targetSectionId = targetBody?.dataset.dropSection;
        const newIndex = targetBody ? directRows(targetBody).indexOf(drag.placeholder) : -1;
        const item = widgets.find(widget => widget.key === drag.key);

        if (!targetSectionId || !item || newIndex < 0) {
            cleanupDragDom();
            drag = null;
            renderManager();
            return;
        }

        assign(item, targetSectionId);

        const otherWidgets = widgets.filter(widget => widget.key !== item.key);

        widgets = sections(true).flatMap(section => {
            const sectionItems = otherWidgets
                .filter(widget => sectionOf(widget).id === section.id)
                .sort((a, b) => a.position - b.position);

            if (section.id === targetSectionId) {
                sectionItems.splice(newIndex, 0, item);
            }

            return sectionItems;
        }).map((widget, position) => ({ ...widget, position }));

        drag.placeholder.replaceWith(renderRowElement(item));
        cleanupDragDom();
        updateManagerCounts();
        drag = null;

        try {
            await persistCurrent();
            renderDashboard();
        } catch (error) {
            console.error('[DashboardSections]', error);
            alert(error.message || 'Could not save dashboard layout.');
            await load();
            renderDashboard();
            renderManager();
        }
    }

    function cancelDrag() {
        if (!drag) {
            return;
        }

        cleanupDragDom();
        drag = null;
        renderManager();
    }

    function cleanupDragDom() {
        document.removeEventListener('pointermove', moveDrag);

        document
            .querySelectorAll('.oc-wm-drop-active')
            .forEach(element => element.classList.remove('oc-wm-drop-active'));

        if (drag?.ghost) {
            drag.ghost.remove();
        }

        if (drag?.placeholder?.parentNode) {
            drag.placeholder.remove();
        }
    }

    function directRows(body) {
        return [...body.children].filter(child => child.classList.contains('oc-wm-row'));
    }

    function updateManagerCounts() {
        document.querySelectorAll('.oc-wm-section[data-section]').forEach(sectionElement => {
            const body = sectionElement.querySelector('[data-drop-section]');
            const count = body ? directRows(body).filter(row => !row.classList.contains('oc-wm-row--placeholder')).length : 0;
            const countElement = sectionElement.querySelector('.oc-wm-section__count');

            if (countElement) {
                countElement.textContent = `${count} ${count === 1 ? 'widget' : 'widgets'}`;
            }
        });
    }

    function getDropBodyAt(x, y) {
        const bodies = [...document.querySelectorAll('[data-drop-section]')];

        const bodyHit = bodies.find(body => {
            const rect = body.getBoundingClientRect();

            return x >= rect.left
                && x <= rect.right
                && y >= rect.top
                && y <= rect.bottom;
        });

        if (bodyHit) {
            return bodyHit;
        }

        const sectionHit = [...document.querySelectorAll('.oc-wm-section[data-section]')].find(section => {
            const rect = section.getBoundingClientRect();

            return x >= rect.left
                && x <= rect.right
                && y >= rect.top
                && y <= rect.bottom;
        });

        return sectionHit?.querySelector('[data-drop-section]') || null;
    }

    function cssEscape(value) {
        if (window.CSS?.escape) {
            return window.CSS.escape(value);
        }

        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    function patchManager() {
        if (patched || !window.DashboardWidgetManager) {
            return;
        }

        patched = true;
        const originalOpenManager = window.DashboardWidgetManager.openManager;

        window.DashboardWidgetManager.openManager = async function () {
            await originalOpenManager.call(window.DashboardWidgetManager);
            await load();
            renderManager();
        };
    }

    document.addEventListener('DOMContentLoaded', async () => {
        patchManager();
        await load();
        renderDashboard();
    });
})();
