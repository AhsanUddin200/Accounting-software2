<?php
// manage_users.php
require 'session.php';
require 'db.php';

// Check if the logged-in user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Initialize variables
$success = "";
$error = "";

// Handle Add User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $username = trim($_POST['username']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $role = $_POST['role'];

        // Basic validation
        if (empty($username) || empty($email) || empty($password) || empty($role)) {
            $error = "All fields are required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email format.";
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error = "Email already exists.";
                } else {
                    // Hash the password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                    // Insert new user
                    $stmt_insert = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("ssss", $username, $email, $hashed_password, $role);
                        if ($stmt_insert->execute()) {
                            $success = "User added successfully.";
                        } else {
                            $error = "Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        $error = "Error preparing statement: " . $conn->error;
                    }
                }
                $stmt->close();
            } else {
                $error = "Error preparing statement: " . $conn->error;
            }
        }
    }
}

// Handle Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $delete_id = intval($_POST['delete_id']);

        // Prevent admin from deleting themselves
        if ($delete_id == $_SESSION['user_id']) {
            $error = "You cannot delete your own account.";
        } else {
            // Check if user exists
            $stmt_check = $conn->prepare("SELECT id FROM users WHERE id = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("i", $delete_id);
                $stmt_check->execute();
                $stmt_check->store_result();
                if ($stmt_check->num_rows == 0) {
                    $error = "User not found.";
                } else {
                    // Proceed to delete
                    $stmt_delete = $conn->prepare("DELETE FROM users WHERE id = ?");
                    if ($stmt_delete) {
                        $stmt_delete->bind_param("i", $delete_id);
                        if ($stmt_delete->execute()) {
                            $success = "User deleted successfully.";
                        } else {
                            $error = "Error deleting user: " . $stmt_delete->error;
                        }
                        $stmt_delete->close();
                    } else {
                        $error = "Error preparing delete statement: " . $conn->error;
                    }
                }
                $stmt_check->close();
            } else {
                $error = "Error preparing check statement: " . $conn->error;
            }
        }
    }
}

// Handle Export to CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Fetch all users
        $users = [];
        $stmt_fetch = $conn->prepare("SELECT id, username, email, role FROM users ORDER BY id ASC");
        if ($stmt_fetch) {
            $stmt_fetch->execute();
            $result = $stmt_fetch->get_result();
            while ($row = $result->fetch_assoc()) {
                $users[] = $row;
            }
            $stmt_fetch->close();
        } else {
            $error = "Error preparing fetch statement: " . $conn->error;
        }

        if (empty($error)) {
            // Set headers to download file
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=users_' . date('Y-m-d') . '.csv');

            // Open the output stream
            $output = fopen('php://output', 'w');

            // Output the column headings
            fputcsv($output, ['ID', 'Username', 'Email', 'Role']);

            // Output the data
            foreach ($users as $user) {
                fputcsv($output, [$user['id'], $user['username'], $user['email'], ucfirst($user['role'])]);
            }

            fclose($output);
            exit();
        }
    }
}

// Fetch all users with Search, Filter, and Pagination
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filter_role = isset($_GET['filter_role']) ? $_GET['filter_role'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Validate sort and order
$allowed_sort = ['id', 'username', 'email', 'role'];
$allowed_order = ['ASC', 'DESC'];
if (!in_array($sort, $allowed_sort)) {
    $sort = 'id';
}
if (!in_array($order, $allowed_order)) {
    $order = 'ASC';
}

// Pagination settings
$limit = 10; // Users per page
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
if ($page < 1) { $page = 1; }
$offset = ($page - 1) * $limit;

// Build the query with search and filter
$query = "SELECT id, username, email, role FROM users WHERE 1=1";
$params = [];
$types = "";

// Search by username or email
if (!empty($search)) {
    $query .= " AND (username LIKE ? OR email LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

// Filter by role
if (!empty($filter_role)) {
    $query .= " AND role = ?";
    $params[] = $filter_role;
    $types .= "s";
}

// Get total records for pagination
$count_query = "SELECT COUNT(*) FROM users WHERE 1=1";
if (!empty($search)) {
    $count_query .= " AND (username LIKE ? OR email LIKE ?)";
}
if (!empty($filter_role)) {
    $count_query .= " AND role = ?";
}

$stmt_count = $conn->prepare($count_query);
if ($stmt_count) {
    if (!empty($search) && !empty($filter_role)) {
        $stmt_count->bind_param("sss", $search_param, $search_param, $filter_role);
    } elseif (!empty($search)) {
        $stmt_count->bind_param("ss", $search_param, $search_param);
    } elseif (!empty($filter_role)) {
        $stmt_count->bind_param("s", $filter_role);
    }
    $stmt_count->execute();
    $stmt_count->bind_result($total_records);
    $stmt_count->fetch();
    $stmt_count->close();
} else {
    die("Error preparing count statement: " . $conn->error);
}

$total_pages = ceil($total_records / $limit);

// Append sorting and pagination to the query
$query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;
$types .= "ii";

// Prepare and execute the main query
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Error preparing main query: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Users</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f8f9fa; }
        .container { margin-top: 30px; }
        .card-header { background-color: #6c757d; color: white; }
        .delete-button { color: #dc3545; }
        .delete-button:hover { color: #c82333; text-decoration: none; }
        .edit-button { color: #0d6efd; }
        .edit-button:hover { color: #0a58ca; text-decoration: none; }
        .form-control:focus, .form-select:focus {
            box-shadow: none;
            border-color: #6c757d;
        }
        .table th a {
            color: inherit;
            text-decoration: none;
        }
        .table th a:hover {
            text-decoration: underline;
        }
        .header {
            background-color: #6c757d;
            padding: 15px 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .left-section {
            font-size: 1.25rem;
            font-weight: bold;
        }
        .right-section a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            transition: opacity 0.3s;
        }
        .right-section a:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="left-section">
            <i class="fas fa-calculator me-2"></i>Financial Management System
        </div>
        <div class="right-section">
            <a href="admin_dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
            <a href="manage_users.php"><i class="fas fa-users me-1"></i>Manage Users</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt me-1"></i>Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Add User Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New User</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_users.php">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="username" name="username" placeholder="Enter username" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" placeholder="Enter email address" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="password" name="password" placeholder="Enter password" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Select role</option>
                                <option value="admin">Admin</option>
                                <option value="user">User</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="add_user" class="btn btn-primary">Add User</button>
                </form>
            </div>
        </div>

        <!-- Search and Filter -->
        <div class="row mb-3">
            <div class="col-md-6">
                <form method="GET" action="manage_users.php" class="d-flex">
                    <input type="text" name="search" class="form-control me-2" placeholder="Search by username or email" value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-secondary">Search</button>
                </form>
            </div>
            <div class="col-md-3">
                <form method="GET" action="manage_users.php">
                    <select class="form-select" name="filter_role" onchange="this.form.submit()">
                        <option value="">Filter by role</option>
                        <option value="admin" <?php echo ($filter_role == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo ($filter_role == 'user') ? 'selected' : ''; ?>>User</option>
                    </select>
                </form>
            </div>
            <div class="col-md-3 text-end">
                <form method="POST" action="manage_users.php">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" name="export_csv" class="btn btn-success">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                </form>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Users</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'id', 'order' => ($sort == 'id' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                            ID
                                            <?php if ($sort == 'id'): ?>
                                                <i class="bi bi-arrow-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>-short"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'username', 'order' => ($sort == 'username' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                            Username
                                            <?php if ($sort == 'username'): ?>
                                                <i class="bi bi-arrow-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>-short"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'email', 'order' => ($sort == 'email' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                            Email
                                            <?php if ($sort == 'email'): ?>
                                                <i class="bi bi-arrow-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>-short"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>
                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['sort' => 'role', 'order' => ($sort == 'role' && $order == 'ASC') ? 'DESC' : 'ASC'])); ?>">
                                            Role
                                            <?php if ($sort == 'role'): ?>
                                                <i class="bi bi-arrow-<?php echo ($order == 'ASC') ? 'up' : 'down'; ?>-short"></i>
                                            <?php endif; ?>
                                        </a>
                                    </th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td>
                                            <?php if ($user['role'] == 'admin'): ?>
                                                <span class="badge bg-warning text-dark">Admin</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark">User</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="edit-button" title="Edit User">
                                                    <i class="bi bi-pencil-fill"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" class="delete-button btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $user['id']; ?>" title="Delete User">
                                                        <i class="bi bi-trash-fill"></i>
                                                    </button>

                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $user['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $user['id']; ?>" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <form method="POST" action="manage_users.php">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="deleteModalLabel<?php echo $user['id']; ?>">Confirm Deletion</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        Are you sure you want to delete user <strong><?php echo htmlspecialchars($user['username']); ?></strong>?
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                        <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                        <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">Self</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="User pagination">
                            <ul class="pagination justify-content-center mt-3">
                                <li class="page-item <?php if ($page <= 1) echo 'disabled'; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php if ($i == $page) echo 'active'; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php if ($page >= $total_pages) echo 'disabled'; ?>">
                                    <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <p class="mb-0">No users found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export to CSV Confirmation Modal -->
        <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="manage_users.php">
                        <div class="modal-header">
                            <h5 class="modal-title" id="exportModalLabel">Export Users to CSV</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to export the users list to a CSV file?
                        </div>
                        <div class="modal-footer">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="export_csv" class="btn btn-success">Export</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Trigger Export Modal via Button -->
        <script>
            // Optional: Trigger the export modal when export button is clicked
            // Currently, export is directly handled on button click without confirmation
            // If you want to add confirmation, modify the export button to trigger the modal
        </script>

        <!-- Include Bootstrap JS and dependencies -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
