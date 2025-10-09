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
                <div class="col-xl-3 col-md-6">
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
                <div class="col-xl-3 col-md-6">
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
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card leads-stats-card">
                        <div class="stats-card-icon conversion">
                            <i class="bx bx-line-chart"></i>
                        </div>
                        <div class="stats-card-body">
                            <span class="stats-label">Conversion Rate</span>
                            <h2 class="mb-0"><?php echo $totalLeads > 0 ? $conversionRate . '%': '0%'; ?></h2>
                        </div>
                        <div class="stats-card-footer">
                            <?php echo renderTrend($conversionTrend); ?>
                        </div>
                    </div>
                </div>
                <div class="col-xl-3 col-md-6">
                    <div class="stats-card leads-stats-card">
                        <div class="stats-card-icon rating">
                            <i class="bx bx-star"></i>
                        </div>
                        <div class="stats-card-body">
                            <span class="stats-label">Avg Rating</span>
                            <h2 class="mb-0"><?php echo $averageRating !== null ? $averageRating : '—'; ?></h2>
                        </div>
                        <div class="stats-card-footer">
                            <span class="trend trend-neutral">Updated in real time</span>
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
                <div class="card-header border-0 pb-0">
                    <div class="lead-table-header d-flex flex-wrap gap-3 align-items-center justify-content-between">
                        <div>
                            <h5 class="mb-1">Leads Overview</h5>
                            <p class="text-muted mb-0">Stay on top of every prospect with quick insights and smart sorting.</p>
                        </div>
                        <div class="table-header-meta d-flex flex-wrap align-items-center gap-3">
                            <div class="badge rounded-pill bg-light text-body-tertiary px-3 py-2">
                                <i class="bx bx-time-five me-1"></i>Updated just now
                            </div>
                            <a href="add-leads.php" class="btn btn-outline-primary btn-sm d-flex align-items-center gap-2">
                                <i class="bx bx-plus"></i>
                                <span>Quick Add</span>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Stage</th>
                                    <th scope="col">Rating</th>
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
        $leadTag = trim((string) ($lead['interested_in'] ?? ''));
        if ($leadTag === '') {
            $leadTag = trim((string) ($lead['property_type'] ?? ''));
        }
        if ($leadTag === '') {
            $leadTag = trim((string) ($lead['purpose'] ?? ''));
        }

        $contactEmail = trim((string) ($lead['email'] ?? ''));
        $contactPhone = trim((string) ($lead['phone'] ?? ''));
        $alternatePhone = trim((string) ($lead['alternate_phone'] ?? ''));

        $ratingValue = ratingValue($ratingText);
        $ratingLabel = '';
        if ($ratingValue !== null) {
            if ($ratingText !== '' && !is_numeric($ratingText)) {
                $ratingLabel = (string) $ratingText;
            } elseif ($ratingText !== '') {
                $ratingLabel = number_format((float) $ratingText, 1) . '/5';
            } else {
                $ratingLabel = number_format($ratingValue, 1) . '/5';
            }
        }

        $assignedName = trim((string) ($lead['assigned_to'] ?? ''));
        $assignedPalette = avatarPalette($assignedName !== '' ? $assignedName : 'Unassigned');
        $assignedInitials = $assignedName !== '' ? leadInitials($assignedName) : leadInitials('Unassigned');

        $sourceText = trim((string) ($lead['source'] ?? ''));
        $sourceDisplay = $sourceText !== '' ? $sourceText : 'Unknown';
        $sourceClass = sourceBadgeClass($sourceText);
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="lead-profile d-flex align-items-center gap-3">
                                                    <div class="lead-avatar" style="background: <?php echo htmlspecialchars($leadPalette['background']); ?>; color: <?php echo htmlspecialchars($leadPalette['color']); ?>;">
                                                        <span><?php echo htmlspecialchars($leadInitials); ?></span>
                                                    </div>
                                                    <div class="lead-info">
                                                        <div class="lead-title"><?php echo htmlspecialchars($leadName); ?></div>
                                                        <div class="lead-meta">
                                                            <span class="lead-created">Created on <?php echo htmlspecialchars($formattedDate); ?></span>
                                                            <?php if ($leadTag !== ''): ?>
                                                                <span class="lead-tag"><?php echo htmlspecialchars($leadTag); ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="lead-contact">
                                                    <?php if ($contactEmail !== ''): ?>
                                                        <a href="mailto:<?php echo htmlspecialchars($contactEmail); ?>" class="contact-item">
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
                                                <?php if ($ratingValue !== null): ?>
                                                    <div class="rating-display">
                                                        <div class="rating-stars" aria-label="<?php echo htmlspecialchars('Rating ' . number_format($ratingValue, 1) . ' out of 5'); ?>">
                                                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                                                <?php if ($ratingValue >= $star): ?>
                                                                    <i class="bx bxs-star"></i>
                                                                <?php elseif ($ratingValue >= $star - 0.5): ?>
                                                                    <i class="bx bxs-star-half"></i>
                                                                <?php else: ?>
                                                                    <i class="bx bx-star"></i>
                                                                <?php endif; ?>
                                                            <?php endfor; ?>
                                                        </div>
                                                        <?php if ($ratingLabel !== ''): ?>
                                                            <span class="rating-text"><?php echo htmlspecialchars($ratingLabel); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php elseif ($ratingText !== ''): ?>
                                                    <span class="rating-badge <?php echo ratingBadgeClass((string) $ratingText); ?>"><?php echo htmlspecialchars((string) $ratingText); ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">—</span>
                                                <?php endif; ?>
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
                                                <div class="dropdown">
                                                    <button class="btn btn-actions" type="button" id="actions-<?php echo (int) $lead['id']; ?>" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <span>Actions</span>
                                                        <i class="bx bx-chevron-down"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="actions-<?php echo (int) $lead['id']; ?>">
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#">
                                                                <i class="bx bx-show-alt"></i>
                                                                <span>View Details</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#">
                                                                <i class="bx bx-edit"></i>
                                                                <span>Edit Lead</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#">
                                                                <i class="bx bx-git-compare"></i>
                                                                <span>Change Stage</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item d-flex align-items-center gap-2" href="#">
                                                                <i class="bx bx-user-plus"></i>
                                                                <span>Assign To</span>
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="#">
                                                                <i class="bx bx-trash"></i>
                                                                <span>Delete Lead</span>
                                                            </a>
                                                        </li>
                                                    </ul>
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
    </main>
</div>
<?php include __DIR__ . '/includes/common-footer.php'; ?>
