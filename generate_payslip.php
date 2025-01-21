<?php
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

// Add error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Ensure the user is an admin
if ($_SESSION['role'] != 'admin') {
    header("Location: user_dashboard.php");
    exit();
}

if (!isset($_GET['user_id'])) {
    die("User ID not provided");
}

$user_id = intval($_GET['user_id']);

$query = "SELECT 
    u.*,
    COALESCE(u.monthly_salary, 0) as monthly_salary,
    COALESCE(u.current_month_salary, 0) as current_month_salary
FROM users u
WHERE u.id = ?";

$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $user_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User not found");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($user['username']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #60a5fa;
            --border-color: #e2e8f0;
            --background-color: #f8fafc;
            --text-color: #1e293b;
            --success-color: #059669;
        }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 40px 20px;
            background-color: var(--background-color);
            color: var(--text-color);
        }

        .payslip {
            max-width: 1000px;
            margin: 0 auto;
            border: 1px solid var(--border-color);
            padding: 48px;
            background-color: white;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
            border-radius: 16px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
            padding: 20px 0;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            margin: -48px -48px 48px -48px;
            border-radius: 16px 16px 0 0;
            color: white;
        }

        .header h2 {
            font-size: 2.8em;
            margin: 0;
            font-weight: 800;
            letter-spacing: -0.5px;
        }

        .header p {
            font-size: 1.2em;
            margin-top: 8px;
            opacity: 0.9;
        }

        .employee-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 48px;
            margin-bottom: 48px;
            padding: 32px;
            background-color: var(--background-color);
            border-radius: 12px;
            border: 1px solid var(--border-color);
        }

        .detail-section h3 {
            color: var(--primary-color);
            margin: 0 0 24px 0;
            font-size: 1.4em;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-section h3::after {
            content: '';
            flex: 1;
            height: 2px;
            background: linear-gradient(to right, var(--accent-color), transparent);
        }

        .detail-section p {
            margin: 16px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .detail-section i {
            color: var(--primary-color);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: var(--accent-color);
            border-radius: 6px;
            color: white;
            font-size: 0.9em;
        }

        .salary-details {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 40px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }

        .salary-details th, .salary-details td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
        }

        .salary-details th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.95em;
            letter-spacing: 0.5px;
        }

        .salary-details tr:last-child td {
            border-bottom: none;
        }

        .salary-details tr:nth-child(even) {
            background-color: var(--background-color);
        }

        .amount {
            text-align: right;
            font-family: 'JetBrains Mono', monospace;
            font-weight: 600;
            color: var(--success-color);
        }

        .total-row th {
            background-color: var(--secondary-color);
            color: white;
            font-size: 1.1em;
        }

        .footer {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
            padding-top: 40px;
            border-top: 2px dashed var(--border-color);
        }

        .signature-line {
            width: 280px;
            border-top: 3px solid var(--primary-color);
            margin-top: 40px;
            text-align: center;
            padding-top: 12px;
        }

        .signature-line p {
            margin: 4px 0;
            color: var(--text-color);
            font-weight: 600;
        }

        .print-button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 40px auto 0;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }

        .print-button:hover {
            background-color: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(37, 99, 235, 0.3);
        }

        .notes {
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            padding: 16px 24px;
            border-radius: 8px;
            margin-top: 32px;
            color: #9a3412;
        }

        @media print {
            body {
                padding: 0;
                background: white;
            }
            .payslip {
                box-shadow: none;
                border: none;
                padding: 20px;
            }
            .header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .no-print {
                display: none;
            }
            .salary-details th {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        @media (max-width: 768px) {
            .employee-details {
                grid-template-columns: 1fr;
                gap: 32px;
            }
            .payslip {
                padding: 24px;
            }
            .header {
                margin: -24px -24px 32px -24px;
            }
            .salary-details th, .salary-details td {
                padding: 12px 16px;
            }
            .signature-line {
                width: 200px;
            }
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 5em;
            color: rgba(0, 0, 0, 0.1);
            text-align: center;
            pointer-events: none;
            z-index: 1;
            opacity: 0.5;
            bottom: 9000px;
        }
    </style>
</head>
<body>
    <div class="payslip">
        <div class="watermark">Financial Management System</div>
        <div class="header">
            <h2>PAYSLIP</h2>
            <p>For the month of <?php echo date('F Y'); ?></p>
        </div>

        <div class="employee-details">
            <div class="detail-section">
                <h3>Employee Details</h3>
                <p><i class="fas fa-user"></i> <strong>Name:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                <p><i class="fas fa-id-card"></i> <strong>Employee ID:</strong> <?php echo htmlspecialchars($user['id']); ?></p>
            </div>
            <div class="detail-section">
                <h3>Payment Details</h3>
                <p><i class="fas fa-calendar"></i> <strong>Payment Date:</strong> <?php echo date('d F Y'); ?></p>
                <p><i class="fas fa-money-bill-transfer"></i> <strong>Payment Method:</strong> Bank Transfer</p>
                <p><i class="fas fa-bank"></i> <strong>Bank Account:</strong> <?php echo htmlspecialchars($user['bank_account'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <table class="salary-details">
            <tr>
                <th colspan="2">Earnings</th>
            </tr>
            <tr>
                <td>Basic Monthly Salary</td>
                <td class="amount">PKR <?php echo number_format($user['monthly_salary'], 2); ?></td>
            </tr>
            <tr>
                <td>Days Worked</td>
                <td class="amount"><?php 
                    $percentage = ($user['current_month_salary'] / $user['monthly_salary']) * 100;
                    echo number_format($percentage, 0) . "%";
                ?></td>
            </tr>
            <tr>
                <td>Current Month Salary</td>
                <td class="amount">PKR <?php echo number_format($user['current_month_salary'], 2); ?></td>
            </tr>
            
            <tr class="total-row">
                <th>Gross Earnings</th>
                <th class="amount">PKR <?php echo number_format($user['current_month_salary'], 2); ?></th>
            </tr>
            
            <tr>
                <th colspan="2">Calculations</th>
            </tr>
            <tr>
                <td>Base Salary</td>
                <td class="amount">PKR <?php echo number_format($user['monthly_salary'], 2); ?></td>
            </tr>
            <tr>
                <td>Attendance Percentage</td>
                <td class="amount"><?php echo number_format($percentage, 1); ?>%</td>
            </tr>
            <tr>
                <td>Calculated Amount</td>
                <td class="amount">PKR <?php echo number_format($user['current_month_salary'], 2); ?></td>
            </tr>
            
            <tr>
                <th colspan="2">Deductions</th>
            </tr>
            <tr>
                <td>Tax</td>
                <td class="amount">PKR 0.00</td>
            </tr>
            
            <tr class="total-row">
                <th>Total Deductions</th>
                <th class="amount">PKR 0.00</th>
            </tr>
            
            <tr class="total-row">
                <th>Net Pay</th>
                <th class="amount">PKR <?php echo number_format($user['current_month_salary'], 2); ?></th>
            </tr>
        </table>

        <div class="notes">
            <p><strong>Note:</strong> Current month salary (PKR <?php echo number_format($user['current_month_salary'], 2); ?>) 
            is <?php echo number_format($percentage, 1); ?>% of base monthly salary (PKR <?php echo number_format($user['monthly_salary'], 2); ?>)</p>
        </div>

        <div class="footer">
            <div>
                <div class="signature-line">
                    <p>Employee Signature</p>
                </div>
            </div>
            <div>
                <div class="signature-line">
                    <p>Authorized Signature</p>
                </div>
            </div>
        </div>
    </div>

    <div class="no-print" style="text-align: center;">
        <button onclick="window.print()" class="print-button">
            <i class="fas fa-print"></i> Print Payslip
        </button>
    </div>
</body>
</html>