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
    $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : null;

    try {
        switch ($action) {
            case 'create':
                if ($fullName === '' || $email === '' || $role === '') {
                    throw new RuntimeException('All fields are required to add a user.');
                }

                $stmt = $mysqli->prepare('INSERT INTO users (full_name, email, role) VALUES (?, ?, ?)');
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare insert statement: ' . $mysqli->error);
                }

                $stmt->bind_param('sss', $fullName, $email, $role);
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

                $stmt = $mysqli->prepare('UPDATE users SET full_name = ?, email = ?, role = ? WHERE id = ?');
                if (!$stmt) {
                    throw new RuntimeException('Failed to prepare update statement: ' . $mysqli->error);
                }

                $stmt->bind_param('sssi', $fullName, $email, $role, $userId);
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
$result = $mysqli->query('SELECT id, full_name, email, role FROM users ORDER BY id ASC');
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

        <?php if (!empty($flash)): ?>
            <div class="alert alert-<?php echo htmlspecialchars($flash['type']); ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Full Name</th>
                                <th scope="col">Email</th>
                                <th scope="col">Role</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($users) === 0): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No users found. Add your first user to get started.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($users as $index => $user): ?>
                                    <tr>
                                        <th scope="row"><?php echo $index + 1; ?></th>
                                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php
                                                $roleClass = [
                                                    'admin' => 'bg-primary',
                                                    'manager' => 'bg-success',
                                                    'agent' => 'bg-info'
                                                ][$user['role']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $roleClass; ?>">
                                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary me-2" title="Edit" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                                <i class="bx bx-edit-alt"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                    <i class="bx bx-trash"></i>
                                                </button>
                                            </form>
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form method="post" action="users.php">
                    <input type="hidden" name="action" value="create">
                    <div class="mb-3">
                        <label for="fullName" class="form-label">Full Name</label>
                        <input type="text" class="form-control" id="fullName" name="full_name" placeholder="Enter full name" required>
                    </div>
                    <div class="mb-3">
                        <label for="emailAddress" class="form-label">Email address</label>
                        <input type="email" class="form-control" id="emailAddress" name="email" placeholder="Enter email" required>
                    </div>
                    <div class="mb-3">
                        <label for="userRole" class="form-label">Role</label>
                        <select id="userRole" class="form-select" name="role" data-choices required>
                            <option value="admin">Admin</option>
                            <option value="manager">Manager</option>
                            <option value="agent" selected>Agent</option>
                        </select>
                    </div>
                    <div class="modal-footer px-0 pb-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php foreach ($users as $user): ?>
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel<?php echo $user['id']; ?>">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="users.php">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="user_id" value="<?php echo (int) $user['id']; ?>">
                        <div class="mb-3">
                            <label for="editFullName<?php echo $user['id']; ?>" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editFullName<?php echo $user['id']; ?>" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="editEmailAddress<?php echo $user['id']; ?>" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="editEmailAddress<?php echo $user['id']; ?>" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="editUserRole<?php echo $user['id']; ?>" class="form-label">Role</label>
                            <select id="editUserRole<?php echo $user['id']; ?>" class="form-select" name="role" data-choices required>
                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                <option value="manager" <?php echo $user['role'] === 'manager' ? 'selected' : ''; ?>>Manager</option>
                                <option value="agent" <?php echo $user['role'] === 'agent' ? 'selected' : ''; ?>>Agent</option>
                            </select>
                        </div>
                        <div class="modal-footer px-0 pb-0">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Update User</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php include 'includes/common-footer.php'; ?>
