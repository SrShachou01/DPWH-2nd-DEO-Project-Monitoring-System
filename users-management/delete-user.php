<?php
// users-management/delete-user.php

include "../includes/database.php";
session_start();

// Error reporting settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/meows.txt');

// Ensure the user is an admin (role_id == 1)
if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] != 1) {
    // Redirect to dashboard or show an error message
    header("Location: ../pages/dashboard.php");
    exit();
}

$admin_id = $_SESSION['user_id']; // Admin's user ID

// Check if the form is submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Establish the database connection
    $conn = ConnectDB();

    // Retrieve and sanitize the user ID
    if (isset($_POST['user_id']) && is_numeric($_POST['user_id'])) {
        $user_id = intval($_POST['user_id']);
    } else {
        $_SESSION['error_message'] = "Invalid user ID.";
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Prevent deletion of the currently logged-in admin
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
        header("Location: ../users-management/all-users.php");
        exit();
    }

    // Begin Transaction
    mysqli_begin_transaction($conn);

    try {
        // Step 1: Retrieve all projects created by the user
        $projectsQuery = "SELECT proj_ID FROM projects WHERE user_id = ?";
        $stmtProjects = mysqli_prepare($conn, $projectsQuery);
        mysqli_stmt_bind_param($stmtProjects, "i", $user_id);
        mysqli_stmt_execute($stmtProjects);
        $resultProjects = mysqli_stmt_get_result($stmtProjects);

        $projectIDs = [];
        while ($row = mysqli_fetch_assoc($resultProjects)) {
            $projectIDs[] = $row['proj_ID'];
        }
        mysqli_stmt_close($stmtProjects);

        // Step 2: Check each project and handle accordingly
        foreach ($projectIDs as $proj_ID) {
            // Check if the project has associated records (progress, documents)
            $checkRecordsQuery = "
                SELECT
                    COUNT(p.proj_ID) AS progress_count,
                    COUNT(cm.proj_ID) AS manpower_count,
                    COUNT(cte.proj_ID) AS time_extension_count,
                    COUNT(cwr.proj_ID) AS work_resumption_count,
                    COUNT(cws.proj_ID) AS work_suspension_count,
                    COUNT(fc.proj_ID) AS final_completion_count,
                    COUNT(iom.proj_ID) AS office_manpower_count,
                    COUNT(mtsr.proj_ID) AS suspension_report_count,
                    COUNT(vo.proj_ID) AS variation_orders_count
                FROM
                    `progress` p
                LEFT JOIN
                    `contract-manpower` cm ON cm.proj_ID = p.proj_ID
                LEFT JOIN
                    `contract-time-extension` cte ON cte.proj_ID = p.proj_ID
                LEFT JOIN
                    `contract-work-resumption` cwr ON cwr.proj_ID = p.proj_ID
                LEFT JOIN
                    `contract-work-suspension` cws ON cws.proj_ID = p.proj_ID
                LEFT JOIN
                    `final-completion` fc ON fc.proj_ID = p.proj_ID
                LEFT JOIN
                    `implementing-office-manpower` iom ON iom.proj_ID = p.proj_ID
                LEFT JOIN
                    `monthly-time-suspension-report` mtsr ON mtsr.proj_ID = p.proj_ID
                LEFT JOIN
                    `variation-orders` vo ON vo.proj_ID = p.proj_ID
                WHERE
                    p.proj_ID = ?
            ";

            $stmtCheckRecords = mysqli_prepare($conn, $checkRecordsQuery);
            mysqli_stmt_bind_param($stmtCheckRecords, "i", $proj_ID);
            mysqli_stmt_execute($stmtCheckRecords);
            mysqli_stmt_bind_result($stmtCheckRecords, $progress_count, $manpower_count, $time_extension_count, $work_resumption_count, $work_suspension_count, $final_completion_count, $office_manpower_count, $suspension_report_count, $variation_orders_count);
            mysqli_stmt_fetch($stmtCheckRecords);
            mysqli_stmt_close($stmtCheckRecords);

            // Step 3: If records exist (progress or documents), transfer the data to admin
            if ($progress_count > 0 || $manpower_count > 0 || $time_extension_count > 0 || $work_resumption_count > 0 || $work_suspension_count > 0 || $final_completion_count > 0 || $office_manpower_count > 0 || $suspension_report_count > 0 || $variation_orders_count > 0) {
                // Transfer the progress records
                $updateProgressQuery = "UPDATE `progress` SET user_id = ? WHERE proj_ID = ?";
                $stmtUpdateProgress = mysqli_prepare($conn, $updateProgressQuery);
                mysqli_stmt_bind_param($stmtUpdateProgress, "is", $admin_id, $proj_ID);
                mysqli_stmt_execute($stmtUpdateProgress);
                mysqli_stmt_close($stmtUpdateProgress);

                // Transfer the contract-manpower records
                $updateContractManpowerQuery = "UPDATE `contract-manpower` SET user_id = ? WHERE proj_ID = ?";
                $stmtUpdateContractManpower = mysqli_prepare($conn, $updateContractManpowerQuery);
                mysqli_stmt_bind_param($stmtUpdateContractManpower, "is", $admin_id, $proj_ID);
                mysqli_stmt_execute($stmtUpdateContractManpower);
                mysqli_stmt_close($stmtUpdateContractManpower);

                // Repeat the update for other related tables (contract-time-extension, etc.)
                $tablesToUpdate = [
                    'contract-time-extension', 'contract-work-resumption', 'contract-work-suspension',
                    'final-completion', 'implementing-office-manpower', 'monthly-time-suspension-report', 'variation-orders'
                ];

                foreach ($tablesToUpdate as $table) {
                    $updateQuery = "UPDATE `$table` SET user_id = ? WHERE proj_ID = ?";
                    $stmtUpdate = mysqli_prepare($conn, $updateQuery);
                    mysqli_stmt_bind_param($stmtUpdate, "is", $admin_id, $proj_ID);
                    mysqli_stmt_execute($stmtUpdate);
                    mysqli_stmt_close($stmtUpdate);
                }
            }

            // Step 4: If no records exist (progress and documents), just transfer the project
            if ($progress_count == 0 && $manpower_count == 0 && $time_extension_count == 0 && $work_resumption_count == 0 && $work_suspension_count == 0 && $final_completion_count == 0 && $office_manpower_count == 0 && $suspension_report_count == 0 && $variation_orders_count == 0) {
                // Transfer the project to admin (set user_id to admin)
                $updateProjectQuery = "UPDATE `projects` SET user_id = ? WHERE proj_ID = ?";
                $stmtUpdateProject = mysqli_prepare($conn, $updateProjectQuery);
                mysqli_stmt_bind_param($stmtUpdateProject, "is", $admin_id, $proj_ID);
                mysqli_stmt_execute($stmtUpdateProject);
                mysqli_stmt_close($stmtUpdateProject);
            }
        }

        // Step 5: Delete the user from the users table
        $deleteUserQuery = "DELETE FROM `users` WHERE user_id = ?";
        $stmtDeleteUser = mysqli_prepare($conn, $deleteUserQuery);
        mysqli_stmt_bind_param($stmtDeleteUser, "i", $user_id);
        mysqli_stmt_execute($stmtDeleteUser);

        if (mysqli_stmt_affected_rows($stmtDeleteUser) === 0) {
            throw new Exception("User not found or already deleted.");
        }

        mysqli_stmt_close($stmtDeleteUser);

        // Commit the transaction
        mysqli_commit($conn);

        // Step 6: Delete the user's profile picture if it's not the default
        $fetchPhotoQuery = "SELECT user_photo FROM `users` WHERE user_id = ?";
        $stmtFetchPhoto = mysqli_prepare($conn, $fetchPhotoQuery);
        mysqli_stmt_bind_param($stmtFetchPhoto, "i", $user_id);
        mysqli_stmt_execute($stmtFetchPhoto);
        mysqli_stmt_bind_result($stmtFetchPhoto, $user_photo);
        mysqli_stmt_fetch($stmtFetchPhoto);
        mysqli_stmt_close($stmtFetchPhoto);

        if (!empty($user_photo) && $user_photo !== '../images/default-pic.jpg' && file_exists($user_photo)) {
            unlink($user_photo);
        }

        // Set success message
        $_SESSION['success_message'] = "User and all associated data transferred to admin successfully.";

    } catch (Exception $e) {
        // Rollback the transaction on error
        mysqli_rollback($conn);
        $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();

        // Optionally, delete the uploaded image if it was uploaded before the error
        if (isset($user_photo) && $user_photo !== '../images/default-pic.jpg' && file_exists($user_photo)) {
            unlink($user_photo);
        }
    }

    // Close the database connection
    mysqli_close($conn);

    // Redirect back to the users management page
    header("Location: ../users-management/all-users.php");
    exit();
} else {
    // If not a POST request, redirect to the users management page
    header("Location: ../users-management/all-users.php");
    exit();
}
?>
