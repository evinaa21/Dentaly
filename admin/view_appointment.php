<?php
include '../config/db.php';

// Define page title *before* including header
$page_title = 'View Appointment Details';
include '../includes/header.php';

// Check if the user is actually an admin - redirect if not
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$appointment_id = null;
$appointment = null;
$message = '';
$message_class = 'alert-danger';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $message = "Invalid or missing appointment ID.";
} else {
    $appointment_id = intval($_GET['id']);

    try {
        // Fetch appointment details with joins - Fixed query
        $query = "
            SELECT
                a.id AS appointment_id,
                p.id AS patient_id,
                CONCAT(p.first_name, ' ', p.last_name) AS patient_name,
                p.contact_number AS patient_contact,
                p.address AS patient_address,
                d.name AS doctor_name,

                s.service_name,
                s.description AS service_description,
                s.price AS service_price,
                a.appointment_date,
                a.status,
                a.notes,
                a.created_at,
                pu.email AS patient_email
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN users d ON a.doctor_id = d.id
            JOIN services s ON a.service_id = s.id
            LEFT JOIN users pu ON p.user_id = pu.id
            WHERE a.id = :appointment_id
        ";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            $message = "Appointment not found.";
            $appointment = null;
        }
    } catch (PDOException $e) {
        $message = "Database Query Failed: " . $e->getMessage();
        $appointment = null;
    }
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">Appointment Details</h2>
    <div>
        <?php if ($appointment): ?>
            <a href="edit_appointment.php?id=<?php echo htmlspecialchars($appointment_id); ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit me-2"></i> Edit Appointment
            </a>
        <?php endif; ?>
        <a href="manage_appointments.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to List
        </a>
    </div>
</div>

<!-- Display Message if any error occurred -->
<?php if ($message && !$appointment): ?>
    <div class="alert <?php echo $message_class; ?>" role="alert">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<?php if ($appointment): ?>
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <i class="fas fa-calendar-check me-2"></i>
                Appointment Details
                <span class="badge rounded-pill ms-2 <?php
                switch ($appointment['status']) {
                    case 'completed':
                        echo 'bg-success';
                        break;
                    case 'canceled':
                        echo 'bg-danger';
                        break;
                    default:
                        echo 'bg-warning text-dark';
                        break;
                }
                ?>">
                    <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                </span>
            </span>
            <small class="text-muted">Booked:
                <?php echo htmlspecialchars(date('d M Y', strtotime($appointment['created_at']))); ?></small>
        </div>
        <div class="card-body">
            <div class="row">
                <!-- Appointment Details Section -->
                <div class="col-md-6 col-lg-4 mb-3">
                    <h5><i class="fas fa-calendar-alt text-primary me-2"></i>Appointment Info</h5>
                    <hr class="mt-1 mb-2">
                    <p class="mb-1"><strong>Date & Time:</strong></p>
                    <p class="text-muted">
                        <?php echo htmlspecialchars(date('l, F j, Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                    </p>

                    <p class="mb-1"><strong>Doctor:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($appointment['doctor_name']); ?></p>

                    <p class="mb-1"><strong>Status:</strong></p>
                    <p class="text-muted"><?php echo ucfirst(htmlspecialchars($appointment['status'])); ?></p>

                    <?php if (!empty($appointment['notes'])): ?>
                        <p class="mb-1"><strong>Notes:</strong></p>
                        <p class="text-muted"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Patient Details Section -->
                <div class="col-md-6 col-lg-4 mb-3">
                    <h5><i class="fas fa-user text-primary me-2"></i>Patient Details</h5>
                    <hr class="mt-1 mb-2">
                    <p class="mb-1"><strong>Name:</strong></p>
                    <p class="text-muted">
                        <a href="client_details.php?client_id=<?php echo htmlspecialchars($appointment['patient_id']); ?>"
                            title="View Client History">
                            <?php echo htmlspecialchars($appointment['patient_name']); ?>
                        </a>
                    </p>

                    <p class="mb-1"><strong>Contact:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($appointment['patient_contact'] ?: 'N/A'); ?></p>

                    <p class="mb-1"><strong>Email:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($appointment['patient_email'] ?: 'N/A'); ?></p>

                    <p class="mb-1"><strong>Address:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($appointment['patient_address'] ?: 'N/A'); ?></p>
                </div>

                <!-- Service Details Section -->
                <div class="col-lg-4 mb-3">
                    <h5><i class="fas fa-briefcase-medical text-primary me-2"></i>Service Details</h5>
                    <hr class="mt-1 mb-2">
                    <p class="mb-1"><strong>Service:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($appointment['service_name']); ?></p>

                    <p class="mb-1"><strong>Description:</strong></p>
                    <p class="text-muted">
                        <?php echo htmlspecialchars($appointment['service_description'] ?: 'No description available.'); ?>
                    </p>

                    <p class="mb-1"><strong>Price:</strong></p>
                    <p class="text-muted">
                        $<?php echo htmlspecialchars(number_format($appointment['service_price'] ?? 0, 2)); ?></p>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>