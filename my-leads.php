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
        $firstPart = trim($firstPart, " \t\n\r\0\x0B\"'");
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
    $trimmed = trim($name);
    if ($trimmed === '') {
        return '—';
    }

    if (function_exists('mb_substr') && function_exists('mb_strtoupper')) {
        return mb_strtoupper(mb_substr($trimmed, 0, 1, 'UTF-8'), 'UTF-8');
    }

    return strtoupper(substr($trimmed, 0, 1));
}

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
    $sql = "SELECT id, name, email, phone, stage, assigned_to, source FROM all_leads WHERE assigned_to IS NOT NULL AND TRIM(assigned_to) <> '' AND LOWER(TRIM(assigned_to)) IN ($placeholders) ORDER BY created_at DESC";
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
    $sql = "SELECT id, name, email, phone, stage, assigned_to, source FROM all_leads WHERE assigned_to IS NOT NULL AND TRIM(assigned_to) <> '' AND ($likeFragments) ORDER BY created_at DESC";
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

$leads = array_values($leadsById);

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
                                    $leadName = trim((string) ($lead['name'] ?? ''));
                                    $leadEmail = trim((string) ($lead['email'] ?? ''));
                                    $leadPhone = trim((string) ($lead['phone'] ?? ''));
                                    $assignedLabel = trim((string) ($lead['assigned_to'] ?? ''));
                                    $sourceLabel = trim((string) ($lead['source'] ?? ''));
                                    $stageLabel = format_lead_stage($lead['stage'] ?? '');
                                    $stageClass = stage_badge_class($stageLabel);
                                    $avatarInitial = lead_avatar_initial($leadName !== '' ? $leadName : ($assignedLabel !== '' ? $assignedLabel : ''));
                                    $displayName = $leadName !== '' ? $leadName : 'Unnamed Lead';
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="lead-info">
                                                <div class="avatar"><?php echo htmlspecialchars($avatarInitial); ?></div>
                                                <div><strong><?php echo htmlspecialchars($displayName); ?></strong></div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="contact-info">
                                                <?php if ($leadEmail !== ''): ?>
                                                    <span><i class="bi bi-envelope"></i> <?php echo htmlspecialchars($leadEmail); ?></span>
                                                    <?php if ($leadPhone !== ''): ?><br><?php endif; ?>
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
                                        <td><?php echo $assignedLabel !== '' ? htmlspecialchars($assignedLabel) : '—'; ?></td>
                                        <td><?php echo $sourceLabel !== '' ? htmlspecialchars($sourceLabel) : '—'; ?></td>
                                        <td>
                                            <div class="action-icons">
                                                <a href="all-leads.php" class="text-primary" title="View details in All Leads"><i class="bi bi-box-arrow-up-right"></i></a>
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