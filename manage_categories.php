<?php
// manage_categories.php
require 'session.php';
require 'db.php';

// Check if the logged-in user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

// Initialize variables
$success = "";
$error = "";

// Handle Add Category
if (isset($_POST['add_category'])) {
    $name = trim($_POST['name']);

    // Basic validation
    if (empty($name)) {
        $error = "Category name is required.";
    } else {
        // Check if category already exists
        $stmt = $conn->prepare("SELECT id FROM categories WHERE name = ?");
        $stmt->bind_param("s", $name);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $error = "Category already exists.";
        } else {
            // Insert new category
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param("s", $name);
            if ($stmt->execute()) {
                $success = "Category added successfully.";
            } else {
                $error = "Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Handle Delete Category
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);

    // Check if any transactions are linked to this category
    $stmt = $conn->prepare("SELECT id FROM transactions WHERE category_id = ?");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $error = "Cannot delete category. Transactions are linked to this category.";
    } else {
        $stmt = $conn->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $success = "Category deleted successfully.";
        } else {
            $error = "Error deleting category: " . $stmt->error;
        }
    }
    $stmt->close();
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
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 90%; margin: 20px auto; padding: 20px; background: #fff; border-radius: 5px; }
        h2 { text-align: center; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        table, th, td { border: 1px solid #ccc; }
        th, td { padding: 10px; text-align: left; }
        th { background-color: #f9f9f9; }
        form { margin-bottom: 30px; }
        input { width: 100%; padding: 8px; margin: 5px 0 10px; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #5cb85c; border: none; color: #fff; cursor: pointer; }
        input[type="submit"]:hover { background: #4cae4c; }
        .delete-button { color: #a94442; text-decoration: none; }
        .delete-button:hover { text-decoration: underline; }
        .back-button { text-align: center; margin-top: 20px; }
        .back-button a { 
            padding: 10px 20px; 
            background-color: #5bc0de; 
            color: #fff; 
            text-decoration: none; 
            border-radius: 4px; 
        }
        .back-button a:hover { background-color: #31b0d5; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Categories</h2>

        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (!empty($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Add Category Form -->
        <h3>Add New Category</h3>
        <form method="POST" action="manage_categories.php">
            <label for="name">Category Name<span style="color: red;">*</span></label>
            <input type="text" id="name" name="name" placeholder="Category Name" required>

            <input type="submit" name="add_category" value="Add Category">
        </form>

        <!-- Categories Table -->
        <h3>All Categories</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Actions</th>
            </tr>
            <?php if (count($categories) > 0): ?>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($category['id']); ?></td>
                        <td><?php echo htmlspecialchars($category['name']); ?></td>
                        <td>
                            <a href="edit_category.php?id=<?php echo $category['id']; ?>" class="edit-button">Edit</a> | 
                            <a href="manage_categories.php?delete=<?php echo $category['id']; ?>" class="delete-button" onclick="return confirm('Are you sure you want to delete this category?');">Delete</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="3">No categories found.</td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Back Button -->
        <div class="back-button">
            <a href="admin_dashboard.php">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
