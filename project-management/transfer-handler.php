<?php
include '../includes/database.php';  // Include your DB connection script

$proj_ID = $_POST['proj_ID'];
$newOwner_ID = $_POST['newOwner_ID'];

$db = ConnectDB();  // Establish database connection

$sql = "UPDATE projects SET user_ID = ? WHERE proj_ID = ?";
$stmt = $db->prepare($sql);
if ($stmt) {
    $stmt->bind_param('is', $newOwner_ID, $proj_ID);
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Project transferred successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error transferring project']);
    }
    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $db->error]);
}
$db->close();
?>
