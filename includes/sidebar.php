<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><i class="bx bx-crown"></i> <span class="sidebar-text">Admin Panel</span></h3>
    </div>
    <ul class="sidebar-menu">
        <li class="active">
            <a href="index.php" class="sidebar-link">
                <i class="bx bx-home-alt"></i>
                <span class="sidebar-text">Dashboard</span>
            </a>
        </li>
        <li class="sidebar-dropdown">
            <button type="button" class="sidebar-link sidebar-dropdown-toggle">
                <i class="bx bx-group"></i>
                <span class="sidebar-text">Leads</span>
                <i class="bx bx-chevron-down sidebar-dropdown-arrow"></i>
            </button>
            <ul class="sidebar-submenu">
                <li>
                    <a href="all-leads.php" class="sidebar-link">
                        <i class="bx bx-list-ul"></i>
                        <span class="sidebar-text">All Leads</span>
                    </a>
                </li>
                <li>
                    <a href="my-leads.php" class="sidebar-link">
                        <i class="bx bx-user-circle"></i>
                        <span class="sidebar-text">My Leads</span>
                    </a>
                </li>
                <li>
                    <a href="add-leads.php" class="sidebar-link">
                        <i class="bx bx-user-plus"></i>
                        <span class="sidebar-text">Add Leads</span>
                    </a>
                </li>
            </ul>
        </li>
        <li class="mt-auto">
            <a href="logout.php" class="sidebar-link" onclick="return confirm('Are you sure you want to logout?')">
                <i class="bx bx-log-out"></i>
                <span class="sidebar-text">Logout</span>
            </a>
        </li>
    </ul>
</aside>