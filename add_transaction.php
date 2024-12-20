<?php
// add_transaction.php
session_start(); // Ensure the session is started
require 'db.php'; // Make sure this file connects to your database

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); // Redirect to login page if not logged in
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Using prepared statements for better security
    $amount = $_POST['amount'];
    $type = $_POST['type'];
    $category_id = $_POST['category'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $user_id = $_SESSION['user_id'];

    // Validate inputs
    if (empty($amount) || empty($type) || empty($category_id) || empty($date)) {
        $error = "Please fill in all required fields.";
    } else {
        // Prepare the SQL statement
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, amount, type, category_id, date, description) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("idsiss", $user_id, $amount, $type, $category_id, $date, $description);
            if ($stmt->execute()) {
                $success = "Transaction added successfully.";
            } else {
                $error = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparing statement: " . $conn->error;
        }
    }
}

// Fetch categories
$cat_query = "SELECT * FROM categories ORDER BY name ASC";
$cat_result = $conn->query($cat_query);
if (!$cat_result) {
    die("Error fetching categories: " . $conn->error);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Transaction</title>
    <style>
        /* Basic styling */
        body { font-family: Arial, sans-serif; background-color: #f4f4f4; }
        .container { width: 500px; margin: 50px auto; padding: 20px; background: #fff; border-radius: 5px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
        h2 { text-align: center; }
        input, select, textarea { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ccc; border-radius: 4px; }
        input[type="submit"] { background: #5bc0de; border: none; color: #fff; cursor: pointer; }
        input[type="submit"]:hover { background: #31b0d5; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
        .back-button { margin-top: 20px; text-align: center; }
        .back-button button { padding: 10px 20px; background-color: #5bc0de; border: none; color: #fff; border-radius: 4px; cursor: pointer; }
        .back-button button:hover { background-color: #31b0d5; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Transaction</h2>
        
        <!-- Display Success or Error Messages -->
        <?php if (isset($success)): ?>
            <div class="message success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if (isset($error)): ?>
            <div class="message error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="add_transaction.php">
            <label for="amount">Amount<span style="color: red;">*</span></label>
            <input type="number" step="0.01" id="amount" name="amount" placeholder="Amount" required value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>">

            <label for="type">Type<span style="color: red;">*</span></label>
            <select id="type" name="type" required>
                <option value="">Select Type</option>
                <option value="income" <?php echo (isset($_POST['type']) && $_POST['type'] == 'income') ? 'selected' : ''; ?>>Income</option>
                <option value="expense" <?php echo (isset($_POST['type']) && $_POST['type'] == 'expense') ? 'selected' : ''; ?>>Expense</option>
            </select>

            <label for="category">Category<span style="color: red;">*</span></label>
            <select id="category" name="category" required>
                <option value="">Select Category</option>
                <?php
                    while($row = $cat_result->fetch_assoc()) {
                        $selected = (isset($_POST['category']) && $_POST['category'] == $row['id']) ? 'selected' : '';
                        echo "<option value='" . htmlspecialchars($row['id']) . "' $selected>" . htmlspecialchars($row['name']) . "</option>";
                    }
                ?>
            </select>

            <label for="date">Date<span style="color: red;">*</span></label>
            <input type="date" id="date" name="date" required value="<?php echo isset($_POST['date']) ? htmlspecialchars($_POST['date']) : ''; ?>">

            <label for="description">Description</label>
            <textarea id="description" name="description" placeholder="Description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>

            <input type="submit" value="Add Transaction">
        </form>

        <!-- Back Button Section -->
        <div class="back-button">
            <a href="user_dashboard.php">
                <button type="button">Back to Dashboard</button>
            </a>
        </div>
    </div>
</body>
</html>
