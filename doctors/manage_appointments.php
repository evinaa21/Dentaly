<?php
// Start session right away for flash messages
// session_start(); // Already started in header.php

include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Manage Appointments';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a doctor
if ($_SESSION['role'] !== 'doctor') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// Fetch the logged-in doctor's ID from the session
$doctor_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// --- Handle Status Update POST Request ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id_to_update = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $new_status = $_POST['new_status'] ?? null;
    $valid_statuses_update = ['pending', 'completed', 'canceled']; // Allowed statuses for update

    if (!$appointment_id_to_update || !$new_status || !in_array($new_status, $valid_statuses_update)) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid data received for status update.'];
    } else {
        try {
            // Ensure the appointment belongs to the logged-in doctor before updating
            $update_query = "UPDATE appointments SET status = :status
                             WHERE id = :appointment_id AND doctor_id = :doctor_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
            $update_stmt->bindParam(':appointment_id', $appointment_id_to_update, PDO::PARAM_INT);
            $update_stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT); // Security check!
            $update_stmt->execute();

            if ($update_stmt->rowCount() > 0) {
                $_SESSION['flash_message'] = ['type' => 'success', 'text' => "Appointment #{$appointment_id_to_update} status updated to '{$new_status}'.'"];
            } else {
                $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Appointment not found, permission denied, or status already set.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error during update.'];
            // error_log("Manage Appointments POST Update Error: " . $e->getMessage());
        }
    }
    // Redirect back to the same page (with filter if possible) to prevent re-submission
    $redirect_url = "manage_appointments.php";
    if (isset($_GET['status'])) {
        $redirect_url .= "?status=" . urlencode($_GET['status']); // Keep filter
    }
    header("Location: " . $redirect_url);
    exit;
}
// --- End Handle Status Update ---


// --- Filtering ---
$filter_status = $_GET['status'] ?? 'all'; // Default to 'all'
$valid_statuses = ['all', 'pending', 'completed', 'canceled'];
if (!in_array($filter_status, $valid_statuses)) {
    $filter_status = 'all'; // Reset if invalid status provided
}

// --- Fetch Appointments ---
$appointments = [];
try {
    $query = "
        SELECT
            a.id AS appointment_id,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.contact_number,
            s.service_name,
            a.appointment_date,
            a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN services s ON a.service_id = s.id
        WHERE a.doctor_id = :doctor_id";

    // Add status filter if not 'all'
    if ($filter_status !== 'all') {
        $query .= " AND a.status = :status";
    }

    $query .= " ORDER BY a.appointment_date DESC"; // Show most recent first

    $stmt = $conn->prepare($query);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    if ($filter_status !== 'all') {
        $stmt->bindParam(':status', $filter_status, PDO::PARAM_STR);
    }
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = "Error fetching appointments: " . htmlspecialchars($e->getMessage());
    // error_log("Manage Appointments DB Error: " . $e->getMessage());
}

// --- Display Flash Message ---
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear message after displaying
}

?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-calendar-alt me-2 text-primary"></i> Manage Appointments</h2>
    <!-- Optional: Add button like "Schedule New" -->
</div>

<!-- Display Messages -->
<?php if (!empty($flash_message)): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
        role="alert">
        <?php echo htmlspecialchars($flash_message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>
<!-- Removed AJAX message div -->


<!-- Filtering Controls -->
<div class="mb-3 d-flex justify-content-start align-items-center">
    <span class="me-2 fw-bold">Filter by Status:</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="Filter by status">
        <a href="manage_appointments.php?status=all"
            class="btn <?php echo $filter_status === 'all' ? 'btn-primary' : 'btn-outline-secondary'; ?>">All</a>
        <a href="manage_appointments.php?status=pending"
            class="btn <?php echo $filter_status === 'pending' ? 'btn-warning' : 'btn-outline-secondary'; ?>">Pending</a>
        <a href="manage_appointments.php?status=completed"
            class="btn <?php echo $filter_status === 'completed' ? 'btn-success' : 'btn-outline-secondary'; ?>">Completed</a>
        <a href="manage_appointments.php?status=canceled"
            class="btn <?php echo $filter_status === 'canceled' ? 'btn-danger' : 'btn-outline-secondary'; ?>">Canceled</a>
    </div>
</div>


<!-- Appointments Table Card -->
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Appointment List (<?php echo ucfirst($filter_status); ?>)</h5>
        <span class="badge bg-secondary rounded-pill"><?php echo count($appointments); ?> appointments found</span>
    </div>
    <div class="card-body p-0"> <!-- Remove padding for table flush -->
        <?php if (!empty($appointments)): ?>
            <div class="table-responsive">
                <table class="table table-hover table-striped align-middle mb-0">
                    <!-- Added table-hover, align-middle, mb-0 -->
                    <thead class="table-light">
                        <tr>
                            <th>Patient Name</th>
                            <th>Contact</th>
                            <th>Service</th>
                            <th>Date & Time</th>
                            <th class="text-center">Status</th>
                            <th class="text-center" style="min-width: 220px;">Actions</th> <!-- Wider actions column -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr> <!-- Removed ID from TR -->
                                <td><?php echo htmlspecialchars($appointment['patient_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['contact_number'] ?: 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('D, d M Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                                </td>
                                <td class="text-center">
                                    <?php
                                    $status_class = 'bg-secondary'; // Default
                                    if ($appointment['status'] == 'pending')
                                        $status_class = 'bg-warning text-dark';
                                    elseif ($appointment['status'] == 'completed')
                                        $status_class = 'bg-success';
                                    elseif ($appointment['status'] == 'canceled')
                                        $status_class = 'bg-danger';
                                    ?>
                                    <span class="badge <?php echo $status_class; ?>"> <!-- Removed ID from badge -->
                                        <?php echo ucfirst($appointment['status']); ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <!-- View Details Link -->
                                    <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                        class="btn btn-sm btn-outline-primary me-1" data-bs-toggle="tooltip"
                                        title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>

                                    <!-- Update Status Form -->
                                    <form method="POST"
                                        action="manage_appointments.php<?php echo isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''; ?>"
                                        class="d-inline-block">
                                        <input type="hidden" name="appointment_id"
                                            value="<?php echo $appointment['appointment_id']; ?>">
                                        <input type="hidden" name="update_status" value="1">
                                        <!-- Identifier for the POST handler -->

                                        <select name="new_status" class="form-select form-select-sm d-inline-block w-auto"
                                            aria-label="Update status"> <!-- Added name attribute -->
                                            <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="canceled" <?php echo $appointment['status'] === 'canceled' ? 'selected' : ''; ?>>Canceled</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-primary ms-1" data-bs-toggle="tooltip"
                                            title="Update Status"> <!-- Changed to type="submit" -->
                                            <i class="fas fa-save"></i> <!-- Save icon -->
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center text-muted p-4 mb-0">No appointments found matching the current filter.</p>
        <?php endif; ?>
    </div>
</div>

<!-- REMOVED JavaScript for AJAX Status Update -->
<script>
    // Initialize Bootstrap Tooltips when DOM is ready (Keep this part)
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    });
</script>


<?php
include '../includes/footer.php'; // Include the shared footer
?>