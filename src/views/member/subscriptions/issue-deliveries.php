<?php
/**
 * @var \App\Models\Member $member
 * @var \App\Models\Site $site
 * @var \App\Models\Subscription $subscription
 * @var \App\Framework\Support\Collection $upcomingDeliveries
 * @var \App\Framework\Support\Collection $pastDeliveries
 */

// Combine all deliveries for calendar view
$allDeliveries = $upcomingDeliveries->merge($pastDeliveries);

// Group deliveries by month
$deliveriesByMonth = [];
foreach ($allDeliveries as $delivery) {
    $monthKey = $delivery->estimated_delivery_date->format('Y-m');
    if (!isset($deliveriesByMonth[$monthKey])) {
        $deliveriesByMonth[$monthKey] = [];
    }
    $deliveriesByMonth[$monthKey][] = $delivery;
}

// Get current month or first month with deliveries
$currentMonth = isset($_GET['month']) ? $_GET['month'] : (count($deliveriesByMonth) > 0 ? array_key_first($deliveriesByMonth) : date('Y-m'));
$currentDate = new \DateTime($currentMonth . '-01');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue Delivery Schedule - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #2c3e50;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 32px;
            border-radius: 20px;
            margin-bottom: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .header p {
            color: #64748b;
            font-size: 16px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 16px;
        }

        .subscription-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            margin-top: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 13px;
            color: #64748b;
            font-weight: 600;
        }

        .info-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 700;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 32px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .calendar-nav {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .nav-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
        }

        .nav-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .current-month {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
        }

        .view-toggle {
            display: flex;
            gap: 8px;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
        }

        .view-toggle button {
            padding: 8px 16px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
        }

        .view-toggle button.active {
            background: white;
            color: #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
        }

        .calendar-day-header {
            text-align: center;
            font-weight: 700;
            color: #64748b;
            padding: 16px 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 2px solid #f1f5f9;
            border-radius: 12px;
            padding: 8px;
            min-height: 120px;
            display: flex;
            flex-direction: column;
            position: relative;
            transition: all 0.3s;
            background: white;
        }

        .calendar-day:hover {
            border-color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .calendar-day.other-month {
            opacity: 0.3;
        }

        .calendar-day.today {
            border-color: #667eea;
            background: linear-gradient(135deg, #f0f4ff, #faf5ff);
        }

        .calendar-day.has-delivery {
            border-color: #10b981;
            background: linear-gradient(135deg, #f0fdf4, #ecfdf5);
        }

        .calendar-day.has-delivery.delivered {
            border-color: #94a3b8;
            background: #f8fafc;
        }

        .day-number {
            font-weight: 700;
            font-size: 18px;
            color: #1e293b;
            margin-bottom: 8px;
        }

        .calendar-day.today .day-number {
            color: #667eea;
        }

        .delivery-indicator {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #10b981;
        }

        .delivery-indicator.delivered {
            background: #94a3b8;
        }

        .delivery-info {
            margin-top: auto;
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            line-height: 1.4;
        }

        .delivery-info.scheduled {
            color: #667eea;
        }

        .delivery-info.in-transit {
            color: #f59e0b;
        }

        .delivery-info.delivered {
            color: #10b981;
        }

        .issue-badge {
            background: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 10px;
            font-weight: 700;
            display: inline-block;
            margin-top: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .legend {
            display: flex;
            gap: 24px;
            margin-top: 24px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .legend-dot.scheduled {
            background: #667eea;
        }

        .legend-dot.in-transit {
            background: #f59e0b;
        }

        .legend-dot.delivered {
            background: #10b981;
        }

        .list-view {
            display: none;
        }

        .list-view.active {
            display: block;
        }

        .delivery-list-item {
            padding: 20px;
            margin-bottom: 16px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }

        .delivery-list-item:hover {
            transform: translateX(4px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .delivery-list-item.delivered {
            border-left-color: #10b981;
            opacity: 0.7;
        }

        .delivery-list-item.in-transit {
            border-left-color: #f59e0b;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #64748b;
        }

        .empty-state-icon {
            font-size: 72px;
            margin-bottom: 20px;
            opacity: 0.4;
        }

        @media (max-width: 1024px) {
            .calendar {
                gap: 4px;
            }

            .calendar-day {
                min-height: 100px;
                padding: 6px;
            }

            .day-number {
                font-size: 16px;
            }

            .delivery-info {
                font-size: 10px;
            }
        }

        @media (max-width: 768px) {
            .calendar-header {
                flex-direction: column;
                gap: 16px;
            }

            .calendar {
                gap: 6px;
            }

            .calendar-day {
                min-height: 80px;
            }

            .issue-badge {
                font-size: 9px;
                padding: 2px 6px;
            }
        }
    </style>
</head>
<body>

@include('member._header')

<div class="container" style="margin-top: 40px;">
    <div class="header">
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/member/subscriptions" class="back-link">
            ← Back to Subscriptions
        </a>

        <h1>📅 Issue Delivery Schedule</h1>
        <p>Track your upcoming and past issue deliveries</p>

        <div class="subscription-info">
            <div class="info-item">
                <span class="info-label">Subscription</span>
                <span class="info-value"><?= htmlspecialchars($subscription->plan_name) ?></span>
            </div>
            <div class="info-item">
                <span class="info-label">Delivery Type</span>
                <span class="info-value">📦 Print</span>
            </div>
            <div class="info-item">
                <span class="info-label">Total Deliveries</span>
                <span class="info-value"><?= $allDeliveries->count() ?></span>
            </div>
            <?php if ($subscription->end_date): ?>
                <div class="info-item">
                    <span class="info-label">Subscription End</span>
                    <span class="info-value"><?= $subscription->end_date->format('M d, Y') ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($allDeliveries->isEmpty()): ?>
        <div class="card">
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Deliveries Scheduled</h3>
                <p>Your delivery schedule will appear here once issues are scheduled</p>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="calendar-header">
                <div class="calendar-nav">
                    <?php
                    $prevMonth = (clone $currentDate)->modify('-1 month');
                    $nextMonth = (clone $currentDate)->modify('+1 month');
                    $hasPrevMonth = isset($deliveriesByMonth[$prevMonth->format('Y-m')]) || $prevMonth->format('Y-m') >= date('Y-m', strtotime('-6 months'));
                    $hasNextMonth = isset($deliveriesByMonth[$nextMonth->format('Y-m')]) || $nextMonth->format('Y-m') <= date('Y-m', strtotime('+12 months'));
                    ?>
                    <button class="nav-btn"
                            onclick="changeMonth('<?= $prevMonth->format('Y-m') ?>')" <?= !$hasPrevMonth ? 'disabled' : '' ?>>
                        ← Previous
                    </button>
                    <div class="current-month">
                        <?= $currentDate->format('F Y') ?>
                    </div>
                    <button class="nav-btn"
                            onclick="changeMonth('<?= $nextMonth->format('Y-m') ?>')" <?= !$hasNextMonth ? 'disabled' : '' ?>>
                        Next →
                    </button>
                </div>

                <div class="view-toggle">
                    <button class="active" onclick="toggleView('calendar')">Calendar</button>
                    <button onclick="toggleView('list')">List</button>
                </div>
            </div>

            <!-- Calendar View -->
            <div id="calendarView">
                <div class="calendar">
                    <?php
                    $daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($daysOfWeek as $day): ?>
                        <div class="calendar-day-header"><?= $day ?></div>
                    <?php endforeach;

                    // Get first day of month and number of days
                    $firstDay = (int)$currentDate->format('w');
                    $daysInMonth = (int)$currentDate->format('t');
                    $today = new \DateTime();

                    // Get deliveries for current month
                    $monthDeliveries = $deliveriesByMonth[$currentMonth] ?? [];
                    $deliveriesByDay = [];
                    foreach ($monthDeliveries as $delivery) {
                        $day = (int)$delivery->estimated_delivery_date->format('d');
                        if (!isset($deliveriesByDay[$day])) {
                            $deliveriesByDay[$day] = [];
                        }
                        $deliveriesByDay[$day][] = $delivery;
                    }

                    // Previous month padding
                    $prevMonthDate = (clone $currentDate)->modify('-1 month');
                    $prevMonthDays = (int)$prevMonthDate->format('t');
                    for ($i = $firstDay - 1; $i >= 0; $i--):
                        $day = $prevMonthDays - $i;
                        ?>
                        <div class="calendar-day other-month">
                            <div class="day-number"><?= $day ?></div>
                        </div>
                    <?php endfor;

                    // Current month days
                    for ($day = 1; $day <= $daysInMonth; $day++):
                        $dayDate = new \DateTime($currentMonth . '-' . str_pad($day, 2, '0', STR_PAD_LEFT));
                        $isToday = $dayDate->format('Y-m-d') === $today->format('Y-m-d');
                        $hasDelivery = isset($deliveriesByDay[$day]);
                        $delivery = $hasDelivery ? $deliveriesByDay[$day][0] : null;
                        $status = $delivery ? $delivery->calculateStatus() : '';
                        $isDelivered = $delivery && $status === 'Delivered';
                        ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $hasDelivery ? 'has-delivery' : '' ?> <?= $isDelivered ? 'delivered' : '' ?>">
                            <div class="day-number"><?= $day ?></div>
                            <?php if ($hasDelivery): ?>
                                <div class="delivery-indicator <?= $isDelivered ? 'delivered' : '' ?>"></div>
                                <?php foreach ($deliveriesByDay[$day] as $del): ?>
                                    <div class="delivery-info <?= strtolower(str_replace(' ', '-', $del->calculateStatus())) ?>">
                                        <?= htmlspecialchars($del->issue_title) ?>
                                        <div class="issue-badge">#<?= $del->issue_number ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor;

                    // Next month padding
                    $remainingCells = 42 - ($firstDay + $daysInMonth); // 6 weeks * 7 days
                    for ($day = 1; $day <= $remainingCells; $day++):
                        ?>
                        <div class="calendar-day other-month">
                            <div class="day-number"><?= $day ?></div>
                        </div>
                    <?php endfor; ?>
                </div>

                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-dot scheduled"></div>
                        <span>Scheduled</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot in-transit"></div>
                        <span>In Transit</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot delivered"></div>
                        <span>Delivered</span>
                    </div>
                </div>
            </div>

            <!-- List View -->
            <div id="listView" class="list-view">
                <?php foreach ($monthDeliveries as $delivery):
                    $status = $delivery->calculateStatus();
                    $statusClass = strtolower(str_replace(' ', '-', $status));
                    ?>
                    <div class="delivery-list-item <?= $statusClass ?>">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
                            <div>
                                <div style="font-weight: 700; font-size: 18px; color: #1e293b; margin-bottom: 4px;">
                                    <?= htmlspecialchars($delivery->issue_title) ?>
                                </div>
                                <div style="color: #64748b; font-size: 14px;">
                                    Issue #<?= $delivery->issue_number ?>
                                </div>
                            </div>
                            <span style="background: white; padding: 6px 14px; border-radius: 24px; font-size: 13px; font-weight: 700;">
                                <?= $delivery->getStatusLabel() ?>
                            </span>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-top: 16px;">
                            <?php if ($delivery->on_sale_date): ?>
                                <div>
                                    <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">
                                        ON SALE DATE
                                    </div>
                                    <div style="font-weight: 700; color: #1e293b;"><?= $delivery->on_sale_date->format('M d, Y') ?></div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <div style="font-size: 12px; color: #64748b; font-weight: 600; margin-bottom: 4px;">EST.
                                    DELIVERY
                                </div>
                                <div style="font-weight: 700; color: #1e293b;"><?= $delivery->estimated_delivery_date->format('M d, Y') ?></div>
                            </div>
                        </div>

                        <?php if ($delivery->tracking_info && !empty($delivery->tracking_info['notes'])): ?>
                            <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid #e2e8f0; font-size: 14px; color: #64748b;">
                                <strong>Note:</strong> <?= htmlspecialchars($delivery->tracking_info['notes']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
    function changeMonth(month) {
        window.location.href = window.location.pathname + '?month=' + month;
    }

    function toggleView(view) {
        const calendarView = document.getElementById('calendarView');
        const listView = document.getElementById('listView');
        const buttons = document.querySelectorAll('.view-toggle button');

        buttons.forEach(btn => btn.classList.remove('active'));

        if (view === 'calendar') {
            calendarView.style.display = 'block';
            listView.classList.remove('active');
            buttons[0].classList.add('active');
        } else {
            calendarView.style.display = 'none';
            listView.classList.add('active');
            buttons[1].classList.add('active');
        }
    }
</script>

</body>
</html>