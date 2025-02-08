<?php
session_start();
require_once 'session.php';
require_once 'db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get statistics
$query = "SELECT COUNT(*) as total FROM library_books";
$result = $conn->query($query);
$total_books = $result->fetch_assoc()['total'];

$query = "SELECT COUNT(*) as issued FROM library_books WHERE status = 'issued'";
$result = $conn->query($query);
$issued_books = $result->fetch_assoc()['issued'];

$query = "SELECT COUNT(*) as overdue FROM book_issues WHERE status = 'overdue'";
$result = $conn->query($query);
$overdue_books = $result->fetch_assoc()['overdue'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Dashboard - FMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .stats-card {
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s;
            border: none;
            height: 100%;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        .stats-number {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        .stats-label {
            font-size: 1.4rem;
            color: #fffff;
        }
        .action-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: transform 0.3s, box-shadow 0.3s;
            border: none;
            height: 100%;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 1.5rem;
        }
        .action-title {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            color: #2d3748;
        }
        .action-description {
            color: #718096;
            margin-bottom: 1.5rem;
        }
        .btn-custom {
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="container py-5">
        <!-- Statistics Section -->
        <div class="row mb-5">
            <div class="col-md-4 mb-4">
                <div class="stats-card bg-primary text-white">
                    <div class="stats-number"><?php echo $total_books; ?></div>
                    <div class="stats-label">Total Books</div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stats-card bg-success text-white">
                    <div class="stats-number"><?php echo $issued_books; ?></div>
                    <div class="stats-label">Currently Issued</div>
                </div>
            </div>
            <div class="col-md-4 mb-4">
                <div class="stats-card bg-danger text-white">
                    <div class="stats-number"><?php echo $overdue_books; ?></div>
                    <div class="stats-label">Overdue Books</div>
                </div>
            </div>
        </div>

        <!-- Actions Section -->
        <div class="row">
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="action-card">
                    <i class="fas fa-search action-icon text-info"></i>
                    <h3 class="action-title">Search Books</h3>
                    <p class="action-description">Search for books in the library</p>
                    <a href="search_books.php" class="btn btn-info btn-custom w-100">
                        <i class="fas fa-search me-2"></i>Search Books
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="action-card">
                    <i class="fas fa-book action-icon text-primary"></i>
                    <h3 class="action-title">Manage Books</h3>
                    <p class="action-description">Add, edit, or remove books</p>
                    <a href="manage_books.php" class="btn btn-primary btn-custom w-100">
                        <i class="fas fa-cog me-2"></i>Go to Books
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="action-card">
                    <i class="fas fa-paper-plane action-icon text-success"></i>
                    <h3 class="action-title">Issue Book</h3>
                    <p class="action-description">Issue books to students</p>
                    <a href="issue_book.php" class="btn btn-success btn-custom w-100">
                        <i class="fas fa-plus me-2"></i>Issue New
                    </a>
                </div>
            </div>
            <div class="col-md-6 col-lg-3 mb-4">
                <div class="action-card">
                    <i class="fas fa-list action-icon text-warning"></i>
                    <h3 class="action-title">View Issues</h3>
                    <p class="action-description">Manage issued books</p>
                    <a href="view_issued_books.php" class="btn btn-warning btn-custom w-100">
                        <i class="fas fa-eye me-2"></i>View All
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>