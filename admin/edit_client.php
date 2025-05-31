<?php

include '../config/db.php';



// Initialize variables
$client_id = null;
$client = null;
$form_data = [];
$message = '';
$message_class = 'alert-info';

// 1. Get Client ID from URL and Validate
if (!isset($_GET['id']) || !($client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT)) || $client_id <= 0) {
    $message = "Invalid or missing client ID.";
    $message_class = 'alert-danger';
    $page_title = 'Edit Client Information - Error';
} else {
    // 2. Handle Form Submission (POST Request) - BEFORE including header
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get form data directly without validation
        $form_data = $_POST;
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $contact_number = trim($_POST['contact_number']);
        $address = trim($_POST['address']);
        $dental_history = trim($_POST['dental_history']);

        // Basic check for required fields
        if (empty($first_name) || empty($last_name) || empty($contact_number)) {
            $message = "First Name, Last Name, and Contact Number are required.";
            $message_class = 'alert-danger';
        } else {
            // Proceed with Update
            try {
                $query = "UPDATE patients SET
                            first_name = :first_name,
                            last_name = :last_name,
                            contact_number = :contact_number,
                            address = :address,
                            dental_history = :dental_history
                          WHERE id = :client_id";

                $stmt = $conn->prepare($query);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':contact_number', $contact_number);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':dental_history', $dental_history);
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Success - Set flash message and redirect
                    $_SESSION['flash_message'] = "Client information updated successfully!";
                    $_SESSION['flash_message_class'] = 'alert-success';
                    header("Location: client_details.php?client_id=" . $client_id);
                    exit;
                } else {
                    $message = "Failed to update client information. Database error.";
                    $message_class = 'alert-danger';
                }
            } catch (PDOException $e) {
                $message = "Database error updating client: " . $e->getMessage();
                $message_class = 'alert-danger';
            }
        }
    }

    // 3. Fetch Client Data (for both GET requests and failed POST requests)
    try {
        $query = "SELECT * FROM patients WHERE id = :client_id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
        $stmt->execute();
        $client = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($client) {
            $page_title = "Edit Client: " . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);
            // If form_data is empty (GET request), populate with client data
            if (empty($form_data)) {
                $form_data = $client;
            }
        } else {
            $message = "Client with ID $client_id not found.";
            $message_class = 'alert-warning';
            $page_title = 'Edit Client Information - Not Found';
        }
    } catch (PDOException $e) {
        $message = "Database error fetching client details: " . $e->getMessage();
        $message_class = 'alert-danger';
        $page_title = 'Edit Client Information - Error';
    }
}

// NOW include the header after all processing is done
include '../includes/header.php';
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
    <?php if ($client_id && $client): ?>
        <a href="client_details.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Client Details
        </a>
    <?php endif; ?>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo htmlspecialchars($message_class); ?> alert-dismissible fade show" role="alert"
        data-auto-dismiss="7000">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($client): ?>
    <!-- Edit Client Form Card -->
    <div class="card shadow-sm">
        <div class="card-header">
            <i class="fas fa-user-edit me-2"></i> Modify Client Information
        </div>
        <div class="card-body">
            <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $client_id; ?>" method="POST">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="first_name" class="form-label">First Name <span class="text-danger">*</span></label>
                        <input type="text" name="first_name" id="first_name" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['first_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="last_name" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['last_name'] ?? ''); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contact_number" class="form-label">Contact Number <span
                                class="text-danger">*</span></label>
                        <input type="tel" name="contact_number" id="contact_number" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['contact_number'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" name="address" id="address" class="form-control"
                            value="<?php echo htmlspecialchars($form_data['address'] ?? ''); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="dental_history" class="form-label">Dental History</label>
                    <textarea name="dental_history" id="dental_history" class="form-control"
                        rows="3"><?php echo htmlspecialchars($form_data['dental_history'] ?? ''); ?></textarea>
                </div>

                <?php if (!empty($client['user_id'])): ?>
                    <?php
                    $user_email_query = $conn->prepare("SELECT email FROM users WHERE id = :user_id");
                    $user_email_query->execute([':user_id' => $client['user_id']]);
                    $user_email = $user_email_query->fetchColumn();
                    if ($user_email):
                        ?>
                        <div class="mb-3">
                            <label class="form-label">Linked Account Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" readonly
                                disabled>
                            <small class="text-muted">Email can be changed via User Management if this patient is also a system
                                user.</small>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

                <div class="d-flex justify-content-end mt-3">
                    <a href="client_details.php?client_id=<?php echo $client_id; ?>" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

<?php else: ?>
    <?php if (empty($message)): ?>
        <div class="alert alert-warning">Client data could not be loaded. Please go back and try again.</div>
    <?php endif; ?>
    <a href="client_history.php" class="btn btn-secondary">
        <i class="fas fa-search me-2"></i> Back to Client Search
    </a>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>