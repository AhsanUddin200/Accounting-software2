\<?php
// manage_categories.php
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

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $name = trim($_POST['name']);
        $main_head = intval($_POST['main_head']);

        // Basic validation
        if (empty($name) || empty($main_head)) {
            $error = "Category name and main head are required.";
        } else {
            // Check if category already exists
            $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
            if ($stmt) {
                $stmt->bind_param("s", $name);
                $stmt->execute();
                $stmt->store_result();
                if ($stmt->num_rows > 0) {
                    $error = "Category already exists.";
                } else {
                    // Insert new category
                    $stmt_insert = $conn->prepare("INSERT INTO categories (name, main_head_id) VALUES (?, ?)");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("si", $name, $main_head);
                        if ($stmt_insert->execute()) {
                            $success = "Category added successfully.";
                        } else {
                            $error = "Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    } else {
                        $error = "Error preparing insert statement: " . $conn->error;
                    }
                }
                $stmt->close();
            } else {
                $error = "Error preparing select statement: " . $conn->error;
            }
        }
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $delete_id = intval($_POST['category_id']);

        // Check if any transactions are linked to this category
        $stmt_check = $conn->prepare("SELECT id FROM transactions WHERE category_id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $delete_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows > 0) {
                $error = "Cannot delete category. Transactions are linked to this category.";
            } else {
                // Proceed to delete
                $stmt_delete = $conn->prepare("DELETE FROM categories WHERE id = ?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("i", $delete_id);
                    if ($stmt_delete->execute()) {
                        $success = "Category deleted successfully.";
                    } else {
                        $error = "Error deleting category: " . $stmt_delete->error;
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

// Handle Export to CSV
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export_csv'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        // Fetch all categories
        $categories = [];
        $result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $categories[] = $row;
            }
        } else {
            $error = "Error fetching categories: " . $conn->error;
        }

        if (empty($error)) {
            // Set headers to download file
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=categories_' . date('Y-m-d') . '.csv');

            // Open the output stream
            $output = fopen('php://output', 'w');

            // Output the column headings
            fputcsv($output, ['ID', 'Name']);

            // Output the data
            foreach ($categories as $category) {
                fputcsv($output, [$category['id'], $category['name']]);
            }

            fclose($output);
            exit();
        }
    }
}

// Fetch all categories
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("Error fetching categories: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Categories</title>
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
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 600;
            color: white !important;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.8rem 1.2rem;
            border-radius: 8px;
            margin: 0 0.2rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white !important;
            transform: translateY(-1px);
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
            border: none;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
            padding: 1rem;
        }

        .table tbody td {
            padding: 1rem;
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(67, 97, 238, 0.05);
        }

        .btn-action {
            padding: 0.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        .form-control {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .action-buttons .btn {
            padding: 0.5rem;
            border-radius: 8px;
        }

        .action-buttons .btn-danger {
            background: var(--danger-color);
            border: none;
        }

        .action-buttons .btn-primary {
            padding: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-tags me-2"></i>Manage Categories
            </a>
            <div class="ms-auto">
                <a href="admin_dashboard.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Category Card -->
        <div class="card mb-4">
            <div class="card-header">
                <i class="fas fa-plus me-2"></i>Add New Category
            </div>
            <div class="card-body">
                <form method="POST" action="manage_categories.php">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    <button type="submit" name="add_category" class="btn btn-primary">
                        <i class="fas fa-plus me-2"></i>Add Category
                    </button>
                </form>
            </div>
        </div>

        <!-- Categories List Card -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-list me-2"></i>Categories List</span>
                <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" name="export_csv" class="btn btn-light btn-sm">
                        <i class="fas fa-download me-1"></i>Export CSV
                </button>
            </form>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($categories)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                                        <td class="action-buttons">
                                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" 
                                               class="btn btn-primary btn-action me-2">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="csrf_token" 
                                                       value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                <input type="hidden" name="category_id" 
                                                       value="<?php echo $category['id']; ?>">
                                                <button type="submit" name="delete_category" 
                                                        class="btn btn-danger btn-action"
                                                        onclick="return confirm('Are you sure you want to delete this category?');">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-4 text-center">
                        <p class="mb-0">No categories found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
