/**
 * oc-surface-controller.js
 * Thin orchestrator: fetches each section's data and hands it to whichever
 * widget owns that component. It owns NO widget-specific markup or actions —
 * those live in each oc-*-widget.js file. Load this file last.
 *
 * Load order:
 *   oc-shared.js
 *   oc-api-client.js
 *   oc-earnings-widget.js
 *   oc-payouts-widget.js
 *   oc-disputes-widget.js
 *   oc-admin-disputes-widget.js
 *   oc-admin-payouts-widget.js
 *   oc-admin-violations-widget.js
 *   oc-surface-controller.js
 */
(() => {
    const {skeleton, errorBox} = window.OpenCollabShared;

    class OpenCollabSurfaceRenderer {
        constructor({surface, site, sections, token, context = {}}) {
            this.surface = surface;
            this.site = site;
            this.sections = Array.isArray(sections) ? sections : [];
            this.token = token;
            this.context = context ?? {};

            this.api = new window.OpenCollabApiClient(token);
            const reload = (section, bypassCache) => this.loadSection(section, bypassCache);

            // Each widget instance owns its own state + actions for its components.
            // Only widgets whose script was actually loaded on this page are instantiated —
            // a view that only includes oc-earnings-widget.js won't pull in the others.
            const widgetFactories = [
                ['OpenCollabEarningsWidget', () => new window.OpenCollabEarningsWidget({site, context: this.context})],
                ['OpenCollabPayoutsWidget', () => new window.OpenCollabPayoutsWidget({site, api: this.api, reload})],
                ['OpenCollabDisputesWidget', () => new window.OpenCollabDisputesWidget({site, api: this.api, context: this.context, reload})],
                ['OpenCollabAdminDisputesWidget', () => new window.OpenCollabAdminDisputesWidget({site, api: this.api, reload})],
                ['OpenCollabAdminPayoutsWidget', () => new window.OpenCollabAdminPayoutsWidget({site, api: this.api, reload})],
                ['OpenCollabAdminViolationsWidget', () => new window.OpenCollabAdminViolationsWidget({site, api: this.api, context: this.context, reload})],
            ];

            this.widgets = widgetFactories
                .filter(([globalName]) => typeof window[globalName] !== 'undefined')
                .map(([, factory]) => factory());

            // Build a component -> owning-widget lookup from each widget's declared components.
            this.componentOwner = new Map();
            this.widgets.forEach(widget => {
                widget.constructor.components.forEach(component => this.componentOwner.set(component, widget));
            });
        }

        init() {
            this.sections.forEach((section) => this.loadSection(section));
        }

        async loadSection(section, bypassCache = false) {
            const el = document.querySelector(`[data-surface-section="${CSS.escape(section.key)}"]`);
            if (!el) return;

            if (bypassCache) {
                this.api.bust(section.endpoint);
            }

            el.innerHTML = skeleton(section.title);

            const widget = this.componentOwner.get(section.component);
            if (!widget) {
                el.innerHTML = errorBox(`Renderer not found: ${section.component}`);
                return;
            }

            try {
                const payload = await this.api.fetchJson(section.endpoint);
                const raw = payload?.data ?? payload;
                const data = widget.normalise(section.component, raw);
                await widget.render(el, section, section.component, data);
            } catch (e) {
                console.error('Surface section failed', section, e);
                el.innerHTML = errorBox('Could not load this section.');
            }
        }
    }

    window.OpenCollabSurfaceRenderer = OpenCollabSurfaceRenderer;
})();