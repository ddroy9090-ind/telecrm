<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
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
        <h1 class="mb-4">Dashboard</h1>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>