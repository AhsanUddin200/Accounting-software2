<?php
// manage_transactions.php
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

// Handle Delete Transaction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_transaction'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = "Invalid CSRF token.";
    } else {
        $delete_id = intval($_POST['transaction_id']);

        // Check if transaction exists
        $stmt_check = $conn->prepare("SELECT id FROM transactions WHERE id = ?");
        if ($stmt_check) {
            $stmt_check->bind_param("i", $delete_id);
            $stmt_check->execute();
            $stmt_check->store_result();
            if ($stmt_check->num_rows == 0) {
                $error = "Transaction not found.";
            } else {
                // Proceed to delete
                $stmt_delete = $conn->prepare("DELETE FROM transactions WHERE id = ?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("i", $delete_id);
                    if ($stmt_delete->execute()) {
                        $success = "Transaction deleted successfully.";
                    } else {
                        $error = "Error deleting transaction: " . $stmt_delete->error;
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
        // Fetch all transactions
        $transactions = [];
        $query = "SELECT transactions.id, users.username, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description
                  FROM transactions
                  JOIN users ON transactions.user_id = users.id
                  JOIN categories ON transactions.category_id = categories.id
                  ORDER BY transactions.id DESC";
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $transactions[] = $row;
            }
        } else {
            $error = "Error fetching transactions: " . $conn->error;
        }

        if (empty($error)) {
            // Set headers to download file
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename=transactions_' . date('Y-m-d') . '.csv');

            // Open the output stream
            $output = fopen('php://output', 'w');

            // Output the column headings
            fputcsv($output, ['ID', 'User', 'Amount', 'Type', 'Category', 'Date', 'Description']);

            // Output the data
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['id'],
                    $transaction['username'],
                    number_format($transaction['amount'], 2),
                    ucfirst($transaction['type']),
                    $transaction['category'],
                    $transaction['date'],
                    $transaction['description']
                ]);
            }

            fclose($output);
            exit();
        }
    }
}

// Fetch all transactions with user and category details
$transactions = [];
$query = "SELECT transactions.id, users.username, transactions.amount, transactions.type, categories.name AS category, transactions.date, transactions.description
          FROM transactions
          JOIN users ON transactions.user_id = users.id
          JOIN categories ON transactions.category_id = categories.id
          ORDER BY transactions.id DESC";
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
} else {
    die("Error fetching transactions: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Manage Transactions</title>
    <!-- Include Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Add Font Awesome CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            <a href="dashboard.php"><i class="fas fa-tachometer-alt me-1"></i>Dashboard</a>
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

        <!-- Export and Add Transaction Buttons -->
        <div class="d-flex justify-content-between mb-3">
            <h5 class="mb-0">All Transactions</h5>
            <div>
                <form method="POST" action="manage_transactions.php" class="d-inline">
                    <!-- CSRF Token -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <button type="submit" name="export_csv" class="btn btn-success">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                </form>
                <a href="add_transaction.php" class="btn btn-primary ms-2">
                    <i class="bi bi-plus-lg"></i> Add Transaction
                </a>
            </div>
        </div>

        <!-- Transactions Table Card -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Transactions List</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($transactions)): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['id']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['username']); ?></td>
                                        <td><?php echo htmlspecialchars('$' . number_format($transaction['amount'], 2)); ?></td>
                                        <td>
                                            <?php if ($transaction['type'] == 'income'): ?>
                                                <span class="badge bg-success">Income</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Expense</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($transaction['category']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['date']); ?></td>
                                        <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                        <td>
                                            <a href="edit_transaction.php?id=<?php echo $transaction['id']; ?>" class="edit-button me-2" title="Edit Transaction">
                                                <i class="bi bi-pencil-fill"></i>
                                            </a>
                                            <button type="button" class="delete-button btn btn-link text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $transaction['id']; ?>" title="Delete Transaction">
                                                <i class="bi bi-trash-fill"></i>
                                            </button>

                                            <!-- Delete Confirmation Modal -->
                                            <div class="modal fade" id="deleteModal<?php echo $transaction['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $transaction['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <form method="POST" action="manage_transactions.php">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $transaction['id']; ?>">Confirm Deletion</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to delete this transaction?
                                                            </div>
                                                            <div class="modal-footer">
                                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                                <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <button type="submit" name="delete_transaction" class="btn btn-danger">Delete</button>
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
                        <p class="mb-0">No transactions found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back Button -->
        <div class="mt-4 text-center">
            <a href="admin_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Include Bootstrap JS and dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
                    