<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Edit Client Information'; // Default title
include '../includes/header.php';

// Check if the user has permission (e.g., admin or receptionist)
if (!in_array($_SESSION['role'], ['admin', 'receptionist'])) { // Adjust roles as needed
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to perform this action.</div>';
    include '../includes/footer.php';
    exit;
}

$client_id = null;
$client = null;
$message = '';
$message_class = 'alert-info';

// 1. Get Client ID from URL and Validate
if (!isset($_GET['id']) || !($client_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT))) {
    $message = "Invalid or missing client ID.";
    $message_class = 'alert-danger';
} else {
    // 2. Handle Form Submission (POST Request)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and validate inputs
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        $contact_number = filter_input(INPUT_POST, 'contact_number', FILTER_SANITIZE_STRING); // Basic sanitize, more specific validation might be needed
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING, ['flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH]);
        // Note: Editing the linked user email might be complex and is omitted here for simplicity.
        // It might require checking uniqueness in the 'users' table if the patient is linked.

        // Basic Validation
        if (empty($first_name) || empty($last_name) || empty($contact_number)) { // Add more specific validation as needed
            $message = "Invalid input. Please check First Name, Last Name, and Contact Number.";
            $message_class = 'alert-danger';
            // Re-fetch client data to display the form again with errors
            try {
                $query = "SELECT * FROM patients WHERE id = :client_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt->execute();
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    $page_title = "Edit Client: " . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);
                } else {
                    // Should not happen if ID was valid initially, but handle defensively
                    $message = "Client not found.";
                    $message_class = 'alert-danger';
                    $client = null;
                }
            } catch (PDOException $e) {
                $message = "Database error fetching client details: " . $e->getMessage();
                $message_class = 'alert-danger';
                $client = null;
            }

        } else {
            // Proceed with Update
            try {
                $query = "UPDATE patients SET
                            first_name = :first_name,
                            last_name = :last_name,
                            contact_number = :contact_number,
                            address = :address
                          WHERE id = :client_id";

                $stmt = $conn->prepare($query);
                $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
                $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
                $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
                $stmt->bindParam(':address', $address, PDO::PARAM_STR);
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    // Success - Set flash message and redirect back to details page
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
            // If update failed, re-fetch data to show form again
            try {
                $query = "SELECT * FROM patients WHERE id = :client_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
                $stmt->execute();
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($client) {
                    $page_title = "Edit Client: " . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);
                }
            } catch (PDOException $e) { /* Error already handled above */
            }
        }
    } else {
        // 3. Fetch Client Data for Initial Form Display (GET Request)
        try {
            $query = "SELECT * FROM patients WHERE id = :client_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':client_id', $client_id, PDO::PARAM_INT);
            $stmt->execute();
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($client) {
                $page_title = "Edit Client: " . htmlspecialchars($client['first_name'] . ' ' . $client['last_name']);
            } else {
                $message = "Client with ID $client_id not found.";
                $message_class = 'alert-warning';
                $client = null; // Ensure client is null
            }
        } catch (PDOException $e) {
            $message = "Database error fetching client details: " . $e->getMessage();
            $message_class = 'alert-danger';
            $client = null; // Ensure client is null on error
        }
    }
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
    <?php if ($client_id): // Show back button only if ID is valid ?>
        <a href="client_details.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i> Back to Client Details
        </a>
    <?php endif; ?>
</div>

<!-- Display Message -->
<?php if ($message): ?>
    <div class="alert <?php echo $message_class; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
<?php endif; ?>

<?php if ($client): // Only display form if client was found ?>
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
                            value="<?php echo htmlspecialchars($client['first_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="last_name" class="form-label">Last Name <span class="text-danger">*</span></label>
                        <input type="text" name="last_name" id="last_name" class="form-control"
                            value="<?php echo htmlspecialchars($client['last_name']); ?>" required>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="contact_number" class="form-label">Contact Number <span
                                class="text-danger">*</span></label>
                        <input type="tel" name="contact_number" id="contact_number" class="form-control"
                            value="<?php echo htmlspecialchars($client['contact_number']); ?>" required>
                        <!-- Consider adding pattern attribute for specific phone formats -->
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="address" class="form-label">Address</label>
                        <input type="text" name="address" id="address" class="form-control"
                            value="<?php echo htmlspecialchars($client['address']); ?>">
                    </div>
                </div>
                <!-- If patient is linked to a user, you might display the email read-only or provide a separate mechanism to change it -->
                <?php /*
           if (!empty($client['user_id'])) {
               // Fetch user email if needed
               $user_email_query = $conn->prepare("SELECT email FROM users WHERE id = :user_id");
               $user_email_query->execute([':user_id' => $client['user_id']]);
               $user_email = $user_email_query->fetchColumn();
               if ($user_email) {
                   echo '<div class="mb-3">';
                   echo '<label class="form-label">Linked Account Email</label>';
                   echo '<input type="email" class="form-control" value="' . htmlspecialchars($user_email) . '" readonly disabled>';
                   echo '<small class="text-muted">Email can be changed via User Management.</small>';
                   echo '</div>';
               }
           }
           */ ?>

                <div class="d-flex justify-content-end mt-3">
                    <a href="client_details.php?client_id=<?php echo $client_id; ?>" class="btn btn-secondary me-2">
                        <i class="fas fa-times me-2"></i>Cancel
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div> <!-- /card-body -->
    </div> <!-- /card -->

<?php else: ?>
    <?php if (empty($message)): // Show default message if no specific error occurred but client not found ?>
        <div class="alert alert-warning">Client data could not be loaded. Please go back and try again.</div>
    <?php endif; ?>
    <a href="client_history.php" class="btn btn-secondary">
        <i class="fas fa-search me-2"></i> Back to Client Search
    </a>
<?php endif; // End check for if ($client) ?>


<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>