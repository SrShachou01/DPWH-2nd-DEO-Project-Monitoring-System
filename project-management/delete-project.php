<?php 
// project-management/delete-project.php

session_start();
include '../includes/database.php';

// Set Content-Type to plain text
header('Content-Type: text/plain');

// Function to send plain text response and exit
function sendResponse($message) {
    echo $message;
    exit();
}

// Ensure user is authenticated and has the right role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    exit();
}

$db = ConnectDB();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize the 'id' from POST data
    $projectID = trim($_POST['id'] ?? '');

    // Validate that 'id' is provided
    if (empty($projectID)) {
        $_SESSION['error_message'] = 'Invalid input.';
        exit();
    }

    // Begin transaction
    $db->begin_transaction();

    try {
        // Delete related records from various tables
        $relatedTables = [
            'contract-time-extension',
            'project-collaborators',
            // Add other related tables as necessary
        ];

        foreach ($relatedTables as $table) {
            $deleteQuery = "DELETE FROM `$table` WHERE `proj_ID` = ?";
            $stmt = $db->prepare($deleteQuery);
            if (!$stmt) {
                throw new Exception('Database error: ' . $db->error);
            }
            $stmt->bind_param('s', $projectID);
            if (!$stmt->execute()) {
                throw new Exception('Failed to delete records from ' . $table . ': ' . $stmt->error);
            }
            $stmt->close();
        }

        // Soft delete the project record by setting 'proj_isDeleted' to 1 and updating 'deleted_at'
        $deleteProjectQuery = "UPDATE projects 
                               SET proj_isDeleted = 1, 
                                   proj_progress = 0, 
                                   proj_isApproved = 0, 
                                   proj_status = 'Not Yet Started',
                                   deleted_at = NOW()
                               WHERE proj_ID = ?";
        $deleteProjectStmt = $db->prepare($deleteProjectQuery);
        if (!$deleteProjectStmt) {
            throw new Exception('Database error: ' . $db->error);
        }
        $deleteProjectStmt->bind_param('s', $projectID);
        if (!$deleteProjectStmt->execute()) {
            throw new Exception('Failed to archive project: ' . $deleteProjectStmt->error);
        }
        $deleteProjectStmt->close();

        // Commit transaction
        $db->commit();

        // Set success message in session
        $_SESSION['success_message'] = "Project archived successfully.";

    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();
        
        // Set error message in session
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }

    // Redirect back to the projects page
    header("Location: ../pages/projects.php?trash=1");
    exit();
} else {
    $_SESSION['error_message'] = 'Invalid request method.';
    header("Location: ../pages/projects.php");
    exit();
}
?>
