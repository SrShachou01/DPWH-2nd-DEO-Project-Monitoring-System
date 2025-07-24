<?php
include '../includes/database.php';
$db = ConnectDB();

function calculateLineSubtotal($proj_ID, $db) {
    // Count the number of progress entries for the project
    $progressCountQuery = "SELECT COUNT(*) as progress_count, COUNT(DISTINCT prog_date) as progress_days FROM progress WHERE proj_ID = ?";
    $stmt = $db->prepare($progressCountQuery);
    $stmt->bind_param("s", $proj_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $progressData = $result->fetch_assoc();

    $progressCount = $progressData['progress_count'];
    $progressDays = $progressData['progress_days'];

    // Avoid division by zero
    if ($progressDays == 0) {
        return 0;
    }

    // Calculate line_subtotal
    $line_subtotal = $progressCount / $progressDays;
    return $line_subtotal;
}

function calculateProjectProgress($proj_ID, $db) {
    // Get project contract duration
    $query = "SELECT proj_cont_duration FROM projects WHERE proj_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param("s", $proj_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    $project = $result->fetch_assoc();
    
    $contractDuration = $project['proj_cont_duration'];
    
    // Avoid division by zero
    if ($contractDuration == 0) {
        return 0;
    }

    // Calculate line_subtotal using the function
    $line_subtotal = calculateLineSubtotal($proj_ID, $db);

    // Calculate project progress
    $proj_progress = $line_subtotal + ($line_subtotal / $contractDuration);
    return $proj_progress;
}

// Fetch all projects
$query = "SELECT proj_ID FROM projects WHERE proj_isDeleted = 0";
$stmt = $db->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $proj_ID = $row['proj_ID'];

    // Calculate the project progress
    $proj_progress = calculateProjectProgress($proj_ID, $db);

    // Update the project progress in the database
    $updateQuery = "UPDATE projects SET proj_progress = ? WHERE proj_ID = ?";
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->bind_param("ds", $proj_progress, $proj_ID);
    $updateStmt->execute();
}

echo "Project progress calculated and updated successfully.";

?>
