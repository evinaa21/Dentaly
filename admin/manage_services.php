<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Manage Services';
include '../includes/header.php'; // Include the header with the sidebar

// Check if the user is actually an admin - redirect if not
if ($_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = '';
$message_class = 'alert-info'; // Default message class

// Handle Add Service
if (isset($_POST['add_service'])) {
    $service_name = filter_input(INPUT_POST, 'service_name', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT); // Validate as float

    if (empty($service_name) || $price === false || $price < 0) { // Basic validation
        $message = "Invalid input. Please check service name and price (must be a non-negative number).";
        $message_class = 'alert-danger';
    } else {
        try {
            $query = "INSERT INTO services (service_name, description, price) VALUES (:name, :desc, :price)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $service_name, PDO::PARAM_STR);
            $stmt->bindParam(':desc', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price); // PDO figures out float/double type

            if ($stmt->execute()) {
                $message = "Service '$service_name' added successfully!";
                $message_class = 'alert-success';
            } else {
                $message = "Failed to add service. Database error.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Database error adding service: " . $e->getMessage();
            $message_class = 'alert-danger';
        }
    }
}

// Handle Delete Service
if (isset($_POST['delete_service'])) { // Changed to POST
    $id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

    if (!$id) {
        $message = "Invalid service ID for deletion.";
        $message_class = 'alert-danger';
    } else {
        try {
            // Check if the service is used in appointments
            $check_query = "SELECT COUNT(*) FROM appointments WHERE service_id = :id";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $check_stmt->execute();
            $isReferenced = $check_stmt->fetchColumn();

            if ($isReferenced > 0) {
                $message = "Error: Cannot delete service (ID: $id). It is currently linked to $isReferenced appointment(s).";
                $message_class = 'alert-warning';
            } else {
                // Proceed with deletion
                $query = "DELETE FROM services WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $message = "Service (ID: $id) deleted successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Failed to delete service (ID: $id).";
                    $message_class = 'alert-danger';
                }
            }
        } catch (PDOException $e) {
            $message = "Database error deleting service: " . $e->getMessage();
            $message_class = 'alert-danger';
        }
    }
}

// Handle Edit Service
if (isset($_POST['edit_service'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $service_name = filter_input(INPUT_POST, 'service_name', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
    $description = filter_input(INPUT_POST, 'description', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);

    if (!$id || empty($service_name) || $price === false || $price < 0) {
        $message = "Invalid input for editing service ID: $id.";
        $message_class = 'alert-danger';
    } else {
        try {
            $query = "UPDATE services SET service_name = :name, description = :desc, price = :price WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $service_name, PDO::PARAM_STR);
            $stmt->bindParam(':desc', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "Service '$service_name' (ID: $id) updated successfully!";
                $message_class = 'alert-success';
            } else {
                $message = "Failed to update service (ID: $id).";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            $message = "Database error updating service: " . $e->getMessage();
            $message_class = 'alert-danger';
        }
    }
}

// Fetch all services AFTER potential modifications
try {
    $services = $conn->query("SELECT * FROM services ORDER BY service_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $message = "Error fetching services: " . $e->getMessage();
    $message_class = 'alert-danger';
    $services = [];
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

<!-- Add Service Form Card (Collapsible) -->
<div class="accordion mb-4" id="addServiceAccordion">
    <div class="accordion-item card shadow-sm border-0">
        <h2 class="accordion-header card-header bg-light border-bottom-0" id="headingAdd">
            <button class="accordion-button collapsed bg-transparent text-dark fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseAdd" aria-expanded="false"
                aria-controls="collapseAdd">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Service
            </button>
        </h2>
        <div id="collapseAdd" class="accordion-collapse collapse" aria-labelledby="headingAdd"
            data-bs-parent="#addServiceAccordion">
            <div class="accordion-body card-body pt-2">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_service_name" class="form-label">Service Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="service_name" id="add_service_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_price" class="form-label">Price ($) <span
                                    class="text-danger">*</span></label>
                            <input type="number" name="price" id="add_price" class="form-control" step="0.01" min="0"
                                required placeholder="e.g., 75.50">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea name="description" id="add_description" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary" name="add_service">
                            <i class="fas fa-plus-circle me-2"></i>Add Service
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Services Table Card -->
<div class="card shadow-sm">
    <div class="card-header">
        <i class="fas fa-briefcase-medical me-2"></i> Services List
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">ID</th>
                        <th>Service Name</th>
                        <th>Description</th>
                        <th class="text-end">Price</th> <!-- Align price right -->
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">No services found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td class="text-center"><?php echo $service['id']; ?></td>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td class="text-muted small"><?php echo htmlspecialchars($service['description'] ?: '-'); ?>
                                </td> <!-- Lighter text for description -->
                                <td class="text-end">$<?php echo number_format($service['price'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $service['id']; ?>" data-bs-toggle="tooltip"
                                        title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <!-- Delete Form -->
                                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>"
                                        class="d-inline"
                                        onsubmit="return confirm('Are you sure you want to delete service \'<?php echo htmlspecialchars($service['service_name']); ?>\'?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $service['id']; ?>">
                                        <button type="submit" name="delete_service" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="tooltip" title="Delete">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div> <!-- /card-body -->
</div> <!-- /card -->


<!-- Edit Modals (One per service) -->
<?php foreach ($services as $service): ?>
    <div class="modal fade" id="editModal<?php echo $service['id']; ?>" tabindex="-1"
        aria-labelledby="editModalLabel<?php echo $service['id']; ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editModalLabel<?php echo $service['id']; ?>"><i
                                class="fas fa-edit me-2"></i>Edit Service:
                            <?php echo htmlspecialchars($service['service_name']); ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                        <div class="row">
                            <div class="col-md-8 mb-3">
                                <label for="edit_service_name_<?php echo $service['id']; ?>" class="form-label">Service Name
                                    <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" id="edit_service_name_<?php echo $service['id']; ?>"
                                    class="form-control" value="<?php echo htmlspecialchars($service['service_name']); ?>"
                                    required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="edit_price_<?php echo $service['id']; ?>" class="form-label">Price ($) <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="price" id="edit_price_<?php echo $service['id']; ?>"
                                    class="form-control" step="0.01" min="0"
                                    value="<?php echo htmlspecialchars($service['price']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description_<?php echo $service['id']; ?>"
                                class="form-label">Description</label>
                            <textarea name="description" id="edit_description_<?php echo $service['id']; ?>"
                                class="form-control"
                                rows="3"><?php echo htmlspecialchars($service['description']); ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i
                                class="fas fa-times me-2"></i>Cancel</button>
                        <button type="submit" name="edit_service" class="btn btn-primary"><i
                                class="fas fa-save me-2"></i>Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endforeach; ?>

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