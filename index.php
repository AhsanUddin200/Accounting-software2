    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Financial Management System</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        body {
            background:  url('https://d23qowwaqkh3fj.cloudfront.net/wp-content/uploads/2024/05/What-is-Financial-Management.jpg.optimal.jpg') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 40px 0;
            color: #ffffff;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        .header {
            text-align: center;
            margin-bottom: 80px;
            position: relative;
        }

        .header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
            border-radius: 2px;
        }

        .header h1 {
            font-size: 3.2em;
            margin-bottom: 30px;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: -0.5px;
        }

        .dashboard-btn {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 16px 40px;
            border: none;
            border-radius: 12px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(37, 99, 235, 0.2);
        }

        .dashboard-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 40px;
            margin-top: 60px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 40px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #3b82f6 0%, #60a5fa 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .feature-card:hover::before {
            opacity: 1;
        }

        .feature-card h3 {
            font-size: 1.5em;
            margin-bottom: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .feature-card i {
            color: #3b82f6;
            font-size: 1.2em;
        }

        .feature-card p {
            color: #e2e8f0;
            line-height: 1.8;
            font-size: 1.1em;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 40px;
            margin-top: 80px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            padding: 32px;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            text-align: center;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .stat-card::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6 0%, #60a5fa 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .stat-card:hover::after {
            opacity: 1;
        }

        .stat-card h4 {
            color: #e2e8f0;
            font-size: 1.2em;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .stat-card p {
            color: #ffffff;
            font-size: 2.4em;
            font-weight: 700;
            letter-spacing: -0.5px;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .feature-card, .stat-card {
    backdrop-filter: blur(20px); /* Increased blur effect */
}

        .feature-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .feature-card:nth-child(3) {
            animation-delay: 0.4s;
        }

        .stat-card:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stat-card:nth-child(3) {
            animation-delay: 0.4s;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.5em;
            }
            
            .features, .stats {
                gap: 24px;
            }
            
            .feature-card, .stat-card {
                padding: 24px;
            }
        }
    </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Financial Management System</h1>
                <button class="dashboard-btn" onclick="redirectToLogin()">
    Access Dashboard
</button>
            </div>

            <div class="features">
                <div class="feature-card">
                    <h3>
                        <i class="fas fa-chart-line"></i>
                        Expense Tracking
                    </h3>
                    <p>Track all your expenses in real-time with detailed categorization and reporting features.</p>
                </div>
                <div class="feature-card">
                    <h3>
                        <i class="fas fa-wallet"></i>
                        Budget Management
                    </h3>
                    <p>Create and manage budgets effectively with our intuitive budgeting tools.</p>
                </div>
                <div class="feature-card">
                    <h3>
                        <i class="fas fa-file-invoice-dollar"></i>
                        Financial Reports
                    </h3>
                    <p>Generate comprehensive financial reports with just a few clicks.</p>
                </div>
            </div>

            <div class="stats">
                <?php
                require_once 'db.php';
                
                // Get stats from database
                $trans_query = "SELECT COUNT(DISTINCT voucher_number) as total_transactions FROM transactions";
                $users_query = "SELECT COUNT(*) as total_users FROM users";
                $income_query = "SELECT SUM(credit) as total_income FROM ledgers";
                
                $trans_count = $conn->query($trans_query)->fetch_assoc()['total_transactions'] ?? 0;
                $users_count = $conn->query($users_query)->fetch_assoc()['total_users'] ?? 0;
                $total_income = $conn->query($income_query)->fetch_assoc()['total_income'] ?? 0;
                ?>
                
                <div class="stat-card">
                    <h4>Total Transactions</h4>
                    <p><?php echo number_format($trans_count); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Active Users</h4>
                    <p><?php echo number_format($users_count); ?></p>
                </div>
                <div class="stat-card">
                    <h4>Total Revenue</h4>
                    <p>PKR <?php echo number_format($total_income, 2); ?></p>
                </div>
            </div>
        </div>

        <script>
             function redirectToLogin() {
        // Redirect to login.php
        window.location.href = "login.php";
    }
        </script>
    </body>
    </html>