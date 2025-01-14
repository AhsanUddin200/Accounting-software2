<?php
require_once 'db.php';

// Main Accounting Heads
$main_heads = [
    'Asset' => ['type' => ['income', 'expense']],
    'Liabilities' => ['type' => ['income', 'expense']],
    'Equities' => ['type' => ['income', 'expense']],
    'Income' => ['type' => ['income']],
    'Expenses' => ['type' => ['expense']]
];

// Income Categories
$income_categories = [
    'Tuition Fees',
    'Admission Fees',
    'Library Fees',
    'Transport Fees',
    'Examination Fees',
    'Activity Fees',
    'Development Fees',
    'Donations and Grants',
    'Uniform Fees',
    'Summer Camp Fees',
    'Workshop/Training Fees',
    'Miscellaneous Income'
];

// First, insert main heads
foreach ($main_heads as $head_name => $properties) {
    $stmt = $conn->prepare("INSERT INTO accounting_heads (name) VALUES (?)");
    $stmt->bind_param("s", $head_name);
    $stmt->execute();
    $head_id = $conn->insert_id;
    
    // For Income head, add all income categories
    if ($head_name == 'Income') {
        foreach ($income_categories as $category) {
            $stmt = $conn->prepare("INSERT INTO account_categories (name, head_id) VALUES (?, ?)");
            $stmt->bind_param("si", $category, $head_id);
            $stmt->execute();
        }
    }
}

echo "Categories setup completed!";
?>
