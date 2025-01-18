<?php
require_once 'session.php';
require_once 'db.php';

// Check admin access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Function to determine transaction type
function getTransactionType($headName) {
    $headName = ucfirst(strtolower($headName));
    switch($headName) {
        case 'Assets':
            return 'Asset';
        case 'Liabilities':
            return 'Liability';
        case 'Equities':
            return 'Equity';
        case 'Income':
            return 'Income';
        case 'Expenses':
            return 'Expense';
        default:
            return $headName;
    }
}

// Function to generate voucher number
function generateVoucherNumber($conn) {
    $prefix = 'TRN';  // Using a generic transaction prefix
    $yearMonth = date('Ym');
    
    $query = "SELECT voucher_number 
             FROM transactions 
             WHERE voucher_number LIKE '$prefix$yearMonth%' 
             ORDER BY voucher_number DESC 
             LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $lastNumber = $result->fetch_assoc()['voucher_number'];
        $sequence = intval(substr($lastNumber, -4)) + 1;
    } else {
        $sequence = 1;
    }
    
    return $prefix . $yearMonth . str_pad($sequence, 4, '0', STR_PAD_LEFT);
}

// Function to get type from head
function getTypeFromHead($head_id, $conn) {
    $query = "SELECT name FROM accounting_heads WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $head = $result->fetch_assoc();
    return getTransactionType($head['name']);
}

// Function to add a ledger entry
function addLedgerEntry($conn, $transactionId, $ledgerCode, $accountType, $debit, $credit, $description, $date) {
    $stmt = $conn->prepare("INSERT INTO ledgers (transaction_id, ledger_code, account_type, debit, credit, description, date) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issddss", $transactionId, $ledgerCode, $accountType, $debit, $credit, $description, $date);
    $stmt->execute();
    $stmt->close();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data with proper validation
    $debit_head_id = isset($_POST['debit_head_id']) ? intval($_POST['debit_head_id']) : 0;
    $debit_category_id = isset($_POST['debit_category_id']) ? intval($_POST['debit_category_id']) : 0;
    $debit_amount = isset($_POST['debit_amount']) ? floatval($_POST['debit_amount']) : 0;
    $credit_head_id = isset($_POST['credit_head_id']) ? intval($_POST['credit_head_id']) : 0;
    $credit_category_id = isset($_POST['credit_category_id']) ? intval($_POST['credit_category_id']) : 0;
    $credit_amount = isset($_POST['credit_amount']) ? floatval($_POST['credit_amount']) : 0;
    $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    $user_id = $_SESSION['user_id'];

    try {
        // Start transaction
        $conn->begin_transaction();

        // Validate categories belong to their respective heads
        // ... existing validation code ...

        // Get head names
        $head_query = "SELECT ah.id, ah.name 
                      FROM accounting_heads ah 
                      WHERE ah.id IN (?, ?)";
        $stmt = $conn->prepare($head_query);
        $stmt->bind_param("ii", $debit_head_id, $credit_head_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $heads = [];
        while($row = $result->fetch_assoc()) {
            $heads[$row['id']] = $row;
        }

        // Generate voucher number
        $voucher_number = generateVoucherNumber($conn);

        // Insert debit transaction
        $debit_sql = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_debit = $conn->prepare($debit_sql);
        if (!$stmt_debit) {
            throw new Exception("Prepare failed for debit: " . $conn->error);
        }

        $debit_type = getTransactionType($heads[$debit_head_id]['name']);
        $stmt_debit->bind_param("iiidssss", 
            $user_id,
            $debit_head_id,
            $debit_category_id,
            $debit_amount,
            $debit_type,
            $date,
            $description,
            $voucher_number
        );

        if (!$stmt_debit->execute()) {
            throw new Exception("Error inserting debit: " . $stmt_debit->error);
        }

        $debit_transaction_id = $stmt_debit->insert_id;

        // Insert credit transaction
        $credit_sql = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_credit = $conn->prepare($credit_sql);
        if (!$stmt_credit) {
            throw new Exception("Prepare failed for credit: " . $conn->error);
        }

        $credit_type = getTransactionType($heads[$credit_head_id]['name']);
        $stmt_credit->bind_param("iiidssss", 
            $user_id,
            $credit_head_id,
            $credit_category_id,
            $credit_amount,
            $credit_type,
            $date,
            $description,
            $voucher_number
        );

        if (!$stmt_credit->execute()) {
            throw new Exception("Error inserting credit: " . $stmt_credit->error);
        }

        $credit_transaction_id = $stmt_credit->insert_id;

        // Add corresponding ledger entries with correct types
        $ledgerCodeDebit = generateLedgerCode($debit_head_id, $conn);
        addLedgerEntry($conn, $debit_transaction_id, $ledgerCodeDebit, $debit_type, $debit_amount, 0, $description, $date);

        $ledgerCodeCredit = generateLedgerCode($credit_head_id, $conn);
        addLedgerEntry($conn, $credit_transaction_id, $ledgerCodeCredit, $credit_type, 0, $credit_amount, $description, $date);

        // Commit transaction
        $conn->commit();
        
        $_SESSION['success'] = "Transaction added successfully with voucher number: " . $voucher_number;
        header("Location: accounting.php");
        exit();

    } catch (Exception $e) {
        // Rollback and log error
        $conn->rollback();
        error_log("Transaction Error: " . $e->getMessage());
        $_SESSION['error'] = "Error: " . $e->getMessage();
        header("Location: add_transaction.php");
        exit();
    }
}

// Fetch accounting heads for the form
$heads_query = "SELECT * FROM accounting_heads ORDER BY FIELD(name, 'Assets', 'Liabilities', 'Equities', 'Income', 'Expenses')";
$heads = $conn->query($heads_query);

function generateLedgerCode($head_id, $conn) {
    // Get the head prefix (e.g., AS for Assets, EX for Expenses)
    $prefix_query = "SELECT 
        CASE 
            WHEN name = 'Assets' THEN 'AS'
            WHEN name = 'Liabilities' THEN 'LB'
            WHEN name = 'Equities' THEN 'EQ'
            WHEN name = 'Income' THEN 'IN'
            WHEN name = 'Expenses' THEN 'EX'
        END as prefix
        FROM accounting_heads WHERE id = ?";
    
    $stmt = $conn->prepare($prefix_query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $prefix_result = $stmt->get_result();
    $prefix = $prefix_result->fetch_assoc()['prefix'];

    // Get the last number used for this prefix
    $last_code_query = "SELECT ledger_code 
                       FROM ledgers 
                       WHERE ledger_code LIKE '$prefix%' 
                       ORDER BY ledger_code DESC 
                       LIMIT 1";
    $result = $conn->query($last_code_query);
    
    if ($result->num_rows > 0) {
        $last_code = $result->fetch_assoc()['ledger_code'];
        $number = intval(substr($last_code, 2)) + 1;
    } else {
        $number = 1;
    }

    // Generate new code (e.g., AS0001)
    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .form-select, .form-control {
            margin-bottom: 20px;
        }
        textarea.form-control {
            min-height: 120px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-plus-circle"></i> Add New Transaction</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <!-- Debit Section -->
                    <div class="row mb-4">
                        <h5>Debit Entry</h5>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accounting Head</label>
                                <select name="debit_head_id" id="debit_head_id" class="form-select" required>
                                    <option value="">Select Head</option>
                                    <?php foreach($heads as $head): ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo htmlspecialchars($head['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">    
                                <label>Category</label>
                                <select name="debit_category_id" id="debit_category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" name="debit_amount" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Credit Section -->
                    <div class="row mb-4">
                        <h5>Credit Entry</h5>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Accounting Head</label>
                                <select name="credit_head_id" id="credit_head_id" class="form-select" required>
                                    <option value="">Select Head</option>
                                    <?php foreach($heads as $head): ?>
                                        <option value="<?php echo $head['id']; ?>">
                                            <?php echo htmlspecialchars($head['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">    
                                <label>Category</label>
                                <select name="credit_category_id" id="credit_category_id" class="form-select" required>
                                    <option value="">Select Head First</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label>Amount</label>
                                <input type="number" name="credit_amount" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>

                    <!-- Common Fields -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Date</label>
                                <input type="date" name="date" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="4"></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Transaction
                        </button>
                        <a href="accounting.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            // When debit accounting head changes
            $('#debit_head_id').change(function() {
                var head_id = $(this).val();
                if (head_id) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: head_id },
                        success: function(response) {
                            $('#debit_category_id').html(response);
                        }
                    });
                } else {
                    $('#debit_category_id').html('<option value="">Select Head First</option>');
                }
            });

            // When credit accounting head changes
            $('#credit_head_id').change(function() {
                var head_id = $(this).val();
                if (head_id) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: head_id },
                        success: function(response) {
                            $('#credit_category_id').html(response);
                        }
                    });
                } else {
                    $('#credit_category_id').html('<option value="">Select Head First</option>');
                }
            });
        });
    </script>
</body>
</html>