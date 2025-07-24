<?php

include '../includes/database.php';
session_start();
$db = ConnectDB();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit();
}

// Retrieve the prog_ID from GET parameters
$prog_ID = isset($_GET['prog_ID']) ? $_GET['prog_ID'] : '';

if (empty($prog_ID)) {
    echo json_encode(['success' => false, 'message' => 'Invalid progress ID']);
    exit();
}

// Fetch progress details from the database
$query = "SELECT prog_ID, prog_date, prog_desc, prog_percentage, prog_issue, prog_photos, proj_ID
          FROM progress WHERE prog_ID = ?";
$stmt = $db->prepare($query);
$stmt->bind_param('s', $prog_ID);
$stmt->execute();
$stmt->bind_result($prog_ID, $prog_date, $prog_desc, $prog_percentage, $prog_issue, $prog_photos, $proj_ID);

// Check if the progress entry was found
if ($stmt->fetch()) {
    $progress_data = [
        'success' => true,
        'progress' => [
            'prog_ID' => $prog_ID,
            'prog_date' => $prog_date,
            'prog_desc' => $prog_desc,
            'prog_percentage' => $prog_percentage,
            'prog_issue' => $prog_issue,
            'prog_photos' => $prog_photos,
            'proj_ID' => $proj_ID
        ]
    ];
    echo json_encode($progress_data);
} else {
    echo json_encode(['success' => false, 'message' => 'Progress not found']);
}

$stmt->close();
