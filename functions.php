<?php
// functions.php

/**
 * Logs an action to the audit_logs table.
 *
 * @param mysqli $conn Database connection
 * @param int $user_id ID of the user performing the action
 * @param string $action Description of the action
 * @param string $details Additional details
 */
function log_action($conn, $user_id, $action, $details = '') {
    $stmt = $conn->prepare("INSERT INTO audit_logs (user_id, action, details) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param("iss", $user_id, $action, $details);
        $stmt->execute();
        $stmt->close();
    } else {
        error_log("Failed to prepare log_action statement: (" . $conn->errno . ") " . $conn->error);
    }
}
?>
