<?php
include "../includes/database.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Establish the database connection
$conn = ConnectDB();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_POST['user_id'];
    $username = $_POST['user_username'];
    $first_name = $_POST['user_first_name'];
    $last_name = $_POST['user_last_name'];
    $email = $_POST['user_email'];
    $position = $_POST['user_position'];
    $role_id = $_POST['role_id']; // Get role_id from form

    // Update the user info including the role in the database
    $query = "UPDATE users SET user_username = ?, user_first_name = ?, user_last_name = ?, user_email = ?, user_position = ?, role_id = ? WHERE user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ssssssi', $username, $first_name, $last_name, $email, $position, $role_id, $user_id);

    if ($stmt->execute()) {
        // Redirect to all-users.php after update
        header("Location: ../project-management/all-users.php?update=success");
        exit();
    } else {
        echo "Error: " . $conn->error;
    }

    $stmt->close();
}


mysqli_close($conn);
?>
