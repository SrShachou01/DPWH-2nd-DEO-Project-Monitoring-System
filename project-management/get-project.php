<?php
include '../includes/database.php';
$db = ConnectDB();

if (isset($_GET['id'])) {
    $proj_ID = $_GET['id'];
    $query = "SELECT * FROM projects WHERE proj_ID = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $proj_ID);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $project = $result->fetch_assoc();
        echo json_encode($project);
    } else {
        echo json_encode(['error' => 'Project not found.']);
    }
    
    $stmt->close();
    $db->close();
} else {
    echo json_encode(['error' => 'No project ID provided.']);
}
?>
