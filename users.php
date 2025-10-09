<?php
session_start();

// Check if user is logged in
if(!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header("Location: login.php");
    exit;
}

require_once __DIR__ . '/includes/config.php';

// Handle create, update, and delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $contactNumber = trim($_POST['contact_number'] ?? '');
    if ($contactNumber !== '') {
        $contactNumber = preg_replace('/[^0-9+()\-\s]/', '', $contactNumber);
    }
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : null;

    try {
        switch ($action) {
            case 'create':
                if ($fullName === '' || $email === '' || $role === '') {
                    throw new RuntimeException('All fields are required to add a user.');
                }

                if ($password === '' || $confirmPassword === '') {
                    throw new RuntimeException('Password and confirmation are required to add a user.');
                }

                if ($password !== $confirmPassword) {
                    throw new RuntimeException('Password and confirm password must match.');
                }

                $passwordHash = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, contact_number, password_hash, role) VALUES (?, ?, ?, ?, ?)');
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare insert statement: ' . $mysqli->error);
                }

                $stmt->bind_param('sssss', $fullName, $email, $contactNumber, $passwordHash, $role);
                if (!$stmt->execute()) {
                    if ($mysqli->errno === 1062) {
                        throw new RuntimeException('A user with this email already exists.');
                    }
                    throw new RuntimeException('Failed to save user: ' . $stmt->error);
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'User added successfully.'];
                break;

            case 'update':
                if (!$userId) {
                    throw new RuntimeException('Invalid user selected for update.');
                }
                if ($fullName === '' || $email === '' || $role === '') {
                    throw new RuntimeException('All fields are required to update a user.');
                }

                $shouldUpdatePassword = $password !== '' || $confirmPassword !== '';
                if ($shouldUpdatePassword) {
                    if ($password === '' || $confirmPassword === '') {
                        throw new RuntimeException('Both password fields are required to update the password.');
                    }

                    if ($password !== $confirmPassword) {
                        throw new RuntimeException('Password and confirm password must match.');
                    }
                }

                if ($shouldUpdatePassword) {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $updateSql = 'UPDATE users SET full_name = ?, email = ?, contact_number = ?, role = ?, password_hash = ? WHERE id = ?';
                    $stmt = $mysqli->prepare($updateSql);
                } else {
                    $updateSql = 'UPDATE users SET full_name = ?, email = ?, contact_number = ?, role = ? WHERE id = ?';
                    $stmt = $mysqli->prepare($updateSql);
                }
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare update statement: ' . $mysqli->error);
                }

                if ($shouldUpdatePassword) {
                    $stmt->bind_param('sssssi', $fullName, $email, $contactNumber, $role, $passwordHash, $userId);
                } else {
                    $stmt->bind_param('ssssi', $fullName, $email, $contactNumber, $role, $userId);
                }
                if (!$stmt->execute()) {
                    if ($mysqli->errno === 1062) {
                        throw new RuntimeException('A user with this email already exists.');
                    }
                    throw new RuntimeException('Failed to update user: ' . $stmt->error);
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'User updated successfully.'];
                break;

            case 'delete':
                if (!$userId) {
                    throw new RuntimeException('Invalid user selected for deletion.');
                }

                $stmt = $mysqli->prepare('DELETE FROM users WHERE id = ?');
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare delete statement: ' . $mysqli->error);
                }

                $stmt->bind_param('i', $userId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Failed to delete user: ' . $stmt->error);
                }

                $_SESSION['flash'] = ['type' => 'success', 'message' => 'User deleted successfully.'];
                break;

            default:
                throw new RuntimeException('Unsupported action requested.');
        }
    } catch (RuntimeException $e) {
        $_SESSION['flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
    }

    header('Location: users.php');
    exit;
}

$users = [];
$result = $mysqli->query('SELECT id, full_name, email, contact_number, role FROM users ORDER BY id ASC');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
    $result->free();
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<?php include 'includes/common-header.php'; ?>

<div id="adminPanel">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="mb-0">Users</h1>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bx bx-user-plus me-1"></i> Add User
            </button>
        </div>

        <div class="row g-3 user-stats">
            <div class="col-12 col-md-6 col-xl-4">
                <div class="stats-card">
                    <div class="stats-card-icon paid">
                        <i class="bx bx-credit-card"></i>
                    </div>
                    <div class="stats-card-body">
                        <span class="stats-label">Paid Users</span>
                        <h2 class="mb-0">4,567</h2>
                        <span class="stats-subtitle">Last week analytics</span>
                    </div>
                    <div class="stats-card-footer">
                        <span class="trend trend-up">+6.8%</span>
                        <span class="comparison">vs last week</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="stats-card">
                    <div class="stats-card-icon active">
                        <i class="bx bx-user-check"></i>
                    </div>
                    <div class="stats-card-body">
                        <span class="stats-label">Active Users</span>
                        <h2 class="mb-0">19,680</h2>
                        <span class="stats-subtitle">Last week analytics</span>
                    </div>
                    <div class="stats-card-footer">
                        <span class="trend trend-down">-1.4%</span>
                        <span class="comparison">vs last week</span>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="stats-card">
                    <div class="stats-card-icon pending">
                        <i class="bx bx-time-five"></i>
                    </div>
                    <div class="stats-card-body">
                        <span class="stats-label">Pending Users</span>
                        <h2 class="mb-0">237</h2>
                        <span class="stats-subtitle">Last week analytics</span>
                    </div>
                    <div class="stats-card-footer">
                        <span class="trend trend-up">+0.2%</span>
                        <span class="comparison">vs last week</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm users-card">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table users-table align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Full Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col">Contact Number</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No users found. Add your first user to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php
                                                $name = trim($user['full_name']);
                                                $initial = '?';
                                                if ($name !== '') {
                                                    if (function_exists('mb_substr')) {
                                                        $initial = mb_substr($name, 0, 1, 'UTF-8');
                                                    } else {
                                                        $initial = substr($name, 0, 1);
                                                    }
                                                }
                                                $initial = strtoupper($initial ?: '?');
                                            ?>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="user-avatar">
                                                    <span><?php echo htmlspecialchars($initial); ?></span>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-decoration-none"><?php echo htmlspecialchars($user['email']); ?></a>
                                        </td>
                                        <td>
                                            <?php
                                                $roleClass = [
                                                    'admin' => 'badge-role-admin',
                                                    'manager' => 'badge-role-manager',
                                                    'agent' => 'badge-role-agent'
                                                ][$user['role']] ?? 'badge-role-default';
                                            ?>
                                            <span class="badge <?php echo $roleClass; ?>">
                                                <?php echo htmlspecialchars(ucfirst($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($user['contact_number'])): ?>
                                                <span class=""><?php echo htmlspecialchars($user['contact_number']); ?></span>
                                            <?php else: ?>
                                                <span class="">Not provided</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex justify-content-end gap-2">
                                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Edit" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                    <i class="bx bx-edit-alt"></i>
                                                </button>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                        <i class="bx bx-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered minimal-modal-dialog">
        <div class="modal-content minimal-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body pt-2 p-0">
                <div class="container-fluid">
                    <form method="post" action="users.php" class="minimal-form">
                        <input type="hidden" name="action" value="create">

                        <div class="row">
                            <div class="col-lg-12 col-md-6 mb-3">
                                <label for="fullName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="fullName" name="full_name" required>
                            </div>

                            <div class="col-lg-12 col-md-6 mb-3">
                                <label for="emailAddress" class="form-label">Email</label>
                                <input type="email" class="form-control" id="emailAddress" name="email"  required>
                            </div>

                            <div class="col-lg-12 col-md-6 mb-3">
                                <label for="contactNumber" class="form-label">Contact Number</label>
                                <input type="tel" class="form-control" id="contactNumber" name="contact_number"  inputmode="tel">
                            </div>

                            <div class="col-lg-6 col-md-6 mb-3">
                                <label for="addPassword" class="form-label">Password</label>
                                <input type="password" class="form-control" id="addPassword" name="password"  required autocomplete="new-password">
                            </div>

                            <div class="col-lg-6 col-md-6 mb-3">
                                <label for="addConfirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="addConfirmPassword" name="confirm_password"  required autocomplete="new-password">
                            </div>

                            <div class="col-lg-12 col-md-6 mb-3">
                                <label for="userRole" class="form-label">Role</label>
                                <select id="userRole" class="select-dropDownClass" name="role" data-choices required>
                                    <option value="admin">Admin</option>
                                    <option value="manager">Manager</option>
                                    <option value="agent" selected>Agent</option>
                                </select>
                            </div>
                        </div>

                        <div class="modal-footer px-0 border-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary shadow-sm">Save User</button>
                        </div>
                    </form>
                </div> <!-- /.container -->
            </div>
        </div>
    </div>
</div>


<?php foreach ($users as $user): ?>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered minimal-modal-dialog">
            <div class="modal-content minimal-modal">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-2">
                    <form method="post" action="users.php" class="minimal-form">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                        <div class="form-group mb-4">
                            <label for="editFullName<?php echo $user['id']; ?>" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editFullName<?php echo $user['id']; ?>" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" placeholder="Enter full name" required>
                        </div>
                        <div class="form-group mb-4">
                            <label for="editEmailAddress<?php echo $user['id']; ?>" class="form-label">Email</label>
                            <input type="email" class="form-control" id="editEmailAddress<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Enter email address" required>
                        </div>
                        <div class="form-group mb-4">
                            <label for="editContactNumber<?php echo $user['id']; ?>" class="form-label">Contact Number</label>
                            <input type="tel" class="form-control" id="editContactNumber<?php echo $user['id']; ?>" name="contact_number" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="e.g. +1 555 0123" inputmode="tel">
                        </div>
                        <div class="form-group mb-4">
                            <label for="editPassword<?php echo $user['id']; ?>" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="editPassword<?php echo $user['id']; ?>" name="password" placeholder="Leave blank to keep current password" autocomplete="new-password">
                            <div class="form-text mt-2">Leave blank to keep the existing password.</div>
                        </div>
                        <div class="form-group mb-4">
                            <label for="editConfirmPassword<?php echo $user['id']; ?>" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="editConfirmPassword<?php echo $user['id']; ?>" name="confirm_password" placeholder="Re-enter new password" autocomplete="new-password">
                        </div>
                        <div class="form-group mb-4">
                            <label for="editUserRole<?php echo $user['id']; ?>" class="form-label">Role</label>
                            <select id="editUserRole<?php echo $user['id']; ?>" class="form-select" name="role" data-choices required>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="agent" <?php echo $user['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                            </select>
                        </div>
                        <div class="modal-footer px-0 pb-0 border-0">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary shadow-sm">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>



<?php include 'includes/common-footer.php'; ?>
