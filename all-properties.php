<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';
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
                        <th scope="col" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">Marina Heights Tower</div>
                        </td>
                        <td>
                            <i class="bx bx-map me-1"></i> Dubai Marina
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">Apartment</span>
                        </td>
                        <td>
                            <span class="fw-semibold text-success">$1,250,000</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bx bx-show-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">Palm Residences</div>
                        </td>
                        <td>
                            <i class="bx bx-map me-1"></i> Palm Jumeirah
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">Villa</span>
                        </td>
                        <td>
                            <span class="fw-semibold text-success">$3,450,000</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bx bx-show-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="fw-semibold text-dark">Downtown Views</div>
                        </td>
                        <td>
                            <i class="bx bx-map me-1"></i> Downtown Dubai
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">Penthouse</span>
                        </td>
                        <td>
                            <span class="fw-semibold text-success">$5,200,000</span>
                        </td>
                        <td class="text-end">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit">
                                    <i class="bx bx-edit-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" title="View">
                                    <i class="bx bx-show-alt"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Delete">
                                    <i class="bx bx-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
