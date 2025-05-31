<?php
// filepath: c:\xampp\htdocs\Dentaly\receptionist\client_details.php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Client Details'; // Default title
include '../includes/header.php';

// Check if the user has permission
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'receptionist', 'doctor'])) { // Adjust roles as needed
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$client_id = null;
$client = null;
$appointments = [];
$teeth_graphs = [];
$total_spent = 0;
$message = '';
$message_class = 'alert-info';

if (!isset($_GET['client_id']) || !($client_id = filter_input(INPUT_GET, 'client_id', FILTER_VALIDATE_INT))) {
    $message = "Invalid or missing client ID.";
    $message_class = 'alert-danger';
} else {
    try {
        // Fetch Client Details
        $query = "SELECT p.*, u.email FROM patients p LEFT JOIN users u ON p.user_id = u.id WHERE p.id = :client_id"; // Fetch email if linked
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $page_title = "Details for " . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); // Set dynamic title

            // Fetch Appointment History
            $query_app = "
                SELECT
                    a.id AS appointment_id, a.appointment_date, a.status,
                    d.name AS doctor_name, s.service_name, s.price AS service_price
                FROM appointments a
                JOIN users d ON a.doctor_id = d.id
                JOIN services s ON a.service_id = s.id
                WHERE a.patient_id = :client_id
                ORDER BY a.appointment_date DESC;
            ";
            $stmt_app = $conn->prepare($query_app);
            $stmt_app->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt_app->execute();
            $appointments = $stmt_app->fetchAll(PDO::FETCH_ASSOC);

            // Fetch Teeth Graphs
            $query_graph = "SELECT * FROM teeth_graph WHERE patient_id = :client_id ORDER BY created_at DESC";
            $stmt_graph = $conn->prepare($query_graph);
            $stmt_graph->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt_graph->execute();
            $teeth_graphs = $stmt_graph->fetchAll(PDO::FETCH_ASSOC);

            // Calculate Total Money Spent (only completed appointments)
            $query_spent = "
                SELECT SUM(s.price) AS total_spent
                FROM appointments a JOIN services s ON a.service_id = s.id
                WHERE a.patient_id = :client_id AND a.status = 'completed';
            ";
            $stmt_spent = $conn->prepare($query_spent);
            $stmt_spent->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt_spent->execute();
            $total_spent_result = $stmt_spent->fetch(PDO::FETCH_ASSOC);
            $total_spent = $total_spent_result ? $total_spent_result['total_spent'] : 0;

        } else {
            $message = "Client with ID $client_id not found.";
            $message_class = 'alert-warning';
            $client = null; // Ensure client is null
        }

    } catch (PDOException $e) {
        $message = "Database error fetching client details: " . $e->getMessage();
        $message_class = 'alert-danger';
        $client = null; // Ensure client is null on error
        // error_log("Client Details Fetch Error: " . $e->getMessage()); // Log error
    }
}

// === POST Request Handling ===
if ($client && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle New Graph Upload
        if (isset($_POST['upload_graph'])) {
            $description = $_POST['description'] ?? 'N/A';
            $image = $_FILES['image'] ?? null;

            // Basic validation
            if (!$image || $image['error'] !== UPLOAD_ERR_OK || $image['size'] == 0) {
                $message = "Invalid input. Please provide a description and select a valid image file.";
                $message_class = 'alert-danger';
            } else {
                // Validate image type (example)
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp']; // Added webp
                $max_file_size = 5 * 1024 * 1024; // 5MB

                if (!in_array($image['type'], $allowed_types)) {
                    $message = "Invalid file type. Only JPG, PNG, GIF, and WEBP are allowed.";
                } elseif ($image['size'] > $max_file_size) {
                    $message = "File is too large. Maximum size is 5MB.";
                    $message_class = 'alert-danger';
                } else {
                    $upload_dir = '../uploads/teeth_graphs/';
                    if (!is_dir($upload_dir)) {
                        if (!mkdir($upload_dir, 0775, true)) { // Use 0775 for better security
                            throw new Exception("Failed to create upload directory.");
                        }
                    }

                    // Sanitize filename and create a unique name
                    $original_filename = basename($image['name']);
                    $safe_filename = preg_replace("/[^a-zA-Z0-9._-]/", "", $original_filename); // Remove special chars
                    $file_extension = strtolower(pathinfo($safe_filename, PATHINFO_EXTENSION));
                    if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) { // Double check extension
                        $message = "Invalid file extension after sanitization.";
                        $message_class = 'alert-danger';
                    } else {
                        $unique_file_name = "graph_" . $client_id . "_" . uniqid() . "_" . time() . "." . $file_extension;
                        $file_path = $upload_dir . $unique_file_name;
                        $relative_path = 'uploads/teeth_graphs/' . $unique_file_name; // Path to store in DB

                        if (move_uploaded_file($image['tmp_name'], $file_path)) {
                            $query = "INSERT INTO teeth_graph (patient_id, image_url, description) VALUES (:patient_id, :image_url, :description)";
                            $stmt = $conn->prepare($query);
                            $stmt->bindParam(':patient_id', $client_id, PDO::PARAM_INT);
                            $stmt->bindParam(':image_url', $relative_path, PDO::PARAM_STR); // Store relative path
                            $stmt->bindParam(':description', $description, PDO::PARAM_STR);

                            if ($stmt->execute()) {
                                $message = "Teeth graph uploaded successfully!";
                                $message_class = 'alert-success';
                                // Refresh graph list
                                $stmt_graph->execute();
                                $teeth_graphs = $stmt_graph->fetchAll(PDO::FETCH_ASSOC);
                            } else {
                                $message = "Failed to save graph information to database.";
                                $message_class = 'alert-danger';
                                if (file_exists($file_path))
                                    unlink($file_path); // Clean up uploaded file if DB insert fails
                            }
                        } else {
                            $message = "Failed to move uploaded file. Check permissions.";
                            $message_class = 'alert-danger';
                        }
                    }
                }
            }
        }

        // Handle Graph Deletion
        elseif (isset($_POST['delete_graph'])) {
            $graph_id = filter_input(INPUT_POST, 'graph_id', FILTER_VALIDATE_INT);
            // Fetch image_url from DB to ensure correct file is deleted
            $fetch_graph_stmt = $conn->prepare("SELECT image_url FROM teeth_graph WHERE id = :graph_id AND patient_id = :patient_id");
            $fetch_graph_stmt->bindParam(':graph_id', $graph_id, PDO::PARAM_INT);
            $fetch_graph_stmt->bindParam(':patient_id', $client_id, PDO::PARAM_INT);
            $fetch_graph_stmt->execute();
            $graph_data = $fetch_graph_stmt->fetch(PDO::FETCH_ASSOC);

            if (!$graph_id || !$graph_data) {
                $message = "Invalid graph ID or graph not found for this patient.";
                $message_class = 'alert-danger';
            } else {
                $query = "DELETE FROM teeth_graph WHERE id = :graph_id AND patient_id = :patient_id"; // Ensure it belongs to this patient
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':graph_id', $graph_id, PDO::PARAM_INT);
                $stmt->bindParam(':patient_id', $client_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    if ($stmt->rowCount() > 0) { // Check if a row was actually deleted
                        $message = "Graph deleted successfully!";
                        $message_class = 'alert-success';
                        // Attempt to delete the image file
                        if (!empty($graph_data['image_url'])) {
                            $image_path_absolute = '../' . $graph_data['image_url'];
                            if (file_exists($image_path_absolute)) {
                                if (!unlink($image_path_absolute)) {
                                    $message .= " (Warning: Failed to delete image file from server.)";
                                    $message_class = 'alert-warning';
                                }
                            }
                        }
                        // Refresh graph list
                        $stmt_graph->execute();
                        $teeth_graphs = $stmt_graph->fetchAll(PDO::FETCH_ASSOC);
                    } else {
                        $message = "Graph not found or already deleted.";
                        $message_class = 'alert-warning';
                    }
                } else {
                    $message = "Failed to delete graph from database.";
                    $message_class = 'alert-danger';
                }
            }
        }

        // Handle Client Deletion (Requires Admin Role) - but receptionist can't delete
        elseif (isset($_POST['delete_client']) && $_SESSION['role'] === 'admin') {
            if ($client['user_id'] == $_SESSION['user_id']) {
                $message = "Admins cannot delete their own linked patient record through this interface.";
                $message_class = 'alert-danger';
            } else {
                $conn->beginTransaction();

                // 1. Delete teeth graphs files first
                $query_get_graphs = "SELECT image_url FROM teeth_graph WHERE patient_id = :client_id";
                $stmt_get_graphs = $conn->prepare($query_get_graphs);
                $stmt_get_graphs->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt_get_graphs->execute();
                $graph_files = $stmt_get_graphs->fetchAll(PDO::FETCH_ASSOC);
                foreach ($graph_files as $graph_file) {
                    $file_to_delete = '../' . $graph_file['image_url'];
                    if (!empty($graph_file['image_url']) && file_exists($file_to_delete)) {
                        unlink($file_to_delete);
                    }
                }

                // 2. Delete teeth graph records
                $query_del_graphs = "DELETE FROM teeth_graph WHERE patient_id = :client_id";
                $stmt_del_graphs = $conn->prepare($query_del_graphs);
                $stmt_del_graphs->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt_del_graphs->execute();

                // 3. Delete appointments
                $query_del_app = "DELETE FROM appointments WHERE patient_id = :client_id";
                $stmt_del_app = $conn->prepare($query_del_app);
                $stmt_del_app->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt_del_app->execute();

                // 4. Delete the patient record
                $query_del_patient = "DELETE FROM patients WHERE id = :client_id";
                $stmt_del_patient = $conn->prepare($query_del_patient);
                $stmt_del_patient->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt_del_patient->execute();

                $conn->commit();

                // Redirect after successful deletion
                $_SESSION['flash_message'] = "Client (ID: $client_id) and all associated data deleted successfully.";
                $_SESSION['flash_message_class'] = 'alert-success';
                header("Location: client_history.php");
                exit;
            }

        } elseif (isset($_POST['delete_client']) && $_SESSION['role'] !== 'admin') {
            $message = "You do not have permission to delete clients.";
            $message_class = 'alert-danger';
        }

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $message = "Database error processing request: " . $e->getMessage();
        $message_class = 'alert-danger';
    } catch (Exception $e) {
        $message = "An error occurred: " . $e->getMessage();
        $message_class = 'alert-danger';
    }
}

// Retrieve flash message if redirected - FIX THIS SECTION
if (isset($_SESSION['flash_message'])) {
    // Check if it's an array (from edit_patient.php) or string (from other sources)
    if (is_array($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message']['text'] ?? 'Operation completed.';
        $message_class = $_SESSION['flash_message']['type'] === 'success' ? 'alert-success' : 'alert-info';
    } else {
        // Handle legacy string format
        $message = $_SESSION['flash_message'];
        $message_class = $_SESSION['flash_message_class'] ?? 'alert-info';
    }

    // Clean up session variables
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_message_class']);
}

?>

<!-- Page Title & Actions -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
    <div>
        <a href="client_history.php" class="btn btn-outline-secondary me-2">
            <i class="fas fa-search me-2"></i> Back to Search
        </a>
        <?php if ($client): ?>
            <a href="edit_patient.php?id=<?php echo $client_id; ?>" class="btn btn-outline-primary me-2">
                <i class="fas fa-edit me-1"></i> Edit Client
            </a>
            <?php if ($_SESSION['role'] === 'admin' && $client['user_id'] != $_SESSION['user_id']): ?>
                <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteClientModal">
                    <i class="fas fa-trash-alt me-2"></i> Delete Client
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo htmlspecialchars($message_class); ?> alert-dismissible fade show" role="alert"
        data-auto-dismiss="8000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($client): ?>

    <!-- Client Information Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <i class="fas fa-user-circle me-2"></i> Client Information
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Name:</strong></p>
                    <p class="text-muted">
                        <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?>
                    </p>

                    <p class="mb-1"><strong>Contact:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($client['contact_number'] ?: 'N/A'); ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>Email:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($client['email'] ?: 'N/A'); ?></p>

                    <p class="mb-1"><strong>Address:</strong></p>
                    <p class="text-muted"><?php echo htmlspecialchars($client['address'] ?: 'N/A'); ?></p>
                </div>
                <div class="col-12 mt-2">
                    <p class="mb-1"><strong>Total Spent (Completed Appointments):</strong></p>
                    <h4 class="text-success">$<?php echo number_format($total_spent ?? 0, 2); ?></h4>
                </div>
            </div>
            <a href="edit_patient.php?id=<?php echo $client_id; ?>" class="btn btn-sm btn-outline-secondary mt-2">
                <i class="fas fa-edit me-1"></i> Edit Client Info
            </a>
        </div>
    </div>

    <!-- Appointment History Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header">
            <i class="fas fa-calendar-alt me-2"></i> Appointment History
        </div>
        <div class="card-body p-0">
            <?php if (empty($appointments)): ?>
                <p class="text-muted text-center p-3">No appointment history found for this client.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Date & Time</th>
                                <th>Doctor</th>
                                <th>Service</th>
                                <th class="text-center">Status</th>
                                <th class="text-end">Price</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($appointment['appointment_date']))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                    <td class="text-center">
                                        <span class="badge rounded-pill <?php
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
                                    </td>
                                    <td class="text-end">$<?php echo number_format($appointment['service_price'], 2); ?></td>
                                    <td class="text-center">
                                        <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                            class="btn btn-sm btn-outline-info" data-bs-toggle="tooltip" title="View Appointment">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-footer text-end">
            <a href="add_appointment.php?patient_id=<?php echo $client_id; ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i> Add New Appointment for Client
            </a>
        </div>
    </div>

    <!-- Teeth Graphs Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-tooth me-2"></i> Teeth Graphs</span>
            <button class="btn btn-sm btn-success" type="button" data-bs-toggle="collapse"
                data-bs-target="#uploadGraphCollapse" aria-expanded="false" aria-controls="uploadGraphCollapse">
                <i class="fas fa-upload me-1"></i> Upload New Graph
            </button>
        </div>
        <div class="card-body">
            <!-- Upload Form (Collapsible) -->
            <div class="collapse mb-4" id="uploadGraphCollapse">
                <div class="card card-body bg-light border">
                    <h6 class="mb-3">Upload New Teeth Graph</h6>
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?client_id=' . $client_id; ?>"
                        method="POST" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="description" class="form-label">Description <span
                                    class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="description" name="description" required>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="image" name="image"
                                accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <small class="text-muted">Allowed types: JPG, PNG, GIF, WEBP. Max 5MB.</small>
                        </div>
                        <button type="submit" class="btn btn-primary" name="upload_graph"><i
                                class="fas fa-upload me-2"></i>Upload</button>
                    </form>
                </div>
            </div>

            <!-- Display Existing Graphs -->
            <?php if (empty($teeth_graphs)): ?>
                <p class="text-muted text-center">No teeth graphs uploaded for this client.</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                    <?php foreach ($teeth_graphs as $graph): ?>
                        <div class="col">
                            <div class="card h-100 shadow-sm">
                                <a href="#" data-bs-toggle="modal" data-bs-target="#graphModal<?php echo $graph['id']; ?>">
                                    <img src="<?php echo htmlspecialchars($graph['image_url']); ?>" class="card-img-top"
                                        alt="Teeth Graph <?php echo $graph['id']; ?>" style="max-height: 200px; object-fit: cover;">
                                </a>
                                <div class="card-body d-flex flex-column">
                                    <p class="card-text small text-muted flex-grow-1">
                                        <?php echo htmlspecialchars($graph['description']); ?>
                                    </p>
                                    <small class="text-muted d-block mb-2">Uploaded:
                                        <?php echo date('d M Y', strtotime($graph['created_at'])); ?></small>
                                    <form
                                        action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?client_id=' . $client_id; ?>"
                                        method="POST" onsubmit="return confirm('Are you sure you want to delete this graph?');">
                                        <input type="hidden" name="graph_id" value="<?php echo $graph['id']; ?>">
                                        <button type="submit" name="delete_graph"
                                            class="btn btn-outline-danger btn-sm w-100 confirm-delete"
                                            data-message="Are you sure you want to delete this graph: <?php echo htmlspecialchars($graph['description']); ?>?">
                                            <i class="fas fa-trash-alt me-1"></i> Delete Graph
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Image Modal -->
                        <div class="modal fade" id="graphModal<?php echo $graph['id']; ?>" tabindex="-1"
                            aria-labelledby="graphModalLabel<?php echo $graph['id']; ?>" aria-hidden="true">
                            <div class="modal-dialog modal-lg modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="graphModalLabel<?php echo $graph['id']; ?>">
                                            <?php echo htmlspecialchars($graph['description']); ?>
                                        </h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <img src="<?php echo htmlspecialchars($graph['image_url']); ?>" class="img-fluid"
                                            alt="Teeth Graph <?php echo $graph['id']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Client Confirmation Modal (for Admin only) -->
    <?php if ($_SESSION['role'] === 'admin' && $client && $client['user_id'] != $_SESSION['user_id']): ?>
        <div class="modal fade" id="deleteClientModal" tabindex="-1" aria-labelledby="deleteClientModalLabel"
            aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?client_id=' . $client_id; ?>"
                        method="POST">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteClientModalLabel"><i class="fas fa-exclamation-triangle me-2"></i>
                                Confirm Client Deletion</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p>Are you absolutely sure you want to delete client
                                <strong><?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></strong>
                                (ID: <?php echo $client_id; ?>)?
                            </p>
                            <p class="text-danger fw-bold">This action cannot be undone. All associated appointments and teeth
                                graphs will also be permanently deleted.</p>
                        </div>
                        <div class="modal-footer"></div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_client" class="btn btn-danger"><i class="fas fa-trash-alt me-2"></i>
                            Yes, Delete Client</button>
                </div>
                </form>
            </div>
        </div>
        </div>
    <?php endif; ?>

<?php endif; ?>

<!-- Initialize Bootstrap Tooltips -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<?php include '../includes/footer.php'; ?>