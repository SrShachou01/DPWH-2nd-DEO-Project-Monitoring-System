<?php
include '../includes/database.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Temporarily enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Keep logging errors
ini_set('log_errors', 1);
ini_set('error_log', '../pages/report.txt');

$user_id = $_SESSION['user_id'];  // Get the logged-in user ID
$role_id = $_SESSION['role_id'];  // Get the role ID of the user

$currentUserRoleId = $_SESSION['role_id'];

$db = ConnectDB();

// Prepare the base query
$query = "SELECT 
    p.proj_ID, 
    p.proj_cont_name, 
    p.proj_cont_loc, 
    p.proj_progress, 
    p.proj_description,
    TRIM(p.proj_status) AS proj_status, 
    p.proj_effect_date, 
    p.proj_NOA, 
    p.proj_NOP, 
    p.proj_expiry_date, 
    p.proj_cont_duration, 
    p.proj_unwork_days, 
    p.proj_cont_amt, 
    GROUP_CONCAT(c.cont_name SEPARATOR ', ') AS contractors, 
    p.proj_isApproved,
    p.user_ID AS uploader_id
FROM projects p 
LEFT JOIN project_contractors pc ON p.proj_ID = pc.proj_ID 
LEFT JOIN contractors c ON pc.cont_ID = c.cont_ID 
LEFT JOIN `contract-time-extension` cte ON p.proj_ID = cte.proj_ID
LEFT JOIN `project-collaborators` pc2 ON p.proj_ID = pc2.proj_ID  -- Renamed alias to avoid conflict
WHERE p.proj_isDeleted = 0";

// Modify the query based on the role_id
if ($role_id == 1) {
    // For role 1, display all projects (the deletion condition is already in the base query)
    // No need to modify further for role 1
} elseif ($role_id == 2) {
    // For role 2, display the user's projects and collaborations
    $query .= " AND (p.user_ID = ? OR pc2.user_ID = ?)";
}

// Group by project ID
$query .= " GROUP BY p.proj_ID";

// Order by project status as per your specified priority
$query .= " ORDER BY FIELD(p.proj_status, 'Ongoing', 'Not Yet Started', 'Completed', 'Suspended')";

// Prepare the statement
$stmt = $db->prepare($query);

// Bind parameters
if ($role_id == 2) {
    // For role 2, bind the user_id to the conditions where the user is either the uploader or a collaborator
    $stmt->bind_param("ii", $user_id, $user_id);  // Two user_id conditions
}

// Execute the statement
$stmt->execute();

// Get the result
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Project Reports</title>
    <!-- Bootstrap 4.5.2 CSS -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <style>
        .btn {
            border-radius: 20px;
        }

        .progress {
            height: 20px;
        }

        .table-container {
            flex: 1 1 auto;
            overflow-x: auto;
            overflow-y: auto;
            height: auto;
            position: relative;
        }

        .table td {
            word-wrap: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: normal;
            z-index: 10;
        }

        .table th, .table td {
            padding: 10px;
            vertical-align: top;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .table th {
            background-color: #E67040;
            color: #fff;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .table-container {
            overflow-x: auto; 
            overflow-y: visible;
            position: relative;
            flex: 1 1 auto;
        }

        .col-actions {
            width: 50px;
            min-width: 50px;
            overflow: visible;
        }

        .progress-bar {
            color: #fff;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.5);
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #000;
            font-weight: bold;
            text-shadow: 1px 1px 2px rgba(255, 255, 255, 0.7);
            pointer-events: none;
            word-wrap: break-word;
            white-space: normal;
        }

        .modal-header {
            background-color: #E67040;
            color: white;
        }

        .modal-footer {
            background-color: #E67040;
            color: white;
        }

        .modal-body {
            background: linear-gradient(to bottom right, #2C5B8C 42%, #AF8B84 85%);
            color: white;
        }

        .modal-content {
            background: linear-gradient(to bottom right, #2C5B8C 42%, #AF8B84 85%);
            color: white;
        }

        .btn-primary {
            background-color: #c15c34;
            border-color: #c15c34;
            color: white;
            text-align: center;
            justify-content: center;
        }

        .btn-primary:hover {
            background-color: orangered;
            border-color: orangered;
        }
    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid">
    <?php include "../includes/navbar.php"; ?>
    
    <!-- Button to trigger modal (Positioned at top right) -->
    <div class="d-flex justify-content-end mb-3 mt-3">
        <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#generateReportModal">
            Generate Report
        </button>
    </div>
    
    <!-- Modal for Report Filters -->
    <div class="modal fade" id="generateReportModal" tabindex="-1" role="dialog" aria-labelledby="generateReportModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="generateReportModalLabel">Generate Report</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="../project-management/generate-report-pdf.php" method="get">
                        <!-- Project Status Filter -->
                        <div class="form-group">
                            <label for="proj_status">Project Status</label>
                            <select name="proj_status" id="proj_status" class="form-control">
                                <option value="All">All Statuses</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Not Yet Started">Not Yet Started</option>
                                <option value="Completed">Completed</option>
                                <option value="Suspended">Suspended</option>
                            </select>
                        </div>
    
                        <!-- Report Type Filter -->
                        <div class="form-group">
                            <label for="report_type">Report Type</label>
                            <select name="report_type" id="report_type" class="form-control">
                                <option value="Daily">Daily Report</option>
                                <option value="Weekly">Weekly Report</option>
                                <option value="Monthly">Monthly Report</option>
                                <option value="Annual">Annual Report</option>
                            </select>
                        </div>
    
                        <!-- Submit Button -->
                        <button type="submit" class="btn btn-primary btn-block">Generate Report</button>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <div class="table-container mt-3">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Project ID</th>
                    <th>Project Name / Contractors</th>
                    <th>Status / Milestones</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()) { ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['proj_ID']); ?></td>
                        <td>
                            <?php echo htmlspecialchars($row['proj_cont_name']); ?><br>
                            <strong>Contractor(s):</strong>
                            <ul>
                                <?php 
                                    $contractors = explode(',', $row['contractors']);
                                    foreach ($contractors as $contractor) {
                                        echo '<li>' . htmlspecialchars(trim($contractor)) . '</li>';
                                    }
                                ?>
                            </ul>
                        </td>
                        <td>
                            <strong>Status:</strong> <?php echo htmlspecialchars($row['proj_status']); ?><br>
                            <strong>Start Date:</strong> <?php echo htmlspecialchars($row['proj_NOA']); ?><br>
                            <strong>End Date:</strong> <?php echo htmlspecialchars($row['proj_expiry_date']); ?>
                        </td>
                        <td class="col-actions">
                            <a href="../project-management/generate-report.php?proj_ID=<?php echo urlencode($row['proj_ID']); ?>" class="btn btn-primary" target="_blank" data-toggle="tooltip" data-placement="right" title="Generate Report">
                                <i class="fas fa-file"></i>
                            </a>
                        </td>
                    </tr>
                <?php } ?>
                <?php if ($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="4" class="text-center">No projects found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- JavaScript dependencies -->
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../js/script-for-main.js"></script>
<script src="../js/script-sidebar.js"></script>
<script>
    $(function () {
        $('[data-toggle="tooltip"]').tooltip()
    })
</script>
</body>
</html>
