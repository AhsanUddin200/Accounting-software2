<?php
require_once 'session.php';
require_once 'db.php';

if (isset($_GET['category_id'])) {
    $category_id = intval($_GET['category_id']);
    
    $query = "SELECT sub.*, ac.name as category_name 
              FROM account_subcategories sub
              JOIN account_categories ac ON sub.category_id = ac.id
              WHERE sub.category_id = ?
              ORDER BY sub.name";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $category_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo '<table class="table">
                <thead>
                    <tr>
                        <th>Sub Category</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>';
        
        while ($row = $result->fetch_assoc()) {
            echo '<tr>
                    <td>' . htmlspecialchars($row['name']) . '</td>
                    <td>' . htmlspecialchars($row['description']) . '</td>
                </tr>';
        }
        
        echo '</tbody></table>';
    } else {
        echo '<div class="alert alert-info">No sub-categories found for this category</div>';
    }
} 