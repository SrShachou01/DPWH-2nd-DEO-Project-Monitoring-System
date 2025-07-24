<?php
// project-management/restore-project.php
session_start();
include '../includes/database.php';

// Ensure user is authenticated and has the right role
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role_id'], [1, 2])) {
    $_SESSION['error_message'] = 'Unauthorized access.';
    exit();
}

$db = ConnectDB();

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize the 'proj_ID' from POST data
    $proj_ID = trim($_POST['proj_ID'] ?? '');

    // Validate that 'proj_ID' is provided
    if (empty($proj_ID)) {
        $_SESSION['error_message'] = 'Invalid input.';
        exit();
    }

    // Begin transaction
    $db->begin_transaction();

    try {
        // Restore the project by setting 'proj_isDeleted' to 0
        $restoreQuery = "UPDATE projects SET proj_isDeleted = 0 WHERE proj_ID = ?";
        $restoreStmt = $db->prepare($restoreQuery);
        if (!$restoreStmt) {
            throw new Exception('Database error: ' . $db->error);
        }
        $restoreStmt->bind_param('s', $proj_ID);
        if (!$restoreStmt->execute()) {
            throw new Exception('Failed to restore project: ' . $restoreStmt->error);
        }
        $restoreStmt->close();

        // Commit transaction
        $db->commit();

        // Set success message in session
        $_SESSION['success_message'] = "Project restored successfully.";
    } catch (Exception $e) {
        // Rollback transaction on error
        $db->rollback();

        // Set error message in session
        $_SESSION['error_message'] = "Error: " . $e->getMessage();
    }

    // Redirect back to the projects page
    exit();
} else {
    $_SESSION['error_message'] = 'Invalid request method.';
    exit();
}

?>
