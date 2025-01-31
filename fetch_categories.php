<?php
require_once 'db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if(isset($_POST['head_id'])) {
    $head_id = $_POST['head_id'];
    
    // Log received head_id
    error_log("Fetching categories for head_id: " . $head_id);
    
    $sql = "SELECT id, name FROM account_categories WHERE head_id = ? ORDER BY name";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Log number of categories found
    error_log("Found " . $result->num_rows . " categories");
    
    echo '<option value="">Select Category</option>';
    while($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
    }
} else {
    error_log("No head_id received in POST request");
    echo '<option value="">Error: No head selected</option>';
}
?> 