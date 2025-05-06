<?php
// Define page title *before* including header
// We'll set a more specific title after fetching the client name
$page_title = 'Client Details';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a receptionist
if ($_SESSION['role'] !== 'receptionist') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

if (!isset($_GET['client_id']) || empty($_GET['client_id'])) {
    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid client ID provided.'];
    header("Location: client_history.php"); // Redirect back to search
    exit;
}

$client_id = intval($_GET['client_id']);
$client = null;
$appointments = [];
$teeth_graphs = [];
$total_spent = 0;
$error_message = '';

// --- Fetch Client Data ---
try {
    // Fetch Client Details
    $query = "SELECT * FROM patients WHERE id = :client_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($client) {
        // Set a more specific page title
        $page_title = 'Details for ' . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);

        // Fetch Appointment History
        $query_app = "
            SELECT
                a.id AS appointment_id,
                a.appointment_date,
                a.status,
                d.name AS doctor_name,
                s.service_name,
                s.price AS service_price
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

        // Calculate Total Money Spent (only on completed appointments)
        $query_spent = "
            SELECT SUM(s.price) AS total_spent
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.patient_id = :client_id AND a.status = 'completed';
        ";
        $stmt_spent = $conn->prepare($query_spent);
        $stmt_spent->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt_spent->execute();
        $total_spent_result = $stmt_spent->fetch(PDO::FETCH_ASSOC);
        $total_spent = $total_spent_result ? $total_spent_result['total_spent'] : 0; // Handle null case

    } else {
        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Client with the specified ID was not found.'];
        header("Location: client_history.php"); // Redirect back to search
        exit;
    }
} catch (PDOException $e) {
    $error_message = "Database Error: Failed to fetch client data.";
    // error_log("Client Details Fetch Error: " . $e->getMessage());
    // Don't redirect here, show error on the page
}


// --- Handle New Graph Upload ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_graph'])) {
    $description = $_POST['description'] ?? 'N/A';
    $image = $_FILES['image'] ?? null;

    if ($image && $image['error'] === UPLOAD_ERR_OK) {
        // Ensure the upload directory exists
        $upload_dir = '../uploads/teeth_graphs/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0775, true)) { // Use 0775 for better security
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to create upload directory. Check permissions.'];
                header("Location: client_details.php?client_id=$client_id");
                exit;
            }
        }

        // Generate a unique file name
        $file_extension = pathinfo($image['name'], PATHINFO_EXTENSION);
        $safe_filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', pathinfo($image['name'], PATHINFO_FILENAME)); // Sanitize filename
        $file_name = uniqid($client_id . '_', true) . '_' . $safe_filename . '.' . $file_extension;
        $file_path = $upload_dir . $file_name;
        $relative_path = '../uploads/teeth_graphs/' . $file_name; // Path to store in DB

        // Validate file type (basic check)
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($image['type'], $allowed_types)) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed.'];
        }
        // Validate file size (e.g., max 5MB)
        elseif ($image['size'] > 5 * 1024 * 1024) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'File is too large. Maximum size is 5MB.'];
        }
        // Move the uploaded file
        elseif (move_uploaded_file($image['tmp_name'], $file_path)) {
            try {
                $query_insert_graph = "INSERT INTO teeth_graph (patient_id, image_url, description) VALUES (:patient_id, :image_url, :description)";
                $stmt_insert_graph = $conn->prepare($query_insert_graph);
                $stmt_insert_graph->bindParam(':patient_id', $client_id, PDO::PARAM_INT);
                $stmt_insert_graph->bindParam(':image_url', $relative_path, PDO::PARAM_STR); // Store relative path
                $stmt_insert_graph->bindParam(':description', $description, PDO::PARAM_STR);

                if ($stmt_insert_graph->execute()) {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Teeth graph uploaded successfully!'];
                } else {
                    $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to save graph details to database.'];
                    unlink($file_path); // Delete the uploaded file if DB insert fails
                }
            } catch (PDOException $e) {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error saving graph.'];
                // error_log("Graph Upload DB Error: " . $e->getMessage());
                unlink($file_path); // Delete the uploaded file if DB insert fails
            }
        } else {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to move uploaded file. Check server permissions.'];
        }
    } elseif ($image && $image['error'] !== UPLOAD_ERR_NO_FILE) {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Error uploading file. Code: ' . $image['error']];
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'No file selected or invalid upload data.'];
    }
    header("Location: client_details.php?client_id=$client_id"); // Redirect to show message
    exit;
}

// --- Handle Graph Deletion ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_graph'])) {
    $graph_id = filter_input(INPUT_POST, 'graph_id', FILTER_VALIDATE_INT);
    $image_url_relative = $_POST['image_url'] ?? null; // The relative path stored in DB

    if ($graph_id && $image_url_relative) {
        try {
            // Delete the graph record from the database
            $query_delete_graph = "DELETE FROM teeth_graph WHERE id = :graph_id AND patient_id = :patient_id"; // Add patient_id check for security
            $stmt_delete_graph = $conn->prepare($query_delete_graph);
            $stmt_delete_graph->bindParam(':graph_id', $graph_id, PDO::PARAM_INT);
            $stmt_delete_graph->bindParam(':patient_id', $client_id, PDO::PARAM_INT);

            if ($stmt_delete_graph->execute() && $stmt_delete_graph->rowCount() > 0) {
                // Construct the absolute path to the file
                $image_path_absolute = realpath(__DIR__ . '/' . $image_url_relative); // Assumes image_url is relative to this script's dir

                // Delete the image file from the server if it exists
                if ($image_path_absolute && file_exists($image_path_absolute)) {
                    if (unlink($image_path_absolute)) {
                        $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Teeth graph deleted successfully!'];
                    } else {
                        // DB record deleted, but file deletion failed. Log this.
                        $_SESSION['flash_message'] = ['type' => 'warning', 'text' => 'Graph record deleted, but failed to delete the image file. Please check server permissions.'];
                        // error_log("Failed to delete graph file: " . $image_path_absolute);
                    }
                } else {
                    $_SESSION['flash_message'] = ['type' => 'success', 'text' => 'Graph record deleted. Image file not found or path incorrect.'];
                    // error_log("Graph file not found for deletion: " . $image_url_relative);
                }
            } else {
                $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Failed to delete the graph record from the database or graph not found.'];
            }
        } catch (PDOException $e) {
            $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Database error deleting graph.'];
            // error_log("Graph Deletion DB Error: " . $e->getMessage());
        }
    } else {
        $_SESSION['flash_message'] = ['type' => 'danger', 'text' => 'Invalid data provided for graph deletion.'];
    }
    header("Location: client_details.php?client_id=$client_id"); // Redirect to show message
    exit;
}

// --- Display Flash Message ---
$flash_message = '';
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    unset($_SESSION['flash_message']); // Clear message after displaying
}

// Function to get status badge class (copied from manage appointments)
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
    <h2 class="m-0"><i class="fas fa-user-circle me-2 text-primary"></i> <?php echo $page_title; ?></h2>
    <div>
        <a href="client_history.php" class="btn btn-secondary me-2"><i class="fas fa-arrow-left me-1"></i> Back to
            Search</a>
        <a href="edit_patient.php?id=<?php echo $client_id; ?>" class="btn btn-warning"><i class="fas fa-edit me-1"></i>
            Edit Patient</a>
    </div>
</div>

<!-- Display Errors / Messages -->
<?php if ($flash_message): ?>
    <div class="alert alert-<?php echo htmlspecialchars($flash_message['type']); ?> alert-dismissible fade show"
        role="alert">
        <?php echo htmlspecialchars($flash_message['text']); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>
<?php if ($error_message): // Show fetch error only if no flash message ?>
    <div class="alert alert-danger"><?php echo $error_message; ?></div>
<?php endif; ?>


<?php if ($client && !$error_message): // Only display content if client was found and no fetch error ?>
    <div class="row">
        <!-- Left Column: Client Info & Stats -->
        <div class="col-lg-4 mb-4">
            <!-- Client Information Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-id-card me-2"></i> Client Information</h5>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-5"><i class="fas fa-user fa-fw me-1 text-muted"></i> Name:</dt>
                        <dd class="col-sm-7">
                            <?php echo htmlspecialchars($client['first_name'] . ' ' . $client['last_name']); ?></dd>

                        <dt class="col-sm-5"><i class="fas fa-phone fa-fw me-1 text-muted"></i> Contact:</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($client['contact_number'] ?: 'N/A'); ?></dd>

                        <dt class="col-sm-5"><i class="fas fa-map-marker-alt fa-fw me-1 text-muted"></i> Address:</dt>
                        <dd class="col-sm-7"><?php echo htmlspecialchars($client['address'] ?: 'N/A'); ?></dd>

                        <dt class="col-sm-5"><i class="fas fa-calendar-alt fa-fw me-1 text-muted"></i> Registered:</dt>
                        <dd class="col-sm-7">
                            <?php echo htmlspecialchars(date('M j, Y', strtotime($client['created_at']))); ?></dd>
                    </dl>
                </div>
            </div>

            <!-- Financial Summary Card -->
            <div class="card shadow-sm text-center">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-dollar-sign me-2 text-success"></i> Financial Summary</h5>
                </div>
                <div class="card-body">
                    <h6 class="text-muted">Total Spent (Completed)</h6>
                    <p class="h2 fw-bold text-success">$<?php echo number_format($total_spent ?? 0, 2); ?></p>
                    <!-- Add more stats if needed, e.g., pending amount -->
                </div>
            </div>
        </div>

        <!-- Right Column: Appointments & Graphs -->
        <div class="col-lg-8 mb-4">
            <!-- Appointment History Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-history me-2"></i> Appointment History</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($appointments)): ?>
                        <p class="text-muted text-center">No appointment history found for this client.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th>Date</th>
                                        <th>Doctor</th>
                                        <th>Service</th>
                                        <th>Status</th>
                                        <th>Price</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="text-center">
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr>
                                            <td class="text-nowrap">
                                                <?php echo htmlspecialchars(date('M j, Y h:i A', strtotime($appointment['appointment_date']))); ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($appointment['doctor_name']); ?></td>
                                            <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                            <td>
                                                <span class="badge <?php echo getStatusBadgeClass($appointment['status']); ?>">
                                                    <?php echo ucfirst(htmlspecialchars($appointment['status'])); ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($appointment['service_price'] ?? 0, 2); ?></td>
                                            <td>
                                                <a href="view_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                                    class="btn btn-outline-info btn-sm" data-bs-toggle="tooltip"
                                                    title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <!-- Add edit link if needed -->
                                                <a href="edit_appointment.php?id=<?php echo $appointment['appointment_id']; ?>"
                                                    class="btn btn-outline-warning btn-sm" data-bs-toggle="tooltip" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Teeth Graphs Card -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-tooth me-2"></i> Teeth Graphs</h5>
                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal"
                        data-bs-target="#uploadGraphModal">
                        <i class="fas fa-upload me-1"></i> Upload New Graph
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($teeth_graphs)): ?>
                        <p class="text-muted text-center">No teeth graphs uploaded for this client.</p>
                    <?php else: ?>
                        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 g-3">
                            <?php foreach ($teeth_graphs as $graph): ?>
                                <div class="col">
                                    <div class="card h-100 shadow-sm">
                                        <a href="<?php echo htmlspecialchars($graph['image_url']); ?>" data-bs-toggle="tooltip"
                                            title="Click to view larger" target="_blank">
                                            <img src="<?php echo htmlspecialchars($graph['image_url']); ?>" class="card-img-top"
                                                alt="Teeth Graph" style="aspect-ratio: 4/3; object-fit: cover;">
                                        </a>
                                        <div class="card-body pb-2">
                                            <p class="card-text small text-muted">
                                                <?php echo htmlspecialchars($graph['description'] ?: 'No description'); ?></p>
                                        </div>
                                        <div class="card-footer bg-white border-0 pt-0 text-end">
                                            <form action="client_details.php?client_id=<?php echo $client_id; ?>" method="POST"
                                                class="d-inline"
                                                onsubmit="return confirm('Are you sure you want to delete this graph?');">
                                                <input type="hidden" name="graph_id" value="<?php echo $graph['id']; ?>">
                                                <input type="hidden" name="image_url"
                                                    value="<?php echo htmlspecialchars($graph['image_url']); ?>">
                                                <button type="submit" name="delete_graph" class="btn btn-outline-danger btn-sm"
                                                    data-bs-toggle="tooltip" title="Delete Graph">
                                                    <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div> <!-- /col-lg-8 -->
    </div> <!-- /row -->

    <!-- Upload New Teeth Graph Modal -->
    <div class="modal fade" id="uploadGraphModal" tabindex="-1" aria-labelledby="uploadGraphModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="client_details.php?client_id=<?php echo $client_id; ?>" method="POST"
                    enctype="multipart/form-data">
                    <input type="hidden" name="upload_graph" value="1">
                    <div class="modal-header">
                        <h5 class="modal-title" id="uploadGraphModalLabel"><i class="fas fa-upload me-2"></i> Upload New
                            Teeth Graph</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description">
                            <small class="text-muted">Optional description for the graph.</small>
                        </div>
                        <div class="mb-3">
                            <label for="image" class="form-label">Image File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" id="image" name="image"
                                accept="image/jpeg,image/png,image/gif,image/webp" required>
                            <small class="text-muted">Allowed types: JPG, PNG, GIF, WEBP. Max size: 5MB.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times me-1"></i> Cancel</button>
                        <button type="submit" class="btn btn-primary"><i class="fas fa-upload me-1"></i> Upload</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<?php endif; // End check for client && !$error_message ?>

<?php
include '../includes/footer.php'; // Include the shared footer
?>