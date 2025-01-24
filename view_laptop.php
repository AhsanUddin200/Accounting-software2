<?php
require_once 'session.php';
require_once 'db.php';

// Get laptop details
$laptop_id = $_GET['id'] ?? 0;
$query = "SELECT l.*, u.username as custodian_name 
          FROM laptops l 
          LEFT JOIN users u ON l.custodian_id = u.id 
          WHERE l.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $laptop_id);
$stmt->execute();
$laptop = $stmt->get_result()->fetch_assoc();

if (!$laptop) {
    header("Location: laptop_report.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Laptop Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        .navbar {
            background: linear-gradient(135deg, #1e40af, #3b82f6);
        }
        
        .details-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .asset-sticker {
            width: 200px;
            padding: 10px;
            border: 1px dashed #ccc;
            text-align: center;
            font-size: 12px;
            background: white;
            margin-bottom: 1rem;
        }

        .status-active { color: #10B981; }
        .status-maintenance { color: #F59E0B; }
        .status-sold { color: #EF4444; }

        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .btn-action:hover {
            transform: translateY(-2px);
        }

        @media print {
            .no-print { display: none; }
            .print-only { display: block; }
            .details-card {
                border: none;
                box-shadow: none;
            }
            .sticker-preview { display: none; }
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark no-print">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-laptop me-2"></i>Laptop Details
            </a>
            <div class="ms-auto">
                <a href="laptop_report.php" class="btn btn-outline-light me-2">
                    <i class="fas fa-list me-2"></i>Back to List
                </a>
                <button onclick="printDetails()" class="btn btn-light me-2">
                    <i class="fas fa-print me-2"></i>Print Details
                </button>
                <button onclick="printSticker()" class="btn btn-light">
                    <i class="fas fa-tag me-2"></i>Print Sticker
                </button>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Action Buttons -->
        <div class="mb-4 no-print">
            <a href="edit_laptop.php?id=<?php echo $laptop['id']; ?>" class="btn btn-primary btn-action me-2">
                <i class="fas fa-edit me-2"></i>Edit Laptop
            </a>
            <!-- <a href="maintenance_history.php?id=<?php echo $laptop['id']; ?>" class="btn btn-info btn-action me-2">
                <i class="fas fa-history me-2"></i>Maintenance History
            </a>
            <a href="assignment_history.php?id=<?php echo $laptop['id']; ?>" class="btn btn-secondary btn-action">
                <i class="fas fa-exchange-alt me-2"></i>Assignment History
            </a> -->
        </div>

        <div class="row">
            <!-- Details Section -->
            <div class="col-md-8">
                <div class="details-card" id="detailsCard">
                    <h4 class="mb-4">Laptop Information</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Asset ID:</strong> <?php echo htmlspecialchars($laptop['asset_id']); ?></p>
                            <p><strong>Model:</strong> <?php echo htmlspecialchars($laptop['model']); ?></p>
                            <p><strong>Serial Number:</strong> <?php echo htmlspecialchars($laptop['serial_number']); ?></p>
                            <p><strong>Status:</strong> 
                                <span class="status-<?php echo $laptop['status']; ?>">
                                    <i class="fas fa-circle me-1"></i>
                                    <?php echo ucfirst($laptop['status']); ?>
                                </span>
                            </p>
                            <p><strong>Custodian:</strong> <?php echo htmlspecialchars($laptop['custodian_name'] ?? 'Not Assigned'); ?></p>
                            <p><strong>Sale Value:</strong> PKR <?php echo $laptop['sale_value'] ? number_format($laptop['sale_value'], 2) : 'Not Set'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($laptop['location'] ?? 'Not Specified'); ?></p>
                            <p><strong>Purchase Date:</strong> <?php echo date('Y-m-d', strtotime($laptop['purchase_date'])); ?></p>
                            <p><strong>Purchase Price:</strong> PKR <?php echo number_format($laptop['purchase_price'], 2); ?></p>
                            <p><strong>Current Value:</strong> PKR <?php echo number_format($laptop['current_value'], 2); ?></p>
                            <p><strong>Notes:</strong> <?php echo htmlspecialchars($laptop['notes'] ?? 'No notes'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Specifications Card -->
                <div class="details-card">
                    <h4 class="mb-4">Specifications</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Processor:</strong> <?php echo htmlspecialchars($laptop['processor'] ?? 'Not specified'); ?></p>
                            <p><strong>RAM:</strong> <?php echo htmlspecialchars($laptop['ram'] ?? 'Not specified'); ?></p>
                            <p><strong>Storage:</strong> <?php echo htmlspecialchars($laptop['storage'] ?? 'Not specified'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Graphics:</strong> <?php echo htmlspecialchars($laptop['graphics'] ?? 'Not specified'); ?></p>
                            <p><strong>Screen Size:</strong> <?php echo htmlspecialchars($laptop['screen_size'] ?? 'Not specified'); ?></p>
                            <p><strong>OS:</strong> <?php echo htmlspecialchars($laptop['operating_system'] ?? 'Not specified'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Single Sticker Preview Section -->
            <div class="col-md-4">
                <div class="details-card">
                    <h4 class="mb-4">Asset Sticker Preview</h4>
                    <div class="asset-sticker mx-auto" id="stickerCard">
                        <div class="mb-2">
                            <strong>Asset ID: <?php echo htmlspecialchars($laptop['asset_id']); ?></strong>
                        </div>
                        <svg id="barcode"></svg>
                        <div class="mt-2">
                            <small><?php echo htmlspecialchars($laptop['model']); ?></small><br>
                            <small>S/N: <?php echo htmlspecialchars($laptop['serial_number']); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Generate single barcode
        JsBarcode("#barcode", "<?php echo $laptop['asset_id']; ?>", {
            format: "CODE128",
            width: 1.5,
            height: 40,
            displayValue: false
        });

        function printDetails() {
            window.print();
        }

        function printSticker() {
            // Hide everything except the sticker
            document.querySelectorAll('.details-card').forEach(card => {
                if (!card.contains(document.getElementById('stickerCard'))) {
                    card.style.display = 'none';
                }
            });
            window.print();
            // Restore visibility
            document.querySelectorAll('.details-card').forEach(card => {
                card.style.display = 'block';
            });
        }
    </script>
</body>
</html> 