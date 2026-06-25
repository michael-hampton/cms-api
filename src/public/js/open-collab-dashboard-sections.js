(() => {
  const main = { id: 'main', title: 'Dashboard' };
  const hidden = { id: 'hidden', title: 'Hidden', hidden: true };
  const html = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }[c]));
  const api = () => `/api/${window.DASHBOARD_SITE || ''}/open-collab/dashboard/widgets`;
  const headers = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('oc_token') || ''}` });

  let widgets = [];
  let customSections = [];
  let drag = null;
  let patched = false;

  async function load() {
    const res = await fetch(api(), { headers: headers(), credentials: 'same-origin' });
    if (!res.ok) return;

    const json = await res.json();
    widgets = (json.widgets || json.data?.widgets || []).map((widget, index) => ({
      ...widget,
      enabled: widget.enabled !== false,
      position: Number.isFinite(+widget.position) ? +widget.position : index,
      settings: widget.settings && typeof widget.settings === 'object' ? widget.settings : {},
    })).sort((a, b) => a.position - b.position);

    const holder = widgets.find(widget => Array.isArray(widget.settings.__dashboard_sections));
    customSections = (holder?.settings.__dashboard_sections || []).filter(section => section?.id && section?.title);
  }

  function sectionOf(widget) {
    if (!widget.enabled) return hidden;

    const saved = widget.settings.dashboard_section;
    if (!saved?.id || saved.id === hidden.id) return main;

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
      if (section.id !== hidden.id) map.set(section.id, section);
    });

    if (showHidden) map.set(hidden.id, hidden);

    return [...map.values()];
  }

  function items(sectionId) {
    return widgets.filter(widget => sectionOf(widget).id === sectionId).sort((a, b) => a.position - b.position);
  }

  function assign(widget, sectionId) {
    widget.enabled = sectionId !== hidden.id;
    widget.settings = { ...(widget.settings || {}) };

    if (sectionId === main.id) {
      delete widget.settings.dashboard_section;
      return;
    }

    const section = sectionId === hidden.id ? hidden : customSections.find(section => section.id === sectionId);
    widget.settings.dashboard_section = { id: section.id, title: section.title };
  }

  async function saveAll() {
    if (widgets[0]) {
      widgets[0].settings = {
        ...(widgets[0].settings || {}),
        __dashboard_sections: customSections.map(({ id, title }) => ({ id, title })),
      };
    }

    widgets = sections(true).flatMap(section => items(section.id)).map((widget, position) => ({ ...widget, position }));

    await Promise.all(widgets.map(widget => fetch(`${api()}/${encodeURIComponent(widget.key)}/settings`, {
      method: 'PUT',
      headers: headers(),
      credentials: 'same-origin',
      body: JSON.stringify({ enabled: widget.enabled, position: widget.position, settings: widget.settings || {} }),
    })));

    renderDashboard();
  }

  function renderDashboard() {
    const root = document.getElementById('dashboard-widget-grid');
    if (!root || !widgets.length) return;

    const existing = new Map([...root.querySelectorAll('[data-widget-key]')].map(element => [element.dataset.widgetKey, element]));

    root.classList.remove('oc-widget-grid');
    root.classList.add('oc-dashboard-sections');
    root.innerHTML = '';

    sections(false).forEach(section => {
      const visible = items(section.id).filter(widget => widget.enabled);
      if (!visible.length) return;

      const wrapper = document.createElement('section');
      wrapper.className = 'oc-dashboard-section';
      wrapper.innerHTML = `<div class="oc-dashboard-section__header"><h2 class="oc-dashboard-section__title">${html(section.title)}</h2></div><div class="oc-dashboard-section__grid"></div>`;

      const grid = wrapper.querySelector('.oc-dashboard-section__grid');
      visible.forEach(widget => {
        const element = existing.get(widget.key);
        if (element) grid.appendChild(element);
      });

      root.appendChild(wrapper);
    });
  }

  function renderManager() {
    const list = document.getElementById('oc-wm-list');
    if (!list) return;

    list.innerHTML = `<li class="oc-wm-toolbar"><span>Sections organise visible widgets. Hidden is the disabled shelf.</span><button class="oc-wm-mini-btn" data-act="add">+ Section</button></li>${sections(true).map(renderSection).join('')}`;
    list.onclick = onClick;
    list.onpointerdown = startDrag;
  }

  function renderSection(section) {
    const controls = section.id === main.id || section.id === hidden.id
      ? ''
      : `<button class="oc-wm-mini-btn" data-act="rename" data-section="${html(section.id)}">Rename</button><button class="oc-wm-mini-btn" data-act="remove" data-section="${html(section.id)}">Remove</button>`;

    const rows = items(section.id).map(widget => `<div class="oc-wm-row ${widget.enabled ? '' : 'oc-wm-row--hidden'}" data-key="${html(widget.key)}"><span class="oc-wm-row__handle">⋮⋮</span><span class="oc-wm-row__title">${html(widget.title)}</span>${widget.enabled ? '' : '<span class="oc-wm-row__meta">Hidden</span>'}<button class="oc-wm-mini-btn" data-act="toggle" data-key="${html(widget.key)}">${widget.enabled ? 'Hide' : 'Show'}</button></div>`).join('') || '<div class="oc-wm-section__empty">Drop widgets here.</div>';

    return `<li class="oc-wm-section ${section.hidden ? 'oc-wm-section--hidden' : ''}" data-section="${html(section.id)}"><div class="oc-wm-section__header"><span class="oc-wm-section__title">${html(section.title)}</span><span>${controls}</span></div><div class="oc-wm-section__body" data-drop-section="${html(section.id)}">${rows}</div></li>`;
  }

  async function onClick(event) {
    const button = event.target.closest('[data-act]');
    if (!button) return;

    const act = button.dataset.act;

    if (act === 'add') {
      const title = prompt('Section heading');
      if (title?.trim()) customSections.push({ id: `section_${Date.now()}`, title: title.trim() });
    }

    if (act === 'rename') {
      const section = customSections.find(section => section.id === button.dataset.section);
      const title = section ? prompt('Rename section', section.title) : null;
      if (title?.trim()) section.title = title.trim();
    }

    if (act === 'remove') {
      const sectionId = button.dataset.section;
      const section = customSections.find(section => section.id === sectionId);
      if (!section || !confirm(`Remove "${section.title}"? Widgets in it will move back to Dashboard.`)) return;

      customSections = customSections.filter(section => section.id !== sectionId);
      widgets.forEach(widget => {
        if (sectionOf(widget).id === sectionId) assign(widget, main.id);
      });
    }

    if (act === 'toggle') {
      const widget = widgets.find(widget => widget.key === button.dataset.key);
      if (widget) assign(widget, widget.enabled ? hidden.id : main.id);
    }

    await saveAll();
    renderManager();
  }

  function startDrag(event) {
    const row = event.target.closest('.oc-wm-row');
    if (!row || event.target.closest('button')) return;

    drag = {
      key: row.dataset.key,
      bodies: [...document.querySelectorAll('[data-drop-section]')].map(body => ({
        id: body.dataset.dropSection,
        rect: body.getBoundingClientRect(),
        rows: [...body.querySelectorAll('.oc-wm-row')].map(row => {
          const rect = row.getBoundingClientRect();
          return { key: row.dataset.key, mid: rect.top + rect.height / 2 };
        }),
      })),
    };

    row.classList.add('oc-wm-row--dragging');
    document.addEventListener('pointerup', drop, { once: true });
  }

  async function drop(event) {
    if (!drag) return;

    const body = drag.bodies.find(body => event.clientY >= body.rect.top && event.clientY <= body.rect.bottom) || drag.bodies[0];
    const rows = body.rows.filter(row => row.key !== drag.key);
    const before = rows.find(row => event.clientY < row.mid);
    const index = before ? rows.indexOf(before) : rows.length;
    const item = widgets.find(widget => widget.key === drag.key);

    drag = null;
    if (!item) return;

    assign(item, body.id);

    const other = widgets.filter(widget => widget.key !== item.key);
    widgets = sections(true).flatMap(section => {
      const list = other.filter(widget => sectionOf(widget).id === section.id).sort((a, b) => a.position - b.position);
      if (section.id === body.id) list.splice(index, 0, item);
      return list;
    });

    await saveAll();
    renderManager();
  }

  function patchManager() {
    if (patched || !window.DashboardWidgetManager) return;

    patched = true;
    const original = window.DashboardWidgetManager.openManager;

    window.DashboardWidgetManager.openManager = async function () {
      await original.call(window.DashboardWidgetManager);
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
