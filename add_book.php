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

// Check if super admin
$is_super_admin = ($_SESSION['username'] === 'saim' || 
                   $_SESSION['username'] === 'admin' || 
                   empty($_SESSION['cost_center_id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $book_number = trim($_POST['book_number']);
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $shelf_number = trim($_POST['shelf_number']);
    $school = trim($_POST['school']);
    $cost_center_id = $is_super_admin ? $_POST['cost_center_id'] : $_SESSION['cost_center_id'];

    // First check if book number already exists
    $check_query = "SELECT id FROM library_books WHERE book_number = ?";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param('s', $book_number);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows > 0) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'message' => 'Book number already exists. Please use a different book number.'
        ]);
        exit();
    }

    // Handle image upload if present
    $book_image = '';
    if (isset($_FILES['book_image']) && $_FILES['book_image']['error'] === 0) {
        $upload_dir = 'uploads/books/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['book_image']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = uniqid() . '_Screenshot ' . date('Y-m-d H.i.s') . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;

            if (move_uploaded_file($_FILES['book_image']['tmp_name'], $upload_path)) {
                $book_image = $upload_path;
            }
        }
    }

    // Insert new book
    $query = "INSERT INTO library_books (book_number, title, author, shelf_number, school, book_image, status, cost_center_id) 
              VALUES (?, ?, ?, ?, ?, ?, 'available', ?)";
    
    try {
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssssssi', 
            $book_number,
            $title,
            $author,
            $shelf_number,
            $school,
            $book_image,
            $cost_center_id
        );

        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Book added successfully']);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Error adding book: ' . $stmt->error]);
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?> 