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
        <form action="">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-6">
                        <h1 class="main-heading">Dashboard</h1>
                        <p class="subheading">Manage and track all your real estate leads</p>
                    </div>
                    <div class="col-lg-6">
                        <div class="right-search">
                            <input type="text" class="form-control" placeholder="Search leads, projects, clients" />
                            <select class="form-control select-dropDownClass" name="">
                                <option>Last 30 Days</option>
                                <option>Last 7 Days</option>
                                <option>Last Month</option>
                                <option>Last Quarter</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </form>
        <div class="lead-stats-section">
            <div class="container-fluid">
                <div class="row g-3">
                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card">
                            <div class="lead-metric-icon">
                                <i class='bx bx-group'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Total Leads</h6>
                                <h2>1,847</h2>
                                <p>Qualified, Site Visit, Offer stages</p>
                                <div class="lead-metric-growth">
                                    <i class='bx bx-trending-up'></i>
                                    <span>+12.5%</span> <small>vs last month</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card">
                            <div class="lead-metric-icon">
                                <i class='bx bx-trending-up'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Hot / Active Leads</h6>
                                <h2>456</h2>
                                <p>Ready for conversion</p>
                                <div class="lead-metric-growth">
                                    <i class='bx bx-trending-up'></i>
                                    <span>+8.2%</span> <small>vs last month</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card">
                            <div class="lead-metric-icon">
                                <i class='bx bx-dollar'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Closed Deals</h6>
                                <h2>89</h2>
                                <p>AED 124.5M total value</p>
                                <div class="lead-metric-growth">
                                    <i class='bx bx-trending-up'></i>
                                    <span>+15.8%</span> <small>vs last month</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-sm-6 col-lg-3">
                        <div class="lead-metric-card">
                            <div class="lead-metric-icon">
                                <i class='bx bx-user-pin'></i>
                            </div>
                            <div class="lead-metric-content">
                                <h6>Channel Partners</h6>
                                <h2>234</h2>
                                <p>+18 new this month</p>
                                <div class="lead-metric-growth">
                                    <i class='bx bx-trending-up'></i>
                                    <span>+7.7%</span> <small>vs last month</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <section class="lead-analytics-section">
            <div class="container-fluid">
                <div class="row">
                    <!-- Lead Source Analytics -->
                    <div class="col-lg-4">
                        <div class="chart-section">
                            <h5 class="chart-title">Lead Source Analytics</h5>
                            <p class="chart-subtitle">Distribution by channel</p>

                            <div id="chart"></div>

                            <ul class="lead-source-list mt-3">
                                <li><span class="dot meta"></span> Meta Ads <strong>385 (20.8%)</strong></li>
                                <li><span class="dot google"></span> Google Ads <strong>312 (16.9%)</strong></li>
                                <li><span class="dot website"></span> Website <strong>268 (14.5%)</strong></li>
                                <li><span class="dot whatsapp"></span> WhatsApp <strong>445 (24.1%)</strong></li>
                                <li><span class="dot referral"></span> Referral <strong>187 (10.1%)</strong></li>
                                <li><span class="dot channel"></span> Channel Partner <strong>250 (13.5%)</strong></li>
                            </ul>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="top-agents-card">
                            <div class="top-agents-header">
                                <i class="bi bi-trophy"></i>
                                <div>
                                    <h5>Top Agents</h5>
                                    <p>This month's leaders</p>
                                </div>
                            </div>

                            <div class="agent-list">
                                <div class="agent-item">
                                    <div class="agent-info">
                                        <div class="agent-avatar">SA</div>
                                        <div class="agent-details">
                                            <h6>Sarah Ahmed</h6>
                                            <div class="agent-stats">
                                                <span>23 deals</span>
                                                <span class="growth">+15%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="agent-value">
                                        <h6>AED 12.5M</h6>
                                        <span>Total Value</span>
                                    </div>
                                </div>

                                <div class="agent-item">
                                    <div class="agent-info">
                                        <div class="agent-avatar">MA</div>
                                        <div class="agent-details">
                                            <h6>Mohammed Al</h6>
                                            <div class="agent-stats">
                                                <span>19 deals</span>
                                                <span class="growth">+12%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="agent-value">
                                        <h6>AED 10.2M</h6>
                                        <span>Total Value</span>
                                    </div>
                                </div>

                                <div class="agent-item">
                                    <div class="agent-info">
                                        <div class="agent-avatar">FK</div>
                                        <div class="agent-details">
                                            <h6>Fatima Khan</h6>
                                            <div class="agent-stats">
                                                <span>17 deals</span>
                                                <span class="growth">+8%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="agent-value">
                                        <h6>AED 9.8M</h6>
                                        <span>Total Value</span>
                                    </div>
                                </div>

                                <div class="agent-item">
                                    <div class="agent-info">
                                        <div class="agent-avatar">AH</div>
                                        <div class="agent-details">
                                            <h6>Ahmed Hassan</h6>
                                            <div class="agent-stats">
                                                <span>15 deals</span>
                                                <span class="growth">+5%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="agent-value">
                                        <h6>AED 8.1M</h6>
                                        <span>Total Value</span>
                                    </div>
                                </div>

                                <div class="agent-item">
                                    <div class="agent-info">
                                        <div class="agent-avatar">AH</div>
                                        <div class="agent-details">
                                            <h6>Rahul</h6>
                                            <div class="agent-stats">
                                                <span>15 deals</span>
                                                <span class="growth">+5%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="agent-value">
                                        <h6>AED 8.1M</h6>
                                        <span>Total Value</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4">
                        <div class="recent-activity-card">
                            <div class="recent-header">
                                <i class="bi bi-clock-history"></i>
                                <div>
                                    <h5>Recent Activity</h5>
                                    <p>Latest updates</p>
                                </div>
                            </div>

                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon"><i class="bi bi-check-circle"></i></div>
                                    <div class="activity-content">
                                        <p>Deal closed with Ahmed Hassan for Emaar Beachfront</p>
                                        <div class="activity-meta">
                                            <span>5 min ago</span>
                                            <span class="amount">AED 2.8M</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon"><i class="bi bi-telephone"></i></div>
                                    <div class="activity-content">
                                        <p>Follow-up call scheduled with Sarah Al Mansoori</p>
                                        <div class="activity-meta">
                                            <span>12 min ago</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon"><i class="bi bi-file-earmark-text"></i></div>
                                    <div class="activity-content">
                                        <p>MOU signed for Sobha Hartland Villa</p>
                                        <div class="activity-meta">
                                            <span>45 min ago</span>
                                            <span class="amount">AED 3.2M</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon"><i class="bi bi-person-plus"></i></div>
                                    <div class="activity-content">
                                        <p>New lead added from Property Finder</p>
                                        <div class="activity-meta">
                                            <span>1 hour ago</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="activity-item">
                                    <div class="activity-icon"><i class="bi bi-envelope"></i></div>
                                    <div class="activity-content">
                                        <p>Proposal sent to Mohammed Abdullah</p>
                                        <div class="activity-meta">
                                            <span>2 hours ago</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="activity-section">
            <div class="container-fluid">
                <div class="row g-4">
                    <!-- Activity Heatmap -->
                    <div class="col-lg-8">
                        <div class="card heatmap-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <div class="icon-box me-2">
                                                <i class="bi bi-activity"></i>
                                            </div>
                                            <div>
                                                <h5 class="mb-0 fw-semibold">Activity Heatmap</h5>
                                                <p class="text-muted small mb-0">Peak engagement times</p>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <h5 class="fw-semibold text-success mb-0">44%</h5>
                                        <p class="text-muted small mb-0">Avg Activity</p>
                                    </div>
                                </div>

                                <!-- Blank Heatmap Grid -->
                                <div class="heatmap-grid">

                                </div>

                                <div class="text-center small text-muted">
                                    <span>Less</span>
                                    <span class="legend"></span>
                                    <span>More</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Metrics -->
                    <div class="col-lg-4">
                        <div class="card metrics-card">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="icon-box me-2">
                                        <i class="bi bi-bar-chart-line"></i>
                                    </div>
                                    <div>
                                        <h5 class="mb-0 fw-semibold">Performance Metrics</h5>
                                    </div>
                                </div>

                                <!-- Metric 1 -->
                                <div class="metric-item mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-bullseye me-2 text-success"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Target Achievement</p>
                                                <small class="text-muted">Target: 100%</small>
                                            </div>
                                        </div>
                                        <span class="fw-semibold">94%</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:94%"></div>
                                    </div>
                                </div>

                                <!-- Metric 2 -->
                                <div class="metric-item mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-stopwatch me-2 text-success"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Avg. Response Time</p>
                                                <small class="text-muted">Target: &lt; 3h</small>
                                            </div>
                                        </div>
                                        <span class="fw-semibold">2.4h</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-success" style="width:90%"></div>
                                    </div>
                                </div>

                                <!-- Metric 3 -->
                                <div class="metric-item mb-3">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-people me-2 text-primary"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Lead Engagement</p>
                                                <small class="text-muted">Target: 85%</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success bg-opacity-10 text-success">Met</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-primary" style="width:87%"></div>
                                    </div>
                                </div>

                                <!-- Metric 4 -->
                                <div class="metric-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-lightning-charge me-2 text-warning"></i>
                                            <div>
                                                <p class="mb-0 fw-semibold">Deal Velocity</p>
                                                <small class="text-muted">Target: 21 days</small>
                                            </div>
                                        </div>
                                        <span class="badge bg-success bg-opacity-10 text-success">Met</span>
                                    </div>
                                    <div class="progress mt-2" style="height:6px;">
                                        <div class="progress-bar bg-warning" style="width:80%"></div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class=" card lead-table-card">
            <div class="container-fluid p-0">
                <div class="card-body p-0">
                    <div class="inventory-header">
                        <div class="left">
                            <div class="icon"><i class="bi bi-buildings"></i></div>
                            <div>
                                <h5>Property Inventory Summary</h5>
                                <p>Active projects across Dubai</p>
                            </div>
                        </div>
                        <div class="right">
                            <div>
                                <p>Total Inventory Value</p>
                                <h6>AED 3.8B</h6>
                            </div>
                            <div>
                                <p>Avg. Sold Out</p>
                                <h6 class="text-success"><i class="bi bi-graph-up-arrow me-1"></i>70%</h6>
                            </div>
                            <!-- <button class="filter-btn"><i class="bi bi-funnel me-1"></i>Filters</button> -->
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 lead-table">
                            <thead>
                                <tr>
                                    <th scope="col">Project Name</th>
                                    <th scope="col">Total Units</th>
                                    <th scope="col">Sold</th>
                                    <th scope="col">Available</th>
                                    <th scope="col">Avg. Price</th>
                                    <th scope="col">Progress</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Emaar Beachfront</td>
                                    <td>450</td>
                                    <td><span class="badge-sold">324</span></td>
                                    <td>126</td>
                                    <td>AED 2.8M</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" style="width:72%"></div>
                                        </div>
                                        <span class="progress-text">72%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Sobha Hartland II</td>
                                    <td>380</td>
                                    <td><span class="badge-sold">285</span></td>
                                    <td>95</td>
                                    <td>AED 1.9M</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" style="width:75%"></div>
                                        </div>
                                        <span class="progress-text">75%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Damac Lagoons</td>
                                    <td>520</td>
                                    <td><span class="badge-sold">312</span></td>
                                    <td>208</td>
                                    <td>AED 1.5M</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" style="width:60%"></div>
                                        </div>
                                        <span class="progress-text">60%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Danube Elitz</td>
                                    <td>280</td>
                                    <td><span class="badge-sold">238</span></td>
                                    <td>42</td>
                                    <td>AED 890K</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" style="width:85%"></div>
                                        </div>
                                        <span class="progress-text">85%</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Emaar Creek Beach</td>
                                    <td>320</td>
                                    <td><span class="badge-sold">192</span></td>
                                    <td>128</td>
                                    <td>AED 2.2M</td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" style="width:60%"></div>
                                        </div>
                                        <span class="progress-text">60%</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>


    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>