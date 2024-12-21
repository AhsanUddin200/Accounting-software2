<?php
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

        // Basic validation
        if (empty($name)) {
            $error = "Category name is required.";
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
                    $stmt_insert = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
                    if ($stmt_insert) {
                        $stmt_insert->bind_param("s", $name);
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
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Include Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
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
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calculator me-2"></i>Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                    data-bs-target="#navbarNav" aria-controls="navbarNav" 
                    aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i>Manage Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i>Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

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

        <!-- Add Category Card -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Category</h5>
            </div>
            <div class="card-body">
                <form method="POST" action="manage_categories.php">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                    <div class="mb-3">
                        <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="Enter category name" required>
                    </div>

                    <button type="submit" name="add_category" class="btn btn-primary">Add Category</button>
                </form>
            </div>
        </div>

        <!-- Export Categories Button -->
        <div class="mb-3">
            <form method="POST" action="manage_categories.php" class="d-inline">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <button type="submit" name="export_csv" class="btn btn-success">
                    <i class="bi bi-download"></i> Export CSV
                </button>
            </form>
        </div>

        <!-- Categories Table Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Categories List</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($categories)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
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
                                        <td>
                                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="edit-button me-2" title="Edit Category">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button" class="delete-button btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $category['id']; ?>" title="Delete Category">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>

                                            <!-- Delete Confirmation Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $category['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $category['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="manage_categories.php">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $category['id']; ?>">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete the category "<strong><?php echo htmlspecialchars($category['name']); ?></strong>"?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <input type="hidden" name="category_id" value="<?php echo $category['id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_category" class="btn btn-danger">Delete</button>
                                                            </div>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
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

        <!-- Back Button -->
        <div class="back-button mt-4 text-center">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
