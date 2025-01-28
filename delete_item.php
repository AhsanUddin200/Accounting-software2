<?php
require_once 'db.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

try {
    $id = intval($_POST['id']);

    // Check if item is being used in any transactions
    $check_query = "SELECT 
        (SELECT COUNT(*) FROM po_items WHERE item_code = (SELECT item_code FROM items WHERE id = ?)) +
        (SELECT COUNT(*) FROM grn_items WHERE item_code = (SELECT item_code FROM items WHERE id = ?)) +
        (SELECT COUNT(*) FROM mr_items WHERE item_code = (SELECT item_code FROM items WHERE id = ?)) as usage_count";
    
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param('iii', $id, $id, $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row['usage_count'] > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'This item cannot be deleted as it is being used in transactions'
        ]);
        exit;
    }

    // Delete the item
    $query = "DELETE FROM items WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 