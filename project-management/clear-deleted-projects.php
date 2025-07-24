<?php
session_start();
include '../includes/database.php';

// Check if user is logged in and has the appropriate role (Admin only)
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

$db = ConnectDB();

// Begin transaction
$db->begin_transaction();

try {
    // First, fetch all proj_IDs where proj_isDeleted = 1
    $stmt = $db->prepare("SELECT proj_ID FROM projects WHERE proj_isDeleted = 1");
    if (!$stmt) {
        throw new Exception('Prepare failed for selecting proj_IDs: ' . $db->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execution failed for selecting proj_IDs: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $proj_IDs = [];
    while ($row = $result->fetch_assoc()) {
        $proj_IDs[] = $row['proj_ID'];
    }

    $stmt->close();

    if (empty($proj_IDs)) {
        throw new Exception('No archived projects found to delete.');
    }

    // Prepare the list of proj_IDs for the IN clause
    $placeholders = implode(',', array_fill(0, count($proj_IDs), '?'));
    $types = str_repeat('s', count($proj_IDs));
    
    // List of tables related to projects
    $relatedTables = [
        'contract-manpower',
        'contract-time-extension',
        'contract-work-resumption',
        'contract-work-suspension',
        'final-completion',
        'implementing-office-manpower',
        'monthly-time-suspension-report',
        'progress',
        'project-collaborators',
        'variation-orders'
    ];
    
    // Iterate through each related table and delete records
    foreach ($relatedTables as $table) {
        // Prepare the DELETE statement with IN clause
        $stmt = $db->prepare("DELETE FROM `$table` WHERE proj_ID IN ($placeholders)");
        if (!$stmt) {
            throw new Exception("Prepare failed for table `$table`: " . $db->error);
        }
        $stmt->bind_param($types, ...$proj_IDs);
        if (!$stmt->execute()) {
            throw new Exception("Execution failed for table `$table`: " . $stmt->error);
        }
        $stmt->close();
    }

    // Now delete the projects from the projects table
    $stmt = $db->prepare("DELETE FROM projects WHERE proj_isDeleted = 1");
    if (!$stmt) {
        throw new Exception('Prepare failed for projects table: ' . $db->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execution failed for projects table: ' . $stmt->error);
    }

    $deleted_count = $stmt->affected_rows;
    $stmt->close();

    // Commit the transaction
    $db->commit();

    echo json_encode(['status' => 'success', 'message' => "$deleted_count archived project(s) and their related records permanently deleted successfully."]);
} catch (Exception $e) {
    // Rollback the transaction on error
    $db->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}

$db->close();
?>
