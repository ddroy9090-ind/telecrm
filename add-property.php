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
        <h1 class="main-heading">Add Property</h1>
        <p class="subheading">Create and manage property details.</p>
        <div class="card">
            <div class="card-body">
                <p class="mb-0">This section will allow you to add new properties to the system.</p>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>
