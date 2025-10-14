<?php
session_start();

if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/includes/config.php';

$pageTitle = 'Channel Partners';

include __DIR__ . '/includes/common-header.php';
?>

<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-6">
                    <h1 class="main-heading">All Channel Partners</h1>
                    <p class="subheading">Overview of your channel partner network</p>
                </div>
                <div class="col-lg-6">
                    <div class="text-end">
                        <button type="button" class="btn btn-primary" data-open-lead-sidebar aria-controls="leadSidebar">
                            <i class="bx bx-user-plus me-1"></i> Add Partner
                        </button>
                    </div>
                </div>
            </div>
            <div class="row g-3 lead-stats">
                <div class="col-md-3">
                    <div class="stat-card total-leads">
                        <h6>Total Partners</h6>
                        <h2>6</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card active-leads">
                        <h6>Active</h6>
                        <h2>3</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card closed-leads">
                        <h6>Pending</h6>
                        <h2>1</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card lost-leads">
                        <h6>Inactive</h6>
                        <h2>1</h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Bootstrap row used below (BOOTSTRAP class used by .row); note row itself has bootstrap class only -->
            <div class="row">
                <!-- To respect "no mixing" rule: each column uses Bootstrap col-* classes only -->
                <div class="col-12">
                    <div class="filter-bar">
                        <!-- A custom inner wrapper that will hold grid layout (custom class only) -->
                        <div class="inner-wrap">
                            <div class="filter-grid">
                                <!-- Column 1: Search (Bootstrap col was above so inner grid controls width) -->
                                <div>
                                    <!-- Parent div for input styles (custom) -->
                                    <div class="search-wrap">
                                        <!-- Icon -->
                                        <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <circle cx="11" cy="11" r="7" stroke="currentColor" stroke-width="1.6"></circle>
                                            <path d="M20 20 L16.65 16.65" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"></path>
                                        </svg>
                                        <!-- Input has NO class per rule (parent has class) -->
                                        <input type="text" placeholder="Search by company, contact, email, or phone..." aria-label="Search">
                                    </div>
                                </div>

                                <!-- Column 2: Status select (parent custom wrapper present) -->
                                <div>
                                    <div class="select-wrap">
                                        <select aria-label="Status filter" class="select-dropDownClass">
                                            <option>All Statuses</option>
                                            <option>Active</option>
                                            <option>Pending</option>
                                            <option>Closed</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 3: Countries select (with outline-parent for focus visual) -->
                                <div>
                                    <div class="select-wrap outline">
                                        <select aria-label="Country filter" class="select-dropDownClass">
                                            <option>All Countries</option>
                                            <option>UAE</option>
                                            <option>India</option>
                                            <option>USA</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Column 4: Clear button -->
                                <div>
                                    <div class="action-wrap d-flex gap-2">
                                        <!-- Button has NO class (parent has class) -->
                                        <button type="button" id="clearFilters" aria-label="Clear filters">
                                            <span class="x" aria-hidden="true">&times;</span> Clear
                                        </button>
                                        <button type="button" class="btn btn-primary">
                                            Apply Filter
                                        </button>
                                    </div>
                                </div>

                            </div> <!-- /.filter-grid -->
                        </div> <!-- /.inner-wrap -->
                    </div> <!-- /.col-12 -->
                </div> <!-- /.row -->
            </div> <!-- /.filter-bar -->
        </div>

        <div class="card lead-table-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 lead-table">
                        <thead>
                            <tr>
                                <th scope="col">Partner Code</th>
                                <th scope="col">Company Name</th>
                                <th scope="col">Contact Person</th>
                                <!-- <th scope="col">Email</th>
                                <th scope="col">Phone/WhatsApp</th> -->
                                <th scope="col">Country</th>
                                <th scope="col">Status</th>
                                <th scope="col">Commission</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="lead-table-row" data-lead-json='{"id":1,"name":"John Doe","email":"john@example.com","phone":"+971 555 1234","stage":"New Lead","stageClass":"stage-new","source":"Website","assignedTo":"Kasim","avatarInitial":"J"}' data-lead-id="1" data-lead-name="John Doe" tabindex="0" role="button" aria-label="View details for John Doe">
                                <td>
                                    #12
                                </td>
                                <td>
                                    <div class="lead-info">
                                        <div class="avatar" data-lead-avatar>R</div>
                                        <div><strong data-lead-name>Reliant Surveyors</strong></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info" data-lead-contact>
                                        <span data-lead-contact-email><i class="bi bi-envelope"></i> john@example.com</span><br>
                                        <span data-lead-contact-phone><i class="bi bi-telephone"></i> +971 555 1234</span>
                                    </div>
                                </td>
                                <td data-lead-stage>
                                    <div class="stage-badge stage-new" data-lead-stage-pill>New Lead</div>
                                </td>
                                <td>
                                    <div class="assigned-dropdown" data-prevent-lead-open>
                                        <select class="form-select assigned-select" data-lead-assigned-select>
                                            <option value="">Unassigned</option>
                                            <option value="Kasim" selected>Kasim</option>
                                            <option value="Shoaib">Shoaib</option>
                                            <option value="Ravi">Ravi</option>
                                        </select>
                                    </div>
                                </td>
                                <td data-lead-source>30%</td>
                                <td>
                                    <div class="dropdown" data-prevent-lead-open>
                                        <button class="btn btn-link p-0 border-0 text-dark" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-three-dots-vertical fs-5"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><button class="dropdown-item" type="button" data-lead-action="view">
                                                    <i class="bi bi-eye me-2"></i> View Details
                                                </button></li>

                                            <li><button class="dropdown-item" type="button" data-lead-action="edit">
                                                    <i class="bi bi-pencil-square me-2"></i> Edit Partner
                                                </button></li>

                                            <li><button class="dropdown-item" type="button" data-lead-action="documents">
                                                    <i class="bi bi-file-earmark-text me-2"></i> Documents
                                                </button></li>

                                            <li><button class="dropdown-item active-item" type="button" data-lead-action="mark-active">
                                                    <i class="bi bi-check-circle me-2"></i> Mark as Active
                                                </button></li>

                                            <li><button class="dropdown-item" type="button" data-lead-action="mark-inactive">
                                                    <i class="bi bi-x-circle me-2"></i> Mark as Inactive
                                                </button></li>

                                            <li><button class="dropdown-item" type="button" data-lead-action="duplicate">
                                                    <i class="bi bi-files me-2"></i> Duplicate
                                                </button></li>

                                            <li><button class="dropdown-item" type="button" data-lead-action="archive">
                                                    <i class="bi bi-archive me-2"></i> Archive
                                                </button></li>

                                            <li><button class="dropdown-item text-danger" type="button" data-lead-action="delete" data-lead-id="1" data-lead-name="John Doe">
                                                    <i class="bi bi-trash me-2"></i> Delete
                                                </button></li>
                                        </ul>

                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <nav class="p-3" aria-label="Lead pagination">
                    <div class="row">

                        <div class="col-lg-12">
                            <ul class="pagination justify-content-center mb-0">
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&laquo;</span>
                                </li>
                                <li class="page-item active">
                                    <span class="page-link" aria-current="page">1</span>
                                </li>
                                <li class="page-item disabled">
                                    <span class="page-link" aria-hidden="true">&raquo;</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </nav>
            </div>
        </div>


        <div class="lead-sidebar-overlay" id="leadSidebarOverlay" hidden></div>
        <aside class="lead-sidebar" id="leadSidebar" aria-hidden="true">
            <div class="lead-sidebar__inner">
                <header class="lead-sidebar__header">
                    <div class="lead-sidebar__header-background"></div>
                    <div class="lead-sidebar__header-actions">
                        <button type="button" class="lead-sidebar__action-btn lead-sidebar__close" data-action="close" aria-label="Close sidebar">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="lead-sidebar__header-content">
                        <div class="lead-sidebar__header-text">
                            <p class="lead-sidebar__header-title mb-1">Add New Partner</p>
                            <p class="text-white small">Enter partner details to add them to your network</p>
                        </div>
                    </div>
                </header>

                <div class="lead-sidebar__body">
                    <form id="addPartnerForm" class="lead-sidebar__form" novalidate>
                        <section class="lead-sidebar__section">
                            <h3 class="lead-sidebar__section-title">Basic Details</h3>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Company Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="company_name" placeholder="Enter company name" required data-sidebar-focus>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Contact Person <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="contact_person" placeholder="Full name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" name="email" placeholder="email@example.com" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Phone <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" name="phone" placeholder="+971-50-123-4567" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">WhatsApp</label>
                                    <input type="tel" class="form-control" name="whatsapp" placeholder="+971-50-123-4567">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Country <span class="text-danger">*</span></label>
                                    <select class="select-dropDownClass" name="country" required>
                                        <option value="">Select country</option>
                                        <option value="India">India</option>
                                        <option value="United Arab Emirates">United Arab Emirates (UAE)</option>
                                        <option value="United Kingdom">United Kingdom (UK)</option>
                                        <option value="United States">United States (USA)</option>
                                        <option value="Saudi Arabia">Saudi Arabia</option>
                                        <option value="Canada">Canada</option>
                                        <option value="Australia">Australia</option>
                                        <option value="Qatar">Qatar</option>
                                        <option value="Singapore">Singapore</option>
                                        <option value="Germany">Germany</option>
                                    </select>

                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">City</label>
                                    <input type="text" class="form-control" name="city" placeholder="Enter city">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Address</label>
                                    <textarea class="form-control" name="address" placeholder="Full address" rows="2"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Compliance & Business</h3>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">RERA Number</label>
                                    <input type="text" class="form-control" name="rera_number" placeholder="RERA-12345">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">License Number</label>
                                    <input type="text" class="form-control" name="license_number" placeholder="LIC-67890">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Website</label>
                                    <input type="url" class="form-control" name="website" placeholder="https://example.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Status <span class="text-danger">*</span></label>
                                    <select class="select-dropDownClass" name="status" required>
                                        <option value="Pending">Pending</option>
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Commission Structure <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="commission_structure" placeholder="e.g., 3% or 3000" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Remarks</label>
                                    <textarea class="form-control" name="remarks" placeholder="Additional notes..." rows="2"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="lead-sidebar__section mt-4">
                            <h3 class="lead-sidebar__section-title">Documents Upload</h3>
                            <div class="mb-3">
                                <label class="form-label">RERA Certificate</label>
                                <input type="file" class="form-control" name="rera_certificate" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Trade License</label>
                                <input type="file" class="form-control" name="trade_license" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Agreement/MOU</label>
                                <input type="file" class="form-control" name="agreement" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG, PNG (Max 5MB)</small>
                            </div>
                        </section>

                        <div class="text-end mt-4">
                            <button type="submit" class="btn btn-primary me-2">Save Partner</button>
                            <button type="button" class="btn btn-outline-secondary" data-action="close">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
</body>

</html>