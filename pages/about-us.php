<?php
include '../includes/database.php';
session_start();
$db = ConnectDB();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Retrieve the current user's role_id from the session
if (isset($_SESSION['role_id'])) {
    $currentUserRoleId = $_SESSION['role_id'];
} else {
    // Optionally, you can fetch the role_id from the database based on user_id
    // For simplicity, we'll set it to a default value (e.g., 3 - Guest)
    // You should adjust this according to your application's logic
    $currentUserRoleId = 3;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>About Us</title>
    <!-- Bootstrap 4.5.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        .about-container {
            margin: 50px auto;
            padding: 30px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            max-width: 800px;
        }
        .about-header {
            text-align: center;
            margin-bottom: 30px;
        }
        .about-header h2 {
            font-weight: 600;
            color: #333;
        }
        .about-section {
            margin-bottom: 30px;
        }
        .about-section h3 {
            font-size: 1.5rem;
            color: #007bff;
            margin-bottom: 15px;
        }
        .about-section p {
            font-size: 1.1rem;
            line-height: 1.6;
            color: #555;
        }
        .core-values ul {
            list-style: none;
            padding: 0;
        }
        .core-values ul li {
            font-size: 1.1rem;
            margin-bottom: 10px;
            color: #555;
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid">
<?php include "../includes/navbar.php"; ?>
    <!-- About Us Content -->
    <div class="about-container">
        <div class="about-header">
            <h2>About Us</h2>
        </div>
        <div class="about-section">
            <h3>Mission</h3>
            <p>
                To provide and manage quality infrastructure facilities and services responsive to the needs of the Filipino people in the pursuit of national development objectives.
            </p>
        </div>

        <div class="about-section">
            <h3>Vision</h3>
            <p>
                By 2040, DPWH is an excellent government agency, enabling a comfortable life for Filipinos through safe, reliable, and resilient infrastructure.
            </p>
        </div>

        <div class="about-section core-values">
            <h3>Core Values</h3>
            <ul>
                <li><strong>Public Service</strong></li>
                <li><strong>Integrity</strong></li>
                <li><strong>Professionalism</strong></li>
                <li><strong>Excellence</strong></li>
                <li><strong>Teamwork</strong></li>
            </ul>
        </div>
    </div>
</div>

<!-- JavaScript dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<!-- Custom Scripts -->
<script src="../js/script-for-main.js"></script>
<script src="../js/script-sidebar.js"></script>
</body>
</html>
