<?php
require_once 'db.php';
require_once 'session.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Generate unique item code
    $category_code = substr($_POST['category'], 0, 3);
    $date_code = date('ym');
    
    // Get the latest sequence number for this category and month
    $pattern = $category_code . '-' . $date_code . '-%';
    $stmt = $conn->prepare("SELECT item_code FROM items WHERE item_code LIKE ? ORDER BY item_code DESC LIMIT 1");
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $last_sequence = intval(substr($row['item_code'], -4));
        $new_sequence = $last_sequence + 1;
    } else {
        $new_sequence = 1;
    }
    
    $item_code = sprintf('%s-%s-%04d', strtoupper($category_code), $date_code, $new_sequence);
    
    // Insert the new item - REMOVED location field from here
    $query = "INSERT INTO items (
        item_code, 
        name, 
        category, 
        unit, 
        minimum_quantity, 
        description, 
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssisi', 
        $item_code,
        $_POST['name'],
        $_POST['category'],
        $_POST['unit'],
        $_POST['minimum_quantity'],
        $_POST['description'],
        $_SESSION['user_id']
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Item added successfully',
            'item_code' => $item_code
        ]);
    } else {
        throw new Exception("Error adding item");
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 