<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Fetch accounting heads
$heads_query = "SELECT * FROM accounting_heads ORDER BY name";
$heads = $conn->query($heads_query);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $category_id = $_POST['category_id'] ?? '';
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        if (empty($category_id) || empty($name)) {
            throw new Exception("Category and name are required fields");
        }
        
        $stmt = $conn->prepare("INSERT INTO account_subcategories (category_id, name, description) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $category_id, $name, $description);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Sub-category added successfully";
            header("Location: manage_subcategories.php");
            exit();
        } else {
            throw new Exception("Error adding sub-category");
        }
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Sub Categories</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h4>Manage Sub Categories</h4>
            </div>
            <div class="card-body">
                <!-- Add Sub Category Form -->
                <form method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Accounting Head</label>
                                <select id="head_id" class="form-select" required>
                                    <option value="">Select Accounting Head</option>
                                    <?php while ($head = $heads->fetch_assoc()): ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo htmlspecialchars($head['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Category</label>
                                <select name="category_id" id="category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Sub Category Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group mb-3">
                                <label>Description</label>
                                <input type="text" name="description" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Add Sub Category</button>
                        </div>
                    </div>
                </form>

                <!-- List of existing subcategories -->
                <div id="subcategories-list" class="mt-4">
                    <!-- Will be populated via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // When accounting head is selected
        $('#head_id').change(function() {
            const headId = $(this).val();
            if (headId) {
                $.ajax({
                    url: 'get_categories.php',
                    type: 'GET',
                    data: { head_id: headId },
                    success: function(response) {
                        // Clear existing options first
                        $('#category_id').empty();
                        // Add default option
                        $('#category_id').html('<option value="">Select Category</option>' + response);
                    }
                });
            } else {
                // If no head selected, show default message
                $('#category_id').html('<option value="">Select Head First</option>');
            }
        });

        // When category is selected
        $('#category_id').change(function() {
            const categoryId = $(this).val();
            console.log('Selected category ID:', categoryId);
            
            if (categoryId) {
                $.ajax({
                    url: 'get_subcategories_list.php',
                    type: 'GET',
                    data: { category_id: categoryId },
                    success: function(response) {
                        console.log('Subcategories response:', response);
                        $('#subcategories-list').html(response);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                    }
                });
            }
        });
    });
    </script>
</body>
</html>