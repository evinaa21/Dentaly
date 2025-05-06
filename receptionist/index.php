<?php
// Define page title *before* including header
$page_title = 'Receptionist Dashboard';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// Initialize variables
$total_patients = 0;
$total_appointments = 0;
$pending_appointments = 0;
$completed_appointments = 0;
$current_week_appointments = 0;
$last_week_appointments = 0;
$popular_service = null;
$top_patient = null;
$appointments_last_7_days = [];
$error_message = '';

// Fetch dashboard metrics
try {
    $total_patients = $conn->query("SELECT COUNT(*) AS count FROM patients")->fetchColumn();
    $total_appointments = $conn->query("SELECT COUNT(*) AS count FROM appointments")->fetchColumn();
    $pending_appointments = $conn->query("SELECT COUNT(*) AS count FROM appointments WHERE status = 'pending'")->fetchColumn();
    $completed_appointments = $conn->query("SELECT COUNT(*) AS count FROM appointments WHERE status = 'completed'")->fetchColumn();

    // Weekly appointment statistics
    $current_week_appointments = $conn->query("
        SELECT COUNT(*) AS count
        FROM appointments
        WHERE YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1)
    ")->fetchColumn();

    $last_week_appointments = $conn->query("
        SELECT COUNT(*) AS count
        FROM appointments
        WHERE YEARWEEK(appointment_date, 1) = YEARWEEK(CURDATE(), 1) - 1
    ")->fetchColumn();

    // Most Popular Service
    $popular_service = $conn->query("
        SELECT s.service_name, COUNT(a.id) AS count
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        GROUP BY s.id, s.service_name  -- Group by ID and name
        ORDER BY count DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Top Patient
    $top_patient = $conn->query("
        SELECT p.id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, COUNT(a.id) AS count
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        GROUP BY p.id, patient_name -- Group by ID and name
        ORDER BY count DESC
        LIMIT 1
    ")->fetch(PDO::FETCH_ASSOC);

    // Data for Chart: Appointments in the last 7 days
    $stmt_chart = $conn->query("
        SELECT DATE(appointment_date) as app_date, COUNT(*) as count
        FROM appointments
        WHERE appointment_date >= CURDATE() - INTERVAL 6 DAY AND appointment_date < CURDATE() + INTERVAL 1 DAY
        GROUP BY app_date
        ORDER BY app_date ASC
    ");
    $appointments_last_7_days = $stmt_chart->fetchAll(PDO::FETCH_ASSOC);


} catch (PDOException $e) {
    $error_message = "Error fetching dashboard data: " . $e->getMessage();
    // error_log("Receptionist Dashboard Error: " . $e->getMessage());
}

// Calculate weekly change
$week_change = $current_week_appointments - $last_week_appointments;
$week_change_percentage = $last_week_appointments > 0
    ? round(($week_change / $last_week_appointments) * 100, 1) // Use 1 decimal place
    : ($current_week_appointments > 0 ? 100 : 0); // Avoid division by zero

// Determine color and icon for weekly change
$week_change_color = 'text-muted';
$week_change_icon = 'fa-minus';
if ($week_change > 0) {
    $week_change_color = 'text-success';
    $week_change_icon = 'fa-arrow-up';
} elseif ($week_change < 0) {
    $week_change_color = 'text-danger';
    $week_change_icon = 'fa-arrow-down';
}

// Prepare data for Chart.js
$chart_labels = [];
$chart_data = [];
$date_map = []; // Map dates to counts

// Initialize map with 0 counts for the last 7 days
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chart_labels[] = date('D, M j', strtotime($date)); // Format label e.g., "Mon, Apr 22"
    $date_map[$date] = 0;
}

// Fill map with actual counts
foreach ($appointments_last_7_days as $row) {
    if (isset($date_map[$row['app_date']])) {
        $date_map[$row['app_date']] = (int) $row['count'];
    }
}
$chart_data = array_values($date_map); // Get counts in the correct order

?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-concierge-bell me-2 text-primary"></i> Receptionist Dashboard</h2>
    <!-- Optional: Add button like "Schedule New" -->
</div>

<!-- Display Errors -->
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Quick Actions -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i> Quick Actions</h5>
            </div>
            <div class="card-body text-center d-flex flex-wrap justify-content-center">
                <!-- Changed link to receptionist's add appointment page -->
                <a href="add_appointment.php" class="btn btn-primary m-2"><i class="fas fa-calendar-plus me-1"></i> Book
                    Appointment</a>
                <!-- Link to manage patients (assuming this page exists or will be created) -->
                <a href="manage_patients.php" class="btn btn-info m-2"><i class="fas fa-users me-1"></i> Manage
                    Patients</a>
                <!-- Added link to add patient page -->
                <a href="add_patient.php" class="btn btn-success m-2"><i class="fas fa-user-plus me-1"></i> Add New
                    Patient</a>
                <!-- Link to view appointments (assuming this page exists or will be created) -->
                <a href="manage_appointments.php" class="btn btn-secondary m-2"><i class="fas fa-calendar-alt me-1"></i>
                    View Appointments</a>
                <!-- Added link to client history/search page -->
                <a href="client_history.php" class="btn btn-purple m-2"><i class="fas fa-search me-1"></i> Search
                    Patients</a>

            </div>
        </div>
    </div>
</div>


<!-- Dashboard Metrics -->
<div class="row mb-4">
    <div class="col-6 col-lg-3 mb-4">
        <div class="card shadow-sm h-100 text-center border-start border-primary border-4">
            <div class="card-body">
                <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Patients</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?php echo $total_patients; ?></div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <a href="manage_patients.php" class="text-primary small">View Details <i
                        class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-4">
        <div class="card shadow-sm h-100 text-center border-start border-info border-4">
            <div class="card-body">
                <div class="text-xs fw-bold text-info text-uppercase mb-1">Total Appointments</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?php echo $total_appointments; ?></div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <a href="manage_appointments.php" class="text-info small">View Details <i
                        class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-4">
        <div class="card shadow-sm h-100 text-center border-start border-warning border-4">
            <div class="card-body">
                <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Appointments</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?php echo $pending_appointments; ?></div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <a href="manage_appointments.php?status=pending" class="text-warning small">View Details <i
                        class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-4">
        <div class="card shadow-sm h-100 text-center border-start border-success border-4">
            <div class="card-body">
                <div class="text-xs fw-bold text-success text-uppercase mb-1">Completed Appointments</div>
                <div class="h3 mb-0 fw-bold text-gray-800"><?php echo $completed_appointments; ?></div>
            </div>
            <div class="card-footer bg-white border-0 pt-0">
                <a href="manage_appointments.php?status=completed" class="text-success small">View Details <i
                        class="fas fa-arrow-circle-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Weekly Statistics & Chart -->
<div class="row mb-4">
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light d-flex flex-row align-items-center justify-content-between">
                <h5 class="m-0 font-weight-bold text-primary"><i class="fas fa-chart-line me-2"></i> Appointments (Last
                    7 Days)</h5>
            </div>
            <div class="card-body">
                <div class="chart-area" style="height: 250px;"> <!-- Adjust height as needed -->
                    <canvas id="appointmentsChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-calendar-week me-2"></i> Weekly Summary</h5>
            </div>
            <div class="card-body d-flex flex-column justify-content-center text-center">
                <h6 class="text-muted">This Week's Appointments</h6>
                <div class="h2 fw-bold mb-3"><?php echo $current_week_appointments; ?></div>
                <hr class="w-50 mx-auto">
                <p class="mb-0">Compared to last week (<?php echo $last_week_appointments; ?>):</p>
                <p class="h5 fw-bold <?php echo $week_change_color; ?>">
                    <i class="fas <?php echo $week_change_icon; ?> me-1"></i>
                    <?php echo $week_change >= 0 ? '+' : ''; ?><?php echo $week_change; ?>
                    (<?php echo $week_change_percentage; ?>%)
                </p>
            </div>
        </div>
    </div>
</div>


<!-- Additional Insights -->
<div class="row">
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-star me-2 text-info"></i> Most Popular Service</h5>
            </div>
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <?php if ($popular_service): ?>
                    <h4 class="text-truncate"><?php echo htmlspecialchars($popular_service['service_name']); ?></h4>
                    <p class="h5 text-muted"><?php echo $popular_service['count']; ?> appointments</p>
                <?php else: ?>
                    <p class="text-muted">No appointment data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-user-check me-2 text-success"></i> Top Patient (By Appointments)</h5>
            </div>
            <div class="card-body text-center d-flex flex-column justify-content-center">
                <?php if ($top_patient): ?>
                    <h4 class="text-truncate"><?php echo htmlspecialchars($top_patient['patient_name']); ?></h4>
                    <p class="h5 text-muted"><?php echo $top_patient['count']; ?> appointments</p>
                    <a href="patient_details.php?patient_id=<?php echo $top_patient['id']; ?>" class="mt-2 small">View
                        History <i class="fas fa-external-link-alt"></i></a>
                <?php else: ?>
                    <p class="text-muted">No appointment data available.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Chart.js Initialization -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const ctx = document.getElementById('appointmentsChart');
        if (ctx) { // Check if the canvas element exists
            new Chart(ctx, {
                type: 'line', // or 'bar'
                data: {
                    labels: <?php echo json_encode($chart_labels); ?>,
                    datasets: [{
                        label: 'Appointments',
                        data: <?php echo json_encode($chart_data); ?>,
                        fill: true, // Fill area below line
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: 'rgba(75, 192, 192, 0.2)', // Semi-transparent fill
                        tension: 0.1 // Makes the line slightly curved
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // Allows chart to fill container height
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                // Ensure only whole numbers are shown on the y-axis
                                stepSize: 1,
                                callback: function (value) { if (value % 1 === 0) { return value; } }
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            display: false // Hide legend if only one dataset
                        }
                    }
                }
            });
        } else {
            console.error("Canvas element #appointmentsChart not found.");
        }
    });
</script>


<?php
include '../includes/footer.php'; // Include the shared footer
?>

<!-- Add custom style for purple button if needed (e.g., in your main CSS or <style> block) -->
<style>
    .btn-purple {
        color: #fff;
        background-color: #6f42c1;
        /* Bootstrap purple */
        border-color: #6f42c1;
    }

    .btn-purple:hover {
        color: #fff;
        background-color: #5a34a0;
        border-color: #533093;
    }
</style>