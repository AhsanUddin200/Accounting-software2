<?php
require_once 'db.php';
session_start();

if (!isset($_GET['id'])) {
    echo "Invalid request";
    exit;
}

$po_id = intval($_GET['id']);

try {
    // Get PO details with MR info and supplier
    $query = "SELECT po.*, mr.mr_number, mr.department 
              FROM purchase_orders po 
              LEFT JOIN material_requisitions mr ON po.mr_id = mr.id 
              WHERE po.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $po = $result->fetch_assoc();

    // Get PO items
    $items_query = "SELECT * FROM po_items WHERE po_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $po_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    ?>
    
    <div class="container-fluid p-0">
        <!-- Header Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted">Purchase Order Information</h6>
                <table class="table table-sm">
                    <tr>
                        <th width="150">PO Number:</th>
                        <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                    </tr>
                    <tr>
                        <th>MR Number:</th>
                        <td><?php echo htmlspecialchars($po['mr_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Department:</th>
                        <td><?php echo htmlspecialchars($po['department']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="badge bg-<?php echo $po['status'] === 'pending' ? 'warning' : ($po['status'] === 'approved' ? 'success' : 'danger'); ?>">
                            <?php echo ucfirst(htmlspecialchars($po['status'])); ?>
                        </span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Supplier & Delivery Information</h6>
                <table class="table table-sm">
                    <tr>
                        <th width="150">Supplier:</th>
                        <td><?php echo htmlspecialchars($po['supplier']); ?></td>
                    </tr>
                    <tr>
                        <th>Delivery Date:</th>
                        <td><?php echo date('Y-m-d', strtotime($po['delivery_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Terms:</th>
                        <td><?php echo htmlspecialchars($po['terms'] ?? 'N/A'); ?></td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td><?php echo date('Y-m-d H:i', strtotime($po['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mt-4">
            <h6 class="text-muted mb-3">Order Items</h6>
            <table class="table table-bordered table-striped">
                <thead class="table-light">
                    <tr>
                        <th>Item Code</th>
                        <th>Description</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-center">Unit</th>
                        <th class="text-end">Unit Price</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $grand_total = 0;
                    while ($item = $items_result->fetch_assoc()): 
                        $total = $item['quantity'] * $item['unit_price'];
                        $grand_total += $total;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['description']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['quantity']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                        <td class="text-end"><?php echo number_format($total, 2); ?></td>
                    </tr>
                    <?php endwhile; ?>
                    <tr>
                        <td colspan="5" class="text-end"><strong>Grand Total:</strong></td>
                        <td class="text-end"><strong><?php echo number_format($grand_total, 2); ?></strong></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Notes/Comments -->
        <?php if (!empty($po['notes'])): ?>
        <div class="mt-4">
            <h6 class="text-muted">Notes</h6>
            <div class="card">
                <div class="card-body">
                    <?php echo nl2br(htmlspecialchars($po['notes'])); ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
} catch (Exception $e) {
    echo '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?> 