<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'TeleCRM Dashboard';

/**
 * Check whether a table exists for the connected database.
 */
function hh_table_exists(PDO $pdo, string $table): bool
{
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);

        return $stmt->fetchColumn() !== false;
    } catch (Throwable $e) {
        return false;
    }
}

$pdo = hh_db();

$propertyTableExists = hh_table_exists($pdo, 'properties_list');
$leadTableExists = hh_table_exists($pdo, 'all_leads');
$userTableExists = hh_table_exists($pdo, 'users');

$propertyCount = 0;
$propertyStatusData = [];
$propertyTypeData = [];
$propertyStatusDisplay = [];
$upcomingCompletions = 0;

if ($propertyTableExists) {
    try {
        $propertyCount = (int) $pdo->query('SELECT COUNT(*) FROM properties_list')->fetchColumn();

        $propertyStatusData = $pdo->query(
            "SELECT COALESCE(NULLIF(TRIM(project_status), ''), 'Unspecified') AS label, COUNT(*) AS total\n             FROM properties_list\n             GROUP BY label\n             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $propertyTypeData = $pdo->query(
            "SELECT COALESCE(NULLIF(TRIM(property_type), ''), 'Unspecified') AS label, COUNT(*) AS total\n             FROM properties_list\n             GROUP BY label\n             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $propertyStatusDisplay = array_map(
            static fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? 'Unspecified')),
                'total' => (int) ($row['total'] ?? 0),
            ],
            array_slice($propertyStatusData, 0, 5)
        );

        $upcomingCompletions = (int) $pdo->query(
            "SELECT COUNT(*) FROM properties_list\n             WHERE completion_date IS NOT NULL AND completion_date >= CURDATE()"
        )->fetchColumn();
    } catch (Throwable $e) {
        $propertyCount = 0;
        $propertyStatusData = [];
        $propertyTypeData = [];
        $propertyStatusDisplay = [];
        $upcomingCompletions = 0;
    }
}

$leadCount = 0;
$leadNew30 = 0;
$leadStageData = [];
$leadStageDisplay = [];
$leadMonthlyLabels = [];
$leadMonthlySeries = [];
$leadThisMonth = 0;
$leadPrevMonth = 0;
$leadGrowthDisplay = 'No change';
$leadGrowthClass = 'trend-flat';

if ($leadTableExists) {
    try {
        $leadCount = (int) $pdo->query('SELECT COUNT(*) FROM all_leads')->fetchColumn();

        $leadNew30 = (int) $pdo->query(
            "SELECT COUNT(*) FROM all_leads WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        $leadStageData = $pdo->query(
            "SELECT COALESCE(NULLIF(TRIM(stage), ''), 'Unassigned') AS label, COUNT(*) AS total\n             FROM all_leads\n             GROUP BY label\n             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $leadStageDisplay = array_map(
            static fn (array $row): array => [
                'label' => trim((string) ($row['label'] ?? 'Unassigned')),
                'total' => (int) ($row['total'] ?? 0),
            ],
            array_slice($leadStageData, 0, 5)
        );

        $monthRange = [];
        $currentMonth = new DateTimeImmutable('first day of this month');
        for ($i = 5; $i >= 0; $i--) {
            $month = $currentMonth->modify("-{$i} months");
            $key = $month->format('Y-m');
            $monthRange[$key] = [
                'label' => $month->format('M Y'),
                'value' => 0,
            ];
        }

        $monthlyStmt = $pdo->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS total\n             FROM all_leads\n             WHERE created_at >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 5 MONTH)\n             GROUP BY ym\n             ORDER BY ym"
        );

        foreach ($monthlyStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = $row['ym'] ?? '';
            if ($key !== '' && isset($monthRange[$key])) {
                $monthRange[$key]['value'] = (int) $row['total'];
            }
        }

        $leadMonthlyLabels = array_column($monthRange, 'label');
        $leadMonthlySeries = array_column($monthRange, 'value');

        if (!empty($leadMonthlySeries)) {
            $leadThisMonth = (int) end($leadMonthlySeries);
            $leadPrevMonth = (int) ($leadMonthlySeries[count($leadMonthlySeries) - 2] ?? 0);

            if ($leadPrevMonth > 0) {
                $leadGrowthValue = (($leadThisMonth - $leadPrevMonth) / $leadPrevMonth) * 100;
                $leadGrowthDisplay = ($leadGrowthValue >= 0 ? '+' : '') . number_format($leadGrowthValue, 1) . '%';
                $leadGrowthClass = $leadGrowthValue > 0 ? 'trend-up' : ($leadGrowthValue < 0 ? 'trend-down' : 'trend-flat');
            } elseif ($leadThisMonth > 0) {
                $leadGrowthDisplay = 'New';
                $leadGrowthClass = 'trend-up';
            } elseif ($leadCount === 0) {
                $leadGrowthDisplay = 'No data';
                $leadGrowthClass = 'trend-flat';
            }
        }
    } catch (Throwable $e) {
        $leadCount = 0;
        $leadNew30 = 0;
        $leadStageData = [];
        $leadStageDisplay = [];
        $leadMonthlyLabels = [];
        $leadMonthlySeries = [];
        $leadThisMonth = 0;
        $leadPrevMonth = 0;
        $leadGrowthDisplay = 'No change';
        $leadGrowthClass = 'trend-flat';
    }
}

$userCount = 0;
$usersNew30 = 0;
$usersByRoleData = [];
$usersRoleCount = 0;
$dominantRole = '';
$recentUsers = [];

if ($userTableExists) {
    try {
        $userCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $usersNew30 = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
        )->fetchColumn();

        $usersByRoleData = $pdo->query(
            "SELECT COALESCE(NULLIF(TRIM(role), ''), 'Unassigned') AS label, COUNT(*) AS total\n             FROM users\n             GROUP BY label\n             ORDER BY total DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $usersRoleCount = count($usersByRoleData);
        $dominantRole = $usersByRoleData[0]['label'] ?? '';

        $recentStmt = $pdo->query(
            "SELECT full_name, role, created_at\n             FROM users\n             ORDER BY created_at DESC\n             LIMIT 5"
        );
        $recentUsers = $recentStmt ? $recentStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        $userCount = 0;
        $usersNew30 = 0;
        $usersByRoleData = [];
        $usersRoleCount = 0;
        $dominantRole = '';
        $recentUsers = [];
    }
}

$propertyTypeLabels = array_map(static fn ($row) => (string) ($row['label'] ?? 'Unspecified'), $propertyTypeData);
$propertyTypeSeries = array_map(static fn ($row) => (int) ($row['total'] ?? 0), $propertyTypeData);
if (empty($propertyTypeSeries) || array_sum($propertyTypeSeries) === 0) {
    $propertyTypeLabels = [];
    $propertyTypeSeries = [];
}

$propertyStatusLabels = array_map(static fn ($row) => (string) ($row['label'] ?? 'Unspecified'), $propertyStatusData);
$propertyStatusSeries = array_map(static fn ($row) => (int) ($row['total'] ?? 0), $propertyStatusData);
if (empty($propertyStatusSeries) || array_sum($propertyStatusSeries) === 0) {
    $propertyStatusLabels = [];
    $propertyStatusSeries = [];
}

$leadStageLabels = array_map(static fn ($row) => (string) ($row['label'] ?? 'Unassigned'), $leadStageData);
$leadStageSeries = array_map(static fn ($row) => (int) ($row['total'] ?? 0), $leadStageData);
if (empty($leadStageSeries) || array_sum($leadStageSeries) === 0) {
    $leadStageLabels = [];
    $leadStageSeries = [];
}

if (empty($leadMonthlyLabels)) {
    $leadMonthlyLabels = ['No data'];
    $leadMonthlySeries = [0];
}

$usersByRoleLabels = array_map(static fn ($row) => ucfirst(strtolower((string) ($row['label'] ?? 'Unassigned'))), $usersByRoleData);
$usersByRoleSeries = array_map(static fn ($row) => (int) ($row['total'] ?? 0), $usersByRoleData);
if (empty($usersByRoleSeries) || array_sum($usersByRoleSeries) === 0) {
    $usersByRoleLabels = [];
    $usersByRoleSeries = [];
}

$usersRoleDisplay = array_map(
    static fn (array $row): array => [
        'label' => ucfirst(strtolower((string) ($row['label'] ?? 'Unassigned'))),
        'total' => (int) ($row['total'] ?? 0),
    ],
    array_slice($usersByRoleData, 0, 5)
);

$recentUsersDisplay = array_map(
    static function (array $user): array {
        $name = trim((string) ($user['full_name'] ?? ''));
        $role = trim((string) ($user['role'] ?? ''));
        $createdAt = $user['created_at'] ?? '';

        $displayName = $name !== '' ? $name : 'Unnamed User';
        $displayRole = $role !== '' ? ucfirst(strtolower($role)) : 'Unassigned';
        $createdLabel = '—';
        if ($createdAt) {
            $timestamp = strtotime((string) $createdAt);
            if ($timestamp) {
                $createdLabel = date('M j, Y', $timestamp);
            }
        }

        return [
            'name' => $displayName,
            'role' => $displayRole,
            'date' => $createdLabel,
        ];
    },
    $recentUsers
);

$propertyTypeCount = count($propertyTypeData);
$propertyStatusCount = count($propertyStatusData);
$averageLeadsPerProperty = $propertyCount > 0 ? round($leadCount / max($propertyCount, 1), 1) : 0.0;

$propertyDetailParts = [];
if ($propertyCount > 0) {
    $propertyDetailParts[] = $propertyTypeCount . ' type' . ($propertyTypeCount === 1 ? '' : 's');
    $propertyDetailParts[] = $propertyStatusCount . ' status' . ($propertyStatusCount === 1 ? '' : 'es');
}
$propertySummaryDetail = $propertyCount > 0
    ? implode(' • ', $propertyDetailParts)
    : 'Add your first property to begin tracking.';

$summaryCards = [
    [
        'label' => 'Properties in portfolio',
        'value' => number_format($propertyCount),
        'detail' => $propertySummaryDetail,
    ],
    [
        'label' => 'Total leads captured',
        'value' => number_format($leadCount),
        'detail' => $leadNew30 > 0 ? number_format($leadNew30) . ' new in 30 days' : 'No new leads in 30 days',
    ],
    [
        'label' => 'Team members',
        'value' => number_format($userCount),
        'detail' => $usersNew30 > 0 ? number_format($usersNew30) . ' joined recently' : 'Team stable in last 30 days',
    ],
    [
        'label' => 'Leads per property',
        'value' => number_format($averageLeadsPerProperty, 1),
        'detail' => $propertyCount > 0 ? 'Lead to property ratio' : 'Add properties to unlock this metric',
    ],
];

$propertySnapshot = [
    ['label' => 'Tracked projects', 'value' => number_format($propertyCount)],
    ['label' => 'Upcoming completions', 'value' => number_format($upcomingCompletions)],
    ['label' => 'Property types', 'value' => number_format($propertyTypeCount)],
    ['label' => 'Status categories', 'value' => number_format($propertyStatusCount)],
];

$leadSnapshot = [
    ['label' => 'Total leads', 'value' => number_format($leadCount)],
    ['label' => 'New (30 days)', 'value' => number_format($leadNew30)],
    ['label' => 'This month', 'value' => number_format($leadThisMonth)],
    [
        'label' => 'Month over month',
        'value' => $leadGrowthDisplay,
        'is_trend' => true,
        'trend_class' => $leadGrowthClass,
    ],
];

$userSnapshot = [
    ['label' => 'Total members', 'value' => number_format($userCount)],
    ['label' => 'New (30 days)', 'value' => number_format($usersNew30)],
    ['label' => 'Roles tracked', 'value' => number_format($usersRoleCount)],
    ['label' => 'Top role', 'value' => $dominantRole !== '' ? ucfirst(strtolower($dominantRole)) : '—'],
];

$jsonOptions = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
$propertyTypeLabelsJson = json_encode($propertyTypeLabels, $jsonOptions) ?: '[]';
$propertyTypeSeriesJson = json_encode($propertyTypeSeries, $jsonOptions) ?: '[]';
$propertyStatusLabelsJson = json_encode($propertyStatusLabels, $jsonOptions) ?: '[]';
$propertyStatusSeriesJson = json_encode($propertyStatusSeries, $jsonOptions) ?: '[]';
$leadStageLabelsJson = json_encode($leadStageLabels, $jsonOptions) ?: '[]';
$leadStageSeriesJson = json_encode($leadStageSeries, $jsonOptions) ?: '[]';
$leadMonthlyLabelsJson = json_encode($leadMonthlyLabels, $jsonOptions) ?: '[]';
$leadMonthlySeriesJson = json_encode($leadMonthlySeries, $jsonOptions) ?: '[]';
$usersByRoleLabelsJson = json_encode($usersByRoleLabels, $jsonOptions) ?: '[]';
$usersByRoleSeriesJson = json_encode($usersByRoleSeries, $jsonOptions) ?: '[]';

$pageScriptFiles = $pageScriptFiles ?? [];
$pageScriptFiles[] = 'https://cdn.jsdelivr.net/npm/apexcharts';

$dashboardScript = <<<JS
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.ApexCharts) {
        return;
    }

    const propertyTypeChartEl = document.querySelector('#property-type-chart');
    if (propertyTypeChartEl) {
        const propertyTypeChart = new ApexCharts(propertyTypeChartEl, {
            chart: { type: 'bar', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Projects', data: {$propertyTypeSeriesJson} }],
            xaxis: {
                categories: {$propertyTypeLabelsJson},
                labels: { style: { colors: '#475569' } }
            },
            yaxis: {
                labels: { style: { colors: '#475569' } }
            },
            colors: ['#0d9488'],
            dataLabels: { enabled: false },
            plotOptions: {
                bar: { borderRadius: 8, columnWidth: '55%' }
            },
            grid: {
                borderColor: '#d7e4de',
                strokeDashArray: 4
            },
            noData: {
                text: 'No property data yet',
                align: 'center',
                style: { color: '#94a3b8', fontWeight: 600 }
            }
        });
        propertyTypeChart.render();
    }

    const propertyStatusChartEl = document.querySelector('#property-status-chart');
    if (propertyStatusChartEl) {
        const propertyStatusChart = new ApexCharts(propertyStatusChartEl, {
            chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
            series: {$propertyStatusSeriesJson},
            labels: {$propertyStatusLabelsJson},
            colors: ['#0f766e', '#2dd4bf', '#134e4a', '#5eead4', '#99f6e4', '#0f172a'],
            legend: {
                position: 'bottom',
                labels: { colors: '#475569' }
            },
            stroke: { width: 0 },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } },
            plotOptions: {
                pie: {
                    donut: { size: '58%' }
                }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' properties';
                    }
                }
            },
            noData: {
                text: 'No status data yet',
                align: 'center',
                style: { color: '#94a3b8', fontWeight: 600 }
            }
        });
        propertyStatusChart.render();
    }

    const leadStageChartEl = document.querySelector('#lead-stage-chart');
    if (leadStageChartEl) {
        const leadStageChart = new ApexCharts(leadStageChartEl, {
            chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
            series: {$leadStageSeriesJson},
            labels: {$leadStageLabelsJson},
            colors: ['#2563eb', '#0ea5e9', '#38bdf8', '#7dd3fc', '#1e293b', '#94a3b8'],
            legend: {
                position: 'bottom',
                labels: { colors: '#475569' }
            },
            stroke: { width: 0 },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } },
            plotOptions: {
                pie: { donut: { size: '58%' } }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' leads';
                    }
                }
            },
            noData: {
                text: 'No lead data yet',
                align: 'center',
                style: { color: '#94a3b8', fontWeight: 600 }
            }
        });
        leadStageChart.render();
    }

    const leadMonthlyChartEl = document.querySelector('#lead-monthly-chart');
    if (leadMonthlyChartEl) {
        const leadMonthlyChart = new ApexCharts(leadMonthlyChartEl, {
            chart: { type: 'area', height: 320, toolbar: { show: false }, fontFamily: 'inherit' },
            series: [{ name: 'Leads', data: {$leadMonthlySeriesJson} }],
            xaxis: {
                categories: {$leadMonthlyLabelsJson},
                axisBorder: { color: '#d7e4de' },
                axisTicks: { color: '#d7e4de' },
                labels: { style: { colors: '#475569' } }
            },
            yaxis: {
                labels: { style: { colors: '#475569' } }
            },
            colors: ['#0ea5e9'],
            stroke: { curve: 'smooth', width: 3 },
            markers: { size: 4, colors: '#0ea5e9', strokeColors: '#fff', strokeWidth: 2 },
            fill: {
                type: 'gradient',
                gradient: {
                    shadeIntensity: 1,
                    opacityFrom: 0.45,
                    opacityTo: 0.05,
                    stops: [0, 95, 100]
                }
            },
            grid: {
                borderColor: '#d7e4de',
                strokeDashArray: 4
            },
            noData: {
                text: 'No lead history yet',
                align: 'center',
                style: { color: '#94a3b8', fontWeight: 600 }
            }
        });
        leadMonthlyChart.render();
    }

    const userRoleChartEl = document.querySelector('#user-role-chart');
    if (userRoleChartEl) {
        const userRoleChart = new ApexCharts(userRoleChartEl, {
            chart: { type: 'donut', height: 320, fontFamily: 'inherit' },
            series: {$usersByRoleSeriesJson},
            labels: {$usersByRoleLabelsJson},
            colors: ['#4ade80', '#22c55e', '#16a34a', '#14532d', '#bbf7d0'],
            legend: {
                position: 'bottom',
                labels: { colors: '#475569' }
            },
            stroke: { width: 0 },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } },
            plotOptions: {
                pie: { donut: { size: '58%' } }
            },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return val + ' users';
                    }
                }
            },
            noData: {
                text: 'No user data yet',
                align: 'center',
                style: { color: '#94a3b8', fontWeight: 600 }
            }
        });
        userRoleChart.render();
    }
});
</script>
JS;

$pageInlineScripts = $pageInlineScripts ?? [];
$pageInlineScripts[] = $dashboardScript;

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-wrap">
            <div class="dashboard-header">
                <h1 class="main-heading">Dashboard</h1>
                <p class="subheading">Manage and track all your real estate leads</p>
            </div>

            <section class="summary-grid">
                <?php foreach ($summaryCards as $card): ?>
                    <article class="summary-card">
                        <span class="summary-card__label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <span class="summary-card__value"><?= htmlspecialchars($card['value'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if (!empty($card['detail'])): ?>
                            <span class="summary-card__detail"><?= htmlspecialchars($card['detail'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="insight-section">
                <div class="section-heading">
                    <h2>Property Insights</h2>
                    <p>Understand how your projects are distributed across the portfolio.</p>
                </div>
                <div class="insight-grid">
                    <div class="chart-card">
                        <div class="chart-card__title">Inventory by Property Type</div>
                        <div class="chart-canvas" id="property-type-chart"></div>
                    </div>
                    <div class="chart-card">
                        <div class="chart-card__title">Project Status Mix</div>
                        <div class="chart-canvas" id="property-status-chart"></div>
                        <ul class="chart-summary">
                            <?php if (!empty($propertyStatusDisplay)): ?>
                                <?php foreach ($propertyStatusDisplay as $statusRow): ?>
                                    <li>
                                        <span><?= htmlspecialchars($statusRow['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= number_format($statusRow['total']) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="chart-summary__empty">Add property details to see status distribution.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="metric-card">
                        <div class="chart-card__title">Property Snapshot</div>
                        <ul class="metric-list">
                            <?php foreach ($propertySnapshot as $item): ?>
                                <li>
                                    <span class="metric-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="metric-value"><?= htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="insight-section">
                <div class="section-heading">
                    <h2>Lead Insights</h2>
                    <p>Monitor pipeline health and lead flow trends.</p>
                </div>
                <div class="insight-grid">
                    <div class="chart-card">
                        <div class="chart-card__title">Lead Stage Distribution</div>
                        <div class="chart-canvas" id="lead-stage-chart"></div>
                        <ul class="chart-summary">
                            <?php if (!empty($leadStageDisplay)): ?>
                                <?php foreach ($leadStageDisplay as $stageRow): ?>
                                    <li>
                                        <span><?= htmlspecialchars($stageRow['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= number_format($stageRow['total']) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="chart-summary__empty">Add leads to visualise your pipeline.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="chart-card">
                        <div class="chart-card__title">Monthly Lead Flow</div>
                        <div class="chart-canvas" id="lead-monthly-chart"></div>
                    </div>
                    <div class="metric-card">
                        <div class="chart-card__title">Lead Snapshot</div>
                        <ul class="metric-list">
                            <?php foreach ($leadSnapshot as $item): ?>
                                <li>
                                    <span class="metric-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="metric-value">
                                        <?php if (!empty($item['is_trend'])): ?>
                                            <span class="trend-pill <?= htmlspecialchars($item['trend_class'] ?? 'trend-flat', ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8') ?>
                                            </span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8') ?>
                                        <?php endif; ?>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="insight-section">
                <div class="section-heading">
                    <h2>User Insights</h2>
                    <p>Keep an eye on team distribution and recent additions.</p>
                </div>
                <div class="insight-grid">
                    <div class="chart-card">
                        <div class="chart-card__title">Users by Role</div>
                        <div class="chart-canvas" id="user-role-chart"></div>
                        <ul class="chart-summary">
                            <?php if (!empty($usersRoleDisplay)): ?>
                                <?php foreach ($usersRoleDisplay as $roleRow): ?>
                                    <li>
                                        <span><?= htmlspecialchars($roleRow['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <strong><?= number_format($roleRow['total']) ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="chart-summary__empty">Invite teammates to collaborate here.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="metric-card">
                        <div class="chart-card__title">Team Snapshot</div>
                        <ul class="metric-list">
                            <?php foreach ($userSnapshot as $item): ?>
                                <li>
                                    <span class="metric-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="metric-value"><?= htmlspecialchars($item['value'], ENT_QUOTES, 'UTF-8') ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="metric-card">
                        <div class="chart-card__title">Latest Members</div>
                        <ul class="metric-list metric-list--stacked">
                            <?php if (!empty($recentUsersDisplay)): ?>
                                <?php foreach ($recentUsersDisplay as $user): ?>
                                    <li>
                                        <span class="metric-title"><?= htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="metric-meta"><?= htmlspecialchars($user['role'] . ' • ' . $user['date'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </li>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <li class="chart-summary__empty">No team members added yet.</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
