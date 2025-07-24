<?php
session_start();
include_once "../includes/database.php";

if (isset($_POST['prog_ID'])) {
    $prog_ID = $_POST['prog_ID'];
    $proj_ID = $_POST['proj_ID'];

    // Connect to the database
    $db = ConnectDB();

    // Fetch the progress percentage before deletion
    $query = "SELECT prog_percentage FROM progress WHERE prog_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $prog_ID);
    $stmt->execute();
    $stmt->bind_result($deleted_percentage);
    $stmt->fetch();
    $stmt->close();

    // Delete the progress entry
    $delete_query = "DELETE FROM progress WHERE prog_ID = ?";
    $stmt = $db->prepare($delete_query);
    $stmt->bind_param('i', $prog_ID);
    if ($stmt->execute()) {
        // Update the project progress if the deleted progress was the current one
        $update_progress_query = "UPDATE projects SET proj_progress = (SELECT IFNULL(MAX(prog_percentage), 0) FROM progress WHERE proj_ID = ?) WHERE proj_ID = ?";
        $stmt = $db->prepare($update_progress_query);
        $stmt->bind_param('ss', $proj_ID, $proj_ID);
        $stmt->execute();

        // Check if there are any progress entries left
        $check_progress_query = "SELECT COUNT(*) FROM progress WHERE proj_ID = ?";
        $stmt = $db->prepare($check_progress_query);
        $stmt->bind_param('s', $proj_ID);
        $stmt->execute();
        $stmt->bind_result($progress_count);
        $stmt->fetch();
        $stmt->close();

        // If no progress entries left, enable the "Add Progress" button
        if ($progress_count == 0) {
            $_SESSION['enable_add_progress'] = true;
        }

        // Set a success message
        $_SESSION['success_message'] = "Progress entry deleted successfully.";
        exit;
    } else {
        // Set an error message
        $_SESSION['error_message'] = "Error deleting the progress entry.";
        exit;
    }

    $stmt->close();
    $db->close();
}
?>
