<?php
include '../config/db.php'; // Include database connection
// Remove validation.php include since we deleted it

// Define page title *before* including header
$page_title = 'Manage Services';
include '../includes/header.php'; // Include the header with the sidebar

// Check if the user is actually an admin - redirect if not
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

$message = '';
$message_class = 'alert-info'; // Default message class

// Handle Add Service
if (isset($_POST['add_service'])) {
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $price_input = $_POST['price'];

    // Basic validation using PHP functions
    $price_valid = is_numeric($price_input) && floatval($price_input) > 0;

    if (empty($service_name) || !$price_valid) {
        $message = "Invalid input. Service Name is required and Price must be a valid positive number.";
        $message_class = 'alert-danger';
    } else {
        $price = floatval($price_input);
        try {
            $query = "INSERT INTO services (service_name, description, price) VALUES (:service_name, :description, :price)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':service_name', $service_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);

            if ($stmt->execute()) {
                $message = "Service added successfully!";
                $message_class = 'alert-success';
            } else {
                $message = "Failed to add service.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Integrity constraint violation (e.g., unique name)
                $message = "Error adding service: A service with this name might already exist.";
            } else {
                $message = "Error adding service: " . $e->getMessage();
            }
            $message_class = 'alert-danger';
        }
    }
}

// Handle Delete Service
if (isset($_POST['delete_service'])) {
    $id = filter_input(INPUT_POST, 'delete_id', FILTER_VALIDATE_INT);

    if (!$id) {
        $message = "Invalid service ID for deletion.";
        $message_class = 'alert-danger';
    } else {
        try {
            $query = "DELETE FROM services WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                if ($stmt->rowCount() > 0) {
                    $message = "Service deleted successfully!";
                    $message_class = 'alert-success';
                } else {
                    $message = "Service not found or already deleted.";
                    $message_class = 'alert-warning';
                }
            } else {
                $message = "Failed to delete service.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Foreign key constraint
                $message = "Cannot delete service. It is currently associated with existing appointments. Please remove or reassign those appointments first.";
            } else {
                $message = "Error deleting service: " . $e->getMessage();
            }
            $message_class = 'alert-danger';
        }
    }
}

// Handle Edit Service
if (isset($_POST['edit_service'])) {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $service_name = trim($_POST['service_name']);
    $description = trim($_POST['description']);
    $price_input = $_POST['price'];

    // Basic validation using PHP functions
    $price_valid = is_numeric($price_input) && floatval($price_input) > 0;

    if (!$id || empty($service_name) || !$price_valid) {
        $message = "Invalid input for editing service. Service Name is required and Price must be a valid positive number.";
        $message_class = 'alert-danger';
    } else {
        $price = floatval($price_input);
        try {
            $query = "UPDATE services SET service_name = :service_name, description = :description, price = :price WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':service_name', $service_name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $message = "Service updated successfully!";
                $message_class = 'alert-success';
            } else {
                $message = "Failed to update service.";
                $message_class = 'alert-danger';
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $message = "Error updating service: A service with this name might already exist.";
            } else {
                $message = "Error updating service: " . $e->getMessage();
            }
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
    <div class="alert <?php echo $message_class; ?> alert-dismissible fade show" role="alert" data-auto-dismiss="7000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<!-- Add Service Form Card (Collapsible) -->
<div class="accordion mb-4" id="addServiceAccordion">
    <div class="accordion-item card shadow-sm border-0">
        <h2 class="accordion-header card-header bg-light border-bottom-0" id="headingAdd">
            <button class="accordion-button collapsed bg-transparent text-dark fw-semibold" type="button"
                data-bs-toggle="collapse" data-bs-target="#collapseAdd"
                aria-expanded="<?php echo (isset($_POST['add_service']) && !empty($message) && $message_class === 'alert-danger') ? 'true' : 'false'; ?>"
                aria-controls="collapseAdd">
                <i class="fas fa-plus-circle me-2 text-primary"></i> Add New Service
            </button>
        </h2>
        <div id="collapseAdd" class="accordion-collapse collapse <?php if (isset($_POST['add_service']) && !empty($message) && $message_class === 'alert-danger')
            echo 'show'; ?>" aria-labelledby="headingAdd" data-bs-parent="#addServiceAccordion">
            <div class="accordion-body card-body pt-2">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="add_service_name" class="form-label">Service Name <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="service_name" id="add_service_name" class="form-control" required
                                value="<?php echo isset($_POST['service_name']) && $message_class === 'alert-danger' ? htmlspecialchars($_POST['service_name']) : ''; ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="add_price" class="form-label">Price ($) <span
                                    class="text-danger">*</span></label>
                            <input type="number" name="price" id="add_price" class="form-control" step="0.01" min="0"
                                required
                                value="<?php echo isset($_POST['price']) && $message_class === 'alert-danger' ? htmlspecialchars($_POST['price']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="add_description" class="form-label">Description</label>
                        <textarea name="description" id="add_description" class="form-control"
                            rows="2"><?php echo isset($_POST['description']) && $message_class === 'alert-danger' ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn btn-primary" name="add_service">
                            <i class="fas fa-plus me-2"></i>Add Service
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
                        <th>Service Name</th>
                        <th>Description</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="4" class="text-center text-muted p-4">No services found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($service['service_name']); ?></td>
                                <td><?php echo nl2br(htmlspecialchars($service['description'] ?: 'N/A')); ?></td>
                                <td class="text-end">$<?php echo number_format($service['price'], 2); ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-outline-warning me-1" data-bs-toggle="modal"
                                        data-bs-target="#editModal<?php echo $service['id']; ?>" data-bs-tooltip="tooltip"
                                        title="Edit Service">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST"
                                        class="d-inline confirm-delete-form">
                                        <input type="hidden" name="delete_id" value="<?php echo $service['id']; ?>">
                                        <button type="submit" name="delete_service"
                                            class="btn btn-sm btn-outline-danger confirm-delete" data-bs-tooltip="tooltip"
                                            title="Delete Service"
                                            data-message="Are you sure you want to delete service '<?php echo htmlspecialchars($service['service_name']); ?>'? This may affect existing appointments.">
                                            <i class="fas fa-trash"></i>
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
                            <?php echo htmlspecialchars($service['service_name']); ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" value="<?php echo $service['id']; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="edit_service_name_<?php echo $service['id']; ?>" class="form-label">Service Name
                                    <span class="text-danger">*</span></label>
                                <input type="text" name="service_name" id="edit_service_name_<?php echo $service['id']; ?>"
                                    class="form-control" value="<?php echo htmlspecialchars($service['service_name']); ?>"
                                    required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="edit_price_<?php echo $service['id']; ?>" class="form-label">Price ($) <span
                                        class="text-danger">*</span></label>
                                <input type="number" name="price" id="edit_price_<?php echo $service['id']; ?>"
                                    class="form-control" value="<?php echo htmlspecialchars($service['price']); ?>"
                                    step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_description_<?php echo $service['id']; ?>"
                                class="form-label">Description</label>
                            <textarea name="description" id="edit_description_<?php echo $service['id']; ?>"
                                class="form-control"
                                rows="2"><?php echo htmlspecialchars($service['description']); ?></textarea>
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
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-tooltip="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>

<script>
    // Custom confirm for delete forms
    const confirmDeleteForms = document.querySelectorAll('.confirm-delete-form');
    confirmDeleteForms.forEach(form => {
        form.addEventListener('submit', function (event) {
            const button = form.querySelector('.confirm-delete');
            const message = button.dataset.message || 'Are you sure you want to delete this item?';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
</script>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>