<!-- includes/sidebar.php -->
<?php
// includes/sidebar.php

// Start the session and set default session variables for demonstration
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// For demonstration purposes, set some session variables if not already set
$_SESSION['user_photo'] = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : '../images/user1.jpg';
$_SESSION['user_first_name'] = isset($_SESSION['user_first_name']) ? $_SESSION['user_first_name'] : 'John';
$_SESSION['user_middle_initial'] = isset($_SESSION['user_middle_initial']) ? $_SESSION['user_middle_initial'] : ''; // Added
$_SESSION['user_last_name'] = isset($_SESSION['user_last_name']) ? $_SESSION['user_last_name'] : 'Doe';
$_SESSION['user_suffix'] = isset($_SESSION['user_suffix']) ? $_SESSION['user_suffix'] : 'None'; // New session variable
$_SESSION['user_position'] = isset($_SESSION['user_position']) ? $_SESSION['user_position'] : 'Engineer';
$_SESSION['user_email'] = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : 'john.doe@example.com';
$_SESSION['user_id_type'] = isset($_SESSION['user_id_type']) ? $_SESSION['user_id_type'] : 'None';
$_SESSION['user_id_number'] = isset($_SESSION['user_id_number']) ? $_SESSION['user_id_number'] : 'None';
$_SESSION['role_id'] = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 3; // 1 for Admin, 2 for Member, 3 for Guest

// Function to get role name based on role_id
function getRoleName($role_id) {
    switch ($role_id) {
        case 1:
            return 'Admin';
        case 2:
            return 'Member';
        case 3:
        default:
            return 'Guest';
    }
}

// Get the role name
$role_name = getRoleName($_SESSION['role_id']);

// Construct full name with middle initial and suffix if available
$full_name = $_SESSION['user_first_name'];
if (!empty($_SESSION['user_middle_initial'])) {
    $full_name .= ' ' . strtoupper($_SESSION['user_middle_initial']) . '.';
}
$full_name .= ' ' . $_SESSION['user_last_name'];

if (!empty($_SESSION['user_suffix']) && $_SESSION['user_suffix'] !== 'None') {
    $full_name .= ', ' . htmlspecialchars($_SESSION['user_suffix']);
}

// Combine full name with role name
$display_name = $full_name;
?>
<div id="sidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header-container">
        <div class="sidebar-header">
            <img src="../images/dpwh-icon.png" alt="Logo">
            <div class="sidebar-title">
                <h3>DPWH</h3>
                <h4>Second District Engineering Office</h4>
            </div>
        </div>
    </div>

    <!-- Profile Information -->
    <div class="profile-info">
        <img src="<?php echo htmlspecialchars($_SESSION['user_photo']); ?>" alt="Profile Picture" class="profile-pic border-<?php echo strtolower($role_name); ?>" data-toggle="tooltip" title="<?php echo $role_name; ?>">
        <div class="profile-details">
            <h4><?php echo htmlspecialchars($display_name); ?></h4>
            <h5><?php echo htmlspecialchars($_SESSION['user_position']); ?></h5>
            <p style="font-size: 12px;"><?php echo htmlspecialchars($_SESSION['user_email']); ?></p>
            <?php if ($_SESSION['user_id_type'] !== 'None' && !empty($_SESSION['user_id_number'])): ?>
                <p style="font-size: 12px;">
                    <?php echo htmlspecialchars($_SESSION['user_id_type']) . ': ' . htmlspecialchars($_SESSION['user_id_number']); ?>
                </p>
            <?php endif; ?>

            <?php if ($_SESSION['role_id'] == 3): ?>
                <p style="font-size: 11px; color: red; margin-bottom: 0px;">Account Pending for Admin Approval</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- New "My Projects" Button -->
    <div class="my-projects-button">
        <a href="../pages/profile.php" class="btn-my-projects"  data-toggle="tooltip" data-placement="right" title="My Projects">
            <i class="fas fa-folder-open"></i>
            <span>My Projects</span>
        </a>
    </div>

    <!-- Sidebar Menu -->
    <ul class="components">
        <!-- Profile Section as Direct Buttons -->
        <li class="profile-buttons">
            <a href="../users-management/all-users.php" class="sidebar-button" data-toggle="tooltip" data-placement="right" title="All Users">
                <div class="icon"><i class="fas fa-users"></i></div>
                <div class="text">Users</div>
            </a>
            <a href="../users-management/contractors.php" class="sidebar-button" data-toggle="tooltip" data-placement="right" title="All Contractors">
                <div class="icon"><i class="fas fa-hard-hat"></i></div>
                <div class="text">Contractors</div>
            </a>
            <a href="../pages/reports.php" class="sidebar-button" data-toggle="tooltip" data-placement="right" title="My Profile">
                <div class="icon"><i class="fas fa-file-alt"></i></div>
                <div class="text">Reports</div>
            </a>
            <?php if ($_SESSION['role_id'] == 1): ?> <!-- Only show for Admin -->
                <a href="../pages/edit-requests.php" class="sidebar-button" data-toggle="tooltip" data-placement="right" title="Edit Requests">
                    <div class="icon"><i class="fas fa-edit"></i></div>
                    <div class="text">Edit Requests</div>
                </a>
                <a href="../pages/request-view.php" class="sidebar-button" data-toggle="tooltip" data-placement="right" title="Request to View">
                    <div class="icon"><i class="fas fa-user-check"></i></div>
                    <div class="text">Request to View</div>
                </a>
            <?php endif; ?>
        </li>
    </ul>
</div>

<!-- Revamped Sidebar Icons Strip as Four Squares -->
<div id="sidebar-strip">
    <div class="strip-square" onclick="location.href='../users-management/all-users.php';" data-toggle="tooltip" data-placement="right" title="Users">
        <i class="fas fa-users"></i>
    </div>
    <div class="strip-square" onclick="location.href='../users-management/contractors.php';" data-toggle="tooltip" data-placement="right" title="Contractors">
        <i class="fas fa-hard-hat"></i>
    </div>
    <div class="strip-square" onclick="location.href='../pages/reports.php';" data-toggle="tooltip" data-placement="right" title="Reports">
        <i class="fas fa-file-alt"></i>
    </div>  
    <?php if ($_SESSION['role_id'] == 1): ?> <!-- Only show for Admin -->
        <div class="strip-square" onclick="location.href='../pages/edit-requests.php';" data-toggle="tooltip" data-placement="right" title="Edit Requests">
            <i class="fas fa-edit"></i>
        </div>
        <div class="strip-square" onclick="location.href='../pages/request-view.php';" data-toggle="tooltip" data-placement="right" title="Request to View">
            <i class="fas fa-user-check"></i>
        </div>
    <?php endif; ?>
</div>
