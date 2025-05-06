<?php
include '../config/db.php'; // Include database connection
include '../includes/header.php'; // Include the shared header with the sidebar

$message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name']);
    $last_name = trim($_POST['last_name']);
    $contact_number = trim($_POST['contact_number']);
    $address = trim($_POST['address']);
    $dental_history = trim($_POST['dental_history']);

    // Validate required fields
    if (empty($first_name) || empty($last_name)) {
        $message = "First name and last name are required.";
    } else {
        try {
            // Insert new patient into the database
            $query = "
                INSERT INTO patients (user_id, first_name, last_name, contact_number, address, dental_history)
                VALUES (:user_id, :first_name, :last_name, :contact_number, :address, :dental_history)
            ";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT); // Assuming receptionist's user ID is stored in the session
            $stmt->bindParam(':first_name', $first_name, PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $last_name, PDO::PARAM_STR);
            $stmt->bindParam(':contact_number', $contact_number, PDO::PARAM_STR);
            $stmt->bindParam(':address', $address, PDO::PARAM_STR);
            $stmt->bindParam(':dental_history', $dental_history, PDO::PARAM_STR);

            if ($stmt->execute()) {
                $message = "Patient added successfully!";
            } else {
                $message = "Failed to add the patient.";
            }
        } catch (PDOException $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Patient</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4 text-center">Add New Patient</h1>

        <!-- Success/Error Message -->
        <?php if ($message): ?>
            <div class="alert alert-info text-center"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <!-- Add Patient Form -->
        <form method="POST" action="">
            <div class="mb-3">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" class="form-control" id="first_name" name="first_name" required>
            </div>
            <div class="mb-3">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" class="form-control" id="last_name" name="last_name" required>
            </div>
            <div class="mb-3">
                <label for="contact_number" class="form-label">Contact Number</label>
                <input type="text" class="form-control" id="contact_number" name="contact_number">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Address</label>
                <textarea class="form-control" id="address" name="address" rows="3"></textarea>
            </div>
            <div class="mb-3">
                <label for="dental_history" class="form-label">Dental History</label>
                <textarea class="form-control" id="dental_history" name="dental_history" rows="3"></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Add Patient</button>
        </form>
    </div>
    <script src="../assets/js/script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>