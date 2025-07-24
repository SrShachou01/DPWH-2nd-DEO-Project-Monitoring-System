<?php
session_start();
include '../includes/database.php';

// Check if user is logged in and has admin role
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit();
}

// Check if proj_ID is set
if (!isset($_POST['proj_ID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Project ID not provided.']);
    exit();
}

$proj_ID = $_POST['proj_ID'];

$db = ConnectDB();

// Begin transaction
$db->begin_transaction();

try {
    // Fetch the project's deleted_at timestamp
    $stmt = $db->prepare("SELECT deleted_at FROM projects WHERE proj_ID = ? AND proj_isDeleted = 1");
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $db->error);
    }
    $stmt->bind_param('s', $proj_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows == 0) {
        throw new Exception("Project not found or not in archive mode.");
    }
    $row = $result->fetch_assoc();
    $deleted_at = new DateTime($row['deleted_at']);
    $current_date = new DateTime();
    $interval = $current_date->diff($deleted_at)->days;
    if ($interval < 30) {
        throw new Exception("Cannot permanently delete the project before 30 days have passed since archiving.");
    }
    $stmt->close();

    // List of tables related to projects
    $relatedTables = [
        'contract-manpower',
        'contract-time-extension',
        'contract-work-resumption',
        'contract-work-suspension',
        'final-completion',
        'monthly-time-suspension-report',
        'variation-orders',
        // Add other related tables as necessary
    ];
    
    // Iterate through each related table and delete records
    foreach ($relatedTables as $table) {
        // Prepare the DELETE statement
        $stmt = $db->prepare("DELETE FROM `$table` WHERE proj_ID = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed for table `$table`: " . $db->error);
        }
        $stmt->bind_param('s', $proj_ID);
        if (!$stmt->execute()) {
            throw new Exception("Execution failed for table `$table`: " . $stmt->error);
        }
        $stmt->close();
    }

    // Now delete the project from the projects table
    $stmt = $db->prepare("DELETE FROM projects WHERE proj_ID = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed for projects table: ' . $db->error);
    }

    $stmt->bind_param('s', $proj_ID);
    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Commit the transaction
            $db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Project and all related records permanently deleted successfully.']);
        } else {
            throw new Exception('Project not found.');
        }
    } else {
        throw new Exception('Execution failed for projects table: ' . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    // Rollback the transaction on error
    $db->rollback();
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit();
}

$db->close();
?>
