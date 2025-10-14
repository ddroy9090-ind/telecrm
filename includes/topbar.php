<?php $displayName = $_SESSION['username'] ?? 'User'; ?>
<header class="topbar">
    <div class="topbar-left">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bx bx-menu"></i>
        </button>
        <h5 class="mb-0 ms-3">Welcome, <?php echo htmlspecialchars($displayName); ?></h5>
    </div>
    <div class="topbar-right">
        <!-- <div class="notification-icon">
            <i class="bx bx-bell"></i>
            <span class="badge-notification">3</span>
        </div> -->
        <div class="user-profile dropdown">
            <span class="profile"><i class="bx bx-user" aria-hidden="true"></i></span>
            <!-- <div class="user-avatar dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Open user menu">
                <i class="bx bx-user" aria-hidden="true"></i>
            </div> -->
            <span class="user-name"><?php echo htmlspecialchars(ucfirst($displayName)); ?></span>
            <!-- <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#profile"><i class="bx bx-user-circle"></i> Profile</a></li>
                <li><a class="dropdown-item" href="#settings"><i class="bx bx-cog"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bx bx-log-out"></i> Logout</a></li>
            </ul> -->
        </div>
    </div>
</header>