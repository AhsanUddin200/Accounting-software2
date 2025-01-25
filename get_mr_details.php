<?php
require_once 'db.php';
session_start();

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['id'])) {
    echo "Invalid request";
    exit;
}

$mr_id = intval($_GET['id']);

try {
    // Get MR details
    $query = "SELECT mr.*, u.username as requester_name 
              FROM material_requisitions mr 
              LEFT JOIN users u ON mr.requested_by = u.id 
              WHERE mr.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $mr_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $mr = $result->fetch_assoc();

    if (!$mr) {
        throw new Exception("Material Requisition not found");
    }

    // Get MR items
    $items_query = "SELECT * FROM mr_items WHERE mr_id = ?";
    $stmt = $conn->prepare($items_query);
    $stmt->bind_param("i", $mr_id);
    $stmt->execute();
    $items_result = $stmt->get_result();
    
    // Output the HTML
    ?>
    <input type="hidden" id="mrId" value="<?php echo $mr_id; ?>">

    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-md-6">
                <strong>MR Number:</strong>
                <p><?php echo htmlspecialchars($mr['mr_number']); ?></p>
            </div>
            <div class="col-md-6">
                <strong>Department:</strong>
                <p><?php echo htmlspecialchars($mr['department']); ?></p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <strong>Requested By:</strong>
                <p><?php echo htmlspecialchars($mr['requester_name']); ?></p>
            </div>
            <div class="col-md-6">
                <strong>Date Required:</strong>
                <p><?php echo date('Y-m-d', strtotime($mr['date_required'])); ?></p>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-12">
                <strong>Items:</strong>
                <div class="table-responsive mt-2">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Item Code</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit</th>
                                <th>Purpose</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = $items_result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_code']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td><?php echo htmlspecialchars($item['unit']); ?></td>
                                <td><?php echo htmlspecialchars($item['purpose']); ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label for="mrStatus">Status:</label>
                    <select class="form-select" id="mrStatus">
                        <option value="pending" <?php echo $mr['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $mr['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $mr['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for="mrRemarks">Remarks:</label>
                    <textarea class="form-control" id="mrRemarks"><?php echo htmlspecialchars($mr['remarks'] ?? ''); ?></textarea>
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