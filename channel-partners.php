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
                <div class="col-12">
                    <h1 class="main-heading">All Channel Partners</h1>
                    <p class="subheading">Overview of your channel partner network</p>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include __DIR__ . '/includes/common-footer.php'; ?>
</body>
</html>
