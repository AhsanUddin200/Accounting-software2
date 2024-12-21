<?php
// Previous PHP code remains the same until the HTML part
require_once 'session.php';
require_once 'db.php';
require_once 'functions.php';

if ($_SESSION['role'] != 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die("Invalid CSRF token.");
    }

    $description = trim($_POST['description']);
    $amount = trim($_POST['amount']);
    $date = trim($_POST['date']);
    $admin_id = $_SESSION['user_id'];

    if (empty($description) || empty($amount) || empty($date)) {
        $error = "All fields are required.";
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = "Amount must be a positive number.";
    } else {
        $stmt = $conn->prepare("INSERT INTO transactions (type, description, amount, date, user_id, added_by_admin_id) VALUES ('income', ?, ?, ?, NULL, ?)");
        if ($stmt) {
            $stmt->bind_param("sdsi", $description, $amount, $date, $admin_id);
            if ($stmt->execute()) {
                log_action($conn, $admin_id, 'Added Income', "Description: $description, Amount: $amount, Date: $date");
                $success = "Income added successfully.";
            } else {
                $error = "Failed to add income: (" . $stmt->errno . ") " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Prepare failed: (" . $conn->errno . ") " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Income | Accounting Software</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
        }
        
        body {
            background-color: #f8fafc;
        }
        
        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-5px);
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            padding: 0.75rem 1.5rem;
            font-weight: 500;
        }
        
        .btn-primary:hover {
            background-color: var(--secondary-color);
            border-color: var(--secondary-color);
        }
        
        .nav-link {
            padding: 0.75rem 1rem;
            font-weight: 500;
        }
        
        .nav-link.active {
            color: var(--primary-color) !important;
            background-color: rgba(37, 99, 235, 0.1);
            border-radius: 0.375rem;
        }
        
        .form-label {
            font-weight: 500;
            color: #4b5563;
        }
        
        .alert {
            border: none;
            border-radius: 0.5rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-calculator me-2"></i>
                Financial Management System

            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin_dashboard.php">
                                <i class="fas fa-tachometer-alt me-1"></i> Admin Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="view_audit_logs.php">
                                <i class="fas fa-history me-1"></i> Audit Logs
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="add_income.php">
                                <i class="fas fa-plus-circle me-1"></i> Add Income
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-chart-bar me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt me-1"></i> 
                            Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-white py-3">
                        <h4 class="card-title mb-0">
                            <i class="fas fa-plus-circle text-primary me-2"></i>
                            Add New Income
                        </h4>
                    </div>
                    <div class="card-body p-4">
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger d-flex align-items-center" role="alert">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <i class="fas fa-check-circle me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="add_income.php" class="needs-validation" novalidate>
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                            
                            <div class="mb-4">
                                <label for="description" class="form-label">
                                    <i class="fas fa-file-alt text-primary me-2"></i>Description
                                </label>
                                <input type="text" class="form-control form-control-lg" id="description" 
                                    name="description" required 
                                    value="<?php echo htmlspecialchars($_POST['description'] ?? ''); ?>"
                                    placeholder="Enter income description">
                                <div class="invalid-feedback">
                                    Please provide a description.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="amount" class="form-label">
                                    <i class="fas fa-dollar-sign text-primary me-2"></i>Amount
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control form-control-lg" 
                                        id="amount" name="amount" required 
                                        value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>"
                                        placeholder="0.00">
                                </div>
                                <div class="invalid-feedback">
                                    Please enter a valid amount.
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="date" class="form-label">
                                    <i class="fas fa-calendar text-primary me-2"></i>Date
                                </label>
                                <input type="date" class="form-control form-control-lg" id="date" 
                                    name="date" required 
                                    value="<?php echo htmlspecialchars($_POST['date'] ?? date('Y-m-d')); ?>">
                                <div class="invalid-feedback">
                                    Please select a date.
                                </div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save me-2"></i>Add Income
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Form Validation Script -->
    <script>
        (() => {
            'use strict'
            const forms = document.querySelectorAll('.needs-validation')
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault()
                        event.stopPropagation()
                    }
                    form.classList.add('was-validated')
                }, false)
            })
        })()
    </script>
</body>
</html>