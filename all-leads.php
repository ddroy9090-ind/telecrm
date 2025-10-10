<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';
include __DIR__ . '/includes/common-header.php';

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

function resolve_assigned_to($rawAssignedTo, array $users): string
{
    if ($rawAssignedTo === null || $rawAssignedTo === '') {
        return '';
    }

    $rawAssignedTo = trim((string) $rawAssignedTo);

    if ($rawAssignedTo !== '' && ctype_digit($rawAssignedTo)) {
        $userId = (int) $rawAssignedTo;
        if (isset($users[$userId])) {
            return $users[$userId];
        }
    }

    foreach ($users as $userName) {
        if (strcasecmp($userName, $rawAssignedTo) === 0) {
            return $userName;
        }
    }

    return $rawAssignedTo;
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
                                    $assignedName = resolve_assigned_to($lead['assigned_to'] ?? '', $users);
                                    $sourceLabel = trim($lead['source'] ?? '') !== '' ? $lead['source'] : '—';
                                    $avatarInitial = lead_avatar_initial($leadName);
                                    ?>
                                    <tr>
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
                                            <div class="assigned-dropdown">
                                                <select class="form-select assigned-select">
                                                    <option value="" <?php echo $assignedName === '' ? 'selected' : ''; ?>>Unassigned</option>
                                                    <?php $selectedMatchFound = false; ?>
                                                    <?php foreach ($users as $userId => $userName): ?>
                                                        <?php
                                                        $isSelected = ($lead['assigned_to'] !== null && $lead['assigned_to'] !== '' && ((string) $lead['assigned_to'] === (string) $userId || strcasecmp($lead['assigned_to'], $userName) === 0));
                                                        if ($isSelected) {
                                                            $selectedMatchFound = true;
                                                        }
                                                        ?>
                                                        <option value="<?php echo htmlspecialchars((string) $userId); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($userName); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <?php if (!$selectedMatchFound && $assignedName !== ''): ?>
                                                        <option value="<?php echo htmlspecialchars((string) ($lead['assigned_to'] ?? '')); ?>" selected>
                                                            <?php echo htmlspecialchars($assignedName); ?>
                                                        </option>
                                                    <?php endif; ?>
                                                </select>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($sourceLabel); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                                    <i class="bi bi-three-dots-vertical fs-5"></i>
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <li><button class="dropdown-item" type="button">View</button></li>
                                                    <li><button class="dropdown-item" type="button">Edit</button></li>
                                                    <li><button class="dropdown-item" type="button">Delete</button></li>
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

    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>