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

// Fetch expense categories
$categories_query = "SELECT id, COALESCE(name, '') as name FROM account_categories WHERE head_id = 5 ORDER BY name";
$categories = $conn->query($categories_query);

// Fetch cash subcategories
$cash_subcategories_query = "SELECT id, name FROM account_subcategories WHERE category_id = 23 ORDER BY name";
$cash_subcategories = $conn->query($cash_subcategories_query);

if (!$categories) {
    die("Error fetching categories: " . $conn->error);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $conn->begin_transaction();

        // Validate inputs
        if (empty($_POST['expense_category_id']) || empty($_POST['debit_amount']) || empty($_POST['credit_amount'])) {
            throw new Exception("All fields are required");
        }

        $user_id = $_SESSION['user_id'];
        $expense_category_id = (int)$_POST['expense_category_id'];
        $expense_subcategory_id = !empty($_POST['expense_subcategory_id']) ? (int)$_POST['expense_subcategory_id'] : null;
        $cash_subcategory_id = !empty($_POST['cash_subcategory_id']) ? (int)$_POST['cash_subcategory_id'] : null;
        $debit_amount = (float)$_POST['debit_amount'];
        $credit_amount = (float)$_POST['credit_amount'];
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
            $debit_amount,
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
            $credit_amount,
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
            <div class="col-md-10">
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
                            <!-- Date and Voucher Section -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date</label>
                                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>

                            <!-- Debit Entry Section -->
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Debit Entry</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Expense Category</label>
                                            <select name="expense_category_id" class="form-select expense-category" required>
                                                <option value="">Select Expense Category</option>
                                                <?php while($category = $categories->fetch_assoc()): ?>
                                                    <option value="<?php echo (int)$category['id']; ?>">
                                                        <?php echo htmlspecialchars($category['name']); ?>
                                                    </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Sub Category (Optional)</label>
                                            <select name="expense_subcategory_id" class="form-select expense-subcategory">
                                                <option value="">Select Category First</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Debit Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" name="debit_amount" class="form-control amount-input" step="0.01" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Credit Entry Section -->
                            <div class="card mb-3">
                                <div class="card-header bg-light">
                                    <h5 class="mb-0">Credit Entry</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Cash Account</label>
                                            <input type="text" class="form-control" value="Cash in Hand" readonly>
                                            <input type="hidden" name="cash_category_id" value="23">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Sub Category (Optional)</label>
                                            <select name="cash_subcategory_id" class="form-select">
                                                <option value="">Select Sub Category</option>
                                                <?php if ($cash_subcategories && $cash_subcategories->num_rows > 0): ?>
                                                    <?php while($sub = $cash_subcategories->fetch_assoc()): ?>
                                                        <option value="<?php echo (int)$sub['id']; ?>">
                                                            <?php echo htmlspecialchars($sub['name']); ?>
                                                        </option>
                                                    <?php endwhile; ?>
                                                <?php endif; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Credit Amount</label>
                                            <div class="input-group">
                                                <span class="input-group-text">PKR</span>
                                                <input type="number" name="credit_amount" class="form-control amount-input" step="0.01" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2" required></textarea>
                            </div>

                            <!-- Total Display -->
                            <div class="alert alert-info mb-3">
                                <div class="row">
                                    <div class="col-md-4">Total Debit: <span id="total-debit">0.00</span></div>
                                    <div class="col-md-4">Total Credit: <span id="total-credit">0.00</span></div>
                                    <div class="col-md-4">Difference: <span id="difference">0.00</span></div>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="submit-btn">
                                    Record Transaction
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        function updateTotals() {
            let debitAmount = parseFloat($('input[name="debit_amount"]').val()) || 0;
            let creditAmount = parseFloat($('input[name="credit_amount"]').val()) || 0;
            
            $('#total-debit').text(debitAmount.toFixed(2));
            $('#total-credit').text(creditAmount.toFixed(2));
            
            let difference = Math.abs(debitAmount - creditAmount);
            $('#difference').text(difference.toFixed(2));
            
            // Disable submit if amounts don't match
            if (difference > 0) {
                $('#submit-btn').prop('disabled', true);
                $('#difference').parent().addClass('text-danger');
            } else {
                $('#submit-btn').prop('disabled', false);
                $('#difference').parent().removeClass('text-danger');
            }
        }

        // Update totals when amounts change
        $('.amount-input').on('input', updateTotals);

        // Copy debit amount to credit amount
        $('input[name="debit_amount"]').on('input', function() {
            $('input[name="credit_amount"]').val($(this).val());
            updateTotals();
        });

        // Function to load subcategories
        function loadSubcategories(categoryId) {
            if(categoryId) {
                $.ajax({
                    url: 'get_subcategories.php',
                    type: 'GET',
                    data: { category_id: categoryId },
                    success: function(response) {
                        $('.expense-subcategory').html(response);
                    }
                });
            } else {
                $('.expense-subcategory').html('<option value="">Select Category First</option>');
            }
        }

        // Load subcategories when expense category changes
        $('.expense-category').change(function() {
            loadSubcategories($(this).val());
        });
    });
    </script>
</body>
</html> 