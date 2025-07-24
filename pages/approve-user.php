<?php
// approve-user.php
include '../includes/database.php';
session_start();

// Only allow logged-in Admin users to access this endpoint
if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: ../index.php");
    exit();
}

// Error reporting settings (for debugging; disable display in production)
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/approve-user-error.log');

$db = ConnectDB();

// Log POST data
error_log("POST data received: " . print_r($_POST, true)); // Log the POST data

if (isset($_POST['approve']) && isset($_POST['user_id'])) {
    $user_id = intval($_POST['user_id']);
    error_log("Received user_id: " . $user_id);  // Log received user_id

    // Check if the session is properly set
    error_log("Session user_id: " . $_SESSION['user_id']);
    error_log("Session role_id: " . $_SESSION['role_id']);

    // Use a prepared statement to update the user's role to Guest (3)
    $stmt = $db->prepare("UPDATE users SET role_id = 3 WHERE user_id = ?");
    if (!$stmt) {
        error_log("Prepare failed: " . $db->error);
        die("Prepare failed: " . $db->error);
    }
    if (!$stmt->bind_param("i", $user_id)) {
        error_log("Bind failed: " . $stmt->error);
        die("Bind failed: " . $stmt->error);
    }
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        die("Execute failed: " . $stmt->error);
    }

    error_log("Update executed. Affected rows: " . $stmt->affected_rows);  // Log affected rows
    $stmt->close();
} else {
    error_log("Approve or user_id not set in POST");
}

// Redirect back to the request-view page after processing
header("Location: ../pages/request-view.php");
exit();
?>

?>
