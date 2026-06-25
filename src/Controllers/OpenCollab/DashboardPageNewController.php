<?php

namespace App\Controllers\OpenCollab;

use App\Controllers\Controller;
use App\Framework\Authorization\Auth;
use App\Framework\Http\JsonResponse;
use App\Framework\Support\SiteContext;
use App\Models\User;
use App\Services\OpenCollab\Dashboard\WidgetRegistry;
use App\Services\OpenCollab\Dashboard\WidgetResolver;
use App\Services\OpenCollab\Dashboard\WidgetResponse;
use App\Services\OpenCollab\SitePermissionResolver;

class DashboardPageNewController extends Controller
{
    public function __construct(
        private readonly WidgetResolver         $widgetResolver,
        private readonly WidgetRegistry         $widgetRegistry,
        private readonly SitePermissionResolver $permissionResolver,
    )
    {
        parent::__construct();
    }

    public function index(): JsonResponse
    {
        $user = Auth::getUser();

        return $this->jsonResponse([
            'widgets' => $this->widgetResolver->availableForUser(User::hydrateStatic($user)),
        ]);
    }

    /**
     * GET /contributor/dashboard
     */
    public function show()
    {
        $user = User::hydrateStatic(Auth::getUser());
        $widgets = $this->widgetResolver->resolveForUser($user);

        // Pass only keys and titles to the view.
        // Actual data is fetched per-widget via the JS widget manager.
        $widgetManifest = array_map(
            fn($w) => ['key' => $w->key(), 'title' => $w->title()],
            $widgets
        );

        return $this->view('open-collab.dashboard-new.show', [
            'widgets' => $widgetManifest,
            'currentUser' => $user,
            'site' => SiteContext::slug(),
            'extraHead' => $this->dashboardSectionsEnhancer(),
        ]);
    }

    public function getWidget(string $slug)
    {
        $user = User::hydrateStatic(Auth::getUser());
        $widget = $this->widgetRegistry->get($slug);
        $siteId = (int)SiteContext::getId();

        foreach ($this->widgetRegistry->permissionsFor($slug) as $permission) {
            if (!$this->permissionResolver->allows($user->id, $siteId, $permission)) {
                return $this->errorResponse('Forbidden.', 403);
            }
        }

        $response = WidgetResponse::make(
            $widget->key(),
            $widget->title(),
            $widget->data($user),
        );

        return $this->resourceResponse($response->toArray());
    }

    private function dashboardSectionsEnhancer(): string
    {
        return <<<'HTML'
<style>
    .oc-dashboard-sections { display:flex; flex-direction:column; gap:24px; }
    .oc-dashboard-section__header { display:flex; align-items:center; justify-content:space-between; margin:0 0 12px; }
    .oc-dashboard-section__title { font-family:var(--font-display); font-size:1rem; font-weight:800; color:var(--navy); margin:0; }
    .oc-dashboard-section__grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(280px,1fr)); gap:20px; }
    .oc-wm-toolbar { display:flex; justify-content:space-between; align-items:center; gap:8px; padding:10px 4px 14px; }
    .oc-wm-section { border:1px solid var(--border); border-radius:12px; background:#fff; margin-bottom:12px; overflow:hidden; }
    .oc-wm-section--hidden { background:var(--slate-pale); border-style:dashed; }
    .oc-wm-section__header { display:flex; align-items:center; justify-content:space-between; gap:8px; padding:10px 12px; border-bottom:1px solid var(--border); }
    .oc-wm-section__title { font-size:.78rem; font-weight:800; letter-spacing:.07em; text-transform:uppercase; color:var(--navy); }
    .oc-wm-section__actions { display:flex; gap:6px; }
    .oc-wm-section__body { min-height:44px; padding:6px; }
    .oc-wm-section__empty { padding:12px; font-size:.78rem; color:var(--slate); border:1px dashed var(--border); border-radius:8px; background:#fff; }
    .oc-wm-row { display:flex; align-items:center; gap:10px; padding:10px 8px; border-radius:8px; border:1px solid var(--border); background:#fff; margin-bottom:6px; cursor:grab; user-select:none; touch-action:none; transition:box-shadow .15s, opacity .15s, transform .15s; }
    .oc-wm-row:focus-within { outline:2px solid var(--amber); outline-offset:2px; }
    .oc-wm-row--hidden { opacity:.62; border-style:dashed; filter:grayscale(.35); }
    .oc-wm-row--hidden .oc-wm-row__title { text-decoration:line-through; }
    .oc-wm-row--dragging { opacity:.35; }
    .oc-wm-row__handle { color:var(--slate-light); flex-shrink:0; line-height:0; }
    .oc-wm-row__title { flex:1; font-size:.875rem; font-weight:600; color:var(--navy); }
    .oc-wm-row__meta { font-size:.68rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; border:1px solid currentColor; border-radius:999px; padding:2px 7px; color:var(--slate); }
    .oc-wm-drop-active { box-shadow:inset 0 0 0 2px var(--amber); }
    .oc-wm-mini-btn { border:1px solid var(--border); background:#fff; color:var(--navy); border-radius:7px; padding:5px 8px; font-size:.72rem; font-weight:700; cursor:pointer; }
    .oc-wm-mini-btn:hover { border-color:var(--amber); color:var(--amber); }
</style>
<script>
(() => {
    const MAIN_SECTION = { id: 'main', title: 'Dashboard', system: true };
    const HIDDEN_SECTION = { id: 'hidden', title: 'Hidden', system: true, hidden: true };
    const token = () => localStorage.getItem('oc_token') || '';
    const esc = (value) => String(value ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    const idFor = () => `section_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 7)}`;

    let manifest = [];
    let sectionCatalog = [];
    let patched = false;
    let dragState = null;

    function baseUrl() {
        const site = window.DASHBOARD_SITE || '';
        return `/api/${site}/open-collab/dashboard/widgets`;
    }

    async function fetchManifest() {
        const res = await fetch(baseUrl(), {
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token()}`,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const payload = await res.json();
        manifest = Array.isArray(payload.widgets)
            ? payload.widgets
            : (Array.isArray(payload.data?.widgets) ? payload.data.widgets : []);
        manifest = manifest.map((item, index) => ({
            ...item,
            enabled: item.enabled !== false,
            position: Number.isFinite(Number(item.position)) ? Number(item.position) : index,
            settings: item.settings && typeof item.settings === 'object' ? item.settings : {},
        })).sort((a, b) => a.position - b.position);
        sectionCatalog = readCatalog();
        return manifest;
    }

    function readCatalog() {
        const firstWithCatalog = manifest.find(item => Array.isArray(item.settings?.__dashboard_sections));
        const catalog = firstWithCatalog?.settings?.__dashboard_sections || [];
        return catalog
            .filter(section => section && section.id && section.title && section.id !== MAIN_SECTION.id && section.id !== HIDDEN_SECTION.id)
            .map(section => ({ id: String(section.id), title: String(section.title), system: false }));
    }

    function writeCatalog() {
        if (!manifest.length) return;
        const first = manifest[0];
        first.settings = { ...(first.settings || {}), __dashboard_sections: sectionCatalog.map(({ id, title }) => ({ id, title })) };
    }

    function sectionFor(item) {
        if (!item.enabled) return HIDDEN_SECTION;
        const saved = item.settings?.dashboard_section;
        if (!saved || !saved.id || saved.id === HIDDEN_SECTION.id) return MAIN_SECTION;
        const catalogSection = sectionCatalog.find(section => section.id === saved.id);
        return catalogSection || { id: String(saved.id), title: String(saved.title || 'Section'), system: false };
    }

    function sections(includeHidden = true) {
        const unique = new Map();
        unique.set(MAIN_SECTION.id, MAIN_SECTION);
        sectionCatalog.forEach(section => unique.set(section.id, section));
        manifest.forEach(item => {
            const section = sectionFor(item);
            if (section.id !== HIDDEN_SECTION.id) unique.set(section.id, section);
        });
        if (includeHidden) unique.set(HIDDEN_SECTION.id, HIDDEN_SECTION);
        return [...unique.values()];
    }

    function itemsFor(sectionId) {
        return manifest.filter(item => sectionFor(item).id === sectionId).sort((a, b) => a.position - b.position);
    }

    function setItemSection(item, sectionId) {
        if (sectionId === HIDDEN_SECTION.id) {
            item.enabled = false;
            item.settings = { ...(item.settings || {}), dashboard_section: { id: HIDDEN_SECTION.id, title: HIDDEN_SECTION.title } };
            return;
        }

        item.enabled = true;
        if (sectionId === MAIN_SECTION.id) {
            const settings = { ...(item.settings || {}) };
            delete settings.dashboard_section;
            item.settings = settings;
            return;
        }

        const section = sectionCatalog.find(section => section.id === sectionId) || MAIN_SECTION;
        item.settings = { ...(item.settings || {}), dashboard_section: { id: section.id, title: section.title } };
    }

    function flattenBySections() {
        const ordered = [];
        sections(true).forEach(section => ordered.push(...itemsFor(section.id)));
        ordered.forEach((item, index) => item.position = index);
        manifest = ordered;
    }

    async function saveItem(item) {
        const res = await fetch(`${baseUrl()}/${encodeURIComponent(item.key)}/settings`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token()}`,
                Accept: 'application/json',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                enabled: item.enabled,
                position: item.position,
                settings: item.settings || {},
            }),
        });
        if (!res.ok) throw new Error(`Could not save ${item.key}`);
    }

    async function saveAll() {
        writeCatalog();
        flattenBySections();
        await Promise.all(manifest.map(saveItem));
        syncLiveDashboard();
    }

    function syncLiveDashboard() {
        manifest.forEach(item => {
            if (!item.enabled && window.DashboardWidgetManager?._dashboardRemoveWidgetSlot) {
                window.DashboardWidgetManager._dashboardRemoveWidgetSlot(item.key);
            }
        });
        renderDashboardSections();
    }

    function renderDashboardSections() {
        const root = document.getElementById('dashboard-widget-grid');
        if (!root || !manifest.length) return;

        const enabled = manifest.filter(item => item.enabled);
        const existingWidgets = new Map([...root.querySelectorAll('[data-widget-key]')].map(el => [el.dataset.widgetKey, el]));
        if (!enabled.length) return;

        root.classList.remove('oc-widget-grid');
        root.classList.add('oc-dashboard-sections');
        root.innerHTML = '';

        sections(false).forEach(section => {
            const items = itemsFor(section.id).filter(item => item.enabled);
            if (!items.length) return;

            const wrapper = document.createElement('section');
            wrapper.className = 'oc-dashboard-section';
            wrapper.dataset.sectionId = section.id;
            wrapper.innerHTML = `
                <div class="oc-dashboard-section__header">
                    <h2 class="oc-dashboard-section__title">${esc(section.title)}</h2>
                </div>
                <div class="oc-dashboard-section__grid"></div>`;

            const grid = wrapper.querySelector('.oc-dashboard-section__grid');
            items.forEach(item => {
                const el = existingWidgets.get(item.key);
                if (el) grid.appendChild(el);
            });

            root.appendChild(wrapper);
        });
    }

    function renderManager() {
        const list = document.getElementById('oc-wm-list');
        if (!list) return;
        list.style.display = 'block';
        list.innerHTML = `
            <li class="oc-wm-toolbar">
                <span style="font-size:.78rem;color:var(--slate);font-weight:600;">Sections organise visible widgets. Hidden is the disabled shelf.</span>
                <button type="button" class="oc-wm-mini-btn" data-action="add-section">+ Section</button>
            </li>
            ${sections(true).map(renderManagerSection).join('')}`;
        bindManagerEvents(list);
    }

    function renderManagerSection(section) {
        const items = itemsFor(section.id);
        const actions = section.system ? '' : `
            <span class="oc-wm-section__actions">
                <button type="button" class="oc-wm-mini-btn" data-action="rename-section" data-section-id="${esc(section.id)}">Rename</button>
                <button type="button" class="oc-wm-mini-btn" data-action="remove-section" data-section-id="${esc(section.id)}">Remove</button>
            </span>`;

        return `
            <li class="oc-wm-section ${section.hidden ? 'oc-wm-section--hidden' : ''}" data-section-id="${esc(section.id)}">
                <div class="oc-wm-section__header">
                    <span class="oc-wm-section__title">${esc(section.title)}</span>
                    ${actions}
                </div>
                <div class="oc-wm-section__body" data-drop-section="${esc(section.id)}">
                    ${items.length ? items.map(renderManagerRow).join('') : '<div class="oc-wm-section__empty">Drop widgets here.</div>'}
                </div>
            </li>`;
    }

    function renderManagerRow(item) {
        const hidden = !item.enabled;
        return `
            <div class="oc-wm-row ${hidden ? 'oc-wm-row--hidden' : ''}" data-key="${esc(item.key)}" tabindex="0" aria-label="${esc(item.title)}">
                <span class="oc-wm-row__handle" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor" width="16"><path d="M7 2a2 2 0 110 4 2 2 0 010-4zM7 8a2 2 0 110 4 2 2 0 010-4zM7 14a2 2 0 110 4 2 2 0 010-4zM13 2a2 2 0 110 4 2 2 0 010-4zM13 8a2 2 0 110 4 2 2 0 010-4zM13 14a2 2 0 110 4 2 2 0 010-4z"/></svg>
                </span>
                <span class="oc-wm-row__title">${esc(item.title)}</span>
                ${hidden ? '<span class="oc-wm-row__meta">Hidden</span>' : ''}
                <button type="button" class="oc-wm-mini-btn" data-action="toggle-widget" data-key="${esc(item.key)}">${hidden ? 'Show' : 'Hide'}</button>
            </div>`;
    }

    function bindManagerEvents(list) {
        list.onclick = async (event) => {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (!action) return;

            try {
                if (action === 'add-section') await addSection();
                if (action === 'rename-section') await renameSection(event.target.closest('[data-section-id]').dataset.sectionId);
                if (action === 'remove-section') await removeSection(event.target.closest('[data-section-id]').dataset.sectionId);
                if (action === 'toggle-widget') await toggleWidget(event.target.closest('[data-key]').dataset.key);
            } catch (error) {
                console.error(error);
                alert('Could not save the dashboard change. Please try again.');
            }
        };

        list.onpointerdown = startPointerDrag;
    }

    async function addSection() {
        const title = prompt('Section heading');
        if (!title || !title.trim()) return;
        sectionCatalog.push({ id: idFor(), title: title.trim(), system: false });
        await saveAll();
        renderManager();
    }

    async function renameSection(sectionId) {
        const section = sectionCatalog.find(section => section.id === sectionId);
        if (!section) return;
        const title = prompt('Rename section', section.title);
        if (!title || !title.trim()) return;
        section.title = title.trim();
        manifest.forEach(item => {
            if (item.settings?.dashboard_section?.id === sectionId) {
                item.settings.dashboard_section.title = section.title;
            }
        });
        await saveAll();
        renderManager();
    }

    async function removeSection(sectionId) {
        const section = sectionCatalog.find(section => section.id === sectionId);
        if (!section) return;
        if (!confirm(`Remove "${section.title}"? Widgets in it will move back to Dashboard.`)) return;
        sectionCatalog = sectionCatalog.filter(section => section.id !== sectionId);
        manifest.forEach(item => {
            if (sectionFor(item).id === sectionId) setItemSection(item, MAIN_SECTION.id);
        });
        await saveAll();
        renderManager();
    }

    async function toggleWidget(key) {
        const item = manifest.find(item => item.key === key);
        if (!item) return;
        setItemSection(item, item.enabled ? HIDDEN_SECTION.id : MAIN_SECTION.id);
        await saveAll();
        renderManager();
    }

    function startPointerDrag(event) {
        const row = event.target.closest('.oc-wm-row[data-key]');
        if (!row || event.target.closest('button')) return;

        const list = document.getElementById('oc-wm-list');
        const sectionsFrozen = [...list.querySelectorAll('[data-drop-section]')].map(body => ({
            id: body.dataset.dropSection,
            rect: body.getBoundingClientRect(),
            rows: [...body.querySelectorAll('.oc-wm-row[data-key]')].map(row => {
                const rect = row.getBoundingClientRect();
                return { key: row.dataset.key, top: rect.top, mid: rect.top + (rect.height / 2), bottom: rect.bottom };
            }),
        }));

        dragState = { key: row.dataset.key, sectionsFrozen };
        row.classList.add('oc-wm-row--dragging');
        row.setPointerCapture?.(event.pointerId);
        document.addEventListener('pointermove', onPointerMove);
        document.addEventListener('pointerup', onPointerUp, { once: true });
    }

    function targetFromPointer(y) {
        const fallback = dragState.sectionsFrozen[0];
        const section = dragState.sectionsFrozen.find(section => y >= section.rect.top && y <= section.rect.bottom) || fallback;
        const rows = section.rows.filter(row => row.key !== dragState.key);
        const before = rows.find(row => y < row.mid);
        return { sectionId: section.id, index: before ? rows.indexOf(before) : rows.length };
    }

    function onPointerMove(event) {
        if (!dragState) return;
        document.querySelectorAll('.oc-wm-drop-active').forEach(el => el.classList.remove('oc-wm-drop-active'));
        const target = targetFromPointer(event.clientY);
        document.querySelector(`[data-drop-section="${CSS.escape(target.sectionId)}"]`)?.classList.add('oc-wm-drop-active');
    }

    async function onPointerUp(event) {
        document.removeEventListener('pointermove', onPointerMove);
        document.querySelectorAll('.oc-wm-row--dragging').forEach(el => el.classList.remove('oc-wm-row--dragging'));
        document.querySelectorAll('.oc-wm-drop-active').forEach(el => el.classList.remove('oc-wm-drop-active'));

        if (!dragState) return;
        const target = targetFromPointer(event.clientY);
        const key = dragState.key;
        dragState = null;

        try {
            moveWidgetTo(key, target.sectionId, target.index);
            await saveAll();
            renderManager();
        } catch (error) {
            console.error(error);
            alert('Could not save widget order. Please try again.');
        }
    }

    function moveWidgetTo(key, sectionId, index) {
        const item = manifest.find(item => item.key === key);
        if (!item) return;
        setItemSection(item, sectionId);

        const currentSections = sections(true);
        const withoutDragged = manifest.filter(item => item.key !== key);
        const next = [];

        currentSections.forEach(section => {
            const sectionItems = withoutDragged.filter(item => sectionFor(item).id === section.id).sort((a, b) => a.position - b.position);
            if (section.id === sectionId) sectionItems.splice(index, 0, item);
            next.push(...sectionItems);
        });

        manifest = next.map((item, position) => ({ ...item, position }));
    }

    function patchManager() {
        if (patched || !window.DashboardWidgetManager) return;
        patched = true;
        const originalOpen = window.DashboardWidgetManager.openManager;

        window.DashboardWidgetManager.openManager = async function openSectionAwareManager() {
            await originalOpen.call(window.DashboardWidgetManager);
            try {
                await fetchManifest();
                renderManager();
            } catch (error) {
                console.error('Section-aware dashboard manager failed:', error);
            }
        };
    }

    function boot() {
        patchManager();
        setTimeout(async () => {
            try {
                await fetchManifest();
                renderDashboardSections();
            } catch (error) {
                console.error('Dashboard section render failed:', error);
            }
        }, 0);
    }

    document.addEventListener('DOMContentLoaded', boot);
})();
</script>
HTML;
    }
}
