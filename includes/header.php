<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: ../index.php");
    exit;
}

$role = $_SESSION['role'];

// Ensure DB connection is available if needed later, but avoid including it if already set
if (!isset($conn)) {
    // Check if db.php exists before including
    if (file_exists('../config/db.php')) {
        include '../config/db.php';
    } else {
        // Handle error: DB config not found
        // You might want to log this or display a user-friendly error
        error_log("Database configuration file not found at ../config/db.php");
        // Optionally die or redirect
        // die("Critical error: Database configuration missing.");
    }
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_id = $_SESSION['user_id'] ?? 0; // Get user ID for notifications
$user_role = $_SESSION['role'] ?? 'guest'; // Get role

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) : 'Dentaly Dashboard'; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
        integrity="sha512-SnH5WK+bZxgPHs44uWIX+LLJAJ9/2PkPKZ5QiAj6Ta86w+fsb2TkcmfRyVX3pBnMFcV7oQPJkl9QevSCWr3W6A=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Custom Styles -->
    <link rel="stylesheet" href="../assets/css/style.css">

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

    <!-- Add some basic styles for notifications -->
    <style>
        .notification-dropdown-menu {
            min-width: 300px;
            /* Adjust as needed */
            max-height: 400px;
            overflow-y: auto;
        }

        .notification-item {
            white-space: normal;
            /* Allow text wrapping */
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item .small {
            font-size: 0.8em;
            color: #777;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
            /* Light background for unread */
        }

        .notification-icon-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            padding: 0.25em 0.5em;
            font-size: 0.7em;
        }

        .sidebar {
            min-height: 100vh;
            background: #fff;
            border-right: 1px solid #e5e5e5;
            box-shadow: 0 8px 32px 0 rgba(13, 110, 253, 0.07), 2px 0 16px 0 rgba(0, 0, 0, 0.04);
            border-radius: 0 2rem 2rem 0;
            overflow: hidden;
            backdrop-filter: blur(2px);
            display: flex;
            flex-direction: column;
            padding: 0;
            transition: box-shadow 0.2s;
            z-index: 1030;
        }

        .sidebar-header {
            padding: 2rem 1.5rem 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            text-align: center;
            background: linear-gradient(90deg, #fff 80%, #f0f4ff 100%);
            box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.03);
            border-top-left-radius: 0;
            border-top-right-radius: 2rem;
        }

        .sidebar-brand-link {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            text-decoration: none;
            font-size: 1.5rem;
            font-weight: 700;
            letter-spacing: 1px;
            transition: transform 0.18s cubic-bezier(.4, 2, .6, 1), box-shadow 0.18s;
        }

        .sidebar-brand-link:hover {
            transform: scale(1.04) rotate(-1deg);
            box-shadow: 0 4px 16px 0 rgba(13, 110, 253, 0.08);
            background: #f0f4ff;
            border-radius: 1rem;
        }

        .sidebar-brand-icon {
            font-size: 2.2rem;
            color: #0d6efd;
            filter: drop-shadow(0 2px 6px rgba(13, 110, 253, 0.08));
            animation: tooth-bounce 2.5s infinite cubic-bezier(.4, 2, .6, 1);
        }

        @keyframes tooth-bounce {

            0%,
            100% {
                transform: translateY(0);
            }

            10% {
                transform: translateY(-4px);
            }

            20% {
                transform: translateY(0);
            }
        }

        .sidebar-user {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.5rem 1.5rem 1rem 1.5rem;
            border-bottom: 1px solid #f0f0f0;
            background: #fafbfc;
            position: relative;
        }

        .user-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #e9ecef;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.7rem;
            color: #0d6efd;
            box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.07);
            border: 2.5px solid #fff;
            transition: box-shadow 0.18s, transform 0.18s;
        }

        .user-avatar:hover {
            box-shadow: 0 4px 16px 0 rgba(13, 110, 253, 0.13);
            transform: scale(1.08) rotate(-2deg);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.1rem;
            transition: color 0.18s;
        }

        .user-name {
            font-weight: 700;
            font-size: 1.1rem;
            color: #222;
            letter-spacing: 0.5px;
            text-shadow: 0 1px 0 #f0f4ff;
        }

        .user-status {
            font-size: 0.9rem;
            color: #6c757d;
            font-style: italic;
        }

        .sidebar-nav {
            flex-grow: 1;
            padding: 1.5rem 0.5rem 1rem 0.5rem;
            margin-top: 0.5rem;
        }

        .sidebar-nav .nav {
            gap: 0.25rem;
        }

        .sidebar-nav .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.85rem 1.25rem;
            border-radius: 0.7rem;
            font-size: 1.05rem;
            font-weight: 500;
            color: #222;
            transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.18s;
            position: relative;
            overflow: hidden;
        }

        .sidebar-nav .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background: #0d6efd;
            opacity: 0;
            transition: opacity 0.18s;
            border-radius: 0 4px 4px 0;
        }

        .sidebar-nav .nav-link.active::before,
        .sidebar-nav .nav-link:hover::before,
        .sidebar-nav .nav-link:focus::before {
            opacity: 1;
        }

        .sidebar-nav .nav-link.active,
        .sidebar-nav .nav-link:hover,
        .sidebar-nav .nav-link:focus {
            background: #f0f4ff;
            color: #0d6efd;
            box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.06);
            text-decoration: none;
            transform: translateX(2px) scale(1.03);
            font-weight: 700;
            letter-spacing: 0.5px;
        }

        .sidebar-nav .nav-link .fa-fw {
            font-size: 1.2rem;
            opacity: 0.88;
            transition: color 0.18s, transform 0.18s;
        }

        .sidebar-nav .nav-link.active .fa-fw,
        .sidebar-nav .nav-link:hover .fa-fw {
            color: #0d6efd;
            transform: scale(1.18) rotate(-8deg);
        }

        .sidebar-footer {
            padding: 1.5rem 1.5rem 1.5rem 1.5rem;
            border-top: 1px solid #f0f0f0;
            background: #fff;
            box-shadow: 0 -2px 8px 0 rgba(13, 110, 253, 0.03);
            border-bottom-right-radius: 2rem;
        }

        .logout-button {
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 0.7rem;
            padding: 0.7rem 0;
            transition: background 0.18s, color 0.18s, box-shadow 0.18s, transform 0.18s;
            border: 2px solid #dc3545;
            background: #fff;
            color: #dc3545;
            font-size: 1.05rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .logout-button:hover,
        .logout-button:focus {
            background: #dc3545;
            color: #fff;
            border-color: #dc3545;
            box-shadow: 0 4px 12px 0 rgba(220, 53, 69, 0.25);
            transform: translateY(-2px) scale(1.02);
            text-decoration: none;
        }

        .logout-button:active {
            transform: translateY(0) scale(1);
            box-shadow: 0 2px 6px 0 rgba(220, 53, 69, 0.15);
        }

        .logout-button i {
            font-size: 1.1rem;
            transition: transform 0.18s;
        }

        .logout-button:hover i {
            transform: rotate(-10deg);
        }

        @media (max-width: 991.98px) {
            .sidebar {
                min-width: 0;
                width: 80vw;
                max-width: 320px;
                position: fixed;
                left: -100vw;
                top: 0;
                bottom: 0;
                z-index: 1050;
                transition: left 0.3s;
                box-shadow: 2px 0 16px 0 rgba(0, 0, 0, 0.12);
                border-radius: 0 1.5rem 1.5rem 0;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .sidebar-header {
                border-top-right-radius: 1.5rem;
            }

            .sidebar-footer {
                border-bottom-right-radius: 1.5rem;
            }
        }
    </style>

</head>

<body>
    <!-- Sidebar -->
    <aside id="sidebar" class="sidebar">
        <!-- Brand/Logo -->
        <div class="sidebar-header">
            <a href="index.php" class="sidebar-brand-link">
                <i class="fas fa-tooth sidebar-brand-icon"></i>
                <span class="sidebar-brand-text">Dentaly</span>
            </a>
            <div class="sidebar-brand-desc">Dental Clinic</div>
        </div>

        <!-- User Info -->
        <div class="sidebar-user">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars(ucfirst($_SESSION['role'])); ?></div>
                <div class="user-status">Welcome!</div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="sidebar-nav flex-grow-1">
            <ul class="nav nav-pills flex-column">
                <li class="nav-item">
                    <a href="index.php" class="nav-link <?php echo $current_page == 'index.php' ? 'active' : ''; ?>"
                        title="Dashboard">
                        <i class="fas fa-tachometer-alt fa-fw me-2"></i> Dashboard
                    </a>
                </li>

                <?php // Dynamically generate menu based on role
                $menuItems = [];
                // --- ROLE-BASED MENU ITEMS ---
                if ($user_role === 'admin') {
                    $menuItems = [
                        ['link' => 'manage_appointments.php', 'icon' => 'fas fa-calendar-check', 'label' => 'Appointments'],
                        ['link' => 'manage_users.php', 'icon' => 'fas fa-users-cog', 'label' => 'Manage Users'],
                        ['link' => 'manage_services.php', 'icon' => 'fas fa-briefcase-medical', 'label' => 'Manage Services'],
                        ['link' => 'client_history.php', 'icon' => 'fas fa-history', 'label' => 'Client History'],
                    ];
                } elseif ($user_role === 'doctor') {
                    $menuItems = [
                        ['link' => 'manage_appointments.php', 'icon' => 'fas fa-calendar-alt', 'label' => 'My Appointments'],
                        ['link' => 'view_patient_history.php', 'icon' => 'fas fa-notes-medical', 'label' => 'Patient Records'],
                        ['link' => 'my_profile.php', 'icon' => 'fas fa-user-edit', 'label' => 'My Profile'], // <-- ADD THIS LINE
                        // Add 'My Schedule' or similar if needed
                    ];
                } elseif ($user_role === 'receptionist') {
                    $menuItems = [
                        ['link' => 'manage_appointments.php', 'icon' => 'fas fa-calendar-day', 'label' => 'View Appointments'],
                        ['link' => 'add_appointment.php', 'icon' => 'fas fa-calendar-plus', 'label' => 'Book Appointment'],
                        ['link' => 'my_profile.php', 'icon' => 'fas fa-user-edit', 'label' => 'My Profile'],
                        ['link' => 'client_history.php', 'icon' => 'fas fa-history', 'label' => 'Client History'],

                    ];
                }
                // --- END ROLE-BASED MENU ITEMS ---
                
                foreach ($menuItems as $item): ?>
                    <li class="nav-item">
                        <a href="<?php echo $item['link']; ?>"
                            class="nav-link <?php echo $current_page == $item['link'] ? 'active' : ''; ?>"
                            title="<?php echo htmlspecialchars($item['label']); ?>">
                            <i class="<?php echo $item['icon']; ?> fa-fw me-2"></i>
                            <?php echo htmlspecialchars($item['label']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <!-- Logout -->
        <div class="sidebar-footer">
            <a href="../auth/logout.php" class="btn btn-outline-light w-100 logout-button" title="Logout">
                <i class="fas fa-sign-out-alt fa-fw me-2"></i> Logout
            </a>
        </div>
    </aside>

    <!-- Mobile Sidebar Toggle -->
    <button class="btn btn-primary sidebar-toggle d-lg-none" type="button" aria-label="Toggle sidebar">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Main Content Wrapper -->
    <div class="main-content">

        <!-- Main Page Content Start -->
        <main class="container-fluid">
            <!-- The rest of the page content from other files will go here -->
        </main>


</body>