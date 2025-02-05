<!-- Trial Balance -->
<div class="col-md-6 mb-4">
    <div class="report-card text-center">
        <div class="report-icon">
            <i class="fas fa-balance-scale"></i>
        </div>
        <h4>Trial Balance</h4>
        <p>View the trial balance report showing final balances of all accounts</p>
        <a href="trial_balance.php" class="btn btn-primary">View Trial Balance</a>
    </div>
</div>

<!-- Add this in the filter form section -->
<div class="row mb-3">
    <!-- Existing date filters -->
    
    <!-- Add this cost center filter -->
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
</div>

<!-- Then modify the SQL query to include cost center -->
<?php
$query = "SELECT 
    ah.name as head_name,
    ac.name as category_name,
    cc.code as cost_center_code,
    cc.name as cost_center_name,
    t.type,
    SUM(l.debit) as total_debit,
    SUM(l.credit) as total_credit
    FROM ledgers l
    JOIN transactions t ON l.transaction_id = t.id
    JOIN accounting_heads ah ON t.head_id = ah.id
    JOIN account_categories ac ON t.category_id = ac.id
    LEFT JOIN cost_centers cc ON t.cost_center_id = cc.id
    WHERE t.date BETWEEN ? AND ?";

// Add cost center filter if selected
if (!empty($_GET['cost_center'])) {
    $query .= " AND t.cost_center_id = " . intval($_GET['cost_center']);
}

$query .= " GROUP BY ah.name, ac.name, cc.code, cc.name, t.type";
?> 