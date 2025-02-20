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
    u.id,
    u.username,
    u.monthly_salary,
    s.tax_percentage,
    s.other_deductions,
    s.payment_date,
    s.days_worked
FROM users u
LEFT JOIN (
    SELECT user_id, tax_percentage, other_deductions, payment_date, days_worked 
    FROM salaries 
    WHERE user_id = ? 
    ORDER BY payment_date DESC 
    LIMIT 1
) s ON u.id = s.user_id
WHERE u.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Calculate all values here
$monthly_salary = floatval($user['monthly_salary']);
$working_days = 30; // Total working days in month
$days_worked = isset($user['days_worked']) ? intval($user['days_worked']) : 30; // Get actual days worked
$attendance_percentage = ($days_worked / $working_days) * 100;

// Calculate current month salary based on days worked
$current_month_salary = ($monthly_salary / $working_days) * $days_worked;

// Calculate deductions
$tax_percentage = floatval($user['tax_percentage'] ?? 0);
$tax_amount = ($current_month_salary * $tax_percentage) / 100;
$other_deductions = floatval($user['other_deductions'] ?? 0);

// Calculate net pay
$net_pay = $current_month_salary - $tax_amount - $other_deductions;

// Add these calculated values to user array
$user['days_worked'] = $days_worked;
$user['current_month_salary'] = $current_month_salary;
$user['attendance_percentage'] = $attendance_percentage;
$user['tax_amount'] = $tax_amount;
$user['net_pay'] = $net_pay;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - <?php echo htmlspecialchars($user['username']); ?></title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }

        .payslip {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 40px;
            color: rgba(0, 0, 0, 0.05);
            white-space: nowrap;
            pointer-events: none;
            z-index: 0;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #eee;
            position: relative;
            z-index: 1;
        }

        .header h2 {
            margin: 0;
            font-size: 24px;
        }

        .employee-details {
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .detail-section {
            margin-bottom: 20px;
        }

        .detail-section h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #666;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
            background: rgba(255, 255, 255, 0.9);
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #f8f8f8;
            font-weight: normal;
        }

        .amount {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background: #f8f8f8;
        }

        .notes {
            padding: 15px;
            background: #f8f8f8;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }

        .footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }

        .signature-line {
            width: 200px;
            border-top: 1px solid #000;
            margin-top: 30px;
            text-align: center;
            padding-top: 5px;
        }

        .print-button {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            cursor: pointer;
            margin: 20px auto;
            display: block;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .payslip {
                box-shadow: none;
            }
            .print-button {
                display: none;
            }
            .watermark {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
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
                <p>Name: <?php echo htmlspecialchars($user['username']); ?></p>
                <p>Employee ID: <?php echo htmlspecialchars($user['id']); ?></p>
            </div>
            <div class="detail-section">
                <h3>Payment Details</h3>
                <p>Payment Date: <?php echo date('d F Y'); ?></p>
                <p>Payment Method: Bank Transfer</p>
                <p>Bank Account: <?php echo htmlspecialchars($user['bank_account'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <table>
            <tr>
                <th colspan="2">Earnings</th>
            </tr>
            <tr>
                <td>Basic Monthly Salary</td>
                <td class="amount">PKR <?php echo number_format($monthly_salary, 2); ?></td>
            </tr>
            <tr>
                <td>Days Worked</td>
                <td class="amount"><?php echo $days_worked; ?> days (<?php echo number_format($attendance_percentage, 0); ?>%)</td>
            </tr>
            <tr>
                <td>Current Month Salary</td>
                <td class="amount">PKR <?php echo number_format($current_month_salary, 2); ?></td>
            </tr>
            <tr>
                <td>Tax (<?php echo number_format($tax_percentage, 1); ?>%)</td>
                <td class="amount">- PKR <?php echo number_format($tax_amount, 2); ?></td>
            </tr>
            <tr>
                <td>Other Deductions</td>
                <td class="amount">- PKR <?php echo number_format($other_deductions, 2); ?></td>
            </tr>
            <tr class="total-row">
                <td>Net Pay</td>
                <td class="amount">PKR <?php echo number_format($net_pay, 2); ?></td>
            </tr>
        </table>

        <div class="notes">
            <p>Note: Current month salary (PKR <?php echo number_format($current_month_salary, 2); ?>) 
            is calculated based on <?php echo $days_worked; ?> days worked out of <?php echo $working_days; ?> total working days.</p>
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

    <button onclick="window.print()" class="print-button">Print Payslip</button>
</body>
</html>