<?php
require_once 'session.php';
require_once 'db.php';

if (isset($_GET['head_id'])) {
    $head_id = intval($_GET['head_id']);
    
    $query = "SELECT id, name FROM account_categories WHERE head_id = ? ORDER BY name";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $head_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
    }
}
?>