<?php
// No need for error reporting changes unless debugging specifically
include '../config/db.php'; // Connect to DB first

// Define page title *before* including header
$page_title = 'Admin Dashboard';
include '../includes/header.php'; // Include the header - this starts the HTML, head, body, sidebar, and main-content div

// Check if the user is actually an admin - redirect if not
if ($_SESSION['role'] !== 'admin') {
    // Redirect to a different page or show an error
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php'; // Include footer to close HTML properly
    exit; // Stop script execution
}

// Fetch dashboard data
try {
    // Core Metrics
    $total_doctors = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'doctor'")->fetchColumn();
    $total_receptionists = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'receptionist'")->fetchColumn();
    $total_patients = $conn->query("SELECT COUNT(*) FROM patients")->fetchColumn();
    $total_appointments = $conn->query("SELECT COUNT(*) FROM appointments")->fetchColumn();

    // Chart Data
    $pending_appointments_count = $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending'")->fetchColumn();
    $completed_appointments_count = $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'")->fetchColumn();
    $canceled_appointments_count = $conn->query("SELECT COUNT(*) FROM appointments WHERE status = 'canceled'")->fetchColumn();

    // New Widgets Data
    $appointments_today = $conn->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()")->fetchColumn();

    // Upcoming Appointments (Limit to 5 for example)
    $upcoming_query = "
        SELECT
            a.id,
            a.appointment_date,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            d.name AS doctor_name,
            s.service_name
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        JOIN services s ON a.service_id = s.id
        WHERE a.status = 'pending' AND a.appointment_date >= NOW()
        ORDER BY a.appointment_date ASC
        LIMIT 5
    ";
    $upcoming_stmt = $conn->query($upcoming_query);
    $upcoming_appointments = $upcoming_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Example: Revenue Overview (Requires accurate price data and logic)
    $revenue_today = $conn->query("SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status = 'completed' AND DATE(a.appointment_date) = CURDATE()")->fetchColumn() ?: 0;
    $revenue_month = $conn->query("SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status = 'completed' AND MONTH(a.appointment_date) = MONTH(CURDATE()) AND YEAR(a.appointment_date) = YEAR(CURDATE())")->fetchColumn() ?: 0;


} catch (PDOException $e) {
    // Handle potential DB errors gracefully
    echo '<div class="alert alert-danger m-3">Database error: ' . $e->getMessage() . '</div>';
    include '../includes/footer.php';
    exit;
}

?>

<!-- Page-Specific CSS (Load after global CSS from header) -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/main.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://unpkg.com/tippy.js@6/dist/tippy.css" /> <!-- Tippy CSS -->
<style>
    /* Dashboard Specific Styles */
    .metric-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        border: none;
        /* Remove default border if using shadow */
    }

    .metric-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .metric-card .card-header {
        font-weight: 600;
        border-bottom: none;
        /* Cleaner look */
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .metric-card .card-body h3 {
        color: var(--primary-color);
        font-weight: 700;
        margin-bottom: 0;
        /* Remove default margin */
        font-size: 2.25rem;
        /* Larger number */
    }

    #appointmentCalendar {
        height: 650px;
        /* Fixed height can be better sometimes */
    }

    .fc-event {
        cursor: pointer;
        border: none !important;
        padding: 4px 6px;
        font-size: 0.85em;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }

    .fc-event:hover {
        opacity: 0.9;
    }

    /* Tippy Tooltip Style */
    .tippy-box[data-theme~='light-border'] {
        border: 1px solid var(--border-color);
        font-size: 0.9rem;
    }

    .tippy-box[data-theme~='light-border'] .tippy-content {
        padding: 0.5rem 0.75rem;
    }

    /* Chart Container */
    #chartContainer {
        position: relative;
        height: 300px;
        /* Adjust as needed */
        width: 100%;
    }

    #appointmentChart {
        max-width: 100%;
        max-height: 100%;
    }

    /* Premium Dashboard Enhancements */
    .metric-card {
        border-radius: 1.25rem;
        box-shadow: 0 4px 24px 0 rgba(13, 110, 253, 0.07), 0 1.5px 6px 0 rgba(0, 0, 0, 0.04);
        background: linear-gradient(120deg, #fff 80%, #f0f4ff 100%);
        border: none;
        overflow: hidden;
        position: relative;
    }

    .metric-card::after {
        content: '';
        position: absolute;
        right: -40px;
        top: -40px;
        width: 80px;
        height: 80px;
        background: rgba(13, 110, 253, 0.04);
        border-radius: 50%;
        z-index: 0;
    }

    .metric-card .card-body {
        position: relative;
        z-index: 1;
    }

    .icon-circle {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.10);
        border: 2px solid #fff;
        transition: transform 0.18s, box-shadow 0.18s;
    }

    .metric-card:hover .icon-circle {
        transform: scale(1.12) rotate(-8deg);
        box-shadow: 0 6px 18px 0 rgba(13, 110, 253, 0.18);
    }

    .metric-label {
        font-size: 1rem;
        font-weight: 500;
        letter-spacing: 0.2px;
        margin-bottom: 0.15rem;
    }

    .metric-value {
        font-size: 2.1rem;
        font-weight: 800;
        color: #0d6efd;
        letter-spacing: 1px;
        text-shadow: 0 1px 0 #f0f4ff;
    }

    .bg-primary-soft {
        background: rgba(13, 110, 253, 0.08) !important;
    }

    .bg-success-soft {
        background: rgba(25, 135, 84, 0.08) !important;
    }

    .icon-circle.bg-warning {
        color: #856404 !important;
    }

    .upcoming-appt-item {
        border-left: 4px solid #0d6efd;
        border-radius: 0.7rem;
        margin: 0.5rem 0;
        box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.04);
        transition: box-shadow 0.18s, transform 0.18s;
        position: relative;
        overflow: hidden;
    }

    .upcoming-appt-item:hover {
        box-shadow: 0 6px 18px 0 rgba(13, 110, 253, 0.11);
        transform: scale(1.02) translateX(2px);
        background: #f0f4ff;
    }

    .upcoming-appt-item .stretched-link {
        z-index: 2;
    }

    .card-header {
        background: linear-gradient(90deg, #fff 80%, #f0f4ff 100%);
        border-radius: 1rem 1rem 0 0;
        font-weight: 600;
        font-size: 1.08rem;
        letter-spacing: 0.2px;
        border-bottom: 1px solid #e5e5e5;
    }

    .card {
        border-radius: 1.25rem;
        overflow: hidden;
    }

    .btn {
        border-radius: 0.7rem;
        font-weight: 600;
        letter-spacing: 0.2px;
        box-shadow: 0 2px 8px 0 rgba(13, 110, 253, 0.04);
        transition: box-shadow 0.18s, transform 0.18s;
    }

    .btn:hover,
    .btn:focus {
        box-shadow: 0 4px 16px 0 rgba(13, 110, 253, 0.10);
        transform: scale(1.03);
    }

    /* Responsive Adjustments */
    @media (max-width: 767.98px) {
        #appointmentCalendar {
            height: 500px;
            /* Shorter height on mobile */
        }

        .metric-card .card-body h3 {
            font-size: 1.75rem;
            /* Slightly smaller number on mobile */
        }

        .metric-card {
            border-radius: 1rem;
        }

        .card {
            border-radius: 1rem;
        }

        .card-header {
            border-radius: 1rem 1rem 0 0;
        }
    }
</style>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Admin Dashboard</h2>
    <span class="text-muted"><?php echo date("l, F j, Y"); ?></span> <!-- Display current date -->
</div>


<!-- Dashboard Metrics Row 1 -->
<div class="row mb-4">
    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-primary text-white me-3">
                    <i class="fas fa-user-md fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Total Doctors</div>
                    <div class="metric-value"><?php echo $total_doctors; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-info text-white me-3">
                    <i class="fas fa-user-tie fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Total Receptionists</div>
                    <div class="metric-value"><?php echo $total_receptionists; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-success text-white me-3">
                    <i class="fas fa-users fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Total Patients</div>
                    <div class="metric-value"><?php echo $total_patients; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-sm-6 col-lg-3 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-warning text-dark me-3">
                    <i class="fas fa-calendar-check fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Total Appointments</div>
                    <div class="metric-value"><?php echo $total_appointments; ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Dashboard Metrics Row 2: Appointments & Revenue -->
<div class="row mb-4">
    <div class="col-12 col-md-4 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-secondary text-white me-3">
                    <i class="fas fa-calendar-day fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Appointments Today</div>
                    <div class="metric-value"><?php echo $appointments_today; ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-primary-soft text-primary me-3"> <!-- Custom class needed -->
                    <i class="fas fa-dollar-sign fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Revenue Today</div>
                    <div class="metric-value">$<?php echo number_format($revenue_today, 2); ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4 mb-3">
        <div class="card metric-card shadow-sm h-100">
            <div class="card-body d-flex align-items-center">
                <div class="icon-circle bg-success-soft text-success me-3"> <!-- Custom class needed -->
                    <i class="fas fa-chart-line fa-lg"></i>
                </div>
                <div>
                    <div class="metric-label text-muted">Revenue This Month</div>
                    <div class="metric-value">$<?php echo number_format($revenue_month, 2); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Main Content Row: Upcoming Appointments, Chart, Quick Actions -->
<div class="row">
    <!-- Upcoming Appointments -->
    <div class="col-12 col-lg-6 col-xl-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock me-2"></i>Upcoming Appointments</span>
                <a href="manage_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($upcoming_appointments)): ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcoming_appointments as $appt): ?>
                            <li class="list-group-item upcoming-appt-item">
                                <div class="d-flex w-100 justify-content-between">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($appt['patient_name']); ?></h6>
                                    <small
                                        class="text-muted"><?php echo date('D, M j', strtotime($appt['appointment_date'])); ?></small>
                                </div>
                                <p class="mb-1 small">
                                    <i class="fas fa-user-md text-muted me-1"></i> Dr.
                                    <?php echo htmlspecialchars($appt['doctor_name']); ?> |
                                    <i class="fas fa-briefcase-medical text-muted ms-2 me-1"></i>
                                    <?php echo htmlspecialchars($appt['service_name']); ?>
                                </p>
                                <small
                                    class="text-primary fw-bold"><?php echo date('h:i A', strtotime($appt['appointment_date'])); ?></small>
                                <a href="view_appointment.php?id=<?php echo $appt['id']; ?>" class="stretched-link"
                                    aria-label="View appointment details"></a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-center text-muted p-3">No upcoming appointments.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chart -->
    <div class="col-12 col-md-6 col-lg-3 col-xl-3 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="fas fa-chart-pie me-2"></i> Appointment Status
            </div>
            <div class="card-body d-flex align-items-center justify-content-center p-2">
                <div id="chartContainer">
                    <canvas id="appointmentChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="col-12 col-md-6 col-lg-3 col-xl-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i> Quick Actions
            </div>
            <div class="card-body d-flex flex-column justify-content-around">
                <a href="add_appointment.php" class="btn btn-primary mb-2"><i class="fas fa-calendar-plus me-2"></i> Add
                    Appointment</a>
                <a href="manage_users.php#addUserCard" class="btn btn-info text-white mb-2"><i
                        class="fas fa-user-plus me-2"></i> Add User</a> <!-- Link to specific part if possible -->
                <a href="manage_services.php" class="btn btn-secondary mb-2"><i class="fas fa-cogs me-2"></i> Manage
                    Services</a>
                <a href="client_history.php" class="btn btn-success"><i class="fas fa-search me-2"></i> Find Client</a>
            </div>
        </div>
    </div>
</div>

<!-- Page-Specific JS (Load after global JS from footer) -->
<!-- REMOVE FullCalendar JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<!-- REMOVE Tippy.js unless used elsewhere -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Appointment Status Chart Initialization (Keep this)
        const ctxPie = document.getElementById('appointmentChart');
        if (ctxPie) {
            new Chart(ctxPie.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Completed', 'Canceled'],
                    datasets: [{
                        label: 'Appointments',
                        data: [
                            <?php echo $pending_appointments_count; ?>,
                            <?php echo $completed_appointments_count; ?>,
                            <?php echo $canceled_appointments_count; ?>
                        ],
                        backgroundColor: [
                            'rgba(255, 193, 7, 0.8)',
                            'rgba(25, 135, 84, 0.8)',
                            'rgba(220, 53, 69, 0.8)'
                        ],
                        borderColor: ['#fff', '#fff', '#fff'],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { padding: 10 } // Reduced padding
                        },
                        tooltip: { /* ... tooltip config ... */ }
                    }
                }
            });
        }

        // REMOVE Calendar Initialization code
    });
</script>

<?php
include '../includes/footer.php'; // Include the footer
?>