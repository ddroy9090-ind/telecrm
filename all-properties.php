<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$pdo = hh_db();

// Handle flash messages
$flash = $_SESSION['all_properties_flash'] ?? ['success' => null, 'error' => null];
unset($_SESSION['all_properties_flash']);

// Handle delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_property_id'])) {
    $propertyId = (int) ($_POST['delete_property_id'] ?? 0);

    if ($propertyId > 0) {
        try {
            $stmt = $pdo->prepare('DELETE FROM properties_list WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $propertyId]);

            if ($stmt->rowCount() > 0) {
                $_SESSION['all_properties_flash'] = [
                    'success' => 'Property deleted successfully.',
                    'error'   => null,
                ];
            } else {
                $_SESSION['all_properties_flash'] = [
                    'success' => null,
                    'error'   => 'Property not found or already deleted.',
                ];
            }
        } catch (Throwable $e) {
            $_SESSION['all_properties_flash'] = [
                'success' => null,
                'error'   => 'Failed to delete property. Please try again later.',
            ];
        }
    } else {
        $_SESSION['all_properties_flash'] = [
            'success' => null,
            'error'   => 'Invalid property selected.',
        ];
    }

    header('Location: all-properties.php');
    exit;
}

try {
    $propertiesStmt = $pdo->query(
        'SELECT id, project_name, property_title, property_location, property_type, starting_price, brochure, created_at
         FROM properties_list
         ORDER BY created_at DESC, id DESC'
    );
    $properties = $propertiesStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $properties = [];
    $flash['error'] = $flash['error'] ?? 'Failed to load properties. Please try again later.';
}

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="dashboard-wrap">
            <div class="dashboard-header">
                <h1 class="main-heading">All Property List</h1>
                <p class="subheading">Manage and track all your real estate leads</p>
            </div>
        </div>
        <div class="row g-3 lead-stats">
            <div class="col-md-3">
                <div class="stat-card total-leads">
                    <h6>Offplan Projects</h6>
                    <h2>0</h2>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card active-leads">
                    <h6>Buy Property</h6>
                    <h2>0</h2>
                    
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card closed-leads">
                    <h6>Rent Property</h6>
                    <h2>0</h2>
                    
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card lost-leads">
                    <h6>Active Projects</h6>
                    <h2>0</h2>
                </div>
            </div>
        </div>
        <?php if (!empty($flash['success'])): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($flash['success'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($flash['error'])): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($flash['error'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>
        <div class="card lead-table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 lead-table">
                <thead>
                    <tr>
                        <th scope="col">Project Name</th>
                        <th scope="col">Location</th>
                        <th scope="col">Property Type</th>
                        <th scope="col">Price</th>
                        <th scope="col">Brochure</th>
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$properties): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4">
                            No properties found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($properties as $property): ?>
                        <?php
                        $propertyId = (int) ($property['id'] ?? 0);
                        $projectName = $property['project_name'] ?? '';
                        $propertyTitle = $property['property_title'] ?? '';
                        $displayName = $projectName !== '' ? $projectName : $propertyTitle;
                        $displayName = $displayName !== '' ? $displayName : 'Untitled Property';
                        $location = trim((string) ($property['property_location'] ?? ''));
                        $propertyType = trim((string) ($property['property_type'] ?? ''));
                        $startingPrice = trim((string) ($property['starting_price'] ?? ''));
                        $brochurePath = trim((string) ($property['brochure'] ?? ''));
                        $brochureUrl = '';
                        if ($brochurePath !== '') {
                            if (preg_match('#^(?:https?:)?//#i', $brochurePath)) {
                                $brochureUrl = $brochurePath;
                            } else {
                                $brochureUrl = hh_asset($brochurePath);
                            }
                        }
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <?php echo htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8'); ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($location !== ''): ?>
                                    <i class="bx bx-map me-1"></i>
                                    <?php echo htmlspecialchars($location, ENT_QUOTES, 'UTF-8'); ?>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($propertyType !== ''): ?>
                                    <span class="badge bg-light text-dark">
                                        <?php echo htmlspecialchars($propertyType, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($startingPrice !== ''): ?>
                                    <span class="fw-semibold text-success">
                                        <?php echo htmlspecialchars($startingPrice, ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($brochureUrl !== ''): ?>
                                    <a
                                        href="<?php echo htmlspecialchars($brochureUrl, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn btn-sm btn-outline-success"
                                        target="_blank"
                                        rel="noopener"
                                        download
                                    >
                                        <i class="bx bx-download"></i>
                                        <span class="ms-1">Download</span>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                    <a
                                        href="<?php echo htmlspecialchars('add-property.php?edit=' . $propertyId, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn btn-sm btn-outline-secondary"
                                        title="Edit"
                                    >
                                        <i class="bx bx-edit-alt"></i>
                                    </a>
                                    <a
                                        href="<?php echo htmlspecialchars('property-details.php?id=' . $propertyId, ENT_QUOTES, 'UTF-8'); ?>"
                                        class="btn btn-sm btn-outline-primary"
                                        title="View"
                                    >
                                        <i class="bx bx-show-alt"></i>
                                    </a>
                                    <form
                                        method="post"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete this property?');"
                                    >
                                        <input type="hidden" name="delete_property_id" value="<?php echo $propertyId; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete" style="font-size: 18px;">
                                            <i class="bx bx-trash"></i>
                                        </button>
                                    </form>
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
