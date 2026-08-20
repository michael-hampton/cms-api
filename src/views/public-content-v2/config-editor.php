<?php
$initialSiteId = $siteSlug ?? 'guitar-world';
$configType = 'public_content'; // Default active tab view
$knownWidgetDefaults = $widgetDefaults ?? [];
$knownWidgetSettingsSchema = $widgetSettingsSchema ?? [];
$knownWidgetRegions = $widgetRegions ?? [];
$csrfToken = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Config Engine Workspace</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs/loader.js"></script>
    <style>
        :root {
            --bg-primary: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --accent-color: #2563eb;
            --accent-hover: #1d4ed8;
            --danger-color: #dc2626;
            --danger-bg: #fef2f2;
            --warning-color: #d97706;
            --warning-bg: #fffbeb;
            --success-color: #16a34a;
            --success-bg: #f0fdf4;
            --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-stack);
            background-color: var(--bg-primary);
            color: var(--text-main);
            padding: 2rem;
            line-height: 1.5;
        }

        .workspace-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .workspace-title h1 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .workspace-title p {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .tab-navigation-bar {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 1px;
        }

        .tab-btn {
            padding: 0.75rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-muted);
            background: transparent;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .tab-btn:hover {
            color: var(--text-main);
            background: #f1f5f9;
            border-radius: 6px 6px 0 0;
        }

        .tab-btn.active-tab {
            color: var(--accent-color);
            border-bottom-color: var(--accent-color);
            background: #ffffff;
            font-weight: 700;
        }

        .meta-controls {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .badge {
            font-family: monospace;
            font-size: 0.75rem;
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            background: #e2e8f0;
            border: 1px solid #cbd5e1;
        }

        .site-selector-container {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: #fff;
            padding: 0.25rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
        }

        .site-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            font-weight: 700;
        }

        .site-select {
            border: none;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--accent-color);
            outline: none;
            background: transparent;
            cursor: pointer;
        }

        .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 6px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .btn-primary {
            background-color: var(--accent-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--accent-hover);
        }

        .btn-success {
            background-color: var(--success-color);
            color: white;
        }

        .btn-danger {
            background-color: transparent;
            color: var(--danger-color);
            border-color: var(--border-color);
        }

        .btn-danger:hover {
            background-color: var(--danger-bg);
            border-color: var(--danger-color);
        }

        .btn-secondary {
            background-color: #f1f5f9;
            color: #334155;
            border-color: var(--border-color);
        }

        .btn-secondary:hover {
            background-color: #e2e8f0;
        }

        .btn-xs {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }

        .editor-grid {
            display: grid;
            grid-template-columns: 1.3fr 0.7fr;
            gap: 2rem;
            align-items: start;
        }

        .panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        .panel-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #334155;
        }

        .search-container {
            margin-bottom: 1rem;
            position: relative;
        }

        .search-input {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            font-size: 0.875rem;
        }

        .entries-list {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            max-height: 750px;
            overflow-y: auto;
            padding-right: 0.25rem;
        }

        .entry-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: #ffffff;
            padding: 1.25rem;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.02);
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            border-left: 4px solid #cbd5e1;
        }

        .entry-card.active-bool { border-left-color: #10b981; }
        .entry-card.active-structural { border-left-color: var(--accent-color); }
        .entry-card.has-error { border-color: var(--danger-color); background: var(--danger-bg); }

        .card-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.5rem;
        }

        .card-title {
            font-family: monospace;
            font-size: 0.9375rem;
            font-weight: 700;
            color: #1e293b;
            text-transform: uppercase;
        }

        .input-field {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.875rem;
            font-family: inherit;
        }

        .input-field.code-font {
            font-family: 'Courier New', Courier, monospace;
            font-size: 0.84375rem;
            line-height: 1.4;
        }

        .cell-select {
            width: 100%;
            padding: 0.5rem;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            font-size: 0.875rem;
            background: #fff;
            font-weight: 500;
        }

        .sub-form-block {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .token-category-wrapper {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .token-category-header {
            font-size: 0.75rem;
            text-transform: uppercase;
            font-weight: 700;
            color: var(--accent-color);
            border-bottom: 1px solid #f1f5f9;
            padding-bottom: 0.25rem;
            letter-spacing: 0.05em;
        }

        .token-row-grid {
            display: grid;
            grid-template-columns: 180px 1fr auto;
            gap: 1rem;
            align-items: center;
        }

        .token-color-picker-wrapper {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            width: 100%;
        }

        .color-input-node {
            width: 40px;
            height: 36px;
            padding: 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            background: none;
            flex-shrink: 0;
        }

        .widgets-dashboard {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }

        .widget-config-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.03);
        }

        .widget-card-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px dashed #f1f5f9;
            padding-bottom: 0.5rem;
        }

        .widget-card-identity {
            font-family: monospace;
            font-size: 0.875rem;
            font-weight: 700;
            color: #334155;
            background: #f1f5f9;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
        }

        .widget-card-body-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.1fr) minmax(240px, 1fr);
            gap: 1.5rem;
            align-items: start;
        }

        .widget-scopes-pane { display: flex; flex-direction: column; gap: 0.35rem; }
        .widget-pane-title { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); font-weight: 700; }
        .pill-checkbox-group { display: flex; flex-wrap: wrap; gap: 0.4rem; }

        .pill-checkbox-label {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            background: #f8fafc;
            border: 1px solid var(--border-color);
            padding: 0.35rem 0.65rem;
            border-radius: 20px;
            cursor: pointer;
        }

        .pill-checkbox-label:has(input:checked) {
            background: #e0f2fe;
            border-color: #bae6fd;
            color: var(--accent-color);
        }

        .widget-limit-pane { display: flex; flex-direction: column; gap: 0.35rem; }
        .widget-page-type-placements {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-top: 0.35rem;
        }
        .widget-page-type-placement-row {
            display: grid;
            grid-template-columns: minmax(5.5rem, 7rem) minmax(0, 1fr);
            gap: 0.4rem;
            align-items: center;
        }
        .widget-page-type-placement-row span {
            font-size: 0.75rem;
            font-weight: 600;
            color: #475569;
            text-transform: lowercase;
        }

        .page-override-panel {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .page-override-results {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            max-height: 12rem;
            overflow-y: auto;
        }

        .page-override-result {
            text-align: left;
            border: 1px solid var(--border-color);
            background: #fff;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            cursor: pointer;
        }

        .page-override-result:hover {
            border-color: var(--accent-color);
            background: #eff6ff;
        }

        .page-override-result.is-selected {
            border-color: var(--accent-color);
            background: #eff6ff;
            box-shadow: inset 0 0 0 1px var(--accent-color);
        }

        .page-override-selected.is-active {
            border: 1px solid var(--accent-color);
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            background: #eff6ff;
        }

        .page-override-result strong {
            display: block;
            font-size: 0.85rem;
        }

        .page-override-result span {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        .page-override-selected {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
        }

        .page-override-rows {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
        }

        .page-override-row {
            display: grid;
            grid-template-columns: auto minmax(0, 1.2fr) auto minmax(7rem, 0.8fr) 5rem;
            gap: 0.5rem;
            align-items: center;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            padding: 0.45rem 0.6rem;
        }

        .page-override-row.is-inherited {
            opacity: 0.72;
        }

        .page-override-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
        }

        .page-override-status {
            font-size: 0.75rem;
            color: var(--text-muted);
        }

        #page-override-host {
            margin-bottom: 1rem;
        }

        #page-override-host[hidden] {
            display: none !important;
        }

        .json-textarea {
            width: 100%;
            height: 680px;
            font-family: monospace;
            font-size: 0.84375rem;
            padding: 1rem;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            resize: vertical;
            background-color: #1e1e1e;
            color: #d4d4d4;
        }

        .monaco-toolbar {
            display: none;
            gap: 0.5rem;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 0.75rem;
        }

        .monaco-toolbar .badge {
            font-weight: 700;
        }

        .monaco-toolbar .badge.diag-ok { background: var(--success-bg); color: var(--success-color); border-color: #bbf7d0; }
        .monaco-toolbar .badge.diag-error { background: var(--danger-bg); color: var(--danger-color); border-color: #fecaca; }
        .monaco-toolbar .badge.diag-warn { background: var(--warning-bg); color: var(--warning-color); border-color: #fde68a; }

        .monaco-host {
            display: none;
            width: 100%;
            height: 680px;
            border: 1px solid var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }

        .diagnostics-list {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            max-height: 560px;
            overflow-y: auto;
        }

        .diagnostic-item {
            font-family: monospace;
            font-size: 0.75rem;
            padding: 0.5rem 0.65rem;
            border-radius: 4px;
            border-left: 3px solid;
            cursor: pointer;
            line-height: 1.4;
        }

        .diagnostic-item:hover { filter: brightness(0.97); }
        .diagnostic-item.sev-error { background: var(--danger-bg); border-left-color: var(--danger-color); color: #991b1b; }
        .diagnostic-item.sev-warning { background: var(--warning-bg); border-left-color: var(--warning-color); color: #92400e; }
        .diagnostic-item.sev-info { background: #eff6ff; border-left-color: #3b82f6; color: #1e40af; }
        .diagnostic-item .diag-loc { opacity: 0.7; margin-right: 0.35rem; }
        .diagnostics-empty { font-size: 0.8125rem; color: var(--success-color); font-style: italic; }

        .error-banner { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 6px; font-size: 0.875rem; display: none; }
        .error-banner.visible { display: block; }
        .error-banner.syntax { background-color: #fef2f2; border: 1px solid #fee2e2; color: #991b1b; }
        .error-banner.validation { background-color: #fffbeb; border: 1px solid #fef3c7; color: #92400e; }
        .error-banner.success-banner { background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; }

        .studio-form-label { font-size: 0.8125rem; font-weight: 700; color: #475569; display: flex; flex-direction: column; gap: 0.35rem; margin-bottom: 1rem; }
        .line-validator-matrix { font-family: monospace; font-size: 0.75rem; background: #fafafa; border: 1px dashed #cbd5e1; padding: 0.5rem; border-radius: 4px; color: #475569; }

        .conflict-overlay {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px);
            display: flex; align-items: center; justify-content: center; z-index: 1000;
            visibility: hidden; opacity: 0; transition: all 0.25s ease;
        }
        .conflict-overlay.visible { visibility: visible; opacity: 1; }
        .conflict-modal { background: var(--bg-card); width: 95%; max-width: 1100px; max-height: 85vh; border-radius: 12px; display: flex; flex-direction: column; overflow: hidden; }
        .conflict-header { padding: 1.5rem; border-bottom: 1px solid var(--border-color); background: var(--danger-bg); }
        .conflict-body { padding: 1.5rem; overflow-y: auto; flex-grow: 1; }
        .conflict-footer { padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 1rem; }
        .conflict-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        .conflict-table th, .conflict-table td { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color); vertical-align: top; }
        .conflict-table th { background: #f1f5f9; }

    </style>
</head>
<body>

<header class="workspace-header">
    <div class="workspace-title">
        <h1>Configuration Gateway</h1>
        <p>Tab-Isolated Multitenant Schema Form Management Dashboard</p>
    </div>
    <div class="meta-controls">
        <div class="site-selector-container">
            <span class="site-label">Active Tenant:</span>
            <select class="site-select" id="app-site-selector">
                <?php
                foreach (\App\Models\Site::all() as $site) { ?>
                    <option value="<?= $site->slug ?>" <?= \App\Framework\Support\SiteContext::slug() === $site->slug ? 'selected="selected"' : '' ?>><?= $site->name ?></option>
                <?php } ?>

            </select>
        </div>
        <div>Fingerprint: <span id="app-fingerprint" class="badge">Checking...</span></div>
        <button class="btn btn-primary" id="master-save-btn">Publish Changes</button>
    </div>
</header>

<nav class="tab-navigation-bar" id="app-tab-navigation">
    <button class="tab-btn" data-type="site_config">Site Identity Studio</button>
    <button class="tab-btn" data-type="public_content">Public Content Manager</button>
    <button class="tab-btn" data-type="design_tokens">Design Tokens Builder</button>
    <button class="tab-btn" data-type="custom_css">Custom CSS Studio</button>
    <button class="tab-btn" data-type="custom_js">Custom JS Script Space</button>
</nav>

<main class="editor-grid">
    <section class="panel">
        <div class="panel-title">
            <span id="visual-panel-title-text">Visual Parameter Controls</span>
            <button class="btn btn-secondary btn-xs" id="add-entry-btn">+ Add Arbitrary Root Key</button>
        </div>
        <div class="search-container" id="search-filter-wrapper">
            <input type="text" class="search-input" id="search-filter" placeholder="Filter variables...">
        </div>
        <div id="validation-banner-form" class="error-banner validation"></div>
        <div id="page-override-host" hidden></div>
        <div class="entries-list" id="visual-entries-container"></div>
    </section>

    <section class="panel">
        <div class="panel-title">
            <span id="authoritative-right-title">Authoritative Synchronized JSON</span>
            <span class="badge" id="json-status-indicator">Initializing...</span>
        </div>
        <div class="monaco-toolbar" id="monaco-toolbar">
            <button class="btn btn-secondary btn-xs" id="monaco-format-btn" title="Shift+Alt+F">⇥ Format Document</button>
            <button class="btn btn-secondary btn-xs" id="monaco-minimap-btn">Minimap: On</button>
            <button class="btn btn-secondary btn-xs" id="monaco-wrap-btn">Wrap: Off</button>
            <button class="btn btn-secondary btn-xs" id="monaco-theme-btn">Theme: Dark</button>
            <span class="badge diag-ok" id="monaco-diag-badge">0 problems</span>
        </div>
        <textarea class="json-textarea" id="json-editor-textarea" spellcheck="false"></textarea>
        <div class="monaco-host" id="monaco-host"></div>
        <div id="syntax-error-banner" class="error-banner syntax"></div>
        <div id="validation-error-banner" class="error-banner validation"></div>
        <div id="validation-success-banner" class="error-banner success-banner"></div>
    </section>
</main>

<div class="conflict-overlay" id="conflict-modal-overlay">
    <div class="conflict-modal">
        <div class="conflict-header">
            <h2>409 Concurrent Integration Mismatch</h2>
            <p>Overlapping variant states detected on remote branch. Select explicit structural strategy overrides.</p>
        </div>
        <div class="conflict-body">
            <table class="conflict-table">
                <thead>
                <tr>
                    <th style="width: 20%;">Key Path</th>
                    <th style="width: 25%;">Remote Version (Latest)</th>
                    <th style="width: 25%;">Your Version (Mine)</th>
                    <th style="width: 30%;">Resolution Action Target Choice</th>
                </tr>
                </thead>
                <tbody id="conflict-table-body"></tbody>
            </table>
        </div>
        <div class="conflict-footer">
            <button class="btn btn-secondary" id="conflict-cancel-btn">Abort</button>
            <button class="btn btn-primary" id="conflict-resolve-btn">Execute Resolution Package</button>
        </div>
    </div>
</div>

<script>
    // =========================================================================
    // MONACO EDITOR INTEGRATION (Full-featured CSS / JavaScript authoring)
    // =========================================================================
    // Loaded lazily on first visit to the Custom CSS / Custom JS tabs so the
    // ~2-3MB Monaco bundle never blocks initial page render for other tabs.
    const MonacoLoader = {
        _promise: null,
        load() {
            if (this._promise) return this._promise;
            this._promise = new Promise((resolve, reject) => {
                if (window.monaco) {
                    resolve(window.monaco);
                    return;
                }
                if (typeof require === 'undefined' || !require.config) {
                    reject(new Error('Monaco AMD loader script failed to initialize.'));
                    return;
                }
                require.config({ paths: { vs: 'https://cdnjs.cloudflare.com/ajax/libs/monaco-editor/0.47.0/min/vs' } });
                require(['vs/editor/editor.main'], () => {
                    try {
                        // Full JS IntelliSense + live syntax/semantic diagnostics
                        monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions({
                            noSemanticValidation: false,
                            noSyntaxValidation: false
                        });
                        monaco.languages.typescript.javascriptDefaults.setCompilerOptions({
                            target: monaco.languages.typescript.ScriptTarget.ES2020,
                            allowNonTsExtensions: true,
                            allowJs: true,
                            checkJs: false
                        });
                        // Live CSS linting (unknown properties, duplicate rules, vendor prefixes, etc.)
                        monaco.languages.css.cssDefaults.setOptions({
                            validate: true,
                            lint: {
                                emptyRules: 'warning',
                                duplicateProperties: 'warning',
                                unknownProperties: 'warning',
                                vendorPrefix: 'warning',
                                zeroUnits: 'ignore'
                            }
                        });
                    } catch (e) {
                        console.warn('Monaco language default configuration failed to apply.', e);
                    }
                    resolve(window.monaco);
                }, reject);
            });
            return this._promise;
        }
    };

    // =========================================================================
    // CODE DIAGNOSTIC ENGINE VERIFIERS (Non-Reformatting, Located Reporting)
    // =========================================================================
    // NOTE: Structural HTML validation remains available for any future tab
    // that needs it. CSS/JS diagnostics are now delegated to Monaco's own
    // language services (see MonacoLoader + refreshMonacoDiagnostics below).
    class AdvancedSourceValidator {
        static validateHTML(code) {
            const errors = [];
            const openedTags = [];
            const tagRegex = /<\/?([a-zA-Z0-9-]+)([^>]*)\/?>/g;
            let match;
            while ((match = tagRegex.exec(code)) !== null) {
                const fullTag = match[0];
                const tagName = match[1].toLowerCase();
                const isClosing = fullTag.startsWith('</');
                const isSelfClosing = fullTag.endsWith('/>') || ['img','br','input','meta','link','hr'].includes(tagName);

                if (!isClosing && !isSelfClosing) {
                    openedTags.push({ tag: tagName, index: match.index });
                } else if (isClosing) {
                    if (openedTags.length === 0) {
                        errors.push(`Unmatched closing tag </${tagName}> detected near index position ${match.index}.`);
                    } else {
                        const last = openedTags.pop();
                        if (last.tag !== tagName) {
                            errors.push(`Structural balance fault: expected closing tag </${last.tag}> but found </${tagName}>.`);
                        }
                    }
                }
            }
            return errors;
        }
    }
    // =========================================================================
    // DOMAIN CONFIG MODELS (Ticket 1 Specifications)
    // =========================================================================
    class ConfigEntry {
        constructor(key, value, id = null) {
            this.id = id || 'uuid-' + Math.random().toString(36).slice(2, 11);
            this.key = key;
            this.value = value;
        }

        withKey(newKey) {
            return new ConfigEntry(newKey, this.value, this.id);
        }

        withValue(newValue) {
            return new ConfigEntry(this.key, newValue, this.id);
        }

        clone() {
            return new ConfigEntry(this.key, this.value, this.id);
        }
    }

    class ConfigModel {
        constructor(entries = []) {
            this.entries = entries.map(e => e instanceof ConfigEntry ? e : new ConfigEntry(e.key, e.value, e.id));
        }

        static fromSerializableArray(arr) {
            if (!Array.isArray(arr)) return new ConfigModel([]);
            return new ConfigModel(arr.map(item => new ConfigEntry(item.key, item.value, item.id)));
        }

        static fromPairs(pairs) {
            if (!Array.isArray(pairs)) return new ConfigModel([]);
            return new ConfigModel(pairs.map(p => new ConfigEntry(p[0], p[1])));
        }

        all() {
            return this.entries;
        }

        getById(id) {
            return this.entries.find(e => e.id === id) || null;
        }

        getByKey(key) {
            return this.entries.find(e => e.key === key) || null;
        }

        add(key, value) {
            return new ConfigModel([...this.entries.map(e => e.clone()), new ConfigEntry(key, value)]);
        }

        removeById(id) {
            return new ConfigModel(this.entries.filter(e => e.id !== id).map(e => e.clone()));
        }

        rename(id, newKey) {
            return new ConfigModel(this.entries.map(e => e.id === id ? e.withKey(newKey) : e.clone()));
        }

        setValue(id, newValue) {
            return new ConfigModel(this.entries.map(e => e.id === id ? e.withValue(newValue) : e.clone()));
        }

        findDuplicateKeys() {
            const counts = {}, order = [], duplicates = [];
            this.entries.forEach(e => {
                if (!counts[e.key]) {
                    counts[e.key] = [];
                    order.push(e.key);
                }
                counts[e.key].push(e.id);
            });
            order.forEach(k => {
                if (counts[k].length > 1) duplicates.push({key: k, entryIds: counts[k]});
            });
            return duplicates;
        }

        toPairs() {
            return this.entries.map(e => [e.key, e.value]);
        }

        toArray() {
            const obj = {};
            this.entries.forEach(e => {
                obj[e.key] = e.value;
            });
            return obj;
        }

        toSerializableArray() {
            return this.entries.map(e => ({id: e.id, key: e.key, value: e.value}));
        }
    }

    class ConfigValidator {
        static validate(model) {
            const errors = [];
            if (!model || typeof model.all !== 'function') return errors;
            model.all().forEach(entry => {
                if (!entry.key || entry.key.trim() === '') {
                    errors.push({
                        entryId: entry.id,
                        key: entry.key,
                        message: 'Configuration keys cannot be left blank.'
                    });
                }
            });
            return errors;
        }
    }

    // =========================================================================
    // STATE EDITOR APPLICATION ENGINE (TAB IMPLEMENTATION)
    // =========================================================================
    class ConfigEditorApp {
        constructor(initialSiteId, documentType, knownWidgetDefaults = {}, widgetSettingsSchema = {}, widgetRegions = [], csrfToken = '') {
            this.site_id = initialSiteId || 'guitar-world';
            this.type = documentType; // Tracks active tab ('public_content' or 'design_tokens')
            this.knownWidgetDefaults = knownWidgetDefaults && typeof knownWidgetDefaults === 'object'
                ? knownWidgetDefaults
                : {};
            this.widgetSettingsSchema = widgetSettingsSchema && typeof widgetSettingsSchema === 'object'
                ? widgetSettingsSchema
                : {};
            this.widgetRegions = Array.isArray(widgetRegions) ? widgetRegions : [];
            this.csrfToken = csrfToken || '';
            this.pageOverrideState = {selected: null, draft: {}, lastSearchTerm: '', lastSearchPages: []};
            this.pageOverridePanelMounted = false;
            this.pageOverrideUi = null;

            this.model = new ConfigModel();
            this.baseSnapshotModel = null;
            this.fingerprint = '';
            this.filterTerm = '';
            this.syntaxError = null;
            this.validationErrors = [];
            this.rawTextValue = '{}';
            this.cellDraftErrors = {};

            // Monaco editor state (shared single instance, reused across css/js tab switches)
            this.monacoEditor = null;
            this.monacoChangeDisposable = null;
            this.monacoMarkerDisposable = null;
            this.monacoMinimapOn = true;
            this.monacoWrapOn = false;
            this.monacoThemeDark = true;

            this.initDomElements();
            this.bindEvents();
            this.loadSiteConfigurationPipeline();
        }

        initDomElements() {
            this.dom = {
                siteSelector: document.getElementById('app-site-selector'),
                tabNav: document.getElementById('app-tab-navigation'),
                formContainer: document.getElementById('visual-entries-container'),
                jsonTextarea: document.getElementById('json-editor-textarea'),
                searchFilter: document.getElementById('search-filter'),
                searchWrapper: document.getElementById('search-filter-wrapper'),
                addBtn: document.getElementById('add-entry-btn'),
                saveBtn: document.getElementById('master-save-btn'),
                syntaxBanner: document.getElementById('syntax-error-banner'),
                validationBanner: document.getElementById('validation-error-banner'),
                successBanner: document.getElementById('validation-success-banner'),
                formValidationBanner: document.getElementById('validation-banner-form'),
                pageOverrideHost: document.getElementById('page-override-host'),
                jsonStatusBadge: document.getElementById('json-status-indicator'),
                fingerprintDisplay: document.getElementById('app-fingerprint'),
                conflictOverlay: document.getElementById('conflict-modal-overlay'),
                conflictTableBody: document.getElementById('conflict-table-body'),
                conflictCancel: document.getElementById('conflict-cancel-btn'),
                conflictConfirm: document.getElementById('conflict-resolve-btn'),
                visualTitle: document.getElementById('visual-panel-title-text'),
                rightTitle: document.getElementById('authoritative-right-title'),
                monacoToolbar: document.getElementById('monaco-toolbar'),
                monacoHost: document.getElementById('monaco-host'),
                monacoFormatBtn: document.getElementById('monaco-format-btn'),
                monacoMinimapBtn: document.getElementById('monaco-minimap-btn'),
                monacoWrapBtn: document.getElementById('monaco-wrap-btn'),
                monacoThemeBtn: document.getElementById('monaco-theme-btn'),
                monacoDiagBadge: document.getElementById('monaco-diag-badge'),
                monacoDiagnosticsList: null // populated dynamically each time the diagnostics panel is (re)built
            };
            this.site_id = this.dom.siteSelector.value;
            this.updateTabSelectionUi();
        }

        bindEvents() {
            // Reactive context updates when user swaps site bounds
            this.dom.siteSelector.addEventListener('change', (e) => {
                const newSlug = e.target.value;
                this.site_id = newSlug;

                // 1. Parse the current browser path segments
                // e.g., "/guitar-world/public/config" -> ['guitar-world', 'public', 'config']
                const pathSegments = window.location.pathname.split('/').filter(Boolean);

                if (pathSegments.length > 0 && pathSegments[0] !== 'api') {
                    // Swap out the old slug with the newly selected site slug
                    pathSegments[0] = newSlug;

                    // Reconstruct the absolute path URL string
                    const newPath = '/' + pathSegments.join('/');

                    // Push the new path seamlessly to the browser's address bar history
                    history.pushState({site_id: newSlug}, '', newPath);
                }

                // 2. Clear out tracking caches and reload the network streams
                this.cellDraftErrors = {};
                this.loadSiteConfigurationPipeline();
            });


            this.dom.tabNav.addEventListener('click', (e) => {
                const targetTabButton = e.target.closest('.tab-btn');
                if (!targetTabButton) return;

                this.type = targetTabButton.getAttribute('data-type');
                this.updateTabSelectionUi();
                this.loadSiteConfigurationPipeline();
            });

            this.dom.addBtn.addEventListener('click', () => {
                const customKey = prompt("Enter new configuration root key name:");
                if (!customKey) return;
                this.model = this.model.add(customKey, "");
                this.rawTextValue = this.serializeModelToText(this.model);
                this.runLocalValidation();
                this.render();
            });

            this.dom.searchFilter.addEventListener('input', (e) => {
                this.filterTerm = e.target.value.toLowerCase();
                this.renderVisualFormOnly();
            });

            // The plain textarea now only drives the JSON-based tabs
            // (site_config, public_content, design_tokens). CSS/JS editing
            // is fully delegated to the Monaco editor instance below.
            this.dom.jsonTextarea.addEventListener('input', (e) => {
                this.rawTextValue = e.target.value;
                this.clearBanners();
                this.synchronizeFromTextToModel();
            });

            this.dom.saveBtn.addEventListener('click', () => this.publishToServerRouting());
            this.dom.conflictCancel.addEventListener('click', () => this.dom.conflictOverlay.classList.remove('visible'));
            this.dom.conflictConfirm.addEventListener('click', () => this.resolveConflictAndPublish());

            // --- Monaco toolbar controls (format / minimap / word-wrap / theme) ---
            this.dom.monacoFormatBtn.addEventListener('click', () => {
                if (!this.monacoEditor) return;
                const formatAction = this.monacoEditor.getAction('editor.action.formatDocument');
                if (formatAction) formatAction.run();
            });

            this.dom.monacoMinimapBtn.addEventListener('click', () => {
                this.monacoMinimapOn = !this.monacoMinimapOn;
                if (this.monacoEditor) this.monacoEditor.updateOptions({ minimap: { enabled: this.monacoMinimapOn } });
                this.dom.monacoMinimapBtn.innerText = `Minimap: ${this.monacoMinimapOn ? 'On' : 'Off'}`;
            });

            this.dom.monacoWrapBtn.addEventListener('click', () => {
                this.monacoWrapOn = !this.monacoWrapOn;
                if (this.monacoEditor) this.monacoEditor.updateOptions({ wordWrap: this.monacoWrapOn ? 'on' : 'off' });
                this.dom.monacoWrapBtn.innerText = `Wrap: ${this.monacoWrapOn ? 'On' : 'Off'}`;
            });

            this.dom.monacoThemeBtn.addEventListener('click', () => {
                this.monacoThemeDark = !this.monacoThemeDark;
                if (window.monaco) monaco.editor.setTheme(this.monacoThemeDark ? 'vs-dark' : 'vs');
                this.dom.monacoThemeBtn.innerText = `Theme: ${this.monacoThemeDark ? 'Dark' : 'Light'}`;
            });
        }

        updateTabSelectionUi() {
            this.dom.tabNav.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.toggle('active-tab', btn.getAttribute('data-type') === this.type);
            });

            this.dom.addBtn.style.display = (this.type === 'public_content') ? 'inline-block' : 'none';
            this.dom.searchWrapper.style.display = (this.type === 'public_content' || this.type === 'design_tokens') ? 'block' : 'none';
            if (this.dom.pageOverrideHost) {
                this.dom.pageOverrideHost.hidden = this.type !== 'public_content';
            }

            // Toggle between the plain JSON textarea and the Monaco editor host
            const isCodeTab = (this.type === 'custom_css' || this.type === 'custom_js');
            this.dom.jsonTextarea.style.display = isCodeTab ? 'none' : 'block';
            this.dom.monacoToolbar.style.display = isCodeTab ? 'flex' : 'none';
            this.dom.monacoHost.style.display = isCodeTab ? 'block' : 'none';
            if (isCodeTab && this.monacoEditor) {
                // Ensure correct sizing when returning to a previously-hidden host element
                requestAnimationFrame(() => this.monacoEditor.layout());
            }

            const dynamicTitles = {
                'site_config': ['Site Identity Properties', 'Identity Data Configuration Layout'],
                'public_content': ['Public Content Parameter Layout', 'Authoritative Synchronized JSON Mapping'],
                'design_tokens': ['Design Tokens Dashboard', 'Authoritative Token Variable JSON'],
                'custom_css': ['Live Theme Overrides Studio', 'Isolated Custom CSS Code Asset (Monaco Editor)'],
                'custom_js': ['Custom JavaScript Automation Surface', 'Isolated JavaScript Target Code (Monaco Editor)']
            };

            this.dom.visualTitle.innerText = dynamicTitles[this.type][0];
            this.dom.rightTitle.innerText = dynamicTitles[this.type][1];
        }

        // ---------------------------------------------------------------------
        // MULTI-TENANT FETCH REST API LAYOUT BOUNDARIES
        // ---------------------------------------------------------------------

        async loadSiteConfigurationPipeline() {
            this.pageOverrideState = {selected: null, draft: {}, lastSearchTerm: '', lastSearchPages: []};
            this.pageOverridePanelMounted = false;
            this.pageOverrideUi = null;
            this.dom.jsonStatusBadge.innerText = "Loading Node...";
            this.clearBanners();
            try {
                // Fetch all configuration types across the unified multitenant segment API route
                const response = await fetch(this.getEndpointUrl());
                const rawText = await response.text();

                if (rawText.trim().startsWith('<')) {
                    throw new Error("Backend server threw an exception instead of JSON data. Verify database configurations.");
                }

                const data = JSON.parse(rawText);
                this.fingerprint = data.fingerprint || '';

                if (this.type === 'custom_css' || this.type === 'custom_js') {
                    // For custom script and style spaces, isolate and pull the raw string field value
                    const targetEntry = data.entries && data.entries[0];
                    this.rawTextValue = targetEntry ? targetEntry.value : '';
                    this.model = new ConfigModel(); // Free model tracking collection context
                } else {
                    // For site_config, public_content, and design_tokens structural form models
                    this.model = ConfigModel.fromSerializableArray(data.entries);
                    this.baseSnapshotModel = ConfigModel.fromSerializableArray(data.entries);
                    this.rawTextValue = this.serializeModelToText(this.model);
                }

                this.dom.fingerprintDisplay.innerText = this.fingerprint || 'None';
                this.dom.jsonStatusBadge.innerText = "Synced";
                this.filterTerm = '';
                this.dom.searchFilter.value = '';

                this.render();

                // Spin up (or re-target) the Monaco editor for the code-asset workspaces
                if (this.type === 'custom_css' || this.type === 'custom_js') {
                    const language = this.type === 'custom_css' ? 'css' : 'javascript';
                    this.ensureMonacoEditor(language).catch(err => {
                        console.error('Monaco editor failed to initialize', err);
                        this.dom.jsonStatusBadge.innerText = "Editor Load Error";
                        this.dom.syntaxBanner.innerText = `Monaco failed to load: ${err.message}`;
                        this.dom.syntaxBanner.classList.add('visible');
                    });
                }
            } catch (err) {
                console.error(`Error loading configurations for site: ${this.site_id}`, err);
                this.dom.jsonStatusBadge.innerText = "Fetch Error";
                this.model = new ConfigModel();
                this.rawTextValue = '{}';
                this.render();
                this.dom.formValidationBanner.innerHTML = `<strong>Active System Crash:</strong> ${err.message}`;
                this.dom.formValidationBanner.classList.add('visible');
            }
        }

        // ---------------------------------------------------------------------
        // MONACO EDITOR LIFECYCLE + DIAGNOSTICS (Custom CSS / Custom JS tabs)
        // ---------------------------------------------------------------------

        async ensureMonacoEditor(language) {
            await MonacoLoader.load();

            if (!this.monacoEditor) {
                this.monacoEditor = monaco.editor.create(this.dom.monacoHost, {
                    value: this.rawTextValue || '',
                    language,
                    theme: this.monacoThemeDark ? 'vs-dark' : 'vs',
                    automaticLayout: true,
                    minimap: { enabled: this.monacoMinimapOn },
                    wordWrap: this.monacoWrapOn ? 'on' : 'off',
                    fontSize: 13,
                    fontFamily: "'Courier New', Courier, monospace",
                    scrollBeyondLastLine: false,
                    tabSize: 2,
                    renderWhitespace: 'selection',
                    bracketPairColorization: { enabled: true },
                    formatOnPaste: true,
                    smoothScrolling: true,
                    suggestOnTriggerCharacters: true,
                    quickSuggestions: true,
                    folding: true,
                    matchBrackets: 'always',
                    contextmenu: true
                });

                // Keep rawTextValue (used by the publish payload builder) in lockstep
                this.monacoChangeDisposable = this.monacoEditor.onDidChangeModelContent(() => {
                    this.rawTextValue = this.monacoEditor.getValue();
                    if (this.dom.jsonStatusBadge.innerText !== 'Invalid Code') {
                        this.dom.jsonStatusBadge.innerText = 'Unsaved edits';
                    }
                    if (language === 'css') this.runCssValueValidation();
                });

                // Global marker feed drives the live diagnostics panel + status badge
                this.monacoMarkerDisposable = monaco.editor.onDidChangeMarkers(() => {
                    this.refreshMonacoDiagnostics();
                });
            } else {
                const currentModel = this.monacoEditor.getModel();
                if (currentModel) monaco.editor.setModelLanguage(currentModel, language);
                this.monacoEditor.setValue(this.rawTextValue || '');
            }

            this.monacoEditor.layout();
            this.refreshMonacoDiagnostics();
            if (language === 'css') this.runCssValueValidation();
        }

        // Monaco's built-in CSS service only checks *structure* (braces, semicolons,
        // unknown property names, duplicate/empty rules). It does NOT check whether
        // a value is actually valid for a given property (e.g. `000` instead of
        // `#000`), because value grammars get complicated with var()/calc()/etc.
        // This uses the browser's own CSS.supports() to close that gap and reports
        // failures as ordinary Monaco error markers under a separate marker owner,
        // so they show up in the diagnostics panel and block publish like anything else.
        runCssValueValidation() {
            if (this.type !== 'custom_css' || !this.monacoEditor || !window.monaco) return;
            const model = this.monacoEditor.getModel();
            if (!model || typeof CSS === 'undefined' || typeof CSS.supports !== 'function') return;

            const markers = [];
            const declRegex = /([a-zA-Z-]+)\s*:\s*([^;{}]+);/;
            const lines = model.getValue().split('\n');

            lines.forEach((lineText, idx) => {
                const match = lineText.match(declRegex);
                if (!match) return;

                const prop = match[1].trim();
                const value = match[2].trim();
                if (!prop || !value || prop.startsWith('--') || value.includes('var(')) return;

                let isSupported = true;
                try {
                    isSupported = CSS.supports(prop, value);
                } catch (e) {
                    isSupported = true; // fail-open on parser quirks rather than false-flag
                }

                if (!isSupported) {
                    const startCol = lineText.indexOf(match[0]) + 1;
                    const looksLikeMissingHex = /^[0-9a-fA-F]{3}$|^[0-9a-fA-F]{6}$/.test(value);
                    markers.push({
                        severity: monaco.MarkerSeverity.Error,
                        startLineNumber: idx + 1,
                        startColumn: startCol,
                        endLineNumber: idx + 1,
                        endColumn: startCol + match[0].length,
                        message: looksLikeMissingHex
                            ? `"${value}" is not a valid value for "${prop}". Hex colors need a leading '#' — did you mean "#${value}"?`
                            : `"${value}" is not a valid value for "${prop}".`
                    });
                }
            });

            monaco.editor.setModelMarkers(model, 'css-value-validator', markers);
        }

        refreshMonacoDiagnostics() {
            if (!this.monacoEditor || !window.monaco) return;
            const model = this.monacoEditor.getModel();
            if (!model) return;

            const markers = monaco.editor.getModelMarkers({ resource: model.uri });
            this.renderDiagnosticsList(markers);

            const errorCount = markers.filter(m => m.severity === monaco.MarkerSeverity.Error).length;
            const warnCount = markers.filter(m => m.severity === monaco.MarkerSeverity.Warning).length;

            this.dom.monacoDiagBadge.classList.remove('diag-ok', 'diag-error', 'diag-warn');
            if (errorCount > 0) {
                this.dom.monacoDiagBadge.innerText = `${errorCount} error${errorCount === 1 ? '' : 's'}, ${warnCount} warning${warnCount === 1 ? '' : 's'}`;
                this.dom.monacoDiagBadge.classList.add('diag-error');
                this.dom.jsonStatusBadge.innerText = 'Invalid Code';
            } else if (warnCount > 0) {
                this.dom.monacoDiagBadge.innerText = `${warnCount} warning${warnCount === 1 ? '' : 's'}`;
                this.dom.monacoDiagBadge.classList.add('diag-warn');
                this.dom.jsonStatusBadge.innerText = 'Synced (warnings)';
            } else {
                this.dom.monacoDiagBadge.innerText = '0 problems';
                this.dom.monacoDiagBadge.classList.add('diag-ok');
                this.dom.jsonStatusBadge.innerText = 'Synced';
            }
        }

        renderDiagnosticsList(markers) {
            const listEl = this.dom.monacoDiagnosticsList;
            if (!listEl) return;

            if (!markers || markers.length === 0) {
                listEl.innerHTML = '<span class="diagnostics-empty">✓ No problems detected.</span>';
                return;
            }

            const sevClass = (sev) => {
                if (sev === monaco.MarkerSeverity.Error) return 'sev-error';
                if (sev === monaco.MarkerSeverity.Warning) return 'sev-warning';
                return 'sev-info';
            };

            listEl.innerHTML = markers.map(m => `
                <div class="diagnostic-item ${sevClass(m.severity)}" data-line="${m.startLineNumber}" data-col="${m.startColumn}">
                    <span class="diag-loc">Ln ${m.startLineNumber}, Col ${m.startColumn}</span>${String(m.message || '').replace(/</g, '&lt;')}
                </div>
            `).join('');

            listEl.querySelectorAll('.diagnostic-item').forEach(item => {
                item.addEventListener('click', () => {
                    if (!this.monacoEditor) return;
                    const line = Number(item.getAttribute('data-line'));
                    const col = Number(item.getAttribute('data-col'));
                    this.monacoEditor.revealLineInCenter(line);
                    this.monacoEditor.setPosition({ lineNumber: line, column: col });
                    this.monacoEditor.focus();
                });
            });
        }

        // Returns true when it's safe to publish (no Monaco error-severity markers)
        runCodeDiagnostics() {
            if (!(this.type === 'custom_css' || this.type === 'custom_js')) return true;
            if (!this.monacoEditor || !window.monaco) return true;

            const model = this.monacoEditor.getModel();
            const markers = monaco.editor.getModelMarkers({ resource: model.uri });
            this.renderDiagnosticsList(markers);

            const errorMarkers = markers.filter(m => m.severity === monaco.MarkerSeverity.Error);
            if (errorMarkers.length > 0) {
                this.dom.syntaxBanner.innerHTML = `<strong>Verification Alerts Found:</strong><ul>${errorMarkers.map(m => `<li>Line ${m.startLineNumber}: ${String(m.message || '').replace(/</g, '&lt;')}</li>`).join('')}</ul>`;
                this.dom.syntaxBanner.classList.add('visible');
                this.dom.jsonStatusBadge.innerText = "Invalid Code";
                return false;
            }

            this.dom.successBanner.innerText = "✓ No errors detected by Monaco's language service.";
            this.dom.successBanner.classList.add('visible');
            this.dom.jsonStatusBadge.innerText = "Synced";
            return true;
        }

        getEndpointUrl() {
            if (this.type === 'public_content' || this.type === 'design_tokens') {
                // Keeps original content/config documents table functionality safe
                return `/api/v1/${this.site_id}/content/config/${this.type}`;
            }
            // New independent routes directing straight to SiteController.php actions
            return `/api/v1/${this.site_id}/content/site-config/${this.type}`;
        }

        async publishToServerRouting() {
            this.runLocalValidation();
            if (this.type === 'custom_css' || this.type === 'custom_js') {
                if (!this.runCodeDiagnostics()) return;
            } else {
                if (this.syntaxError || this.validationErrors.length > 0) {
                    alert("Please resolve structure warnings before writing context states into database.");
                    return;
                }
            }

            let payloadBody = {};
            if (this.type === 'site_config') {
                const parsed = JSON.parse(this.rawTextValue);
                payloadBody = {
                    name: parsed.name,
                    slug: parsed.slug,
                    logo: parsed.logo,
                    gentle_html_formatting: parsed.gentle_html_formatting,
                    loadedFingerprint: this.fingerprint
                };
            } else if (this.type === 'custom_css') {
                payloadBody = {
                    custom_css: this.monacoEditor ? this.monacoEditor.getValue() : this.rawTextValue,
                    loadedFingerprint: this.fingerprint
                };
            } else if (this.type === 'custom_js') {
                payloadBody = {
                    custom_js: this.monacoEditor ? this.monacoEditor.getValue() : this.rawTextValue,
                    loadedFingerprint: this.fingerprint
                };
            } else {
                // Retain standard legacy structure for 'public_content' and 'design_tokens'
                payloadBody = {
                    rawJson: this.rawTextValue,
                    loadedFingerprint: this.fingerprint,
                    updatedBy: 'Unified Schema Engine Client Operator'
                };
            }

            try {
                const response = await fetch(this.getEndpointUrl(), {
                    method: 'PUT',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(payloadBody)
                });

                const result = await response.json();

                if (result.status === 'saved' || result.status === 'synced') {
                    const saveBtn = this.dom.saveBtn;
                    if (saveBtn) {
                        const originalText = saveBtn.innerText;
                        saveBtn.innerText = '✓ Changes Published!';
                        saveBtn.classList.remove('btn-primary');
                        saveBtn.classList.add('btn-success');
                        saveBtn.disabled = true;

                        setTimeout(() => {
                            saveBtn.innerText = originalText;
                            saveBtn.classList.remove('btn-success');
                            saveBtn.classList.add('btn-primary');
                            saveBtn.disabled = false;
                        }, 2500);
                    }

                    this.fingerprint = result.fingerprint;
                    if (this.dom.fingerprintDisplay) {
                        this.dom.fingerprintDisplay.innerText = this.fingerprint;
                    }

                    if (this.type === 'custom_css' || this.type === 'custom_js') {
                        const targetEntry = result.entries && result.entries[0];
                        this.rawTextValue = targetEntry ? targetEntry.value : '';
                        if (this.monacoEditor) this.monacoEditor.setValue(this.rawTextValue);
                    } else {
                        this.model = ConfigModel.fromSerializableArray(result.entries);
                        this.baseSnapshotModel = ConfigModel.fromSerializableArray(result.entries);
                    }
                    this.render();

                } else if (result.status === 'conflict') {
                    this.handleConflictInterception(result);
                } else if (result.status === 'invalid') {
                    alert("Storage engine rejected execution shape.");
                    this.validationErrors = result.validationErrors || [];
                    this.renderErrorsOnly();
                }
            } catch (err) {
                console.error("Communication socket error tracking link crash: ", err);
            }
        }

        async resolveConflictAndPublish() {
            const resolutions = {};
            const keysWithInputs = this.dom.conflictTableBody.querySelectorAll('tr');

            keysWithInputs.forEach(tr => {
                const key = tr.getAttribute('data-key');
                const selectedRadio = tr.querySelector(`input[name="res-${key}"]:checked`);

                if (selectedRadio) {
                    const choice = selectedRadio.value;
                    resolutions[key] = {choice: choice};

                    if (choice === 'edited') {
                        try {
                            resolutions[key].value = JSON.parse(tr.querySelector(`#custom-val-${key}`).value);
                        } catch (e) {
                            resolutions[key].value = tr.querySelector(`#custom-val-${key}`).value;
                        }
                    }
                }
            });

            try {
                const response = await fetch(`/api/v1/${this.site_id}/content/config/${this.type}/publish`, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        base: this.baseSnapshotModel.toSerializableArray(),
                        mine: this.model.toSerializableArray(),
                        resolutions: resolutions,
                        updatedBy: 'Reconciliation Module Execution Gateway Session'
                    })
                });

                const result = await response.json();

                if (result.status === 'saved') {
                    this.dom.conflictOverlay.classList.remove('visible');
                    alert(`Conflicting parameter variations combined cleanly for multi-tenant segment: ${this.site_id}`);

                    this.fingerprint = result.fingerprint;
                    this.dom.fingerprintDisplay.innerText = this.fingerprint;
                    this.model = ConfigModel.fromSerializableArray(result.entries);
                    this.baseSnapshotModel = ConfigModel.fromSerializableArray(result.entries);
                    this.rawTextValue = this.serializeModelToText(this.model);
                    this.render();
                } else {
                    alert("Failure compiled inside backend during resolution merge process execution.");
                }
            } catch (err) {
                console.error("Exception handled while deploying manual conflict updates: ", err);
            }
        }

        handleFormValueChange(id, newValue) {
            this.model = this.model.setValue(id, newValue);
            this.rawTextValue = this.serializeModelToText(this.model);
            this.runLocalValidation();
            this.dom.jsonTextarea.value = this.rawTextValue;
            this.renderErrorsOnly();
        }

        // ---------------------------------------------------------------------
        // OPTION 1 SUB-FORMS GRAPHICAL DISPATCHER MATRIX
        // ---------------------------------------------------------------------

        renderVisualFormOnly() {
            try {
                this.ensurePageOverridePanel();
                this.dom.formContainer.innerHTML = '';

                if (this.type === 'site_config') {
                    this.buildSiteConfigForm();
                    return;
                }
                if (this.type === 'custom_css' || this.type === 'custom_js') {
                    this.buildMonacoDiagnosticsPanel();
                    return;
                }

                const pageTypesEntry = this.model.getByKey('page_types');
                const systemAvailablePageTypes = pageTypesEntry && Array.isArray(pageTypesEntry.value) ? pageTypesEntry.value : ['content', 'article', 'landing-page', 'review'];

                const filtered = this.model.all().filter(e => this.filterTerm === '' || e.key.toLowerCase().includes(this.filterTerm));

                filtered.forEach(entry => {
                const card = document.createElement('div');
                card.className = 'entry-card';
                card.setAttribute('data-id', entry.id);

                const headerRow = document.createElement('div');
                headerRow.className = 'card-header-row';
                headerRow.innerHTML = `
                    <span class="card-title">${entry.key}</span>
                    <button class="btn btn-danger btn-xs js-del-root">Delete</button>
                `;
                headerRow.querySelector('.js-del-root').addEventListener('click', () => {
                    this.model = this.model.removeById(entry.id);
                    this.rawTextValue = this.serializeModelToText(this.model);
                    this.render();
                });
                card.appendChild(headerRow);

                // 100% Complete original switch configuration statement matching input keys
                if (this.type === 'public_content') {
                    switch (entry.key) {
                        case 'enabled':
                        case 'preview_enabled':
                        case 'shadow_enabled':
                            card.classList.add('active-bool');
                            this.buildBooleanSubForm(card, entry);
                            break;
                        case 'page_types':
                        case 'widget_definitions':
                        case 'site_ids':
                            card.classList.add('active-structural');
                            this.buildFlatListSubForm(card, entry);
                            break;
                        case 'cache':
                            card.classList.add('active-structural');
                            this.buildCacheSubForm(card, entry);
                            break;
                        case 'slug_patterns':
                            card.classList.add('active-structural');
                            this.buildSlugPatternsSubForm(card, entry);
                            break;
                        case 'widgets':
                            card.classList.add('active-structural');
                            this.buildWidgetsSubForm(card, entry, systemAvailablePageTypes);
                            break;
                        default:
                            this.buildDefaultSubForm(card, entry);
                            break;
                    }
                } else if (this.type === 'design_tokens') {
                    card.classList.add('active-structural');
                    this.buildDesignTokenSubForm(card, entry);
                }

                this.dom.formContainer.appendChild(card);
                });
            } catch (err) {
                console.error('Visual form render failed', err);
                this.dom.formValidationBanner.innerHTML = `<strong>Visual editor failed:</strong> ${err.message || err}`;
                this.dom.formValidationBanner.classList.add('visible');
            }
        }

        buildSiteConfigForm() {
            const wrapper = document.createElement('div');
            wrapper.className = 'sub-form-block';
            wrapper.style.background = '#ffffff';

            const nameVal = this.model.getByKey('name')?.value || '';
            const slugVal = this.model.getByKey('slug')?.value || '';
            const logoVal = this.model.getByKey('logo')?.value || '';
            const gentleHtml = this.model.getByKey('gentle_html_formatting')?.value !== false;

            wrapper.innerHTML = `
                <label class="studio-form-label">Canonical Brand Name
                    <input type="text" class="input-field" id="sc-name" value="${nameVal}">
                </label>
                <label class="studio-form-label">System Gateway Deployment Slug
                    <input type="text" class="input-field" id="sc-slug" value="${slugVal}">
                </label>
                <label class="studio-form-label">Asset Path Brand Logo Route
                    <input type="text" class="input-field" id="sc-logo" value="${logoVal}">
                </label>
                <label class="studio-form-label" style="flex-direction:row; gap:8px; align-items:center; cursor:pointer; margin-top:0.5rem;">
                    <input type="checkbox" id="sc-gentle" ${gentleHtml ? 'checked' : ''}>
                    <span>Enable Safe Gentle HTML Formatter Integration</span>
                </label>
            `;

            const syncFields = () => {
                let targetPairs = [
                    ['name', wrapper.querySelector('#sc-name').value],
                    ['slug', wrapper.querySelector('#sc-slug').value],
                    ['logo', wrapper.querySelector('#sc-logo').value],
                    ['gentle_html_formatting', wrapper.querySelector('#sc-gentle').checked]
                ];
                this.model = ConfigModel.fromPairs(targetPairs);
                this.rawTextValue = JSON.stringify(this.model.toArray(), null, 4);
                this.dom.jsonTextarea.value = this.rawTextValue;
            };

            wrapper.querySelectorAll('input').forEach(input => input.addEventListener('input', syncFields));
            this.dom.formContainer.appendChild(wrapper);
        }

        // Left-panel companion for the Custom CSS / Custom JS tabs. Rather than
        // duplicating Monaco's own highlighting, this surfaces a live,
        // click-to-jump list of the errors/warnings Monaco's language service
        // reports for the code currently in the editor.
        buildMonacoDiagnosticsPanel() {
            const card = document.createElement('div');
            card.className = 'entry-card active-structural';
            card.innerHTML = `
                <h3 style="margin-bottom:0.25rem; font-size:1rem; color:#334155;">Monaco Language Diagnostics</h3>
                <p style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.25rem;">
                    Live errors and warnings from Monaco's built-in ${this.type === 'custom_css' ? 'CSS' : 'JavaScript'} language service.
                    <strong>Ctrl/Cmd+F</strong> to find &amp; replace, <strong>Shift+Alt+F</strong> to format, right-click in the editor for the full command palette (multi-cursor, code folding, go-to-definition, and more).
                </p>
                <div class="diagnostics-list" id="monaco-diagnostics-list">
                    <span class="diagnostics-empty">Scanning...</span>
                </div>
            `;
            this.dom.formContainer.appendChild(card);
            this.dom.monacoDiagnosticsList = card.querySelector('#monaco-diagnostics-list');

            // Populate immediately with whatever markers already exist
            if (this.monacoEditor && window.monaco) {
                const model = this.monacoEditor.getModel();
                if (model) this.renderDiagnosticsList(monaco.editor.getModelMarkers({ resource: model.uri }));
            }
        }

        // ---------------------------------------------------------------------
        // TAB B: TWO-LEVEL DEEP NESTED DESIGN TOKENS CORE BUILDER
        // ---------------------------------------------------------------------

        buildDesignTokenSubForm(container, entry) {
            const rootGroupObj = entry.value && typeof entry.value === 'object' ? entry.value : {};

            const outerBlock = document.createElement('div');
            outerBlock.className = 'sub-form-block';
            outerBlock.style.gap = '1.25rem';

            // Helper Utility: Parses CSS strings like "16px" or "3rem" into numeric parts
            const parseCssDimension = (str) => {
                if (typeof str !== 'string') return null;
                const match = str.trim().match(/^([\d.]+)([a-zA-Z%]+)$/);
                if (!match) return null;
                return {numericValue: parseFloat(match[1]), unitToken: match[2]};
            };

            // Loop through Level 1 category objects (e.g. "color", "brand", "font")
            Object.keys(rootGroupObj).forEach(categoryKey => {
                const categoryData = rootGroupObj[categoryKey] || {};

                const categoryCard = document.createElement('div');
                categoryCard.className = 'token-category-wrapper';

                const categoryHeader = document.createElement('div');
                categoryHeader.className = 'token-category-header';
                categoryHeader.innerText = categoryKey;
                categoryCard.appendChild(categoryHeader);

                // Loop through Level 2 token variables inside this category (e.g. "primary", "card_radius")
                Object.keys(categoryData).forEach(tokenKey => {
                    const currentVal = categoryData[tokenKey];
                    const row = document.createElement('div');
                    row.className = 'token-row-grid';

                    let inputControlHtml = '';
                    let controlInitializerHook = (rowNode) => {
                    }; // Callback context for event listeners

                    // Run structural type-evaluation checks
                    const isCleanHexColor = typeof currentVal === 'string' && currentVal.startsWith('#') && (currentVal.length === 7 || currentVal.length === 4);
                    const parsedDimension = parseCssDimension(currentVal);

                    // --- TYPE CONTROL DISPATCHER MATRIX ---
                    if (isCleanHexColor) {
                        // Control A: Combined dual-binding Hex Color Picker
                        inputControlHtml = `
                    <div class="token-color-picker-wrapper">
                        <input type="color" class="color-input-node js-token-color-picker" value="${currentVal}">
                        <input type="text" class="input-field code-font js-token-text" style="padding:0.35rem; max-width:140px;" value="${currentVal}">
                    </div>
                `;
                        controlInitializerHook = (rowNode) => {
                            const textIn = rowNode.querySelector('.js-token-text');
                            const pickerIn = rowNode.querySelector('.js-token-color-picker');

                            const updateColor = (nextHex) => {
                                rootGroupObj[categoryKey][tokenKey] = nextHex;
                                this.handleFormValueChange(entry.id, rootGroupObj);
                            };

                            textIn.addEventListener('input', (e) => {
                                const val = e.target.value;
                                if (val.match(/^#[0-9a-fA-F]{6}$/)) pickerIn.value = val;
                                updateColor(val);
                            });
                            pickerIn.addEventListener('input', (e) => {
                                textIn.value = e.target.value;
                                updateColor(e.target.value);
                            });
                        };
                    } else if (parsedDimension) {
                        // Control B: Clean Number Input matching CSS measurements ("8px" -> [ 8 ] px)
                        inputControlHtml = `
                    <div style="display: inline-flex; align-items: center; gap: 0.5rem; width: 100%;">
                        <input type="number" step="0.1" class="input-field code-font js-token-dimension-num" style="padding:0.35rem; max-width:120px;" value="${parsedDimension.numericValue}">
                        <span class="badge" style="background:#f1f5f9; color:#475569; font-weight:700; border-color:#e2e8f0;">${parsedDimension.unitToken}</span>
                    </div>
                `;
                        controlInitializerHook = (rowNode) => {
                            const numberIn = rowNode.querySelector('.js-token-dimension-num');
                            numberIn.addEventListener('input', () => {
                                const val = numberIn.value;
                                // Glue scalar value and measurement label back together natively
                                rootGroupObj[categoryKey][tokenKey] = `${val}${parsedDimension.unitToken}`;
                                this.handleFormValueChange(entry.id, rootGroupObj);
                            });
                        };
                    } else {
                        // Control C: Fallback text handler fields matching fonts, transforms, or gradients
                        inputControlHtml = `
                    <input type="text" class="input-field code-font js-token-generic-text" style="padding:0.35rem;" value="${String(currentVal)}">
                `;
                        controlInitializerHook = (rowNode) => {
                            const genericIn = rowNode.querySelector('.js-token-generic-text');
                            genericIn.addEventListener('input', (e) => {
                                rootGroupObj[categoryKey][tokenKey] = e.target.value;
                                this.handleFormValueChange(entry.id, rootGroupObj);
                            });
                        };
                    }

                    // Render complete item layout wrapper row
                    row.innerHTML = `
                <div><span style="font-size:0.75rem; font-family:monospace; font-weight:700; color:#475569;">${tokenKey}</span></div>
                <div class="js-control-slot">${inputControlHtml}</div>
                <div><button class="btn btn-danger btn-xs js-del-token">×</button></div>
            `;

                    // Execute input bindings tracking current layout row instance references
                    controlInitializerHook(row);

                    row.querySelector('.js-del-token').addEventListener('click', () => {
                        delete rootGroupObj[categoryKey][tokenKey];
                        this.handleFormValueChange(entry.id, rootGroupObj);
                        this.renderVisualFormOnly();
                    });

                    categoryCard.appendChild(row);
                });

                // Add parameter property button
                const appendTokenFieldBtn = document.createElement('button');
                appendTokenFieldBtn.className = 'btn btn-secondary btn-xs';
                appendTokenFieldBtn.style.alignSelf = 'flex-start';
                appendTokenFieldBtn.innerText = `+ Add Parameter to ${categoryKey}`;
                appendTokenFieldBtn.addEventListener('click', () => {
                    const tokenName = prompt(`Enter property variable token name under [${categoryKey}]:`);
                    if (!tokenName) return;

                    // Standard smart default value based on parent group assignment types
                    rootGroupObj[categoryKey][tokenName] = categoryKey === 'color' ? '#ffffff' : '0px';
                    this.handleFormValueChange(entry.id, rootGroupObj);
                    this.renderVisualFormOnly();
                });
                categoryCard.appendChild(appendTokenFieldBtn);

                outerBlock.appendChild(categoryCard);
            });

            const addCategoryBtn = document.createElement('button');
            addCategoryBtn.className = 'btn btn-secondary btn-xs';
            addCategoryBtn.innerText = `+ Append New Category Group`;
            addCategoryBtn.addEventListener('click', () => {
                const catName = prompt("Enter new structural category block name (e.g. borders):");
                if (!catName) return;
                rootGroupObj[catName] = {};
                this.handleFormValueChange(entry.id, rootGroupObj);
                this.renderVisualFormOnly();
            });
            outerBlock.appendChild(addCategoryBtn);

            container.appendChild(outerBlock);
        }

        // ---------------------------------------------------------------------
        // TAB A: PUBLIC CONTENT MANAGER CONTROL LAYOUTS
        // ---------------------------------------------------------------------

        buildBooleanSubForm(container, entry) {
            const select = document.createElement('select');
            select.className = 'cell-select';
            select.innerHTML = `
                <option value="true" ${entry.value === true ? 'selected' : ''}>Enabled (True)</option>
                <option value="false" ${entry.value === false ? 'selected' : ''}>Disabled (False)</option>
            `;
            select.addEventListener('change', (e) => this.handleFormValueChange(entry.id, e.target.value === 'true'));
            container.appendChild(select);
        }

        buildFlatListSubForm(container, entry) {
            const currentArray = Array.isArray(entry.value) ? entry.value : [];
            const block = document.createElement('div');
            block.className = 'sub-form-block';
            const pillWrapper = document.createElement('div');
            pillWrapper.className = 'flat-list-builder';

            if (currentArray.length === 0) pillWrapper.innerHTML = '<span style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">List is empty</span>';

            currentArray.forEach((item, index) => {
                const pill = document.createElement('button');
                pill.className = 'btn btn-secondary btn-xs';
                pill.style.cssText = "display:inline-flex; align-items:center; gap:4px; margin-bottom:4px;";
                pill.innerHTML = `<span>${item}</span> <strong style="color:var(--danger-color)">×</strong>`;
                pill.addEventListener('click', () => {
                    this.handleFormValueChange(entry.id, currentArray.filter((_, idx) => idx !== index));
                    this.renderVisualFormOnly();
                });
                pillWrapper.appendChild(pill);
            });
            block.appendChild(pillWrapper);

            const inputGroup = document.createElement('div');
            inputGroup.style.display = "flex";
            inputGroup.style.gap = "4px";
            inputGroup.style.marginTop = "0.5rem";
            inputGroup.innerHTML = `
                <input type="text" class="input-field code-font js-add-val-input" style="padding:0.25rem; max-width:240px;" placeholder="Add value...">
                <button class="btn btn-primary btn-xs js-add-val-btn">Add</button>
            `;
            const triggerAddAction = () => {
                const targetInput = inputGroup.querySelector('.js-add-val-input');
                if (targetInput.value.trim() === '') return;
                this.handleFormValueChange(entry.id, [...currentArray, targetInput.value.trim()]);
                this.renderVisualFormOnly();
            };
            inputGroup.querySelector('.js-add-val-btn').addEventListener('click', triggerAddAction);
            block.appendChild(inputGroup);
            container.appendChild(block);
        }

        buildCacheSubForm(container, entry) {
            const cacheObj = entry.value && typeof entry.value === 'object' ? entry.value : {
                public_ttl_seconds: 300,
                viewer_state: 'private, no-store'
            };
            const block = document.createElement('div');
            block.className = 'sub-form-block';
            block.innerHTML = `
                <div style="display:flex; flex-direction:column; gap:0.5rem;">
                    <label style="font-size:0.75rem; font-weight:600;">Public Cache TTL (Seconds):
                        <input type="number" class="input-field js-cache-ttl" value="${cacheObj.public_ttl_seconds || 0}">
                    </label>
                    <label style="font-size:0.75rem; font-weight:600;">Viewer State Header Value:
                        <input type="text" class="input-field code-font js-cache-state" value="${cacheObj.viewer_state || ''}">
                    </label>
                </div>
            `;
            const syncCacheFields = () => {
                this.handleFormValueChange(entry.id, {
                    public_ttl_seconds: Number(block.querySelector('.js-cache-ttl').value),
                    viewer_state: block.querySelector('.js-cache-state').value
                });
            };
            block.querySelector('.js-cache-ttl').addEventListener('input', syncCacheFields);
            block.querySelector('.js-cache-state').addEventListener('input', syncCacheFields);
            container.appendChild(block);
        }

        buildSlugPatternsSubForm(container, entry) {
            const AVAILABLE_SLUG_BLUEPRINTS = {
                'flat': {pattern: '{slug}', priority: 100},
                'category_prefix': {pattern: 'category/{slug}', priority: 90},
                'category_slug': {pattern: '{category}/{slug}', priority: 80},
                'category_subcategory_slug': {pattern: '{category}/{subcategory}/{slug}', priority: 70}
            };
            const currentPatterns = entry.value && typeof entry.value === 'object' ? entry.value : {};
            const block = document.createElement('div');
            block.className = 'sub-form-block';

            Object.keys(currentPatterns).forEach(patternKey => {
                const item = currentPatterns[patternKey] || {pattern: '', priority: 0};
                const card = document.createElement('div');
                card.className = 'widget-config-card';
                card.style.borderLeft = "4px solid #f59e0b";
                card.innerHTML = `
                    <div class="widget-card-meta">
                        <div><strong style="font-family:monospace; font-size:0.875rem;">${patternKey}</strong> <code style="margin-left:0.5rem; background:#fff7ed; padding:0.15rem 0.5rem; border-radius:4px; font-size:0.8125rem; color:#c2410c;">${item.pattern}</code></div>
                        <button class="btn btn-danger btn-xs js-del-pattern">Remove</button>
                    </div>
                    <label style="font-size:0.7rem; font-weight:700; color:var(--text-muted); display:flex; flex-direction:column; gap:0.25rem;">Priority Order Weight
                        <input type="number" class="input-field js-pattern-priority" style="max-width:130px; padding:0.35rem;" value="${item.priority}">
                    </label>
                `;
                const priorityInput = card.querySelector('.js-pattern-priority');
                priorityInput.addEventListener('input', () => {
                    currentPatterns[patternKey].priority = Number(priorityInput.value);
                    this.handleFormValueChange(entry.id, currentPatterns);
                });
                card.querySelector('.js-del-pattern').addEventListener('click', () => {
                    delete currentPatterns[patternKey];
                    this.handleFormValueChange(entry.id, currentPatterns);
                    this.renderVisualFormOnly();
                });
                block.appendChild(card);
            });

            const unaddedBlueprintKeys = Object.keys(AVAILABLE_SLUG_BLUEPRINTS).filter(key => !currentPatterns[key]);
            const actionControlRow = document.createElement('div');
            actionControlRow.style.cssText = "margin-top:0.5rem; display:flex; gap:0.5rem; align-items:center;";
            if (unaddedBlueprintKeys.length > 0) {
                let optionsHtml = '';
                unaddedBlueprintKeys.forEach(key => {
                    optionsHtml += `<option value="${key}">${key} ➔ (${AVAILABLE_SLUG_BLUEPRINTS[key].pattern})</option>`;
                });
                actionControlRow.innerHTML = `<select class="cell-select js-blueprint-selector" style="max-width:320px; padding:0.35rem;">${optionsHtml}</select><button class="btn btn-secondary btn-xs js-add-blueprint-btn">+ Activate Pattern</button>`;
                actionControlRow.querySelector('.js-add-blueprint-btn').addEventListener('click', () => {
                    const chosenKey = actionControlRow.querySelector('.js-blueprint-selector').value;
                    currentPatterns[chosenKey] = {
                        pattern: AVAILABLE_SLUG_BLUEPRINTS[chosenKey].pattern,
                        priority: AVAILABLE_SLUG_BLUEPRINTS[chosenKey].priority
                    };
                    this.handleFormValueChange(entry.id, currentPatterns);
                    this.renderVisualFormOnly();
                });
            } else {
                actionControlRow.innerHTML = `<span style="font-size:0.75rem; color:var(--text-muted); font-style:italic;">All available routing patterns active.</span>`;
            }
            block.appendChild(actionControlRow);
            container.appendChild(block);
        }

        ensurePageOverridePanel() {
            if (this.type !== 'public_content') {
                if (this.dom.pageOverrideHost) {
                    this.dom.pageOverrideHost.hidden = true;
                    this.dom.pageOverrideHost.replaceChildren();
                }
                this.pageOverridePanelMounted = false;
                this.pageOverrideUi = null;
                return;
            }

            if (!this.pageOverridePanelMounted) {
                this.renderPageOverridePanel();
                this.pageOverridePanelMounted = true;
                return;
            }

            this.refreshPageOverrideWidgetRows();
        }

        refreshPageOverrideWidgetRows() {
            if (!this.pageOverrideUi) {
                return;
            }

            this.pageOverrideUi.refreshRows();
            this.pageOverrideUi.showSelectedPage();

            if (this.pageOverrideState.lastSearchPages.length) {
                this.pageOverrideUi.renderPageResults(this.pageOverrideState.lastSearchPages);
            }
        }

        renderPageOverridePanel() {
            const host = this.dom.pageOverrideHost;
            if (!host) return;
            if (this.type !== 'public_content') {
                host.hidden = true;
                host.replaceChildren();
                return;
            }

            const widgetsEntry = this.model.getByKey('widgets');
            const widgetsObj = widgetsEntry && typeof widgetsEntry.value === 'object' ? widgetsEntry.value : {};
            const knownDefaults = this.knownWidgetDefaults || {};
            const settingsSchema = this.widgetSettingsSchema || {};
            const regionOptions = Array.isArray(this.widgetRegions) && this.widgetRegions.length
                ? this.widgetRegions
                : [
                    {value: 'top', label: 'Top', aliases: ['top', 'header']},
                    {value: 'middle', label: 'Middle', aliases: ['middle', 'after-content']},
                    {value: 'bottom', label: 'Bottom', aliases: ['bottom', 'below-content']},
                    {value: 'sidebar', label: 'Sidebar', aliases: ['sidebar']},
                    {value: 'notices', label: 'Notices', aliases: ['notices']},
                    {value: 'modals', label: 'Modals', aliases: ['modals']},
                ];
            const editorRegionValue = (stored) => {
                if (!stored) return '';
                const match = regionOptions.find((option) =>
                    option.value === stored || (option.aliases || []).includes(stored)
                );
                return match ? match.value : stored;
            };
            const regionSelectHtml = (selected, extraClass = '', emptyLabel = 'Same as default') => {
                const current = editorRegionValue(selected);
                const options = regionOptions.map((option) =>
                    `<option value="${option.value}" ${current === option.value ? 'selected' : ''}>${option.label}</option>`
                ).join('');
                return `<select class="input-field ${extraClass}" style="padding:0.35rem;"><option value="">${emptyLabel}</option>${options}</select>`;
            };
            const orderedKeys = [...new Set([
                ...Object.keys(settingsSchema),
                ...Object.keys(knownDefaults),
                ...Object.keys(widgetsObj || {}),
            ])].sort((a, b) => {
                const confA = widgetsObj[a] || knownDefaults[a] || {};
                const confB = widgetsObj[b] || knownDefaults[b] || {};
                const pa = Number(confA.priority ?? 9999);
                const pb = Number(confB.priority ?? 9999);
                if (pa !== pb) return pa - pb;
                return a.localeCompare(b);
            });
            const widgetLabel = (widgetKey) =>
                settingsSchema[widgetKey]?.label
                || (widgetKey === 'adverts' ? 'Ads in the article' : widgetKey);
            const escapePageHtml = (value) => String(value == null ? '' : value)
                .split('&').join('&amp;')
                .split('<').join('&lt;')
                .split('>').join('&gt;')
                .split('"').join('&quot;');

            host.hidden = false;
            host.replaceChildren();
            const pageOverridePanel = document.createElement('div');
            pageOverridePanel.className = 'page-override-panel';
            pageOverridePanel.innerHTML = [
                '<span class="widget-pane-title">Per-page overrides</span>',
                '<p style="font-size:0.8rem;color:var(--text-muted);margin:0;">Search for a page, then override article-type widget placement for that page only. This saves separately from Publish Changes.</p>',
                '<input type="search" class="search-input js-page-override-search" placeholder="Search pages by title, slug or id">',
                '<div class="page-override-results js-page-override-results"></div>',
                '<div class="js-page-override-editor" hidden>',
                '<div class="page-override-selected">',
                '<div class="js-page-override-selected-label"></div>',
                '<button type="button" class="btn btn-secondary btn-xs js-page-override-clear">Clear page</button>',
                '</div>',
                '<div class="page-override-rows js-page-override-rows"></div>',
                '<div class="page-override-actions">',
                '<button type="button" class="btn btn-primary btn-xs js-page-override-save">Save page overrides</button>',
                '<button type="button" class="btn btn-danger btn-xs js-page-override-reset">Remove all overrides</button>',
                '<span class="page-override-status js-page-override-status"></span>',
                '</div>',
                '</div>',
            ].join('');
            host.appendChild(pageOverridePanel);

            const searchInput = pageOverridePanel.querySelector('.js-page-override-search');
            const resultsEl = pageOverridePanel.querySelector('.js-page-override-results');
            const editorEl = pageOverridePanel.querySelector('.js-page-override-editor');
            const selectedLabel = pageOverridePanel.querySelector('.js-page-override-selected-label');
            const rowsEl = pageOverridePanel.querySelector('.js-page-override-rows');
            const statusEl = pageOverridePanel.querySelector('.js-page-override-status');
            if (!searchInput || !resultsEl || !editorEl || !selectedLabel || !rowsEl || !statusEl) return;

            const pageOverrideHeaders = {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(this.csrfToken ? {'X-CSRF-TOKEN': this.csrfToken} : {}),
            };
            let searchTimer = null;
            const setStatus = (message) => {
                statusEl.textContent = message || '';
            };
            const collectDraft = () => {
                const draft = {};
                rowsEl.querySelectorAll('.page-override-row').forEach((row) => {
                    const widgetKey = row.dataset.widgetKey;
                    const toggle = row.querySelector('.js-page-override-toggle');
                    if (!widgetKey || !toggle || !toggle.checked) return;
                    const regionInput = row.querySelector('.js-page-override-region');
                    const priorityInput = row.querySelector('.js-page-override-priority');
                    const enabledInput = row.querySelector('.js-page-override-enabled');
                    draft[widgetKey] = {
                        widget_key: widgetKey,
                        region: regionInput ? regionInput.value : '',
                        priority: priorityInput ? String(priorityInput.value).trim() : '',
                        is_enabled: enabledInput ? enabledInput.checked !== false : true,
                    };
                });
                this.pageOverrideState.draft = draft;
            };
            const applyRowEnabledState = (row) => {
                const toggle = row.querySelector('.js-page-override-toggle');
                const on = !!(toggle && toggle.checked);
                row.classList.toggle('is-inherited', !on);
                row.querySelectorAll('.js-page-override-region, .js-page-override-priority, .js-page-override-enabled')
                    .forEach((input) => {
                        input.disabled = !on;
                    });
            };
            const renderOverrideRows = () => {
                const draft = this.pageOverrideState.draft || {};
                rowsEl.innerHTML = orderedKeys.map((widgetKey) => {
                    const override = draft[widgetKey] || null;
                    const defaults = {...(knownDefaults[widgetKey] || {}), ...(widgetsObj[widgetKey] || {})};
                    const region = (override && override.region) || defaults.region || '';
                    const priority = override && override.priority !== undefined && override.priority !== ''
                        ? override.priority
                        : (defaults.priority !== undefined && defaults.priority !== null ? defaults.priority : '');
                    const enabled = override ? override.is_enabled !== false : true;
                    return `<div class="page-override-row ${override ? '' : 'is-inherited'}" data-widget-key="${escapePageHtml(widgetKey)}">`
                        + `<label class="pill-checkbox-label"><input type="checkbox" class="js-page-override-toggle" ${override ? 'checked' : ''}><span>Override</span></label>`
                        + `<strong>${escapePageHtml(widgetLabel(widgetKey))}</strong>`
                        + `<label class="pill-checkbox-label"><input type="checkbox" class="js-page-override-enabled" ${enabled ? 'checked' : ''} ${override ? '' : 'disabled'}><span>Enabled</span></label>`
                        + regionSelectHtml(region, 'js-page-override-region', 'Keep article-type region')
                        + `<input type="number" class="input-field js-page-override-priority" style="padding:0.35rem;" value="${escapePageHtml(priority)}" placeholder="Priority" ${override ? '' : 'disabled'}>`
                        + '</div>';
                }).join('');
                rowsEl.querySelectorAll('.page-override-row').forEach((row) => {
                    applyRowEnabledState(row);
                    const toggle = row.querySelector('.js-page-override-toggle');
                    if (toggle) {
                        toggle.addEventListener('change', () => {
                            applyRowEnabledState(row);
                            collectDraft();
                        });
                    }
                    row.querySelectorAll('.js-page-override-region, .js-page-override-priority, .js-page-override-enabled')
                        .forEach((input) => input.addEventListener('change', () => {
                            const toggle = row.querySelector('.js-page-override-toggle');
                            if (toggle && !toggle.checked) {
                                toggle.checked = true;
                                applyRowEnabledState(row);
                            }
                            collectDraft();
                        }));
                    const priorityInput = row.querySelector('.js-page-override-priority');
                    if (priorityInput) priorityInput.addEventListener('input', collectDraft);
                });
            };
            const showSelectedPage = () => {
                const selected = this.pageOverrideState.selected;
                editorEl.hidden = !selected;
                const selectedWrap = pageOverridePanel.querySelector('.page-override-selected');
                if (selectedWrap) {
                    selectedWrap.classList.toggle('is-active', !!selected);
                }
                if (!selected) {
                    selectedLabel.textContent = '';
                    return;
                }
                selectedLabel.innerHTML = `<strong>${escapePageHtml(selected.title)}</strong>`
                    + `<div style="font-size:0.75rem;color:var(--text-muted);">id ${escapePageHtml(selected.id)} · /${escapePageHtml(selected.custom_route || selected.slug)} · ${escapePageHtml(selected.page_type)} · ${escapePageHtml(selected.status)}</div>`;
                renderOverrideRows();
            };
            const selectOverridePage = async (page) => {
                this.pageOverrideState.selected = page;
                setStatus('Loading overrides...');
                showSelectedPage();
                if (this.pageOverrideState.lastSearchPages.length) {
                    renderPageResults(this.pageOverrideState.lastSearchPages);
                }
                try {
                    const response = await fetch(`/api/v1/${this.site_id}/content/${page.id}/widgets`, {headers: pageOverrideHeaders});
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.error || payload.message || 'Could not load page overrides.');
                    }
                    const draft = {};
                    (payload.widgets || []).forEach((row) => {
                        if (!row || !row.widget_key) return;
                        draft[row.widget_key] = {
                            widget_key: row.widget_key,
                            region: row.region || '',
                            priority: row.priority !== undefined && row.priority !== null ? row.priority : '',
                            is_enabled: row.is_enabled !== false,
                        };
                    });
                    this.pageOverrideState.draft = draft;
                    renderOverrideRows();
                    setStatus((payload.widgets || []).length
                        ? `${payload.widgets.length} override(s) on this page.`
                        : 'No page overrides yet. Tick Override to change a widget.');
                } catch (error) {
                    this.pageOverrideState.draft = {};
                    renderOverrideRows();
                    setStatus(error.message || 'Could not load page overrides.');
                }
            };
            const renderPageResults = (pages) => {
                if (!pages.length) {
                    resultsEl.textContent = '';
                    const empty = document.createElement('p');
                    empty.style.cssText = 'font-size:0.75rem;color:var(--text-muted);margin:0;';
                    empty.textContent = 'No matching pages.';
                    resultsEl.appendChild(empty);
                    return;
                }
                resultsEl.replaceChildren();
                pages.forEach((page) => {
                    const button = document.createElement('button');
                    const isSelected = this.pageOverrideState.selected && this.pageOverrideState.selected.id === page.id;
                    button.type = 'button';
                    button.className = 'page-override-result' + (isSelected ? ' is-selected' : '');
                    button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
                    button.innerHTML = `<strong>${escapePageHtml(page.title)}</strong>`
                        + `<span>id ${escapePageHtml(page.id)} · /${escapePageHtml(page.custom_route || page.slug)} · ${escapePageHtml(page.page_type)} · ${escapePageHtml(page.status)}</span>`;
                    button.addEventListener('click', () => selectOverridePage(page));
                    resultsEl.appendChild(button);
                });
            };
            const searchOverridePages = async (term) => {
                resultsEl.textContent = '';
                const searching = document.createElement('p');
                searching.style.cssText = 'font-size:0.75rem;color:var(--text-muted);margin:0;';
                searching.textContent = 'Searching...';
                resultsEl.appendChild(searching);
                try {
                    const params = new URLSearchParams({q: term, per_page: '20'});
                    const response = await fetch(`/api/v1/${this.site_id}/content/pages?${params}`, {headers: pageOverrideHeaders});
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.error || payload.message || 'Page search failed.');
                    }
                    this.pageOverrideState.lastSearchTerm = term;
                    this.pageOverrideState.lastSearchPages = payload.pages || [];
                    renderPageResults(this.pageOverrideState.lastSearchPages);
                } catch (error) {
                    resultsEl.textContent = '';
                    const failed = document.createElement('p');
                    failed.style.cssText = 'font-size:0.75rem;color:var(--danger-color);margin:0;';
                    failed.textContent = error.message || 'Page search failed.';
                    resultsEl.appendChild(failed);
                }
            };
            const savePageOverrides = async (widgets) => {
                const selected = this.pageOverrideState.selected;
                if (!selected) return;
                setStatus('Saving...');
                try {
                    const response = await fetch(`/api/v1/${this.site_id}/content/${selected.id}/widgets`, {
                        method: 'PUT',
                        headers: pageOverrideHeaders,
                        credentials: 'same-origin',
                        body: JSON.stringify({widgets}),
                    });
                    const payload = await response.json();
                    if (!response.ok) {
                        throw new Error(payload.error || payload.message || 'Could not save page overrides.');
                    }
                    const draft = {};
                    (payload.widgets || []).forEach((row) => {
                        if (!row || !row.widget_key) return;
                        draft[row.widget_key] = {
                            widget_key: row.widget_key,
                            region: row.region || '',
                            priority: row.priority !== undefined && row.priority !== null ? row.priority : '',
                            is_enabled: row.is_enabled !== false,
                        };
                    });
                    this.pageOverrideState.draft = draft;
                    renderOverrideRows();
                    setStatus(`Saved ${widgets.length} override(s) for page ${selected.id}.`);
                } catch (error) {
                    setStatus(error.message || 'Could not save page overrides.');
                }
            };

            searchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => searchOverridePages(searchInput.value.trim()), 250);
            });
            const clearBtn = pageOverridePanel.querySelector('.js-page-override-clear');
            if (clearBtn) {
                clearBtn.addEventListener('click', () => {
                    this.pageOverrideState = {selected: null, draft: {}, lastSearchTerm: '', lastSearchPages: []};
                    searchInput.value = '';
                    resultsEl.replaceChildren();
                    showSelectedPage();
                    setStatus('');
                });
            }
            const saveBtn = pageOverridePanel.querySelector('.js-page-override-save');
            if (saveBtn) {
                saveBtn.addEventListener('click', () => {
                    collectDraft();
                    const widgets = Object.values(this.pageOverrideState.draft).map((row) => ({
                        widget_key: row.widget_key,
                        region: row.region || null,
                        priority: row.priority === '' ? null : Number(row.priority),
                        is_enabled: row.is_enabled === true,
                    }));
                    savePageOverrides(widgets);
                });
            }
            const resetBtn = pageOverridePanel.querySelector('.js-page-override-reset');
            if (resetBtn) {
                resetBtn.addEventListener('click', () => {
                    if (!this.pageOverrideState.selected) return;
                    if (!confirm('Remove every widget override from this page?')) return;
                    savePageOverrides([]);
                });
            }
            this.pageOverrideUi = {
                refreshRows: renderOverrideRows,
                renderPageResults,
                showSelectedPage,
            };

            if (this.pageOverrideState.selected) {
                searchInput.value = this.pageOverrideState.lastSearchTerm
                    || this.pageOverrideState.selected.title
                    || '';
                showSelectedPage();
                if (this.pageOverrideState.lastSearchPages.length) {
                    renderPageResults(this.pageOverrideState.lastSearchPages);
                } else if (searchInput.value.trim()) {
                    searchOverridePages(searchInput.value.trim());
                }
            }
        }

        buildWidgetsSubForm(container, entry, availablePageTypes) {
            const widgetsObj = entry.value && typeof entry.value === 'object' ? entry.value : {};
            const wrapper = document.createElement('div');
            wrapper.className = 'widgets-dashboard';

            const regionOptions = Array.isArray(this.widgetRegions) && this.widgetRegions.length
                ? this.widgetRegions
                : [
                    {value: 'top', label: 'Top', aliases: ['top', 'header']},
                    {value: 'middle', label: 'Middle', aliases: ['middle', 'after-content']},
                    {value: 'bottom', label: 'Bottom', aliases: ['bottom', 'below-content']},
                    {value: 'sidebar', label: 'Sidebar', aliases: ['sidebar']},
                    {value: 'notices', label: 'Notices', aliases: ['notices']},
                    {value: 'modals', label: 'Modals', aliases: ['modals']},
                ];
            const knownDefaults = this.knownWidgetDefaults || {};
            const settingsSchema = this.widgetSettingsSchema || {};

            const editorRegionValue = (stored) => {
                if (!stored) return '';
                const match = regionOptions.find((option) =>
                    option.value === stored || (option.aliases || []).includes(stored)
                );
                return match ? match.value : stored;
            };

            const regionSelectHtml = (selected, extraClass = '', emptyLabel = 'Same as default') => {
                const current = editorRegionValue(selected);
                const options = regionOptions.map((option) =>
                    `<option value="${option.value}" ${current === option.value ? 'selected' : ''}>${option.label}</option>`
                ).join('');
                return `<select class="input-field ${extraClass}" style="padding:0.35rem;">
                    <option value="">${emptyLabel}</option>
                    ${options}
                </select>`;
            };

            const orderedKeys = [...new Set([
                ...Object.keys(settingsSchema),
                ...Object.keys(knownDefaults),
                ...Object.keys(widgetsObj),
            ])].sort((a, b) => {
                const confA = widgetsObj[a] || knownDefaults[a] || {};
                const confB = widgetsObj[b] || knownDefaults[b] || {};
                const pa = Number(confA.priority ?? 9999);
                const pb = Number(confB.priority ?? 9999);
                if (pa !== pb) return pa - pb;
                return a.localeCompare(b);
            });

            const widgetLabel = (widgetKey) =>
                settingsSchema[widgetKey]?.label
                || (widgetKey === 'adverts' ? 'Ads in the article' : widgetKey);

            const collectSchemaValues = (card, widgetKey) => {
                const values = {};
                const fields = settingsSchema[widgetKey]?.fields || [];
                fields.forEach((field) => {
                    if (field.type === 'number') {
                        const input = card.querySelector(`.js-widget-setting[data-setting-key="${field.key}"]`);
                        const raw = input?.value.trim() ?? '';
                        if (raw !== '') values[field.key] = Number(raw);
                    } else if (field.type === 'text') {
                        const input = card.querySelector(`.js-widget-setting[data-setting-key="${field.key}"]`);
                        const raw = input?.value ?? '';
                        if (raw.trim() !== '') values[field.key] = raw;
                    } else if (field.type === 'choice') {
                        const selected = card.querySelector(`.js-widget-setting[data-setting-key="${field.key}"]:checked`);
                        if (selected?.value) values[field.key] = selected.value;
                    }
                });
                return values;
            };

            const renderSchemaFields = (widgetKey, widgetConf) => {
                const fields = settingsSchema[widgetKey]?.fields || [];
                if (!fields.length) {
                    return '<p style="font-size:0.8rem;color:var(--text-muted);margin:0;">No extra settings for this widget.</p>';
                }

                return fields.map((field) => {
                    const current = widgetConf[field.key] !== undefined ? widgetConf[field.key] : field.default;
                    if (field.type === 'number') {
                        return `
                            <span class="widget-pane-title">${field.label}</span>
                            <input type="number"
                                   class="input-field js-widget-setting"
                                   data-setting-key="${field.key}"
                                   style="padding:0.35rem;"
                                   min="${field.min ?? 1}"
                                   max="${field.max ?? ''}"
                                   value="${current !== undefined && current !== null ? current : ''}"
                                   placeholder="${field.default ?? ''}">
                        `;
                    }
                    if (field.type === 'text') {
                        const escaped = String(current ?? '').replace(/"/g, '&quot;');
                        return `
                            <span class="widget-pane-title">${field.label}</span>
                            <input type="text"
                                   class="input-field js-widget-setting"
                                   data-setting-key="${field.key}"
                                   style="padding:0.35rem;"
                                   value="${escaped}"
                                   placeholder="${field.default ?? ''}">
                        `;
                    }
                    if (field.type === 'choice') {
                        const options = (field.options || []).map((option) => `
                            <label class="pill-checkbox-label">
                                <input type="radio"
                                       class="js-widget-setting"
                                       data-setting-key="${field.key}"
                                       name="widget-setting-${widgetKey}-${field.key}"
                                       value="${option.value}"
                                       ${current === option.value ? 'checked' : ''}>
                                <span>${option.label}</span>
                            </label>
                        `).join('');
                        return `
                            <span class="widget-pane-title">${field.label}</span>
                            <div class="pill-checkbox-group" role="radiogroup" aria-label="${field.label}">${options}</div>
                        `;
                    }
                    return '';
                }).join('');
            };

            const collectPageTypePlacements = (card) => {
                const placements = {};
                card.querySelectorAll('.js-ptype-placement-row').forEach((row) => {
                    const pageType = row.dataset.pageType;
                    const regionVal = row.querySelector('.js-ptype-region')?.value ?? '';
                    if (!pageType || !regionVal) return;
                    const placement = {region: regionVal};
                    if (regionVal === 'bottom') {
                        placement.priority = 900;
                    } else if (regionVal === 'middle') {
                        placement.priority = 100;
                    } else if (regionVal === 'top') {
                        placement.priority = 10;
                    }
                    placements[pageType] = placement;
                });
                return placements;
            };

            const renderPageTypePlacementRows = (container, pageTypes, placements, defaultRegion) => {
                if (!container) return;
                const types = pageTypes.length ? pageTypes : [];
                if (!types.length) {
                    container.innerHTML = '<p style="font-size:0.75rem;color:var(--text-muted);margin:0;">Select page types to set placement per article type.</p>';
                    return;
                }
                container.innerHTML = types.map((pageType) => {
                    const typed = placements[pageType] && typeof placements[pageType] === 'object'
                        ? placements[pageType]
                        : {};
                    const selected = typed.region || '';
                    return `<div class="widget-page-type-placement-row js-ptype-placement-row" data-page-type="${pageType}">
                        <span>${pageType}</span>
                        ${regionSelectHtml(selected, 'js-ptype-region')}
                    </div>`;
                }).join('');
                container.querySelectorAll('.js-ptype-region').forEach((select) => {
                    select.addEventListener('change', () => {
                        const card = container.closest('.widget-config-card');
                        if (card) syncWidgetCard(card);
                    });
                });
            };

            const checkedPageTypes = (card) => {
                const selectedTypes = [];
                card.querySelectorAll('.js-ptype-box:checked').forEach(box => selectedTypes.push(box.value));
                return selectedTypes;
            };

            const renumberPriorities = () => {
                const cards = [...wrapper.querySelectorAll('.widget-config-card')];
                cards.forEach((card, index) => {
                    const key = card.dataset.widgetKey;
                    if (!widgetsObj[key]) {
                        widgetsObj[key] = {
                            page_types: [...(knownDefaults[key]?.page_types || [])],
                            ...collectSchemaValues(card, key),
                        };
                    }
                    widgetsObj[key].priority = (index + 1) * 10;
                    const priorityInput = card.querySelector('.js-widget-priority');
                    if (priorityInput) priorityInput.value = String(widgetsObj[key].priority);
                });
                this.handleFormValueChange(entry.id, widgetsObj);
            };

            const syncWidgetCard = (card) => {
                const widgetKey = card.dataset.widgetKey;
                const selectedTypes = checkedPageTypes(card);
                const regionVal = card.querySelector('.js-widget-region')?.value ?? '';
                const priorityVal = card.querySelector('.js-widget-priority')?.value.trim() ?? '';
                const schemaValues = collectSchemaValues(card, widgetKey);
                const pageTypePlacements = collectPageTypePlacements(card);
                const isCatalogWidget = Object.prototype.hasOwnProperty.call(knownDefaults, widgetKey)
                    || Object.prototype.hasOwnProperty.call(settingsSchema, widgetKey);
                const defaultPageTypes = [...(knownDefaults[widgetKey]?.page_types || [])];

                const hasSchemaValues = Object.keys(schemaValues).length > 0;
                const hasPageTypePlacements = Object.keys(pageTypePlacements).length > 0;
                if (selectedTypes.length === 0 && !regionVal && priorityVal === '' && !hasSchemaValues && !hasPageTypePlacements) {
                    if (isCatalogWidget) {
                        widgetsObj[widgetKey] = {page_types: []};
                        this.handleFormValueChange(entry.id, widgetsObj);
                        return;
                    }
                    delete widgetsObj[widgetKey];
                    this.handleFormValueChange(entry.id, widgetsObj);
                    this.renderVisualFormOnly();
                    return;
                }

                // Editing settings with no page-type boxes checked must not wipe eligibility.
                // Use Disable Widget to turn a catalog widget off.
                const pageTypesToSave = selectedTypes.length > 0
                    ? selectedTypes
                    : defaultPageTypes;

                widgetsObj[widgetKey] = {
                    page_types: pageTypesToSave,
                    ...schemaValues,
                };
                if (regionVal) widgetsObj[widgetKey].region = regionVal;
                if (priorityVal !== '') widgetsObj[widgetKey].priority = Number(priorityVal);
                if (hasPageTypePlacements) {
                    widgetsObj[widgetKey].page_type_placements = pageTypePlacements;
                }
                this.handleFormValueChange(entry.id, widgetsObj);
            };

            orderedKeys.forEach(widgetKey => {
                const storedConf = (widgetsObj[widgetKey] && typeof widgetsObj[widgetKey] === 'object')
                    ? widgetsObj[widgetKey]
                    : null;
                const defaultsConf = knownDefaults[widgetKey] || {};
                // Merge file/catalog defaults under stored config so editing a limit/title
                // does not present empty page_types (which would persist as a disable).
                const widgetConf = {...defaultsConf, ...(storedConf || {})};
                if (storedConf && !Array.isArray(storedConf.page_types)) {
                    widgetConf.page_types = [...(defaultsConf.page_types || [])];
                }
                const currentCheckedTypes = Array.isArray(widgetConf.page_types) ? widgetConf.page_types : [];
                const currentPlacements = widgetConf.page_type_placements && typeof widgetConf.page_type_placements === 'object'
                    ? widgetConf.page_type_placements
                    : {};
                const meta = settingsSchema[widgetKey] || {};
                const card = document.createElement('div');
                card.className = 'widget-config-card';
                card.dataset.widgetKey = widgetKey;
                card.draggable = true;

                let pillGroupHtml = `<div class="pill-checkbox-group">`;
                availablePageTypes.forEach(pType => {
                    pillGroupHtml += `<label class="pill-checkbox-label"><input type="checkbox" class="js-ptype-box" value="${pType}" ${currentCheckedTypes.includes(pType) ? 'checked' : ''}><span>${pType}</span></label>`;
                });
                pillGroupHtml += `</div>`;

                const descriptionHtml = meta.description
                    ? `<p style="font-size:0.8rem;color:var(--text-muted);margin:0 0 0.75rem;">${meta.description}</p>`
                    : '';

                card.innerHTML = `
                    <div class="widget-card-meta">
                        <span class="widget-card-identity" title="Drag to reorder">☰ ${widgetLabel(widgetKey)}</span>
                        <div style="display:flex;gap:0.35rem;">
                            <button type="button" class="btn btn-secondary btn-xs js-move-up">↑</button>
                            <button type="button" class="btn btn-secondary btn-xs js-move-down">↓</button>
                            <button class="btn btn-danger btn-xs js-del-widget">Disable Widget</button>
                        </div>
                    </div>
                    ${descriptionHtml}
                    <div class="widget-card-body-grid">
                        <div class="widget-scopes-pane">
                            <span class="widget-pane-title">Show on page types</span>
                            ${pillGroupHtml}
                            <span class="widget-pane-title" style="margin-top:0.75rem;">Placement by page type</span>
                            <div class="widget-page-type-placements js-ptype-placements"></div>
                        </div>
                        <div class="widget-limit-pane">
                            <span class="widget-pane-title">Widget settings</span>
                            ${renderSchemaFields(widgetKey, widgetConf)}
                            <span class="widget-pane-title" style="margin-top:0.75rem;">Default placement</span>
                            ${regionSelectHtml(widgetConf.region, 'js-widget-region', 'Catalog default')}
                            <span class="widget-pane-title" style="margin-top:0.75rem;">Priority (lower = earlier)</span>
                            <input type="number" class="input-field js-widget-priority" style="padding:0.35rem;" value="${widgetConf.priority !== undefined ? widgetConf.priority : ''}" placeholder="Catalog default">
                        </div>
                    </div>
                `;

                renderPageTypePlacementRows(
                    card.querySelector('.js-ptype-placements'),
                    currentCheckedTypes,
                    currentPlacements,
                    widgetConf.region,
                );

                card.querySelectorAll('.js-ptype-box').forEach(box => box.addEventListener('change', () => {
                    const existing = collectPageTypePlacements(card);
                    renderPageTypePlacementRows(
                        card.querySelector('.js-ptype-placements'),
                        checkedPageTypes(card),
                        existing,
                        card.querySelector('.js-widget-region')?.value ?? '',
                    );
                    syncWidgetCard(card);
                }));
                card.querySelectorAll('.js-widget-setting').forEach(input => {
                    input.addEventListener('change', () => syncWidgetCard(card));
                    input.addEventListener('input', () => syncWidgetCard(card));
                });
                card.querySelector('.js-widget-region').addEventListener('change', () => syncWidgetCard(card));
                card.querySelector('.js-widget-priority').addEventListener('input', () => syncWidgetCard(card));
                card.querySelector('.js-del-widget').addEventListener('click', () => {
                    if (Object.prototype.hasOwnProperty.call(knownDefaults, widgetKey)
                        || Object.prototype.hasOwnProperty.call(settingsSchema, widgetKey)) {
                        widgetsObj[widgetKey] = {page_types: []};
                    } else {
                        delete widgetsObj[widgetKey];
                    }
                    this.handleFormValueChange(entry.id, widgetsObj);
                    this.renderVisualFormOnly();
                });
                card.querySelector('.js-move-up').addEventListener('click', () => {
                    const prev = card.previousElementSibling;
                    if (prev && prev.classList.contains('widget-config-card')) {
                        wrapper.insertBefore(card, prev);
                        renumberPriorities();
                    }
                });
                card.querySelector('.js-move-down').addEventListener('click', () => {
                    const next = card.nextElementSibling;
                    if (next && next.classList.contains('widget-config-card')) {
                        wrapper.insertBefore(next, card);
                        renumberPriorities();
                    }
                });

                card.addEventListener('dragstart', (event) => {
                    card.classList.add('dragging');
                    event.dataTransfer.setData('text/plain', widgetKey);
                });
                card.addEventListener('dragend', () => {
                    card.classList.remove('dragging');
                    renumberPriorities();
                });
                card.addEventListener('dragover', (event) => {
                    event.preventDefault();
                    const dragging = wrapper.querySelector('.widget-config-card.dragging');
                    if (!dragging || dragging === card) return;
                    const rect = card.getBoundingClientRect();
                    const after = (event.clientY - rect.top) > (rect.height / 2);
                    wrapper.insertBefore(dragging, after ? card.nextSibling : card);
                });

                wrapper.appendChild(card);
            });

            const appendWidgetBtn = document.createElement('button');
            appendWidgetBtn.className = 'btn btn-secondary btn-xs';
            appendWidgetBtn.innerHTML = `<span>+ Map New Widget Instance</span>`;
            appendWidgetBtn.addEventListener('click', () => {
                const wName = prompt("Enter target unique widget configuration instance identifier:");
                if (!wName) return;
                const nextPriority = (Object.keys(widgetsObj).length + 1) * 10;
                widgetsObj[wName] = {page_types: [], priority: nextPriority};
                this.handleFormValueChange(entry.id, widgetsObj);
                this.renderVisualFormOnly();
            });
            wrapper.appendChild(appendWidgetBtn);
            container.appendChild(wrapper);
        }

        buildDefaultSubForm(container, entry) {
            const input = document.createElement('input');
            input.className = 'input-field';
            input.value = typeof entry.value === 'object' ? JSON.stringify(entry.value) : String(entry.value);
            input.addEventListener('input', (e) => this.handleFormValueChange(entry.id, e.target.value));
            container.appendChild(input);
        }

        // ---------------------------------------------------------------------
        // TEXT RECONCILIATION LAYER
        // ---------------------------------------------------------------------

        serializeModelToText(model) {
            if (!model || typeof model.toArray !== 'function') return '{}';
            return JSON.stringify(model.toArray(), null, 4);
        }

        synchronizeFromTextToModel() {
            try {
                const parsedObject = JSON.parse(this.rawTextValue);
                this.syntaxError = null;
                const looksLikeEntry = (item) => item
                    && typeof item === 'object'
                    && Object.prototype.hasOwnProperty.call(item, 'key')
                    && Object.prototype.hasOwnProperty.call(item, 'value');

                let nextModel = null;
                let rewriteCanonicalText = false;

                if (Array.isArray(parsedObject) && parsedObject.length && parsedObject.every(looksLikeEntry)) {
                    nextModel = ConfigModel.fromSerializableArray(parsedObject);
                    rewriteCanonicalText = true;
                } else if (
                    parsedObject
                    && typeof parsedObject === 'object'
                    && !Array.isArray(parsedObject)
                    && Array.isArray(parsedObject.entries)
                    && parsedObject.entries.every(looksLikeEntry)
                    && ('fingerprint' in parsedObject || 'success' in parsedObject || 'type' in parsedObject)
                ) {
                    nextModel = ConfigModel.fromSerializableArray(parsedObject.entries);
                    if (parsedObject.fingerprint) {
                        this.fingerprint = parsedObject.fingerprint;
                        this.dom.fingerprintDisplay.innerText = this.fingerprint;
                    }
                    rewriteCanonicalText = true;
                } else {
                    const entriesList = Object.keys(parsedObject).map(k => [k, parsedObject[k]]);
                    const currentEntries = this.model.all();
                    nextModel = new ConfigModel(entriesList.map((pair, idx) => (
                        new ConfigEntry(pair[0], pair[1], currentEntries[idx] ? currentEntries[idx].id : null)
                    )));
                }

                const errors = ConfigValidator.validate(nextModel);
                this.validationErrors = errors;
                if (errors.length === 0) {
                    this.model = nextModel;
                    if (rewriteCanonicalText) {
                        this.rawTextValue = this.serializeModelToText(this.model);
                        this.dom.jsonTextarea.value = this.rawTextValue;
                    }
                }
                this.dom.jsonStatusBadge.innerText = errors.length > 0 ? "Invalid Config" : "Synced";
            } catch (err) {
                this.syntaxError = err.message;
                this.validationErrors = [];
                this.dom.jsonStatusBadge.innerText = "Syntax Error";
            }
            this.renderErrorsOnly();
            this.renderVisualFormOnly();
        }

        handleConflictInterception(apiConflictResponse) {
            this.dom.conflictTableBody.innerHTML = '';
            if (!apiConflictResponse.diff) return;

            apiConflictResponse.diff.forEach(diffItem => {
                const key = diffItem.key;
                const isConflict = diffItem.status === 'Conflict';
                const tr = document.createElement('tr');
                tr.setAttribute('data-key', key);
                tr.innerHTML = `
                    <td><strong>${key}</strong></td>
                    <td><pre style="font-size:0.75rem; background:#f1f5f9; padding:0.4rem; max-height:120px; overflow-y:auto;">${diffItem.latestExists ? JSON.stringify(diffItem.latestValue, null, 2) : '[DELETED]'}</pre></td>
                    <td><pre style="font-size:0.75rem; background:#f8fafc; padding:0.4rem; max-height:120px; overflow-y:auto;">${diffItem.mineExists ? JSON.stringify(diffItem.mineValue, null, 2) : '[DELETED]'}</pre></td>
                    <td><div class="resolution-options">${isConflict ? `<label><input type="radio" name="res-${key}" value="keep_mine" checked> Keep Mine</label><label><input type="radio" name="res-${key}" value="keep_theirs"> Keep Theirs</label><label><input type="radio" name="res-${key}" value="edited"> Override</label><textarea class="input-field cell-textarea" id="custom-val-${key}" style="display:none;">${diffItem.mineExists ? JSON.stringify(diffItem.mineValue, null, 4) : ''}</textarea>` : `<span>Status: ${diffItem.status}</span>`}</div></td>
                `;
                this.dom.conflictTableBody.appendChild(tr);
                if (isConflict) {
                    const radios = tr.querySelectorAll(`input[name="res-${key}"]`);
                    const customField = tr.querySelector(`#custom-val-${key}`);
                    radios.forEach(radio => radio.addEventListener('change', (e) => {
                        customField.style.display = e.target.value === 'edited' ? 'block' : 'none';
                    }));
                }
            });
            this.dom.conflictOverlay.classList.add('visible');
        }

        runLocalValidation() {
            this.syntaxError = null;
            this.validationErrors = ConfigValidator.validate(this.model);
        }

        render() {
            this.dom.jsonTextarea.value = this.rawTextValue;
            this.renderVisualFormOnly();
            this.renderErrorsOnly();
        }

        renderErrorsOnly() {
            this.dom.syntaxBanner.classList.toggle('visible', !!this.syntaxError);
            this.dom.syntaxBanner.innerText = this.syntaxError ? `Syntax Error: ${this.syntaxError}` : '';
            const validationActive = this.validationErrors.length > 0;
            this.dom.validationBanner.classList.toggle('visible', validationActive);
            this.dom.formValidationBanner.classList.toggle('visible', validationActive);
            if (validationActive) {
                const msgs = [...new Set(this.validationErrors.map(e => e.message))];
                this.dom.formValidationBanner.innerHTML = `<strong>Active Schema Warnings:</strong><ul>${msgs.map(m => `<li>${m}</li>`).join('')}</ul>`;
            }
        }

        clearBanners() {
            this.dom.syntaxBanner.classList.remove('visible');
            this.dom.validationBanner.classList.remove('visible');
            this.dom.successBanner.classList.remove('visible');
            this.dom.formValidationBanner.classList.remove('visible');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.ConfigAppInstance = new ConfigEditorApp(
            '<?php echo htmlspecialchars((string) $initialSiteId, ENT_QUOTES, 'UTF-8'); ?>',
            '<?php echo htmlspecialchars((string) $configType, ENT_QUOTES, 'UTF-8'); ?>',
            <?php echo json_encode($knownWidgetDefaults, JSON_UNESCAPED_SLASHES); ?>,
            <?php echo json_encode($knownWidgetSettingsSchema, JSON_UNESCAPED_SLASHES); ?>,
            <?php echo json_encode($knownWidgetRegions, JSON_UNESCAPED_SLASHES); ?>,
            <?php echo json_encode($csrfToken, JSON_UNESCAPED_SLASHES); ?>
        );
    });
</script>
</body>
</html>