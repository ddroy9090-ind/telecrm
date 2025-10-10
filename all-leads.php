<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$searchTerm = trim($_GET['search'] ?? '');
$stageFilter = $_GET['stage'] ?? 'all';
$sortOption = $_GET['sort'] ?? 'latest';

$conditions = [];
$parameters = [];
$parameterTypes = '';

if ($searchTerm !== '') {
    $conditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR assigned_to LIKE ? OR source LIKE ?)";
    $likeTerm = '%' . $searchTerm . '%';
    $parameterTypes .= 'sssss';
    $parameters = array_merge($parameters, array_fill(0, 5, $likeTerm));
}

if ($stageFilter !== 'all' && $stageFilter !== '') {
    $conditions[] = 'stage = ?';
    $parameterTypes .= 's';
    $parameters[] = $stageFilter;
}

$query = 'SELECT id, stage, rating, assigned_to, source, name, phone, email, alternate_phone, nationality, interested_in, property_type, location_preferences, budget_range, size_required, purpose, urgency, alternate_email, payout_received, created_at FROM `All leads`';

if (!empty($conditions)) {
    $query .= ' WHERE ' . implode(' AND ', $conditions);
}

switch ($sortOption) {
    case 'name-asc':
        $query .= ' ORDER BY name ASC';
        break;
    case 'name-desc':
        $query .= ' ORDER BY name DESC';
        break;
    case 'oldest':
        $query .= ' ORDER BY COALESCE(created_at, CURRENT_TIMESTAMP) ASC';
        break;
    case 'latest':
    default:
        $query .= ' ORDER BY COALESCE(created_at, CURRENT_TIMESTAMP) DESC';
        break;
}

$leads = [];

if ($stmt = $mysqli->prepare($query)) {
    if ($parameterTypes !== '') {
        $stmt->bind_param($parameterTypes, ...$parameters);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $leads[] = $row;
        }
        $result->free();
    }

    $stmt->close();
}

$distinctStages = [];
if ($stageResult = $mysqli->query("SELECT DISTINCT stage FROM `All leads` WHERE stage IS NOT NULL AND stage <> '' ORDER BY stage ASC")) {
    while ($stageRow = $stageResult->fetch_assoc()) {
        $distinctStages[] = $stageRow['stage'];
    }
    $stageResult->free();
}

$totalLeads = count($leads);
$closedStageKeywords = ['closed', 'converted', 'lost', 'inactive', 'dead', 'won'];
$conversionStageKeywords = ['converted', 'won'];

$activeLeads = 0;
$convertedLeads = 0;
$ratingAccumulator = 0.0;
$ratingCount = 0;

$currentMonth = (new DateTime('first day of this month'))->format('Y-m');
$previousMonth = (new DateTime('first day of previous month'))->format('Y-m');

$statsByMonth = [
    $currentMonth => ['total' => 0, 'active' => 0, 'converted' => 0],
    $previousMonth => ['total' => 0, 'active' => 0, 'converted' => 0],
];

foreach ($leads as $lead) {
    $stage = strtolower((string) ($lead['stage'] ?? ''));
    $createdAt = $lead['created_at'] ?? null;
    $monthKey = null;

    if ($createdAt) {
        $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $createdAt) ?: DateTime::createFromFormat('Y-m-d', $createdAt);
        if ($timestamp) {
            $monthKey = $timestamp->format('Y-m');
        }
    }

    $isClosed = false;
    foreach ($closedStageKeywords as $keyword) {
        if ($keyword !== '' && str_contains($stage, $keyword)) {
            $isClosed = true;
            break;
        }
    }

    if (!$isClosed) {
        $activeLeads++;
    }

    $isConverted = false;
    foreach ($conversionStageKeywords as $keyword) {
        if ($keyword !== '' && str_contains($stage, $keyword)) {
            $isConverted = true;
            break;
        }
    }

    if ($isConverted) {
        $convertedLeads++;
    }

    if (is_numeric($lead['rating'])) {
        $ratingAccumulator += (float) $lead['rating'];
        $ratingCount++;
    }

    if ($monthKey && isset($statsByMonth[$monthKey])) {
        $statsByMonth[$monthKey]['total']++;
        if (!$isClosed) {
            $statsByMonth[$monthKey]['active']++;
        }
        if ($isConverted) {
            $statsByMonth[$monthKey]['converted']++;
        }
    }
}

$averageRating = $ratingCount > 0 ? round($ratingAccumulator / $ratingCount, 1) : null;
$conversionRate = $totalLeads > 0 ? round(($convertedLeads / $totalLeads) * 100, 1) : 0.0;

function calculateTrend(?array $statsByMonth, string $metric): ?float
{
    if (!$statsByMonth) {
        return null;
    }

    $keys = array_keys($statsByMonth);
    if (count($keys) < 2) {
        return null;
    }

    $currentKey = $keys[0];
    $previousKey = $keys[1];

    $currentValue = $statsByMonth[$currentKey][$metric] ?? 0;
    $previousValue = $statsByMonth[$previousKey][$metric] ?? 0;

    if ($previousValue == 0) {
        if ($currentValue == 0) {
            return 0.0;
        }
        return null;
    }

    return round((($currentValue - $previousValue) / $previousValue) * 100, 1);
}

$totalTrend = calculateTrend($statsByMonth, 'total');
$activeTrend = calculateTrend($statsByMonth, 'active');
$conversionTrend = calculateTrend($statsByMonth, 'converted');

function renderTrend(?float $trend): string
{
    if ($trend === null) {
        return '<span class="trend trend-neutral">—</span>';
    }

    if ($trend > 0) {
        return '<span class="trend trend-up">+' . htmlspecialchars((string) $trend) . "% vs last month</span>";
    }

    if ($trend < 0) {
        return '<span class="trend trend-down">' . htmlspecialchars((string) $trend) . "% vs last month</span>";
    }

    return '<span class="trend trend-neutral">0% vs last month</span>';
}

function stageBadgeClass(string $stage): string
{
    $normalized = strtolower($stage);

    return match (true) {
        str_contains($normalized, 'new') => 'badge-stage-new',
        str_contains($normalized, 'contact') => 'badge-stage-contact',
        str_contains($normalized, 'proposal') => 'badge-stage-proposal',
        str_contains($normalized, 'convert') || str_contains($normalized, 'won') => 'badge-stage-converted',
        str_contains($normalized, 'lost') || str_contains($normalized, 'inactive') || str_contains($normalized, 'dead') => 'badge-stage-lost',
        default => 'badge-stage-default',
    };
}

function ratingBadgeClass(?string $rating): string
{
    if ($rating === null || $rating === '') {
        return 'badge-rating-default';
    }

    $normalized = strtolower($rating);

    if (is_numeric($rating)) {
        $value = (float) $rating;
        if ($value >= 4) {
            return 'badge-rating-hot';
        }
        if ($value >= 2.5) {
            return 'badge-rating-warm';
        }
        return 'badge-rating-cold';
    }

    return match (true) {
        str_contains($normalized, 'hot') => 'badge-rating-hot',
        str_contains($normalized, 'warm') => 'badge-rating-warm',
        str_contains($normalized, 'cold') => 'badge-rating-cold',
        default => 'badge-rating-default',
    };
}

function leadStringLength(string $text): int
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($text);
    }

    return strlen($text);
}

function leadSubstring(string $text, int $start, ?int $length = null): string
{
    if (function_exists('mb_substr')) {
        return $length === null ? mb_substr($text, $start) : mb_substr($text, $start, $length);
    }

    return $length === null ? substr($text, $start) : substr($text, $start, $length);
}

function leadToUpper(string $text): string
{
    if (function_exists('mb_strtoupper')) {
        return mb_strtoupper($text);
    }

    return strtoupper($text);
}

function leadInitials(?string $name): string
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'NA';
    }

    $parts = preg_split('/\s+/u', $name) ?: [];
    $initials = '';

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        $initials .= leadToUpper(leadSubstring($part, 0, 1));

        if (leadStringLength($initials) >= 2) {
            break;
        }
    }

    if ($initials === '') {
        $initials = leadToUpper(leadSubstring($name, 0, 1));
    }

    if (leadStringLength($initials) > 2) {
        $initials = leadSubstring($initials, 0, 2);
    }

    return $initials;
}

function avatarPalette(?string $seed): array
{
    $seed = trim((string) $seed);

    if ($seed === '') {
        $seed = 'default';
    }

    $palettes = [
        ['background' => 'linear-gradient(135deg, #e0f2fe, #bae6fd)', 'color' => '#0369a1'],
        ['background' => 'linear-gradient(135deg, #ede9fe, #ddd6fe)', 'color' => '#5b21b6'],
        ['background' => 'linear-gradient(135deg, #fce7f3, #fbcfe8)', 'color' => '#be185d'],
        ['background' => 'linear-gradient(135deg, #dcfce7, #bbf7d0)', 'color' => '#15803d'],
        ['background' => 'linear-gradient(135deg, #fef3c7, #fde68a)', 'color' => '#b45309'],
        ['background' => 'linear-gradient(135deg, #fee2e2, #fecaca)', 'color' => '#b91c1c'],
        ['background' => 'linear-gradient(135deg, #f1f5f9, #e2e8f0)', 'color' => '#0f172a'],
    ];

    $index = abs(crc32(strtolower($seed))) % count($palettes);

    return $palettes[$index];
}

function ratingValue(?string $ratingText): ?float
{
    if ($ratingText === null) {
        return null;
    }

    $ratingText = trim((string) $ratingText);

    if ($ratingText === '') {
        return null;
    }

    if (is_numeric($ratingText)) {
        $value = (float) $ratingText;

        return max(0.0, min(5.0, $value));
    }

    $normalized = strtolower($ratingText);

    return match (true) {
        str_contains($normalized, 'hot') => 5.0,
        str_contains($normalized, 'warm') => 4.0,
        str_contains($normalized, 'qualified') => 4.0,
        str_contains($normalized, 'cold') => 2.5,
        str_contains($normalized, 'new') => 2.0,
        default => null,
    };
}

function sourceBadgeClass(?string $source): string
{
    $normalized = strtolower(trim((string) $source));

    return match (true) {
        $normalized === '' => 'source-badge-default',
        str_contains($normalized, 'social') => 'source-badge-social',
        str_contains($normalized, 'web') => 'source-badge-website',
        str_contains($normalized, 'walk') => 'source-badge-walkin',
        str_contains($normalized, 'referral') => 'source-badge-referral',
        str_contains($normalized, 'email') => 'source-badge-email',
        default => 'source-badge-default',
    };
}

include __DIR__ . '/includes/common-header.php';
?>
<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <div class="container-fluid px-0">
            <style>
                body.lead-sidebar-open {
                    overflow: hidden;
                }

                .lead-sidebar-overlay {
                    position: fixed;
                    inset: 0;
                    background: rgba(15, 23, 42, 0.45);
                    opacity: 0;
                    pointer-events: none;
                    transition: opacity 0.3s ease;
                    z-index: 1040;
                }

                .lead-sidebar-overlay.active {
                    opacity: 1;
                    pointer-events: auto;
                }

                .lead-sidebar {
                    position: fixed;
                    top: 0;
                    right: 0;
                    height: 100vh;
                    width: 50vw;
                    max-width: 100%;
                    background: #fff;
                    box-shadow: -12px 0 30px rgba(15, 23, 42, 0.08);
                    transform: translateX(100%);
                    transition: transform 0.35s ease;
                    z-index: 1050;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }

                .lead-sidebar.open {
                    transform: translateX(0);
                }

                .lead-sidebar-header {
                    padding: 24px;
                    border-bottom: 1px solid rgba(15, 23, 42, 0.08);
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 12px;
                }

                .lead-sidebar-close {
                    border: 0;
                    background: transparent;
                    color: #64748b;
                    font-size: 22px;
                    line-height: 1;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border-radius: 999px;
                    width: 34px;
                    height: 34px;
                    transition: background 0.2s ease, color 0.2s ease;
                }

                .lead-sidebar-close:hover {
                    background: rgba(15, 23, 42, 0.05);
                    color: #0f172a;
                }

                .lead-sidebar-body {
                    padding: 24px;
                    overflow-y: auto;
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    gap: 24px;
                }

                .lead-sidebar-actions {
                    display: flex;
                    gap: 12px;
                    flex-wrap: wrap;
                }

                .lead-sidebar-actions .btn {
                    flex: 1 1 110px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    font-weight: 600;
                }

                .lead-sidebar-section {
                    background: #f8fafc;
                    border-radius: 16px;
                    padding: 20px;
                    display: flex;
                    flex-direction: column;
                    gap: 16px;
                }

                .lead-sidebar-section h3 {
                    font-size: 16px;
                    font-weight: 700;
                    margin: 0;
                    color: #0f172a;
                }

                .lead-sidebar-list {
                    list-style: none;
                    padding: 0;
                    margin: 0;
                    display: flex;
                    flex-direction: column;
                    gap: 14px;
                }

                .lead-sidebar-list li {
                    display: flex;
                    align-items: flex-start;
                    gap: 12px;
                }

                .lead-sidebar-list .label {
                    font-size: 12px;
                    text-transform: uppercase;
                    letter-spacing: 0.08em;
                    color: #94a3b8;
                    font-weight: 600;
                }

                .lead-sidebar-list .value {
                    font-size: 15px;
                    color: #0f172a;
                    font-weight: 600;
                    word-break: break-word;
                }

                .lead-sidebar-list .detail-icon {
                    flex-shrink: 0;
                    width: 36px;
                    height: 36px;
                    border-radius: 10px;
                    background: rgba(15, 23, 42, 0.05);
                    color: #0f172a;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 18px;
                }

                .lead-sidebar-list .detail-content {
                    display: flex;
                    flex-direction: column;
                    gap: 4px;
                    flex: 1;
                }

                .lead-sidebar-rating {
                    display: flex;
                    align-items: center;
                    gap: 8px;
                    color: #f59e0b;
                    font-size: 18px;
                }

                .lead-sidebar-rating span {
                    font-size: 14px;
                    color: #0f172a;
                    font-weight: 600;
                }

                .lead-sidebar-empty {
                    color: #94a3b8;
                    font-size: 14px;
                }

                @media (max-width: 768px) {
                    .lead-sidebar {
                        width: min(100%, 100vw);
                    }

                    .lead-sidebar.open {
                        transform: translateX(0) translateY(0);
                    }
                }
            </style>
            <div class="page-header d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
                <div>
                    <h1 class="mb-1">All Leads</h1>
                    <p class="text-muted mb-0">Monitor every lead in your pipeline and take quick actions.</p>
                </div>
                <a href="add-leads.php" class="btn btn-primary d-flex align-items-center gap-2">
                    <i class="bx bx-plus"></i>
                    <span>Add Lead</span>
                </a>
            </div>

            <div class="row g-3 lead-summary-row mb-4">
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card leads-stats-card">
                        <div class="stats-card-icon total">
                            <i class="bx bx-group"></i>
                        </div>
                        <div class="stats-card-body">
                            <span class="stats-label">Total Leads</span>
                            <h2 class="mb-0"><?php echo number_format($totalLeads); ?></h2>
                        </div>
                        <div class="stats-card-footer">
                            <?php echo renderTrend($totalTrend); ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card leads-stats-card">
                        <div class="stats-card-icon active">
                            <i class="bx bx-target-lock"></i>
                        </div>
                        <div class="stats-card-body">
                            <span class="stats-label">Active Leads</span>
                            <h2 class="mb-0"><?php echo number_format($activeLeads); ?></h2>
                        </div>
                        <div class="stats-card-footer">
                            <?php echo renderTrend($activeTrend); ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-4 col-md-6">
                    <div class="stats-card leads-stats-card">
                        <div class="stats-card-icon conversion">
                            <i class="bx bx-line-chart"></i>
                        </div>
                        <div class="stats-card-body">
                            <span class="stats-label">Conversion Rate</span>
                            <h2 class="mb-0"><?php echo $totalLeads > 0 ? $conversionRate . '%' : '0%'; ?></h2>
                        </div>
                        <div class="stats-card-footer">
                            <?php echo renderTrend($conversionTrend); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="lead-filters-card mb-4">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-12 col-lg-4">
                        <label for="search" class="form-label">Search</label>
                        <div class="input-with-icon">
                            <span class="input-icon"><i class="bx bx-search"></i></span>
                            <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, email or phone" value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="stage" class="form-label">Stage</label>
                        <select id="stage" name="stage" class="form-select" data-choices>
                            <option value="all" <?php echo $stageFilter === 'all' ? 'selected' : ''; ?>>All Stages</option>
                            <?php foreach ($distinctStages as $stageOption): ?>
                                <option value="<?php echo htmlspecialchars($stageOption); ?>" <?php echo $stageFilter === $stageOption ? 'selected' : ''; ?>><?php echo htmlspecialchars($stageOption); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-3">
                        <label for="sort" class="form-label">Sort By</label>
                        <select id="sort" name="sort" class="form-select" data-choices>
                            <option value="latest" <?php echo $sortOption === 'latest' ? 'selected' : ''; ?>>Latest First</option>
                            <option value="oldest" <?php echo $sortOption === 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="name-asc" <?php echo $sortOption === 'name-asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                            <option value="name-desc" <?php echo $sortOption === 'name-desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 col-lg-2">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn btn-primary flex-fill">Apply Filters</button>
                            <a href="all-leads.php" class="btn btn-outline-secondary flex-fill">Reset</a>
                        </div>
                    </div>
                </form>
                <div class="lead-results-count mt-3">
                    Showing <strong><?php echo number_format($totalLeads); ?></strong> <?php echo $totalLeads === 1 ? 'lead' : 'leads'; ?>
                    <?php if ($searchTerm !== '' || ($stageFilter !== 'all' && $stageFilter !== '') || $sortOption !== 'latest'): ?>
                        <a class="reset-filters" href="all-leads.php">Reset filters</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card lead-table-card">
                <div class="card-body p-0">
                    <div class="">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Stage</th>
                                    <th scope="col">Assigned To</th>
                                    <th scope="col">Source</th>
                                    <th scope="col" class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($leads)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <div class="empty-state">
                                                <i class="bx bx-package"></i>
                                                <p class="mb-1">No leads found</p>
                                                <small>Try adjusting your filters or add a new lead to get started.</small>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($leads as $lead): ?>
                                        <?php
                                        $stageText = $lead['stage'] ?? '';
                                        $ratingText = $lead['rating'] ?? '';
                                        $createdAt = $lead['created_at'] ?? null;
                                        $formattedDate = '—';
                                        if ($createdAt) {
                                            $timestamp = DateTime::createFromFormat('Y-m-d H:i:s', $createdAt) ?: DateTime::createFromFormat('Y-m-d', $createdAt);
                                            if ($timestamp) {
                                                $formattedDate = $timestamp->format('d M, Y');
                                            }
                                        }

                                        $leadName = $lead['name'] ?? 'Untitled Lead';
                                        $leadPalette = avatarPalette($leadName);
                                        $leadInitials = leadInitials($leadName);
                                        $contactEmail = trim((string) ($lead['email'] ?? ''));
                                        $contactPhone = trim((string) ($lead['phone'] ?? ''));
                                        $alternatePhone = trim((string) ($lead['alternate_phone'] ?? ''));

                                        $ratingValue = ratingValue($ratingText);
                                        $ratingDisplay = '';
                                        if ($ratingValue !== null) {
                                            if ($ratingText !== '' && !is_numeric($ratingText)) {
                                                $ratingDisplay = (string) $ratingText;
                                            } elseif ($ratingText !== '') {
                                                $ratingDisplay = number_format((float) $ratingText, 1) . '/5';
                                            } else {
                                                $ratingDisplay = number_format($ratingValue, 1) . '/5';
                                            }
                                        } elseif ($ratingText !== '') {
                                            $ratingDisplay = (string) $ratingText;
                                        }

                                        $assignedName = trim((string) ($lead['assigned_to'] ?? ''));
                                        $assignedPalette = avatarPalette($assignedName !== '' ? $assignedName : 'Unassigned');
                                        $assignedInitials = $assignedName !== '' ? leadInitials($assignedName) : leadInitials('Unassigned');

                                        $sourceText = trim((string) ($lead['source'] ?? ''));
                                        $sourceDisplay = $sourceText !== '' ? $sourceText : 'Unknown';
                                        $sourceClass = sourceBadgeClass($sourceText);
                                        $leadCountry = trim((string) ($lead['nationality'] ?? ''));
                                        $leadCreatedAt = trim((string) ($lead['created_at'] ?? ''));
                                        $leadAlternateEmail = trim((string) ($lead['alternate_email'] ?? ''));
                                        $leadInterestedIn = trim((string) ($lead['interested_in'] ?? ''));
                                        $leadPropertyType = trim((string) ($lead['property_type'] ?? ''));
                                        $leadLocationPreferences = trim((string) ($lead['location_preferences'] ?? ''));
                                        $leadBudgetRange = trim((string) ($lead['budget_range'] ?? ''));
                                        $leadSizeRequired = trim((string) ($lead['size_required'] ?? ''));
                                        $leadPurpose = trim((string) ($lead['purpose'] ?? ''));
                                        $leadUrgency = trim((string) ($lead['urgency'] ?? ''));
                                        $leadPayoutReceived = trim((string) ($lead['payout_received'] ?? ''));
                                        ?>
                                        <tr class="lead-row"
                                            data-lead-id="<?php echo (int) $lead['id']; ?>"
                                            data-lead-name="<?php echo htmlspecialchars($leadName, ENT_QUOTES); ?>"
                                            data-lead-stage="<?php echo htmlspecialchars($stageText, ENT_QUOTES); ?>"
                                            data-lead-stage-class="<?php echo htmlspecialchars(stageBadgeClass($stageText), ENT_QUOTES); ?>"
                                            data-lead-rating-text="<?php echo htmlspecialchars((string) $ratingText, ENT_QUOTES); ?>"
                                            data-lead-rating-value="<?php echo htmlspecialchars($ratingValue !== null ? (string) $ratingValue : '', ENT_QUOTES); ?>"
                                            data-lead-rating-display="<?php echo htmlspecialchars($ratingDisplay, ENT_QUOTES); ?>"
                                            data-lead-email="<?php echo htmlspecialchars($contactEmail, ENT_QUOTES); ?>"
                                            data-lead-alternate-email="<?php echo htmlspecialchars($leadAlternateEmail, ENT_QUOTES); ?>"
                                            data-lead-phone="<?php echo htmlspecialchars($contactPhone, ENT_QUOTES); ?>"
                                            data-lead-alternate-phone="<?php echo htmlspecialchars($alternatePhone, ENT_QUOTES); ?>"
                                            data-lead-nationality="<?php echo htmlspecialchars($leadCountry, ENT_QUOTES); ?>"
                                            data-lead-assigned="<?php echo htmlspecialchars($assignedName, ENT_QUOTES); ?>"
                                            data-lead-source="<?php echo htmlspecialchars($sourceDisplay, ENT_QUOTES); ?>"
                                            data-lead-created-at="<?php echo htmlspecialchars($leadCreatedAt, ENT_QUOTES); ?>"
                                            data-lead-created-at-formatted="<?php echo htmlspecialchars($formattedDate, ENT_QUOTES); ?>"
                                            data-lead-interested-in="<?php echo htmlspecialchars($leadInterestedIn, ENT_QUOTES); ?>"
                                            data-lead-property-type="<?php echo htmlspecialchars($leadPropertyType, ENT_QUOTES); ?>"
                                            data-lead-location-preferences="<?php echo htmlspecialchars($leadLocationPreferences, ENT_QUOTES); ?>"
                                            data-lead-budget-range="<?php echo htmlspecialchars($leadBudgetRange, ENT_QUOTES); ?>"
                                            data-lead-size-required="<?php echo htmlspecialchars($leadSizeRequired, ENT_QUOTES); ?>"
                                            data-lead-purpose="<?php echo htmlspecialchars($leadPurpose, ENT_QUOTES); ?>"
                                            data-lead-urgency="<?php echo htmlspecialchars($leadUrgency, ENT_QUOTES); ?>"
                                            data-lead-payout-received="<?php echo htmlspecialchars($leadPayoutReceived, ENT_QUOTES); ?>"
                                        >
                                            <td>
                                                <div class="lead-profile d-flex align-items-center gap-3">
                                                    <div class="lead-avatar" style="background: <?php echo htmlspecialchars($leadPalette['background']); ?>; color: <?php echo htmlspecialchars($leadPalette['color']); ?>;">
                                                        <span><?php echo htmlspecialchars($leadInitials); ?></span>
                                                    </div>
                                                    <div class="lead-info">
                                                        <div class="lead-title"><?php echo htmlspecialchars($leadName); ?></div>
                                                        <div class="lead-country<?php echo $leadCountry === '' ? ' text-muted' : ''; ?>">
                                                            <span><?php echo $leadCountry !== '' ? htmlspecialchars($leadCountry) : 'Country not provided'; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="lead-contact">
                                                    <?php if ($contactEmail !== ''): ?>
                                                        <a href="javascript:void(0)" class="contact-item">
                                                            <i class="bx bx-envelope"></i>
                                                            <span><?php echo htmlspecialchars($contactEmail); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if ($contactPhone !== ''): ?>
                                                        <div class="contact-item text-muted">
                                                            <i class="bx bx-phone"></i>
                                                            <span><?php echo htmlspecialchars($contactPhone); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($alternatePhone !== '' && $alternatePhone !== $contactPhone): ?>
                                                        <div class="contact-item text-muted">
                                                            <i class="bx bx-phone-call"></i>
                                                            <span><?php echo htmlspecialchars($alternatePhone); ?></span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="status-badge <?php echo stageBadgeClass($stageText); ?>"><?php echo $stageText !== '' ? htmlspecialchars($stageText) : 'Not set'; ?></span>
                                            </td>
                                            <td>
                                                <?php if ($assignedName !== ''): ?>
                                                    <div class="assigned-user d-flex align-items-center gap-2">
                                                        <div class="assigned-avatar" style="background: <?php echo htmlspecialchars($assignedPalette['background']); ?>; color: <?php echo htmlspecialchars($assignedPalette['color']); ?>;">
                                                            <span><?php echo htmlspecialchars($assignedInitials); ?></span>
                                                        </div>
                                                        <div class="assigned-name"><?php echo htmlspecialchars($assignedName); ?></div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Unassigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="source-badge <?php echo htmlspecialchars($sourceClass); ?>">
                                                    <i class="bx bx-share-alt"></i>
                                                    <?php echo htmlspecialchars($sourceDisplay); ?>
                                                </span>
                                            </td>
                                            <td class="text-end">
                                                <div class="lead-actions">
                                                    <button class="btn btn-actions" type="button" data-action-toggle="actions-<?php echo (int) $lead['id']; ?>" aria-expanded="false" aria-controls="actions-<?php echo (int) $lead['id']; ?>">
                                                        <i class="bx bx-dots-vertical-rounded"></i>
                                                        <span class="visually-hidden">Toggle actions</span>
                                                    </button>
                                                    <div class="actions-menu" id="actions-<?php echo (int) $lead['id']; ?>">
                                                        <a class="action-item" href="#">
                                                            <i class="bx bx-show-alt"></i>
                                                            <span>View Details</span>
                                                        </a>
                                                        <a class="action-item" href="#">
                                                            <i class="bx bx-edit"></i>
                                                            <span>Edit Lead</span>
                                                        </a>
                                                        <a class="action-item" href="#">
                                                            <i class="bx bx-git-compare"></i>
                                                            <span>Change Stage</span>
                                                        </a>
                                                        <a class="action-item" href="#">
                                                            <i class="bx bx-user-plus"></i>
                                                            <span>Assign To</span>
                                                        </a>
                                                        <a class="action-item text-danger" href="#">
                                                            <i class="bx bx-trash"></i>
                                                            <span>Delete Lead</span>
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="lead-sidebar-overlay" data-lead-sidebar-overlay hidden></div>
        <aside class="lead-sidebar" data-lead-sidebar aria-hidden="true">
            <div class="lead-sidebar-header">
                <div class="lead-sidebar-title">
                    <h2 class="mb-1" data-lead-field="name">Lead Name</h2>
                    <div class="d-flex flex-wrap align-items-center gap-2">
                        <span class="status-badge" data-lead-field="stage">Stage</span>
                        <div class="lead-sidebar-rating" data-lead-field="rating-container" hidden>
                            <div class="lead-sidebar-stars" data-lead-field="rating-stars"></div>
                            <span data-lead-field="rating-value"></span>
                        </div>
                    </div>
                    <div class="text-muted mt-2" data-lead-field="assigned">Assigned to —</div>
                </div>
                <button type="button" class="lead-sidebar-close" data-lead-sidebar-close aria-label="Close lead details">
                    <i class="bx bx-x"></i>
                </button>
            </div>
            <div class="lead-sidebar-body">
                <div class="lead-sidebar-actions">
                    <a class="btn btn-success" data-lead-action="call" href="#" target="_self">
                        <i class="bx bx-phone"></i>
                        Call
                    </a>
                    <a class="btn btn-info text-white" data-lead-action="email" href="#" target="_self">
                        <i class="bx bx-envelope"></i>
                        Email
                    </a>
                    <a class="btn btn-success" style="background: #25D366; border-color: #25D366;" data-lead-action="whatsapp" href="#" target="_blank">
                        <i class="bx bxl-whatsapp"></i>
                        WhatsApp
                    </a>
                </div>

                <section class="lead-sidebar-section">
                    <h3>Lead Details</h3>
                    <ul class="lead-sidebar-list">
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-envelope"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Email</span>
                                <span class="value" data-lead-field="email">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-phone"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Phone</span>
                                <span class="value" data-lead-field="phone">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-phone-call"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Alternate Phone</span>
                                <span class="value" data-lead-field="alternate-phone">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-globe"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Nationality</span>
                                <span class="value" data-lead-field="nationality">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-share-alt"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Source</span>
                                <span class="value" data-lead-field="source">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-calendar"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Created</span>
                                <span class="value" data-lead-field="created-at">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-wallet"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Payout Received</span>
                                <span class="value" data-lead-field="payout-received">Not provided</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="lead-sidebar-section">
                    <h3>Property Requirements</h3>
                    <ul class="lead-sidebar-list">
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-heart"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Interested In</span>
                                <span class="value" data-lead-field="interested-in">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-home"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Property Type</span>
                                <span class="value" data-lead-field="property-type">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-map"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Location Preferences</span>
                                <span class="value" data-lead-field="location-preferences">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-money"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Budget Range</span>
                                <span class="value" data-lead-field="budget-range">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-ruler"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Size Required</span>
                                <span class="value" data-lead-field="size-required">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-bullseye"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Purpose</span>
                                <span class="value" data-lead-field="purpose">Not provided</span>
                            </div>
                        </li>
                        <li>
                            <span class="detail-icon">
                                <i class="bx bx-timer"></i>
                            </span>
                            <div class="detail-content">
                                <span class="label">Urgency</span>
                                <span class="value" data-lead-field="urgency">Not provided</span>
                            </div>
                        </li>
                    </ul>
                </section>

                <section class="lead-sidebar-section">
                    <h3>Remarks &amp; Updates</h3>
                    <p class="lead-sidebar-empty mb-0">No remarks recorded yet.</p>
                </section>
            </div>
        </aside>
    </main>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const actionContainers = document.querySelectorAll('.lead-actions');

        function closeAllMenus(except) {
            actionContainers.forEach(function(container) {
                if (container === except) {
                    return;
                }

                container.classList.remove('is-open');
                const button = container.querySelector('[data-action-toggle]');

                if (button) {
                    button.setAttribute('aria-expanded', 'false');
                }
            });
        }

        actionContainers.forEach(function(container) {
            const toggleButton = container.querySelector('[data-action-toggle]');
            const menu = container.querySelector('.actions-menu');

            if (!toggleButton || !menu) {
                return;
            }

            toggleButton.addEventListener('click', function(event) {
                event.preventDefault();
                event.stopPropagation();

                const isOpen = container.classList.contains('is-open');
                if (isOpen) {
                    container.classList.remove('is-open');
                    toggleButton.setAttribute('aria-expanded', 'false');
                } else {
                    closeAllMenus(container);
                    container.classList.add('is-open');
                    toggleButton.setAttribute('aria-expanded', 'true');
                }
            });
        });

        document.addEventListener('click', function() {
            closeAllMenus();
        });

        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAllMenus();
            }
        });

        const leadRows = document.querySelectorAll('.lead-row');
        const sidebar = document.querySelector('[data-lead-sidebar]');
        const overlay = document.querySelector('[data-lead-sidebar-overlay]');
        const closeSidebarButton = sidebar ? sidebar.querySelector('[data-lead-sidebar-close]') : null;

        if (sidebar && overlay && leadRows.length > 0) {
            const fields = {
                name: sidebar.querySelector('[data-lead-field="name"]'),
                stage: sidebar.querySelector('[data-lead-field="stage"]'),
                ratingContainer: sidebar.querySelector('[data-lead-field="rating-container"]'),
                ratingStars: sidebar.querySelector('[data-lead-field="rating-stars"]'),
                ratingValue: sidebar.querySelector('[data-lead-field="rating-value"]'),
                assigned: sidebar.querySelector('[data-lead-field="assigned"]'),
                email: sidebar.querySelector('[data-lead-field="email"]'),
                phone: sidebar.querySelector('[data-lead-field="phone"]'),
                alternatePhone: sidebar.querySelector('[data-lead-field="alternate-phone"]'),
                nationality: sidebar.querySelector('[data-lead-field="nationality"]'),
                source: sidebar.querySelector('[data-lead-field="source"]'),
                createdAt: sidebar.querySelector('[data-lead-field="created-at"]'),
                payoutReceived: sidebar.querySelector('[data-lead-field="payout-received"]'),
                interestedIn: sidebar.querySelector('[data-lead-field="interested-in"]'),
                propertyType: sidebar.querySelector('[data-lead-field="property-type"]'),
                locationPreferences: sidebar.querySelector('[data-lead-field="location-preferences"]'),
                budgetRange: sidebar.querySelector('[data-lead-field="budget-range"]'),
                sizeRequired: sidebar.querySelector('[data-lead-field="size-required"]'),
                purpose: sidebar.querySelector('[data-lead-field="purpose"]'),
                urgency: sidebar.querySelector('[data-lead-field="urgency"]')
            };

            const actions = {
                call: sidebar.querySelector('[data-lead-action="call"]'),
                email: sidebar.querySelector('[data-lead-action="email"]'),
                whatsapp: sidebar.querySelector('[data-lead-action="whatsapp"]')
            };

            function displayValue(field, value, fallback = 'Not provided') {
                if (!field) {
                    return;
                }

                const safeValue = typeof value === 'string' ? value.trim() : '';
                if (safeValue !== '') {
                    field.textContent = safeValue;
                    field.classList.remove('text-muted');
                } else {
                    field.textContent = fallback;
                    field.classList.add('text-muted');
                }
            }

            function normalizePhone(value) {
                const digits = (value || '').replace(/[^+\d]/g, '');
                return digits;
            }

            function setAction(button, type, value) {
                if (!button) {
                    return;
                }

                const safeValue = typeof value === 'string' ? value.trim() : '';

                if (safeValue === '') {
                    button.classList.add('disabled');
                    button.setAttribute('aria-disabled', 'true');
                    button.setAttribute('tabindex', '-1');
                    button.href = '#';
                    return;
                }

                button.classList.remove('disabled');
                button.removeAttribute('aria-disabled');
                button.setAttribute('tabindex', '0');

                if (type === 'call') {
                    button.href = 'tel:' + normalizePhone(safeValue);
                } else if (type === 'email') {
                    button.href = 'mailto:' + safeValue;
                } else if (type === 'whatsapp') {
                    const digits = safeValue.replace(/\D/g, '');
                    button.href = digits ? 'https://wa.me/' + digits : '#';
                    if (!digits) {
                        button.classList.add('disabled');
                        button.setAttribute('aria-disabled', 'true');
                        button.setAttribute('tabindex', '-1');
                    }
                }
            }

            function renderStars(value) {
                if (!fields.ratingStars) {
                    return;
                }

                const maxStars = 5;
                const numeric = Math.max(0, Math.min(maxStars, value));
                const fullStars = Math.floor(numeric);
                const hasHalf = numeric - fullStars >= 0.5 && fullStars < maxStars;

                let stars = '';
                for (let i = 0; i < fullStars; i++) {
                    stars += '<i class="bx bxs-star"></i>';
                }

                if (hasHalf) {
                    stars += '<i class="bx bxs-star-half"></i>';
                }

                const remaining = maxStars - fullStars - (hasHalf ? 1 : 0);
                for (let i = 0; i < remaining; i++) {
                    stars += '<i class="bx bx-star"></i>';
                }

                fields.ratingStars.innerHTML = stars;
            }

            function openSidebar(row) {
                closeAllMenus();
                const data = row.dataset;

                if (fields.name) {
                    fields.name.textContent = data.leadName && data.leadName.trim() !== '' ? data.leadName : 'Unnamed Lead';
                }

                if (fields.stage) {
                    const stageClass = data.leadStageClass ? 'status-badge ' + data.leadStageClass : 'status-badge badge-stage-default';
                    fields.stage.className = stageClass;
                    fields.stage.textContent = data.leadStage && data.leadStage.trim() !== '' ? data.leadStage : 'Not set';
                }

                if (fields.assigned) {
                    const assignedText = data.leadAssigned && data.leadAssigned.trim() !== '' ? 'Assigned to ' + data.leadAssigned : 'Unassigned';
                    fields.assigned.textContent = assignedText;
                }

                const ratingValueRaw = parseFloat(data.leadRatingValue || '');
                const ratingDisplay = data.leadRatingDisplay ? data.leadRatingDisplay.trim() : '';

                if (!Number.isNaN(ratingValueRaw) && ratingValueRaw > 0) {
                    if (fields.ratingContainer) {
                        fields.ratingContainer.hidden = false;
                    }
                    renderStars(ratingValueRaw);
                    if (fields.ratingValue) {
                        fields.ratingValue.textContent = ratingDisplay !== '' ? ratingDisplay : ratingValueRaw.toFixed(1) + '/5';
                    }
                } else if (ratingDisplay !== '') {
                    if (fields.ratingContainer) {
                        fields.ratingContainer.hidden = false;
                    }
                    if (fields.ratingStars) {
                        fields.ratingStars.innerHTML = '<i class="bx bx-star"></i>'.repeat(5);
                    }
                    if (fields.ratingValue) {
                        fields.ratingValue.textContent = ratingDisplay;
                    }
                } else if (fields.ratingContainer) {
                    fields.ratingContainer.hidden = true;
                    if (fields.ratingStars) {
                        fields.ratingStars.innerHTML = '';
                    }
                    if (fields.ratingValue) {
                        fields.ratingValue.textContent = '';
                    }
                }

                displayValue(fields.email, data.leadEmail || data.leadAlternateEmail || '');
                displayValue(fields.phone, data.leadPhone || '');
                displayValue(fields.alternatePhone, data.leadAlternatePhone || '');
                displayValue(fields.nationality, data.leadNationality || '');
                displayValue(fields.source, data.leadSource || '');

                const createdAtDisplay = data.leadCreatedAtFormatted && data.leadCreatedAtFormatted !== '—' ? data.leadCreatedAtFormatted : data.leadCreatedAt || '';
                displayValue(fields.createdAt, createdAtDisplay, 'Not provided');

                displayValue(fields.payoutReceived, data.leadPayoutReceived || '');
                displayValue(fields.interestedIn, data.leadInterestedIn || '');
                displayValue(fields.propertyType, data.leadPropertyType || '');
                displayValue(fields.locationPreferences, data.leadLocationPreferences || '');
                displayValue(fields.budgetRange, data.leadBudgetRange || '');
                displayValue(fields.sizeRequired, data.leadSizeRequired || '');
                displayValue(fields.purpose, data.leadPurpose || '');
                displayValue(fields.urgency, data.leadUrgency || '');

                setAction(actions.call, 'call', data.leadPhone || data.leadAlternatePhone || '');
                setAction(actions.email, 'email', data.leadEmail || data.leadAlternateEmail || '');
                setAction(actions.whatsapp, 'whatsapp', data.leadPhone || data.leadAlternatePhone || '');

                overlay.hidden = false;
                requestAnimationFrame(function() {
                    overlay.classList.add('active');
                    sidebar.classList.add('open');
                    sidebar.setAttribute('aria-hidden', 'false');
                    document.body.classList.add('lead-sidebar-open');
                });
            }

            function closeSidebar() {
                sidebar.classList.remove('open');
                sidebar.setAttribute('aria-hidden', 'true');
                overlay.classList.remove('active');
                document.body.classList.remove('lead-sidebar-open');

                const hideOverlay = function(event) {
                    if (event && event.target !== overlay) {
                        return;
                    }

                    if (!sidebar.classList.contains('open')) {
                        overlay.hidden = true;
                    }

                    overlay.removeEventListener('transitionend', hideOverlay);
                };

                overlay.addEventListener('transitionend', hideOverlay);
                window.setTimeout(hideOverlay, 400);
            }

            leadRows.forEach(function(row) {
                row.addEventListener('click', function(event) {
                    if (event.target.closest('.lead-actions')) {
                        return;
                    }

                    openSidebar(row);
                });
            });

            if (closeSidebarButton) {
                closeSidebarButton.addEventListener('click', function() {
                    closeSidebar();
                });
            }

            overlay.addEventListener('click', function(event) {
                if (event.target === overlay) {
                    closeSidebar();
                }
            });

            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && sidebar.classList.contains('open')) {
                    closeSidebar();
                }
            });
        }
    });
</script>
<?php include __DIR__ . '/includes/common-footer.php'; ?>
