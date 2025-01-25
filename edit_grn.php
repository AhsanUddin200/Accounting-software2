<?php
require_once 'session.php';
require_once 'db.php';

if (!isset($_GET['id'])) {
    header("Location: grn_list.php");
    exit;
}

$grn_id = intval($_GET['id']);

// Get GRN details
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
$items_query = "SELECT gi.*, poi.unit_price 
                FROM grn_items gi
                LEFT JOIN po_items poi ON gi.item_code = poi.item_code 
                WHERE gi.grn_id = ?";
$stmt = $conn->prepare($items_query);
$stmt->bind_param("i", $grn_id);
$stmt->execute();
$items_result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit GRN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit GRN</h5>
                <a href="grn_list.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>
            <div class="card-body">
                <!-- GRN Details -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label class="form-label">GRN Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($grn['grn_number']); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">PO Number</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($grn['po_number']); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($grn['supplier']); ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($grn['status']); ?>" readonly>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Ordered Qty</th>
                                <th>Received Qty</th>
                                <th>Unit</th>
                                <th>Unit Price</th>
                                <th>Total</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grand_total = 0;
                            while ($item = $items_result->fetch_assoc()): 
                                $total = $item['received_qty'] * $item['unit_price'];
                                $grand_total += $total;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['description'] ?? '-'); ?></td>
                                <td class="text-center"><?php echo number_format($item['ordered_qty'], 2); ?></td>
                                <td class="text-center"><?php echo number_format($item['received_qty'], 2); ?></td>
                                <td class="text-center"><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td class="text-end"><?php echo number_format($item['unit_price'], 2); ?></td>
                                <td class="text-end"><?php echo number_format($total, 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-primary btn-sm edit-item" 
                                            data-id="<?php echo $item['id']; ?>"
                                            data-item="<?php echo htmlspecialchars(json_encode($item)); ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Grand Total:</strong></td>
                                <td class="text-end"><strong><?php echo number_format($grand_total, 2); ?></strong></td>
                                <td></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Item Modal -->
    <div class="modal fade" id="editItemModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editItemForm">
                        <input type="hidden" name="item_id" id="item_id">
                        <div class="mb-3">
                            <label class="form-label">Item Code</label>
                            <input type="text" class="form-control" id="item_code" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Received Quantity</label>
                            <input type="number" class="form-control" name="received_qty" id="received_qty" step="0.01" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Condition Status</label>
                            <select class="form-select" name="condition_status" id="condition_status" required>
                                <option value="good">Good</option>
                                <option value="damaged">Damaged</option>
                                <option value="partial">Partial</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" id="remarks" rows="2"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="saveItemChanges">Save changes</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
    $(document).ready(function() {
        // Edit Item Modal
        $('.edit-item').click(function() {
            const item = JSON.parse($(this).data('item'));
            $('#item_id').val(item.id);
            $('#item_code').val(item.item_code);
            $('#received_qty').val(item.received_qty);
            $('#condition_status').val(item.condition_status);
            $('#remarks').val(item.remarks);
            $('#editItemModal').modal('show');
        });

        // Save Item Changes
        $('#saveItemChanges').click(function() {
            const formData = $('#editItemForm').serialize();
            $.post('update_grn_item.php', formData, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error updating item: ' + response.message);
                }
            }, 'json');
        });
    });
    </script>
</body>
</html> 