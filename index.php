<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$allowedRanges = ['last_30_days', 'last_7_days', 'last_month', 'last_quarter', 'this_month', 'custom'];
$requestedRange = strtolower((string) ($_GET['range'] ?? 'last_30_days'));
if (!in_array($requestedRange, $allowedRanges, true)) {
    $requestedRange = 'last_30_days';
}

$rawStartDate = trim((string) ($_GET['start_date'] ?? ''));
$rawEndDate = trim((string) ($_GET['end_date'] ?? ''));

$startDate = null;
if ($rawStartDate !== '') {
    $startDateCandidate = \DateTimeImmutable::createFromFormat('Y-m-d', $rawStartDate);
    if ($startDateCandidate instanceof \DateTimeImmutable) {
        $startDate = $startDateCandidate->format('Y-m-d');
    }
}

$endDate = null;
if ($rawEndDate !== '') {
    $endDateCandidate = \DateTimeImmutable::createFromFormat('Y-m-d', $rawEndDate);
    if ($endDateCandidate instanceof \DateTimeImmutable) {
        $endDate = $endDateCandidate->format('Y-m-d');
    }
}

$selectedRange = $startDate !== null && $endDate !== null ? 'custom' : $requestedRange;
if ($selectedRange === 'custom' && ($startDate === null || $endDate === null)) {
    $selectedRange = 'last_30_days';
}

$selectedAgentId = isset($_GET['agent_id']) ? (int) $_GET['agent_id'] : null;
if ($selectedAgentId !== null && $selectedAgentId <= 0) {
    $selectedAgentId = null;
}

$selectedSource = trim((string) ($_GET['source'] ?? ''));
if ($selectedSource === '') {
    $selectedSource = null;
}

$pageScriptFiles[] = [
    'src' => hh_asset('public/js/dashboard.api.js'),
    'attributes' => ['defer' => true],
];

$dashboardConfig = [
    'defaultRange' => $selectedRange,
    'agentId' => $selectedAgentId,
    'source' => $selectedSource,
    'startDate' => $startDate,
    'endDate' => $endDate,
    'endpoints' => [
        'leadCounters' => hh_url('api/stats/lead-counters/index.php'),
        'leadSources' => hh_url('api/charts/lead-sources/index.php'),
        'topAgents' => hh_url('api/agents/top/index.php'),
        'recentActivities' => hh_url('api/activities/recent/index.php'),
        'activityHeatmap' => hh_url('api/charts/activity-heatmap/index.php'),
        'performance' => hh_url('api/stats/performance/index.php'),
        'inventory' => hh_url('api/inventory/projects/index.php'),
        'search' => hh_url('api/search/index.php'),
    ],
];

$encodedConfig = json_encode(
    $dashboardConfig,
    JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
);

$pageInlineScripts[] = '<script>window.HH_DASHBOARD_BOOT = ' . $encodedConfig . ';</script>';

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <form action="" method="get">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-12">
                        <h1 class="main-heading">Dashboard</h1>
                        <p class="subheading">Manage and track all your real estate leads</p>
                    </div>
                    <div class="col-lg-12">
                        <div class="right-search">
                            <div class="form-group mb-0">
                                <input type="text" class="form-control" placeholder="Search leads, projects, clients" data-dashboard-search />
                                <span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <path d="m21 21-4.3-4.3"></path>
                                    </svg>
                                </span>
                            </div>
                            <input type="hidden" name="agent_id" value="<?= $selectedAgentId !== null ? (int) $selectedAgentId : '' ?>">
                            <input type="hidden" name="source" value="<?= htmlspecialchars($selectedSource ?? '', ENT_QUOTES, 'UTF-8') ?>">
                            <select class="form-control select-dropDownClass" name="range" data-dashboard-range>
                                <option value="last_30_days" <?= $selectedRange === 'last_30_days' ? 'selected' : '' ?>>Last 30 Days</option>
                                <option value="last_7_days" <?= $selectedRange === 'last_7_days' ? 'selected' : '' ?>>Last 7 Days</option>
                                <option value="last_month" <?= $selectedRange === 'last_month' ? 'selected' : '' ?>>Last Month</option>
                                <option value="last_quarter" <?= $selectedRange === 'last_quarter' ? 'selected' : '' ?>>Last Quarter</option>
                                <option value="this_month" <?= $selectedRange === 'this_month' ? 'selected' : '' ?>>This Month</option>
                                <option value="custom" <?= $selectedRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                            <div class="d-flex gap-2 align-items-center ">
                                <input
                                    type="date"
                                    class="form-control"
                                    name="start_date"
                                    value="<?= $startDate !== null ? htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') : '' ?>"
                                    placeholder="Start date"
                                    data-dashboard-start
                                />
                                <input
                                    type="date"
                                    class="form-control"
                                    name="end_date"
                                    value="<?= $endDate !== null ? htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') : '' ?>"
                                    placeholder="End date"
                                    data-dashboard-end
                                />
                                <button type="button" class="btn btn-primary" data-dashboard-apply-range>Apply</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="lead-stats-section">
            <div class="container-fluid">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card total-leads">
                            <div class="lead-metric-icon">
                                <i class='bx bx-group'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Total Leads</h6>
                                <h2 data-stat-value="total-leads">--</h2>
                                <div class="lead-metric-growth green" data-stat-change="total-leads" data-stat-change-default-class="green">
                                    <i class='bx bx-minus'></i>
                                    <span data-stat-change-value="total-leads">--</span> <small data-stat-change-label="total-leads">vs previous period</small>
                                </div>
                                <div class="lead-metric-detail">
                                    <small class="text-muted d-block" data-stat-detail-current="total-leads">--</small>
                                    <small class="text-muted d-block" data-stat-detail-previous="total-leads">--</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card active-leads">
                            <div class="lead-metric-icon">
                                <i class='bx bx-trending-up'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Hot / Active Leads</h6>
                                <h2 data-stat-value="hot-active">--</h2>
                                <div class="lead-metric-growth blue" data-stat-change="hot-active" data-stat-change-default-class="blue">
                                    <i class='bx bx-minus'></i>
                                    <span data-stat-change-value="hot-active">--</span> <small data-stat-change-label="hot-active">vs previous period</small>
                                </div>
                                <div class="lead-metric-detail">
                                    <small class="text-muted d-block" data-stat-detail-current="hot-active">--</small>
                                    <small class="text-muted d-block" data-stat-detail-previous="hot-active">--</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card lost-leads">
                            <div class="lead-metric-icon">
                                <i class='bx bx-dollar'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Closed Leads</h6>
                                <h2 data-stat-value="closed-leads">--</h2>
                                <div class="lead-metric-growth red" data-stat-change="closed-leads" data-stat-change-default-class="red">
                                    <i class='bx bx-minus'></i>
                                    <span data-stat-change-value="closed-leads">--</span> <small data-stat-change-label="closed-leads">vs previous period</small>
                                </div>
                                <div class="lead-metric-detail">
                                    <small class="text-muted d-block" data-stat-detail-current="closed-leads">--</small>
                                    <small class="text-muted d-block" data-stat-detail-previous="closed-leads">--</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card closed-leads">
                            <div class="lead-metric-icon">
                                <i class='bx bx-user-pin'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Channel Partners</h6>
                                <h2 data-stat-value="channel-partners">--</h2>
                                <div class="lead-metric-growth yellow" data-stat-change="channel-partners" data-stat-change-default-class="yellow">
                                    <i class='bx bx-minus'></i>
                                    <span data-stat-change-value="channel-partners">--</span> <small data-stat-change-label="channel-partners">vs previous period</small>
                                </div>
                                <div class="lead-metric-detail">
                                    <small class="text-muted d-block" data-stat-detail-current="channel-partners">--</small>
                                    <small class="text-muted d-block" data-stat-detail-previous="channel-partners">--</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <section class="lead-analytics-section">
            <div class="container-fluid">
                <div class="row">
                    <!-- Lead Source Analytics -->
                    <div class="col-lg-5">
                        <div class="chart-section">
                            <h5 class="chart-title">Lead Source Analytics</h5>
                            <p class="chart-subtitle">Distribution by channel</p>

                            <div id="chart"></div>

                            <ul class="lead-source-list mt-3" data-lead-source-list>
                                <li class="text-muted">Loading lead source analytics…</li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-3">
                        <div class="top-agents-card">
                            <div class="top-agents-header">
                                <i class="bi bi-trophy"></i>
                                <div>
                                    <h5>Top Agents</h5>
                                    <p>This month's leaders</p>
                                </div>
                            </div>

                            <div class="agent-list" data-top-agents>
                                <div class="text-muted small">Loading top agents…</div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="recent-activity-card">
                            <div class="recent-header">
                                <i class="bi bi-clock-history"></i>
                                <div>
                                    <h5>Recent Activity</h5>
                                    <p>Latest updates</p>
                                </div>
                            </div>

                            <div class="activity-list" data-recent-activities>
                                <div class="text-muted small">Loading recent activity…</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="activity-section">
            <div class="container-fluid">
                <div class="row g-4">
                    <!-- Activity Heatmap -->
                    <div class="col-lg-8">
                        <div class="card heatmap-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <div class="icon-box me-2">
                                                <i class="bi bi-activity"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-0 fw-semibold">Activity Heatmap</h5>
                                                <p class="text-muted small mb-0">Peak engagement times</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="fw-semibold text-success mb-0" data-heatmap-average>--</h5>
                                        <p class="text-muted small mb-0">Avg Activity</p>
                                    </div>
                                </div>

                                <!-- Blank Heatmap Grid -->
                                <div class="heatmap-grid" data-heatmap-grid>
                                    <div class="text-muted small">Loading heatmap…</div>
                                </div>

                                <div class="text-center small text-muted">
                                    <span>Less</span>
                                    <span class="legend"></span>
                                    <span>More</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="col-lg-4">
                        <div class="card metrics-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-box me-2">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Performance Metrics</h5>
                                    </div>
                                </div>

                                <!-- Metric 1 -->
                                <div class="metric-item mb-3" data-performance-metric="target_achievement">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-bullseye me-2 text-success"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Conversion Rate</p>
                                                <small class="text-muted">Closed vs. created</small>
                                            </div>
                                        </div>
                                        <span class="fw-semibold" data-metric-value="target_achievement">--</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:0%" data-metric-bar="target_achievement"></div>
                                    </div>
                                </div>

                                <!-- Metric 2 -->
                                <div class="metric-item mb-3" data-performance-metric="avg_response_time_hours">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-stopwatch me-2 text-success"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Avg. Response Time</p>
                                                <small class="text-muted">Target: &lt; 3h</small>
                                            </div>
                                        </div>
                                        <span class="fw-semibold" data-metric-value="avg_response_time_hours">--</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:0%" data-metric-bar="avg_response_time_hours"></div>
                                    </div>
                                </div>

                                <!-- Metric 3 -->
                                <div class="metric-item mb-3" data-performance-metric="lead_engagement_pct">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-people me-2 text-primary"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Closing Ratio</p>
                                                <small class="text-muted">Closed vs. engaged</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-secondary bg-opacity-10 text-muted" data-metric-status="lead_engagement_pct">Loading</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-primary" style="width:0%" data-metric-bar="lead_engagement_pct"></div>
                                    </div>
                                </div>

                                <!-- Metric 4 -->
                                <div class="metric-item" data-performance-metric="deal_velocity_days">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-lightning-charge me-2 text-warning"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Deal Velocity</p>
                                                <small class="text-muted">Target: 21 days</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-secondary bg-opacity-10 text-muted" data-metric-status="deal_velocity_days">Loading</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-warning" style="width:0%" data-metric-bar="deal_velocity_days"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <div class="container-fluid">
            <section class="card lead-table-card">
                <div class="card-body p-0">
                    <div class="inventory-header">
                        <div class="left">
                            <div class="icon"><i class="bi bi-buildings"></i></div>
                            <div>
                                <h5>Property Inventory Summary</h5>
                                <p>Active projects across Dubai</p>
                            </div>
                        </div>
                        <div class="right">
                            <div>
                                <p>Total Inventory Value</p>
                                <h6 data-inventory-total-value>--</h6>
                            </div>
                            <div>
                                <p>Avg. Sold Out</p>
                                <h6 class="text-success"><i class="bi bi-graph-up-arrow me-1"></i><span data-inventory-avg-sold>--</span></h6>
                            </div>
                            <!-- <button class="filter-btn"><i class="bi bi-funnel me-1"></i>Filters</button> -->
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 lead-table">
                            <thead>
                                <tr>
                                    <th scope="col">Project Name</th>
                                    <th scope="col">Total Units</th>
                                    <th scope="col">Sold</th>
                                    <th scope="col">Available</th>
                                    <th scope="col">Avg. Price</th>
                                    <th scope="col">Progress</th>
                                </tr>
                            </thead>
                            <tbody data-inventory-table>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">Loading inventory data…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>


    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>