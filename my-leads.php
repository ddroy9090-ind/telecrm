<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

/**
 * Normalize an assignee label for comparison.
 */
function normalize_assignee_label(?string $value): string
{
    $trimmed = trim((string) $value);
    if ($trimmed === '') {
        return '';
    }

    return function_exists('mb_strtolower')
        ? mb_strtolower($trimmed, 'UTF-8')
        : strtolower($trimmed);
}

function resolve_assigned_user_details(?string $rawValue): array
{
    global $users, $userLookupByNormalized;

    $trimmed = trim((string) $rawValue);
    if ($trimmed === '') {
        return [null, '', ''];
    }

    if (ctype_digit($trimmed)) {
        $candidateId = (int) $trimmed;
        if ($candidateId > 0 && isset($users[$candidateId])) {
            return [$candidateId, $users[$candidateId], $trimmed];
        }
    }

    $normalized = normalize_assignee_label($trimmed);
    if ($normalized !== '' && isset($userLookupByNormalized[$normalized])) {
        $candidateId = (int) $userLookupByNormalized[$normalized];
        if ($candidateId > 0 && isset($users[$candidateId])) {
            return [$candidateId, $users[$candidateId], $trimmed];
        }
    }

    return [null, $trimmed, $trimmed];
}

function format_assigned_user_display($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }

    [$resolvedId, $resolvedLabel, $rawValue] = resolve_assigned_user_details($value);
    if ($resolvedId !== null && $resolvedLabel !== '') {
        return $resolvedLabel;
    }

    $rawTrimmed = trim((string) $rawValue);

    return $rawTrimmed !== '' ? $rawTrimmed : '—';
}

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

function format_activity_timestamp(?string $rawTimestamp): string
{
    if ($rawTimestamp === null || trim($rawTimestamp) === '') {
        return '—';
    }

    $timestamp = strtotime((string) $rawTimestamp);
    if ($timestamp === false) {
        return trim((string) $rawTimestamp);
    }

    return date('M d, Y g:i A', $timestamp);
}

function ensure_lead_activity_table(mysqli $mysqli): bool
{
    static $isReady = null;
    if ($isReady !== null) {
        return $isReady;
    }

    $isReady = false;
    $result = $mysqli->query("SHOW TABLES LIKE 'lead_activity_log'");
    if ($result instanceof mysqli_result) {
        if ($result->num_rows > 0) {
            $isReady = true;
        }
        $result->free();
    }

    if ($isReady) {
        return true;
    }

    $tableDefinitions = [
        <<<SQL
CREATE TABLE IF NOT EXISTS `lead_activity_log` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` INT(10) UNSIGNED NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `metadata` JSON DEFAULT NULL,
    `created_by` INT(10) UNSIGNED DEFAULT NULL,
    `created_by_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `lead_activity_lead_id` (`lead_id`),
    KEY `lead_activity_type` (`activity_type`),
    CONSTRAINT `lead_activity_log_lead_fk` FOREIGN KEY (`lead_id`) REFERENCES `all_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
        <<<SQL
CREATE TABLE IF NOT EXISTS `lead_activity_log` (
    `id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `lead_id` INT(10) UNSIGNED NOT NULL,
    `activity_type` VARCHAR(50) NOT NULL,
    `description` TEXT NOT NULL,
    `metadata` LONGTEXT DEFAULT NULL,
    `created_by` INT(10) UNSIGNED DEFAULT NULL,
    `created_by_name` VARCHAR(255) DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `lead_activity_lead_id` (`lead_id`),
    KEY `lead_activity_type` (`activity_type`),
    CONSTRAINT `lead_activity_log_lead_fk` FOREIGN KEY (`lead_id`) REFERENCES `all_leads` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL,
    ];

    foreach ($tableDefinitions as $definition) {
        if ($mysqli->query($definition)) {
            $isReady = true;
            break;
        }

        error_log('Unable to create lead_activity_log table: ' . $mysqli->error);
    }

    return $isReady;
}

function fetch_lead_activities(mysqli $mysqli, array $leadIds): array
{
    if (empty($leadIds) || !ensure_lead_activity_table($mysqli)) {
        return [];
    }

    $uniqueIds = array_values(array_unique(array_map('intval', $leadIds)));
    if (empty($uniqueIds)) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($uniqueIds), '?'));
    $types = str_repeat('i', count($uniqueIds));

    $statement = $mysqli->prepare("SELECT id, lead_id, activity_type, description, metadata, created_by_name, created_at FROM lead_activity_log WHERE lead_id IN ({$placeholders}) ORDER BY created_at DESC, id DESC");
    if (!$statement instanceof mysqli_stmt) {
        return [];
    }

    $bindParams = array_merge([$types], $uniqueIds);
    $bindReferences = [];
    foreach ($bindParams as $key => &$value) {
        $bindReferences[$key] = &$value;
    }
    unset($value);

    $statement->bind_param(...$bindReferences);

    if (!$statement->execute()) {
        $statement->close();
        return [];
    }

    $result = $statement->get_result();
    $activities = [];

    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $leadId = isset($row['lead_id']) ? (int) $row['lead_id'] : 0;
            if ($leadId <= 0) {
                continue;
            }

            if (!isset($activities[$leadId])) {
                $activities[$leadId] = [
                    'history' => [],
                    'remarks' => [],
                    'files' => [],
                ];
            }

            $activityType = trim((string) ($row['activity_type'] ?? 'update'));
            $actorName = trim((string) ($row['created_by_name'] ?? ''));
            $timestamp = format_activity_timestamp($row['created_at'] ?? null);
            $description = trim((string) ($row['description'] ?? 'Activity recorded'));

            $metadata = [];
            if (!empty($row['metadata'])) {
                $decoded = json_decode((string) $row['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $metadata = $decoded;
                }
            }

            $activities[$leadId]['history'][] = [
                'description' => $description !== '' ? $description : 'Activity recorded',
                'timestamp' => $timestamp,
                'actor' => $actorName,
            ];

            if ($activityType === 'remark') {
                $remarkText = trim((string) ($metadata['remark'] ?? $description));
                $attachments = [];
                if (isset($metadata['attachments']) && is_array($metadata['attachments'])) {
                    $attachments = array_values(array_filter($metadata['attachments'], static function ($item) {
                        return is_array($item) && isset($item['url']);
                    }));
                }

                $activities[$leadId]['remarks'][] = [
                    'author' => $actorName !== '' ? $actorName : 'Team',
                    'timestamp' => $timestamp,
                    'text' => $remarkText !== '' ? $remarkText : 'No remark details provided.',
                    'attachments' => $attachments,
                ];
            }

            if ($activityType === 'file_upload') {
                $fileName = trim((string) ($metadata['file_name'] ?? $description));
                if ($fileName === '') {
                    $fileName = 'Document';
                }

                $fileUrl = trim((string) ($metadata['file_url'] ?? ($metadata['file_path'] ?? '')));
                if ($fileUrl === '') {
                    $fileUrl = '#';
                }

                $fileEntry = [
                    'name' => $fileName,
                    'url' => $fileUrl,
                    'timestamp' => $timestamp,
                ];

                if ($actorName !== '') {
                    $fileEntry['uploadedBy'] = $actorName;
                }

                $historyIndex = count($activities[$leadId]['history']) - 1;
                if ($historyIndex >= 0) {
                    $activities[$leadId]['history'][$historyIndex]['file'] = [
                        'name' => $fileName,
                        'url' => $fileUrl,
                    ];
                }

                $activities[$leadId]['files'][] = $fileEntry;
            }
        }
        $result->free();
    }

    $statement->close();

    return $activities;
}

function lead_avatar_initial(string $name): string
{
    if (function_exists('mb_substr')) {
        return strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
    }

    return strtoupper(substr($name, 0, 1));
}

function build_lead_payload(array $lead, array $relatedActivities = []): array
{
    global $users;

    $leadName = trim($lead['name'] ?? '') !== '' ? $lead['name'] : 'Unnamed Lead';
    $stageLabel = format_lead_stage($lead['stage'] ?? '');
    $stageClass = stage_badge_class($stageLabel);
    $leadEmail = trim((string) ($lead['email'] ?? ''));
    $leadPhone = trim((string) ($lead['phone'] ?? ''));
    $rawAssigned = trim((string) ($lead['assigned_to'] ?? ''));
    [$assignedUserId, $assignedDisplayName, $assignedRawValue] = resolve_assigned_user_details($rawAssigned);
    $assignedTo = $assignedDisplayName;
    if ($assignedTo === '' && $assignedUserId !== null && isset($users[$assignedUserId])) {
        $assignedTo = $users[$assignedUserId];
    }
    if ($assignedTo === '' && $assignedRawValue !== '') {
        $assignedTo = $assignedRawValue;
    }
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

    $existingHistory = [];
    if (isset($relatedActivities['history']) && is_array($relatedActivities['history'])) {
        $existingHistory = $relatedActivities['history'];
    }

    $historyEntries = $existingHistory;
    $historyDescriptions = array_map(static function ($entry) {
        return isset($entry['description']) ? trim((string) $entry['description']) : '';
    }, $historyEntries);

    if ($stageLabel !== '') {
        $stageDescription = 'Stage set to ' . $stageLabel;
        if (!in_array($stageDescription, $historyDescriptions, true)) {
            $historyEntries[] = [
                'description' => $stageDescription,
                'timestamp' => $createdAtDisplay,
            ];
        }
    }

    if ($assignedTo !== '') {
        $assignmentDescription = 'Assigned to ' . $assignedTo;
        if (!in_array($assignmentDescription, $historyDescriptions, true)) {
            $historyEntries[] = [
                'description' => $assignmentDescription,
                'timestamp' => $createdAtDisplay,
            ];
        }
    }

    if (!in_array('Lead created', $historyDescriptions, true)) {
        $historyEntries[] = [
            'description' => 'Lead created',
            'timestamp' => $createdAtDisplay,
        ];
    }

    $existingRemarks = [];
    if (isset($relatedActivities['remarks']) && is_array($relatedActivities['remarks'])) {
        $existingRemarks = $relatedActivities['remarks'];
    }

    $existingFiles = [];
    if (isset($relatedActivities['files']) && is_array($relatedActivities['files'])) {
        $existingFiles = $relatedActivities['files'];
    }

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
        'assignedToId' => $assignedUserId,
        'assignedToRaw' => $assignedRawValue,
        'createdAt' => $createdAtRaw,
        'createdAtDisplay' => $createdAtDisplay,
        'tags' => $leadTags,
        'remarks' => $existingRemarks,
        'files' => $existingFiles,
        'history' => $historyEntries,
        'avatarInitial' => lead_avatar_initial($leadName),
    ];
}

$users = [];
$userLookupByNormalized = [];
$usersQuery = $mysqli->query('SELECT id, full_name, email FROM users ORDER BY full_name ASC');
if ($usersQuery instanceof mysqli_result) {
    while ($userRow = $usersQuery->fetch_assoc()) {
        $userId = isset($userRow['id']) ? (int) $userRow['id'] : 0;
        if ($userId <= 0) {
            continue;
        }

        $fullName = trim((string) ($userRow['full_name'] ?? ''));
        $emailAddress = trim((string) ($userRow['email'] ?? ''));
        $displayName = $fullName !== '' ? $fullName : ($emailAddress !== '' ? $emailAddress : 'User #' . $userId);

        $users[$userId] = $displayName;

        $lookupCandidates = [
            $displayName,
            $fullName,
            $emailAddress,
            (string) $userId,
            'User #' . $userId,
        ];

        foreach ($lookupCandidates as $candidate) {
            $normalized = normalize_assignee_label($candidate);
            if ($normalized === '') {
                continue;
            }

            $userLookupByNormalized[$normalized] = $userId;
        }
    }
    $usersQuery->free();
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

$ratingOptions = [
    'New',
    'Cold',
    'Warm',
    'Hot',
    'Nurture',
];

$currentUserId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
$currentUserName = trim((string) ($_SESSION['username'] ?? ''));
$currentUserEmail = trim((string) ($_SESSION['email'] ?? ''));
$currentUserRole = trim((string) ($_SESSION['role'] ?? ''));

$normalizedIdentifierSet = [];
$primaryIdentifiers = [];

$addIdentifier = static function (?string $value) use (&$primaryIdentifiers, &$normalizedIdentifierSet): void {
    $normalized = normalize_assignee_label($value);
    if ($normalized === '' || isset($normalizedIdentifierSet[$normalized])) {
        return;
    }
    $normalizedIdentifierSet[$normalized] = true;
    $primaryIdentifiers[] = $normalized;
};

$addIdentifier($currentUserName);
$addIdentifier($currentUserEmail);

if ($currentUserId !== null) {
    $addIdentifier((string) $currentUserId);
    $addIdentifier('User #' . $currentUserId);
}

$addIdentifier($currentUserRole);
$addIdentifier($currentUserRole !== '' ? ucfirst($currentUserRole) : '');
$addIdentifier($currentUserRole !== '' ? strtoupper($currentUserRole) : '');

if ($currentUserRole === 'admin') {
    $addIdentifier('administrator');
}

$leadsById = [];
$leadQueryError = '';

if (!empty($primaryIdentifiers)) {
    $placeholders = implode(', ', array_fill(0, count($primaryIdentifiers), '?'));
    $sql = "SELECT * FROM all_leads WHERE assigned_to IS NOT NULL AND TRIM(assigned_to) <> '' AND LOWER(TRIM(assigned_to)) IN ($placeholders) AND created_by IS NOT NULL AND EXISTS (SELECT 1 FROM users WHERE users.id = all_leads.created_by) ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($sql);

    if ($stmt instanceof mysqli_stmt) {
        $paramTypes = str_repeat('s', count($primaryIdentifiers));
        $stmt->bind_param($paramTypes, ...$primaryIdentifiers);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    if (isset($row['id'])) {
                        $leadsById[(int) $row['id']] = $row;
                    }
                }
                $result->free();
            }
        }

        $stmt->close();
    } else {
        $leadQueryError = 'Unable to prepare the lead lookup query. Please try again later.';
    }
}

$roleLikePatterns = [];

if ($currentUserRole !== '') {
    $normalizedRole = normalize_assignee_label($currentUserRole);
    if ($normalizedRole !== '') {
        $roleLikePatterns[] = '%' . $normalizedRole . '%';
        if ($normalizedRole === 'admin') {
            $roleLikePatterns[] = '%administrator%';
        }
    }
}

if (!empty($roleLikePatterns)) {
    $roleLikePatterns = array_values(array_unique($roleLikePatterns));
    $likeFragments = implode(' OR ', array_fill(0, count($roleLikePatterns), 'LOWER(assigned_to) LIKE ?'));
    $sql = "SELECT * FROM all_leads WHERE assigned_to IS NOT NULL AND TRIM(assigned_to) <> '' AND ($likeFragments) AND created_by IS NOT NULL AND EXISTS (SELECT 1 FROM users WHERE users.id = all_leads.created_by) ORDER BY created_at DESC";
    $stmt = $mysqli->prepare($sql);

    if ($stmt instanceof mysqli_stmt) {
        $paramTypes = str_repeat('s', count($roleLikePatterns));
        $stmt->bind_param($paramTypes, ...$roleLikePatterns);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    if (!isset($row['id'])) {
                        continue;
                    }
                    $leadId = (int) $row['id'];
                    if (!isset($leadsById[$leadId])) {
                        $leadsById[$leadId] = $row;
                    }
                }
                $result->free();
            }
        }

        $stmt->close();
    } elseif ($leadQueryError === '') {
        $leadQueryError = 'Unable to prepare the role-based lead lookup query. Please try again later.';
    }
}

$assigneeTokens = array_values(array_unique(array_filter(array_merge(
    $primaryIdentifiers,
    array_map(static function ($pattern) {
        return normalize_assignee_label(str_replace('%', '', (string) $pattern));
    }, $roleLikePatterns)
))));

$leads = array_values($leadsById);

$leadIds = array_filter(array_map(static function ($lead) {
    return isset($lead['id']) ? (int) $lead['id'] : 0;
}, $leads));

$leadActivities = !empty($leadIds) ? fetch_lead_activities($mysqli, $leadIds) : [];

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <form action="">
            <div class="allLeads">
                <div class="container-fluid p-0">
                    <div class="row align-items-center mb-4">
                        <div class="col-lg-5">
                            <h1 class="main-heading">My Leads</h1>
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
                    <div class="row">
                        <div class="col-lg-10">
                            <div class="lead-search">
                                <div class="form-group">
                                    <input type="text" class="form-control"
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
                            <a href="#" class="clear-all"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x ">
                                    <path d="M18 6 6 18"></path>
                                    <path d="m6 6 12 12"></path>
                                </svg> Clear All</a>
                        </div>

                        <div class="row g-3 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label">Stage</label>
                                <select class="form-select">
                                    <option>All</option>
                                    <option>New</option>
                                    <option>Contacted</option>
                                    <option>Qualified</option>
                                    <option>Proposal</option>
                                    <option>Closed</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select">
                                    <option>All</option>
                                    <option>John Smith</option>
                                    <option>Sarah Lee</option>
                                    <option>David Brown</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Source</label>
                                <select class="form-select">
                                    <option>All</option>
                                    <option>Website Inquiry</option>
                                    <option>Agent Referral</option>
                                    <option>Social Media</option>
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
                                <input type="text" class="form-control" placeholder="e.g., UAE">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table" data-page-type="my" data-current-assignee-tokens="<?php echo htmlspecialchars(json_encode($assigneeTokens, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?>">
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
                            <?php if ($leadQueryError !== ''): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-danger">
                                        <?php echo htmlspecialchars($leadQueryError); ?>
                                    </td>
                                </tr>
                            <?php elseif (empty($leads)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">No leads assigned to you yet.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($leads as $lead): ?>
                                    <?php
                                    $leadIdValue = isset($lead['id']) ? (int) $lead['id'] : 0;
                                    $leadRelated = $leadIdValue && isset($leadActivities[$leadIdValue]) ? $leadActivities[$leadIdValue] : [];
                                    $leadPayload = build_lead_payload($lead, $leadRelated);
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
                                                    <?php
                                                    $assignedId = $leadPayload['assignedToId'];
                                                    $assignedRawValue = $leadPayload['assignedToRaw'];
                                                    $matchedAssignee = false;
                                                    ?>
                                                    <?php foreach ($users as $userId => $userName): ?>
                                                        <?php
                                                        $optionValue = (string) $userId;
                                                        $isSelected = false;
                                                        if ($assignedId !== null) {
                                                            $isSelected = (int) $assignedId === (int) $userId;
                                                        } elseif ($assignedLabel !== '') {
                                                            $isSelected = strcasecmp($assignedLabel, $userName) === 0;
                                                        } elseif ($assignedRawValue !== '') {
                                                            $isSelected = strcasecmp($assignedRawValue, $userName) === 0;
                                                        }

                                                        if ($isSelected) {
                                                            $matchedAssignee = true;
                                                        }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars($optionValue); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($userName); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <?php if (!$matchedAssignee && $assignedLabel !== ''): ?>
                                                        <?php $legacyValue = $assignedId !== null ? (string) $assignedId : ($assignedRawValue !== '' ? $assignedRawValue : $assignedLabel); ?>
                                                        <option value="<?php echo htmlspecialchars($legacyValue); ?>" selected><?php echo htmlspecialchars($assignedLabel); ?></option>
                                                    <?php endif; ?>
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
                                                    <li><button class="dropdown-item" type="button" data-lead-action="edit">Edit</button></li>
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
                                <div class="lead-sidebar__item" data-edit-field="alternate_email">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-envelope-plus"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Alternate Email</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <a href="#" class="lead-sidebar__item-value" data-lead-field="alternateEmail" data-role="display" data-empty-text="No alternate email">No alternate email</a>
                                            <input type="email" class="form-control lead-sidebar__input" data-role="input" name="alternate_email" placeholder="Enter alternate email">
                                        </div>
                                    </div>
                                </div>
                                <div class="lead-sidebar__item" data-edit-field="alternate_phone">
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-phone"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Alternate Phone</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <a href="#" class="lead-sidebar__item-value" data-lead-field="alternatePhone" data-role="display" data-empty-text="No alternate phone">No alternate phone</a>
                                            <input type="tel" class="form-control lead-sidebar__input" data-role="input" name="alternate_phone" placeholder="Enter alternate phone">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </section>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Requirements</h3>
                            <div class="lead-sidebar__details">
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
                                        <span class="lead-sidebar__item-label">Preferred Locations</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <span class="lead-sidebar__item-value" data-lead-field="location" data-role="display">—</span>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="location_preferences" placeholder="Enter preferred locations">
                                        </div>
                                    </div>
                                </div>
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
                                    <span class="lead-sidebar__item-icon"><i class="bi bi-house-heart"></i></span>
                                    <div class="lead-sidebar__item-content">
                                        <span class="lead-sidebar__item-label">Interested In</span>
                                        <div class="lead-sidebar__item-value-wrapper">
                                            <div class="lead-sidebar__chips" data-lead-field="interestedIn" data-role="display"></div>
                                            <input type="text" class="form-control lead-sidebar__input" data-role="input" name="interested_in" placeholder="Enter interests">
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
                                                <?php foreach ($users as $userId => $userName): ?>
                                                    <option value="<?php echo htmlspecialchars((string) $userId); ?>"><?php echo htmlspecialchars($userName); ?></option>
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
                                    <div class="lead-remarks" data-lead-remarks role="log" aria-live="polite" aria-label="Lead remarks"></div>
                                    <form class="lead-remark-form" action="#" method="post" onsubmit="return false;">
                                        <label for="leadRemarkInput" class="form-label">Add Remark</label>
                                        <textarea id="leadRemarkInput" class="form-control" rows="3" placeholder="Add a note about this lead..."></textarea>
                                        <div class="lead-remark-form__actions text-end">
                                            <button type="submit" class="btn btn-primary">Save Remarkes</button>
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
