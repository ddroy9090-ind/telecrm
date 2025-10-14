<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

if (!function_exists('hh_ensure_lead_activity_table')) {
    /**
     * Ensure the lead_activity_log table exists for legacy installations.
     */
    function hh_ensure_lead_activity_table(mysqli $mysqli): bool
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
}

if (!function_exists('hh_format_activity_timestamp')) {
    function hh_format_activity_timestamp(?string $timestamp): string
    {
        if ($timestamp === null || trim($timestamp) === '') {
            return '—';
        }

        $time = strtotime($timestamp);
        if ($time === false) {
            return trim((string) $timestamp);
        }

        return date('M d, Y g:i A', $time);
    }
}

if (!isset($_SESSION['activity_delete_message'], $_SESSION['activity_delete_type'])) {
    $_SESSION['activity_delete_message'] = null;
    $_SESSION['activity_delete_type'] = null;
}

$activities = [];
$perPage = 10;
$activityTableReady = hh_ensure_lead_activity_table($mysqli);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_activity_id'])) {
    $deleteMessage = 'Unable to delete the selected activity. Please try again.';
    $deleteType = 'danger';

    $activityId = filter_input(INPUT_POST, 'delete_activity_id', FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);

    if ($activityId) {
        if ($activityTableReady) {
            $deleteStatement = $mysqli->prepare('DELETE FROM lead_activity_log WHERE id = ?');
            if ($deleteStatement instanceof mysqli_stmt) {
                $deleteStatement->bind_param('i', $activityId);
                if ($deleteStatement->execute()) {
                    if ($deleteStatement->affected_rows > 0) {
                        $deleteMessage = 'Activity deleted successfully.';
                        $deleteType = 'success';
                    } else {
                        $deleteMessage = 'Activity not found or already removed.';
                        $deleteType = 'warning';
                    }
                }
                $deleteStatement->close();
            }
        } else {
            $deleteMessage = 'Unable to delete activity because the activity log table is not available.';
            $deleteType = 'danger';
        }
    } else {
        $deleteMessage = 'Invalid activity selected for deletion.';
        $deleteType = 'danger';
    }

    $_SESSION['activity_delete_message'] = $deleteMessage;
    $_SESSION['activity_delete_type'] = $deleteType;

    $redirectUrl = 'activity-log.php';
    if (!empty($_SERVER['QUERY_STRING'])) {
        $redirectUrl .= '?' . $_SERVER['QUERY_STRING'];
    }

    header('Location: ' . $redirectUrl);
    exit;
}

$deleteFlashMessage = $_SESSION['activity_delete_message'] ?? null;
$deleteFlashType = $_SESSION['activity_delete_type'] ?? 'info';

$_SESSION['activity_delete_message'] = null;
$_SESSION['activity_delete_type'] = null;

$validAlertTypes = ['success', 'warning', 'danger', 'info'];
if (!in_array($deleteFlashType, $validAlertTypes, true)) {
    $deleteFlashType = 'info';
}

$searchTerm = filter_input(INPUT_GET, 'search', FILTER_UNSAFE_RAW);
if (is_string($searchTerm)) {
    $searchTerm = trim($searchTerm);
} else {
    $searchTerm = '';
}

$startDateParam = filter_input(INPUT_GET, 'start_date', FILTER_UNSAFE_RAW);
$endDateParam = filter_input(INPUT_GET, 'end_date', FILTER_UNSAFE_RAW);

$startDate = null;
$endDate = null;

if (is_string($startDateParam) && $startDateParam !== '') {
    $startDateCandidate = DateTime::createFromFormat('Y-m-d', $startDateParam);
    if ($startDateCandidate instanceof DateTime) {
        $startDate = $startDateCandidate->setTime(0, 0, 0);
    }
}

if (is_string($endDateParam) && $endDateParam !== '') {
    $endDateCandidate = DateTime::createFromFormat('Y-m-d', $endDateParam);
    if ($endDateCandidate instanceof DateTime) {
        $endDate = $endDateCandidate->setTime(23, 59, 59);
    }
}

if ($startDate && $endDate && $startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$pageParam = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$currentPage = $pageParam ?: 1;

$totalActivities = 0;
$totalPages = 1;
$offset = ($currentPage - 1) * $perPage;

if ($offset < 0) {
    $offset = 0;
}

if ($activityTableReady) {
    $whereClauses = [];
    $searchParams = [];
    $searchParamTypes = '';

    if ($searchTerm !== '') {
        $searchWildcard = '%' . $searchTerm . '%';
        $whereClauses[] = '(
            log.activity_type LIKE ?
            OR log.description LIKE ?
            OR leads.name LIKE ?
            OR leads.phone LIKE ?
            OR leads.email LIKE ?
            OR log.created_by_name LIKE ?
            OR users.full_name LIKE ?
            OR CAST(log.lead_id AS CHAR) LIKE ?
        )';

        $searchParams = array_fill(0, 8, $searchWildcard);
        $searchParamTypes = str_repeat('s', count($searchParams));
    }

    if ($startDate) {
        $whereClauses[] = 'log.created_at >= ?';
        $searchParams[] = $startDate->format('Y-m-d H:i:s');
        $searchParamTypes .= 's';
    }

    if ($endDate) {
        $whereClauses[] = 'log.created_at <= ?';
        $searchParams[] = $endDate->format('Y-m-d H:i:s');
        $searchParamTypes .= 's';
    }

    $searchConditions = '';
    if (!empty($whereClauses)) {
        $searchConditions = 'WHERE ' . implode(' AND ', $whereClauses);
    }

    $countSql = <<<SQL
SELECT COUNT(*) AS total
FROM lead_activity_log AS log
LEFT JOIN all_leads AS leads ON leads.id = log.lead_id
LEFT JOIN users ON users.id = log.created_by
{$searchConditions}
SQL;

    $countStatement = $mysqli->prepare($countSql);
    if ($countStatement instanceof mysqli_stmt) {
        if ($searchParams) {
            $countStatement->bind_param($searchParamTypes, ...$searchParams);
        }

        if ($countStatement->execute()) {
            $countResult = $countStatement->get_result();
            if ($countResult instanceof mysqli_result) {
                $row = $countResult->fetch_assoc();
                if ($row) {
                    $totalActivities = (int) ($row['total'] ?? 0);
                }
                $countResult->free();
            }
        }

        $countStatement->close();
    }

    if ($totalActivities > 0) {
        $totalPages = (int) ceil($totalActivities / $perPage);
        if ($currentPage > $totalPages) {
            $currentPage = $totalPages;
        }
    } else {
        $totalPages = 1;
        $currentPage = 1;
    }

    $offset = ($currentPage - 1) * $perPage;
    if ($offset < 0) {
        $offset = 0;
    }

    $activitySql = <<<SQL
SELECT
    log.id,
    log.lead_id,
    log.activity_type,
    log.description,
    log.created_by,
    log.created_by_name,
    log.created_at,
    leads.name AS lead_name,
    leads.phone AS lead_phone,
    leads.email AS lead_email,
    users.full_name AS user_full_name
FROM lead_activity_log AS log
LEFT JOIN all_leads AS leads ON leads.id = log.lead_id
LEFT JOIN users ON users.id = log.created_by
{$searchConditions}
ORDER BY log.created_at DESC, log.id DESC
LIMIT ? OFFSET ?
SQL;

    $activityStatement = $mysqli->prepare($activitySql);
    if ($activityStatement instanceof mysqli_stmt) {
        $paramTypes = $searchParamTypes . 'ii';
        $queryParams = array_merge($searchParams, [$perPage, $offset]);

        $activityStatement->bind_param($paramTypes, ...$queryParams);

        if ($activityStatement->execute()) {
            $result = $activityStatement->get_result();
            if ($result instanceof mysqli_result) {
                while ($row = $result->fetch_assoc()) {
                    $activities[] = $row;
                }
                $result->free();
            }
        }

        $activityStatement->close();
    }
}

$pageTitle = 'Activity Log - Admin Panel';
$metaDescription = 'View the complete activity history recorded for leads.';

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <style>
            .activity-log-table {
                table-layout: fixed;
                width: 100%;
            }

            .activity-log-table th,
            .activity-log-table td {
                width: calc(100% / 7);
                word-break: break-word;
            }
        </style>
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
            <div>
                <h1 class="main-heading mb-1">Activity Log</h1>
                <p class="subheading mb-0">Review every interaction captured for your leads</p>
            </div>
            <form method="get" class="d-flex flex-column flex-lg-row gap-2" role="search" aria-label="Search activity log">
                <div class="d-flex gap-2">
                <input
                    type="search"
                    name="search"
                    value="<?php echo htmlspecialchars($searchTerm, ENT_QUOTES, 'UTF-8'); ?>"
                    class="form-control"
                    placeholder="Search activities"
                    aria-label="Search activities"
                >
                <input
                    type="date"
                    name="start_date"
                    value="<?php echo $startDate ? htmlspecialchars($startDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8') : ''; ?>"
                    class="form-control"
                    aria-label="Filter start date"
                >
                <input
                    type="date"
                    name="end_date"
                    value="<?php echo $endDate ? htmlspecialchars($endDate->format('Y-m-d'), ENT_QUOTES, 'UTF-8') : ''; ?>"
                    class="form-control"
                    aria-label="Filter end date"
                >
                </div>
                <div class="d-flex gap-2">
                    <?php if ($searchTerm !== '' || $startDate || $endDate): ?>
                        <a href="activity-log.php" class="btn btn-outline-secondary flex-grow-1 flex-lg-grow-0">Reset</a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary flex-grow-1 flex-lg-grow-0">Apply</button>
                </div>
            </form>
        </div>

        <?php if ($deleteFlashMessage): ?>
            <div class="alert alert-<?php echo htmlspecialchars($deleteFlashType, ENT_QUOTES, 'UTF-8'); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($deleteFlashMessage, ENT_QUOTES, 'UTF-8'); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!$activityTableReady): ?>
            <div class="alert alert-warning" role="alert">
                Unable to load lead activity logs because the <code>lead_activity_log</code> table could not be found or created.
            </div>
        <?php endif; ?>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table activity-log-table">
                        <thead>
                            <tr>
                                <th scope="col">Activity ID</th>
                                <th scope="col">Lead</th>
                                <th scope="col">Activity Type</th>
                                <th scope="col">Description</th>
                                <th scope="col">Created By</th>
                                <th scope="col">Created At</th>
                                <th scope="col" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$activityTableReady || count($activities) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <?php if ($activityTableReady && $searchTerm !== ''): ?>
                                            No activities match your search criteria.
                                        <?php else: ?>
                                            No activity has been recorded yet.
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($activities as $activity): ?>
                                    <?php
                                    $activityIdDisplay = '#' . (int) $activity['id'];

                                    $leadTooltipParts = ['Lead #' . (int) $activity['lead_id']];
                                    if (!empty($activity['lead_name'])) {
                                        $leadTooltipParts[] = (string) $activity['lead_name'];
                                    }
                                    if (!empty($activity['lead_phone'])) {
                                        $leadTooltipParts[] = 'Phone: ' . (string) $activity['lead_phone'];
                                    }
                                    if (!empty($activity['lead_email'])) {
                                        $leadTooltipParts[] = 'Email: ' . (string) $activity['lead_email'];
                                    }
                                    $leadTooltip = implode(' | ', $leadTooltipParts);

                                    $activityTypeDisplay = trim((string) ($activity['activity_type'] ?? ''));
                                    $activityTypeTooltip = $activityTypeDisplay !== '' ? $activityTypeDisplay : '—';

                                    $description = trim((string) ($activity['description'] ?? ''));
                                    $descriptionTooltip = $description !== '' ? preg_replace('/\s+/', ' ', $description) : '—';

                                    $createdBy = trim((string) ($activity['created_by_name'] ?? ''));
                                    if ($createdBy === '' && !empty($activity['user_full_name'])) {
                                        $createdBy = (string) $activity['user_full_name'];
                                    }
                                    if ($createdBy === '' && !empty($activity['created_by'])) {
                                        $createdBy = 'User #' . (int) $activity['created_by'];
                                    }
                                    $createdByDisplay = $createdBy !== '' ? $createdBy : 'System';

                                    $createdAtDisplay = hh_format_activity_timestamp($activity['created_at'] ?? null);
                                    ?>
                                    <tr>
                                        <td title="<?php echo htmlspecialchars($activityIdDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($activityIdDisplay); ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($leadTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars('Lead #' . (int) $activity['lead_id']); ?></div>
                                            <?php if (!empty($activity['lead_name'])): ?>
                                                <div class="text-muted small"><?php echo htmlspecialchars($activity['lead_name']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($activity['lead_phone'])): ?>
                                                <div class="text-muted small">☎ <?php echo htmlspecialchars($activity['lead_phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($activity['lead_email'])): ?>
                                                <div class="text-muted small">✉ <?php echo htmlspecialchars($activity['lead_email']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($activityTypeTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <span class="badge bg-light text-dark border"><?php echo htmlspecialchars($activityTypeDisplay); ?></span>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($descriptionTooltip, ENT_QUOTES, 'UTF-8'); ?>" class="small">
                                            <?php
                                            if ($description === '') {
                                                echo '<span class="text-muted">—</span>';
                                            } else {
                                                echo nl2br(htmlspecialchars($description), false);
                                            }
                                            ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($createdByDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo $createdBy !== '' ? htmlspecialchars($createdBy) : '<span class="text-muted">System</span>'; ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($createdAtDisplay); ?>
                                        </td>
                                        <td class="text-center">
                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this activity?');">
                                                <input type="hidden" name="delete_activity_id" value="<?php echo (int) $activity['id']; ?>">
                                                <button type="submit" class="btn btn-link text-danger p-0" title="Delete activity" aria-label="Delete activity <?php echo htmlspecialchars($activityIdDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Activity log pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    $buildPageUrl = function (int $page) use ($searchTerm, $startDate, $endDate): string {
                        $query = ['page' => $page];
                        if ($searchTerm !== '') {
                            $query['search'] = $searchTerm;
                        }
                        if ($startDate) {
                            $query['start_date'] = $startDate->format('Y-m-d');
                        }
                        if ($endDate) {
                            $query['end_date'] = $endDate->format('Y-m-d');
                        }

                        return 'activity-log.php?' . http_build_query($query);
                    };
                    ?>
                    <li class="page-item<?php echo $currentPage <= 1 ? ' disabled' : ''; ?>">
                        <?php if ($currentPage > 1): ?>
                            <a class="page-link" href="<?php echo htmlspecialchars($buildPageUrl($currentPage - 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Previous page">Previous</a>
                        <?php else: ?>
                            <span class="page-link">Previous</span>
                        <?php endif; ?>
                    </li>

                    <?php for ($page = 1; $page <= $totalPages; $page++): ?>
                        <li class="page-item<?php echo $page === $currentPage ? ' active' : ''; ?>">
                            <?php if ($page === $currentPage): ?>
                                <span class="page-link"><?php echo (int) $page; ?></span>
                            <?php else: ?>
                                <a class="page-link" href="<?php echo htmlspecialchars($buildPageUrl($page), ENT_QUOTES, 'UTF-8'); ?>"><?php echo (int) $page; ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endfor; ?>

                    <li class="page-item<?php echo $currentPage >= $totalPages ? ' disabled' : ''; ?>">
                        <?php if ($currentPage < $totalPages): ?>
                            <a class="page-link" href="<?php echo htmlspecialchars($buildPageUrl($currentPage + 1), ENT_QUOTES, 'UTF-8'); ?>" aria-label="Next page">Next</a>
                        <?php else: ?>
                            <span class="page-link">Next</span>
                        <?php endif; ?>
                    </li>
                </ul>
            </nav>
        <?php endif; ?>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
