<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session.php';
require_once 'db.php';

// Check if user is logged in and has proper access
if (!isset($_SESSION['username']) || 
    ($_SESSION['username'] !== 'saim' && $_SESSION['username'] !== 'admin')) {
    header("Location: unauthorized.php");
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
    <style>
        body {
            background-color: #f5f5f5;
        }
        .card {
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            border: none;
            border-radius: 10px;
            margin-top: 20px;
        }
        .card-header {
            background: linear-gradient(to right, #4a90e2, #357abd);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .card-header h4 {
            margin: 0;
            font-weight: 600;
        }
        .card-body {
            padding: 25px;
        }
        .form-label {
            font-weight: 500;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        .form-select, .form-control {
            border-radius: 7px;
            border: 1px solid #ddd;
            padding: 10px 15px;
            height: 45px;
        }
        .form-select:focus, .form-control:focus {
            border-color: #4a90e2;
            box-shadow: 0 0 0 0.2rem rgba(74, 144, 226, 0.25);
        }
        .btn-primary {
            background: linear-gradient(to right, #4a90e2, #357abd);
            border: none;
            padding: 12px 25px;
            border-radius: 7px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 15px;
        }
        .btn-primary:hover {
            background: linear-gradient(to right, #357abd, #2c6aa0);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .table {
            margin-top: 25px;
            background: white;
            border-radius: 10px;
            overflow: hidden;
        }
        .table thead th {
            background-color: #f8f9fa;
            color: #2c3e50;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 15px;
            border-bottom: 2px solid #eee;
        }
        .table td {
            padding: 15px;
            vertical-align: middle;
            color: #2c3e50;
        }
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        .btn-danger {
            border-radius: 5px;
            padding: 8px 15px;
            font-size: 0.9rem;
            background-color: #dc3545;
            border: none;
        }
        .btn-danger:hover {
            background-color: #c82333;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(220, 53, 69, 0.2);
        }
        .alert {
            border-radius: 7px;
            margin: 15px 0;
            padding: 15px 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success">
                <?php 
                echo htmlspecialchars($_SESSION['success']); 
                unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-layer-group me-2"></i>Manage Sub Categories</h4>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Accounting Head</label>
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
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Sub Category Name</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <input type="text" name="description" class="form-control">
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i>Add Sub Category
                            </button>
                        </div>
                    </div>
                </form>

                <div id="subcategories-list" class="mt-4">
                    <!-- Will be populated via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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