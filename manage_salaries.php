<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    $successes = [];

    // CSRF Protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Invalid CSRF token.";
    } else {
        // Handle updating monthly_salary and current_month_salary
        if (isset($_POST['salaries']) && is_array($_POST['salaries'])) {
            foreach ($_POST['salaries'] as $user_id => $salary_data) {
                $monthly_salary = floatval($salary_data['monthly_salary']);
                $current_month_salary = floatval($salary_data['current_month_salary']);
                $tax_percentage = floatval($salary_data['tax_percentage'] ?? 0);
                $other_deductions = floatval($salary_data['other_deductions'] ?? 0);

                if ($monthly_salary < 0 || $current_month_salary < 0) {
                    $errors[] = "Invalid salary amount for User ID: $user_id.";
                    continue;
                }

                // Begin transaction
                $conn->begin_transaction();

                try {
                    // Update 'monthly_salary' and 'current_month_salary' in 'users' table
                    $stmt_user = $conn->prepare("UPDATE users SET monthly_salary = ?, current_month_salary = ? WHERE id = ?");
                    $stmt_user->bind_param("ddi", $monthly_salary, $current_month_salary, $user_id);
                    $stmt_user->execute();
                    $stmt_user->close();

                    // Insert into salaries table
                    $stmt_salary = $conn->prepare("INSERT INTO salaries (user_id, monthly_salary, current_month_salary, tax_percentage, other_deductions, payment_date) VALUES (?, ?, ?, ?, ?, CURRENT_DATE())");
                    $stmt_salary->bind_param("idddd", $user_id, $monthly_salary, $current_month_salary, $tax_percentage, $other_deductions);
                    $stmt_salary->execute();
                    $stmt_salary->close();

                    // Log the action
                    log_action($conn, $_SESSION['user_id'], 'Updated Salary', "Updated monthly salary to $$monthly_salary and current month salary to $$current_month_salary for User ID: $user_id.");
                    
                    // Commit transaction
                    $conn->commit();
                    $successes[] = "Salaries updated for User ID: $user_id.";

                } catch (Exception $e) {
                    // Rollback transaction on error
                    $conn->rollback();
                    $errors[] = "Failed to update salaries for User ID: $user_id. Error: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch users with their salaries
$users_result = $conn->query("SELECT id, username, monthly_salary, current_month_salary FROM users ORDER BY username ASC");
if (!$users_result) {
    die("Query failed (Fetch Users): (" . $conn->errno . ") " . $conn->error);
}

$users = [];
while ($user = $users_result->fetch_assoc()) {
    $users[] = $user;
}
$users_result->close();

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Salaries | Accounting Software</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Existing styles */
        :root {
            --primary: #2C3E50;
            --secondary: #34495E;
            --success: #27AE60;
            --danger-color: #dc2626;
            --background-color: #f8fafc;
            --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }   

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        .navbar {
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            background: linear-gradient(to right, #1e40af, #3b82f6);
        }

        .card {
            border: none;
            border-radius: 1rem;
            box-shadow: var(--card-shadow);
            transition: transform 0.2s;
        }

        .table {
            margin-bottom: 0;
        }

        .table thead th {
            background-color: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            color: #4b5563;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.875rem;
            letter-spacing: 0.05em;
        }

        .table tbody tr:hover {
            background-color: #f1f5f9;
        }

        .salary-input {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem;
            transition: all 0.2s;
            text-align: right;
        }

        .salary-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
            outline: none;
        }

        .btn-update {
            background-color: var(--primary);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 500;
            transition: all 0.2s;
        }

        .btn-update:hover {
            background-color: var(--secondary);
            transform: translateY(-1px);
        }

        .stats-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .alert {
            border-radius: 0.75rem;
            border: none;
        }

        .nav-link {
            padding: 0.75rem 1rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9) !important;
        }

        .nav-link:hover {
            color: white !important;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
        }

        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border-radius: 0.5rem;
            color: white !important;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
            <img src="https://images.crunchbase.com/image/upload/c_pad,h_170,w_170,f_auto,b_white,q_auto:eco,dpr_1/v1436326579/fv5juvmpaq9zxgnkueof.png" alt="FMS Logo" height="40" class="me-2">
                <i class=" me-2"></i>
                Financial Management System
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">
                            <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-1"></i> Users
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_transactions.php">
                            <i class="fas fa-exchange-alt me-1"></i> Transactions
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_categories.php">
                            <i class="fas fa-tags me-1"></i> Categories
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_salaries.php">
                            <i class="fas fa-money-bill-wave me-1"></i> Salaries
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="financial_reports.php">
                            <i class="fas fa-chart-bar me-1"></i> Reports
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
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">
                <i class="fas fa-money-bill-wave text-primary me-2"></i>
                Manage Salaries
            </h2>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Salaries</li>
                </ol>
            </nav>
        </div>

        <!-- Salary Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Total Employees</h6>
                    <h3 class="mb-0">
                        <i class="fas fa-users text-primary me-2"></i>
                        <?php echo count($users); ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Total Monthly Payroll</h6>
                    <h3 class="mb-0">
                    <i class="fas fa-rupee-sign text-success me-2"></i>
                        <?php 
                            $total_payroll = array_sum(array_column($users, 'monthly_salary'));
                            echo number_format($total_payroll, 0) . ' PKR';
                        ?>
                    </h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <h6 class="text-muted mb-2">Average Salary</h6>
                    <h3 class="mb-0">
                        <i class="fas fa-chart-line text-info me-2"></i>
                        <?php 
                            $avg_salary = count($users) > 0 ? $total_payroll / count($users) : 0;
                            echo number_format($avg_salary, 0); // Display without decimals
                        ?>
                    </h3>
                </div>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($successes)): ?>
            <div class="alert alert-success fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php foreach ($successes as $msg): ?>
                    <div><?php echo htmlspecialchars($msg); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php foreach ($errors as $err): ?>
                    <div><?php echo htmlspecialchars($err); ?></div>
                <?php endforeach; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Salaries Table Card -->
        <div class="card">
            <div class="card-body p-0">
                <form id="salaryForm" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th class="px-4">Employee ID</th>
                                    <th>Employee Name</th>
                                    <th class="text-end">Current Salary ($)</th>
                                    <th class="text-end px-4">New Monthly Salary ($)</th>
                                    <th class="text-end px-4">Current Month Salary ($)</th>
                                    <th class="text-end px-4">Tax Percentage (%)</th>
                                    <th class="text-end px-4">Other Deductions ($)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($users) > 0): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td class="px-4">#<?php echo htmlspecialchars($user['id']); ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-initial rounded-circle bg-light text-primary p-2 me-2">
                                                        <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                                    </div>
                                                    <?php echo htmlspecialchars($user['username']); ?>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <?php echo number_format($user['monthly_salary'], 0); ?>
                                            </td>
                                            <td class="text-end px-4">
                                                <div class="d-flex align-items-center justify-content-end gap-2">
                                                    <input type="number" 
                                                           step="0.01" 
                                                           min="0" 
                                                           name="salaries[<?php echo $user['id']; ?>][monthly_salary]" 
                                                           class="form-control salary-input text-end" 
                                                           value="<?php echo htmlspecialchars($user['monthly_salary']); ?>" 
                                                           required>
                                                    <a href="generate_payslip.php?user_id=<?php echo $user['id']; ?>" 
                                                       class="btn btn-sm btn-outline-primary" 
                                                       target="_blank" 
                                                       title="Generate Payslip">
                                                        <i class="fas fa-file-invoice"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            <td class="text-end px-4">
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       name="salaries[<?php echo $user['id']; ?>][current_month_salary]" 
                                                       class="form-control salary-input text-end" 
                                                       value="<?php echo htmlspecialchars($user['current_month_salary']); ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       max="100"
                                                       name="salaries[<?php echo $user['id']; ?>][tax_percentage]" 
                                                       class="form-control salary-input" 
                                                       value="<?php echo isset($user['tax_percentage']) ? $user['tax_percentage'] : '0.00'; ?>">
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0"
                                                       name="salaries[<?php echo $user['id']; ?>][other_deductions]" 
                                                       class="form-control salary-input" 
                                                       value="<?php echo isset($user['other_deductions']) ? $user['other_deductions'] : '0.00'; ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4">
                                            <i class="fas fa-user-slash text-muted me-2"></i>
                                            No employees found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
            </div>
            <div class="card-footer bg-light p-4">
                <div class="d-flex justify-content-end">
                    <button type="submit" form="salaryForm" class="btn btn-primary btn-update">
                        <i class="fas fa-save me-2"></i>
                        Update All Salaries
                    </button>
                </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Remove thousand separators and ensure float values
        document.querySelectorAll('.salary-input').forEach(input => {
            input.addEventListener('input', function(e) {
                let value = this.value.replace(/[^\d.]/g, ''); // Remove non-digit and non-dot characters
                if (value !== '') {
                    // Ensure only one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }
                    this.value = parseFloat(value).toFixed(2);
                } else {
                    this.value = '';
                }
            });
        });

        // Confirm before submitting changes
        document.getElementById('salaryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const confirmMsg = `Are you sure you want to update the salaries?
This action will:
- Update all employee salaries
- Set current month's salaries
- Take effect immediately
- Be logged in the system`;
    
            if (confirm(confirmMsg)) {
                this.submit();
            }
        });

        // Highlight changed values
        document.querySelectorAll('.salary-input').forEach(input => {
            const originalValue = input.value;
            input.addEventListener('change', function() {
                if (this.value !== originalValue) {
                    this.classList.add('bg-light');
                    this.style.fontWeight = 'bold';
                } else {
                    this.classList.remove('bg-light');
                    this.style.fontWeight = 'normal';
                }
            });
        });

        // Disable form submission on Enter key
        document.querySelectorAll('.salary-input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.blur();
                }
            });
        });
    </script>
</body>
</html>