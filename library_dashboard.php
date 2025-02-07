<?php
session_start();
require_once 'session.php';
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get total books count
$query = "SELECT COUNT(*) as total FROM library_books";
$result = $conn->query($query);
$total_books = $result->fetch_assoc()['total'];

// Get issued books count
$query = "SELECT COUNT(*) as issued FROM library_books WHERE status = 'issued'";
$result = $conn->query($query);
$issued_books = $result->fetch_assoc()['issued'];

// Get overdue books count
$query = "SELECT COUNT(*) as overdue FROM book_issues WHERE status = 'overdue'";
$result = $conn->query($query);
$overdue_books = $result->fetch_assoc()['overdue'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management - FMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .summary-card {
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1rem;
        }
        .action-card {
            text-align: center;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .action-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="summary-card bg-primary text-white">
                    <h3><?php echo $total_books; ?></h3>
                    <p class="mb-0">Total Books</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card bg-success text-white">
                    <h3><?php echo $issued_books; ?></h3>
                    <p class="mb-0">Currently Issued</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card bg-danger text-white">
                    <h3><?php echo $overdue_books; ?></h3>
                    <p class="mb-0">Overdue Books</p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="action-card">
                    <i class="fas fa-book fa-3x mb-3 text-primary"></i>
                    <h4>Manage Books</h4>
                    <p>Add, edit, or remove books from inventory</p>
                    <a href="manage_books.php" class="btn btn-primary">
                        <i class="fas fa-arrow-right me-2"></i>Go to Books
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="action-card">
                    <i class="fas fa-paper-plane fa-3x mb-3 text-success"></i>
                    <h4>Issue Book</h4>
                    <p>Issue books to students</p>
                    <a href="issue_book.php" class="btn btn-success">
                        <i class="fas fa-plus me-2"></i>Issue New Book
                    </a>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="action-card">
                    <i class="fas fa-list fa-3x mb-3 text-info"></i>
                    <h4>View Issues</h4>
                    <p>View all issued books and manage returns</p>
                    <a href="view_issued_books.php" class="btn btn-info">
                        <i class="fas fa-eye me-2"></i>View All
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>