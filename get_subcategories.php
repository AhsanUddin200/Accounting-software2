<?php
require 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

if (isset($_GET['head_id'])) {
    $head_id = intval($_GET['head_id']);
    
    $stmt = $conn->prepare("SELECT id, name FROM account_categories WHERE head_id = ? ORDER BY name");
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $result = $stmt->get_result();

    echo "<option value=''>Select Sub Category</option>";
    while ($row = $result->fetch_assoc()) {
        echo "<option value='" . $row['id'] . "'>" . htmlspecialchars($row['name']) . "</option>";
    }
} 