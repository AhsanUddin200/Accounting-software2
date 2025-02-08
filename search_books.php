<?php
session_start();
require_once 'db.php';

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$author = isset($_GET['author']) ? $_GET['author'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query based on filters
$query = "SELECT * FROM library_books WHERE 1=1";

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (title LIKE ? OR book_number LIKE ?)";
}

if (!empty($author)) {
    $author = "%$author%";
    $query .= " AND author LIKE ?";
}

if ($status !== 'all') {
    $query .= " AND status = ?";
}

$query .= " ORDER BY book_number";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Bind parameters based on filters
if (!empty($search) && !empty($author) && $status !== 'all') {
    $stmt->bind_param('ssss', $search, $search, $author, $status);
} elseif (!empty($search) && !empty($author)) {
    $stmt->bind_param('sss', $search, $search, $author);
} elseif (!empty($search) && $status !== 'all') {
    $stmt->bind_param('sss', $search, $search, $status);
} elseif (!empty($author) && $status !== 'all') {
    $stmt->bind_param('ss', $author, $status);
} elseif (!empty($search)) {
    $stmt->bind_param('ss', $search, $search);
} elseif (!empty($author)) {
    $stmt->bind_param('s', $author);
} elseif ($status !== 'all') {
    $stmt->bind_param('s', $status);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Books - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .search-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .book-card {
            transition: transform 0.2s;
        }
        .book-card:hover {
            transform: translateY(-5px);
        }
        .book-cover {
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-book me-2"></i>Library Book Search
            </a>
            <?php if (isset($_SESSION['role'])): ?>
                <div class="ms-auto">
                    <a href="library_dashboard.php" class="nav-link text-white">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="search-container">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by title or ISBN..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-3">
                    <input type="text" class="form-control" name="author" 
                           placeholder="Search by author..."
                           value="<?php echo htmlspecialchars($author); ?>">
                </div>
                <div class="col-md-3">
                    <select class="form-select" name="status">
                        <option value="all">All Status</option>
                        <option value="available" <?php echo $status === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="issued" <?php echo $status === 'issued' ? 'selected' : ''; ?>>Issued</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-1"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <div class="row row-cols-1 row-cols-md-3 g-4">
            <?php if ($result->num_rows > 0): ?>
                <?php while($book = $result->fetch_assoc()): ?>
                    <div class="col">
                        <div class="card h-100 book-card">
                            <img src="<?php echo !empty($book['book_image']) ? htmlspecialchars($book['book_image']) : 'assets/images/no-book-cover.png'; ?>" 
                                 class="card-img-top book-cover" alt="Book Cover">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($book['title']); ?></h5>
                                <p class="card-text">
                                    <strong>ISBN:</strong> <?php echo htmlspecialchars($book['book_number']); ?><br>
                                    <strong>Author:</strong> <?php echo htmlspecialchars($book['author']); ?><br>
                                    <strong>Shelf:</strong> <?php echo htmlspecialchars($book['shelf_number']); ?><br>
                                    <strong>School:</strong> <?php echo htmlspecialchars($book['school']); ?>
                                </p>
                                <span class="badge bg-<?php echo $book['status'] === 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($book['status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No books found matching your search criteria.
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 