<?php
session_start();

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

    <main class="main-content">
        <form action="">
            <div class="allLeads">
                <div class="container-fluid p-0">
                    <div class="row align-items-center mb-4">
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
                    <div class="filters-section" id="leadFilters">
                        <div class="filters-header d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0 fw-semibold">FILTERS</h6>
                            <a href="#" class="clear-all"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x "><path d="M18 6 6 18"></path><path d="m6 6 12 12"></path></svg> Clear All</a>
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
                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="avatar">N</div>
                                        <div><strong>Nadia Petrov</strong></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <span><i class="bi bi-envelope"></i> nadia.petrov@email.com</span><br>
                                        <span><i class="bi bi-telephone"></i> +1 202-555-0143</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stage-dropdown">
                                        <select class="form-select stage-select">
                                            <option selected>New</option>
                                            <option>Contacted</option>
                                            <option>Qualified</option>
                                            <option>Proposal</option>
                                            <option>Closed</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="assigned-dropdown">
                                        <select class="form-select assigned-select">
                                            <option selected>John Smith</option>
                                            <option>Sarah Lee</option>
                                            <option>David Brown</option>
                                            <option>Emma Wilson</option>
                                        </select>
                                    </div>
                                </td>
                                <td>Agent Referral</td>
                                <td>
                                    <div class="action-icons">
                                        <a href="#" class="text-primary me-2" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="#" class="text-warning me-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="avatar">M</div>
                                        <div><strong>Michael Johnson</strong></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <span><i class="bi bi-envelope"></i> michael.j@email.com</span><br>
                                        <span><i class="bi bi-telephone"></i> +1 202-555-0199</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stage-dropdown">
                                        <select class="form-select stage-select">
                                            <option>New</option>
                                            <option selected>Contacted</option>
                                            <option>Qualified</option>
                                            <option>Proposal</option>
                                            <option>Closed</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="assigned-dropdown">
                                        <select class="form-select assigned-select">
                                            <option>John Smith</option>
                                            <option selected>Sarah Lee</option>
                                            <option>David Brown</option>
                                            <option>Emma Wilson</option>
                                        </select>
                                    </div>
                                </td>
                                <td>Website Inquiry</td>
                                <td>
                                    <div class="action-icons">
                                        <a href="#" class="text-primary me-2" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="#" class="text-warning me-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger" title="Delete"><i class="bi bi-trash"></i></a>
                                    </div>
                                </td>
                            </tr>

                            <tr>
                                <td>
                                    <div class="lead-info">
                                        <div class="avatar">A</div>
                                        <div><strong>Ayesha Khan</strong></div>
                                    </div>
                                </td>
                                <td>
                                    <div class="contact-info">
                                        <span><i class="bi bi-envelope"></i> ayesha.khan@email.com</span><br>
                                        <span><i class="bi bi-telephone"></i> +971 55 789 4561</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="stage-dropdown">
                                        <select class="form-select stage-select">
                                            <option>New</option>
                                            <option>Contacted</option>
                                            <option selected>Qualified</option>
                                            <option>Proposal</option>
                                            <option>Closed</option>
                                        </select>
                                    </div>
                                </td>
                                <td>
                                    <div class="assigned-dropdown">
                                        <select class="form-select assigned-select">
                                            <option>John Smith</option>
                                            <option>Sarah Lee</option>
                                            <option selected>David Brown</option>
                                            <option>Emma Wilson</option>
                                        </select>
                                    </div>
                                </td>
                                <td>Social Media</td>
                                <td>
                                    <div class="action-icons">
                                        <a href="#" class="text-primary me-2" title="View"><i class="bi bi-eye"></i></a>
                                        <a href="#" class="text-warning me-2" title="Edit"><i class="bi bi-pencil"></i></a>
                                        <a href="#" class="text-danger" title="Delete"><i class="bi bi-trash"></i></a>
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