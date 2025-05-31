<?php


include '../config/db.php'; // Include database connection early if needed by POST handling

// Handle appointment deletion FIRST, before any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    try {
        $delete_query = "DELETE FROM appointments WHERE id = :id";
        $delete_stmt = $conn->prepare($delete_query);
        $delete_stmt->bindParam(':id', $delete_id, PDO::PARAM_INT);
        if ($delete_stmt->execute()) {
            $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Appointment deleted successfully!'];
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to delete the appointment.'];
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error deleting appointment.'];
        // error_log("Delete Appointment Error: " . $e->getMessage());
    }
    header("Location: manage_appointments.php"); // Redirect to refresh and show message
    exit; // IMPORTANT: Stop script execution after redirect
}

// Define page title *before* including header
$page_title = 'Manage Appointments';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist (session is already started by header.php or above)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$flash_message_display = null; // Use a different variable name to avoid confusion
if (isset($_SESSION['flash_message'])) {
    $flash_message_display = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear message after preparing it for display
}

// Filtering
$filter_status = $_GET['status'] ?? ''; // Get status from URL query parameter
$filter_doctor = $_GET['doctor_id'] ?? '';
$filter_date = $_GET['date'] ?? '';

// Fetch appointments with patient, doctor, and service details
$appointments = [];
$doctors = []; // For filter dropdown
try {
    // Fetch doctors for the filter dropdown
    $doctors_query = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name");
    if ($doctors_query) {
        $doctors = $doctors_query->fetchAll(PDO::FETCH_ASSOC);
    }


    $query = "
        SELECT
            a.id AS appointment_id,
            p.id AS patient_id,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            d.id AS doctor_id,
            d.name AS doctor_name,
            s.service_name,
            a.appointment_date,
            a.status
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        JOIN services s ON a.service_id = s.id
        WHERE 1=1 -- Start WHERE clause
    ";
    $params = [];

    // Add status filter if provided
    if (!empty($filter_status) && in_array($filter_status, ['pending', 'completed', 'canceled'])) {
        $query .= " AND a.status = :status";
        $params[':status'] = $filter_status;
    }
    // Add doctor filter if provided
    if (!empty($filter_doctor)) {
        $query .= " AND a.doctor_id = :doctor_id";
        $params[':doctor_id'] = (int) $filter_doctor; // Ensure it's an integer
    }
    // Add date filter if provided
    if (!empty($filter_date)) {
        // Validate date format
        $date_obj = DateTime::createFromFormat('Y-m-d', $filter_date);
        if ($date_obj && $date_obj->format('Y-m-d') === $filter_date) {
            $query .= " AND DATE(a.appointment_date) = :app_date";
            $params[':app_date'] = $filter_date;
        } else {
            // Invalid date, perhaps set a message or ignore
            if ($flash_message_display === null)
                $flash_message_display = ['type' => 'warning', 'text' => 'Invalid date format for filter.'];
        }
    }


    $query .= " ORDER BY a.appointment_date DESC"; // Show most recent first

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    if ($flash_message_display === null)
        $flash_message_display = ['type' => 'danger', 'text' => 'Error fetching data: ' . $e->getMessage()];
    // error_log("Manage Appointments Fetch Error: " . $e->getMessage());
}

// Function to get status badge class
function getStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'completed':
            return 'bg-success';
        case 'canceled':
            return 'bg-danger';
        case 'pending':
            return 'bg-warning text-dark'; // Dark text for better contrast on yellow
        default:
            return 'bg-secondary';
    }
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-calendar-alt me-2 text-primary"></i> Manage Appointments</h2>
    <a href="add_appointment.php" class="btn btn-primary"><i class="fas fa-plus me-1"></i> Add New Appointment</a>
</div>

<!-- Display Flash Message -->
<?php if ($flash_message_display): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message_display['type']); ?> alert-dismissible fade show"
        role="alert" data-auto-dismiss="7000">
        <?php echo htmlspecialchars($flash_message_display['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Appointments Card -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0 d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2"></i> Appointments List</span>
            <!-- Filter Form -->
            <form method="GET" action="manage_appointments.php" class="d-inline-flex align-items-center ms-auto">
                <input type="date" name="date" class="form-control form-control-sm me-2"
                    value="<?php echo htmlspecialchars($filter_date); ?>" title="Filter by Date">
                <select name="doctor_id" class="form-select form-select-sm me-2" title="Filter by Doctor">
                    <option value="">All Doctors</option>
                    <?php foreach ($doctors as $doc): ?>
                        <option value="<?php echo $doc['id']; ?>" <?php echo $filter_doctor == $doc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($doc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-select form-select-sm me-2" title="Filter by Status">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed
                    </option>
                    <option value="canceled" <?php echo $filter_status === 'canceled' ? 'selected' : ''; ?>>Canceled
                    </option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm me-1" title="Apply Filters"><i
                        class="fas fa-filter"></i></button>
                <a href="manage_appointments.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters"><i
                        class="fas fa-times"></i></a>
            </form>
        </h5>
    </div>
    <div class="card-body">
        <!-- Responsive Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light text-center">
                    <tr>
                        <!-- ID column removed -->
                        <th>Patient</th>
                        <th>Doctor</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody class="text-center">
                    <?php if (empty($appointments)): ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted">No appointments found matching the criteria.</td>
                            <!-- Changed colspan from 7 to 6 -->
                        </tr>
                    <?php else: ?>
                        <?php foreach ($appointments as $appointment): ?>
                            <tr>
                                <!-- ID column removed -->
                                <td class="text-start">
                                    <a href="client_details.php?client_id=<?php echo $appointment['patient_id']; ?>"
                                        title="View Patient Details">
                                        <?php echo htmlspecialchars($appointment['patient_name']); ?>
                                    </a>
                                </td>
                                <td class="text-start"><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                <td><?php echo htmlspecialchars(date('D, M j, Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                                </td>
                                <td>
                                    <span class="badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                                        <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group" role="group" aria-label="Appointment Actions">
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                            class="btn btn-outline-info btn-sm" data-bs-toggle="tooltip" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                            class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="manage_appointments.php" class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete appointment #<?php echo $appointment['appointment_id']; ?> for <?php echo htmlspecialchars(addslashes($appointment['patient_name'])); ?>?');">
                                            <input type="hidden" name="delete_id"
                                                value="<?php echo $appointment['appointment_id']; ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" data-bs-toggle="tooltip"
                                                title="Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- /card-body -->
</div> <!-- /card -->

<!-- Removed floating button, added button to top -->

<?php
include '../includes/footer.php'; // Include the shared footer
?>