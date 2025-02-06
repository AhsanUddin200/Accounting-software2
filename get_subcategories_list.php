<?php
require_once 'session.php';
require_once 'db.php';

// Add this at the start of the file after the require statements
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debug output
echo "<!-- Debug: Received category_id: " . ($_GET['category_id'] ?? 'none') . " -->";

if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    
    // Debug output
    error_log("Getting subcategories for category_id: " . $category_id);
    
    $query = "SELECT sub.id, sub.name, sub.description, sub.created_at 
              FROM account_subcategories sub 
              WHERE sub.category_id = ?
              ORDER BY sub.name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="table table-striped">
                <thead>
                    <tr>
                        <th>Sub Category</th>
                        <th>Description</th>
                        <th>Created At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . htmlspecialchars($row['name']) . '</td>
                    <td>' . htmlspecialchars($row['description']) . '</td>
                    <td>' . htmlspecialchars($row['created_at']) . '</td>
                    <td>
                        <button class="btn btn-sm btn-danger delete-subcategory" 
                                data-id="' . $row['id'] . '"
                                data-name="' . htmlspecialchars($row['name']) . '">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    </td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">No sub-categories found for this category</div>';
    }
    
    if ($stmt->error) {
        error_log("SQL Error: " . $stmt->error);
    }
} else {
    echo '<div class="alert alert-warning">No category selected</div>';
} 

