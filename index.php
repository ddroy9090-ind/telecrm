<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

include __DIR__ . '/includes/common-header.php';
include __DIR__ . '/includes/config.php';
?>


<div id="adminPanel">
    <?php include __DIR__ . '/includes/sidebar.php'; ?>
    <?php include __DIR__ . '/includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <h1 class="main-heading">Dashboard</h1>
        <p class="subheading">Manage and track all your real estate leads</p>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>