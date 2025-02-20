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
            --success-color: #2ecc71;
            --warning-color: #f1c40f;
            --text-color: #2d3748;
            --light-color: #f8f9fa;
            --border-radius: 15px;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(135deg, #f6f8ff 0%, #f1f4ff 100%);
            color: var(--text-color);
            font-family: 'Segoe UI', sans-serif;
            padding: 2rem 0;
        }

        .page-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .main-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem;
            text-align: center;
            border: none;
            position: relative;
            overflow: hidden;
        }

        .card-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
            transform: skewY(-4deg);
        }

        .card-header h2 {
            margin: 0;
            font-weight: 600;
            position: relative;
            font-size: 2rem;
        }

        .card-header p {
            margin: 0.5rem 0 0;
            opacity: 0.9;
            font-size: 1.1rem;
            position: relative;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            padding: 2rem;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            text-align: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .stat-card h4 {
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary-color);
            margin: 0;
        }

        .stat-card p {
            color: #6c757d;
            margin: 0.5rem 0 0;
            font-size: 1rem;
        }

        .form-section {
            padding: 2rem;
            background: white;
            border-radius: 0 0 var(--border-radius) var(--border-radius);
        }

        .form-group {
            margin-bottom: 2rem;
        }

        .form-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control, .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }

        .btn-generate {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            padding: 1rem 2rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
            color: white;
            font-size: 1.1rem;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 97, 238, 0.3);
        }

        .btn-generate i {
            margin-right: 0.5rem;
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 1rem;
            }

            .card-header {
                padding: 1.5rem;
            }

            .stats-container {
                grid-template-columns: 1fr;
                padding: 1rem;
            }

            .form-section {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="page-container">
        <div class="main-card">
            <!-- Header -->
            <div class="card-header">
                <h2><i class="fas fa-file-invoice me-2"></i>Payslip Generator</h2>
                <p>Generate and manage employee payslips with ease</p>
            </div>

            <!-- Stats Section -->
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4><?php echo count($users); ?></h4>
                    <p>Total Employees</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4><?php echo count($months); ?></h4>
                    <p>Payment Periods</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h4><?php echo count($months) * count($users); ?></h4>
                    <p>Total Payslips</p>
                </div>
            </div>

            <!-- Form Section -->
            <div class="form-section">
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

                    <button type="submit" class="btn btn-generate">
                        <i class="fas fa-file-download"></i>Generate Payslip
                    </button>
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