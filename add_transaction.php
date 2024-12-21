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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2C3E50;
            --secondary: #34495E;
            --success: #27AE60;
            --danger: #C0392B;
            --gray-100: #F7FAFC;
            --gray-200: #EDF2F7;
            --gray-300: #E2E8F0;
            --gray-400: #CBD5E0;
            --gray-500: #A0AEC0;
            --gray-600: #718096;
            --gray-700: #4A5568;
            --gray-800: #2D3748;
            --gray-900: #1A202C;
        }

        body {
            background-color: var(--gray-100);
            font-family: 'Segoe UI', sans-serif;
            color: var(--gray-800);
        }

        /* Header */
        .page-header {
            background: var(--gray-800);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Card */
        .transaction-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--gray-200);
        }

        /* Form Controls */
        .form-label {
            font-weight: 500;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 2px solid var(--gray-300);
            padding: 0.75rem 1rem;
            color: var(--gray-800);
            background-color: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--gray-600);
            box-shadow: 0 0 0 3px rgba(113, 128, 150, 0.2);
        }

        .input-group-text {
            background-color: var(--gray-200);
            border: 2px solid var(--gray-300);
            border-right: none;
            color: var(--gray-700);
        }

        /* Buttons */
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary {
            background: var(--gray-800);
            border: none;
            color: white;
        }

        .btn-primary:hover {
            background: var(--gray-900);
            transform: translateY(-1px);
        }

        .btn-secondary {
            background: var(--gray-500);
            border: none;
            color: white;
        }

        .btn-secondary:hover {
            background: var(--gray-600);
            transform: translateY(-1px);
        }

        /* Alerts */
        .alert {
            border-radius: 8px;
            border: 1px solid transparent;
        }

        .alert-success {
            background-color: #F0FDF4;
            border-color: #BBF7D0;
            color: #166534;
        }

        .alert-danger {
            background-color: #FEF2F2;
            border-color: #FECACA;
            color: #991B1B;
        }

        /* Required Field */
        .required-field::after {
            content: "*";
            color: var(--danger);
            margin-left: 4px;
        }

        /* Table */
        .table {
            color: var(--gray-800);
        }

        .table thead th {
            background: var(--gray-100);
            border-bottom: 2px solid var(--gray-200);
            color: var(--gray-700);
            font-weight: 600;
        }

        .table td {
            border-bottom: 1px solid var(--gray-200);
            vertical-align: middle;
        }

        /* Action Buttons */
        .action-btn {
            padding: 0.5rem;
            border-radius: 6px;
            border: none;
            background: var(--gray-200);
            color: var(--gray-700);
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            background: var(--gray-300);
            color: var(--gray-900);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .transaction-card {
                padding: 1.5rem;
            }
            
            .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Add New Transaction</h1>
                <a href="view_transactions.php" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="transaction-card">
                    <!-- Alerts -->
                    <?php if (isset($success)): ?>
                        <div class="alert alert-success d-flex align-items-center mb-4">
                            <i class="bi bi-check-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($success); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger d-flex align-items-center mb-4">
                            <i class="bi bi-exclamation-circle-fill me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <!-- Form -->
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

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-plus-circle me-2"></i>Add Transaction
                                </button>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="view_transactions.php" class="btn btn-secondary w-100">
                                    <i class="bi bi-x-circle me-2"></i>Cancel
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
