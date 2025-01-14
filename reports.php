<!-- Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="#">
            <i class="fas fa-file me-2"></i>Reports
        </a>
        <div class="ms-auto">
            <a href="admin_dashboard.php" class="nav-link text-white">
                <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>
</nav>

<div class="container mt-4">
    <div class="row">
        <!-- Reports Section -->
        <div class="col-md-6 mb-4">
            <div class="report-card text-center">
                <div class="report-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="report-title">Transaction Reports</div>
                <div class="report-description">
                    View detailed transaction reports and summaries
                </div>
                <a href="transaction_reports.php" class="btn btn-primary">
                    View Reports
                </a>
            </div>
        </div>

        <!-- Financial Reports Section -->
        <div class="col-md-6 mb-4">
            <div class="report-card text-center">
                <div class="report-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="report-title">Financial Reports</div>
                <div class="report-description">
                    View ledgers, trial balance, and financial statements
                </div>
                <a href="financial_reports.php" class="btn btn-primary">
                    View Financial Reports
                </a>
            </div>
        </div>
    </div>
</div> 