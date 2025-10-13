<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/common-header.php';

/**
 * Determine whether the given table exists in the currently connected database.
 */
function hh_table_exists(mysqli $mysqli, string $table): bool
{
    $table = trim($table);

    if ($table === '') {
        return false;
    }

    $escapedTable = $mysqli->real_escape_string($table);
    $result       = $mysqli->query("SHOW TABLES LIKE '{$escapedTable}'");

    if ($result instanceof mysqli_result) {
        $exists = $result->num_rows > 0;
        $result->free();

        return $exists;
    }

    return false;
}

/**
 * Fetch the total number of rows for the given table if it exists.
 */
function hh_fetch_table_count(mysqli $mysqli, string $table): int
{
    if (!hh_table_exists($mysqli, $table)) {
        return 0;
    }

    $query = sprintf('SELECT COUNT(*) AS total FROM `%s`', $table);
    $result = $mysqli->query($query);

    if ($result instanceof mysqli_result) {
        $row = $result->fetch_assoc();
        $result->free();

        return isset($row['total']) ? (int) $row['total'] : 0;
    }

    return 0;
}

function hh_strtolower(string $value): string
{
    return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
}

function hh_normalize_label(string $label): string
{
    $label = trim(hh_strtolower($label));
    $label = str_replace(['–', '_'], '-', $label);
    $label = preg_replace('/[^a-z0-9\-\s]/', '', $label);
    $label = preg_replace('/\s+/', ' ', $label);

    return trim($label);
}

function hh_format_stage(?string $stage): string
{
    $stage = trim((string) $stage);

    if ($stage === '') {
        return '—';
    }

    $stage = str_replace(['_', '-'], ' ', $stage);
    $stage = preg_replace('/\s+/', ' ', $stage);

    return ucwords(hh_strtolower($stage));
}

$dashboardMetrics = [
    'properties' => hh_fetch_table_count($mysqli, 'properties_list'),
    'leads'      => hh_fetch_table_count($mysqli, 'all_leads'),
    'users'      => hh_fetch_table_count($mysqli, 'users'),
    'active'     => 0,
];

$activeStageLabels = [
    'New',
    'Contacted',
    'Follow Up – In Progress',
    'Qualified',
    'Meeting Scheduled',
    'Meeting Done',
    'Offer Made',
    'Negotiation',
    'Site Visit',
];

$activeStageLookup = [];
foreach ($activeStageLabels as $stageLabel) {
    $activeStageLookup[hh_normalize_label($stageLabel)] = true;
}

$activeLeadChartData = [
    'labels'  => [],
    'scores'  => [],
    'details' => [],
];

$ratingOrder = [
    'hot'     => 5,
    'warm'    => 4,
    'nurture' => 3,
    'cold'    => 2,
    'new'     => 1,
];

if (hh_table_exists($mysqli, 'all_leads')) {
    $leadResult = $mysqli->query('SELECT id, name, stage, rating, created_at FROM `all_leads`');

    if ($leadResult instanceof mysqli_result) {
        $activeLeads = [];

        while ($lead = $leadResult->fetch_assoc()) {
            $stage        = $lead['stage'] ?? '';
            $normalized   = hh_normalize_label($stage);

            if ($normalized === '' || !isset($activeStageLookup[$normalized])) {
                continue;
            }

            $ratingRaw = trim((string) ($lead['rating'] ?? ''));
            $ratingKey = hh_normalize_label($ratingRaw);
            $score     = $ratingOrder[$ratingKey] ?? 0;

            $activeLeads[] = [
                'id'        => isset($lead['id']) ? (int) $lead['id'] : null,
                'name'      => trim((string) ($lead['name'] ?? '')),
                'stage'     => hh_format_stage($stage),
                'rating'    => $ratingRaw !== '' ? $ratingRaw : 'Unrated',
                'score'     => $score,
                'createdAt' => $lead['created_at'] ?? null,
            ];
        }

        $leadResult->free();

        if (!empty($activeLeads)) {
            usort(
                $activeLeads,
                static function (array $a, array $b): int {
                    if ($a['score'] === $b['score']) {
                        return strcmp((string) $a['name'], (string) $b['name']);
                    }

                    return $b['score'] <=> $a['score'];
                }
            );

            foreach ($activeLeads as $lead) {
                $dashboardMetrics['active']++;

                $label = $lead['name'] !== ''
                    ? $lead['name']
                    : sprintf('Lead #%d', $lead['id'] ?? 0);

                $activeLeadChartData['labels'][] = $label;
                $activeLeadChartData['scores'][] = $lead['score'];
                $activeLeadChartData['details'][] = [
                    'name'   => $label,
                    'rating' => $lead['rating'],
                    'stage'  => $lead['stage'],
                ];
            }
        }
    }
}

if ($dashboardMetrics['active'] === 0) {
    $activeLeadChartData = [
        'labels'  => ['No Active Leads'],
        'scores'  => [0],
        'details' => [
            [
                'name'   => 'No Active Leads',
                'rating' => 'N/A',
                'stage'  => '—',
            ],
        ],
    ];
}

$monthlyPropertyReport = [
    'labels' => [],
    'data'   => [],
];

if (hh_table_exists($mysqli, 'properties_list')) {
    $propertyResult = $mysqli->query(
        "SELECT DATE_FORMAT(created_at, '%Y-%m-01') AS month_key, COUNT(*) AS total " .
        "FROM `properties_list` " .
        "WHERE created_at IS NOT NULL " .
        "GROUP BY month_key " .
        "ORDER BY month_key ASC"
    );

    if ($propertyResult instanceof mysqli_result) {
        while ($row = $propertyResult->fetch_assoc()) {
            $monthKey = $row['month_key'];
            $total    = isset($row['total']) ? (int) $row['total'] : 0;

            if ($monthKey === null) {
                continue;
            }

            $timestamp = strtotime($monthKey);

            if ($timestamp === false) {
                continue;
            }

            $monthlyPropertyReport['labels'][] = date('M Y', $timestamp);
            $monthlyPropertyReport['data'][]   = $total;
        }

        $propertyResult->free();
    }
}

if (empty($monthlyPropertyReport['labels'])) {
    $months = [
        'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
        'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec',
    ];

    $monthlyPropertyReport['labels'] = $months;
    $monthlyPropertyReport['data']   = array_fill(0, count($months), 0);
}
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
        </div>
        <div class="container-fluid">
            <div class="row g-3 lead-stats">
                <div class="col-md-3">
                    <div class="stat-card total-leads">
                        <h6>Total Properties</h6>
                        <h2><?= number_format($dashboardMetrics['properties']); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card active-leads">
                        <h6>Total Leads</h6>
                        <h2><?= number_format($dashboardMetrics['leads']); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card closed-leads">
                        <h6>Total Users</h6>
                        <h2><?= number_format($dashboardMetrics['users']); ?></h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card lost-leads">
                        <h6>Active Leads</h6>
                        <h2><?= number_format($dashboardMetrics['active']); ?></h2>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-lg-6">
                    <div class="chart-section">
                        <div id="barChart"></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-section">
                        <div id="chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>