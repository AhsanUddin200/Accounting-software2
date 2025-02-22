<?php
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

// Show all categories for everyone
$categories_query = "SELECT DISTINCT
    ah.id as head_id,
    ah.name as head_name,
    ac.id as category_id,
    ac.name as category_name,
    acs.id as subcategory_id,
    acs.name as subcategory_name
    FROM accounting_heads ah
    LEFT JOIN account_categories ac ON ac.head_id = ah.id
    LEFT JOIN account_subcategories acs ON acs.category_id = ac.id
    WHERE ac.id IS NOT NULL
    ORDER BY ah.display_order, ac.name, acs.name";

// For debugging
error_log("Categories Query: " . $categories_query);
$categories = $conn->query($categories_query);
if (!$categories) {
    error_log("Query Error: " . $conn->error);
}

// Debug the results
while ($row = $categories->fetch_assoc()) {
    error_log("Category Row: " . print_r($row, true));
}
$categories->data_seek(0); // Reset the pointer after debugging

// Main ledger query for selected category
if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    
    // First get the category and head info
    $category_info_query = "SELECT 
        ac.id as category_id,
        ac.name as category_name,
        ah.id as head_id,
        ah.name as head_name
        FROM account_categories ac
        JOIN accounting_heads ah ON ac.head_id = ah.id
        WHERE ac.id = ?";
    
    $cat_stmt = $conn->prepare($category_info_query);
    $cat_stmt->bind_param("i", $category_id);
    $cat_stmt->execute();
    $category_info = $cat_stmt->get_result()->fetch_assoc();
    
    // Check if super admin
    $is_super_admin = ($_SESSION['username'] === 'saim' || 
                       $_SESSION['username'] === 'admin' || 
                       empty($_SESSION['cost_center_id']));

    // Ledger details query
    $query = "SELECT 
        l.ledger_code,
        l.date,
        l.description,
        l.debit,
        l.credit,
        t.voucher_number,
        t.type as transaction_type,
        ah.name as head_name,
        ac.name as category_name,
        cc.code as cost_center_code,
        cc.name as cost_center_name
        FROM ledgers l
        JOIN transactions t ON l.transaction_id = t.id
        JOIN accounting_heads ah ON t.head_id = ah.id
        JOIN account_categories ac ON t.category_id = ac.id
        LEFT JOIN cost_centers cc ON t.cost_center_id = cc.id
        WHERE t.category_id = ?";

    // Add cost center restriction for non-super admins
    if (!$is_super_admin) {
        $query .= " AND t.cost_center_id = " . $_SESSION['cost_center_id'];
    }

    // Add date filters if provided
    if (!empty($_GET['from_date'])) {
        $query .= " AND l.date >= '" . $conn->real_escape_string($_GET['from_date']) . "'";
    }
    if (!empty($_GET['to_date'])) {
        $query .= " AND l.date <= '" . $conn->real_escape_string($_GET['to_date']) . "'";
    }

    // Add ledger code range filters
    if (!empty($_GET['from_code'])) {
        $query .= " AND l.ledger_code >= '" . $conn->real_escape_string($_GET['from_code']) . "'";
    }
    if (!empty($_GET['to_code'])) {
        $query .= " AND l.ledger_code <= '" . $conn->real_escape_string($_GET['to_code']) . "'";
    }

    // Add account type filter
    if (!empty($_GET['account_type'])) {
        $query .= " AND ah.name = '" . $conn->real_escape_string($_GET['account_type']) . "'";
    }

    // Add cost center filter
    if (!empty($_GET['cost_center'])) {
        $query .= " AND t.cost_center_id = " . intval($_GET['cost_center']);
    }

    // Change ORDER BY to ascending order
    $query .= " ORDER BY l.date ASC, l.id ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>General Ledger | Accounting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container-fluid py-4">
        <!-- List of Available Ledgers -->
        <?php if (!isset($_GET['category_id'])): ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2 class="mb-0">General Ledgers</h2>
                <div>
                    <a href="financial_reports.php" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i>Back to Reports
                    </a>
                </div>
            </div>
            <div class="row">
                <?php
                $current_head = '';
                $current_category = '';
                while($cat = $categories->fetch_assoc()):
                    if($current_head != $cat['head_name']):
                        if($current_head != '') echo '</div></div></div>';
                        $current_head = $cat['head_name'];
                        $current_category = '';
                ?>
                    <div class="col-md-4 mb-4">
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><?php echo htmlspecialchars($cat['head_name']); ?></h5>
                            </div>
                            <div class="list-group list-group-flush">
                <?php endif; ?>
                
                <?php if($current_category != $cat['category_name']): ?>
                    <a href="?category_id=<?php echo $cat['category_id']; ?>" 
                       class="list-group-item list-group-item-action fw-bold">
                        <?php echo htmlspecialchars($cat['category_name']); ?>
                    </a>
                    <?php $current_category = $cat['category_name']; ?>
                <?php endif; ?>
                
                <?php if($cat['subcategory_id']): ?>
                    <a href="?category_id=<?php echo $cat['category_id']; ?>&subcategory_id=<?php echo $cat['subcategory_id']; ?>" 
                       class="list-group-item list-group-item-action ps-4">
                        → <?php echo htmlspecialchars($cat['subcategory_name']); ?>
                    </a>
                <?php endif; ?>
                
                <?php endwhile; ?>
                <?php if($current_head != '') echo '</div></div></div>'; ?>
            </div>

        <!-- Individual Ledger View -->
        <?php else: ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="mb-0"><?php echo htmlspecialchars($category_info['head_name']); ?> - <?php echo htmlspecialchars($category_info['category_name']); ?></h3>
                    <p class="text-muted mb-0">Ledger Entries</p>
                </div>
                <div class="btn-group gap-2">
                    <a href="financial_reports.php" class="btn btn-info">
                        <i class="fas fa-chart-bar me-2"></i>Back to Reports
                    </a>
                    <a href="view_ledgers.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Ledgers
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print me-2"></i>Print Ledger
                    </button>
                </div>
            </div>

            <!-- Date Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="category_id" value="<?php echo $_GET['category_id']; ?>">
                        
                        <!-- Date Range -->
                        <div class="col-md-3">
                            <label class="form-label">From Date</label>
                            <input type="date" name="from_date" class="form-control" 
                                value="<?php echo $_GET['from_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Date</label>
                            <input type="date" name="to_date" class="form-control" 
                                value="<?php echo $_GET['to_date'] ?? ''; ?>">
                        </div>

                        <!-- Ledger Code Range -->
                        <div class="col-md-3">
                            <label class="form-label">From Ledger Code</label>
                            <input type="text" name="from_code" class="form-control" 
                                value="<?php echo $_GET['from_code'] ?? ''; ?>"
                                placeholder="e.g., IN0001">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">To Ledger Code</label>
                            <input type="text" name="to_code" class="form-control" 
                                value="<?php echo $_GET['to_code'] ?? ''; ?>"
                                placeholder="e.g., IN9999">
                        </div>

                        <!-- Account Type Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Account Type</label>
                            <select name="account_type" class="form-select">
                                <option value="">All Account Types</option>
                                <option value="Assets" <?php echo ($_GET['account_type'] ?? '') === 'Assets' ? 'selected' : ''; ?>>Assets</option>
                                <option value="Liabilities" <?php echo ($_GET['account_type'] ?? '') === 'Liabilities' ? 'selected' : ''; ?>>Liabilities</option>
                                <option value="Equities" <?php echo ($_GET['account_type'] ?? '') === 'Equities' ? 'selected' : ''; ?>>Equities</option>
                                <option value="Income" <?php echo ($_GET['account_type'] ?? '') === 'Income' ? 'selected' : ''; ?>>Income</option>
                                <option value="Expenses" <?php echo ($_GET['account_type'] ?? '') === 'Expenses' ? 'selected' : ''; ?>>Expenses</option>
                            </select>
                        </div>
                          
                        <div class="col-md-3">
                            <label for="fromVoucher" class="form-label">From Voucher #</label>
                            <input type="text" class="form-control" id="fromVoucher" name="fromVoucher" placeholder="e.g., INC202501001">
                        </div>
                        <div class="col-md-3">
                            <label for="toVoucher" class="form-label">To Voucher #</label>
                            <input type="text" class="form-control" id="toVoucher" name="toVoucher" placeholder="e.g., INC202501999">
                        </div>

                        <!-- Cost Center Filter -->
                        <div class="col-md-3">
                            <label class="form-label">Cost Center</label>
                            <select name="cost_center" class="form-select">
                                <option value="">All Cost Centers</option>
                                <?php
                                $cost_centers_query = "SELECT id, code, name FROM cost_centers ORDER BY name";
                                $cost_centers = $conn->query($cost_centers_query);
                                while ($center = $cost_centers->fetch_assoc()):
                                ?>
                                    <option value="<?php echo $center['id']; ?>" 
                                        <?php echo (isset($_GET['cost_center']) && $_GET['cost_center'] == $center['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($center['code'] . ' - ' . $center['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-12 d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary me-2">Apply Filters</button>
                            <a href="?category_id=<?php echo $_GET['category_id']; ?>" class="btn btn-secondary">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>LEDGER CODE</th>
                                    <th>DATE</th>
                                    <th>VOUCHER NO.</th>
                                    <th>COST CENTER</th>
                                    <th>DESCRIPTION</th>
                                    <th class="text-end">DEBIT (PKR)</th>
                                    <th class="text-end">CREDIT (PKR)</th>
                                    <th class="text-end">BALANCE (PKR)</th>
                                    <th>ACTION</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $result->data_seek(0); // Reset pointer to start
                                $total_debit = 0;
                                $total_credit = 0;
                                $running_balance = 0;

                                while($row = $result->fetch_assoc()): 
                                    // Calculate running balance (now in ascending order)
                                    $total_debit += $row['debit'];
                                    $total_credit += $row['credit'];
                                    $running_balance += ($row['debit'] - $row['credit']);
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['ledger_code']); ?></td>
                                        <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($row['voucher_number']); ?></td>
                                        <td><?php echo htmlspecialchars($row['cost_center_code'] . ' - ' . $row['cost_center_name']); ?></td>
                                        <td><?php echo htmlspecialchars($row['description']); ?></td>
                                        <td class="text-end">
                                            <?php echo $row['debit'] ? formatCurrency($row['debit']) : '-'; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php echo $row['credit'] ? formatCurrency($row['credit']) : '-'; ?>
                                        </td>
                                        <td class="text-end"><?php echo formatCurrency($running_balance); ?></td>
                                        <td>
                                            <a href="generate_voucher.php?voucher_number=<?php echo urlencode($row['voucher_number']); ?>" 
                                               class="btn btn-sm btn-primary" 
                                               target="_blank">
                                                <i class="fas fa-file-alt"></i> View Voucher
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr class="fw-bold">
                                    <td colspan="4" class="text-end">TOTAL</td>
                                    <td class="text-end"><?php echo formatCurrency($total_debit); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($total_credit); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($running_balance); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>