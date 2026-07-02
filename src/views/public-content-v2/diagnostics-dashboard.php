<?php
$siteSlug = (string) ($siteSlug ?? '');
$skips = $skips ?? [];
$skipCountsByWidget = $skipCountsByWidget ?? [];
$skipCountsByReason = $skipCountsByReason ?? [];
$parityRecords = $parityRecords ?? [];
$parityMismatches = $parityMismatches ?? [];
$parityFailures = $parityFailures ?? [];

$parityMatchedCount = count(array_filter(
    $parityRecords,
    static fn(array $r): bool => ($r['status'] ?? null) === 'matched',
));

$esc = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Public content v2 diagnostics — <?= $esc($siteSlug) ?></title>
    <style>
        :root {
            --bg: #0f1115;
            --panel: #171a21;
            --panel-border: #262b36;
            --text: #e6e8ec;
            --text-dim: #9aa2b1;
            --accent: #5b8cff;
            --ok: #3ecf8e;
            --warn: #f5a623;
            --bad: #f45b69;
            --radius: 10px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.5;
        }
        header.page-head {
            padding: 24px 32px;
            border-bottom: 1px solid var(--panel-border);
        }
        header.page-head h1 {
            margin: 0 0 4px;
            font-size: 20px;
            font-weight: 600;
        }
        header.page-head p {
            margin: 0;
            color: var(--text-dim);
        }
        main {
            padding: 24px 32px 64px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .summary-card {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            padding: 16px 18px;
        }
        .summary-card .value {
            font-size: 28px;
            font-weight: 700;
            line-height: 1.1;
        }
        .summary-card .label {
            color: var(--text-dim);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-top: 4px;
        }
        .summary-card.ok .value { color: var(--ok); }
        .summary-card.warn .value { color: var(--warn); }
        .summary-card.bad .value { color: var(--bad); }

        section {
            background: var(--panel);
            border: 1px solid var(--panel-border);
            border-radius: var(--radius);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        section h2 {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 14px;
        }
        .two-col {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            align-items: start; /* Prevents the shorter table from stretching vertically */
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            text-align: left;
            padding: 8px 10px;
            border-bottom: 1px solid var(--panel-border);
            font-size: 13px;
        }
        th {
            color: var(--text-dim);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.03em;
        }
        tbody tr:hover { background: rgba(255,255,255,0.02); }
        .empty-row td {
            color: var(--text-dim);
            font-style: italic;
            text-align: center;
            padding: 24px;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge.mismatched { background: rgba(244,91,105,0.15); color: var(--bad); }
        .badge.failed { background: rgba(245,166,35,0.15); color: var(--warn); }
        .badge.matched { background: rgba(62,207,142,0.15); color: var(--ok); }

        .table-card {
            background: rgba(255, 255, 255, 0.015);
            border: 1px solid rgba(255, 255, 255, 0.04);
            border-radius: 6px;
            padding: 16px;
        }

        .table-card h3 {
            margin: 0 0 12px;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        details {
            border: 1px solid var(--panel-border);
            border-radius: 8px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        details summary {
            padding: 12px 14px;
            cursor: pointer;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        details summary:hover { background: rgba(255,255,255,0.03); }
        details pre {
            margin: 0;
            padding: 14px;
            background: #0b0d12;
            border-top: 1px solid var(--panel-border);
            overflow-x: auto;
            font-size: 12px;
            color: var(--text-dim);
            white-space: pre-wrap;
            word-break: break-word;
        }
    </style>
</head>
<body>

<header class="page-head">
    <h1>Public content v2 diagnostics</h1>
    <p><?= $esc($siteSlug) ?></p>
</header>

<main>
    <div class="summary-grid">
        <div class="summary-card">
            <div class="value"><?= count($skips) ?></div>
            <div class="label">Widget skips</div>
        </div>
        <div class="summary-card ok">
            <div class="value"><?= $parityMatchedCount ?></div>
            <div class="label">Parity matched</div>
        </div>
        <div class="summary-card <?= $parityMismatches === [] ? 'ok' : 'bad' ?>">
            <div class="value"><?= count($parityMismatches) ?></div>
            <div class="label">Parity mismatched</div>
        </div>
        <div class="summary-card <?= $parityFailures === [] ? 'ok' : 'warn' ?>">
            <div class="value"><?= count($parityFailures) ?></div>
            <div class="label">Parity failed</div>
        </div>
    </div>

    <section>
        <h2>Widget skips breakdown</h2>
        <div class="two-col">

            <div class="table-card">
                <h3>By Reason</h3>
                <table>
                    <thead><tr><th>Reason</th><th>Count</th></tr></thead>
                    <tbody>
                    <?php if ($skipCountsByReason === []): ?>
                        <tr class="empty-row"><td colspan="2">No skips recorded</td></tr>
                    <?php else: ?>
                        <?php foreach ($skipCountsByReason as $reason => $count): ?>
                            <tr>
                                <td><?= $esc($reason) ?></td>
                                <td><?= (int) $count ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="table-card">
                <h3>By Widget</h3>
                <table>
                    <thead><tr><th>Widget</th><th>Count</th></tr></thead>
                    <tbody>
                    <?php if ($skipCountsByWidget === []): ?>
                        <tr class="empty-row"><td colspan="2">No skips recorded</td></tr>
                    <?php else: ?>
                        <?php foreach ($skipCountsByWidget as $widget => $count): ?>
                            <tr>
                                <td><?= $esc($widget) ?></td>
                                <td><?= (int) $count ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </section>

    <section>
        <h2>Recent skips</h2>
        <table>
            <thead>
            <tr><th>Time</th><th>Widget</th><th>Reason</th><th>Page</th></tr>
            </thead>
            <tbody>
            <?php if ($skips === []): ?>
                <tr class="empty-row"><td colspan="4">No skips recorded</td></tr>
            <?php else: ?>
                <?php foreach ($skips as $skip): ?>
                    <tr>
                        <td><?= $esc($skip['recorded_at'] ?? '') ?></td>
                        <td><?= $esc($skip['widget'] ?? '') ?></td>
                        <td><?= $esc($skip['reason'] ?? '') ?></td>
                        <td><?= (int) ($skip['page_id'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Parity failures <span class="badge failed">threw</span></h2>
        <table>
            <thead><tr><th>Time</th><th>Slug</th><th>Error</th></tr></thead>
            <tbody>
            <?php if ($parityFailures === []): ?>
                <tr class="empty-row"><td colspan="3">No failures recorded</td></tr>
            <?php else: ?>
                <?php foreach ($parityFailures as $failure): ?>
                    <tr>
                        <td><?= $esc($failure['recorded_at'] ?? '') ?></td>
                        <td><?= $esc($failure['slug'] ?? '') ?></td>
                        <td><?= $esc($failure['error']['message'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section>
        <h2>Parity mismatches <span class="badge mismatched">v1 vs v2</span></h2>
        <?php if ($parityMismatches === []): ?>
            <p style="color:var(--text-dim);">No mismatches recorded</p>
        <?php else: ?>
            <?php foreach ($parityMismatches as $mismatch): ?>
                <details>
                    <summary>
                        <span class="badge mismatched"><?= (int) ($mismatch['difference_count'] ?? 0) ?> fields</span>
                        <?= $esc($mismatch['slug'] ?? '') ?>
                        <span style="color:var(--text-dim);">— <?= $esc($mismatch['recorded_at'] ?? '') ?></span>
                    </summary>
                    <pre><?= $esc(json_encode($mismatch['differences'] ?? [], JSON_PRETTY_PRINT)) ?></pre>
                </details>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>

</body>
</html>