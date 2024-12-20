<?php
// export_report.php
require 'session.php';
require 'db.php';

// Enable error reporting for debugging (remove in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Function to sanitize output
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Handle User Report Export
    if (isset($_POST['export_user_report'])) {
        $start_date = $_POST['start_date'];
        $end_date = $_POST['end_date'];
        $user_id = $_SESSION['user_id'];

        // Prepare the query
        if (!empty($start_date) && !empty($end_date)) {
            $stmt = $conn->prepare("SELECT amount, type, date, description FROM transactions WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("SELECT amount, type, date, description FROM transactions WHERE user_id = ? ORDER BY date DESC");
            $stmt->bind_param("i", $user_id);
        }

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            // Create CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename=financial_report_user_' . $user_id . '.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Amount', 'Type', 'Date', 'Description']);

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['amount'],
                    ucfirst($row['type']),
                    $row['date'],
                    $row['description']
                ]);
            }

            fclose($output);
            exit();
        } else {
            die("Error preparing statement: " . $conn->error);
        }
    }

    // Handle Admin Report Export
    if (isset($_POST['export_admin_report'])) {
        // Ensure only admins can perform this action
        if ($_SESSION['role'] != 'admin') {
            die("Access denied.");
        }

        $user_id = $_POST['user_id'];
        $start_date = $_POST['start_date_admin'];
        $end_date = $_POST['end_date_admin'];

        // Validate inputs
        if (empty($user_id)) {
            die("User ID is required.");
        }

        // Prepare the query
        if (!empty($start_date) && !empty($end_date)) {
            $stmt = $conn->prepare("SELECT amount, type, date, description FROM transactions WHERE user_id = ? AND date BETWEEN ? AND ? ORDER BY date DESC");
            $stmt->bind_param("iss", $user_id, $start_date, $end_date);
        } else {
            $stmt = $conn->prepare("SELECT amount, type, date, description FROM transactions WHERE user_id = ? ORDER BY date DESC");
            $stmt->bind_param("i", $user_id);
        }

        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();

            // Fetch username for filename
            $user_stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
            $user_stmt->bind_param("i", $user_id);
            $user_stmt->execute();
            $user_stmt->bind_result($username);
            $user_stmt->fetch();
            $user_stmt->close();

            $username = $username ? $username : 'user_' . $user_id;

            // Create CSV
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment;filename=financial_report_admin_' . sanitize($username) . '.csv');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Amount', 'Type', 'Date', 'Description']);

            while ($row = $result->fetch_assoc()) {
                fputcsv($output, [
                    $row['amount'],
                    ucfirst($row['type']),
                    $row['date'],
                    $row['description']
                ]);
            }

            fclose($output);
            exit();
        } else {
            die("Error preparing statement: " . $conn->error);
        }
    }
}
?>
