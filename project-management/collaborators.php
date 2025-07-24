<?php
session_start();
include '../includes/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$proj_id = isset($_GET['proj_id']) ? (int)$_GET['proj_id'] : 0;

if ($proj_id <= 0) {
    echo json_encode([]);
    exit();
}

// Fetch all users excluding the admin and uploader (you can modify this logic)
$query = "
    SELECT u.user_id, u.user_first_name, u.user_last_name
    FROM users u
    WHERE u.role_id != 1  -- Exclude Admin
    AND u.user_position != 'None'"; // Adjust this based on your roles and permissions

$result = $db->query($query);
$users = [];
while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

echo json_encode($users);
?>
