<?php
include '../includes/database.php';
session_start();
$db = ConnectDB();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Query to get soft-deleted projects
$query = "SELECT proj_ID, proj_cont_name FROM projects WHERE proj_isDeleted = 1 ORDER BY proj_ID ASC";
$result = $db->query($query);

// Output the table rows with deleted projects
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['proj_ID']) . "</td>";
        echo "<td>" . htmlspecialchars($row['proj_cont_name']) . "</td>";
        echo "<td>
                <button type='button' class='btn btn-warning restore-project-btn' data-id='" . htmlspecialchars($row['proj_ID']) . "'>Restore</button>
              </td>";
        echo "</tr>";
    }
} else {
    echo "<tr><td colspan='3'>No deleted projects found.</td></tr>";
}
?>
