<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Manage Doctor Schedules';
include '../includes/header.php'; // Include the header with the sidebar

// Check if the user has permission (e.g., admin or maybe doctor)
// For now, let's restrict to admin
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = '';
$message_class = 'alert-info'; // Default message class

// Days of the week array for display and selection
$days_of_week = [
    0 => 'Sunday',
    1 => 'Monday',
    2 => 'Tuesday',
    3 => 'Wednesday',
    4 => 'Thursday',
    5 => 'Friday',
    6 => 'Saturday'
];

// --- Handle Form Submissions ---

// Handle Add Availability Slot
if (isset($_POST['add_availability'])) {
    $doctor_id = filter_input(INPUT_POST, 'doctor_id', FILTER_VALIDATE_INT);
    $day_of_week = filter_input(INPUT_POST, 'day_of_week', FILTER_VALIDATE_INT);
    $start_time = $_POST['start_time'] ?? ''; // Basic validation needed
    $end_time = $_POST['end_time'] ?? '';     // Basic validation needed

    // Basic Validation (add more robust time validation)
    if (!$doctor_id || !isset($days_of_week[$day_of_week]) || empty($start_time) || empty($end_time) || $start_time >= $end_time) {
        $message = "Invalid input. Please select a doctor, day, and valid start/end times (start must be before end).";
        $message_class = 'alert-danger';
    } else {
        try {
            // Check for overlapping slots (more complex logic needed for full overlap check)
            // Simple check for exact duplicate start time:
            $check_query = "SELECT COUNT(*) FROM doctor_availability WHERE doctor_id = :doctor_id AND day_of_week = :day AND start_time = :start";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute([':doctor_id' => $doctor_id, ':day' => $day_of_week, ':start' => $start_time]);
            if ($check_stmt->fetchColumn() > 0) {
                $message = "Error: This exact start time slot already exists for this doctor on this day.";
                $message_class = 'alert-warning';
            } else {
                $query = "INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time) VALUES (:doctor_id, :day, :start, :end)";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
                $stmt->bindParam(':day', $day_of_week, PDO::PARAM_INT);
                $stmt->bindParam(':start', $start_time, PDO::PARAM_STR);
                $stmt->bindParam(':end', $end_time, PDO::PARAM_STR);

                if ($stmt->execute()) {
                    $message = "Availability slot added successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Failed to add availability slot. Database error.";
                    $message_class = 'alert-danger';
                }
            }
        } catch (PDOException $e) {
            // Catch potential unique constraint violation
            if ($e->getCode() == '23000') {
                $message = "Error: This exact time slot might already exist.";
            } else {
                $message = "Database error adding availability: " . $e->getMessage();
            }
            $message_class = 'alert-danger';
        }
    }
}

// Handle Delete Availability Slot
if (isset($_POST['delete_availability'])) {
    $availability_id = filter_input(INPUT_POST, 'availability_id', FILTER_VALIDATE_INT);

    if (!$availability_id) {
        $message = "Invalid availability ID for deletion.";
        $message_class = 'alert-danger';
    } else {
        try {
            $query = "DELETE FROM doctor_availability WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $availability_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $message = "Availability slot deleted successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Availability slot not found or already deleted.";
                    $message_class = 'alert-warning';
                }
            } else {
                $message = "Failed to delete availability slot.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Database error deleting availability: " . $e->getMessage();
            $message_class = 'alert-danger';
        }
    }
}


// --- Fetch Data for Display ---
try {
    // Fetch Doctors
    $doctors = $conn->query("SELECT id, name FROM users WHERE role = 'doctor' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Existing Availability, joining with users table to get doctor names
    $availability_query = "
        SELECT da.id, da.doctor_id, da.day_of_week, da.start_time, da.end_time, u.name as doctor_name
        FROM doctor_availability da
        JOIN users u ON da.doctor_id = u.id
        WHERE u.role = 'doctor'
        ORDER BY u.name, da.day_of_week, da.start_time
    ";
    $availability_slots = $conn->query($availability_query)->fetchAll(PDO::FETCH_ASSOC);

    // Group availability by doctor for easier display
    $grouped_availability = [];
    foreach ($availability_slots as $slot) {
        $grouped_availability[$slot['doctor_id']]['name'] = $slot['doctor_name'];
        $grouped_availability[$slot['doctor_id']]['slots'][] = $slot;
    }

} catch (PDOException $e) {
    $message = "Error fetching data: " . $e->getMessage();
    $message_class = 'alert-danger';
    $doctors = [];
    $grouped_availability = [];
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo $message_class; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Add Availability Form Card (Collapsible) -->
<div class="accordion mb-4" id="addAvailabilityAccordion">
    <div class="accordion-item card shadow-sm border-0">
        <h2 class="accordion-header card-header bg-light border-bottom-0" id="headingAdd">
            <button class="accordion-button collapsed bg-transparent text-dark fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false"
                aria-controls="collapseAdd">
                <i class="fas fa-calendar-plus me-2 text-primary"></i> Add Weekly Availability Slot
            </button>
        </h2>
        <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd"
            data-bs-parent="#addAvailabilityAccordion">
            <div class="accordion-body card-body pt-2">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="add_doctor_id" class="form-label">Doctor <span
                                    class="text-danger">*</span></label>
                            <select name="doctor_id" id="add_doctor_id" class="form-select" required>
                                <option value="" selected>Select Doctor...</option>
                                <?php foreach ($doctors as $doctor): ?>
                                    <option value="<?php echo $doctor['id']; ?>">
                                        <?php echo htmlspecialchars($doctor['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="add_day_of_week" class="form-label">Day of Week <span
                                    class="text-danger">*</span></label>
                            <select name="day_of_week" id="add_day_of_week" class="form-select" required>
                                <option value="" selected>Select Day...</option>
                                <?php foreach ($days_of_week as $num => $day): ?>
                                    <option value="<?php echo $num; ?>"><?php echo $day; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="add_start_time" class="form-label">Start Time <span
                                    class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="add_start_time" class="form-control" required>
                        </div>
                        <div class="col-md-2 mb-3">
                            <label for="add_end_time" class="form-label">End Time <span
                                    class="text-danger">*</span></label>
                            <input type="time" name="end_time" id="add_end_time" class="form-control" required>
                        </div>
                        <div class="col-md-1 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100" name="add_availability"
                                data-bs-toggle="tooltip" title="Add Slot">
                                <i class="fas fa-plus"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Display Schedules Card -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-clock me-2"></i> Current Weekly Schedules
    </div>
    <div class="card-body">
        <?php if (empty($grouped_availability)): ?>
            <p class="text-muted text-center">No availability slots defined yet.</p>
        <?php else: ?>
            <?php foreach ($grouped_availability as $doctor_id => $data): ?>
                <h5 class="mb-3 mt-3 border-bottom pb-2"><i class="fas fa-user-md me-2 text-info"></i>
                    <?php echo htmlspecialchars($data['name']); ?></h5>
                <div class="table-responsive">
                    <table class="table table-sm table-striped table-hover align-middle mb-4">
                        <thead class="table-light">
                            <tr>
                                <th>Day</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Sort slots by day then start time within the doctor's group
                            usort($data['slots'], function ($a, $b) {
                                if ($a['day_of_week'] == $b['day_of_week']) {
                                    return strcmp($a['start_time'], $b['start_time']);
                                }
                                return $a['day_of_week'] <=> $b['day_of_week'];
                            });
                            ?>
                            <?php foreach ($data['slots'] as $slot): ?>
                                <tr>
                                    <td><?php echo $days_of_week[$slot['day_of_week']]; ?></td>
                                    <td><?php echo date('h:i A', strtotime($slot['start_time'])); ?></td>
                                    <td><?php echo date('h:i A', strtotime($slot['end_time'])); ?></td>
                                    <td class="text-center">
                                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST"
                                            class="d-inline"
                                            onsubmit="return confirm('Are you sure you want to delete this time slot?');">
                                            <input type="hidden" name="availability_id" value="<?php echo $slot['id']; ?>">
                                            <button type="submit" name="delete_availability" class="btn btn-sm btn-outline-danger"
                                                data-bs-toggle="tooltip" title="Delete Slot">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Initialize Bootstrap Tooltips -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>