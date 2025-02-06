<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['finja_csv'])) {
    try {
        $file = $_FILES['finja_csv']['tmp_name'];
        
        if (($handle = fopen($file, "r")) !== FALSE) {
            // Read header row to verify Finja format
            $header = fgetcsv($handle);
            
            // Verify if it's a Finja CSV by checking columns
            if (!verifyFinjaFormat($header)) {
                throw new Exception("Invalid file format. Please upload a Finja CSV export file.");
            }
            
            $conn->begin_transaction();
            
            // First pass: Analyze data and create structure
            while (($data = fgetcsv($handle)) !== FALSE) {
                $description = $data[2]; // Description column
                
                // Extract category and subcategory from description
                list($category, $subcategory) = parseDescription($description);
                
                // Create/get accounting head (default to 'General' if not determined)
                $head_id = getOrCreateHead($conn, 'General');
                
                // Create/get category
                $category_id = getOrCreateCategory($conn, $head_id, $category);
                
                // Create/get subcategory
                $subcategory_id = getOrCreateSubcategory($conn, $category_id, $subcategory);
            }
            
            // Reset file pointer for second pass
            rewind($handle);
            $header = fgetcsv($handle); // Skip header again
            
            // Second pass: Import transactions
            while (($data = fgetcsv($handle)) !== FALSE) {
                $date = date('Y-m-d', strtotime($data[0]));
                $txn_id = $data[1];
                $description = $data[2];
                $debit = floatval(str_replace(',', '', $data[3]));
                $credit = floatval(str_replace(',', '', $data[4]));
                
                // Get subcategory_id based on description
                list($category, $subcategory) = parseDescription($description);
                $subcategory_id = getSubcategoryId($conn, $subcategory);
                
                // Insert transaction
                $stmt = $conn->prepare("INSERT INTO transactions (date, txn_id, subcategory_id, description, debit, credit) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisdd", $date, $txn_id, $subcategory_id, $description, $debit, $credit);
                $stmt->execute();
            }
            
            $conn->commit();
            fclose($handle);
            $_SESSION['success'] = "Finja data imported successfully!";
            
        } else {
            throw new Exception("Could not open CSV file");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header("Location: import_finja_data.php");
    exit();
}

function verifyFinjaFormat($header) {
    $required_columns = ['Transaction Date', 'TXN ID', 'Description', 'Debit', 'Credit', 'Balance'];
    foreach ($required_columns as $column) {
        if (!in_array($column, $header)) {
            return false;
        }
    }
    return true;
}

function parseDescription($description) {
    // This can be customized based on how descriptions are formatted in your Finja CSV
    // For now, using a simple split
    $parts = explode('-', $description);
    $category = trim($parts[0] ?? 'General');
    $subcategory = trim($parts[1] ?? $category);
    return [$category, $subcategory];
}

// Helper functions to create/get IDs
function getOrCreateHead($conn, $name) {
    $stmt = $conn->prepare("SELECT id FROM accounting_heads WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['id'];
    }
    
    $stmt = $conn->prepare("INSERT INTO accounting_heads (name) VALUES (?)");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    return $conn->insert_id;
}

// Similar functions for categories and subcategories...
?>

<!DOCTYPE html>
<html>
<head>
    <title>Import Finja Data</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h4>Import Finja CSV Data</h4>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label class="form-label">Upload Finja CSV File</label>
                        <input type="file" name="finja_csv" class="form-control" accept=".csv" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Import Finja Data</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 