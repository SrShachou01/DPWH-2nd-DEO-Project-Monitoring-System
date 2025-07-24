<?php
session_start();
include '../includes/database.php';

// Define a constant for the alert prefix
define('ALERT_PREFIX', 'DPWH Project Monitoring System says: ');

// Error reporting settings
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/meowe.txt');

// Function to slugify status keys for valid HTML IDs
function slugify($text) {
    return strtolower(str_replace(' ', '-', $text));
}

// Function to escape HTML entities
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Retrieve the current user's role_id and position from the session
$role_id = $_SESSION['role_id'];
$user_id = $_SESSION['user_id'];
$position = $_SESSION['user_position'];

// Determine if Approval comboboxes should be disabled
$isApprovalDisabled = ($position === 'None');

// Determine if Approval column should be shown
$showApproval = ($role_id == 1) || ($role_id == 2 && ($position === "Project Inspector" || $position === "Project Engineer"));

// Check if viewing trash
$isTrashView = isset($_GET['trash']) && $_GET['trash'] == '1' ? 1 : 0;

// Retrieve 'message' and 'file' parameters from URL
$message = isset($_GET['message']) ? $_GET['message'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';

// Initialize search parameters
$search_proj_ID = isset($_GET['search_proj_ID']) ? trim($_GET['search_proj_ID']) : '';
$search_status = isset($_GET['search_status']) ? trim($_GET['search_status']) : '';

// Define possible statuses
$statuses = ['Ongoing', 'Not Yet Started', 'Completed', 'Suspended'];

$db = ConnectDB();

// Retrieve and normalize the 'status' parameter
$status = isset($_GET['status']) ? $_GET['status'] : 'all'; 

$validStatuses = ['all', 'ongoing', 'completed', 'not-yet-started', 'suspended'];

$statusMapping = [
    'not-yet-started' => 'Not Yet Started',
    'ongoing' => 'Ongoing',
    'completed' => 'Completed',
    'suspended' => 'Suspended'
];

if (!in_array($status, $validStatuses)) {
    $status = 'all';
}

if (isset($statusMapping[$status])) {
    $status = $statusMapping[$status];
} else {
    $status = 'all';
}

$userQuery = "SELECT user_id, CONCAT(user_first_name, ' ', user_last_name) AS full_name FROM users WHERE user_id != ?";
$userStmt = $db->prepare($userQuery);
$userStmt->bind_param('i', $user_id); // Exclude the current user if needed
$userStmt->execute();
$userResult = $userStmt->get_result();


// Base SQL query to fetch project details along with collaborators and contractors
$query = "SELECT 
    p.proj_ID, 
    p.proj_cont_name, 
    p.proj_cont_loc, 
    p.proj_progress, 
    TRIM(p.proj_status) AS proj_status, 
    p.proj_effect_date, 
    p.proj_NOA, 
    p.proj_expiry_date, 
    p.proj_cont_duration, 
    p.proj_unwork_days, 
    p.proj_cont_amt, 
    p.proj_isApproved,
    IFNULL(SUM(cte.cte_ext_days), 0) AS total_cte_ext_days,    
    IFNULL(SUM(cws.cws_susp_days), 0) AS total_cws_susp_days,
    IFNULL(SUM(cwr.cwr_susp_days), 0) AS total_cwr_susp_days,
    IFNULL(SUM(mtsr.mtsr_susp_days), 0) AS total_mtsr_susp_days,
    IFNULL(SUM(vo.vo_add_amt), 0) AS total_vo_add_amt,
    p.user_ID AS uploader_id,
    u.user_first_name AS uploader_first_name,
    u.user_last_name AS uploader_last_name,
    GROUP_CONCAT(DISTINCT c.cont_name SEPARATOR ', ') AS contractors_names,
    GROUP_CONCAT(DISTINCT c.cont_location SEPARATOR ', ') AS contractors_locations,
    p.deleted_at,
    er.request_status AS edit_request_status  -- Add this line to get the approval status
FROM projects p
LEFT JOIN `contract-time-extension` cte ON p.proj_ID = cte.proj_ID
LEFT JOIN `contract-work-suspension` cws ON p.proj_ID = cws.proj_ID
LEFT JOIN `contract-work-resumption` cwr ON p.proj_ID = cwr.proj_ID
LEFT JOIN `monthly-time-suspension-report` mtsr ON p.proj_ID = mtsr.proj_ID
LEFT JOIN `variation-orders` vo ON p.proj_ID = vo.proj_ID
LEFT JOIN `project-collaborators` pc ON p.proj_ID = pc.proj_ID
LEFT JOIN users u ON p.user_ID = u.user_id
LEFT JOIN `project_contractors` pc2 ON p.proj_ID = pc2.proj_ID
LEFT JOIN `contractors` c ON pc2.cont_ID = c.cont_ID
LEFT JOIN `edit-request` er ON p.proj_ID = er.proj_ID AND er.request_status = 'Approved' -- Only select approved requests
WHERE p.proj_isDeleted = ?";



// Initialize parameters array
$params = [$isTrashView];
$param_types = 'i';

// Add status filter
if ($status !== 'all') {
    $query .= " AND p.proj_status = ?";
    $params[] = $status;
    $param_types .= 's';
}

// Append search filters to the query
if ($search_proj_ID !== '') {
    $query .= " AND p.proj_ID LIKE ?";
    $params[] = '%' . $search_proj_ID . '%';
    $param_types .= 's';
}

if ($search_status !== '') {
    $query .= " AND p.proj_status = ?";
    $params[] = $search_status;
    $param_types .= 's';
}

// Get the user filter from the GET parameters
$user_filter = isset($_GET['user_filter']) ? $_GET['user_filter'] : 'all';


// Modify the SQL query based on the user filter
if ($user_filter === 'mine') {
    // Filter for only the logged-in user's projects
    $query .= " AND p.user_ID = ?";
    $params[] = $user_id;
    $param_types .= 'i';
} elseif ($user_filter === 'all') {
    // Show all projects
    // No additional filtering needed
} else {
    // Filter for the selected user's projects
    $query .= " AND p.user_ID = ?";
    $params[] = $user_filter;
    $param_types .= 'i';
}

// Group by project ID
$query .= " GROUP BY p.proj_ID";

// Order by project status as per your specified priority and uploader
$query .= " ORDER BY (p.user_ID = ?) DESC, FIELD(p.proj_status, 'Not Yet Started', 'Ongoing', 'Suspended', 'Completed'), p.proj_uploaded DESC";

// Append to the parameters for the user ID condition
$params[] = $user_id;
$param_types .= 'i';

// Prepare the statement
$stmt = $db->prepare($query);
if (!$stmt) {
    // Use custom alert for SQL preparation errors
    echo '<script>showCustomAlert("DPWH Project Monitoring System", "Prepare failed: ' . escape($db->error) . '");</script>';
    exit();
}

// Bind parameters dynamically
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all projects with contractors
$projects = [];
while ($row = $result->fetch_assoc()) {
    // Split the concatenated contractor names and locations into arrays
    $contractors_names = $row['contractors_names'] ? explode(', ', $row['contractors_names']) : [];
    $contractors_locations = $row['contractors_locations'] ? explode(', ', $row['contractors_locations']) : [];
    
    // Combine names and locations into an array of contractors
    $contractors = [];
    for ($i = 0; $i < count($contractors_names); $i++) {
        $contractors[] = [
            'name' => $contractors_names[$i],
            'location' => $contractors_locations[$i]
        ];
    }
    
    // Calculate if 30 days have passed since deletion
    $canDeletePermanently = false;
    if ($isTrashView && !empty($row['deleted_at'])) {
        $deleted_at = new DateTime($row['deleted_at']);
        $current_date = new DateTime();
        $interval = $current_date->diff($deleted_at)->days;
        if ($interval >= 30) {
            $canDeletePermanently = true;
        }
    }
    
    // Add the contractors array and canDeletePermanently flag to the project row
    $row['contractors'] = $contractors;
    $row['canDeletePermanently'] = $canDeletePermanently;
    
    $projects[] = $row;
}

// Count of archived projects for "Clear All" functionality
if ($isTrashView) {
    $count_query = "SELECT COUNT(*) AS archived_count FROM projects WHERE proj_isDeleted = 1";
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $archived_count = $count_result->fetch_assoc()['archived_count'];
} else {
    $archived_count = 0;
}

function formatDate($date) {
    return $date && $date !== '0000-00-00' ? date("M d, Y", strtotime($date)) : 'N/A';
}

function getUserOptions($db, $exclude_user_id) {
    $options = '';
    $query = "SELECT user_id, CONCAT(user_first_name, ' ', user_last_name) AS full_name FROM users WHERE user_id != ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $exclude_user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($user = $result->fetch_assoc()) {
        $options .= '<option value="' . escape($user['user_id']) . '">' . escape($user['full_name']) . '</option>';
    }

    $stmt->close();
    return $options;
}

function memberHasApprovedRequest($db, $user_id, $proj_id) {
    // We assume the table uses 'Approved' (capital A) for the enum
    $sql = "SELECT 1
            FROM `edit-request`
            WHERE user_ID = ?
              AND proj_ID = ?
              AND request_status = 'Approved'
            LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('is', $user_id, $proj_id);
    $stmt->execute();
    $result = $stmt->get_result();
    // If we get at least one row, they have an approved request
    return $result->num_rows > 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Projects List</title>
    <!-- Include Bootstrap CSS and other required stylesheets -->
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <!-- Include Font Awesome for icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Include your custom CSS -->
    <link rel="stylesheet" href="../css/styles-for-main.css">
    <link rel="stylesheet" href="../css/styles-for-projects.css">
    <style>
        .btn-orange {
            background-color: #E67040; /* Vibrant Orange */
            color: #fff;
            border: none;
        }
        .btn-orange:hover {
            background-color: #e67300;
            color: #fff;
        }
        /* ... other styles ... */

        .progress-text {
            position: absolute;
            width: 100%;
            text-align: center;
            color: #000;
            font-weight: bold;
        }
        /* Style for disabled dropdown items */
        .col-actions .dropdown-item.disabled {
            pointer-events: none;
            opacity: 0.6;
        }
        /* Search Form Styles */
        .search-form .form-group {
            margin-right: 15px;
        }
        .search-form .form-group:last-child {
            margin-right: 0;
        }
        @media (max-width: 767.98px) {
            .search-form {
                margin-bottom: 15px;
            }
        }
        /* Ensure tooltips have a higher z-index */
        .tooltip {
            z-index: 1050 !important;
        }

        .col-actions .dropdown-toggle::after {
            display: none;
        }

        /* Custom Alert Modal Styles */
        #customAlert {
            z-index: 1060; /* Above Bootstrap modals */
        }

        /* Contractors List Styling */
        .contractors-list {
            margin-top: 10px;
        }

        .contractors-list ul {
            list-style-type: disc;
            padding-left: 20px;
        }

        .contractors-list li {
            margin-bottom: 5px;
        }

        /* Disabled Delete Permanently Button Styling */
        .delete-permanent-disabled {
            pointer-events: none;
            opacity: 0.6;
        }
        .status-not-yet-started {
            background-color: #f44336;
            color: white;
        }
        .status-ongoing {
            background-color: #4caf50;
            color: white;
        }
        .status-suspended {
            background-color: #ff9800;
            color: white;
        }
        .status-completed {
            background-color: #2196f3;
            color: white;
        }

    </style>
</head>
<body>
<?php include "../includes/sidebar.php"; ?>

<div id="content" class="container-fluid">
<?php include "../includes/navbar.php"; ?>

    <!-- Status Messages -->
    <?php if ($message): ?>
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                showCustomAlert("DPWH Project Monitoring System", "<?= escape($message); ?>");
                <?php if (!empty($file)): ?>
                    // Optionally handle file download links here
                <?php endif; ?>
            });
        </script>
    <?php endif; ?>

    <?php
    if (isset($_SESSION['success_message'])) {
        echo '<script>showCustomAlert("DPWH Project Monitoring System", "' . escape($_SESSION['success_message']) . '");</script>';
        unset($_SESSION['success_message']);
    }

    if (isset($_SESSION['error_message'])) {
        echo '<script>showCustomAlert("DPWH Project Monitoring System", "' . escape($_SESSION['error_message']) . '");</script>';
        unset($_SESSION['error_message']);
    }
    ?>

        <!-- Search Form and Add Project Button -->
        <div class="row mb-3" style="padding-top: 10px;">
            <!-- Search Filter Section (First Row) -->
            <div class="col-md-8">
                <form class="search-form" id="searchForm" method="GET" action="../pages/projects.php">
                    <div class="row">
                        <!-- Expanded Group Box for Search Filter -->
                        <div class="col-md-4">
                            <div class="input-group">
                                <input 
                                    type="text" 
                                    class="form-control form-control-sm" 
                                    id="search_proj_ID" 
                                    name="search_proj_ID" 
                                    placeholder="Project ID" 
                                    value="<?= escape($search_proj_ID); ?>"
                                    data-toggle="tooltip" 
                                    title="Enter the Project ID to search for specific projects."
                                    aria-label="Project ID"
                                >
                                <div class="input-group-append">
                                    <button 
                                        type="submit" 
                                        id="searchBtn" 
                                        class="btn btn-primary btn-sm"
                                        data-toggle="tooltip" 
                                        title="Click to search projects based on the provided filters."
                                        aria-label="Search Button"
                                    >
                                        <i class="fas fa-search"></i>
                                    </button>
                                    <button 
                                        type="button" 
                                        id="resetBtn" 
                                        class="btn btn-secondary btn-sm"
                                        data-toggle="tooltip" 
                                        title="Click to reset all search filters and view all projects."
                                        aria-label="Reset Button"
                                    >
                                        <i class="fas fa-undo"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Status Dropdown -->
                        <div class="col-md-4">
                            <select 
                                class="form-control form-control-sm" 
                                id="search_status" 
                                name="search_status"
                                data-toggle="tooltip" 
                                title="Select a status to filter projects based on their current status."
                                aria-label="Status Dropdown"
                                onchange="this.form.submit();"
                            >
                                <option value="">All Statuses</option>
                                <?php foreach ($statuses as $statusOption): ?>
                                    <option value="<?= escape($statusOption); ?>" <?= $search_status === $statusOption ? 'selected' : ''; ?>>
                                        <?= escape($statusOption); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                             <select 
                                    class="form-control form-control-sm" 
                                    id="user_filter" 
                                    name="user_filter"
                                    data-toggle="tooltip" 
                                    title="Select a user to filter projects"
                                    aria-label="User Filter Dropdown"
                                    onchange="this.form.submit();"
                                >
                                    <option value="all">All Users</option>
                                    <option value="mine" <?= isset($_GET['user_filter']) && $_GET['user_filter'] === 'mine' ? 'selected' : ''; ?>>My Projects</option>
                                    <?php while ($user = $userResult->fetch_assoc()): ?>
                                        <option value="<?= escape($user['user_id']); ?>" <?= isset($_GET['user_filter']) && $_GET['user_filter'] == $user['user_id'] ? 'selected' : ''; ?>>
                                            <?= escape($user['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Add Project and Clear All Buttons (Second Row, Right Aligned) -->
        <div class="row">
            <div class="col-md-12 text-right">
                <?php if (!$isTrashView && ($role_id == 1 || ($role_id == 2))): ?>
                    <button 
                        onclick='window.location.href="../project-management/add-project.php"' 
                        type="button" 
                        class="btn btn-orange"
                        data-toggle="tooltip" 
                        title="Click to add a new project."
                        aria-label="Add Project Button"
                    >
                        <i class="fas fa-plus"></i> Add Project
                    </button>
                <?php endif; ?>

                <!-- Modified "Clear All" Button Condition: Only Admins (role_id = 1) -->
                <?php if ($isTrashView && $role_id == 1): ?>
                    <button 
                        type="button" 
                        class="btn btn-danger ml-2" 
                        id="clearAllBtn"
                        data-toggle="tooltip" 
                        title="Click to permanently delete all archived projects."
                        aria-label="Clear All Button"
                    >
                        <i class="fas fa-trash"></i> Clear All
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Combined Projects Table -->
        <div class="table-container mt-3"> <!-- Use this for table container -->
            <table class="table table-striped project-table" id="projectTable">
                <thead>
                    <tr>
                        <th class="col-proj-id">Project ID</th>
                        <th class="col-proj-name">Project Name & Location</th>
                        <th class="col-budget">Budget</th>
                        <th class="col-contract-duration">Contract Duration</th>
                        <th class="col-effect-date">Effectivity Date</th>
                        <th class="col-notice-date">Expiry Date</th>
                        <th class="col-status">Status</th>
                        <th class="col-status">Author</th>
                        <?php if ($showApproval): ?>
                            <th class="col-approval">Approval</th> <!-- Approval Column -->
                        <?php endif; ?>
                        <th class="col-actions">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($projects) > 0): ?>
                        <?php foreach ($projects as $row): ?>
                            <?php
                            // Check if the current user is the uploader
                            $isUploader = ($user_id == $row['uploader_id']);

                            // Check if the current user is a collaborator
                            $collab_query = "SELECT * FROM `project-collaborators` WHERE proj_ID = ? AND user_ID = ?";
                            $collab_stmt = $db->prepare($collab_query);
                            $collab_stmt->bind_param('si', $row['proj_ID'], $user_id);
                            $collab_stmt->execute();
                            $collab_result = $collab_stmt->get_result();
                            $isCollaborator = $collab_result->num_rows > 0;

                            $hasApprovedEdit = memberHasApprovedRequest($db, $user_id, $row['proj_ID']);
                        
                            // Now define $canEdit
                            // If Admin, or Uploader, or the user has an approved request -> can edit
                            $canEdit = ($role_id == 1 || $isUploader || ($row['edit_request_status'] == 'Approved')); 

                            
                            // Determine if the user can perform collaborator actions
                            $canCollaboratorActions = ($isCollaborator || $role_id == 1 || $isUploader);
                            ?>
                            <tr class="status-<?= strtolower(str_replace(' ', '-', $row['proj_status'])); ?>" style="background-color: 
                                <?php
                                switch ($row['proj_status']) {
                                    case 'Not Yet Started':
                                        echo '#ffffff';  // puti
                                        break;
                                    case 'Ongoing':
                                        echo '#eab26c';  // Yelo
                                        break;
                                    case 'Suspended':
                                        echo '#ea936d';  // c2 red
                                        break;
                                    case 'Completed':
                                        echo '#c3ea6c';  // c2 na green
                                        break;
                                    default:
                                        echo '#ffffff';  // Default background color
                                }
                                ?>
                            ;
                            color: 
                                <?php
                                switch ($row['proj_status']) {
                                    case 'Not Yet Started':
                                        echo '#000000';  // puti
                                        break;
                                    case 'Ongoing':
                                        echo '#000000';  // Yelo
                                        break;
                                    case 'Suspended':
                                        echo '#000000';  // c2 red
                                        break;
                                    case 'Completed':
                                        echo '#000000';  // c2 na green
                                        break;
                                    default:
                                        echo '#ffffff';  // Default background color
                                }
                                ?>
                            ;">
                                <td class="col-proj-id"><?= "<b>" . escape($row['proj_ID']) . "</b><br>(" . escape($row['proj_progress']) . '%)'; ?></td>
                                <td class="col-proj-name">
                                    <strong><?= escape($row['proj_cont_name']); ?></strong><br>
                                    <?= escape($row['proj_cont_loc']); ?>
                                </td>
                                <td class="col-budget"><span>&#8369;</span><?= escape(number_format($row['proj_cont_amt'] + $row['total_vo_add_amt'], 2)); ?></td>
                                <td class="col-contract-duration">
                                    <?= escape($row['proj_cont_duration'] + $row['proj_unwork_days'] + $row['total_cte_ext_days'] + $row['total_cwr_susp_days'] + $row['total_cws_susp_days'] + $row['total_mtsr_susp_days']); ?> days
                                </td>
                                <td class="col-effect-date"><?= formatDate($row['proj_effect_date']); ?></td>
                                <td class="col-notice-date"><?= formatDate($row['proj_expiry_date']); ?></td>
                                <td class="col-status"><?= escape($row['proj_status']); ?></td>
                                <td class="col-status"><?= escape($row['uploader_first_name'] . " " . $row['uploader_last_name']); ?></td>
                                <?php if ($showApproval): ?>
                                    <td class="col-approval">
                                        <select 
                                            class="form-control form-control-sm approval-select" 
                                            data-proj-id="<?= escape($row['proj_ID']); ?>"
                                            <?php if ($row['proj_isApproved'] == 1 || $isApprovalDisabled) echo 'disabled'; ?>
                                            data-toggle="tooltip" 
                                            title="Change the approval status of this project."
                                            aria-label="Approval Dropdown"
                                        >
                                            <option value="1" <?= $row['proj_isApproved'] == 1 ? 'selected' : ''; ?> >Approved</option>
                                            <option value="0" <?= $row['proj_isApproved'] == 0 ? 'selected' : ''; ?>>Not Yet Approved</option>
                                        </select>
                                    </td>
                                <?php endif; ?>

                                <!-- Actions Column -->
                                <td class="col-actions">
                                    <div class="dropdown" data-toggle="tooltip" title="Click to view available actions for this project.">
                                        <button 
                                            class="btn btn-secondary btn-sm dropdown-toggle" 
                                            type="button" 
                                            id="dropdownMenuButton<?= escape($row['proj_ID']); ?>" 
                                            data-toggle="dropdown" 
                                            aria-haspopup="true" 
                                            aria-expanded="false" 
                                            data-boundary="viewport"
                                            data-toggle="tooltip" 
                                            title="Click to view available actions for this project."
                                            aria-label="Actions Dropdown"
                                        >
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton<?= escape($row['proj_ID']); ?>">
                                        <?php if ($role_id == 1 || $isUploader): ?>
                                            <!-- Actions available to Admin and Uploader -->
                                            <?php if ($row['proj_isApproved'] == 1): ?>
                                                <a 
                                                    class="dropdown-item" 
                                                    href="#" 
                                                    onclick="redirectToProgress('<?= escape($row['proj_ID']); ?>')"
                                                    data-toggle="tooltip" 
                                                    title="Add progress to this approved project."
                                                    data-placement="left"
                                                    aria-label="Add Progress"
                                                >
                                                    <i class="fas fa-plus-circle"></i> Add Progress
                                                </a>
                                                <!-- Modification starts here -->
                                                <a 
                                                    class="dropdown-item<?php if ($row['proj_isApproved'] == 0) echo ' disabled'; ?>" 
                                                    href="#" 
                                                    data-proj-id="<?= escape($row['proj_ID']); ?>" 
                                                    onclick="viewDocuments(event, '<?= escape($row['proj_ID']); ?>')"
                                                    data-toggle="tooltip" 
                                                    title="<?= $row['proj_isApproved'] == 0 ? 'Approve the project to access documents.' : 'View project documents.'; ?>"
                                                    data-placement="left"
                                                    aria-label="View Documents"
                                                >
                                                    <i class="fas fa-folder-open"></i> Documents
                                                </a>
                                            <?php endif; ?>
                                            <a 
                                                class="dropdown-item view-project-btn" 
                                                href="#" 
                                                data-id="<?= escape($row['proj_ID']); ?>"
                                                data-toggle="tooltip" 
                                                title="View details of this project."
                                                data-placement="left"
                                                aria-label="View Project"
                                            >
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                              <?php if ($role_id == 1): ?>
                                                <!-- Transfer Project Dropdown Item -->
                                                <a class="dropdown-item transfer-project-btn" 
                                                   href="#" 
                                                   data-id="<?= escape($row['proj_ID']); ?>" 
                                                   data-toggle="tooltip" 
                                                   title="Transfer Project to other users."
                                                   data-placement="left"
                                                   data-target="#transferProjectModal<?= escape($row['proj_ID']); ?>">
                                                    <i class="fas fa-exchange-alt"></i> Transfer Project to...
                                                </a>
                                            <?php endif; ?>
                                            <!-- Modify the Edit option -->
                                            <?php if ($canEdit): ?>
                                                <a 
                                                    class="dropdown-item edit-project-btn" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>" 
                                                    data-toggle="tooltip" 
                                                    title="Edit project details."
                                                    data-placement="left"
                                                    aria-label="Edit Project"
                                                    <?= !$canEdit ? 'class="disabled"' : ''; ?>
                                                >
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php else: ?>
                                                <a 
                                                    class="dropdown-item disabled" 
                                                    href="#" 
                                                    title="You do not have permission to edit this project."
                                                    data-toggle="tooltip" 
                                                    data-placement="left"
                                                    aria-label="Edit Project Disabled"
                                                >
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            <?php endif; ?>
                                            <?php if (!$isTrashView): ?>
                                                <a 
                                                    class="dropdown-item delete-project-btn" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>"
                                                    data-toggle="tooltip" 
                                                    title="Archive this project."
                                                    data-placement="left"
                                                    aria-label="Archive Project"
                                                >
                                                    <i class="fas fa-archive"></i> Archive
                                                </a>
                                            <?php else: ?>
                                                <a 
                                                    class="dropdown-item restore-project-btn" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>"
                                                    data-toggle="tooltip" 
                                                    title="Restore this archived project."
                                                    data-placement="left"
                                                    aria-label="Restore Project"
                                                >
                                                    <i class="fas fa-undo"></i> Restore
                                                </a>
                                                <!-- Add "Delete Permanently" option when in archive mode -->
                                                <?php if ($row['canDeletePermanently']): ?>
                                                    <a 
                                                        class="dropdown-item delete-permanent-project-btn" 
                                                        href="#" 
                                                        data-id="<?= escape($row['proj_ID']); ?>"
                                                        data-toggle="tooltip" 
                                                        title="Permanently delete this project."
                                                        data-placement="left"
                                                        aria-label="Delete Permanently"
                                                    >
                                                        <i class="fas fa-trash-alt text-danger"></i> Delete Permanently
                                                    </a>
                                                <?php else: ?>
                                                    <a 
                                                        class="dropdown-item delete-permanent-project-btn delete-permanent-disabled" 
                                                        href="#" 
                                                        data-id="<?= escape($row['proj_ID']); ?>"
                                                        data-toggle="tooltip" 
                                                        title="Can delete permanently after 30 days of archiving."
                                                        data-placement="left"
                                                        aria-label="Delete Permanently Disabled"
                                                    >
                                                        <i class="fas fa-trash-alt text-danger"></i> Delete Permanently
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            <?php if ($canCollaboratorActions): ?>
                                                <a 
                                                    class="dropdown-item view-collaborators-btn" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>"
                                                    data-toggle="tooltip" 
                                                    title="View collaborators of this project."
                                                    data-placement="left"
                                                    aria-label="View Collaborators"
                                                >
                                                    <i class="fas fa-users"></i> Collaborators
                                                </a>
                                            <?php endif; ?>
                                        <?php elseif ($role_id == 2 || $isCollaborator): ?>
                                            <!-- Actions available to Members and Collaborators -->
                                            <?php if ($canEdit): ?>
                                                <a 
                                                    class="dropdown-item edit-project-btn" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>" 
                                                    data-toggle="tooltip" 
                                                    title="Edit project details."
                                                    data-placement="left"
                                                    aria-label="Edit Project"
                                                    <?= !$canEdit ? 'class="disabled"' : ''; ?>
                                                >
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a 

                                                    class="dropdown-item<?php if ($row['proj_isApproved'] == 0) echo ' disabled'; ?>"  
                                                    href="#" 
                                                    onclick="redirectToProgress('<?= escape($row['proj_ID']); ?>')"
                                                    data-toggle="tooltip" 
                                                    title="Add progress to this approved project."
                                                    data-placement="left"
                                                    aria-label="Add Progress"
                                                >
                                                    <i class="fas fa-plus-circle"></i> Add Progress
                                                </a>
                                                <!-- Modification starts here -->
                                                <a 
                                                    class="dropdown-item<?php if ($row['proj_isApproved'] == 0) echo ' disabled'; ?>" 
                                                    href="#" 
                                                    data-proj-id="<?= escape($row['proj_ID']); ?>" 
                                                    onclick="viewDocuments(event, '<?= escape($row['proj_ID']); ?>')"
                                                    data-toggle="tooltip" 
                                                    title="<?= $row['proj_isApproved'] == 0 ? 'Approve the project to access documents.' : 'View project documents.'; ?>"
                                                    data-placement="left"
                                                    aria-label="View Documents"
                                                >
                                                    <i class="fas fa-folder-open"></i> Documents
                                                </a>
                                            <?php endif; ?>
                                            <a 
                                                class="dropdown-item view-project-btn" 
                                                href="#" 
                                                data-id="<?= escape($row['proj_ID']); ?>"
                                                data-toggle="tooltip" 
                                                title="View details of this project."
                                                data-placement="left"
                                                aria-label="View Project"
                                            >
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <?php if ($canCollaboratorActions): ?>
                                                <?php if ($row['proj_isApproved'] == 1): ?>
                                                    <a 
                                                        class="dropdown-item" 
                                                        href="#" 
                                                        onclick="redirectToProgress('<?= escape($row['proj_ID']); ?>')"
                                                        data-toggle="tooltip" 
                                                        title="Add progress to this approved project."
                                                        data-placement="left"
                                                        aria-label="Add Progress"
                                                    >
                                                        <i class="fas fa-plus-circle"></i> Add Progress
                                                    </a>
                                                <?php else: ?>
                                                    <a 
                                                        class="dropdown-item disabled" 
                                                        href="#" 
                                                        title="Approve the project to add progress"
                                                        data-toggle="tooltip" 
                                                        data-placement="left"
                                                        aria-label="Add Progress Disabled"
                                                    >
                                                        <i class="fas fa-plus-circle"></i> Add Progress
                                                    </a>
                                                <?php endif; ?>
                                                <a 
                                                    class="dropdown-item" 
                                                    href="#" 
                                                    data-id="<?= escape($row['proj_ID']); ?>"
                                                    onclick="viewDocuments(event, '<?= escape($row['proj_ID']); ?>')"
                                                    data-toggle="tooltip" 
                                                    title="Access project documents."
                                                    data-placement="left"
                                                    aria-label="Access Documents"
                                                >
                                                    <i class="fas fa-folder-open"></i> Documents
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        <!-- Member: Request to Edit -->
                                        <!-- Edit Request Button (Visible to members) -->
                                        <?php if ($role_id == 2 && !$isUploader): ?>
                                            <button type="button" class="dropdown-item" id="requestEdit" data-proj-id="<?= escape($row['proj_ID']); ?>" 
                                            <?php if ($row['edit_request_status'] == 'Approved') echo 'style="display:none;"'; ?>>
                                                <i class="far fa-clock"></i> Request to Edit Project
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($role_id == 3): ?>
                                            <a 
                                                class="dropdown-item view-project-btn" 
                                                href="#" 
                                                data-id="<?= escape($row['proj_ID']); ?>"
                                                data-toggle="tooltip" 
                                                title="View details of this project."
                                                data-placement="left"
                                                aria-label="View Project"
                                            >
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php endif; ?>
                                        

                                        
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            
                     <div class="modal fade" id="transferProjectModal<?= escape($row['proj_ID']); ?>" tabindex="-1" role="dialog" aria-labelledby="transferProjectModalLabel<?= escape($row['proj_ID']); ?>" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="transferProjectModalLabel<?= escape($row['proj_ID']); ?>">Transfer Project</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <form id="transferProjectForm<?= escape($row['proj_ID']); ?>">
                    <div class="form-group">
                        <label for="newOwner<?= escape($row['proj_ID']); ?>">Select New Project Owner:</label>
                        <select class="form-control" id="newOwner<?= escape($row['proj_ID']); ?>" name="newOwner">
                            <?= getUserOptions($db, $user_id); // Pass the current user ID to exclude it from the list ?>
                        </select>
                    </div>
                    <button type="button" class="btn btn-primary" onclick="transferProject('<?= escape($row['proj_ID']); ?>')">Transfer</button>
                </form>
            </div>
        </div>
    </div>
</div>
                        <!-- Collaborators Modal -->
                        <div class="modal fade" id="collaboratorsModal<?= escape($row['proj_ID']); ?>" tabindex="-1" aria-labelledby="collaboratorsModalLabel<?= escape($row['proj_ID']); ?>" aria-hidden="true">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <!-- Updated Modal Title to include Uploader's Name -->
                                        <h5 class="modal-title" id="collaboratorsModalLabel<?= escape($row['proj_ID']); ?>">
                                            Collaborators for Project <?= escape($row['proj_ID']); ?> by <?= escape($row['uploader_first_name'] . ' ' . $row['uploader_last_name']); ?>
                                        </h5>
                                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeCollaboratorsModal('<?= escape($row['proj_ID']); ?>')">
                                            <span aria-hidden="true">&times;</span>
                                        </button>
                                    </div>
                                        <div class="modal-body" style="color: black;">
                                            <?php
                                            // Fetch collaborators for this project
                                            $collab_fetch_query = "SELECT u.user_id, u.user_first_name, u.user_last_name FROM `project-collaborators` pc JOIN users u ON pc.user_id = u.user_id WHERE pc.proj_ID = ?";
                                            $collab_fetch_stmt = $db->prepare($collab_fetch_query);
                                            $collab_fetch_stmt->bind_param('s', $row['proj_ID']);
                                            $collab_fetch_stmt->execute();
                                            $collab_fetch_result = $collab_fetch_stmt->get_result();

                                            if ($collab_fetch_result->num_rows > 0):
                                                echo "<ul class='list-group'>";
                                                while ($collab = $collab_fetch_result->fetch_assoc()):
                                                    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>" . escape($collab['user_first_name'] . ' ' . $collab['user_last_name']) . "";
                                                    // Only show the remove button to Admins and Uploader
                                                    if ($role_id == 1 || $isUploader):
                                                        echo "<button class='btn btn-sm btn-danger remove-collaborator-btn' data-proj-id='" . escape($row['proj_ID']) . "' data-user-id='" . escape($collab['user_id']) . "'>
                                                                <i class='fas fa-trash-alt'></i>
                                                            </button>";
                                                    endif;
                                                    echo "</li>";
                                                endwhile;
                                                echo "</ul>";
                                            else:
                                                echo "<p>No collaborators added yet.</p>";
                                            endif;
                                            ?>
                                        </div>
                                        <?php if ($role_id == 1 || $isUploader): ?>
                                            <div class="modal-footer">
                                                <!-- Button to trigger Add Collaborator Modal -->
                                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#addCollaboratorModal" onclick="openAddCollaboratorModal('<?= escape($row['proj_ID']); ?>')">
                                                    Add Collaborator
                                                </button>
                                                <button type="button" class="btn btn-secondary" data-dismiss="modal" onclick="closeCollaboratorsModal('<?= escape($row['proj_ID']); ?>')">Close</button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $showApproval ? '9' : '8'; ?>" class="text-center">No projects found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Clear All Confirmation Modal -->
        <div class="modal fade" id="clearAllModal" tabindex="-1" aria-labelledby="clearAllModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content" style="background-color: #343a40;">
                    <div class="modal-header">
                        <h5 class="modal-title" id="clearAllModalLabel" style="color: white;">Confirm Bulk Deletion</h5>
                        <button 
                            type="button" 
                            class="close" 
                            data-dismiss="modal" 
                            aria-label="Close" 
                            onclick="closeClearAllModal()"
                            data-toggle="tooltip" 
                            title="Close the confirmation dialog."
                            aria-label="Close Modal"
                        >
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body" style="color: white;">
                        <?= ALERT_PREFIX ?>Are you sure you want to permanently delete <span id="clearAllCount">0</span> archived projects? This action cannot be undone.
                    </div>
                    <div class="modal-footer">
                        <button 
                            type="button" 
                            class="btn btn-secondary" 
                            data-dismiss="modal"
                            data-toggle="tooltip" 
                            title="Cancel the deletion process."
                            aria-label="Cancel Deletion"
                        >
                            Cancel
                        </button>
                        <button 
                            type="button" 
                            class="btn btn-danger" 
                            id="confirmClearAll"
                            data-toggle="tooltip" 
                            title="Confirm and permanently delete all archived projects."
                            aria-label="Confirm Clear All"
                        >
                            Delete All
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Other Existing Modals and Content... -->

        <!-- Document Table Container -->
        <div id="documentsTableContainer" class="container mt-5" style="display: none;"></div>

        <!-- Back to Projects Button -->
        <div id="backToProjectsBtn" style="display: none; text-align: right;">
            <button 
                type="button" 
                class="btn btn-secondary" 
                id="backToProjects"
                data-toggle="tooltip" 
                title="Click to return to the main projects list."
                aria-label="Back to Projects Button"
            >
                Back to Projects
            </button>
        </div>
    </div>
     
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteProjectModal" tabindex="-1" aria-labelledby="deleteProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: #343a40;">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteProjectModalLabel" style="color: white;">Confirm Archiving</h5>
                    <button 
                        type="button" 
                        class="close" 
                        data-dismiss="modal" 
                        aria-label="Close" 
                        onclick="closeProjectModal()"
                        data-toggle="tooltip" 
                        title="Close the confirmation dialog."
                        aria-label="Close Modal"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
            <div class="modal-body" style="color: white;">
                <?= ALERT_PREFIX ?>Are you sure you want to archive this project?
            </div>
            <div class="modal-footer">
                <button 
                    type="button" 
                    class="btn btn-secondary" 
                    data-dismiss="modal"
                    data-toggle="tooltip" 
                    title="Cancel the archiving process."
                    aria-label="Cancel Archiving"
                >
                    Cancel
                </button>
                <button 
                    type="button" 
                    class="btn btn-danger" 
                    id="confirmDelete"
                    data-toggle="tooltip" 
                    title="Confirm and archive the project."
                    aria-label="Confirm Archive"
                >
                    Archive
                </button>
            </div>
        </div>
    </div>
    </div>

    <!-- View Project Modal -->
    <div class="modal fade" id="projectModal" tabindex="-1" aria-labelledby="projectModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-lg"> <!-- Larger modal size for PDF viewing -->
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Project Details</h5> <!-- This will be updated dynamically -->
                    <button 
                        type="button" 
                        class="close" 
                        data-dismiss="modal" 
                        aria-label="Close" 
                        onclick="closeProjectModal()"
                        data-toggle="tooltip" 
                        title="Close the project details modal."
                        aria-label="Close Modal"
                    >
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Iframe to embed the PDF -->
                    <iframe id="pdfIframe" style="width: 100%; height: 600px; border: none;"></iframe>
                </div>
                <div class="modal-footer">
                    <?php if ($role_id == 1): ?>
                        <!-- Button that triggers the PDF generation -->
                        <button 
                            id="generateModalPDFBtn" 
                            data-proj-id="" 
                            class="btn btn-primary"
                            data-toggle="tooltip" 
                            title="Generate a PDF report for this project."
                            aria-label="Generate PDF"
                        >
                            Generate PDF
                        </button>
                    <?php endif; ?>
                    <button 
                        type="button" 
                        class="btn btn-secondary" 
                        data-dismiss="modal" 
                        onclick="closeProjectModal()"
                        data-toggle="tooltip" 
                        title="Close the project details modal."
                        aria-label="Close Modal"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Restore Confirmation Modal -->
    <div class="modal fade" id="restoreProjectModal" tabindex="-1" aria-labelledby="restoreProjectModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: #343a40;">
                <div class="modal-header">
                    <h5 class="modal-title" id="restoreProjectModalLabel" style="color: white;">Confirm Restoration</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeRestoreModal()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" style="color: white;">
                    <?= ALERT_PREFIX ?>Are you sure you want to restore this archived project?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmRestore">Restore</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Collaborator Modal (For Adding/Removing Collaborators) -->
    <div class="modal fade" id="addCollaboratorModal" tabindex="-1" aria-labelledby="addCollaboratorModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="background-color: #343a40;">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCollaboratorModalLabel" style="color: white;">Add Collaborator</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeAddCollaboratorModal()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form id="addCollaboratorForm">
                        <div class="form-group">
                            <label for="collaboratorSelect" style="color: white;">Select User</label>
                            <select class="form-control" id="collaboratorSelect" name="collaboratorSelect">
                                <option value="">-- Select User --</option>
                                <?php
                                // Fetch users with role_id 1 or 2 to add as collaborators
                                $collab_users_query = "SELECT user_id, user_first_name, user_last_name FROM users WHERE user_id != ? AND role_id IN (1, 2) AND user_position != 'None'";
                                $collab_users_stmt = $db->prepare($collab_users_query);
                                $collab_users_stmt->bind_param('i', $user_id);
                                $collab_users_stmt->execute();
                                $collab_users_result = $collab_users_stmt->get_result();
                                while ($user = $collab_users_result->fetch_assoc()) {
                                    echo "<option value='" . escape($user['user_id']) . "'>" . escape($user['user_first_name'] . ' ' . $user['user_last_name']) . "</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <input type="hidden" id="currentProjectID" name="currentProjectID" value="">
                        <button type="submit" class="btn btn-primary">Add Collaborator</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Custom Alert and Confirm Modals -->
    <!-- Custom Alert Modal -->
    <div id="customAlert" class="modal" tabindex="-1" role="dialog" aria-labelledby="customAlertLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 id="customAlertTitle" class="modal-title">DPWH Project Monitoring System</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="closeCustomAlert()">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p id="customAlertMessage">This is a custom alert message.</p>
                </div>
                <div class="modal-footer" id="customAlertFooter">
                    <button type="button" class="btn btn-primary" onclick="closeCustomAlert()">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Notification (Optional) -->
    <div aria-live="polite" aria-atomic="true" style="position: fixed; top: 20px; right: 20px; min-width: 200px;">
        <div id="customToast" class="toast" data-delay="5000">
            <div class="toast-header">
                <strong class="mr-auto">DPWH Project Monitoring System</strong>
                <small>Just now</small>
                <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body" id="customToastBody">
                Your message here.
            </div>
        </div>
    </div>
    
<!-- Edit Request Modal (Updated) -->
<div id="requestEditModal" class="modal fade" tabindex="-1" aria-labelledby="requestEditLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestEditLabel">Confirm Request to Edit</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Input Reason for Edit -->
                <div class="form-group">
                    <label for="editRequestReason">Reason for Edit:</label>
                    <textarea class="form-control" id="editRequestReason" rows="4" placeholder="Enter the reason for the edit request"></textarea>
                </div>
                <p>Please provide a valid reason for the requested edit.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmRequestEdit">Request</button>
            </div>
        </div>
    </div>
</div>



    <!-- Approve Edit Request Modal for Admin -->
    <div id="approveEditRequestModal" class="modal fade" tabindex="-1" aria-labelledby="approveEditRequestLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveEditRequestLabel">Confirm Approval</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to approve this edit request?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="confirmApproveEditRequest">Approve</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Include JavaScript Libraries -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- Include your custom JS -->
    <script src="../js/script-for-main.js"></script>
    <script src="../js/script-sidebar.js"></script>

    <!-- Custom Alert and Confirm Functions -->
    <script>
        // Define the archivedCount variable using PHP
        var archivedCount = <?= (int)$archived_count ?>;

        // Function to show the custom alert modal
        function showCustomAlert(title, message) {
            document.getElementById('customAlertTitle').innerText = title;
            document.getElementById('customAlertMessage').innerText = message;
            $('#customAlert').modal('show');
        }

        // Function to close the custom alert modal
        function closeCustomAlert() {
            $('#customAlert').modal('hide');
        }

        // Function to show a custom confirm modal
        function showCustomConfirm(title, message) {
            return new Promise((resolve, reject) => {
                document.getElementById('customAlertTitle').innerText = title;
                document.getElementById('customAlertMessage').innerText = message;

                // Modify the modal footer to include Confirm and Cancel buttons
                const footer = document.getElementById('customAlertFooter');
                footer.innerHTML = `
                    <button type="button" class="btn btn-secondary" onclick="rejectCustomConfirm()">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="resolveCustomConfirm()">OK</button>
                `;

                // Show the modal
                $('#customAlert').modal('show');

                // Define resolve and reject functions
                window.resolveCustomConfirm = function() {
                    $('#customAlert').modal('hide');
                    resolve(true);
                };

                window.rejectCustomConfirm = function() {
                    $('#customAlert').modal('hide');
                    reject(false);
                };
            });
        }

        // Function to show a toast notification
        function showToast(message) {
            document.getElementById('customToastBody').innerText = message;
            $('#customToast').toast('show');
        }

        $(document).ready(function () {

                // Initialize Bootstrap tooltips with container set to 'body' to prevent clipping
                $('[data-toggle="tooltip"]').tooltip({
                    container: 'body',
                    placement: 'top' // Default placement; overridden in dropdown items
                });
                // Optional: Move dropdown menus for .col-actions only to the body to prevent clipping
                $('.col-actions .dropdown').on('show.bs.dropdown', function () {
                    var $dropdown = $(this).find('.dropdown-menu');
                    var button = $(this).find('.dropdown-toggle');
                    var offset = button.offset();
                    var height = button.outerHeight();
                    var width = button.outerWidth();

                    // Append dropdown to body and position it correctly
                    $('body').append($dropdown.detach());

                    // Set dropdown positioning
                    $dropdown.css({
                        'display': 'block',
                        'position': 'absolute',
                        'top': offset.top + height,  // Adjust top position for visibility
                        'left': offset.left,         // Align to button left
                        'z-index': 3000              // Ensure it appears on top
                    });
                });

                $('.col-actions .dropdown').on('hide.bs.dropdown', function () {
                    var $dropdown = $(this).find('.dropdown-menu');
                    $(this).append($dropdown.detach());
                });


                // Handle restoring a project
                $(document).on('click', '.restore-project-btn', function () {
                    var projectID = $(this).data('id');  // Get the project ID from data-id attribute

                    // Open the restore confirmation modal
                    $('#restoreProjectModal').modal('show');

                    // Unbind any previous click event to prevent multiple bindings
                    $('#confirmRestore').off('click').on('click', function () {
                        $.ajax({
                            url: '../project-management/restore-project',
                            type: 'POST',
                            data: { proj_ID: projectID },  // Send the project ID to the server
                            dataType: 'json',
                            success: function(response) {
                                if(response.status === 'success') {
                                    showCustomAlert("DPWH Project Monitoring System", response.message);
                                } else {
                                    showCustomAlert("DPWH Project Monitoring System", response.message);
                                }
                                setTimeout(function() {
                                    location.reload();  
                                }, 500);
                            },
                            error: function(xhr, status, error) {
                                showCustomAlert("DPWH Project Monitoring System", "An error occurred while restoring the project.");
                                setTimeout(function() {
                                    location.reload();  
                                }, 500);
                            }
                        });
                    });
                });



                // Handle deleting a project
                $(document).on('click', '.delete-project-btn', function () {
                    var projectID = $(this).data('id');  // Get the project ID from data-id attribute

                    // Open the delete confirmation modal
                    $('#deleteProjectModal').modal('show');

                    // Unbind any previous click event to prevent multiple bindings
                    $('#confirmDelete').off('click').on('click', function () {
                        $.ajax({
                            url: '../project-management/delete-project',
                            type: 'POST',
                            data: { id: projectID },  // Send the project ID to the server
                            success: function(response) {
                                // Assuming the server returns JSON
                                try {
                                    var res = JSON.parse(response);
                                    if(res.status === 'success') {
                                        showCustomAlert("DPWH Project Monitoring System", res.message);
                                    } else {
                                        showCustomAlert("DPWH Project Monitoring System", res.message);
                                    }
                                } catch(e) {
                                    showCustomAlert("DPWH Project Monitoring System", "An unexpected error occurred.");
                                }
                                setTimeout(function() {
                                    location.reload();  
                                }, 500);
                            },
                            error: function(xhr, status, error) {
                                showCustomAlert("DPWH Project Monitoring System", "An error occurred while deleting the project.");
                                setTimeout(function() {
                                    location.reload();  
                                }, 500);
                            }
                        });
                    });
                });


                // Handle editing a project
                $(document).on('click', '.edit-project-btn', function (e) {
                    var projectID = $(this).data('id');  // Get the project ID from data-id attribute
                    window.location.href = '../project-management/edit-project.php?proj_ID=' + projectID;  // Redirect to edit page
                });

                $(document).on('click', '.view-project-btn', function () {
                    var projectID = $(this).data('id');

                    // Trigger PDF generation for the specified project
                    $.ajax({
                        url: '../project-management/generate-pdf-pd.php',
                        type: 'GET',
                        data: { proj_ID: projectID },
                        dataType: 'json',
                        success: function (response) {
                            if (response.status === 'success') {
                                // Assuming the PDF generation URL is returned in the response
                                var pdfURL = `../uploads/project-details/project_details_${projectID}.pdf`;

                                if (pdfURL) {
                                    // Open the PDF in a new window
                                    window.open(pdfURL, '_blank');
                                } else {
                                    showCustomAlert("DPWH Project Monitoring System", "PDF URL not provided.");
                                }
                            } else {
                                showCustomAlert("DPWH Project Monitoring System", response.message);
                            }
                        },
                        error: function () {
                            showCustomAlert("DPWH Project Monitoring System", "An error occurred while fetching project details.");
                        }
                    });
                });



                // Handle generating PDF from the project modal
                $(document).on('click', '#generateModalPDFBtn', function () {
                    var projID = $(this).data('proj-id');
                    if (!projID) {
                        showCustomAlert("DPWH Project Monitoring System", "Project ID not found!");
                        return;
                    }
                    // Redirect to generate PDF with the project ID
                    window.location.href = '../project-management/generate-pdf-pd.php?proj_ID=' + projID;
                });

                // Handle back to projects button click
                $('#backToProjects').on('click', function () {
                    $('#documentsTableContainer').hide();  // Hide the documents section
                    $('#content').show();  // Show the main project content
                    $('#backToProjectsBtn').hide();  // Hide the back to projects button
                });

                // Toggle between active and deleted projects
                $('#trashBtn').on('click', function () {
                    var url = window.location.href;
                    var newUrl = url.includes('trash=1') ? url.replace('trash=1', '') : url.includes('?') ? url + '&trash=1' : url + '?trash=1';
                    window.location.href = newUrl;
                });

                // Remove URL parameters after displaying the alert
                if (window.location.search.includes('status=') && window.location.search.includes('message=')) {
                    // Remove query parameters after the alert is shown
                    const url = new URL(window.location);
                    url.searchParams.delete('status');
                    url.searchParams.delete('message');
                    url.searchParams.delete('file');

                    // Update the URL without refreshing the page
                    window.history.replaceState({}, document.title, url.toString());
                }

                // Handle approval status change
                $(document).on('change', '.approval-select', function() {
                    var projID = $(this).data('proj-id');
                    var approvalStatus = $(this).val();

                    // Show custom confirm modal
                    showCustomConfirm("DPWH Project Monitoring System", "Are you sure you want to change the approval status?")
                        .then(() => {
                            // User confirmed, proceed with AJAX request
                            $.ajax({
                                url: '../project-management/update-approval',
                                type: 'POST',
                                data: { proj_ID: projID, approval: approvalStatus },
                                dataType: 'json', // Explicitly set to JSON
                                success: function(response) {
                                    if (response.status === 'success') {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                        setTimeout(function() {
                                            location.reload(); // Reload to reflect changes
                                        }, 500);
                                    } else {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                        setTimeout(function() {
                                            location.reload(); // Reload to revert changes
                                        }, 500);
                                    }
                                },
                                error: function() {
                                    showCustomAlert("DPWH Project Monitoring System", "An error occurred while updating approval status.");
                                    setTimeout(function() {
                                        location.reload(); // Reload to revert changes
                                    }, 500);
                                }
                            });
                        })
                        .catch(() => {
                            // User canceled, revert the selection
                            location.reload();
                        });
                });

                // Handle reset button click
                $('#resetBtn').on('click', function() {
                    $('#searchForm')[0].reset(); // Reset the form fields
                    window.location.href = '../pages/projects.php'; // Reload the page without search parameters
                });

                // Handle viewing collaborators
                $(document).on('click', '.view-collaborators-btn', function () {
                    var projID = $(this).data('id');  // Get the project ID from the button's data attribute
                    openCollaboratorsModal(projID);  // Pass projID to the function that opens the Add Collaborator Modal
                });


                // Handle removing a collaborator
                $(document).on('click', '.remove-collaborator-btn', function () {
                    var projID = $(this).data('proj-id');
                    var userID = $(this).data('user-id');

                    showCustomConfirm("DPWH Project Monitoring System", "Are you sure you want to remove this collaborator?")
                        .then(() => {
                            // User confirmed, proceed with AJAX request
                            $.ajax({
                                url: '../project-management/remove-collaborator',
                                type: 'POST',
                                data: { proj_ID: projID, user_ID: userID },
                                dataType: 'json',
                                success: function(response) {
                                    if (response.status === 'success') {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                        setTimeout(function() {
                                            location.reload();
                                        }, 500);
                                    } else {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                    }
                                },
                                error: function() {
                                    showCustomAlert("DPWH Project Monitoring System", "An error occurred while removing the collaborator.");
                                }
                            });
                        })
                        .catch(() => {
                            // User canceled, do nothing
                        });
                });

                // Handle "Delete Permanently" action
                $(document).on('click', '.delete-permanent-project-btn', function () {
                    var projectID = $(this).data('id');  // Get the project ID from data-id attribute

                    // Check if the button is disabled
                    if ($(this).hasClass('delete-permanent-disabled')) {
                        showCustomAlert("DPWH Project Monitoring System", "You can permanently delete this project after 30 days of archiving.");
                        return;
                    }

                    // Show custom confirm modal
                    showCustomConfirm("DPWH Project Monitoring System", "Are you sure you want to permanently delete this project? This action cannot be undone.")
                        .then(() => {
                            // User confirmed, proceed with AJAX request
                            $.ajax({
                                url: '../project-management/hard-delete-project',
                                type: 'POST',
                                data: { proj_ID: projectID },  // Send the project ID to the server
                                dataType: 'json',
                                success: function(response) {
                                    if(response.status === 'success') {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                    } else {
                                        showCustomAlert("DPWH Project Monitoring System", response.message);
                                    }
                                    setTimeout(function() {
                                        location.reload();  
                                    }, 500);
                                },
                                error: function(xhr, status, error) {
                                    showCustomAlert("DPWH Project Monitoring System", "An error occurred while deleting the project.");
                                    setTimeout(function() {
                                        location.reload();  
                                    }, 500);
                                }
                            });
                        })
                        .catch(() => {
                            // User canceled, do nothing
                        });
                });

                // Handle "Clear All" button click
                $('#clearAllBtn').on('click', function () {
                    // Update the count in the modal
                    $('#clearAllCount').text(archivedCount);

                    // Open the confirmation modal
                    $('#clearAllModal').modal('show');
                });

                // Handle confirming "Clear All"
                $('#confirmClearAll').on('click', function () {
                    // Proceed with AJAX request to delete all archived projects
                    $.ajax({
                        url: '../project-management/clear-deleted-projects',
                        type: 'POST',
                        data: {},  // No data needed
                        dataType: 'json',
                        success: function(response) {
                            if(response.status === 'success') {
                                showCustomAlert("DPWH Project Monitoring System", response.message);
                            } else {
                                showCustomAlert("DPWH Project Monitoring System", response.message);
                            }
                            setTimeout(function() {
                                location.reload();  
                            }, 500);
                        },
                        error: function(xhr, status, error) {
                            showCustomAlert("DPWH Project Monitoring System", "An error occurred while clearing archived projects.");
                            setTimeout(function() {
                                location.reload();  
                            }, 500);
                        }
                    });
                });

                // Function to close the "Clear All" modal
                window.closeClearAllModal = function() {
                    $('#clearAllModal').modal('hide');
                };

                $(document).on('click', '#requestEdit', function () {
                    var projID = $(this).data('proj-id');  // Get the project ID from the button's data-id attribute
                    $('#requestEditModal').modal('show');  // Show the modal
                    
                    // Attach the projID to the "Request" button inside the modal
                    $('#confirmRequestEdit').data('proj-id', projID);
                });
                
                $(document).on('click', '#confirmRequestEdit', function () {
                    var projID = $(this).data('proj-id');  // Get the project ID from the "Request" button's data-id attribute
                    var reason = $('#editRequestReason').val();  // Get the reason from the textarea
                    
                    if (!projID || !reason) {
                        showCustomAlert("DPWH Project Monitoring System", "Please provide a reason for your edit request.");
                        return;
                    }
                
                    // Send the reason along with the project ID to the server
                    $.ajax({
                        url: '../project-management/request-edit',
                        type: 'POST',
                        data: { proj_ID: projID, reason: reason },
                        success: function (response) {
                            response = JSON.parse(response);  // Make sure you're parsing the JSON response
                            if (response.status === 'success') {
                                showCustomAlert("DPWH Project Monitoring System", response.message);
                                $('#requestEditModal').modal('hide');  // Close the modal
                                location.reload();
                            } else {
                                showCustomAlert("DPWH Project Monitoring System", response.message);  // Display the error message
                            }
                        },
                        error: function () {
                            showCustomAlert("DPWH Project Monitoring System", "An error occurred while requesting to edit.");
                        }
                    });
                });




            

        });
        



        // Function to redirect to the progress page
        function redirectToProgress(proj_ID) {
            window.location.href = '../project-management/progress.php?proj_ID=' + proj_ID;
        }

        // Function to close the project modal
        function closeProjectModal() {
            // Clear the iframe src to stop displaying the PDF
            $('#pdfIframe').attr('src', '');
            // Reset the modal title
            $('.modal-title').text('Project Details');
            // Clear the data-proj-id attribute
            $('#generateModalPDFBtn').data('proj-id', '');
        }

        // Function to view documents
        function viewDocuments(event, proj_ID) {
            event.preventDefault(); // Prevent default link behavior

            // Redirect to documents.php with the proj_ID parameter
            window.location.href = '../project-management/documents.php?proj_ID=' + encodeURIComponent(proj_ID);
        }

        // Function to view collaborators modal
        function openCollaboratorsModal(proj_ID) {
            $('#collaboratorsModal' + proj_ID).modal('show');
        }

        // Function to close collaborators modal
        function closeCollaboratorsModal(proj_ID) {
            $('#collaboratorsModal' + proj_ID).modal('hide');
        }

        // Function to open Add Collaborator Modal
        function openAddCollaboratorModal(proj_ID) {
            $('#currentProjectID').val(proj_ID);  // Set the proj_ID in the hidden field
            $('#addCollaboratorModal').modal('show');  // Show the modal
        }

        // Function to close Add Collaborator Modal
        function closeAddCollaboratorModal() {
            $('#addCollaboratorModal').modal('hide');
        }

        // Handle Add Collaborator Form Submission
        $(document).on('submit', '#addCollaboratorForm', function(e) {
            e.preventDefault();
            var projID = $('#currentProjectID').val();  // Get the proj_ID from the hidden field

            if (projID === '') {
                showCustomAlert("DPWH Project Monitoring System", "Project ID is missing!");
                return;
            }

            var userID = $('#collaboratorSelect').val();  // Get the collaborator user ID
            if (userID === '') {
                showCustomAlert("DPWH Project Monitoring System", "Please select a user to add as a collaborator.");
                return;
            }

            $.ajax({
                url: '../project-management/add-collaborator',
                type: 'POST',
                data: { proj_ID: projID, user_ID: userID },  // Send both proj_ID and user_ID to the server
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        showCustomAlert("DPWH Project Monitoring System", response.message);
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        showCustomAlert("DPWH Project Monitoring System", response.message);
                    }
                },
                error: function() {
                    showCustomAlert("DPWH Project Monitoring System", "An error occurred while adding the collaborator.");
                }
            });
        });
        
        $(document).on('click', '.transfer-project-btn', function () {
    var projID = $(this).data('id');  // Get the project ID from data-id attribute
    $('#transferProjectModal' + projID).modal('show');  // Manually show the modal
});

        
        function transferProject(projID) {
            var newOwnerID = $('#newOwner' + projID).val();
            $.ajax({
                url: '../project-management/transfer-handler', // Ensure this path is correct
                type: 'POST',
                data: {
                    proj_ID: projID,
                    newOwner_ID: newOwnerID
                },
                success: function(response) {
                    // Assume response is JSON
                    var res = JSON.parse(response);
                    alert(res.message);
                    if (res.status === 'success') {
                        location.reload(); // Refresh page to show changes
                    }
                },
                error: function() {
                    alert('Error transferring project.');
                }
            });
        }
    </script>

</body>
</html>
