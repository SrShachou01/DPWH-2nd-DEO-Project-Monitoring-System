<?php
// users-management/view-user.php

include "../includes/database.php";
session_start();

// Ensure the user is an admin (role_id == 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to dashboard or show an error message
    header("Location: ../pages/dashboard.php");
    exit();
}

// Check if 'id' is present in GET parameters
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $user_id = intval($_GET['id']);

    // Establish the database connection
    $conn = ConnectDB();

    // Fetch user details
    $query = "SELECT users.*, roles.role_name 
              FROM users 
              LEFT JOIN roles ON users.role_id = roles.role_ID 
              WHERE users.user_id = ?";
    
    if ($stmt = mysqli_prepare($conn, $query)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if ($result && mysqli_num_rows($result) == 1) {
            $user = mysqli_fetch_assoc($result);
        } else {
            $_SESSION['error_message'] = "User not found.";
            header("Location: all-users.php");
            exit();
        }
        mysqli_stmt_close($stmt);
    } else {
        $_SESSION['error_message'] = "Database error: " . mysqli_error($conn);
        header("Location: all-users.php");
        exit();
    }

    // Close the database connection
    mysqli_close($conn);
} else {
    $_SESSION['error_message'] = "Invalid user ID.";
    header("Location: all-users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View User - <?php echo htmlspecialchars($user['user_username']); ?></title>
    <!-- Bootstrap 4.5.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <link rel="stylesheet" href="../css/styles-for-users.css">
    <style>
        /* Custom styles for the user profile */
        .user-profile {
            margin-top: 30px;
        }
        .user-profile img {
            max-width: 200px;
            border-radius: 50%;
            margin-bottom: 20px;
        }
        .user-details th {
            width: 200px;
        }
    </style>
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>

    <div id="content" class="container-fluid">
        <?php include "../includes/navbar.php"; ?>

        <!-- User Details -->
        <div class="card mt-4">
            <div class="card-header">
                <h3><?php echo htmlspecialchars($user['user_first_name'] . ' ' . $user['user_last_name']); ?></h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <?php if (!empty($user['user_photo'])): ?>
                        <div class="col-md-4 text-center">
                            <img src="../uploads/<?php echo htmlspecialchars($user['user_photo']); ?>" alt="User Photo" class="img-fluid rounded">
                        </div>
                        <div class="col-md-8">
                    <?php else: ?>
                        <div class="col-md-12">
                    <?php endif; ?>
                        <table class="table table-bordered user-details">
                            <tr>
                                <th>Username</th>
                                <td><?php echo htmlspecialchars($user['user_username']); ?></td>
                            </tr>
                            <tr>
                                <th>First Name</th>
                                <td><?php echo htmlspecialchars($user['user_first_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Middle Initial</th>
                                <td><?php echo htmlspecialchars($user['user_middle_initial']); ?></td>
                            </tr>
                            <tr>
                                <th>Last Name</th>
                                <td><?php echo htmlspecialchars($user['user_last_name']); ?></td>
                            </tr>
                            <tr>
                                <th>Suffix</th>
                                <td><?php echo htmlspecialchars($user['user_suffix']); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($user['user_email']); ?></td>
                            </tr>
                            <tr>
                                <th>Position</th>
                                <td><?php echo htmlspecialchars($user['user_position']); ?></td>
                            </tr>
                            <tr>
                                <th>Role</th>
                                <td><?php echo htmlspecialchars($user['role_name']); ?></td>
                            </tr>
                            <tr>
                                <th>ID Type</th>
                                <td><?php echo htmlspecialchars($user['user_id_type']); ?></td>
                            </tr>
                            <tr>
                                <th>ID Number</th>
                                <td><?php echo htmlspecialchars($user['user_id_number']); ?></td>
                            </tr>
                            <!-- Add more fields as necessary -->
                        </table>
                        <a href="../users-management/all-users.php" class="btn btn-secondary">Back to Users</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <!-- Include Popper.js before Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <!-- Bootstrap 4.5.2 JS -->
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Cropper.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.12/cropper.min.js"></script>
    <!-- bsCustomFileInput -->
    <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
    <!-- Custom Scripts -->
    <script src="../js/script-sidebar.js"></script>
</body>
</html>
