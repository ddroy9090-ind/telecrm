<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class='bx bx-buildings'></i>
        </div>
        <div class="sidebar-brand-details">
            <span class="sidebar-brand-title">TeleCRM</span>
            <span class="sidebar-brand-subtitle">CRM Suite</span>
        </div>
    </div>
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li>
                <a href="index.php" class="sidebar-link">
                    <span class="sidebar-icon"><i class="bx bx-home-alt"></i></span>
                    <span class="sidebar-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php" class="sidebar-link">
                    <span class="sidebar-icon"><i class="bx bx-user"></i></span>
                    <span class="sidebar-text">Users</span>
                </a>
            </li>
            <li class="sidebar-dropdown">
                <button type="button" class="sidebar-link sidebar-dropdown-toggle">
                    <span class="sidebar-icon"><i class="bx bx-group"></i></span>
                    <span class="sidebar-text">Leads</span>
                    <i class="bx bx-chevron-down sidebar-dropdown-arrow"></i>
                </button>
                <ul class="sidebar-submenu">
                    <li>
                        <a href="all-leads.php" class="sidebar-link">
                            <span class="sidebar-icon"><i class="bx bx-list-ul"></i></span>
                            <span class="sidebar-text">All Leads</span>
                        </a>
                    </li>
                    <li>
                        <a href="my-leads.php" class="sidebar-link">
                            <span class="sidebar-icon"><i class="bx bx-user-circle"></i></span>
                            <span class="sidebar-text">My Leads</span>
                        </a>
                    </li>
                    <li>
                        <a href="add-leads.php" class="sidebar-link">
                            <span class="sidebar-icon"><i class="bx bx-user-plus"></i></span>
                            <span class="sidebar-text">Add Leads</span>
                        </a>
                    </li>
                </ul>
            </li>
            <li class="sidebar-logout mt-auto">
                <a href="logout.php" class="sidebar-link" onclick="return confirm('Are you sure you want to logout?')">
                    <span class="sidebar-icon"><i class="bx bx-log-out"></i></span>
                    <span class="sidebar-text">Logout</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>
