<?php
// fetch_projects.php
session_start();
include '../includes/database.php';

// Function to escape HTML entities
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo '<tr><td colspan="12" class="text-center">Unauthorized access.</td></tr>';
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

$isTrashView = isset($_GET['trash']) && $_GET['trash'] == '1' ? 1 : 0;

// Initialize search parameters
$search_proj_ID = isset($_GET['search_proj_ID']) ? trim($_GET['search_proj_ID']) : '';
$search_location = isset($_GET['search_location']) ? trim($_GET['search_location']) : '';
$search_contractor = isset($_GET['search_contractor']) ? trim($_GET['search_contractor']) : '';
$search_status = isset($_GET['search_status']) ? trim($_GET['search_status']) : '';
$search_effect_date = isset($_GET['search_effect_date']) ? trim($_GET['search_effect_date']) : '';
$search_notice_date = isset($_GET['search_notice_date']) ? trim($_GET['search_notice_date']) : '';

$db = ConnectDB();

// Base SQL query
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
            c.cont_name, 
            p.proj_isApproved,
            IFNULL(SUM(CASE WHEN cte.status = 'Approved' THEN cte.cte_ext_days ELSE 0 END), 0) AS total_cte_ext_days
          FROM projects p 
          LEFT JOIN contractors c ON p.cont_ID = c.cont_ID 
          LEFT JOIN `contract-time-extension` cte ON p.proj_ID = cte.proj_ID
          WHERE p.proj_isDeleted = ?";

// Initialize parameters array
$params = [$isTrashView];
$param_types = 'i';

// Append search filters to the query
if ($search_proj_ID !== '') {
    $query .= " AND p.proj_ID LIKE ?";
    $params[] = '%' . $search_proj_ID . '%';
    $param_types .= 's';
}

if ($search_location !== '') {
    $query .= " AND p.proj_cont_loc = ?";
    $params[] = $search_location;
    $param_types .= 's';
}

if ($search_contractor !== '') {
    $query .= " AND c.cont_ID = ?";
    $params[] = $search_contractor;
    $param_types .= 'i';
}

if ($search_status !== '') {
    $query .= " AND p.proj_status = ?";
    $params[] = $search_status;
    $param_types .= 's';
}

if ($search_effect_date !== '') {
    $query .= " AND p.proj_effect_date = ?";
    $params[] = $search_effect_date;
    $param_types .= 's';
}

if ($search_notice_date !== '') {
    // Assuming 'proj_NOA' is the correct column for Notice to Proceed Date
    $query .= " AND p.proj_NOA = ?";
    $params[] = $search_notice_date;
    $param_types .= 's';
}

$query .= " GROUP BY p.proj_ID";

// Order the projects as specified
$query .= " ORDER BY FIELD(p.proj_status, 'Ongoing', 'Not Yet Started', 'Completed', 'Suspended')";

// Prepare the statement
$stmt = $db->prepare($query);
if (!$stmt) {
    echo '<tr><td colspan="' . ($showApproval ? '12' : '11') . '" class="text-center">Error preparing the statement.</td></tr>';
    exit();
}

// Bind parameters dynamically
$stmt->bind_param($param_types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

// Fetch all projects
$projects = [];
while ($row = $result->fetch_assoc()) {
    $projects[] = $row;
}

// Function to format date
function formatDate($date) {
    return $date ? date("F d, Y", strtotime($date)) : 'N/A';
}

?>
<table class="table table-striped project-table" id="projectTable">
    <thead>
        <tr>
            <th class="col-proj-id">Project ID</th>
            <th class="col-proj-name">Project Name & Location</th>
            <th class="col-contractor">Contractor</th>
            <th class="col-budget">Budget</th>
            <th class="col-contract-duration">Contract Duration (Days)</th>
            <th class="col-effect-date">Effectivity Date</th>
            <th class="col-notice-date">Notice to Proceed</th>
            <th class="col-progress">Progress</th>
            <th class="col-status">Status</th>
            <?php if ($showApproval): ?>
                <th class="col-approval">Approval</th> <!-- Approval Column -->
            <?php endif; ?>
            <th class="col-actions">Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (count($projects) > 0): ?>
            <?php foreach ($projects as $row): ?>
                <tr>
                    <td class="col-proj-id"><?= escape($row['proj_ID']); ?></td>
                    <td class="col-proj-name"><?= escape($row['proj_cont_name'] . ' - ' . $row['proj_cont_loc']); ?></td>
                    <td class="col-contractor"><?= escape($row['cont_name']); ?></td>
                    <td class="col-budget"><span>&#8369;</span><?= escape(number_format($row['proj_cont_amt'], 2)); ?></td>
                    <td class="col-contract-duration">
                        <?= escape($row['proj_cont_duration'] + $row['proj_unwork_days'] + ($row['proj_isApproved'] == 1 ? $row['total_cte_ext_days'] : 0)); ?> days
                    </td>
                    <td class="col-effect-date"><?= formatDate($row['proj_effect_date']); ?></td>
                    <td class="col-notice-date"><?= formatDate($row['proj_NOA']); ?></td>
                    <td class="col-progress">
                        <?php 
                        $progress = isset($row['proj_progress']) ? $row['proj_progress'] : 0;
                        $progress = min($progress, 100); // Ensure progress doesn't exceed 100%
                        ?>
                        <div class="progress position-relative" style="border: 2px solid #007bff; border-radius: 5px;">
                            <div 
                                class="progress-bar" 
                                role="progressbar" 
                                style="width: <?= escape($progress); ?>%;" 
                                aria-valuenow="<?= escape($progress); ?>" 
                                aria-valuemin="0" 
                                aria-valuemax="100"
                            ></div>
                            <span class="progress-text"><?= escape($progress); ?>%</span>
                        </div>
                    </td>
                    <td class="col-status"><?= escape($row['proj_status']); ?></td>
                    <?php if ($showApproval): ?>
                        <td class="col-approval">
                            <select 
                                class="form-control form-control-sm approval-select" 
                                data-proj-id="<?= escape($row['proj_ID']); ?>"
                                <?php if ($isApprovalDisabled) echo 'disabled'; ?>
                            >
                                <option value="1" <?= $row['proj_isApproved'] == 1 ? 'selected' : ''; ?>>Approved</option>
                                <option value="0" <?= $row['proj_isApproved'] == 0 ? 'selected' : ''; ?>>Not Yet Approved</option>
                            </select>
                        </td>
                    <?php endif; ?>
                    <!-- Inside your projects table loop -->
                    <td class="col-actions">
                        <div class="dropdown">
                            <button 
                                class="btn btn-link btn-sm dropdown-toggle" 
                                type="button" 
                                id="dropdownMenuButton<?= escape($row['proj_ID']); ?>" 
                                data-toggle="dropdown" 
                                aria-haspopup="true" 
                                aria-expanded="false" 
                                data-boundary="viewport">
                                <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-menu dropdown-menu-right" aria-labelledby="dropdownMenuButton<?= escape($row['proj_ID']); ?>">
                            <?php if ($role_id == 1 || ($role_id == 2 && $position === "Project Inspector")): ?>
                                <!-- Actions available to Admin and Semi-Admin (Project Inspector) -->
                                <?php if ($row['proj_isApproved'] == 1): ?>
                                    <a class="dropdown-item" href="#" onclick="redirectToProgress('<?= escape($row['proj_ID']); ?>')">
                                        <i class="fas fa-plus-circle"></i> Add Progress
                                    </a>
                                <?php else: ?>
                                    <a class="dropdown-item disabled" href="#" title="Approve the project to add progress">
                                        <i class="fas fa-plus-circle"></i> Add Progress
                                    </a>
                                <?php endif; ?>
                                <a class="dropdown-item view-project-btn" href="#" data-id="<?= escape($row['proj_ID']); ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <!-- Modify the Edit option -->
                                <a 
                                    class="dropdown-item edit-project-btn<?php if ($row['proj_isApproved'] == 1) echo ' disabled'; ?>" 
                                    href="#" 
                                    data-id="<?= escape($row['proj_ID']); ?>" 
                                    <?php if ($row['proj_isApproved'] == 1) echo 'tabindex="-1" aria-disabled="true"'; ?>>
                                    <i class="fas fa-edit"></i> Edit
                                </a>
                                <?php if (!$isTrashView): ?>
                                    <a class="dropdown-item delete-project-btn" href="#" data-id="<?= escape($row['proj_ID']); ?>">
                                        <i class="fas fa-archive"></i> Archive
                                    </a>
                                <?php else: ?>
                                    <a class="dropdown-item restore-project-btn" href="#" data-id="<?= escape($row['proj_ID']); ?>">
                                        <i class="fas fa-undo"></i> Restore
                                    </a>
                                <?php endif; ?>
                                <!-- Modification starts here -->
                                <a class="dropdown-item<?php if ($row['proj_isApproved'] == 0) echo ' disabled'; ?>" href="#" data-proj-id="<?= escape($row['proj_ID']); ?>" onclick="viewDocuments(event, '<?= escape($row['proj_ID']); ?>')">
                                    <i class="fas fa-folder-open"></i> Documents
                                </a>
                                <!-- Modification ends here -->
                            <?php elseif ($role_id == 3): ?>
                                <!-- Actions available to Members -->
                                <a class="dropdown-item view-project-btn" href="#" data-id="<?= escape($row['proj_ID']); ?>">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="<?= $showApproval ? '12' : '11'; ?>" class="text-center">No projects found.</td>
            </tr>
        <?php endif; ?>
    </tbody>
</table>
