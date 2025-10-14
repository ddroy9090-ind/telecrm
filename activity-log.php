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

if (!function_exists('hh_normalize_metadata_display')) {
    function hh_normalize_metadata_display(?string $metadata): ?string
    {
        if ($metadata === null) {
            return null;
        }

        $metadata = trim($metadata);
        if ($metadata === '') {
            return null;
        }

        $decoded = json_decode($metadata, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            if ($decoded === null) {
                return 'null';
            }

            return json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        return $metadata;
    }
}

$activities = [];
$activityTableReady = hh_ensure_lead_activity_table($mysqli);

if ($activityTableReady) {
    $activitySql = <<<SQL
SELECT
    log.id,
    log.lead_id,
    log.activity_type,
    log.description,
    log.metadata,
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
ORDER BY log.created_at DESC, log.id DESC
SQL;

    $result = $mysqli->query($activitySql);
    if ($result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            $row['metadata_display'] = hh_normalize_metadata_display($row['metadata'] ?? null);
            $activities[] = $row;
        }
        $result->free();
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="main-heading">Activity Log</h1>
                <p class="subheading">Review every interaction captured for your leads</p>
            </div>
        </div>

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
                                <th scope="col">Metadata</th>
                                <th scope="col">Created By</th>
                                <th scope="col">Created At</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!$activityTableReady || count($activities) === 0): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No activity has been recorded yet.</td>
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

                                    $metadataDisplay = $activity['metadata_display'] ?? null;
                                    $metadataTooltip = $metadataDisplay !== null && $metadataDisplay !== ''
                                        ? preg_replace('/\s+/', ' ', $metadataDisplay)
                                        : '—';

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
                                        <td title="<?php echo htmlspecialchars($descriptionTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php
                                            if ($description === '') {
                                                echo '<span class="text-muted">—</span>';
                                            } else {
                                                echo nl2br(htmlspecialchars($description), false);
                                            }
                                            ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($metadataTooltip, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php if (!empty($metadataDisplay)): ?>
                                                <pre class="mb-0 small text-break"><?php echo htmlspecialchars($metadataDisplay); ?></pre>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($createdByDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo $createdBy !== '' ? htmlspecialchars($createdBy) : '<span class="text-muted">System</span>'; ?>
                                        </td>
                                        <td title="<?php echo htmlspecialchars($createdAtDisplay, ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($createdAtDisplay); ?>
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
