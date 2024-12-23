<?php
// add_transaction.php
session_start(); // Ensure the session is started
require 'db.php'; // Make sure this file connects to your database

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Using prepared statements for better security
    $amount = $_POST['amount'];
    $type = $_POST['type'];
    $category_id = $_POST['category'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    if (empty($amount) || empty($type) || empty($category_id) || empty($date)) {
        $error = "Please fill in all required fields.";
    } else {
        // Prepare the SQL statement
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("idsiss", $user_id, $amount, $type, $category_id, $date, $description);
            if ($stmt->execute()) {
                $success = "Transaction added successfully.";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}

// Fetch categories
$cat_query = "SELECT * FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if (!$cat_result) {
    die("Error fetching categories: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
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

        .navbar-brand {
            font-weight: 600;
            color: white !important;
            font-size: 1.1rem;
        }

        .nav-link {
            color: rgba(255,255,255,0.9) !important;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-size: 0.9rem;
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            margin-bottom: 2rem;
        }

        .card-header {
            background: var(--primary-color);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.8rem 1rem;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .input-group-text {
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-right: none;
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
            transform: translateY(-1px);
        }

        .form-label {
            font-weight: 500;
            color: var(--dark-color);
            margin-bottom: 0.5rem;
        }

        .required-field::after {
            content: "*";
            color: var(--danger-color);
            margin-left: 4px;
        }

        .alert {
            border-radius: 8px;
            border: none;
        }

        .alert-success {
            background-color: rgba(39, 174, 96, 0.1);
            color: var(--success-color);
        }

        .alert-danger {
            background-color: rgba(192, 57, 43, 0.1);
            color: var(--danger-color);
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-plus-circle me-2"></i>Add Transaction
            </a>
            <div class="ms-auto">
                <a href="view_transactions.php" class="nav-link">
                    <i class="fas fa-arrow-left me-1"></i> Back to Transactions
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-plus-circle me-2"></i>New Transaction Details
                    </div>
                    <div class="card-body p-4">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_transaction.php">
                            <div class="mb-4">
                                <label class="form-label required-field">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control" name="amount" 
                                           placeholder="Enter amount" required>
                                </div>
                            </div>

                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <label class="form-label required-field">Type</label>
                                    <select class="form-select" name="type" required>
                                        <option value="">Select Type</option>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label required-field">Category</label>
                                    <select class="form-select" name="category" required>
                                        <option value="">Select Category</option>
                                        <?php while($row = $cat_result->fetch_assoc()): ?>
                                            <option value="<?php echo $row['id']; ?>">
                                                <?php echo htmlspecialchars($row['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label required-field">Date</label>
                                <input type="date" class="form-control" name="date" required>
                            </div>

                            <div class="mb-4">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" 
                                        placeholder="Enter description" rows="3"></textarea>
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Transaction
                                </button>
                                <a href="view_transactions.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times me-2"></i>Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
