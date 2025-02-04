<?php
require_once 'db.php';

$period = isset($_GET['period']) ? $_GET['period'] : '6';
$period_type = isset($_GET['period_type']) ? $_GET['period_type'] : 'month';

// Sanitize inputs
$period = filter_var($period, FILTER_SANITIZE_NUMBER_INT);
$period_type = filter_var($period_type, FILTER_SANITIZE_STRING);

// Get the chart data
require_once 'analytics_dashboard.php';
$data = getChartData($period, $period_type);

// Return JSON response
header('Content-Type: application/json');
echo json_encode($data); 