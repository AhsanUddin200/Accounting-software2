// add_budget.php
<?php
require 'session.php';
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $category_id = $_POST['category'];
    $amount = $_POST['amount'];
    $period = $_POST['period'];

    // Validate inputs
    if (empty($category_id) || empty($amount) || empty($period)) {
        $error = "All fields are required.";
    } else {
        // Insert budget
        $stmt = $conn->prepare("INSERT INTO budgets (user_id, category_id, amount, period) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $_SESSION['user_id'], $category_id, $amount, $period);
        if ($stmt->execute()) {
            $success = "Budget set successfully.";
        } else {
            $error = "Error: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Fetch categories
$categories = [];
$result = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    die("Error fetching categories: " . $conn->error);
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Set Budget</title>
</head>
<body>
    <h2>Set Budget</h2>
    <?php if (isset($success)) echo "<p>$success</p>"; ?>
    <?php if (isset($error)) echo "<p>$error</p>"; ?>
    <form method="POST" action="add_budget.php">
        <label for="category">Category:</label>
        <select name="category" id="category" required>
            <option value="">Select Category</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
            <?php endforeach; ?>
        </select>

        <label for="amount">Budget Amount:</label>
        <input type="number" step="0.01" name="amount" id="amount" required>

        <label for="period">Period:</label>
        <select name="period" id="period" required>
            <option value="monthly">Monthly</option>
            <option value="weekly">Weekly</option>
        </select>

        <input type="submit" value="Set Budget">
    </form>
</body>
</html>
