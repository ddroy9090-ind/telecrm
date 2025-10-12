<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: login.php');
    exit;
}

?>
<?php include 'includes/common-header.php'; ?>

<div id="adminPanel">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <h1 class="main-heading">Property Listing</h1>
        <p class="subheading">Review and manage all properties in your portfolio.</p>
        <div class="card">
            <div class="card-body">
                <p class="mb-0">Property data will appear here once the listing module is implemented.</p>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>
