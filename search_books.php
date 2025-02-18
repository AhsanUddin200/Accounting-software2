<?php
session_start();
require_once 'db.php';

// Check if super admin
$is_super_admin = ($_SESSION['username'] === 'saim' || 
                   $_SESSION['username'] === 'admin' || 
                   empty($_SESSION['cost_center_id']));

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';
$author = isset($_GET['author']) ? $_GET['author'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$cost_center = isset($_GET['cost_center']) ? $_GET['cost_center'] : '';

// Build query based on filters
$query = "SELECT lb.*, cc.name as cost_center_name 
          FROM library_books lb 
          LEFT JOIN cost_centers cc ON lb.cost_center_id = cc.id 
          WHERE 1=1";

// Add cost center filtering
if (!$is_super_admin) {
    $query .= " AND lb.cost_center_id = " . intval($_SESSION['cost_center_id']);
} elseif (!empty($cost_center)) {
    $query .= " AND lb.cost_center_id = ?";
}

if (!empty($search)) {
    $search = "%$search%";
    $query .= " AND (lb.title LIKE ? OR lb.book_number LIKE ?)";
}

if (!empty($author)) {
    $author = "%$author%";
    $query .= " AND lb.author LIKE ?";
}

if ($status !== 'all') {
    $query .= " AND lb.status = ?";
}

$query .= " ORDER BY lb.book_number";

// Prepare and execute query
$stmt = $conn->prepare($query);

// Build parameter array and types string
$params = array();
$types = '';

if ($is_super_admin && !empty($cost_center)) {
    $params[] = $cost_center;
    $types .= 'i';
}

if (!empty($search)) {
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if (!empty($author)) {
    $params[] = $author;
    $types .= 's';
}

if ($status !== 'all') {
    $params[] = $status;
    $types .= 's';
}

// Bind parameters if any
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Get cost center name for regular admin
$cost_center_name = "";
if (!$is_super_admin) {
    $cc_query = "SELECT name FROM cost_centers WHERE id = ?";
    $cc_stmt = $conn->prepare($cc_query);
    $cc_stmt->bind_param('i', $_SESSION['cost_center_id']);
    $cc_stmt->execute();
    $cc_result = $cc_stmt->get_result();
    if ($row = $cc_result->fetch_assoc()) {
        $cost_center_name = $row['name'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $cost_center_name ? "$cost_center_name - " : ""; ?>Search Books - Library System</title>
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
                <?php if (!$is_super_admin): ?>
                    - <?php echo htmlspecialchars($cost_center_name); ?>
                <?php endif; ?>
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
                <?php if ($is_super_admin): ?>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-building"></i></span>
                        <select name="cost_center" class="form-select">
                            <option value="">All Cost Centers</option>
                            <?php
                            $centers_query = "SELECT id, name FROM cost_centers ORDER BY name";
                            $centers = $conn->query($centers_query);
                            while ($center = $centers->fetch_assoc()):
                                $selected = ($cost_center == $center['id']) ? 'selected' : '';
                            ?>
                                <option value="<?php echo $center['id']; ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($center['name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" class="form-control" name="search" 
                               placeholder="Search by title or ISBN..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-2">
                    <input type="text" class="form-control" name="author" 
                           placeholder="Search by author..."
                           value="<?php echo htmlspecialchars($author); ?>">
                </div>
                <div class="col-md-2">
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
                                    <?php if ($is_super_admin): ?>
                                    <br><strong>Cost Center:</strong> <?php echo htmlspecialchars($book['cost_center_name']); ?>
                                    <?php endif; ?>
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