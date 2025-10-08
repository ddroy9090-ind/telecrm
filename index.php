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
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <i class="fas fa-users fa-2x"></i>
                    <h3>2,547</h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <i class="fas fa-shopping-bag fa-2x"></i>
                    <h3>1,234</h3>
                    <p>Orders</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <i class="fas fa-dollar-sign fa-2x"></i>
                    <h3>₹45,678</h3>
                    <p>Revenue</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <i class="fas fa-chart-line fa-2x"></i>
                    <h3>89%</h3>
                    <p>Growth</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <div class="dashboard-card">
                    <h4>Recent Activity</h4>
                    <table class="table table-hover mt-3">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Action</th>
                                <th>Time</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Rahul Kumar</td>
                                <td>New Order</td>
                                <td>2 mins ago</td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                            <tr>
                                <td>Priya Sharma</td>
                                <td>Registration</td>
                                <td>10 mins ago</td>
                                <td><span class="badge bg-info">Active</span></td>
                            </tr>
                            <tr>
                                <td>Amit Patel</td>
                                <td>Payment</td>
                                <td>25 mins ago</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                            </tr>
                            <tr>
                                <td>Sneha Gupta</td>
                                <td>Profile Update</td>
                                <td>1 hour ago</td>
                                <td><span class="badge bg-success">Completed</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <h4>Quick Actions</h4>
                    <div class="d-grid gap-2 mt-3">
                        <button class="btn btn-primary">
                            <i class="fas fa-user-plus"></i> Add New User
                        </button>
                        <button class="btn btn-success">
                            <i class="fas fa-box"></i> Create Product
                        </button>
                        <button class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> View Reports
                        </button>
                        <button class="btn btn-warning">
                            <i class="fas fa-cog"></i> Settings
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<?php include 'includes/common-footer.php'; ?>