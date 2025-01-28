<?php
require_once 'db.php';
session_start();

if (!isset($_GET['id'])) {
    echo "Invalid request";
    exit;
}

$grn_id = intval($_GET['id']);

try {
    // Get GRN details with PO info
    $query = "SELECT grn.*, po.po_number 
              FROM goods_receipt_notes grn 
              LEFT JOIN purchase_orders po ON grn.po_id = po.id 
              WHERE grn.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $grn_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $grn = $result->fetch_assoc();

    // Get GRN items
    $items_query = "SELECT 
        gi.item_code,
        gi.description,
        gi.received_qty,
        gi.unit,
        COALESCE(poi.unit_price, 0) as unit_price
    FROM grn_items gi
    LEFT JOIN goods_receipt_notes grn ON gi.grn_id = grn.id
    LEFT JOIN purchase_orders po ON grn.po_id = po.id
    LEFT JOIN po_items poi ON po.id = poi.po_id AND gi.item_code = poi.item_code
    WHERE gi.grn_id = ?";

    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $grn_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    ?>
    
    <div class="container-fluid p-0">
        <!-- Header Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <h6 class="text-muted">GRN Information</h6>
                <table class="table table-sm">
                    <tr>
                        <th width="150">GRN Number:</th>
                        <td><?php echo htmlspecialchars($grn['grn_number']); ?></td>
                    </tr>
                    <tr>
                        <th>PO Number:</th>
                        <td><?php echo htmlspecialchars($grn['po_number']); ?></td>
                    </tr>
                    <tr>
                        <th>Supplier:</th>
                        <td><?php echo htmlspecialchars($grn['supplier']); ?></td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="status-badge status-<?php echo strtolower($grn['status']); ?>">
                            <?php echo ucfirst($grn['status']); ?>
                        </span></td>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-muted">Delivery Information</h6>
                <table class="table table-sm">
                    <tr>
                        <th width="150">Delivery Date:</th>
                        <td><?php echo date('Y-m-d', strtotime($grn['delivery_date'])); ?></td>
                    </tr>
                    <tr>
                        <th>Created At:</th>
                        <td><?php echo date('Y-m-d H:i', strtotime($grn['created_at'])); ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive mt-4">
            <h6 class="text-muted mb-3">Received Items</h6>
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
                        $total = $item['received_qty'] * ($item['unit_price'] ?? 0);
                        $grand_total += $total;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                        <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['received_qty']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                        <td class="text-end"><?php echo number_format($item['unit_price'] ?? 0, 2); ?></td>
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

        <!-- Add Status Change Section -->
        <?php if ($grn['status'] === 'pending'): ?>
        <div class="mt-4">
            <h6 class="text-muted">Update Status</h6>
            <div class="card">
                <div class="card-body">
                    <input type="hidden" id="grnId" value="<?php echo $grn_id; ?>">
                    <div class="mb-3">
                        <label for="grnStatus" class="form-label">Status</label>
                        <select class="form-select" id="grnStatus">
                            <option value="pending" <?php echo $grn['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $grn['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="rejected" <?php echo $grn['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="grnRemarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="grnRemarks" rows="2"><?php echo htmlspecialchars($grn['remarks'] ?? ''); ?></textarea>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="saveGRNStatus()">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
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