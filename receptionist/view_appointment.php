<?php
// Define page title *before* including header
$page_title = 'Appointment Details';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo '<div class="alert alert-danger m-3">Invalid appointment ID provided.</div>';
    include '../includes/footer.php';
    exit;
}

$appointment_id = intval($_GET['id']);
$appointment = null;
$error_message = '';

try {
    // Fetch appointment details including doctor name
    // REMOVED p.email from SELECT list
    $query = "
        SELECT
            a.id AS appointment_id,
            p.id AS patient_id,
            CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
            p.contact_number AS patient_contact,
            -- p.email AS patient_email, -- REMOVED THIS LINE
            d.name AS doctor_name,
            s.service_name,
            s.description AS service_description,
            a.appointment_date,
            a.status,
            a.created_at
        FROM appointments a
        JOIN patients p ON a.patient_id = p.id
        JOIN users d ON a.doctor_id = d.id
        JOIN services s ON a.service_id = s.id
        WHERE a.id = :appointment_id
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        $error_message = "Appointment with ID " . $appointment_id . " not found.";
    }
} catch (PDOException $e) {
    // Check if the error is the specific column not found error
    if ($e->getCode() == '42S22' && strpos($e->getMessage(), 'p.email') !== false) {
        $error_message = "Database structure mismatch: The 'patients' table does not seem to have an 'email' column. Please update the code or database schema.";
    } else {
        $error_message = "Database Error: " . $e->getMessage();
    }
    // error_log("View Appointment Error: " . $e->getMessage());
}

// Function to get status badge class (copied from manage page)
function getStatusBadgeClass($status)
{
    switch (strtolower($status)) {
        case 'completed':
            return 'bg-success';
        case 'canceled':
            return 'bg-danger';
        case 'pending':
            return 'bg-warning text-dark';
        default:
            return 'bg-secondary';
    }
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-calendar-check me-2 text-info"></i> Appointment Details</h2>
    <a href="manage_appointments.php" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> Back to List</a>
</div>

<!-- Display Errors -->
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php else: ?>
    <!-- Appointment Details Card -->
    <div class="card shadow-sm">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-info-circle me-2"></i> Details for Appointment
            </h5>
            <span class="badge <?php echo getStatusBadgeClass($appointment['status']); ?> fs-6">
                <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
            </span>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5>Patient Information</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Name:</dt>
                        <dd class="col-sm-8">
                            <a href="client_details.php?patient_id=<?php echo $appointment['patient_id']; ?>"
                                title="View Patient Details">
                                <?php echo htmlspecialchars($appointment['patient_name']); ?>
                            </a>
                        </dd>

                        <dt class="col-sm-4">Contact:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['patient_contact'] ?: 'N/A'); ?></dd>

                        <!-- REMOVED Email Display -->
                        <!--
                        <dt class="col-sm-4">Email:</dt>
                        <dd class="col-sm-8"><?php // echo htmlspecialchars($appointment['patient_email'] ?: 'N/A'); ?></dd>
                        -->
                    </dl>
                </div>
                <div class="col-md-6">
                    <h5>Appointment Information</h5>
                    <dl class="row">
                        <dt class="col-sm-4">Date & Time:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(date('l, F j, Y \a\t h:i A', strtotime($appointment['appointment_date']))); ?>
                        </dd>

                        <dt class="col-sm-4">Doctor:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['doctor_name']); ?></dd>

                        <dt class="col-sm-4">Service:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></dd>

                        <?php if (!empty($appointment['service_description'])): ?>
                            <dt class="col-sm-4">Description:</dt>
                            <dd class="col-sm-8"><?php echo nl2br(htmlspecialchars($appointment['service_description'])); ?>
                            </dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Booked On:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(date('M j, Y, h:i A', strtotime($appointment['created_at']))); ?>
                        </dd>
                    </dl>
                </div>
            </div>
        </div> <!-- /card-body -->
        <div class="card-footer bg-light text-end">
            <a href="edit_appointment.php?id=<?php echo $appointment['appointment_id']; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit me-1"></i> Edit Appointment
            </a>
            <!-- Optional: Add Print Button or other actions -->
        </div>
    </div> <!-- /card -->
<?php endif; ?>

<?php
include '../includes/footer.php'; // Include the shared footer
?>