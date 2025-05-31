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

// --- Handle AJAX Status Updates ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    header('Content-Type: application/json');
    
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'] ?? null;
    $doctor_id = $_SESSION['user_id'];
    
    $response = ['success' => false, 'message' => ''];
    
    if (!$appointment_id || !$new_status || !in_array($new_status, ['completed', 'canceled'])) {
        $response['message'] = 'Invalid data provided.';
    } else {
        try {
            // First check the appointment date and ownership
            $check_query = "SELECT appointment_date FROM appointments 
                           WHERE id = :appointment_id AND doctor_id = :doctor_id";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
            $check_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
            $check_stmt->execute();
            $appointment_data = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment_data) {
                $response['message'] = 'Appointment not found or access denied.';
            } else {
                // Check if trying to mark future appointment as completed
                $appointment_date = new DateTime($appointment_data['appointment_date']);
                $current_date = new DateTime();
                
                if ($new_status === 'completed' && $appointment_date > $current_date) {
                    $response['message'] = 'Cannot mark future appointments as completed. Appointment is scheduled for ' . $appointment_date->format('M j, Y \a\t g:i A') . '.';
                } else {
                    // Update the status
                    $update_query = "UPDATE appointments SET status = :status 
                                     WHERE id = :appointment_id AND doctor_id = :doctor_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
                    $update_stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
                    $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                    $update_stmt->execute();
                    
                    if ($update_stmt->rowCount() > 0) {
                        $response['success'] = true;
                        $response['message'] = "Appointment #{$appointment_id} marked as {$new_status}.";
                    } else {
                        $response['message'] = 'Failed to update appointment status.';
                    }
                }
            }
        } catch (PDOException $e) {
            $response['message'] = 'Database error occurred.';
        }
    }
    
    echo json_encode($response);
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
                                    <tr id="today-row-<?php echo $appt['appointment_id']; ?>">
                                        <td><?php echo htmlspecialchars(date('h:i A', strtotime($appt['appointment_date']))); ?></td>
                                        <td><?php echo htmlspecialchars($appt['patient_name']); ?></td>
                                        <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                        <td class="text-center">
                                            <a href="view_appointment.php?id=<?php echo $appt['appointment_id']; ?>"
                                                class="btn btn-xs btn-outline-primary px-1 py-0" data-bs-toggle="tooltip"
                                                title="View Details"><i class="fas fa-eye"></i></a>
                                            <button class="btn btn-xs btn-outline-success px-1 py-0 ms-1"
                                                data-bs-toggle="tooltip" title="Mark Completed"
                                                onclick="markComplete(<?php echo $appt['appointment_id']; ?>)"><i
                                                    class="fas fa-check"></i></button>
                                            <button class="btn btn-xs btn-outline-danger px-1 py-0 ms-1"
                                                data-bs-toggle="tooltip" title="Cancel"
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
                            <?php
                            // Check if appointment is in the future
                            $appointment_date = new DateTime($appointment['appointment_date']);
                            $current_date = new DateTime();
                            $is_future = $appointment_date > $current_date;
                            ?>
                            <tr id="upcoming-row-<?php echo $appointment['appointment_id']; ?>">
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['contact_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(date('D, d M Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                                    <?php if ($is_future): ?>
                                        <span class="badge bg-info ms-1" title="Future appointment">Future</span>
                                    <?php endif; ?>
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
                                    <!-- Disable "Mark Completed" button for future appointments -->
                                    <button class="btn btn-sm btn-outline-success ms-1" 
                                        data-bs-toggle="tooltip"
                                        title="<?php echo $is_future ? 'Cannot mark future appointments as completed' : 'Mark Completed'; ?>"
                                        onclick="markComplete(<?php echo $appointment['appointment_id']; ?>)"
                                        <?php echo $is_future ? 'disabled' : ''; ?>>
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger ms-1" data-bs-toggle="tooltip"
                                        title="Cancel"
                                        onclick="cancelAppt(<?php echo $appointment['appointment_id']; ?>)"><i
                                            class="fas fa-times"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <!-- This message displays when there are no upcoming appointments to show -->
            <!-- text-center centers the text, text-muted makes it gray, p-4 adds padding, mb-0 removes bottom margin -->
            <p class="text-center text-muted p-4 mb-0">No further upcoming appointments found.</p>
        <?php endif; ?>
    </div>
    <!-- The card-footer section appears at the bottom of the card -->
    <!-- text-center aligns content to center, bg-light gives it a light gray background -->
    <div class="card-footer text-center bg-light">
        <!-- This link button takes the doctor to a page showing all their appointments -->
        <!-- btn makes it a button, btn-sm makes it smaller, btn-outline-secondary gives it a gray outline style -->
        <a href="manage_appointments.php" class="btn btn-sm btn-outline-secondary">
            <!-- Font Awesome calendar icon with right margin (me-1) -->
            <i class="fas fa-calendar-alt me-1"></i> View All My Appointments
        </a>
    </div>
</div>

<!-- Alert for future appointments info -->
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    <strong>Note:</strong> Future appointments cannot be marked as "Completed" until the appointment date has passed. 
    You can still cancel them if needed.
</div>

<!-- Page-Specific CSS (Load after global CSS from header) -->
<style>
    /* Dashboard-specific styles to match admin dashboard */
    .metric-card {
        transition: transform 0.2s, box-shadow 0.2s;
        border: none;
        border-radius: 0.75rem;
    }

    .metric-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 25px 0 rgba(0, 0, 0, 0.1);
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2c3e50;
    }

    .metric-label {
        font-size: 0.875rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .icon-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .bg-primary-soft {
        background-color: rgba(13, 110, 253, 0.1);
    }

    .bg-success-soft {
        background-color: rgba(25, 135, 84, 0.1);
    }

    .upcoming-appt-item {
        position: relative;
        transition: background-color 0.2s;
    }

    .upcoming-appt-item:hover {
        background-color: #f8f9fa;
    }

    .timeline {
        position: relative;
        padding-left: 2rem;
    }

    .timeline-item {
        position: relative;
        margin-bottom: 1.5rem;
        padding-left: 1.5rem;
    }

    .timeline-marker {
        position: absolute;
        left: -1.75rem;
        top: 0.25rem;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 0 0 2px #dee2e6;
    }

    .timeline::before {
        content: '';
        position: absolute;
        left: -1.25rem;
        top: 0;
        bottom: 0;
        width: 2px;
        background: #dee2e6;
    }

    .timeline-content h6 {
        margin-bottom: 0.25rem;
        font-weight: 600;
    }

    .timeline-content p {
        margin-bottom: 0.25rem;
        color: #6c757d;
    }

    .btn-xs {
        padding: 0.125rem 0.25rem;
        font-size: 0.75rem;
        line-height: 1.2;
        border-radius: 0.25rem;
    }

    #chartContainer {
        position: relative;
        height: 300px;
        width: 100%;
    }

    #appointmentChart, #appointmentStatusChart {
        max-width: 100%;
        max-height: 100%;
    }
</style>

<!-- Chart.js Initialization and Action Functions -->
<script>
    // The DOMContentLoaded event ensures this code runs only after the HTML page is fully loaded
    document.addEventListener('DOMContentLoaded', function () {
        // Initialize Bootstrap Tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Chart.js Initialization
        const ctx = document.getElementById('appointmentStatusChart');
        if (ctx) {
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
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)',
                        'rgba(220, 53, 69, 0.7)'
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
                type: 'doughnut',
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

    // Function to update appointment status via AJAX
    function updateAppointmentStatus(appointmentId, newStatus, confirmMessage) {
        console.log('updateAppointmentStatus called with:', { appointmentId, newStatus, confirmMessage });
        
        if (confirm(confirmMessage)) {
            console.log('User confirmed the action');
            
            // Show loading state
            showAlert('info', 'Updating appointment status...');
            
            fetch('index.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `ajax_update=1&appointment_id=${appointmentId}&new_status=${newStatus}`
            })
            .then(response => {
                console.log('Response received:', response);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Data received:', data);
                if (data.success) {
                    showAlert('success', data.message);
                    
                    // Remove the row from both tables
                    const todayRow = document.getElementById(`today-row-${appointmentId}`);
                    const upcomingRow = document.getElementById(`upcoming-row-${appointmentId}`);
                    
                    if (todayRow) {
                        todayRow.style.transition = 'opacity 0.3s';
                        todayRow.style.opacity = '0.5';
                        setTimeout(() => todayRow.remove(), 300);
                    }
                    if (upcomingRow) {
                        upcomingRow.style.transition = 'opacity 0.3s';
                        upcomingRow.style.opacity = '0.5';
                        setTimeout(() => upcomingRow.remove(), 300);
                    }
                    
                    // Reload the page after a delay to refresh statistics
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('danger', data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('danger', 'An error occurred while updating the appointment: ' + error.message);
            });
        } else {
            console.log('User cancelled the action');
        }
    }

    // Mark appointment as completed
    function markComplete(appointmentId) {
        console.log('markComplete called with appointmentId:', appointmentId);
        updateAppointmentStatus(
            appointmentId, 
            'completed', 
            'Are you sure you want to mark this appointment as completed?'
        );
    }

    // Cancel appointment
    function cancelAppt(appointmentId) {
        console.log('cancelAppt called with appointmentId:', appointmentId);
        updateAppointmentStatus(
            appointmentId, 
            'canceled', 
            'Are you sure you want to cancel this appointment?'
        );
    }

    // Function to show alert messages
    function showAlert(type, message) {
        // Remove any existing alerts first
        const existingAlerts = document.querySelectorAll('.alert:not(.alert-info):not([class*="mb-4"])');
        existingAlerts.forEach(alert => {
            if (!alert.classList.contains('mb-4')) {
                alert.remove();
            }
        });

        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;
        
        // Insert after the page title
        const pageTitle = document.querySelector('h2').parentElement;
        pageTitle.insertAdjacentElement('afterend', alertDiv);
        
        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentElement) {
                alertDiv.remove();
            }
        }, 5000);
    }
</script>

<?php
include '../includes/footer.php'; // Include the shared footer
?>