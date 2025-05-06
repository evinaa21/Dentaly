<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Doctor Dashboard';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a doctor
if ($_SESSION['role'] !== 'doctor') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// Fetch the logged-in doctor's ID from the session
$doctor_id = $_SESSION['user_id'];
$doctor_name = $_SESSION['user_name'] ?? 'Doctor'; // Get name from session if available

// --- Fetch Data ---
$appointments_upcoming = [];
$appointments_today = [];
$stats = ['completed' => 0, 'pending' => 0, 'canceled' => 0];
$error_message = ''; // To collect errors

try {
    // Fetch PENDING appointments for "Upcoming" (excluding today)
    $query_upcoming = "
        SELECT a.id AS appointment_id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.contact_number, s.service_name, a.appointment_date, a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN services s ON a.service_id = s.id
        WHERE a.doctor_id = :doctor_id AND a.status = 'pending' AND DATE(a.appointment_date) > CURDATE()
        ORDER BY a.appointment_date ASC
        LIMIT 10";
    $stmt_upcoming = $conn->prepare($query_upcoming);
    $stmt_upcoming->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt_upcoming->execute();
    $appointments_upcoming = $stmt_upcoming->fetchAll(PDO::FETCH_ASSOC);

    // Fetch PENDING appointments for "Today"
    $query_today = "
        SELECT a.id AS appointment_id, CONCAT(p.first_name, ' ', p.last_name) AS patient_name, p.contact_number, s.service_name, a.appointment_date, a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN services s ON a.service_id = s.id
        WHERE a.doctor_id = :doctor_id_today AND a.status = 'pending' AND DATE(a.appointment_date) = CURDATE()
        ORDER BY a.appointment_date ASC";
    $stmt_today = $conn->prepare($query_today);
    $stmt_today->bindParam(':doctor_id_today', $doctor_id, PDO::PARAM_INT); // Use different placeholder name
    $stmt_today->execute();
    $appointments_today = $stmt_today->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Appointment Statistics
    $query_stats = "
        SELECT status, COUNT(*) AS count
        FROM appointments
        WHERE doctor_id = :doctor_id_stats
        GROUP BY status";
    $stmt_stats = $conn->prepare($query_stats);
    $stmt_stats->bindParam(':doctor_id_stats', $doctor_id, PDO::PARAM_INT); // Use different placeholder name
    $stmt_stats->execute();
    $results = $stmt_stats->fetchAll(PDO::FETCH_ASSOC);
    foreach ($results as $row) {
        if (isset($stats[$row['status']])) {
            $stats[$row['status']] = $row['count'];
        }
    }

} catch (PDOException $e) {
    $error_message = 'Error fetching dashboard data: ' . htmlspecialchars($e->getMessage());
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Welcome, <?php echo htmlspecialchars($doctor_name); ?>!</h2>
    <!-- Optional: Add a quick action button -->
    <!-- <a href="patient_records.php" class="btn btn-outline-primary"><i class="fas fa-notes-medical me-2"></i>View Patient Records</a> -->
</div>

<!-- Display Error Message if any -->
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>

<!-- Dashboard Row: Stats Chart and Today's Appointments -->
<div class="row mb-4">
    <!-- Statistics Chart Column -->
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2 text-info"></i> Appointment Status Overview</h5>
            </div>
            <div class="card-body d-flex justify-content-center align-items-center">
                <?php if (array_sum($stats) > 0): ?>
                    <div style="max-width: 300px; width: 100%;"> <!-- Constrain chart size -->
                        <canvas id="appointmentStatusChart"></canvas>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">No appointment data available for chart.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Today's Appointments Column -->
    <div class="col-lg-7 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-calendar-day me-2 text-success"></i> Today's Appointments</h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($appointments_today)): ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($appointments_today as $appt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(date('h:i A', strtotime($appt['appointment_date']))); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                        <td class="text-center">
                                            <a href="view_appointment.php?id=<?php echo $appt['appointment_id']; ?>"
                                                class="btn btn-xs btn-outline-primary px-1 py-0" data-bs-toggle="tooltip"
                                                title="View Details"><i class="fas fa-eye"></i></a>
                                            <button class="btn btn-xs btn-outline-success px-1 py-0 ms-1"
                                                data-bs-toggle="tooltip" title="Mark Completed (AJAX)"
                                                onclick="markComplete(<?php echo $appt['appointment_id']; ?>)"><i
                                                    class="fas fa-check"></i></button>
                                            <button class="btn btn-xs btn-outline-danger px-1 py-0 ms-1"
                                                data-bs-toggle="tooltip" title="Cancel (AJAX)"
                                                onclick="cancelAppt(<?php echo $appt['appointment_id']; ?>)"><i
                                                    class="fas fa-times"></i></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-center text-muted p-4 mb-0">No appointments scheduled for today.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<!-- Upcoming Appointments Card (Excluding Today) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-calendar-alt me-2 text-primary"></i> Upcoming Appointments (Next 10)</h5>
    </div>
    <div class="card-body p-0">
        <?php if (!empty($appointments_upcoming)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Patient Name</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments_upcoming as $appointment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['contact_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('D, d M Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $status_badge = 'bg-secondary'; // Default
                                    if ($appointment['status'] == 'pending')
                                        $status_badge = 'bg-warning text-dark';
                                    // Add other statuses if needed (e.g., confirmed)
                                    ?>
                                    <span class="badge <?php echo $status_badge; ?>">
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                        class="btn btn-sm btn-outline-primary" data-bs-toggle="tooltip" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <!-- Placeholder for future quick actions -->
                                    <button class="btn btn-sm btn-outline-success ms-1" data-bs-toggle="tooltip"
                                        title="Mark Completed (AJAX)"
                                        onclick="markComplete(<?php echo $appointment['appointment_id']; ?>)"><i
                                            class="fas fa-check"></i></button>
                                    <button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="tooltip"
                                        title="Cancel (AJAX)"
                                        onclick="cancelAppt(<?php echo $appointment['appointment_id']; ?>)"><i
                                            class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-muted p-4 mb-0">No further upcoming appointments found.</p>
        <?php endif; ?>
    </div>
    <div class="card-footer text-center bg-light">
        <a href="manage_appointments.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-calendar-alt me-1"></i> View All My Appointments
        </a>
    </div>
</div>

<!-- Chart.js Initialization and Placeholder Action Functions -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Chart.js Initialization
        const ctx = document.getElementById('appointmentStatusChart');
        if (ctx) { // Check if canvas element exists
            const appointmentData = {
                labels: ['Completed', 'Pending', 'Canceled'],
                datasets: [{
                    label: 'Appointments',
                    data: [
                        <?php echo $stats['completed']; ?>,
                        <?php echo $stats['pending']; ?>,
                        <?php echo $stats['canceled']; ?>
                    ],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.7)',  // Success (Green)
                        'rgba(255, 193, 7, 0.7)',   // Warning (Yellow)
                        'rgba(220, 53, 69, 0.7)'   // Danger (Red)
                    ],
                    borderColor: [
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)',
                        'rgba(220, 53, 69, 1)'
                    ],
                    borderWidth: 1
                }]
            };

            new Chart(ctx, {
                type: 'doughnut', // Or 'pie'
                data: appointmentData,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    let label = context.label || '';
                                    if (label) {
                                        label += ': ';
                                    }
                                    if (context.parsed !== null) {
                                        label += context.parsed;
                                    }
                                    return label;
                                }
                            }
                        }
                    }
                }
            });
        }
    });

    // Placeholder functions for quick actions (implement AJAX calls here)
    function markComplete(appointmentId) {
        console.log("Marking complete:", appointmentId);
        if (confirm('Are you sure you want to mark this appointment as completed?')) {
            // TODO: Add AJAX call to update status to 'completed'
            alert('Placeholder: Appointment ' + appointmentId + ' marked as completed. Implement AJAX.');
            // Optionally reload part of the page or remove the row
        }
    }

    function cancelAppt(appointmentId) {
        console.log("Canceling:", appointmentId);
        if (confirm('Are you sure you want to cancel this appointment?')) {
            // TODO: Add AJAX call to update status to 'canceled'
            alert('Placeholder: Appointment ' + appointmentId + ' canceled. Implement AJAX.');
            // Optionally reload part of the page or remove the row
        }
    }
</script>


<?php
include '../includes/footer.php'; // Include the shared footer
?>