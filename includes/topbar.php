<header class="topbar">
    <div class="topbar-left">
        <button class="toggle-btn" onclick="toggleSidebar()">
            <i class="bx bx-menu"></i>
        </button>
        <h5 class="mb-0 ms-3">Welcome back, <?php echo $_SESSION['username']; ?>!</h5>
    </div>
    <div class="topbar-right">
        <div class="notification-icon">
            <i class="bx bx-bell"></i>
            <span class="badge-notification">3</span>
        </div>
        <div class="user-profile dropdown">
            <div class="user-avatar dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bx bx-user"></i>
            </div>
            <span class="user-name"><?php echo ucfirst($_SESSION['username']); ?></span>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><a class="dropdown-item" href="#profile"><i class="bx bx-user-circle"></i> Profile</a></li>
                <li><a class="dropdown-item" href="#settings"><i class="bx bx-cog"></i> Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bx bx-log-out"></i> Logout</a></li>
            </ul>
        </div>
    </div>
</header>