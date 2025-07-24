<?php
// users-management/get-user-details.php

include "../includes/database.php";
session_start();

// Check if the request is via AJAX and the user is logged in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['role_id'])) {
    // Retrieve and sanitize the user_id from POST data
    $user_id = intval($_POST['user_id']);

    // Establish the database connection
    $conn = ConnectDB();

    // Prepare and execute the query to fetch user information
    $userQuery = "SELECT 
                    users.user_id, 
                    users.user_username, 
                    users.user_first_name, 
                    users.user_middle_initial, 
                    users.user_last_name, 
                    users.user_suffix, 
                    users.user_email, 
                    users.user_position, 
                    users.user_id_type, 
                    users.user_id_number, 
                    users.user_photo, 
                    roles.role_id, 
                    roles.role_name 
                  FROM users 
                  LEFT JOIN roles ON users.role_id = roles.role_ID
                  WHERE users.user_id = ?";
    $stmtUser = mysqli_prepare($conn, $userQuery);
    mysqli_stmt_bind_param($stmtUser, "i", $user_id);
    mysqli_stmt_execute($stmtUser);
    $userResult = mysqli_stmt_get_result($stmtUser);

    if ($userResult && mysqli_num_rows($userResult) > 0) {
        $user = mysqli_fetch_assoc($userResult);

        // Fetch projects created by the user
        $createdProjectsQuery = "SELECT 
                                    proj_ID, 
                                    proj_progress, 
                                    proj_cont_name, 
                                    proj_status, 
                                    proj_uploaded 
                                FROM projects 
                                WHERE user_ID = ? AND proj_isDeleted = 0";
        $stmtCreated = mysqli_prepare($conn, $createdProjectsQuery);
        mysqli_stmt_bind_param($stmtCreated, "i", $user_id);
        mysqli_stmt_execute($stmtCreated);
        $createdProjectsResult = mysqli_stmt_get_result($stmtCreated);

        $createdProjects = [];
        if ($createdProjectsResult) {
            while ($row = mysqli_fetch_assoc($createdProjectsResult)) {
                $createdProjects[] = $row;
            }
        }

        // Fetch projects the user is collaborating on
        $collabProjectsQuery = "SELECT 
                                    p.proj_ID, 
                                    p.proj_progress, 
                                    p.proj_cont_name, 
                                    p.proj_status, 
                                    p.proj_uploaded 
                                FROM `project-collaborators` pc
                                JOIN projects p ON pc.proj_ID = p.proj_ID
                                WHERE pc.user_ID = ? AND p.proj_isDeleted = 0";
        $stmtCollab = mysqli_prepare($conn, $collabProjectsQuery);
        mysqli_stmt_bind_param($stmtCollab, "i", $user_id);
        mysqli_stmt_execute($stmtCollab);
        $collabProjectsResult = mysqli_stmt_get_result($stmtCollab);

        $collabProjects = [];
        if ($collabProjectsResult) {
            while ($row = mysqli_fetch_assoc($collabProjectsResult)) {
                $collabProjects[] = $row;
            }
        }

        // Prepare the response
        $response = [
            'status' => 'success',
            'user' => $user,
            'created_projects' => $createdProjects,
            'collab_projects' => $collabProjects
        ];

        // Return JSON response
        header('Content-Type: application/json');
        echo json_encode($response);
    } else {
        // User not found
        $response = [
            'status' => 'error',
            'message' => 'User not found.'
        ];
        header('Content-Type: application/json');
        echo json_encode($response);
    }

    // Close statements and connection
    mysqli_stmt_close($stmtUser);
    if (isset($stmtCreated)) mysqli_stmt_close($stmtCreated);
    if (isset($stmtCollab)) mysqli_stmt_close($stmtCollab);

    mysqli_close($conn); 
} else {
    // Invalid request
    $response = [
        'status' => 'error',
        'message' => 'Invalid request.'
    ];
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
