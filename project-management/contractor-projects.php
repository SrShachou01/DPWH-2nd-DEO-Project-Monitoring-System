<?php
require_once "../includes/database.php";
$db = ConnectDB();

if (isset($_GET['cont_ID'])) {
    $contractorId = $_GET['cont_ID'];

    // Fetch completed projects
    $query_completed = "SELECT * FROM projects WHERE cont_ID = ? AND proj_status = 'Completed' AND proj_isDeleted = 0";
    $stmt_completed = $db->prepare($query_completed);
    $stmt_completed->bind_param("i", $contractorId);
    $stmt_completed->execute();
    $result_completed = $stmt_completed->get_result();
    $completed_projects = $result_completed->fetch_all(MYSQLI_ASSOC);

    // Fetch all projects of the contractor
    $query_projects = "SELECT proj_ID FROM projects WHERE cont_ID = ? AND proj_isDeleted = 0";
    $stmt_projects = $db->prepare($query_projects);
    $stmt_projects->bind_param("i", $contractorId);
    $stmt_projects->execute();
    $result_projects = $stmt_projects->get_result();
    $project_ids = [];
    while ($row = $result_projects->fetch_assoc()) {
        $project_ids[] = $row['proj_ID'];
    }

    $delayed_projects = [];
    if (!empty($project_ids)) {
        // Build the IN clause placeholders
        $placeholders = implode(',', array_fill(0, count($project_ids), '?'));
        $types = str_repeat('s', count($project_ids));

        // Prepare the statement for contract-work-suspension
        $query_delays = "SELECT DISTINCT proj_ID FROM `contract-work-suspension` WHERE proj_ID IN ($placeholders)";
        $stmt_delays = $db->prepare($query_delays);
        $stmt_delays->bind_param($types, ...$project_ids);
        $stmt_delays->execute();
        $result_delays = $stmt_delays->get_result();
        $delayed_project_ids = [];
        while ($row = $result_delays->fetch_assoc()) {
            $delayed_project_ids[] = $row['proj_ID'];
        }

        // Prepare the statement for contract-work-resumption
        $query_delays_resumption = "SELECT DISTINCT proj_ID FROM `contract-work-resumption` WHERE proj_ID IN ($placeholders)";
        $stmt_delays_resumption = $db->prepare($query_delays_resumption);
        $stmt_delays_resumption->bind_param($types, ...$project_ids);
        $stmt_delays_resumption->execute();
        $result_delays_resumption = $stmt_delays_resumption->get_result();
        while ($row = $result_delays_resumption->fetch_assoc()) {
            if (!in_array($row['proj_ID'], $delayed_project_ids)) {
                $delayed_project_ids[] = $row['proj_ID'];
            }
        }

        // Get the details of the delayed projects
        if (!empty($delayed_project_ids)) {
            $delayed_placeholders = implode(',', array_fill(0, count($delayed_project_ids), '?'));
            $types_delayed = str_repeat('s', count($delayed_project_ids));
            $query_delayed_projects = "SELECT * FROM projects WHERE proj_ID IN ($delayed_placeholders) AND proj_isDeleted = 0";
            $stmt_delayed_projects = $db->prepare($query_delayed_projects);
            $stmt_delayed_projects->bind_param($types_delayed, ...$delayed_project_ids);
            $stmt_delayed_projects->execute();
            $result_delayed_projects = $stmt_delayed_projects->get_result();
            $delayed_projects = $result_delayed_projects->fetch_all(MYSQLI_ASSOC);
        }
    }

    // Return the data as JSON
    echo json_encode([
        'completed_projects' => $completed_projects,
        'delayed_projects' => $delayed_projects
    ]);
} else {
    echo json_encode(['error' => 'No contractor ID provided.']);
}
?>
