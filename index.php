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
                <h1 class="main-heading">Dashboard</h1>
                <p class="subheading">Manage and track all your real estate leads</p>
            </div>
        </div>
        <div class="container-fluid">
            <div class="row g-3 lead-stats">
                <div class="col-md-3">
                    <div class="stat-card total-leads">
                        <h6>Total Properties</h6>
                        <h2>2</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card active-leads">
                        <h6>Total Leads</h6>
                        <h2>6</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card closed-leads">
                        <h6>Total Users</h6>
                        <h2>0</h2>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card lost-leads">
                        <h6>Active Leads</h6>
                        <h2>0</h2>
                    </div>
                </div>
            </div>
            <div class="row mt-5">
                <div class="col-lg-6">
                    <div class="chart-section">
                        <div id="barChart"></div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="chart-section">
                        <div id="chart"></div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>