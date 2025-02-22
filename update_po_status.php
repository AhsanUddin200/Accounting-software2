<?php
require_once 'db.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log all POST data
error_log('POST data received: ' . print_r($_POST, true));
error_log('Session data: ' . print_r($_SESSION, true));

// Check session
if (!isset($_SESSION['user_id'])) {
    error_log('No user_id in session');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

if (!isset($_SESSION['role'])) {
    error_log('No role in session');
    echo json_encode(['success' => false, 'message' => 'No role defined']);
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    error_log('User is not admin. Role: ' . $_SESSION['role']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['id']) || !isset($_POST['status'])) {
        error_log('Missing required fields');
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }

    $id = intval($_POST['id']);
    $status = $_POST['status'];
    $remarks = $_POST['remarks'] ?? '';

    error_log("Processing update for PO ID: $id, Status: $status, Remarks: $remarks");

    try {
        // Update the status in database
        $query = "UPDATE purchase_orders SET status = ?, remarks = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $status, $remarks, $id);
        
        error_log("Executing query: $query");
        
        if ($stmt->execute()) {
            error_log("Status updated successfully for PO ID: $id");
            echo json_encode([
                'success' => true,
                'message' => 'Status updated successfully'
            ]);
        } else {
            error_log("Database error: " . $stmt->error);
            echo json_encode([
                'success' => false,
                'message' => 'Database error: ' . $stmt->error
            ]);
        }
    } catch (Exception $e) {
        error_log("Exception: " . $e->getMessage());
        echo json_encode([
            'success' => false,
            'message' => 'Error: ' . $e->getMessage()
        ]);
    }
} else {
    error_log('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
}
?> 