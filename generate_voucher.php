<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

function getVoucherType($voucher_number) {
    global $conn;
    
    // Get the transaction details for this voucher
    $query = "SELECT l.debit, l.credit, ah.name as head_name 
              FROM transactions t
              JOIN ledgers l ON t.id = l.transaction_id
              JOIN accounting_heads ah ON t.head_id = ah.id
              WHERE t.voucher_number = ?";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $voucher_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $has_income = false;
    $has_expense = false;
    
    while ($row = $result->fetch_assoc()) {
        // Check for income transactions (credit in income accounts)
        if (stripos($row['head_name'], 'income') !== false || 
            stripos($row['head_name'], 'fees') !== false || 
            stripos($row['head_name'], 'revenue') !== false) {
            if ($row['credit'] > 0) {
                $has_income = true;
            }
        }
        
        // Check for expense transactions (debit in expense accounts)
        if (stripos($row['head_name'], 'expense') !== false || 
            stripos($row['head_name'], 'payment') !== false || 
            stripos($row['head_name'], 'cost') !== false) {
            if ($row['debit'] > 0) {
                $has_expense = true;
            }
        }
    }
    
    // Determine voucher type based on transaction nature
    if ($has_income && !$has_expense) {
        return [
            'receipt-voucher',
            'Income Voucher',
            'Used for recording income transactions such as student fees and other receipts',
            'RV'
        ];
    } elseif ($has_expense && !$has_income) {
        return [
            'payment-voucher',
            'Payment Voucher',
            'Used for recording payments to suppliers, creditors, and other expenses',
            'PV'
        ];
    } else {
        return [
            'journal-voucher',
            'Journal Voucher',
            'Used for recording general transactions that don\'t involve direct cash payments or receipts',
            'JV'
        ];
    }
}

if (isset($_GET['voucher_number'])) {
    $voucher_number = $_GET['voucher_number'];
    
    $query = "SELECT 
        t.voucher_number,
        t.date,
        t.description,
        l.ledger_code,
        ah.name as head_name,
        ac.name as category_name,
        l.debit,
        l.credit,
        u.username as created_by,
        t.created_at
        FROM transactions t
        JOIN ledgers l ON t.id = l.transaction_id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        JOIN users u ON t.user_id = u.id
        WHERE t.voucher_number = ?
        ORDER BY l.id ASC";
        
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $voucher_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get first row for header details
    $header = $result->fetch_assoc();

    list($voucher_type_class, $voucher_type_text, $voucher_type_description, $voucher_prefix) = getVoucherType($voucher_number);

    // Assuming you have the user's name stored in the session
    $generated_by = htmlspecialchars($_SESSION['username']); // Adjust according to your session variable
    $generated_on = date('d M Y H:i:s'); // Format for date and time
?>

<!DOCTYPE html>
<html>
<head>
    <title>Voucher #<?php echo $voucher_number; ?></title>
    <style>
        body {
            position: relative;
        }
        
        /* Watermark container */
        .watermark-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }
        
        /* Logo watermark */
        .watermark-logo {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.1; /* Watermark transparency */
            width: 700px; /* Increase size as needed */
            height: auto; /* Maintain aspect ratio */
        }
        .watermark-text {
            position: absolute;
            width: 100%;
            text-align: center;
            opacity: 0.1;
            font-size: 60px;
            font-weight: bold;
            color: #000;
        }
        
        .watermark-text:nth-child(2) { top: 30%; }
        .watermark-text:nth-child(3) { top: 45%; }
        .watermark-text:nth-child(4) { top: 60%; }

        /* Rest of your existing styles */
        .voucher-header {
            text-align: center;
            margin-bottom: 15px;
            position: relative;
            padding-top: 20px; /* Add some space for logo */
            line-height: 1.2;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
        }
        .details-table th, .details-table td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        .footer {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        /* Add this new style for header logo */
        .header-logo {
            width: 150px; /* Adjust size as needed */
            position: absolute;
            left: 20px;
            top: 20px;
        }

        /* QR Code Style */
        .qr-code {
            width: 100px; /* Adjust size as needed */
            margin-top: 100px;
        }

        /* New style for QR code section at the bottom */
        .qr-section {
            text-align: center;
            margin-top: 100px;
        }

        .voucher-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            color: white;
            font-weight: 500;
            margin: 5px 0 10px 0;
            font-size: 0.9em;
        }

        .receipt-voucher {
            background-color: #48BB78;
        }

        .payment-voucher {
            background-color: #ED8936;
        }

        .journal-voucher {
            background-color: #4299E1;
        }

        .unknown-voucher {
            background-color: #718096;
        }
    </style>
</head>
<body>
    <!-- Add watermark container before your existing content -->
    <div class="watermark-container">
        <img src="https://dcassetcdn.com/design_img/682541/99243/99243_4267349_682541_image.jpg" class="watermark-logo" alt="School Logo">
        <div class="watermark-text">Financial Management System</div>
        <div class="watermark-text">VOUCHER #<?php echo $voucher_number; ?></div>
        <div class="watermark-text"><?php echo date('d-m-Y'); ?></div>
    </div>
    
    <div class="voucher-header">
        <img src="https://dcassetcdn.com/design_img/682541/99243/99243_4267349_682541_image.jpg" class="header-logo" alt="School Logo">
        <h2>VOUCHER</h2>
        <div class="voucher-type <?php echo $voucher_type_class; ?>">
            <?php echo $voucher_type_text; ?>
        </div>
        <p>Voucher #: <?php echo $voucher_prefix . substr($voucher_number, 2); ?></p>
        <p>Date: <?php echo date('d M Y', strtotime($header['date'])); ?></p>
        <p>Description: <?php echo htmlspecialchars($header['description']); ?></p>
    </div>
    
    <table class="details-table">
        <thead>
            <tr>
                <th>Ledger Code</th>
                <th>Account Head</th>
                <th>Category</th>
                <th>Description</th>
                <th>Debit (PKR)</th>
                <th>Credit (PKR)</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $result->data_seek(0);
            $total_debit = 0;
            $total_credit = 0;
            while ($row = $result->fetch_assoc()):
                $total_debit += $row['debit'];
                $total_credit += $row['credit'];
            ?>
            <tr>
                <td><?php echo $row['ledger_code']; ?></td>
                <td><?php echo $row['head_name']; ?></td>
                <td><?php echo $row['category_name']; ?></td>
                <td><?php echo htmlspecialchars($row['description']); ?></td>
                <td class="text-end"><?php echo formatCurrency($row['debit']); ?></td>
                <td class="text-end"><?php echo formatCurrency($row['credit']); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="3">Total</th>
                <th class="text-end"></th>
                <th class="text-end"><?php echo formatCurrency($total_debit); ?></th>
                <th class="text-end"><?php echo formatCurrency($total_credit); ?></th>
            </tr>
        </tfoot>
    </table>
    
    <div class="footer">
        <div>
            <p>_________________</p>
            <p>Authorized Signature</p>
        </div>
        <div>
            <p>_________________</p>
            <p>Received By</p>
        </div>
    </div>

    <!-- QR Code Section at the Bottom -->
    <div class="qr-section">
        <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=https://ahsan.rehan.school/Accountweb" class="qr-code" alt="QR Code">
        <p>Scan to visit Financial Management System</p>
        <p style="font-size: 12px;"><em>Developed by Saad & Ahsan</em></p>
    </div>

    <!-- Add this section where you want to display the generated information -->
    <div class="generated-info" style="text-align: right; margin-top: 20px;">
        <p>Generated By: <?php echo $generated_by; ?></p>
        <p>Generated On: <?php echo $generated_on; ?></p>
    </div>
</body>
</html>
<?php
}
?>