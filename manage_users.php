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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #27AE60;
            --danger-color: #C0392B;
            --warning-color: #f72585;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }

        body {
            background-color: #f5f6fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 0.5rem 0;
            min-height: 60px;
        }

        .filter-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            align-items: end;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
        }

        .table {
            margin-bottom: 0;
        }

        .table th {
            border-top: none;
            background-color: #f8f9fa;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
        }

        .btn-action {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .badge {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
        }

        .badge-admin {
            background-color: var(--warning-color);
            color: white;
        }

        .badge-user {
            background-color: var(--info-color);
            color: white;
        }

        .btn-add-user {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-add-user:hover {
            background: #219a52;
            transform: translateY(-1px);
        }

        .search-box {
            position: relative;
        }

        .search-box .form-control {
            padding-left: 2.5rem;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
        }

        .pagination {
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        .page-link {
            color: var(--primary-color);
            border: none;
            padding: 0.5rem 1rem;
            margin: 0 0.2rem;
            border-radius: 6px;
        }

        .page-item.active .page-link {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-users me-2"></i>Manage Users
            </a>
            <div class="ms-auto">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-header">
                <div class="filter-title">
                    <i class="fas fa-filter me-2"></i>Filter Users
                </div>
                <button type="button" class="btn btn-add-user" data-bs-toggle="modal" data-bs-target="#addUserModal">
                    <i class="fas fa-plus me-2"></i>Add New User
                </button>
            </div>
            
            <form method="GET" class="filter-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" class="form-control" placeholder="Search users..." 
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>

                <div>
                    <select name="filter_role" class="form-select">
                        <option value="">All Roles</option>
                        <option value="admin" <?php echo (isset($_GET['filter_role']) && $_GET['filter_role'] == 'admin') ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo (isset($_GET['filter_role']) && $_GET['filter_role'] == 'user') ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                    <a href="manage_users.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-1"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-users me-2"></i>Users List</span>
                <button type="button" class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#exportModal">
                    <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($user['id']); ?></td>
                                        <td>
                                            <i class="fas fa-user me-2"></i>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </td>
                                        <td>
                                            <i class="fas fa-envelope me-2"></i>
                                            <?php echo htmlspecialchars($user['email']); ?>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $user['role'] == 'admin' ? 'badge-admin' : 'badge-user'; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group">
                                                <a href="edit_user.php?id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-primary btn-action me-2">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                    <button type="button" class="btn btn-danger btn-action" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $user['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
                        <nav aria-label="Page navigation" class="p-3">
                            <ul class="pagination justify-content-center mb-0">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>&<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center p-4">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No users found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Include your modals here (Add User, Delete User, Export) -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
