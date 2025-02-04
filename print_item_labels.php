<?php
require_once 'session.php';
require_once 'db.php';

// Enable error reporting
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Ensure user has appropriate permissions
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

// Get item IDs from URL
$item_ids = isset($_GET['ids']) ? explode(',', $_GET['ids']) : [];
if (empty($item_ids)) {
    header("Location: stock_report.php");
    exit();
}

// Fetch items
$items = [];
$placeholders = str_repeat('?,', count($item_ids) - 1) . '?';
$query = "SELECT * FROM stock_items WHERE id IN ($placeholders)";
$stmt = $conn->prepare($query);
$stmt->bind_param(str_repeat('i', count($item_ids)), ...$item_ids);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Print Labels</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jsbarcode/3.11.5/JsBarcode.all.min.js"></script>
    <style>
        :root {
            --primary-color: #2249b9;
            --primary-dark: #1b3a94;
            --primary-light: #4267c7;
            --background-light: #f0f3f9;
        }

        @media print {
            .no-print {
                display: none !important;
            }
            .label-container {
                page-break-inside: avoid;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            body {
                background-color: white !important;
            }
            .navbar {
                display: none !important;
            }
        }

        body {
            background-color: var(--background-light);
        }

        .top-bar {
        
           
            padding: 1rem 0;
            margin-bottom: 2rem;
            border-top: 1px solid rgba(0,0,0,0.1);
        }

        .labels-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            justify-content: center;
            padding: 20px;
        }

        .label-container {
            width: 300px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: transform 0.2s;
            border: 1px solid rgba(34, 73, 185, 0.1);
        }

        .label-container:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(34, 73, 185, 0.2);
        }

        .item-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 15px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            padding-bottom: 8px;
            border-bottom: 2px solid var(--primary-light);
        }

        .barcode-container {
            background: var(--background-light);
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }

        .item-details {
            font-size: 13px;
            color: #6c757d;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        .item-details div {
            margin-bottom: 5px;
        }

        .item-code {
            font-family: monospace;
            font-size: 14px;
            color: var(--primary-color);
            background: var(--background-light);
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
        }

        .btn-toolbar {
            gap: 10px;
        }

        .btn-print {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .btn-print:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
            color: white;
        }

        .print-options {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .print-count {
            width: 80px;
        }

        .print-count .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(34, 73, 185, 0.25);
        }

        .btn-outline-secondary {
            color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .btn-outline-secondary:hover {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }

        .item-details i {
            color: var(--primary-color);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="top-bar no-print">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="mb-0" style="color: var(--primary-color);">Print Labels</h4>
                <div class="print-options">
                    <div class="input-group print-count">
                        <input type="number" class="form-control" id="printCount" value="1" min="1" max="10">
                        <span class="input-group-text">copies</span>
                    </div>
                    <div class="btn-toolbar">
                        <button class="btn btn-print" onclick="window.print()">
                            <i class="fas fa-print me-2"></i>Print Labels
                        </button>
                        <a href="stock_report.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid">
        <div class="labels-grid">
            <?php foreach ($items as $item): ?>
            <div class="label-container">
                <div class="item-name">
                    <?php echo htmlspecialchars($item['name']); ?>
                </div>
                <div class="barcode-container text-center">
                    <svg class="barcode" 
                         jsbarcode-value="<?php echo htmlspecialchars($item['item_code']); ?>"
                         jsbarcode-width="2"
                         jsbarcode-height="50"
                         jsbarcode-fontSize="12"
                         jsbarcode-background="#f0f3f9">
                    </svg>
                </div>
                <div class="text-center mb-3">
                    <span class="item-code"><?php echo htmlspecialchars($item['item_code']); ?></span>
                </div>
                <div class="item-details">
                    <div><i class="fas fa-tag me-2"></i>Category: <?php echo htmlspecialchars($item['category']); ?></div>
                    <?php if (!empty($item['location'])): ?>
                    <div><i class="fas fa-map-marker-alt me-2"></i>Location: <?php echo htmlspecialchars($item['location']); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Initialize barcodes
        JsBarcode(".barcode").init();

        // Handle print copies
        window.addEventListener('beforeprint', function() {
            const count = parseInt(document.getElementById('printCount').value) || 1;
            const grid = document.querySelector('.labels-grid');
            const originalContent = grid.innerHTML;
            
            // Create copies
            let newContent = '';
            for(let i = 0; i < count; i++) {
                newContent += originalContent;
            }
            grid.innerHTML = newContent;
            
            // Reinitialize barcodes for the copies
            JsBarcode(".barcode").init();
        });

        // Restore original content after printing
        window.addEventListener('afterprint', function() {
            const grid = document.querySelector('.labels-grid');
            const items = grid.querySelectorAll('.label-container');
            const itemCount = <?php echo count($items); ?>;
            
            // Keep only the original number of items
            while(grid.children.length > itemCount) {
                grid.removeChild(grid.lastChild);
            }
        });
    </script>
</body>
</html>