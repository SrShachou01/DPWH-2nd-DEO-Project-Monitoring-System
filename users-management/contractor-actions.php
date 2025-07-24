<?php
require_once "../includes/database.php";
session_start();
$db = ConnectDB();

// Error reporting settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/ugh.txt');

$action = $_GET['action'];

if ($action == 'add') {
    // Gather form data for adding a new contractor
    $cont_name = $_POST['cont_name'];
    $cont_location = $_POST['cont_location'];
    $cont_owner = $_POST['cont_owner'];
    $cont_phone = $_POST['cont_phone'];
    $cont_isDeleted = 0; // Default to not deleted
    $cont_isBlocklisted = 0; // Default to not blocklisted

    // Prepare and execute the insert query
    $query = "INSERT INTO contractors (cont_name, cont_location, cont_owner, cont_phone, cont_isDeleted, cont_isBlocklisted) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssssii', $cont_name, $cont_location, $cont_owner, $cont_phone, $cont_isDeleted, $cont_isBlocklisted);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Contractor added successfully';
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    } else {
        $error = $stmt->error;
        $_SESSION['error_message'] = 'Error adding contractor: ' . $error;
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    }
}

if ($action == 'edit') {
    // Gather form data for editing a contractor
    $cont_ID = $_POST['cont_ID'];
    $cont_name = $_POST['cont_name'];
    $cont_location = $_POST['cont_location'];
    $cont_owner = $_POST['cont_owner'];
    $cont_phone = $_POST['cont_phone'];

    // Prepare and execute the update query
    $query = "UPDATE contractors SET cont_name = ?, cont_location = ?, cont_owner = ?, cont_phone = ? WHERE cont_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('ssssi', $cont_name, $cont_location, $cont_owner, $cont_phone, $cont_ID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Contractor updated successfully';
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    } else {
        $error = $stmt->error;
        $_SESSION['error_message'] = 'Error updating contractor: ' . $error;
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    }
}

if ($action == 'delete') {
    // Gather contractor ID for deletion
    $cont_ID = $_POST['cont_ID'];

    // Perform a soft delete by setting cont_isDeleted to 1
    $query = "UPDATE contractors SET cont_isDeleted = 1 WHERE cont_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $cont_ID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Contractor deleted successfully';
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    } else {
        $error = $stmt->error;
        $_SESSION['error_message'] = 'Error deleting contractor: ' . $error;
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    }
}

if ($action == 'blocklist') {
    // Gather contractor ID and action for blocklisting/unblocking
    $cont_ID = $_POST['cont_ID'];
    $block_action = $_POST['block_action']; // 'block' or 'unblock'

    if ($block_action == 'block') {
        // Blocklist the contractor by setting cont_isBlocklisted to 1
        $query = "UPDATE contractors SET cont_isBlocklisted = 1 WHERE cont_ID = ?";
    } elseif ($block_action == 'unblock') {
        // Unblock the contractor by setting cont_isBlocklisted to 0
        $query = "UPDATE contractors SET cont_isBlocklisted = 0 WHERE cont_ID = ?";
    } else {
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    }

    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $cont_ID);

    if ($stmt->execute()) {
        $_SESSION['success_message'] = 'Contractor blocklisted successfully';
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    } else {
        $error = $stmt->error;
        $_SESSION['error_message'] = 'Error blocklisting contractor: ' . $error;
        header("Location: ../users-management/contractors.php"); // Redirect on error
        exit();
    }
}
?>
