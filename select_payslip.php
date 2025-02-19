<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';

// Fetch employees and months (existing queries)
$users_query = "SELECT id, username FROM users ORDER BY username";
$users = $conn->query($users_query)->fetch_all(MYSQLI_ASSOC);

$months_query = "SELECT DISTINCT MONTH(payment_date) as month, YEAR(payment_date) as year 
                 FROM salaries ORDER BY payment_date DESC";
$months = $conn->query($months_query)->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Payslip | FMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #4895ef;
            --text-color: #2d3748;
            --light-color: #f8f9fa;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f6f8ff 0%, #f1f4ff 100%);
            color: var(--text-color);
            font-family: 'Segoe UI', sans-serif;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
            border: none;
        }

        .card-header h2 {
            margin: 0;
            font-weight: 600;
        }

        .card-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
        }

        .card-body {
            padding: 3rem;
        }

        .form-group {
            margin-bottom: 2rem;
            position: relative;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary-color);
        }

        .has-icon {
            padding-left: 2.5rem;
        }

        .btn-generate {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 12px;
            padding: 1rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .quick-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <!-- Main Card -->
        <div class="card">
            <!-- Header -->
            <div class="card-header">
                <h2><i class="fas fa-file-invoice me-2"></i>Payslip Generator</h2>
                <p>Generate employee payslips with ease</p>
            </div>

            <!-- Quick Stats -->
            <div class="card-body">
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h4><?php echo count($users); ?></h4>
                        <p class="text-muted">Total Employees</p>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <h4><?php echo count($months); ?></h4>
                        <p class="text-muted">Payment Months</p>
                    </div>
                </div>

                <!-- Form -->
                <form id="payslipForm" action="generate_payslip.php" method="GET">
                    <div class="form-group">
                        <label class="form-label" for="user_id">
                            <i class="fas fa-user me-2"></i>Select Employee
                        </label>
                        <select name="user_id" id="user_id" class="form-select" required>
                            <option value="">Choose employee...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="month_year">
                            <i class="fas fa-calendar me-2"></i>Select Month
                        </label>
                        <select name="month_year" id="month_year" class="form-select" required>
                            <option value="">Choose month...</option>
                            <?php foreach ($months as $month): ?>
                                <option value="<?php echo $month['month'] . ',' . $month['year']; ?>">
                                    <?php echo date('F Y', strtotime($month['year'] . '-' . $month['month'] . '-01')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-generate">
                            <i class="fas fa-file-download me-2"></i>Generate Payslip
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('payslipForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const monthYear = document.getElementById('month_year').value.split(',');
            const userId = document.getElementById('user_id').value;
            
            if (userId && monthYear.length === 2) {
                window.location.href = `generate_payslip.php?user_id=${userId}&month=${monthYear[0]}&year=${monthYear[1]}`;
            }
        });
    </script>
</body>
</html> 