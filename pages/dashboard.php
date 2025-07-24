<?php
// dashboard.php
include '../includes/database.php';
session_start();
$db = ConnectDB();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];

// Define queries for all projects (Admins and Guests)
$totalProjectsQuery = "SELECT COUNT(*) as total_count FROM projects WHERE proj_isDeleted = 0";
$ongoingQuery = "SELECT COUNT(*) as ongoing_count FROM projects WHERE proj_status = 'Ongoing' AND proj_isDeleted = 0";
$completedQuery = "SELECT COUNT(*) as completed_count FROM projects WHERE proj_status = 'Completed' AND proj_isDeleted = 0";
$terminatedQuery = "SELECT COUNT(*) as terminated_count FROM projects WHERE proj_status = 'Terminated' AND proj_isDeleted = 0";
$notYetStartedQuery = "SELECT COUNT(*) as not_yet_started_count FROM projects WHERE proj_status = 'Not Yet Started' AND proj_isDeleted = 0";

// Define queries for user-specific projects (role_id = 2 or others if needed)
$totalUserProjectsQuery = "
SELECT COUNT(DISTINCT p.proj_ID) as total_count 
FROM projects p
LEFT JOIN `project-collaborators` pc ON p.proj_ID = pc.proj_ID
WHERE p.proj_isDeleted = 0 AND (p.user_ID = ? OR pc.user_ID = ?)
";
$ongoingUserQuery = "SELECT COUNT(*) as ongoing_count FROM projects WHERE proj_status = 'Ongoing' AND proj_isDeleted = 0 AND (user_id = ? OR proj_ID IN (SELECT proj_ID FROM `project-collaborators` WHERE user_ID = ?))";
$completedUserQuery = "SELECT COUNT(*) as completed_count FROM projects WHERE proj_status = 'Completed' AND proj_isDeleted = 0 AND (user_id = ? OR proj_ID IN (SELECT proj_ID FROM `project-collaborators` WHERE user_ID = ?))";
$terminatedUserQuery = "SELECT COUNT(*) as terminated_count FROM projects WHERE proj_status = 'Terminated' AND proj_isDeleted = 0 AND (user_id = ? OR proj_ID IN (SELECT proj_ID FROM `project-collaborators` WHERE user_ID = ?))";
$notYetStartedUserQuery = "SELECT COUNT(*) as not_yet_started_count FROM projects WHERE proj_status = 'Not Yet Started' AND proj_isDeleted = 0 AND (user_id = ? OR proj_ID IN (SELECT proj_ID FROM `project-collaborators` WHERE user_ID = ?))";

function executeQuery($db, $query, $user_id = null) {
    $stmt = $db->prepare($query);
    if ($user_id !== null) {
        $stmt->bind_param("ss", $user_id, $user_id); // Bind user_id for non-admin/guest users
    }
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $result;
}

// Check if the user is Admin or Guest
if ($role_id == 1 || $role_id == 3 || $role_id == 2) {
    // Admin and Guest: Get total count of all projects
    $resultTotalProjects = executeQuery($db, $totalProjectsQuery);
    
    // Get the count of projects for each status (no user filter needed)
    $resultOngoing = executeQuery($db, $ongoingQuery);
    $resultCompleted = executeQuery($db, $completedQuery);
    $resultTerminated = executeQuery($db, $terminatedQuery);
    $resultNotYetStarted = executeQuery($db, $notYetStartedQuery);
} else {
    // For users with role_id 2, filter by their user_id and also consider collaborations
    $resultTotalProjects = executeQuery($db, $totalUserProjectsQuery, $user_id);
    $resultOngoing = executeQuery($db, $ongoingUserQuery, $user_id);
    $resultCompleted = executeQuery($db, $completedUserQuery, $user_id);
    $resultTerminated = executeQuery($db, $terminatedUserQuery, $user_id);
    $resultNotYetStarted = executeQuery($db, $notYetStartedUserQuery, $user_id);
}

// Progress query (for Admins and Guests: all projects; others: user-specific)
if ($role_id == 1 || $role_id == 3 || $role_id == 2) {
    $progressQuery = "
    SELECT 
        p.proj_ID, 
        CONCAT(u.user_first_name, ' ', u.user_last_name) AS Fullname, 
        p.proj_cont_name, 
        p.proj_progress,
        GROUP_CONCAT(CONCAT(u_c.user_first_name, ' ', u_c.user_last_name) SEPARATOR ', ') AS collaborators
    FROM projects p
    INNER JOIN users u ON p.user_id = u.user_id
    LEFT JOIN `project-collaborators` pc ON p.proj_ID = pc.proj_ID
    LEFT JOIN users u_c ON pc.user_ID = u_c.user_id
    WHERE p.proj_isDeleted = 0
    GROUP BY p.proj_ID 
    ORDER BY p.proj_uploaded DESC, p.proj_progress DESC
    ";
    
    // Admins and Guests: No need to bind parameters
    $stmt = $db->prepare($progressQuery);
} else {
    $progressQuery = "
    SELECT 
        p.proj_ID, 
        CONCAT(u.user_first_name, ' ', u.user_last_name) AS Fullname, 
        p.proj_cont_name, 
        p.proj_progress,
        GROUP_CONCAT(CONCAT(u_c.user_first_name, ' ', u_c.user_last_name) SEPARATOR ', ') AS collaborators
    FROM projects p
    INNER JOIN users u ON p.user_id = u.user_id
    LEFT JOIN `project-collaborators` pc ON p.proj_ID = pc.proj_ID
    LEFT JOIN users u_c ON pc.user_ID = u_c.user_id
    WHERE p.proj_isDeleted = 0 AND (p.user_id = ? OR pc.user_ID = ?)
    GROUP BY p.proj_ID 
    ORDER BY p.proj_uploaded DESC, p.proj_progress DESC
    ";
    
    // For users with role_id 2: Filter by user_id
    $stmt = $db->prepare($progressQuery);
    $stmt->bind_param("ss", $user_id, $user_id);
}

// Execute the progress query
$stmt->execute();
$progressResult = $stmt->get_result();
$progressData = [];

if ($progressResult && $progressResult->num_rows > 0) {
    while ($row = $progressResult->fetch_assoc()) {
        $progressData[] = $row;
    }
} else {
    $progressData[] = [
        'proj_ID' => 'N/A', 
        'Fullname' => 'N/A', 
        'proj_cont_name' => 'N/A', 
        'proj_progress' => 0,
        'collaborators' => 'None' // Handle no collaborators
    ];
}
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <!-- Bootstrap CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 5.15.3 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Custom Styles for Dashboard -->
    <link rel="stylesheet" href="../css/styles-for-dashboard.css">
    <link href="../css/styles-for-main.css" rel="stylesheet">
</head>
<body>
    <?php include "../includes/sidebar.php"; ?>

    <div id="content" class="container-fluid">
        <?php include "../includes/navbar.php"; ?>
        <div class="cards-row mt-2 pt-2" style="padding-right: 40px;">
            <!-- Total Projects Card -->
            <a href="../pages/projects.php?status=all" class="col-md-2 mb-3">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <i class="fas fa-project-diagram card-logo"></i>
                        <h5 class="card-title">Total</h5>
                        <p class="card-text"><?php echo htmlspecialchars($resultTotalProjects['total_count']); ?></p>
                    </div>
                </div>
            </a>

            <!-- Ongoing Projects Card -->
            <a href="../pages/projects.php?status=ongoing" class="col-md-2 mb-3">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <i class="fas fa-play-circle card-logo"></i>
                        <h5 class="card-title">Ongoing</h5>
                        <p class="card-text"><?php echo htmlspecialchars($resultOngoing['ongoing_count']); ?></p>
                    </div>
                </div>
            </a>

            <!-- Completed Projects Card -->
            <a href="../pages/projects.php?status=completed" class="col-md-2 mb-3">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <i class="fas fa-check-circle card-logo"></i>
                        <h5 class="card-title">Completed</h5>
                        <p class="card-text"><?php echo htmlspecialchars($resultCompleted['completed_count']); ?></p>
                    </div>
                </div>
            </a>

            <!-- Not Yet Started Projects Card -->
            <a href="../pages/projects.php?status=not-yet-started" class="col-md-2 mb-3">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <i class="fas fa-hourglass-start card-logo"></i>
                        <h5 class="card-title">Not Started</h5>
                        <p class="card-text"><?php echo htmlspecialchars($resultNotYetStarted['not_yet_started_count']); ?></p>
                    </div>
                </div>
            </a>

            <!-- Suspended Projects Card -->
            <a href="../pages/projects.php?status=suspended" class="col-md-2 mb-3">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <i class="fas fa-ban card-logo"></i>
                        <h5 class="card-title">Suspended</h5>
                        <p class="card-text"><?php echo htmlspecialchars($resultTerminated['terminated_count']); ?></p>
                    </div>
                </div>
            </a>
        </div>

        <!-- Progress Table -->
        <div class="custom-table mt-2">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th style="width: 10%;">Project ID</th>
                        <th style="width: 30%;">Project Title</th>
                        <th style="width: 15%;">Uploader</th>
                        <th style="width: 25%;">Collaborators</th> <!-- New Column -->
                        <th style="width: 10%;">Progress</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($progressData as $project) { ?>
                        <tr>
                            <td><?php echo htmlspecialchars($project['proj_ID']); ?></td>
                            <td><?php echo htmlspecialchars($project['proj_cont_name']); ?></td>
                            <td><?php echo htmlspecialchars($project['Fullname']); ?></td>
                            <td>
                                <?php 
                                // Display collaborators or 'None' if there are none
                                echo !empty($project['collaborators']) ? htmlspecialchars($project['collaborators']) : 'None';
                                ?>
                            </td>
                            <td>
                                <?php 
                                // Cast progress to an integer and check if it's zero
                                $progress = (int)$project['proj_progress'];
                                echo $progress == 0 ? "0%" : $progress . '%';
                                ?>
                            </td>
                        </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Bootstrap JS and dependencies -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Include your custom JS -->
    <script src="../js/script-for-main.js"></script>
    <script src="../js/script-sidebar.js"></script>

    <script>
        $(document).ready(function () {
            bsCustomFileInput.init();
            // Initialize Bootstrap tooltips
            $('[data-toggle="tooltip"]').tooltip();
        });
    </script>
</body>
</html>
