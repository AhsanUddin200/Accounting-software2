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
                
                // Parse date properly
                $date = !empty($data[0]) ? date('Y-m-d', strtotime(str_replace('/', '-', $data[0]))) : date('Y-m-d');
                if ($date === '1970-01-01') {
                    $date = date('Y-m-d'); // Use today's date if parsing fails
                }
                
                $description = trim($data[2]);
                
                // Handle amounts properly
                $debit_amount = str_replace([',' , ' '], '', $data[3]);
                $credit_amount = str_replace([',' , ' '], '', $data[4]);
                
                if (!empty($debit_amount) && floatval($debit_amount) > 0) {
                    $type = 'expense';
                    $amount = floatval($debit_amount);
                    $debit = $amount;
                    $credit = 0;
                } else if (!empty($credit_amount) && floatval($credit_amount) > 0) {
                    $type = 'income';
                    $amount = floatval($credit_amount);
                    $debit = 0;
                    $credit = $amount;
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
                    $category_id, $entry_type, $debit, $credit,
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
</head>
<body>
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h4>Import Transaction Data</h4>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
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

                    <div class="mb-3">
                        <label class="form-label">Upload Transaction CSV File</label>
                        <input type="file" name="transaction_csv" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import Transactions</button>
                </form>

                <div class="mt-4">
                    <h5>CSV Format:</h5>
                    <p>Your CSV should have these columns:</p>
                    <ol>
                        <li>Transaction Date</li>
                        <li>TXN ID</li>
                        <li>Description</li>
                        <li>Debit</li>
                        <li>Credit</li>
                        <li>Balance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
</body>
</html>