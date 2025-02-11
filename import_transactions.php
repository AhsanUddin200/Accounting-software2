<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get all subcategories for dropdown
$subcategories_query = "SELECT s.id as subcat_id, s.name as subcat_name, 
                              c.id as cat_id, c.name as cat_name, 
                              h.id as head_id, h.name as head_name 
                       FROM account_subcategories s 
                       JOIN account_categories c ON s.category_id = c.id
                       JOIN accounting_heads h ON c.head_id = h.id
                       ORDER BY h.name, c.name, s.name";
$subcategories = $conn->query($subcategories_query)->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['transaction_csv'])) {
    try {
        if (!isset($_POST['subcategory_id'])) {
            throw new Exception("Please select a subcategory");
        }
        
        $sub_id = $_POST['subcategory_id'];
        $ids_query = "SELECT s.category_id, c.head_id 
                     FROM account_subcategories s 
                     JOIN account_categories c ON s.category_id = c.id 
                     WHERE s.id = ?";
        $stmt = $conn->prepare($ids_query);
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (!$result) {
            throw new Exception("Invalid subcategory selected");
        }
        
        $category_id = $result['category_id'];
        $head_id = $result['head_id'];
        $user_id = $_SESSION['user_id'];
        
        $file = $_FILES['transaction_csv']['tmp_name'];
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle);
            $conn->begin_transaction();
            
            $current_timestamp = date('Y-m-d H:i:s');
            
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (empty($data[0]) || count($data) < 5) continue;
                
                // Finja specific format handling
                $date = !empty($data[0]) ? date('Y-m-d', strtotime(str_replace('/', '-', $data[0]))) : date('Y-m-d');
                $txn_id = $data[1];  // TXN ID
                $description = trim($data[2]);
                $debit_amount = str_replace([',' , ' '], '', $data[3]);
                $credit_amount = str_replace([',' , ' '], '', $data[4]);
                $balance = str_replace([',' , ' '], '', $data[5]);
                
                // Set type based on debit/credit
                if (!empty($debit_amount) && floatval($debit_amount) > 0) {
                    $type = 'expense';  // Make sure this matches your type filter
                    $amount = floatval($debit_amount);
                } else if (!empty($credit_amount) && floatval($credit_amount) > 0) {
                    $type = 'income';   // Make sure this matches your type filter
                    $amount = floatval($credit_amount);
                } else {
                    continue;
                }
                
                // Generate unique voucher number
                $unique_id = uniqid();
                $voucher_number = "IMP-" . date('Ymd') . "-" . substr($unique_id, -4);
                
                // Insert transaction
                $trans_stmt = $conn->prepare("INSERT INTO transactions (
                    user_id, type, amount, head_id, category_id, description,
                    voucher_number, date, created_at, subcategory_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)");
                
                $trans_stmt->bind_param("isdiisssi",
                    $user_id, $type, $amount, $head_id, $category_id,
                    $description, $voucher_number, $date, $sub_id
                );
                
                if (!$trans_stmt->execute()) {
                    throw new Exception("Error inserting transaction: " . $conn->error);
                }
                
                $transaction_id = $conn->insert_id;
                
                // Generate ledger code
                $ledger_code = ($type == 'expense' ? 'EXP' : 'INC') . date('YmdHis') . rand(100,999);
                
                // Insert ledger entry
                $ledger_stmt = $conn->prepare("INSERT INTO ledgers (
                    voucher_number, ledger_code, transaction_id, account_type,
                    category_id, entry_type, debit, credit, description,
                    date, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                
                $entry_type = 'import';
                
                $ledger_stmt->bind_param("ssisssddss",
                    $voucher_number, $ledger_code, $transaction_id, $type,
                    $category_id, $entry_type, $amount, $amount,
                    $description, $date
                );
                
                if (!$ledger_stmt->execute()) {
                    throw new Exception("Error inserting ledger: " . $conn->error);
                }
            }
            
            $conn->commit();
            fclose($handle);
            $_SESSION['success'] = "Transactions imported successfully!";
            
        } else {
            throw new Exception("Could not open CSV file");
        }
        
    } catch (Exception $e) {
        if (isset($conn)) {
            $conn->rollback();
        }
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: import_transactions.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Transactions</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-container {
            max-width: 800px;
            margin: 40px auto;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-header {
            background: linear-gradient(45deg, #007bff, #0056b3);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 20px;
        }
        .card-header h4 {
            margin: 0;
            font-size: 24px;
        }
        .card-body {
            padding: 30px;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .form-select, .form-control {
            border-radius: 8px;
            padding: 12px;
            border: 1px solid #ced4da;
        }
        .form-select:focus, .form-control:focus {
            border-color: #80bdff;
            box-shadow: 0 0 0 0.2rem rgba(0,123,255,.25);
        }
        .btn-primary {
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            background: linear-gradient(45deg, #007bff, #0056b3);
            border: none;
            transition: all 0.3s ease;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,123,255,0.3);
        }
        .format-guide {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-top: 30px;
        }
        .format-guide h5 {
            color: #0056b3;
            margin-bottom: 15px;
        }
        .alert {
            border-radius: 8px;
            padding: 15px 20px;
        }
        .back-button {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Back Button -->
        <div class="back-button">
            <a href="admin_dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h4><i class="fas fa-file-import me-2"></i>Import Transaction Data</h4>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger d-flex align-items-center">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label">Select Subcategory</label>
                        <select name="subcategory_id" class="form-select" required>
                            <option value="">Choose subcategory...</option>
                            <?php foreach ($subcategories as $sub): ?>
                                <option value="<?= $sub['subcat_id'] ?>">
                                    <?= htmlspecialchars($sub['head_name'] . ' → ' . $sub['cat_name'] . ' → ' . $sub['subcat_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Upload Transaction CSV File</label>
                        <div class="input-group">
                            <input type="file" name="transaction_csv" class="form-control" accept=".csv" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Import
                            </button>
                        </div>
                    </div>
                </form>

                <div class="format-guide">
                    <h5><i class="fas fa-info-circle me-2"></i>CSV Format Guide</h5>
                    <p class="text-muted mb-3">Your CSV file should contain the following columns in order:</p>
                    <div class="row">
                        <div class="col-md-6">
                            <ol class="mb-0">
                                <li>Transaction Date</li>
                                <li>TXN ID</li>
                                <li>Description</li>
                            </ol>
                        </div>
                        <div class="col-md-6">
                            <ol start="4" class="mb-0">
                                <li>Debit Amount</li>
                                <li>Credit Amount</li>
                                <li>Balance</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>