<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch expense categories with error checking
$categories_query = "SELECT id, COALESCE(name, '') as name FROM account_categories WHERE head_id = 5 ORDER BY name";
$categories = $conn->query($categories_query);

if (!$categories) {
    die("Error fetching categories: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validate inputs
        if (empty($_POST['expense_category_id']) || empty($_POST['amount'])) {
            throw new Exception("All fields are required");
        }

        $user_id = $_SESSION['user_id'];
        $expense_category_id = (int)$_POST['expense_category_id'];
        $amount = (float)$_POST['amount'];
        $description = trim($_POST['description'] ?? '');
        $date = $_POST['date'] ?? date('Y-m-d');
        $voucher_number = 'PC-' . date('Ym') . '-' . sprintf('%04d', rand(1, 9999));
        $head_id_expense = 5;
        $head_id_asset = 1;
        $cash_in_hand_id = 23;

        // First Transaction: Expense entry
        $sql = "INSERT INTO transactions 
               (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
               VALUES (?, ?, ?, ?, 'expense', ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiidsss", 
            $user_id,
            $head_id_expense,
            $expense_category_id,
            $amount,
            $date,
            $description,
            $voucher_number
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        // Second Transaction: Asset entry
        $credit_description = "Petty Cash Payment for " . $description;
        $sql = "INSERT INTO transactions 
               (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
               VALUES (?, ?, ?, ?, 'asset', ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiidsss", 
            $user_id,
            $head_id_asset,
            $cash_in_hand_id,
            $amount,
            $date,
            $credit_description,
            $voucher_number
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }

        $conn->commit();
        $_SESSION['success'] = "Petty cash expense recorded successfully!";
        header("Location: petty_cash_expense.php");
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: petty_cash_expense.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Petty Cash Expense</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .card-header {
            background-color: #0d6efd;
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Record Petty Cash Expense</h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">
                            <div class="mb-3">
                                <label class="form-label">Expense Head</label>
                                <select name="expense_category_id" class="form-select" required>
                                    <option value="">Select Expense Head</option>
                                    <?php while($category = $categories->fetch_assoc()): ?>
                                        <option value="<?php echo (int)$category['id']; ?>">
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" name="amount" class="form-control" step="0.01" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" required></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    Record Expense
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 