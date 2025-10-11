<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/common-header.php';

if (!isset($_SESSION['lead_delete_message'], $_SESSION['lead_delete_type'])) {
    $_SESSION['lead_delete_message'] = null;
    $_SESSION['lead_delete_type'] = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lead_id'])) {
    $deleteMessage = 'Unable to delete the selected lead. Please try again.';
    $deleteType = 'danger';

    $leadId = filter_input(INPUT_POST, 'delete_lead_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($leadId) {
        $deleteStatement = $mysqli->prepare('DELETE FROM all_leads WHERE id = ?');
        if ($deleteStatement instanceof mysqli_stmt) {
            $deleteStatement->bind_param('i', $leadId);
            if ($deleteStatement->execute()) {
                if ($deleteStatement->affected_rows > 0) {
                    $deleteMessage = 'Lead deleted successfully.';
                    $deleteType = 'success';
                } else {
                    $deleteMessage = 'Lead not found or already deleted.';
                    $deleteType = 'warning';
                }
            }
            $deleteStatement->close();
        }
    } else {
        $deleteMessage = 'Invalid lead selected for deletion.';
        $deleteType = 'danger';
    }

    $_SESSION['lead_delete_message'] = $deleteMessage;
    $_SESSION['lead_delete_type'] = $deleteType;

    header('Location: all-leads.php');
    exit;
}

$deleteFlashMessage = $_SESSION['lead_delete_message'] ?? null;
$deleteFlashType = $_SESSION['lead_delete_type'] ?? 'info';

$_SESSION['lead_delete_message'] = null;
$_SESSION['lead_delete_type'] = null;

$validAlertTypes = ['success', 'warning', 'danger', 'info'];
if (!in_array($deleteFlashType, $validAlertTypes, true)) {
    $deleteFlashType = 'info';
}

$users = [];
$usersQuery = $mysqli->query('SELECT id, full_name FROM users ORDER BY full_name ASC');
if ($usersQuery instanceof mysqli_result) {
    while ($userRow = $usersQuery->fetch_assoc()) {
        $users[(int) $userRow['id']] = $userRow['full_name'];
    }
    $usersQuery->free();
}

$loggedInUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$loggedInUserName = trim($_SESSION['username'] ?? '');

/**
 * Extract a displayable stage label from the stored stage value.
 */
function format_lead_stage(?string $rawStage): string
{
    if ($rawStage === null || trim($rawStage) === '') {
        return 'New';
    }

    $decoded = json_decode($rawStage, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (is_array($decoded)) {
            $first = reset($decoded);
            if (is_array($first)) {
                $first = reset($first);
            }
            if (is_string($first) && trim($first) !== '') {
                return trim($first);
            }
        } elseif (is_string($decoded) && trim($decoded) !== '') {
            return trim($decoded);
        }
    }

    $parts = array_filter(array_map('trim', explode(',', $rawStage)), static function ($part) {
        return $part !== '';
    });

    if (!empty($parts)) {
        $firstPart = (string) array_shift($parts);
        $firstPart = trim($firstPart, " \t\n\r\0\x0B\"'[]");
        if ($firstPart !== '') {
            return $firstPart;
        }
    }

    $cleaned = trim($rawStage, " \t\n\r\0\x0B\"[]");

    return $cleaned !== '' ? $cleaned : 'New';
}

function stage_badge_class(string $stage): string
{
    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $stage));
    return $slug !== '' ? $slug : 'new';
}

function normalize_stage_label(string $label): string
{
    $dashNormalized = str_replace([
        "\xE2\x80\x93", // en dash
        "\xE2\x80\x94", // em dash
        "\xE2\x88\x92", // minus sign
    ], '-', $label);

    $lowered = function_exists('mb_strtolower') ? mb_strtolower($dashNormalized, 'UTF-8') : strtolower($dashNormalized);
    $singleSpaced = preg_replace('/\s+/u', ' ', $lowered ?? '');

    return trim((string) $singleSpaced);
}

function lead_avatar_initial(string $name): string
{
    if (function_exists('mb_substr')) {
        return strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
    }

    return strtoupper(substr($name, 0, 1));
}

function build_lead_payload(array $lead): array
{
    $leadName = trim($lead['name'] ?? '') !== '' ? $lead['name'] : 'Unnamed Lead';
    $stageLabel = format_lead_stage($lead['stage'] ?? '');
    $stageClass = stage_badge_class($stageLabel);
    $leadEmail = trim((string) ($lead['email'] ?? ''));
    $leadPhone = trim((string) ($lead['phone'] ?? ''));
    $assignedTo = trim((string) ($lead['assigned_to'] ?? ''));
    $createdAtRaw = $lead['created_at'] ?? null;
    $createdAtDisplay = '—';
    if ($createdAtRaw) {
        $createdTimestamp = strtotime((string) $createdAtRaw);
        if ($createdTimestamp !== false) {
            $createdAtDisplay = date('M d, Y g:i A', $createdTimestamp);
        }
    }

    $leadTags = array_values(array_filter(array_map('trim', [
        $lead['purpose'] ?? '',
        $lead['urgency'] ?? '',
        $lead['size_required'] ?? '',
    ]), static function ($tag) {
        return $tag !== '';
    }));

    $historyEntries = [];
    if ($stageLabel !== '') {
        $historyEntries[] = [
            'description' => 'Stage set to ' . $stageLabel,
            'timestamp' => $createdAtDisplay,
        ];
    }
    if ($assignedTo !== '') {
        $historyEntries[] = [
            'description' => 'Assigned to ' . $assignedTo,
            'timestamp' => $createdAtDisplay,
        ];
    }
    $historyEntries[] = [
        'description' => 'Lead created',
        'timestamp' => $createdAtDisplay,
    ];

    return [
        'id' => isset($lead['id']) ? (int) $lead['id'] : null,
        'name' => $leadName,
        'stage' => $stageLabel,
        'stageClass' => $stageClass,
        'rating' => trim((string) ($lead['rating'] ?? '')),
        'phone' => $leadPhone,
        'alternatePhone' => trim((string) ($lead['alternate_phone'] ?? '')),
        'email' => $leadEmail,
        'alternateEmail' => trim((string) ($lead['alternate_email'] ?? '')),
        'nationality' => trim((string) ($lead['nationality'] ?? '')),
        'locationPreferences' => trim((string) ($lead['location_preferences'] ?? '')),
        'propertyType' => trim((string) ($lead['property_type'] ?? '')),
        'interestedIn' => trim((string) ($lead['interested_in'] ?? '')),
        'budgetRange' => trim((string) ($lead['budget_range'] ?? '')),
        'moveInTimeline' => trim((string) ($lead['urgency'] ?? '')),
        'propertiesInterestedIn' => trim((string) ($lead['location_preferences'] ?? '')),
        'purpose' => trim((string) ($lead['purpose'] ?? '')),
        'sizeRequired' => trim((string) ($lead['size_required'] ?? '')),
        'source' => trim((string) ($lead['source'] ?? '')) !== '' ? trim((string) ($lead['source'] ?? '')) : '—',
        'assignedTo' => $assignedTo,
        'createdAt' => $createdAtRaw,
        'createdAtDisplay' => $createdAtDisplay,
        'tags' => $leadTags,
        'remarks' => [],
        'files' => [],
        'history' => $historyEntries,
        'avatarInitial' => lead_avatar_initial($leadName),
    ];
}

$stageCategories = [
    'Active Stage' => [
        'New',
        'Contacted',
        'Follow Up – In Progress',
        'Qualified',
        'Meeting Scheduled',
        'Meeting Done',
        'Offer Made',
        'Negotiation',
        'Site Visit',
    ],
    'Closed Stage' => [
        'Won',
        'Booking Confirmed',
        'Lost',
    ],
    'Reason for Lost Leads' => [
        'Unresponsive',
        'Not Qualified',
        'Budget Issues',
        'Incorrect Number',
        'Lost to Competitor',
        'Unknown Reason',
    ],
];

$stageOptions = [];
foreach ($stageCategories as $categoryStages) {
    foreach ($categoryStages as $stageLabel) {
        $stageOptions[] = $stageLabel;
    }
}

$activeStageSet = [];
foreach ($stageCategories['Active Stage'] as $activeLabel) {
    $activeStageSet[normalize_stage_label($activeLabel)] = true;
}

$closedStageSet = [];
foreach ($stageCategories['Closed Stage'] as $closedLabel) {
    if (normalize_stage_label($closedLabel) === 'lost') {
        continue;
    }

    $closedStageSet[normalize_stage_label($closedLabel)] = true;
}

$lostStageSet = [];
foreach ($stageCategories['Reason for Lost Leads'] as $lostLabel) {
    $lostStageSet[normalize_stage_label($lostLabel)] = true;
}

$leadStats = [
    'total' => 0,
    'active' => 0,
    'closed' => 0,
    'lost' => 0,
];

$statsQuery = $mysqli->query('SELECT stage FROM all_leads');
if ($statsQuery instanceof mysqli_result) {
    while ($statsRow = $statsQuery->fetch_assoc()) {
        $leadStats['total']++;

        $formattedStage = format_lead_stage($statsRow['stage'] ?? '');
        $normalizedStage = normalize_stage_label($formattedStage);

        if (isset($activeStageSet[$normalizedStage])) {
            $leadStats['active']++;
        }

        if (isset($closedStageSet[$normalizedStage])) {
            $leadStats['closed']++;
        }

        if ($normalizedStage === 'lost' || isset($lostStageSet[$normalizedStage])) {
            $leadStats['lost']++;
        }
    }

    $statsQuery->free();
}

$ratingOptions = [
    'New',
    'Cold',
    'Warm',
    'Hot',
    'Nurture',
];

$stageFilterOptions = $stageOptions;
$dynamicStageLabels = [];
$distinctStageQuery = $mysqli->query('SELECT DISTINCT stage FROM all_leads WHERE stage IS NOT NULL AND stage <> ""');
if ($distinctStageQuery instanceof mysqli_result) {
    while ($stageRow = $distinctStageQuery->fetch_assoc()) {
        $formattedStage = format_lead_stage($stageRow['stage'] ?? '');
        if ($formattedStage !== '' && !in_array($formattedStage, $dynamicStageLabels, true)) {
            $dynamicStageLabels[] = $formattedStage;
        }
    }
    $distinctStageQuery->free();
}
$stageFilterOptions = array_values(array_unique(array_merge($stageFilterOptions, $dynamicStageLabels)));

$assignedFilterOptions = array_values(array_filter(array_map('trim', $users), static function ($value) {
    return $value !== '';
}));
$assignedQuery = $mysqli->query('SELECT DISTINCT assigned_to FROM all_leads WHERE assigned_to IS NOT NULL AND assigned_to <> "" ORDER BY assigned_to ASC');
if ($assignedQuery instanceof mysqli_result) {
    while ($assignedRow = $assignedQuery->fetch_assoc()) {
        $assignedName = trim((string) ($assignedRow['assigned_to'] ?? ''));
        if ($assignedName !== '' && !in_array($assignedName, $assignedFilterOptions, true)) {
            $assignedFilterOptions[] = $assignedName;
        }
    }
    $assignedQuery->free();
}
sort($assignedFilterOptions, SORT_NATURAL | SORT_FLAG_CASE);

$sourceFilterOptions = [];
$sourceQuery = $mysqli->query('SELECT DISTINCT source FROM all_leads WHERE source IS NOT NULL AND source <> "" ORDER BY source ASC');
if ($sourceQuery instanceof mysqli_result) {
    while ($sourceRow = $sourceQuery->fetch_assoc()) {
        $sourceValue = trim((string) ($sourceRow['source'] ?? ''));
        if ($sourceValue !== '' && !in_array($sourceValue, $sourceFilterOptions, true)) {
            $sourceFilterOptions[] = $sourceValue;
        }
    }
    $sourceQuery->free();
}

$filterStage = isset($_GET['stage']) ? trim((string) $_GET['stage']) : '';
if (strcasecmp($filterStage, 'all') === 0) {
    $filterStage = '';
}

$filterAssignedTo = isset($_GET['assigned_to']) ? trim((string) $_GET['assigned_to']) : '';
$filterSource = isset($_GET['source']) ? trim((string) $_GET['source']) : '';
$filterNationality = isset($_GET['nationality']) ? trim((string) $_GET['nationality']) : '';
$filterSearch = isset($_GET['search']) ? trim((string) $_GET['search']) : '';

if (strlen($filterStage) > 100) {
    $filterStage = function_exists('mb_substr') ? mb_substr($filterStage, 0, 100, 'UTF-8') : substr($filterStage, 0, 100);
}
if (strlen($filterAssignedTo) > 255) {
    $filterAssignedTo = function_exists('mb_substr') ? mb_substr($filterAssignedTo, 0, 255, 'UTF-8') : substr($filterAssignedTo, 0, 255);
}
if (strlen($filterSource) > 100) {
    $filterSource = function_exists('mb_substr') ? mb_substr($filterSource, 0, 100, 'UTF-8') : substr($filterSource, 0, 100);
}
if (strlen($filterNationality) > 100) {
    $filterNationality = function_exists('mb_substr') ? mb_substr($filterNationality, 0, 100, 'UTF-8') : substr($filterNationality, 0, 100);
}
if (strlen($filterSearch) > 255) {
    $filterSearch = function_exists('mb_substr') ? mb_substr($filterSearch, 0, 255, 'UTF-8') : substr($filterSearch, 0, 255);
}

$whereClauses = [];
$queryParams = [];
$paramTypes = '';

if ($filterStage !== '') {
    $whereClauses[] = '`stage` LIKE ?';
    $queryParams[] = '%' . $filterStage . '%';
    $paramTypes .= 's';
}

if ($filterAssignedTo !== '') {
    if ($filterAssignedTo === '__unassigned__') {
        $whereClauses[] = "(`assigned_to` IS NULL OR `assigned_to` = '')";
    } else {
        $whereClauses[] = '`assigned_to` = ?';
        $queryParams[] = $filterAssignedTo;
        $paramTypes .= 's';
    }
}

if ($filterSource !== '') {
    $whereClauses[] = '`source` = ?';
    $queryParams[] = $filterSource;
    $paramTypes .= 's';
}

if ($filterNationality !== '') {
    $whereClauses[] = '`nationality` LIKE ?';
    $queryParams[] = '%' . $filterNationality . '%';
    $paramTypes .= 's';
}

if ($filterSearch !== '') {
    $searchTerm = '%' . $filterSearch . '%';
    $whereClauses[] = '(`name` LIKE ? OR `email` LIKE ? OR `phone` LIKE ?)';
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $queryParams[] = $searchTerm;
    $paramTypes .= 'sss';
}

$querySql = 'SELECT * FROM all_leads';
if (!empty($whereClauses)) {
    $querySql .= ' WHERE ' . implode(' AND ', $whereClauses);
}
$querySql .= ' ORDER BY created_at DESC';

$leads = [];
if (!empty($queryParams)) {
    $statement = $mysqli->prepare($querySql);
    if ($statement instanceof mysqli_stmt) {
        $bindParams = array_merge([$paramTypes], $queryParams);
        $bindReferences = [];
        foreach ($bindParams as $key => &$value) {
            $bindReferences[$key] = &$value;
        }
        unset($value);

        $bindResult = $statement->bind_param(...$bindReferences);
        if ($bindResult && $statement->execute()) {
            $result = $statement->get_result();
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $leads[] = $row;
                }
                $result->free();
            }
        }
        $statement->close();
    }
} else {
    $leadsQuery = $mysqli->query($querySql);
    if ($leadsQuery instanceof mysqli_result) {
        while ($row = $leadsQuery->fetch_assoc()) {
            $leads[] = $row;
        }
        $leadsQuery->free();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_GET['action']) && $_GET['action'] === 'update-lead')) {
    header('Content-Type: application/json; charset=utf-8');

    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput ?: '[]', true);

    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request payload.']);
        exit;
    }

    $leadId = isset($payload['id']) ? (int) $payload['id'] : 0;
    if ($leadId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'A valid lead identifier is required.']);
        exit;
    }

    $fieldsMap = [
        'name' => 'name',
        'stage' => 'stage',
        'rating' => 'rating',
        'assigned_to' => 'assigned_to',
        'source' => 'source',
        'phone' => 'phone',
        'alternate_phone' => 'alternate_phone',
        'email' => 'email',
        'alternate_email' => 'alternate_email',
        'nationality' => 'nationality',
        'location_preferences' => 'location_preferences',
        'property_type' => 'property_type',
        'interested_in' => 'interested_in',
        'budget_range' => 'budget_range',
        'urgency' => 'urgency',
        'purpose' => 'purpose',
        'size_required' => 'size_required',
    ];

    $updates = [];
    $types = '';
    $params = [];

    foreach ($fieldsMap as $payloadKey => $columnName) {
        if (array_key_exists($payloadKey, $payload)) {
            $value = $payload[$payloadKey];
            if (is_string($value)) {
                $value = trim($value);
            } elseif ($value === null) {
                $value = null;
            } else {
                $value = trim((string) $value);
            }

            $updates[] = "`{$columnName}` = ?";
            $params[] = $value === '' ? null : $value;
            $types .= 's';
        }
    }

    if (empty($updates)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No changes were provided.']);
        exit;
    }

    $params[] = $leadId;
    $types .= 'i';

    $updateStatement = $mysqli->prepare('UPDATE all_leads SET ' . implode(', ', $updates) . ' WHERE id = ?');
    if (!$updateStatement instanceof mysqli_stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to prepare the update statement.']);
        exit;
    }

    $bindParams = array_merge([$types], $params);
    $bindReferences = [];
    foreach ($bindParams as $key => &$value) {
        $bindReferences[$key] = &$value;
    }
    unset($value);

    $bindResult = $updateStatement->bind_param(...$bindReferences);
    if (!$bindResult || !$updateStatement->execute()) {
        $errorMessage = $updateStatement->error ?: $mysqli->error;
        if ($errorMessage) {
            error_log('Lead update failed: ' . $errorMessage);
        }
        $updateStatement->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to update the lead.']);
        exit;
    }

    $updateStatement->close();

    $selectStatement = $mysqli->prepare('SELECT * FROM all_leads WHERE id = ?');
    if (!$selectStatement instanceof mysqli_stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to prepare the fetch statement.']);
        exit;
    }

    $selectStatement->bind_param('i', $leadId);
    if (!$selectStatement->execute()) {
        $selectStatement->close();
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to retrieve the updated lead.']);
        exit;
    }

    $result = $selectStatement->get_result();
    $updatedLeadRow = $result ? $result->fetch_assoc() : null;
    $selectStatement->close();

    if (!$updatedLeadRow) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'The requested lead could not be found.']);
        exit;
    }

    $updatedPayload = build_lead_payload($updatedLeadRow);
    $encodedPayload = json_encode($updatedPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

    if ($encodedPayload === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to encode the updated lead payload.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Lead details updated successfully.',
        'lead' => [
            'row' => [
                'id' => (int) $updatedLeadRow['id'],
                'name' => $updatedPayload['name'],
                'email' => $updatedPayload['email'],
                'phone' => $updatedPayload['phone'],
                'stage' => $updatedPayload['stage'],
                'stageClass' => $updatedPayload['stageClass'],
                'source' => $updatedPayload['source'],
                'assigned_to' => $updatedPayload['assignedTo'],
                'avatarInitial' => $updatedPayload['avatarInitial'],
            ],
            'payload' => $updatedPayload,
            'json' => $encodedPayload,
        ],
    ]);
    exit;
}
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <?php if ($deleteFlashMessage): ?>
            <div class="alert alert-<?php echo htmlspecialchars($deleteFlashType); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($deleteFlashMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <form method="get" action="all-leads.php">
            <div class="allLeads">
                <div class="container-fluid p-0">
                    <div class="row align-items-center">
                        <div class="col-lg-5">
                            <h1 class="main-heading">Leads Managements</h1>
                            <p class="subheading">Manage and track all your real estate leads</p>
                        </div>

                        <div class="col-lg-7">
                            <div class="right-align">
                                <div class="addlead">
                                    <a href="add-leads.php" class="btn btn-primary"><i class="bx bx-user-plus me-1"></i>
                                        &nbsp;Add Leads</a>
                                </div>
                                <div class="filterbtn">
                                    <button class="btn btn-light"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-upload ">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="17 8 12 3 7 8"></polyline>
                                            <line x1="12" x2="12" y1="3" y2="15"></line>
                                        </svg>Import</button>
                                </div>
                                <div class="filterbtn">
                                    <button class="btn btn-light"><svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-download ">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                            <polyline points="7 10 12 15 17 10"></polyline>
                                            <line x1="12" x2="12" y1="15" y2="3"></line>
                                        </svg>Export</button>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div class="row g-3 lead-stats">
                        <div class="col-md-3">
                            <div class="stat-card total-leads">
                                <h6>Total Leads</h6>
                                <h2><?php echo number_format($leadStats['total']); ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card active-leads">
                                <h6>Active Leads</h6>
                                <h2><?php echo number_format($leadStats['active']); ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card closed-leads">
                                <h6>Closed Leads</h6>
                                <h2><?php echo number_format($leadStats['closed']); ?></h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card lost-leads">
                                <h6>Lost Leads</h6>
                                <h2><?php echo number_format($leadStats['lost']); ?></h2>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-10">
                            <div class="lead-search">
                                <div class="form-group">
                                    <input type="text" class="form-control" name="search"
                                        value="<?php echo htmlspecialchars($filterSearch); ?>"
                                        placeholder="Search leads by name, email, or phone...">
                                    <span><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                            stroke-linecap="round" stroke-linejoin="round"
                                            class="lucide lucide-search absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5">
                                            <circle cx="11" cy="11" r="8"></circle>
                                            <path d="m21 21-4.3-4.3"></path>
                                        </svg>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-2">
                            <div class="filterbtn">
                                <button type="button" class="btn btn-light w-100" id="filterToggle" aria-expanded="false"
                                    aria-controls="leadFilters"><svg xmlns="http://www.w3.org/2000/svg" width="24"
                                        height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                                        stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                                        class="lucide lucide-filter w-5 h-5">
                                        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
                                    </svg>Filter</button>
                            </div>
                        </div>
                    </div>

                    <div class="filters-section" id="leadFilters">
                        <div class="filters-header d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-semibold">FILTERS</h6>
                            <a href="all-leads.php" class="clear-all"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x ">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg> Clear All</a>
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Stage</label>
                                <select class="form-select" name="stage">
                                    <option value="">All</option>
                                    <?php foreach ($stageFilterOptions as $stageOption): ?>
                                        <option value="<?php echo htmlspecialchars($stageOption); ?>" <?php echo strcasecmp($filterStage, $stageOption) === 0 ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stageOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select" name="assigned_to">
                                    <option value="">All</option>
                                    <option value="__unassigned__" <?php echo $filterAssignedTo === '__unassigned__' ? 'selected' : ''; ?>>Unassigned</option>
                                    <?php foreach ($assignedFilterOptions as $assignedOption): ?>
                                        <option value="<?php echo htmlspecialchars($assignedOption); ?>" <?php echo strcasecmp($filterAssignedTo, $assignedOption) === 0 ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($assignedOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Source</label>
                                <select class="form-select" name="source">
                                    <option value="">All</option>
                                    <?php foreach ($sourceFilterOptions as $sourceOption): ?>
                                        <option value="<?php echo htmlspecialchars($sourceOption); ?>" <?php echo strcasecmp($filterSource, $sourceOption) === 0 ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($sourceOption); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Min Budget (AED)</label>
                                <input type="text" class="form-control" placeholder="e.g., 1000000">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Max Budget (AED)</label>
                                <input type="text" class="form-control" placeholder="e.g., 5000000">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Nationality</label>
                                <input type="text" class="form-control" name="nationality"
                                    value="<?php echo htmlspecialchars($filterNationality); ?>" placeholder="e.g., UAE">
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col text-end">
                                <button type="submit" class="btn btn-primary">Apply Filters</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <form method="post" class="d-none" id="deleteLeadForm" data-prevent-lead-open>
            <input type="hidden" name="delete_lead_id" id="deleteLeadInput">
        </form>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table">
                        <thead>
                            <tr>
                                <th scope="col">Name</th>
                                <th scope="col">Contact</th>
                                <th scope="col">Stage</th>
                                <th scope="col">Assigned To</th>
                                <th scope="col">Source</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leads)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No leads found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                    <?php
                                    $leadPayload = build_lead_payload($lead);
                                    $leadJson = htmlspecialchars(json_encode($leadPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    $leadName = $leadPayload['name'];
                                    $leadEmail = $leadPayload['email'];
                                    $leadPhone = $leadPayload['phone'];
                                    $stageLabel = $leadPayload['stage'];
                                    $stageClass = $leadPayload['stageClass'];
                                    $sourceLabel = $leadPayload['source'];
                                    $avatarInitial = $leadPayload['avatarInitial'];
                                    $assignedLabel = $leadPayload['assignedTo'];
                                    ?>
                                    <tr class="lead-table-row" data-lead-json="<?php echo $leadJson; ?>" data-lead-id="<?php echo isset($leadPayload['id']) ? (int) $leadPayload['id'] : 0; ?>" data-lead-name="<?php echo htmlspecialchars($leadName); ?>" tabindex="0" role="button" aria-label="View details for <?php echo htmlspecialchars($leadName); ?>">
                                        <td>
                                            <div class="lead-info">
                                                <div class="avatar" data-lead-avatar><?php echo htmlspecialchars($avatarInitial); ?></div>
                                                <div><strong data-lead-name><?php echo htmlspecialchars($leadName); ?></strong></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info" data-lead-contact>
                                                <?php if ($leadEmail !== ''): ?>
                                                    <span data-lead-contact-email><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($leadEmail); ?></span>
                                                    <?php if ($leadPhone !== ''): ?><br><?php endif; ?>
                                                <?php endif; ?>
                                                <?php if ($leadPhone !== ''): ?>
                                                    <span data-lead-contact-phone><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($leadPhone); ?></span>
                                                <?php endif; ?>
                                                <?php if ($leadEmail === '' && $leadPhone === ''): ?>
                                                    <span class="text-muted" data-lead-contact-empty>No contact details</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td data-lead-stage>
                                            <div class="stage-badge <?php echo htmlspecialchars($stageClass); ?>" data-lead-stage-pill><?php echo htmlspecialchars($stageLabel); ?></div>
                                        </td>
                                        <td>
                                            <div class="assigned-dropdown" data-prevent-lead-open>
                                                <select class="form-select assigned-select" data-lead-assigned-select>
                                                    <option value="">Unassigned</option>
                                                    <?php foreach ($users as $userId => $userName): ?>
                                                        <?php
                                                        $isSelected = false;
                                                        if ($assignedLabel !== '') {
                                                            $isSelected = strcasecmp($assignedLabel, $userName) === 0;
                                                        } elseif ($loggedInUserName !== '') {
                                                            $isSelected = strcasecmp($loggedInUserName, $userName) === 0;
                                                        } elseif ($loggedInUserId !== null) {
                                                            $isSelected = $loggedInUserId === (int) $userId;
                                                        }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars($userName); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($userName); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </td>
                                        <td data-lead-source><?php echo htmlspecialchars($sourceLabel); ?></td>
                                        <td>
                                            <div class="dropdown" data-prevent-lead-open>
                                                <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><button class="dropdown-item" type="button" data-lead-action="view">View</button></li>
                                                    <li><button class="dropdown-item" type="button">Edit</button></li>
                                                    <li><button class="dropdown-item text-danger" type="button" data-lead-action="delete" data-lead-id="<?php echo isset($leadPayload['id']) ? (int) $leadPayload['id'] : 0; ?>" data-lead-name="<?php echo htmlspecialchars($leadName); ?>">Delete</button></li>
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

        <div class="lead-sidebar-overlay" id="leadSidebarOverlay" hidden></div>
        <aside class="lead-sidebar" id="leadSidebar" aria-hidden="true">
            <div class="lead-sidebar__inner">
                <header class="lead-sidebar__header">
                    <div class="lead-sidebar__header-background"></div>
                    <div class="lead-sidebar__header-actions">
                        <button type="button" class="lead-sidebar__action-btn lead-sidebar__edit" data-action="edit" aria-label="Edit lead details">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                        <button type="button" class="lead-sidebar__action-btn lead-sidebar__close" data-action="close" aria-label="Close lead details">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="lead-sidebar__header-content">
                        <div class="lead-sidebar__header-text">
                            <p class="lead-sidebar__header-title">Lead Details</p>
                            <!-- <p class="lead-sidebar__header-subtitle">Complete information and activity history</p> -->
                        </div>
                        <div class="lead-sidebar__profile">
                            <div class="lead-sidebar__avatar" data-lead-field="avatarInitial">J</div>
                            <div class="lead-sidebar__profile-info">
                                <div class="lead-sidebar__title-row" data-edit-field="name">
                                    <h2 class="lead-sidebar__name" data-lead-field="name" data-role="display">Lead Name</h2>
                                    <input type="text" class="form-control lead-sidebar__input lead-sidebar__name-input" data-role="input" name="name" placeholder="Enter lead name">
                                </div>
                                <div class="lead-sidebar__status-group">
                                    <div class="lead-sidebar__stage" data-edit-field="stage">
                                        <span class="lead-stage-pill stage-badge" data-lead-field="stage" data-role="display">New</span>
                                        <select class="form-select lead-sidebar__input" data-role="input" name="stage">
                                            <?php foreach ($stageCategories as $categoryLabel => $categoryStages): ?>
                                                <optgroup label="<?php echo htmlspecialchars($categoryLabel); ?>">
                                                    <?php foreach ($categoryStages as $stageOption): ?>
                                                        <option value="<?php echo htmlspecialchars($stageOption); ?>"><?php echo htmlspecialchars($stageOption); ?></option>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="lead-sidebar__rating" data-edit-field="rating">
                                        <span class="lead-sidebar__rating-label" data-lead-field="ratingLabel" data-role="display">Not rated</span>
                                        <select class="form-select lead-sidebar__input" data-role="input" name="rating">
                                            <option value="">Not rated</option>
                                            <?php foreach ($ratingOptions as $ratingOption): ?>
                                                <option value="<?php echo htmlspecialchars($ratingOption); ?>"><?php echo htmlspecialchars($ratingOption); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
                <div class="lead-sidebar__body">
                    <div class="lead-sidebar__quick-actions">
                        <a href="#" class="lead-quick-actions__btn" data-action="call">
                            <div class="lead-quick-actions__icon"><i class="bi bi-telephone"></i></div>
                            <span>Call</span>
                        </a>
                        <a href="#" class="lead-quick-actions__btn" data-action="email">
                            <div class="lead-quick-actions__icon"><i class="bi bi-envelope"></i></div>
                            <span>Email</span>
                        </a>
                        <a href="#" class="lead-quick-actions__btn" data-action="whatsapp">
                            <div class="lead-quick-actions__icon"><i class="bi bi-whatsapp"></i></div>
                            <span>WhatsApp</span>
                        </a>
                    </div>
                    <div class="lead-sidebar__feedback" data-lead-feedback hidden></div>
                    <form class="lead-sidebar__form" data-lead-form id="leadSidebarForm" novalidate>
                        <input type="hidden" name="id" data-edit-id>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Contact Information</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item" data-edit-field="email">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-envelope"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Email</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <a href="#" class="lead-sidebar__item-value" data-lead-field="email" data-role="display" data-empty-text="No email provided">No email provided</a>
                                            <input type="email" class="form-control lead-sidebar__input" data-role="input" name="email" placeholder="Enter email">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="phone">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-telephone"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Phone Number</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <a href="#" class="lead-sidebar__item-value" data-lead-field="phone" data-role="display" data-empty-text="No phone number">No phone number</a>
                                            <input type="tel" class="form-control lead-sidebar__input" data-role="input" name="phone" placeholder="Enter phone number">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="nationality">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-flag"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Nationality</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="nationality" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="nationality" placeholder="Enter nationality">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="location_preferences">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-geo-alt"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Location</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="location" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="location_preferences" placeholder="Enter preferred locations">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Property Preferences</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item" data-edit-field="property_type">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-building"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Property Type</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="propertyType" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="property_type" placeholder="Enter property type">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="interested_in">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-collection"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Properties Interested In</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <div class="lead-sidebar__chips" data-lead-field="interestedIn" data-role="display"></div>
                                            <textarea class="form-control lead-sidebar__input" data-role="input" name="interested_in" rows="2" placeholder="Add interests separated by commas"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="budget_range">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-cash-coin"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Budget Range</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="budget" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="budget_range" placeholder="Enter budget range">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="urgency">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-calendar-event"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Timeline / Expected Move-in</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="moveIn" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="urgency" placeholder="Enter move-in timeline">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="size_required">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-aspect-ratio"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Size Required</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="sizeRequired" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="size_required" placeholder="Enter size requirement">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Lead Information</h3>
                            <div class="lead-sidebar__details">
                                <div class="lead-sidebar__item" data-edit-field="source">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-megaphone"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Source</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="source" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="source" placeholder="Enter source">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="assigned_to">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-person-check"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Assigned To</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="assignedTo" data-role="display">—</span>
                                            <select class="form-select lead-sidebar__input" data-role="input" name="assigned_to">
                                                <option value="">Unassigned</option>
                                                <?php foreach ($users as $userName): ?>
                                                    <option value="<?php echo htmlspecialchars($userName); ?>"><?php echo htmlspecialchars($userName); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="purpose">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-tags"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Purpose</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="purpose" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="purpose" placeholder="Enter purpose">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-clock-history"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Created At</span>
                                        <span class="lead-sidebar__item-value" data-lead-field="createdAt">—</span>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="lead-sidebar__section lead-sidebar__section--tabs">
                            <div class="lead-sidebar-tabs" role="tablist">
                                <button type="button" class="lead-sidebar-tab is-active" data-tab-target="remarks" role="tab" aria-selected="true">Remarks</button>
                                <button type="button" class="lead-sidebar-tab" data-tab-target="files" role="tab" aria-selected="false">Files</button>
                                <button type="button" class="lead-sidebar-tab" data-tab-target="history" role="tab" aria-selected="false">History</button>
                            </div>
                            <div class="lead-sidebar-tabpanels">
                                <div class="lead-sidebar-panel is-active" data-tab-panel="remarks" role="tabpanel">
                                    <div class="lead-remarks" data-lead-remarks></div>
                                    <form class="lead-remark-form" action="#" method="post" onsubmit="return false;">
                                        <label for="leadRemarkInput" class="form-label">Add Remark</label>
                                        <textarea id="leadRemarkInput" class="form-control" rows="3" placeholder="Add a note about this lead..."></textarea>
                                        <div class="lead-remark-form__actions">
                                            <label class="lead-file-upload">
                                                <input type="file" class="lead-file-upload__input" multiple>
                                                <span class="lead-file-upload__btn"><i class="bi bi-paperclip"></i> Attach Files</span>
                                            </label>
                                            <button type="submit" class="btn btn-primary">Save</button>
                                        </div>
                                    </form>
                                </div>
                                <div class="lead-sidebar-panel" data-tab-panel="files" role="tabpanel" aria-hidden="true">
                                    <div class="lead-files" data-lead-files>
                                        <p class="lead-empty-state">No files uploaded yet.</p>
                                    </div>
                                    <div class="lead-files__actions">
                                        <label class="lead-file-upload">
                                            <input type="file" class="lead-file-upload__input" multiple>
                                            <span class="lead-file-upload__btn"><i class="bi bi-upload"></i> Upload files</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="lead-sidebar-panel" data-tab-panel="history" role="tabpanel" aria-hidden="true">
                                    <div class="lead-history" data-lead-history></div>
                                </div>
                            </div>
                        </section>
                    </form>
                </div>
            </div>
        </aside>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
