<?php
// view_transactions.php
require 'session.php';
require 'db.php';

// Initialize variables
$transactions = [];
$categories = [];
$success = "";
$error = "";

// Fetch categories for filter
$cat_query = "SELECT id, name FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if ($cat_result) {
    while ($row = $cat_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("Error fetching categories: " . $conn->error);
}

// Handle Filters
$where_clauses = [];
$params = [];
$types = "";

// Always filter by user_id
$where_clauses[] = "transactions.user_id = ?";
$params[] = $_SESSION['user_id'];
$types .= "i";

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Filter by type
    if (!empty($_GET['type'])) {
        $where_clauses[] = "transactions.type = ?";
        $params[] = $_GET['type'];
        $types .= "s";
    }

    // Filter by category
    if (!empty($_GET['category'])) {
        $where_clauses[] = "transactions.category_id = ?";
        $params[] = $_GET['category'];
        $types .= "i";
    }

    // Filter by date range
    if (!empty($_GET['start_date']) && !empty($_GET['end_date'])) {
        $where_clauses[] = "transactions.date BETWEEN ? AND ?";
        $params[] = $_GET['start_date'];
        $params[] = $_GET['end_date'];
        $types .= "ss";
    }
}

// Build the query
$query = "SELECT transactions.id, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description
          FROM transactions
          JOIN categories ON transactions.category_id = categories.id
          WHERE " . implode(" AND ", $where_clauses) . "
          ORDER BY transactions.date DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    $stmt->close();
} else {
    die("Error preparing statement: " . $conn->error);
}

// Handle Deletion (Optional)
if (isset($_GET['delete'])) {
    $delete_id = intval($_GET['delete']);
    // Ensure the transaction belongs to the user
    $delete_stmt = $conn->prepare("DELETE FROM transactions WHERE id = ? AND user_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $delete_id, $_SESSION['user_id']);
        if ($delete_stmt->execute()) {
            if ($delete_stmt->affected_rows > 0) {
                $success = "Transaction deleted successfully.";
            } else {
                $error = "No such transaction found.";
            }
        } else {
            $error = "Error deleting transaction: " . $delete_stmt->error;
        }
        $delete_stmt->close();
    } else {
        $error = "Prepare failed for deletion: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>View Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #2C3E50;
            --success: #2ECC71;
            --danger: #E74C3C;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', sans-serif;
        }

        .page-header {
            background: var(--primary);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .filter-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            padding: 1.5rem;
        }

        .transactions-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.1);
            padding: 1.5rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            padding: 0.6rem 1rem;
            border: 1px solid #e0e0e0;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(44, 62, 80, 0.25);
        }

        .btn-filter {
            background: var(--primary);
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            border: none;
        }

        .btn-filter:hover {
            background: #34495E;
            color: white;
        }

        .btn-reset {
            background: #95a5a6;
            color: white;
            padding: 0.6rem 1.5rem;
            border-radius: 8px;
            border: none;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e0e0e0;
            color: var(--primary);
            font-weight: 600;
        }

        .table td {
            vertical-align: middle;
        }

        .badge-income {
            background-color: var(--success);
            color: white;
            padding: 0.5em 1em;
            border-radius: 6px;
        }

        .badge-expense {
            background-color: var(--danger);
            color: white;
            padding: 0.5em 1em;
            border-radius: 6px;
        }

        .amount-income {
            color: var(--success);
            font-weight: 600;
        }

        .amount-expense {
            color: var(--danger);
            font-weight: 600;
        }

        .action-btn {
            padding: 0.4rem 0.8rem;
            border-radius: 6px;
            color: white;
            text-decoration: none;
            margin-right: 0.5rem;
        }

        .btn-edit {
            background: #3498db;
        }

        .btn-delete {
            background: var(--danger);
        }

        .action-btn:hover {
            opacity: 0.9;
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1 class="h3 mb-0">Transaction History</h1>
                <a href="add_transaction.php" class="btn btn-light">
                    <i class="bi bi-plus-lg"></i> New Transaction
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Display Success or Error Messages -->
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Filter Section -->
        <div class="filter-card">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Transaction Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <option value="income" <?php echo (isset($_GET['type']) && $_GET['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                        <option value="expense" <?php echo (isset($_GET['type']) && $_GET['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control" 
                           value="<?php echo isset($_GET['start_date']) ? htmlspecialchars($_GET['start_date']) : ''; ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label">End Date</label>
                    <input type="date" name="end_date" class="form-control" 
                           value="<?php echo isset($_GET['end_date']) ? htmlspecialchars($_GET['end_date']) : ''; ?>">
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <div class="d-grid gap-2 w-100">
                        <button type="submit" class="btn btn-filter">
                            <i class="bi bi-funnel"></i> Filter
                        </button>
                        <a href="view_transactions.php" class="btn btn-reset">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Transactions Table -->
        <div class="transactions-card">
            <?php if (count($transactions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th>Category</th>
                                <th>Amount</th>
                                <th>Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($transaction['date'])); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                    <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                    <td class="amount-<?php echo $transaction['type']; ?>">
                                        <?php echo ($transaction['type'] == 'income' ? '+' : '-'); ?>
                                        $<?php echo number_format($transaction['amount'], 2); ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo ($transaction['type'] == 'income') ? 'badge-income' : 'badge-expense'; ?>">
                                            <?php echo ucfirst($transaction['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" 
                                           class="action-btn btn-edit" title="Edit Transaction">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <a href="view_transactions.php?delete=<?php echo $transaction['id']; ?>" 
                                           class="action-btn btn-delete"
                                           onclick="return confirm('Are you sure you want to delete this transaction?');"
                                           title="Delete Transaction">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="bi bi-inbox"></i>
                    <h4>No Transactions Found</h4>
                    <p>Try adjusting your filters or add a new transaction.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Back Button -->
        <div class="mt-4 text-center">
            <a href="user_dashboard.php" class="btn btn-outline-primary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
