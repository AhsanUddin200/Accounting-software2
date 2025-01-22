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
    $debit_head_ids = $_POST['debit_head_id'] ?? [];
    $debit_category_ids = $_POST['debit_category_id'] ?? [];
    $debit_amounts = $_POST['debit_amount'] ?? [];
    $debit_description = $_POST['debit_description'] ?? [];
    $credit_head_ids = $_POST['credit_head_id'] ?? [];
    $credit_category_ids = $_POST['credit_category_id'] ?? [];
    $credit_amounts = $_POST['credit_amount'] ?? [];
    $credit_description = $_POST['credit_description'] ?? [];
    $date = isset($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $user_id = $_SESSION['user_id'];

    try {
        // Validate total debits equals total credits
        $total_debits = array_sum($debit_amounts);
        $total_credits = array_sum($credit_amounts);
        
        if (abs($total_debits - $total_credits) > 0.01) { // Using 0.01 to handle floating point precision
            throw new Exception("Total debits must equal total credits. Current difference: " . 
                              number_format(abs($total_debits - $total_credits), 2));
        }

        // Start transaction
        $conn->begin_transaction();

        // Generate voucher number
        $voucher_number = generateVoucherNumber($conn);

        // Process all debit entries
        $debit_transaction_ids = [];
        for ($i = 0; $i < count($debit_head_ids); $i++) {
            if (empty($debit_head_ids[$i]) || empty($debit_amounts[$i])) continue;
            
            $debit_type = getTypeFromHead($debit_head_ids[$i], $conn);
            
            $debit_sql = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_debit = $conn->prepare($debit_sql);
            $stmt_debit->bind_param("iiidssss", 
                $user_id,
                $debit_head_ids[$i],
                $debit_category_ids[$i],
                $debit_amounts[$i],
                $debit_type,
                $date,
                $debit_description[$i],
                $voucher_number
            );
            
            if (!$stmt_debit->execute()) {
                throw new Exception("Error inserting debit: " . $stmt_debit->error);
            }
            
            $debit_transaction_id = $stmt_debit->insert_id;
            $debit_transaction_ids[] = $debit_transaction_id;
            
            // Add ledger entry for debit
            $ledgerCodeDebit = generateLedgerCode($debit_head_ids[$i], $conn);
            addLedgerEntry($conn, $debit_transaction_id, $ledgerCodeDebit, $debit_type, $debit_amounts[$i], 0, $debit_description[$i], $date);
        }

        // Process all credit entries
        $credit_transaction_ids = [];
        for ($i = 0; $i < count($credit_head_ids); $i++) {
            if (empty($credit_head_ids[$i]) || empty($credit_amounts[$i])) continue;
            
            $credit_type = getTypeFromHead($credit_head_ids[$i], $conn);
            
            $credit_sql = "INSERT INTO transactions (user_id, head_id, category_id, amount, type, date, description, voucher_number) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt_credit = $conn->prepare($credit_sql);
            $stmt_credit->bind_param("iiidssss", 
                $user_id,
                $credit_head_ids[$i],
                $credit_category_ids[$i],
                $credit_amounts[$i],
                $credit_type,
                $date,
                $credit_description[$i],
                $voucher_number
            );
            
            if (!$stmt_credit->execute()) {
                throw new Exception("Error inserting credit: " . $stmt_credit->error);
            }
            
            $credit_transaction_id = $stmt_credit->insert_id;
            $credit_transaction_ids[] = $credit_transaction_id;
            
            // Add ledger entry for credit
            $ledgerCodeCredit = generateLedgerCode($credit_head_ids[$i], $conn);
            addLedgerEntry($conn, $credit_transaction_id, $ledgerCodeCredit, $credit_type, 0, $credit_amounts[$i], $credit_description[$i], $date);
        }

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
                        <h5>Debit Entries</h5>
                        <div id="debit-entries">
                            <div class="debit-entry row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Accounting Head</label>
                                        <select name="debit_head_id[]" class="form-select debit-head" required>
                                            <option value="">Select Head</option>
                                            <?php foreach($heads as $head): ?>
                                                <option value="<?php echo $head['id']; ?>">
                                                    <?php echo htmlspecialchars($head['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">    
                                        <label>Category</label>
                                        <select name="debit_category_id[]" class="form-select debit-category" required>
                                            <option value="">Select Head First</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Amount</label>
                                        <input type="number" name="debit_amount[]" class="form-control debit-amount" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Description</label>
                                        <input type="text" name="debit_description[]" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger remove-entry mb-3"><i class="fas fa-minus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" id="add-debit" class="btn btn-success"><i class="fas fa-plus"></i> Add Debit Entry</button>
                            <div class="float-end">Total Debit: <span id="total-debit">0.00</span></div>
                        </div>
                    </div>

                    <!-- Credit Section -->
                    <div class="row mb-4">
                        <h5>Credit Entries</h5>
                        <div id="credit-entries">
                            <div class="credit-entry row mb-3">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Accounting Head</label>
                                        <select name="credit_head_id[]" class="form-select credit-head" required>
                                            <option value="">Select Head</option>
                                            <?php foreach($heads as $head): ?>
                                                <option value="<?php echo $head['id']; ?>">
                                                    <?php echo htmlspecialchars($head['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">    
                                        <label>Category</label>
                                        <select name="credit_category_id[]" class="form-select credit-category" required>
                                            <option value="">Select Head First</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="form-group">
                                        <label>Amount</label>
                                        <input type="number" name="credit_amount[]" class="form-control credit-amount" step="0.01" required>
                                    </div>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-group">
                                        <label>Description</label>
                                        <input type="text" name="credit_description[]" class="form-control" required>
                                    </div>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger remove-entry mb-3"><i class="fas fa-minus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <button type="button" id="add-credit" class="btn btn-success"><i class="fas fa-plus"></i> Add Credit Entry</button>
                            <div class="float-end">Total Credit: <span id="total-credit">0.00</span></div>
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
            function updateTotals() {
                let totalDebit = 0;
                let totalCredit = 0;
                
                $('.debit-amount').each(function() {
                    totalDebit += parseFloat($(this).val()) || 0;
                });
                
                $('.credit-amount').each(function() {
                    totalCredit += parseFloat($(this).val()) || 0;
                });
                
                $('#total-debit').text(totalDebit.toFixed(2));
                $('#total-credit').text(totalCredit.toFixed(2));
                
                // Validate totals match
                if (Math.abs(totalDebit - totalCredit) > 0.01) {
                    $('.btn-primary[type="submit"]').prop('disabled', true);
                    $('.balance-warning').remove();
                    $('<div class="alert alert-warning balance-warning">Debit and Credit totals must match</div>').insertBefore('form button[type="submit"]');
                } else {
                    $('.btn-primary[type="submit"]').prop('disabled', false);
                    $('.balance-warning').remove();
                }
            }

            // Clone and add new entry
            function addEntry(type) {
                const container = $(`#${type}-entries`);
                const newEntry = container.children().first().clone();
                
                // Clear values
                newEntry.find('input').val('');
                newEntry.find('select').val('');
                
                // Add remove button functionality
                newEntry.find('.remove-entry').click(function() {
                    $(this).closest(`.${type}-entry`).remove();
                    updateTotals();
                });
                
                // Add change handlers for head selection
                newEntry.find(`.${type}-head`).change(function() {
                    const categorySelect = $(this).closest(`.${type}-entry`).find(`.${type}-category`);
                    updateCategories($(this).val(), categorySelect);
                });
                
                container.append(newEntry);
            }

            // Update categories based on head selection
            function updateCategories(headId, categorySelect) {
                if (headId) {
                    $.ajax({
                        url: 'get_categories.php',
                        type: 'GET',
                        data: { head_id: headId },
                        success: function(response) {
                            categorySelect.html(response);
                        }
                    });
                } else {
                    categorySelect.html('<option value="">Select Head First</option>');
                }
            }

            // Add entry button handlers
            $('#add-debit').click(() => addEntry('debit'));
            $('#add-credit').click(() => addEntry('credit'));

            // Initial remove button functionality
            $('.remove-entry').click(function() {
                $(this).closest('.debit-entry, .credit-entry').remove();
                updateTotals();
            });

            // Monitor amount changes
            $(document).on('input', '.debit-amount, .credit-amount', updateTotals);

            // Head change handlers
            $(document).on('change', '.debit-head', function() {
                updateCategories($(this).val(), $(this).closest('.debit-entry').find('.debit-category'));
            });

            $(document).on('change', '.credit-head', function() {
                updateCategories($(this).val(), $(this).closest('.credit-entry').find('.credit-category'));
            });
        });
    </script>
</body>
</html>