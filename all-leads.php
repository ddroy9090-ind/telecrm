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

$leads = [];
$leadsQuery = $mysqli->query('SELECT * FROM all_leads ORDER BY created_at DESC');
if ($leadsQuery instanceof mysqli_result) {
    while ($row = $leadsQuery->fetch_assoc()) {
        $leads[] = $row;
    }
    $leadsQuery->free();
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

function lead_avatar_initial(string $name): string
{
    if (function_exists('mb_substr')) {
        return strtoupper(mb_substr($name, 0, 1, 'UTF-8'));
    }

    return strtoupper(substr($name, 0, 1));
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

        <form action="">
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
                                <h2>0</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card active-leads">
                                <h6>Active Leads</h6>
                                <h2>0</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card closed-leads">
                                <h6>Closed Leads</h6>
                                <h2>0</h2>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card lost-leads">
                                <h6>Lost Leads</h6>
                                <h2>0</h2>
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
                                    $leadName = trim($lead['name'] ?? '') !== '' ? $lead['name'] : 'Unnamed Lead';
                                    $leadEmail = trim($lead['email'] ?? '');
                                    $leadPhone = trim($lead['phone'] ?? '');
                                    $stageLabel = format_lead_stage($lead['stage'] ?? '');
                                    $stageClass = stage_badge_class($stageLabel);
                                    $sourceLabel = trim($lead['source'] ?? '') !== '' ? $lead['source'] : '—';
                                    $avatarInitial = lead_avatar_initial($leadName);
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
                                    if (!empty($lead['assigned_to'])) {
                                        $historyEntries[] = [
                                            'description' => 'Assigned to ' . trim((string) $lead['assigned_to']),
                                            'timestamp' => $createdAtDisplay,
                                        ];
                                    }
                                    $historyEntries[] = [
                                        'description' => 'Lead created',
                                        'timestamp' => $createdAtDisplay,
                                    ];

                                    $leadPayload = [
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
                                        'source' => $sourceLabel,
                                        'assignedTo' => trim((string) ($lead['assigned_to'] ?? '')),
                                        'createdAt' => $createdAtRaw,
                                        'createdAtDisplay' => $createdAtDisplay,
                                        'tags' => $leadTags,
                                        'remarks' => [],
                                        'files' => [],
                                        'history' => $historyEntries,
                                    ];

                                    $leadJson = htmlspecialchars(json_encode($leadPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                                    ?>
                                    <tr class="lead-table-row" data-lead-json="<?php echo $leadJson; ?>" data-lead-id="<?php echo isset($lead['id']) ? (int) $lead['id'] : 0; ?>" data-lead-name="<?php echo htmlspecialchars($leadName); ?>" tabindex="0" role="button" aria-label="View details for <?php echo htmlspecialchars($leadName); ?>">
                                        <td>
                                            <div class="lead-info">
                                                <div class="avatar"><?php echo htmlspecialchars($avatarInitial); ?></div>
                                                <div><strong><?php echo htmlspecialchars($leadName); ?></strong></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <?php if ($leadEmail !== ''): ?>
                                                    <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($leadEmail); ?></span><br>
                                                <?php endif; ?>
                                                <?php if ($leadPhone !== ''): ?>
                                                    <span><i class="bi bi-telephone"></i> <?php echo htmlspecialchars($leadPhone); ?></span>
                                                <?php endif; ?>
                                                <?php if ($leadEmail === '' && $leadPhone === ''): ?>
                                                    <span class="text-muted">No contact details</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="stage-badge <?php echo htmlspecialchars($stageClass); ?>"><?php echo htmlspecialchars($stageLabel); ?></div>
                                        </td>
                                        <td>
                                            <div class="assigned-dropdown" data-prevent-lead-open>
                                                <select class="form-select assigned-select">
                                                    <?php foreach ($users as $userId => $userName): ?>
                                                        <?php
                                                        $isSelected = false;
                                                        if ($loggedInUserId !== null) {
                                                            $isSelected = $loggedInUserId === (int) $userId;
                                                        } elseif ($loggedInUserName !== '') {
                                                            $isSelected = strcasecmp($loggedInUserName, $userName) === 0;
                                                        }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string) $userId); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($userName); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($sourceLabel); ?></td>
                                        <td>
                                            <div class="dropdown" data-prevent-lead-open>
                                                <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><button class="dropdown-item" type="button" data-lead-action="view">View</button></li>
                                                    <li><button class="dropdown-item" type="button">Edit</button></li>
                                                    <li><button class="dropdown-item text-danger" type="button" data-lead-action="delete" data-lead-id="<?php echo isset($lead['id']) ? (int) $lead['id'] : 0; ?>" data-lead-name="<?php echo htmlspecialchars($leadName); ?>">Delete</button></li>
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
                <div class="lead-sidebar__header">
                    <div class="lead-sidebar__headline">
                        <h2 class="lead-sidebar__name" data-lead-field="name">Lead Name</h2>
                        <div class="lead-sidebar__stage">
                            <span class="lead-stage-pill" data-lead-field="stage">Stage</span>
                        </div>
                    </div>
                    <button type="button" class="lead-sidebar__close" data-action="close" aria-label="Close lead details">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
                <div class="lead-sidebar__meta">
                    <div class="lead-rating" data-lead-rating>
                        <div class="lead-rating__stars" role="radiogroup" aria-label="Lead rating">
                            <?php for ($star = 1; $star <= 5; $star++): ?>
                                <button type="button" class="lead-rating__star" data-rating-star="<?php echo $star; ?>" aria-label="Rate <?php echo $star; ?> star<?php echo $star === 1 ? '' : 's'; ?>" aria-pressed="false">
                                    <i class="bi bi-star"></i>
                                </button>
                            <?php endfor; ?>
                        </div>
                        <span class="lead-rating__value" data-lead-field="ratingLabel">Not rated</span>
                    </div>
                    <div class="lead-quick-actions">
                        <a href="#" class="lead-quick-actions__btn" data-action="call"><i class="bi bi-telephone"></i> Call</a>
                        <a href="#" class="lead-quick-actions__btn" data-action="email"><i class="bi bi-envelope"></i> Email</a>
                        <a href="#" class="lead-quick-actions__btn" data-action="whatsapp"><i class="bi bi-whatsapp"></i> WhatsApp</a>
                    </div>
                </div>

                <section class="lead-sidebar__section">
                    <h3 class="lead-sidebar__section-title">Contact Information</h3>
                    <div class="lead-sidebar__details">
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-envelope"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Email</span>
                                <a href="#" class="lead-sidebar__item-value" data-lead-field="email" data-empty-text="No email provided">No email provided</a>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-telephone"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Phone Number</span>
                                <a href="#" class="lead-sidebar__item-value" data-lead-field="phone" data-empty-text="No phone number">No phone number</a>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-flag"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Nationality</span>
                                <span class="lead-sidebar__item-value" data-lead-field="nationality">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-geo-alt"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Location</span>
                                <span class="lead-sidebar__item-value" data-lead-field="location">—</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="lead-sidebar__section">
                    <h3 class="lead-sidebar__section-title">Property Preferences</h3>
                    <div class="lead-sidebar__details">
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-building"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Property Type</span>
                                <span class="lead-sidebar__item-value" data-lead-field="propertyType">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-collection"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Properties Interested In</span>
                                <div class="lead-sidebar__chips" data-lead-field="interestedIn"></div>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-cash-coin"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Budget Range</span>
                                <span class="lead-sidebar__item-value" data-lead-field="budget">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-calendar-event"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Timeline / Expected Move-in</span>
                                <span class="lead-sidebar__item-value" data-lead-field="moveIn">—</span>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="lead-sidebar__section">
                    <h3 class="lead-sidebar__section-title">Lead Information</h3>
                    <div class="lead-sidebar__details">
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-megaphone"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Source</span>
                                <span class="lead-sidebar__item-value" data-lead-field="source">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-person-check"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Assigned To</span>
                                <span class="lead-sidebar__item-value" data-lead-field="assignedTo">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-clock-history"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Created At</span>
                                <span class="lead-sidebar__item-value" data-lead-field="createdAt">—</span>
                            </div>
                        </div>
                        <div class="lead-sidebar__item">
                            <span class="lead-sidebar__item-icon"><i class="bi bi-tags"></i></span>
                            <div class="lead-sidebar__item-content">
                                <span class="lead-sidebar__item-label">Tags</span>
                                <div class="lead-sidebar__chips" data-lead-field="tags"></div>
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

                <footer class="lead-sidebar__footer">
                    <button type="button" class="btn btn-outline-primary">Save Changes</button>
                    <button type="button" class="btn btn-light">Update Stage</button>
                    <button type="button" class="btn btn-primary">Create Task</button>
                </footer>
            </div>
        </aside>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
