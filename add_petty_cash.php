<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Petty Cash Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-md-6">
                <h2><i class="fas fa-cash-register me-2"></i>Add Petty Cash Transaction</h2>
            </div>
            <div class="col-md-6 text-end">
                <a href="petty_cash_ledger.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Petty Cash Ledger
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="process_petty_cash.php">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Transaction Type</label>
                            <select name="type" class="form-select" required>
                                <option value="">Select Type</option>
                                <option value="receipt">Receipt (Money In)</option>
                                <option value="payment">Payment (Money Out)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Amount</label>
                            <input type="number" name="amount" class="form-control" step="0.01" required>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-select" required>
                                <option value="">Select Category</option>
                                <option value="office_supplies">Office Supplies</option>
                                <option value="transportation">Transportation</option>
                                <option value="refreshments">Refreshments</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="postage">Postage & Courier</option>
                                <option value="miscellaneous">Miscellaneous</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Save Transaction
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 