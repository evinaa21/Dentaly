<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'View Appointment Details';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a doctor
if ($_SESSION['role'] !== 'doctor') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// Get logged-in doctor ID
$doctor_id = $_SESSION['user_id'];
$appointment_id = null;
$appointment = null;
$error_message = '';

// 1. Get Appointment ID from URL and Validate
if (!isset($_GET['id']) || !($appointment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT))) {
    $error_message = "Invalid or missing appointment ID.";
} else {
    // 2. Fetch Appointment Data (Ensure it belongs to the logged-in doctor)
    try {
        $query = "
            SELECT
                a.id AS appointment_id,
                a.appointment_date,
                a.status,
                a.notes AS appointment_notes, -- Assuming an 'notes' column exists in 'appointments'
                p.id AS patient_id,
                p.first_name AS patient_first_name,
                p.last_name AS patient_last_name,
                p.contact_number AS patient_contact,
                p.address AS patient_address,
                s.id AS service_id,
                s.service_name,
                s.description AS service_description,
                s.price AS service_price -- Assuming a price column exists
            FROM appointments a
            JOIN patients p ON a.patient_id = p.id
            JOIN services s ON a.service_id = s.id
            WHERE a.id = :appointment_id AND a.doctor_id = :doctor_id
            LIMIT 1
        ";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':appointment_id', $appointment_id, PDO::PARAM_INT);
        $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
        $stmt->execute();
        $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$appointment) {
            $error_message = "Appointment not found or you do not have permission to view it.";
        }

    } catch (PDOException $e) {
        $error_message = "Database error fetching appointment details: " . htmlspecialchars($e->getMessage());
        // error_log("View Appointment DB Error: " . $e->getMessage()); // Log detailed error
    }
}
?>

<!-- Page Title & Back Button -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0">
        <i class="fas fa-calendar-check me-2 text-primary"></i>
        <?php echo $appointment ? 'Appointment Details' : 'View Appointment'; ?>
    </h2>
    <a href="index.php" class="btn btn-secondary"> <!-- Link back to Doctor Dashboard -->
        <i class="fas fa-arrow-left me-2"></i> Back to Dashboard
    </a>
</div>

<!-- Display Error Message if any -->
<?php if ($error_message): ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php else: ?>
    <!-- Main Content Row -->
    <div class="row">
        <!-- Left Column: Appointment & Service Details -->
        <div class="col-lg-6 mb-4">
            <!-- Appointment Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clock me-2 text-info"></i> Appointment Info</h5>
                    <?php
                    // Determine badge class based on status
                    $status_class = 'bg-secondary'; // Default
                    if ($appointment['status'] == 'pending')
                        $status_class = 'bg-warning text-dark';
                    elseif ($appointment['status'] == 'completed')
                        $status_class = 'bg-success';
                    elseif ($appointment['status'] == 'canceled')
                        $status_class = 'bg-danger';
                    ?>
                    <span
                        class="badge <?php echo $status_class; ?> fs-6"><?php echo ucfirst(htmlspecialchars($appointment['status'])); ?></span>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Date & Time:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars(date('D, d M Y - h:i A', strtotime($appointment['appointment_date']))); ?>
                        </dd>

                        <dt class="col-sm-4">Status:</dt>
                        <dd class="col-sm-8"><?php echo ucfirst(htmlspecialchars($appointment['status'])); ?></dd>

                        <dt class="col-sm-4">Appointment ID:</dt>
                        <dd class="col-sm-8">#<?php echo htmlspecialchars($appointment['appointment_id']); ?></dd>

                        <?php if (!empty($appointment['appointment_notes'])): ?>
                            <dt class="col-sm-4 mt-2">Notes:</dt>
                            <dd class="col-sm-8 mt-2 fst-italic">
                                <?php echo nl2br(htmlspecialchars($appointment['appointment_notes'])); ?>
                            </dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>

            <!-- Service Details Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-briefcase-medical me-2 text-secondary"></i> Service Details</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Service:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['service_name']); ?></dd>

                        <?php if (!empty($appointment['service_description'])): ?>
                            <dt class="col-sm-4">Description:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['service_description']); ?></dd>
                        <?php endif; ?>

                        <?php if (isset($appointment['service_price'])): ?>
                            <dt class="col-sm-4">Price:</dt>
                            <dd class="col-sm-8">
                                $<?php echo htmlspecialchars(number_format($appointment['service_price'], 2)); ?></dd>
                        <?php endif; ?>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Right Column: Patient Details & Actions -->
        <div class="col-lg-6 mb-4">
            <!-- Patient Details Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-user-injured me-2 text-success"></i> Patient Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Name:</dt>
                        <dd class="col-sm-8">
                            <?php echo htmlspecialchars($appointment['patient_first_name'] . ' ' . $appointment['patient_last_name']); ?>
                        </dd>

                        <dt class="col-sm-4">Contact:</dt>
                        <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['patient_contact'] ?: 'N/A'); ?></dd>

                        <?php if (!empty($appointment['patient_address'])): ?>
                            <dt class="col-sm-4">Address:</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($appointment['patient_address']); ?></dd>
                        <?php endif; ?>

                        <?php if (!empty($appointment['patient_dob'])): ?>
                            <dt class="col-sm-4">Date of Birth:</dt>
                            <dd class="col-sm-8">
                                <?php echo htmlspecialchars(date('d M Y', strtotime($appointment['patient_dob']))); ?> (Age:
                                <?php echo date_diff(date_create($appointment['patient_dob']), date_create('today'))->y; ?>)
                            </dd>
                        <?php endif; ?>

                        <dt class="col-sm-4">Patient ID:</dt>
                        <dd class="col-sm-8">#<?php echo htmlspecialchars($appointment['patient_id']); ?></dd>
                    </dl>
                    <!-- Link to full patient record -->
                    <div class="mt-3 border-top pt-3 text-center">
                        <a href="patient_details.php?patient_id=<?php echo $appointment['patient_id']; ?>"
                            class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-notes-medical me-1"></i> View Full Patient Record
                        </a>
                    </div>
                </div>
            </div>

            <!-- Actions Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-tasks me-2 text-warning"></i> Actions</h5>
                </div>
                <div class="card-body text-center">
                    <?php if ($appointment['status'] == 'pending'): ?>
                        <button class="btn btn-success me-2"
                            onclick="updateAppointmentStatus(<?php echo $appointment_id; ?>, 'completed')">
                            <i class="fas fa-check me-1"></i> Mark as Completed
                        </button>
                        <button class="btn btn-danger me-2"
                            onclick="updateAppointmentStatus(<?php echo $appointment_id; ?>, 'canceled')">
                            <i class="fas fa-times me-1"></i> Cancel Appointment
                        </button>
                    <?php elseif ($appointment['status'] == 'completed'): ?>
                        <p class="text-success mb-0"><i class="fas fa-check-circle me-1"></i> This appointment is completed.</p>
                        <!-- Option to revert? Maybe too complex for now -->
                    <?php elseif ($appointment['status'] == 'canceled'): ?>
                        <p class="text-danger mb-0"><i class="fas fa-times-circle me-1"></i> This appointment was canceled.</p>
                        <!-- Option to reschedule? -->
                    <?php endif; ?>

                    <!-- Add Notes Button (Could open a modal or link to another page) -->
                    <button class="btn btn-outline-info mt-3" data-bs-toggle="modal" data-bs-target="#notesModal">
                        <i class="fas fa-sticky-note me-1"></i> Add/Edit Notes
                    </button>
                </div>
            </div>

        </div> <!-- /col-lg-6 -->
    </div> <!-- /row -->

    <!-- Notes Modal (Example) -->
    <div class="modal fade" id="notesModal" tabindex="-1" aria-labelledby="notesModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notesModalLabel"><i class="fas fa-sticky-note me-2"></i> Appointment Notes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="notesForm">
                        <input type="hidden" name="appointment_id" value="<?php echo $appointment_id; ?>">
                        <div class="mb-3">
                            <label for="appointmentNotes" class="form-label">Notes:</label>
                            <textarea class="form-control" id="appointmentNotes" name="notes"
                                rows="6"><?php echo htmlspecialchars($appointment['appointment_notes'] ?? ''); ?></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveNotes()">Save Notes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for AJAX Actions -->
    <script>
        function updateAppointmentStatus(id, newStatus) {
            const statusText = newStatus === 'completed' ? 'completed' : 'canceled';
            if (!confirm(`Are you sure you want to mark this appointment as ${statusText}?`)) {
                return;
            }

            // Basic Fetch API example for AJAX
            fetch('../ajax/update_appointment_status.php', { // You need to create this endpoint
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointment_id: id,
                    status: newStatus
                })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Reload the page to show updated status and buttons
                        window.location.reload();
                        // Or update UI dynamically (more complex)
                        // alert('Status updated successfully!');
                    } else {
                        alert('Error updating status: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while communicating with the server.');
                });
        }

        function saveNotes() {
            const form = document.getElementById('notesForm');
            const formData = new FormData(form);

            fetch('../ajax/save_appointment_notes.php', { // You need to create this endpoint
                method: 'POST',
                body: formData // FormData handles encoding
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Close modal and optionally show success message
                        var notesModal = bootstrap.Modal.getInstance(document.getElementById('notesModal'));
                        notesModal.hide();
                        // Optionally update notes display on page without reload
                        alert('Notes saved successfully!');
                        // Simple reload for now:
                        window.location.reload();
                    } else {
                        alert('Error saving notes: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while communicating with the server.');
                });
        }
    </script>

<?php endif; // End check for error message ?>

<?php
include '../includes/footer.php'; // Include the shared footer
?>