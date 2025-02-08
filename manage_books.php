<?php
session_start();
require_once 'db.php';
require_once 'session.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get statistics
$query = "SELECT 
    COUNT(*) as total_books,
    SUM(CASE WHEN status = 'issued' THEN 1 ELSE 0 END) as issued_books,
    SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available_books
FROM library_books";
$result = $conn->query($query);
$stats = $result->fetch_assoc();

// Get all books with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

$books_query = "SELECT * FROM library_books ORDER BY book_number LIMIT $offset, $per_page";
$books_result = $conn->query($books_query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Library System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }
        .summary-icon {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: #4361ee;
        }
        .status-available {
            color: #10B981;
        }
        .status-issued {
            color: #F59E0B;
        }
        .table-container {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .book-thumbnail {
            width: 50px;
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .book-thumbnail:hover {
            transform: scale(1.1);
        }
        .table td {
            vertical-align: middle;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class="fas fa-book me-2"></i>Library Management
            </a>
            <div class="ms-auto">
                <a href="library_dashboard.php" class="nav-link text-white">
                    <i class="fas fa-arrow-left me-1"></i> Back to Library Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon">
                        <i class="fas fa-books"></i>
                    </div>
                    <h5>Total Books</h5>
                    <h3><?php echo $stats['total_books']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon status-available">
                        <i class="fas fa-book"></i>
                    </div>
                    <h5>Available Books</h5>
                    <h3><?php echo $stats['available_books']; ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="summary-card">
                    <div class="summary-icon status-issued">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <h5>Issued Books</h5>
                    <h3><?php echo $stats['issued_books']; ?></h3>
                </div>
            </div>
        </div>

        <!-- Books List -->
        <div class="table-container">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h4 class="mb-0">Books Inventory</h4>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBookModal">
                    <i class="fas fa-plus me-2"></i>Add New Book
                </button>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Book Number</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Shelf Number</th>
                            <th>Status</th>
                            <th>School</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($book = $books_result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <?php if (!empty($book['book_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($book['book_image']); ?>" 
                                         alt="Book Cover" 
                                         class="book-thumbnail"
                                         onclick="showFullImage('<?php echo htmlspecialchars($book['book_image']); ?>', '<?php echo htmlspecialchars($book['title']); ?>')"
                                    >
                                <?php else: ?>
                                    <img src="assets/images/no-book-cover.png" 
                                         alt="No Cover" 
                                         class="book-thumbnail"
                                    >
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($book['book_number']); ?></td>
                            <td><?php echo htmlspecialchars($book['title']); ?></td>
                            <td><?php echo htmlspecialchars($book['author']); ?></td>
                            <td><?php echo htmlspecialchars($book['shelf_number']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo $book['status'] === 'available' ? 'success' : 'warning'; ?>">
                                    <?php echo ucfirst($book['status']); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($book['school']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary me-1" onclick="editBook(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteBook(<?php echo $book['id']; ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Book Modal -->
    <div class="modal fade" id="addBookModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Book</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="addBookForm">
                        <div class="mb-3">
                            <label class="form-label">Book Number (ISBN)*</label>
                            <input type="text" class="form-control" name="book_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Title*</label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Author</label>
                            <input type="text" class="form-control" name="author">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Shelf Number*</label>
                            <input type="text" class="form-control" name="shelf_number" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">School*</label>
                            <input type="text" class="form-control" name="school" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Book Image</label>
                            <input type="file" class="form-control" name="book_image">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="submitBookForm()">Add Book</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Image Modal for full-size view -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalTitle">Book Cover</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="fullSizeImage" src="" alt="Book Cover" class="img-fluid">
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editBook(id) {
            // Implement edit functionality
        }

        function deleteBook(id) {
            if(confirm('Are you sure you want to delete this book?')) {
                // Implement delete functionality
            }
        }

        function submitBookForm() {
            const form = document.getElementById('addBookForm');
            const formData = new FormData(form);

            // Debug log
            console.log('Submitting form...');
            
            fetch('add_book.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Book added successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error saving book. Please try again.');
            });
        }

        // Add event listener for the Add Book button
        document.addEventListener('DOMContentLoaded', function() {
            const addBookBtn = document.querySelector('#addBookModal .btn-primary');
            if (addBookBtn) {
                addBookBtn.addEventListener('click', submitBookForm);
            }
        });

        function showFullImage(imageSrc, title) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            document.getElementById('fullSizeImage').src = imageSrc;
            document.getElementById('imageModalTitle').textContent = title;
            modal.show();
        }

        // Create a default image for books without covers
        document.addEventListener('DOMContentLoaded', function() {
            const images = document.querySelectorAll('.book-thumbnail');
            images.forEach(img => {
                img.onerror = function() {
                    this.src = 'assets/images/no-book-cover.png';
                };
            });
        });
    </script>
</body>
</html> 