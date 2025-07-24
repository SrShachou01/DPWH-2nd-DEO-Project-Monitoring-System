<!-- includes/navbar.php -->
<?php
// Start the session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user session variables are set
$_SESSION['user_photo'] = isset($_SESSION['user_photo']) ? $_SESSION['user_photo'] : '../images/user1.jpg';
$_SESSION['user_first_name'] = isset($_SESSION['user_first_name']) ? $_SESSION['user_first_name'] : 'John';
$_SESSION['user_last_name'] = isset($_SESSION['user_last_name']) ? $_SESSION['user_last_name'] : 'Doe';
$_SESSION['user_username'] = isset($_SESSION['user_username']) ? $_SESSION['user_username'] : 'johndigong';
$_SESSION['role_id'] = isset($_SESSION['role_id']) ? $_SESSION['role_id'] : 3; // 3 for guest



// Get the role name
$role_name = getRoleName($_SESSION['role_id']);
?>

<nav class="navbar">
    <!-- Collapse/Expand Button -->
    <button type="button" id="sidebarCollapse" class="btn btn-toggle" data-toggle="tooltip" data-placement="right" title="Collapse Sidebar" aria-expanded="true" aria-controls="sidebar">
        <i id="toggleIcon" class="fas fa-bars toggle-icon" aria-hidden="true"></i>
        <span class="sr-only">Toggle Sidebar</span>
    </button>

    <!-- DPWH Icon and Name -->
    <div class="navbar-brand d-flex align-items-center ml-3">
        <img src="../images/dpwh-icon.png" alt="DPWH Logo" class="navbar-logo mr-2" data-toggle="tooltip" data-placement="right" title="DPWH 2nd District Logo">
        <div class="navbar-title">
            <h3 class="mb-0">DPWH</h3>
            <h5 class="mb-0">2nd District Engineering Office</h5>
        </div>
    </div>

    <!-- Middle Navbar Buttons with Dropdowns -->
    <div class="navbar-middle-icons mx-auto d-flex">
        <!-- Dashboard Button -->
        <button class="nav-button nav-icon" data-toggle="tooltip" data-placement="bottom" title="Dashboard" onclick="location.href='../pages/dashboard.php'">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </button>

        <!-- Projects Dropdown -->
        <div class="nav-button nav-icon dropdown ml-4" data-toggle="tooltip" data-placement="right" title="Projects">
            <button class="dropdown-toggle nav-button nav-icon" id="projectsDropdownMenu" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="fas fa-briefcase"></i> Projects
            </button>
            <div class="dropdown-menu" aria-labelledby="projectsDropdownMenu">
                <a class="dropdown-item" href="../pages/projects.php" data-toggle="tooltip" data-placement="right" title="Projects">
                    <i class="fas fa-project-diagram mr-2"></i>Projects</a>
                <a class="dropdown-item" href="../pages/projects.php?trash=1" data-toggle="tooltip" data-placement="right" title="Archived Projects">
                    <i class="fas fa-archive mr-2"></i>Archived Projects</a>
            </div>
        </div>

        <!-- Profile Dropdown Removed Here -->

        <!-- About Us Button -->
        <button class="nav-button nav-icon ml-4" data-toggle="tooltip" data-placement="bottom" title="About Us" onclick="location.href='../pages/about-us.php'">
            <i class="fas fa-info-circle"></i> About Us
        </button>
    </div>

    <!-- User Profile Dropdown -->
    <div class="navbar-user dropdown ml-auto">
        <a href="#" class="d-flex align-items-center dropdown-toggle" id="userDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($_SESSION['user_photo']); ?>" alt="Profile Picture" class="navbar-user-pic border-<?php echo strtolower($role_name); ?>" data-toggle="tooltip" title="<?php echo $role_name; ?>">
            <span class="navbar-user-name ml-2">
                <?php echo htmlspecialchars($_SESSION['user_username']); ?>
            </span>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
            <a class="dropdown-item" href="../pages/profile.php" data-toggle="tooltip" data-placement="right" title="View Profile">
                <i class="fas fa-edit mr-2"></i>View Profile</a>
            <div class="dropdown-divider"></div>
            <a class="dropdown-item" href="../logins/logout.php" data-toggle="tooltip" data-placement="right" title="Logout">
                <i class="fas fa-sign-out-alt mr-2"></i>Logout</a>
        </div>
    </div>
</nav>
