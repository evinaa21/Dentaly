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
    // Combined query for better performance
    $dashboard_query = "
        SELECT 
            (SELECT COUNT(*) FROM users WHERE role = 'doctor') as total_doctors,
            (SELECT COUNT(*) FROM users WHERE role = 'receptionist') as total_receptionists,
            (SELECT COUNT(*) FROM patients) as total_patients,
            (SELECT COUNT(*) FROM appointments) as total_appointments,
            (SELECT COUNT(*) FROM appointments WHERE status = 'pending') as pending_count,
            (SELECT COUNT(*) FROM appointments WHERE status = 'completed') as completed_count,
            (SELECT COUNT(*) FROM appointments WHERE status = 'canceled') as canceled_count,
            (SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()) as appointments_today
    ";
    $result = $conn->query($dashboard_query)->fetch(PDO::FETCH_ASSOC);
    extract($result); // Extract variables from the result array

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
<style>
    /* Only keep unique dashboard-specific styles that aren't in main CSS */
    #chartContainer {
        position: relative;
        height: 300px;
        width: 100%;
    }

    #appointmentChart {
        max-width: 100%;
        max-height: 100%;
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

<!-- Recent Activity Widget -->
<div class="col-12 mb-4">
    <div class="card shadow-sm">
        <div class="card-header">
            <i class="fas fa-history me-2"></i> Recent Activity
        </div>
        <div class="card-body">
            <div class="timeline">
                <?php
                $recent_activity = $conn->query("
                    SELECT 
                        'appointment' as type,
                        CONCAT(p.first_name, ' ', p.last_name) as patient_name,
                        a.created_at,
                        a.appointment_date,
                        u.name as doctor_name
                    FROM appointments a
                    JOIN patients p ON a.patient_id = p.id
                    JOIN users u ON a.doctor_id = u.id
                    ORDER BY a.created_at DESC
                    LIMIT 5
                ")->fetchAll(PDO::FETCH_ASSOC);

                foreach ($recent_activity as $activity): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">New appointment booked</h6>
                            <p class="mb-1"><?php echo htmlspecialchars($activity['patient_name']); ?> with Dr.
                                <?php echo htmlspecialchars($activity['doctor_name']); ?>
                            </p>
                            <small
                                class="text-muted"><?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Page-Specific JS (Load after global JS from footer) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Appointment Status Chart Initialization
        const ctxPie = document.getElementById('appointmentChart');
        if (ctxPie) {
            new Chart(ctxPie.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Completed', 'Canceled'],
                    datasets: [{
                        label: 'Appointments',
                        data: [
                            <?php echo $pending_count; ?>,
                            <?php echo $completed_count; ?>,
                            <?php echo $canceled_count; ?>
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
                            labels: { padding: 10 }
                        }
                    }
                }
            });
        }
    });
</script>

<?php
include '../includes/footer.php'; // Include the footer
?>