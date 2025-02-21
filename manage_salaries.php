<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Check if user is logged in and has proper access
if (!isset($_SESSION['username']) || 
    ($_SESSION['username'] !== 'saim' && $_SESSION['username'] !== 'admin')) {
    header("Location: unauthorized.php");
    exit();
}

include 'includes/navbar.php';  

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
                // Validate required fields
                if (!isset($salary_data['days_worked']) || $salary_data['days_worked'] === '') {
                    $errors[] = "Days worked must be specified for User ID: $user_id";
                    continue;
                }

                $monthly_salary = floatval($salary_data['monthly_salary']);
                $days_worked = intval($salary_data['days_worked']);
                
                // Validate days worked
                if ($days_worked < 0 || $days_worked > 30) {
                    $errors[] = "Invalid days worked for User ID: $user_id. Must be between 0 and 30.";
                    continue;
                }

                // Calculate current month salary based on days worked
                $current_month_salary = ($monthly_salary / 30) * $days_worked;
                
                $tax_percentage = floatval($salary_data['tax_percentage'] ?? 0);
                $other_deductions = floatval($salary_data['other_deductions'] ?? 0);

                // Begin transaction
                $conn->begin_transaction();

                try {
                    // Update users table with monthly salary
                    $stmt_user = $conn->prepare("UPDATE users SET monthly_salary = ? WHERE id = ?");
                    $stmt_user->bind_param("di", $monthly_salary, $user_id);
                    $stmt_user->execute();
                    $stmt_user->close();

                    // Insert into salaries table with all details
                    $stmt_salary = $conn->prepare("INSERT INTO salaries (user_id, monthly_salary, current_month_salary, tax_percentage, other_deductions, days_worked, payment_date) VALUES (?, ?, ?, ?, ?, ?, CURRENT_DATE())");
                    $stmt_salary->bind_param("iddddi", $user_id, $monthly_salary, $current_month_salary, $tax_percentage, $other_deductions, $days_worked);
                    $stmt_salary->execute();
                    $stmt_salary->close();

                    // Log the action with days worked
                    log_action($conn, $_SESSION['user_id'], 'Updated Salary', 
                        "Updated salary for User ID: $user_id. Days worked: $days_worked, " .
                        "Monthly salary: $monthly_salary, Current month salary: $current_month_salary"
                    );
                    
                    $conn->commit();
                    $successes[] = "Salary updated for User ID: $user_id (Days worked: $days_worked)";

                } catch (Exception $e) {
                    $conn->rollback();
                    $errors[] = "Failed to update salary for User ID: $user_id. Error: " . $e->getMessage();
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

// Add this PHP code at the beginning of your file
if (isset($_POST['uploadDays']) && isset($_FILES['daysFile'])) {
    $file = $_FILES['daysFile'];
    $errors = [];
    $successes = [];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Skip header row
        fgetcsv($handle);
        
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            while (($data = fgetcsv($handle)) !== FALSE) {
                if (count($data) >= 2) {
                    $employee_id = trim($data[0]);
                    $days_worked = trim($data[1]);
                    
                    // Validate days
                    if (!is_numeric($days_worked) || $days_worked < 0 || $days_worked > 30) {
                        throw new Exception("Invalid days worked for Employee ID: $employee_id");
                    }
                    
                    // Update salaries table
                    $stmt = $conn->prepare("UPDATE salaries SET days_worked = ? WHERE user_id = ? AND MONTH(payment_date) = MONTH(CURRENT_DATE()) AND YEAR(payment_date) = YEAR(CURRENT_DATE())");
                    $stmt->bind_param("ii", $days_worked, $employee_id);
                    $stmt->execute();
                    
                    if ($stmt->affected_rows > 0) {
                        $successes[] = "Updated days for Employee ID: $employee_id (Days: $days_worked)";
                    } else {
                        $errors[] = "No record found for Employee ID: $employee_id";
                    }
                }
            }
            
            $conn->commit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
        }
        
        fclose($handle);
    } else {
        $errors[] = "File upload failed";
    }
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
                                    <th class="text-end">Monthly Salary ($)</th>
                                    <th class="text-end">Days Worked</th>
                                    <th class="text-end">Current Month Salary ($)</th>
                                    <th class="text-end">Tax Percentage (%)</th>
                                    <th class="text-end">Other Deductions ($)</th>
                                    <th class="text-end">Payslip</th>
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
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       name="salaries[<?php echo $user['id']; ?>][monthly_salary]" 
                                                       class="form-control salary-input" 
                                                       value="<?php echo htmlspecialchars($user['monthly_salary']); ?>"
                                                       required>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" 
                                                       step="1" 
                                                       min="0" 
                                                       max="30" 
                                                       name="salaries[<?php echo $user['id']; ?>][days_worked]" 
                                                       class="form-control salary-input" 
                                                       placeholder="Enter days (0-30)"
                                                       onchange="calculateSalary(this)"
                                                       required>
                                            </td>
                                            <td class="text-end">
                                                <input type="number" 
                                                       step="0.01" 
                                                       min="0" 
                                                       name="salaries[<?php echo $user['id']; ?>][current_month_salary]" 
                                                       class="form-control salary-input" 
                                                       readonly>
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
                                            <td>
                                                <a href="select_payslip.php?user_id=<?php echo $user['id']; ?>" 
                                                   class="btn btn-info btn-sm">
                                                    <i class="fas fa-file-invoice"></i> Payslip
                                                </a>
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

        <!-- Add this form after the existing salary form -->
        <div class="card mt-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-file-upload me-2"></i>Upload Days Worked</h5>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" class="mb-3">
                    <div class="mb-3">
                        <label for="daysFile" class="form-label">Upload CSV File (Employee ID, Days Worked)</label>
                        <input type="file" class="form-control" id="daysFile" name="daysFile" accept=".csv" required>
                        <small class="text-muted">File format: CSV with columns "employee_id,days_worked"</small>
                    </div>
                    <button type="submit" name="uploadDays" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Upload Days
                    </button>
                </form>
                
                <div class="mt-3">
                    <h6>Sample Format:</h6>
                    <pre class="bg-light p-2 rounded">
employee_id,days_worked
1,20
2,18
3,22
                    </pre>
                    <a href="sample_days.csv" download class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-download me-2"></i>Download Sample CSV
                    </a>
                </div>
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

        function calculateSalary(input) {
            const row = input.closest('tr');
            const daysWorked = parseInt(input.value) || 0;
            const monthlySalary = parseFloat(row.querySelector('input[name$="[monthly_salary]"]').value) || 0;
            
            // Validate days worked
            if (daysWorked < 0) {
                input.value = 0;
                alert('Days worked cannot be negative');
                return;
            }
            if (daysWorked > 30) {
                input.value = 30;
                alert('Days worked cannot exceed 30');
                return;
            }
            
            // Calculate current month salary based on days worked
            const currentMonthSalary = (monthlySalary / 30) * daysWorked;
            
            // Update current month salary field
            const currentMonthInput = row.querySelector('input[name$="[current_month_salary]"]');
            currentMonthInput.value = currentMonthSalary.toFixed(2);
            
            // Optional: Show calculation details
            console.log(`Calculation: (${monthlySalary} / 30) * ${daysWorked} = ${currentMonthSalary}`);
        }

        // Also recalculate when monthly salary changes
        document.querySelectorAll('input[name$="[monthly_salary]"]').forEach(input => {
            input.addEventListener('change', function() {
                const row = this.closest('tr');
                const daysWorkedInput = row.querySelector('input[name$="[days_worked]"]');
                if (daysWorkedInput.value) {
                    calculateSalary(daysWorkedInput);
                }
            });
        });
    </script>
</body>
</html>