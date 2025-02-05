<?php
require_once 'session.php';
require_once 'db.php';

if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    
    $query = "SELECT id, name FROM account_subcategories WHERE category_id = ? ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    echo '<option value="">Select Sub Category</option>';
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
    }
} 