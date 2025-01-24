<?php
// Include necessary files
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get date from URL parameters or set default to current date
$as_of_date = $_GET['as_of_date'] ?? date('Y-m-d');

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="Balance_Sheet_' . $as_of_date . '.xls"');

// Reuse the same queries from balance_sheet.php
$assets_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.debit) - SUM(l.credit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'asset' 
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

$liabilities_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'liability'
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

$equity_query = "
    SELECT 
        ah.name as head_name,
        ac.name as category_name,
        SUM(l.credit) - SUM(l.debit) as balance
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    WHERE t.type = 'equity'
    AND t.date <= ?
    GROUP BY ah.name, ac.name
    HAVING balance != 0
    ORDER BY ah.name, ac.name";

// Execute queries
$stmt = $conn->prepare($assets_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$assets = $stmt->get_result();

$stmt = $conn->prepare($liabilities_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$liabilities = $stmt->get_result();

$stmt = $conn->prepare($equity_query);
$stmt->bind_param("s", $as_of_date);
$stmt->execute();
$equity = $stmt->get_result();

// Calculate totals
$total_assets = 0;
$total_liabilities = 0;
$total_equity = 0;

// Start Excel content
?>
<table border="1">
    <tr>
        <th colspan="2">Balance Sheet as of <?php echo date('d F Y', strtotime($as_of_date)); ?></th>
    </tr>
    
    <!-- Assets Section -->
    <tr>
        <th colspan="2">ASSETS</th>
    </tr>
    <?php 
    $current_head = '';
    while ($row = $assets->fetch_assoc()):
        $total_assets += $row['balance'];
        if ($current_head != $row['head_name']):
            $current_head = $row['head_name'];
    ?>
        <tr>
            <td colspan="2"><strong><?php echo $row['head_name']; ?></strong></td>
        </tr>
    <?php endif; ?>
        <tr>
            <td><?php echo $row['category_name']; ?></td>
            <td align="right"><?php echo number_format(abs($row['balance']), 2); ?></td>
        </tr>
    <?php endwhile; ?>
    <tr>
        <td><strong>Total Assets</strong></td>
        <td align="right"><strong><?php echo number_format($total_assets, 2); ?></strong></td>
    </tr>

    <!-- Liabilities Section -->
    <tr>
        <th colspan="2">LIABILITIES</th>
    </tr>
    <?php 
    $current_head = '';
    while ($row = $liabilities->fetch_assoc()):
        $total_liabilities += $row['balance'];
        if ($current_head != $row['head_name']):
            $current_head = $row['head_name'];
    ?>
        <tr>
            <td colspan="2"><strong><?php echo $row['head_name']; ?></strong></td>
        </tr>
    <?php endif; ?>
        <tr>
            <td><?php echo $row['category_name']; ?></td>
            <td align="right"><?php echo number_format(abs($row['balance']), 2); ?></td>
        </tr>
    <?php endwhile; ?>
    <tr>
        <td><strong>Total Liabilities</strong></td>
        <td align="right"><strong><?php echo number_format($total_liabilities, 2); ?></strong></td>
    </tr>

    <!-- Equity Section -->
    <tr>
        <th colspan="2">EQUITY</th>
    </tr>
    <?php 
    $current_head = '';
    while ($row = $equity->fetch_assoc()):
        $total_equity += $row['balance'];
        if ($current_head != $row['head_name']):
            $current_head = $row['head_name'];
    ?>
        <tr>
            <td colspan="2"><strong><?php echo $row['head_name']; ?></strong></td>
        </tr>
    <?php endif; ?>
        <tr>
            <td><?php echo $row['category_name']; ?></td>
            <td align="right"><?php echo number_format(abs($row['balance']), 2); ?></td>
        </tr>
    <?php endwhile; ?>
    <tr>
        <td><strong>Total Equity</strong></td>
        <td align="right"><strong><?php echo number_format($total_equity, 2); ?></strong></td>
    </tr>

    <!-- Final Total -->
    <tr>
        <td><strong>Total Liabilities and Equity</strong></td>
        <td align="right"><strong><?php echo number_format($total_liabilities + $total_equity, 2); ?></strong></td>
    </tr>
</table> 