<?php
// get-contractors-project.php

require_once "../includes/database.php";
header('Content-Type: application/json');
session_start();
// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_GET['contractor_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Contractor ID is missing.'
    ]);
    exit();
}

$contractor_id = intval($_GET['contractor_id']);
$db = ConnectDB();

// Fetch Completed Projects (proj_status = 'Completed' and proj_isDeleted = 0)
$query_completed = "
    SELECT p.proj_ID, p.proj_cont_loc
    FROM projects p
    JOIN project_contractors pc ON p.proj_ID = pc.proj_ID
    WHERE pc.cont_ID = ? AND p.proj_status = 'Completed' AND p.proj_isDeleted = 0";
$stmt_completed = $db->prepare($query_completed);
$stmt_completed->bind_param("i", $contractor_id);
$stmt_completed->execute();
$result_completed = $stmt_completed->get_result();

if ($result_completed === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching completed projects: ' . $stmt_completed->error
    ]);
    exit();
}

$completed_projects = [];
while ($row = $result_completed->fetch_assoc()) {
    $completed_projects[] = $row;
}
$stmt_completed->close();

// Fetch Delayed Projects (proj_status = 'Suspended' or 'Ongoing' and proj_isDeleted = 0)
$query_delayed = "
    SELECT p.proj_ID, p.proj_cont_loc
    FROM projects p
    JOIN project_contractors pc ON p.proj_ID = pc.proj_ID
    WHERE pc.cont_ID = ? AND (p.proj_status = 'Suspended' OR p.proj_status = 'Ongoing') AND p.proj_isDeleted = 0";
$stmt_delayed = $db->prepare($query_delayed);
$stmt_delayed->bind_param("i", $contractor_id);
$stmt_delayed->execute();
$result_delayed = $stmt_delayed->get_result();

if ($result_delayed === false) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching delayed projects: ' . $stmt_delayed->error
    ]);
    exit();
}

$delayed_projects = [];
while ($row = $result_delayed->fetch_assoc()) {
    $delayed_projects[] = $row;
}
$stmt_delayed->close();

echo json_encode([
    'success' => true,
    'completed_projects' => $completed_projects,
    'delayed_projects' => $delayed_projects
]);

?>
