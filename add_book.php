<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Get POST data
    $book_number = $_POST['book_number'];
    $title = $_POST['title'];
    $author = $_POST['author'];
    $shelf_number = $_POST['shelf_number'];
    $school = $_POST['school'];
    
    // Log received data
    error_log("Received data: " . print_r($_POST, true));

    // Handle image upload
    $image_path = '';
    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] == 0) {
        $upload_dir = 'uploads/books/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = time() . '_' . $_FILES['book_image']['name'];
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['book_image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }

    // Insert into database using prepared statement
    $sql = "INSERT INTO library_books (book_number, title, author, shelf_number, school, book_image, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'available')";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("ssssss", 
        $book_number,
        $title,
        $author,
        $shelf_number,
        $school,
        $image_path
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Book added successfully'
        ]);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    error_log("Error in add_book.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?> 