<?php
include '../config/db.php'; // Include database connection
include '../includes/header.php';

// Ensure a valid patient ID is provided
if (!isset($_GET['patient_id']) || empty($_GET['patient_id'])) {
    die("Invalid patient ID.");
}

$patient_id = intval($_GET['patient_id']);
$doctor_id = $_SESSION['user_id']; // Assuming the logged-in doctor's ID is stored in the session
$patient = null;
$appointments = [];
$teeth_graphs = [];

// Fetch Patient Details (only if assigned to the logged-in doctor)
$query = "
    SELECT p.* 
    FROM patients p
    JOIN appointments a ON p.id = a.patient_id
    WHERE p.id = :patient_id AND a.doctor_id = :doctor_id
    LIMIT 1;
";
$stmt = $conn->prepare($query);
$stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
$stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
$stmt->execute();
$patient = $stmt->fetch(PDO::FETCH_ASSOC);

if ($patient) {
    // Fetch Appointment History for this patient with the logged-in doctor
    $query = "
        SELECT 
            a.id AS appointment_id,
            a.appointment_date,
            a.status,
            s.service_name,
            s.price AS service_price
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        WHERE a.patient_id = :patient_id AND a.doctor_id = :doctor_id
        ORDER BY a.appointment_date DESC;
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->bindParam(':doctor_id', $doctor_id, PDO::PARAM_INT);
    $stmt->execute();
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Teeth Graphs for this patient
    $query = "
        SELECT 
            tg.id,
            tg.description,
            tg.image_url
        FROM teeth_graph tg
        WHERE tg.patient_id = :patient_id
        ORDER BY tg.id DESC;
    ";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
    $stmt->execute();
    $teeth_graphs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    die("Patient not found or not assigned to you.");
}

// Handle Graph Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_graph'])) {
    $graph_id = intval($_POST['graph_id']);
    $description = $_POST['description'];
    $image = $_FILES['image'];

    // Check if a new image is uploaded
    if (!empty($image['name'])) {
        $upload_dir = '../uploads/teeth_graphs/';
        $file_name = uniqid() . '_' . basename($image['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($image['tmp_name'], $file_path)) {
            // Update the graph with a new image
            $query = "UPDATE teeth_graph SET description = :description, image_url = :image_url WHERE id = :graph_id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':image_url', $file_path, PDO::PARAM_STR);
        } else {
            echo "<script>alert('Failed to upload the new image.');</script>";
            exit;
        }
    } else {
        // Update only the description
        $query = "UPDATE teeth_graph SET description = :description WHERE id = :graph_id";
        $stmt = $conn->prepare($query);
    }

    $stmt->bindParam(':description', $description, PDO::PARAM_STR);
    $stmt->bindParam(':graph_id', $graph_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        header("Location: patient_details.php?patient_id=$patient_id");
        exit;
    } else {
        echo "<script>alert('Failed to update the graph.');</script>";
    }
}

// Handle Graph Deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_graph'])) {
    $graph_id = intval($_POST['graph_id']);
    $image_url = $_POST['image_url'];

    // Delete the graph record
    $query = "DELETE FROM teeth_graph WHERE id = :graph_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':graph_id', $graph_id, PDO::PARAM_INT);

    if ($stmt->execute()) {
        // Delete the image file
        if (file_exists($image_url)) {
            unlink($image_url);
        }
        header("Location: patient_details.php?patient_id=$patient_id");
        exit;
    } else {
        echo "<script>alert('Failed to delete the graph.');</script>";
    }
}

// Handle New Graph Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_graph'])) {
    $description = $_POST['description'];
    $image = $_FILES['image'];

    // Check if an image is uploaded
    if (!empty($image['name'])) {
        $upload_dir = '../uploads/teeth_graphs/';
        $file_name = uniqid() . '_' . basename($image['name']);
        $file_path = $upload_dir . $file_name;

        if (move_uploaded_file($image['tmp_name'], $file_path)) {
            // Insert the new graph
            $query = "INSERT INTO teeth_graph (patient_id, description, image_url) VALUES (:patient_id, :description, :image_url)";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':patient_id', $patient_id, PDO::PARAM_INT);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':image_url', $file_path, PDO::PARAM_STR);

            if ($stmt->execute()) {
                header("Location: patient_details.php?patient_id=$patient_id");
                exit;
            } else {
                echo "<script>alert('Failed to upload the new graph.');</script>";
            }
        } else {
            echo "<script>alert('Failed to upload the image.');</script>";
        }
    } else {
        echo "<script>alert('Please select an image to upload.');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <div class="container mt-5">
        <h1 class="mb-4">Patient Details</h1>

        <!-- Patient Information -->
        <div class="card mb-4">
            <div class="card-header bg-secondary text-white">Patient Information</div>
            <div class="card-body">
                <p><strong>Name:</strong>
                    <?php echo htmlspecialchars($patient['first_name'] . ' ' . $patient['last_name']); ?></p>
                <p><strong>Contact:</strong> <?php echo htmlspecialchars($patient['contact_number']); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($patient['address']); ?></p>
            </div>
        </div>

        <!-- Appointment History -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">Appointment History</div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped text-center">

                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Service</th>
                                <th>Status</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $appointment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($appointment['appointment_date']))); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($appointment['service_name']); ?></td>
                                    <td>
                                        <span
                                            class="badge <?php echo $appointment['status'] === 'completed' ? 'bg-success' : ($appointment['status'] === 'canceled' ? 'bg-danger' : 'bg-warning'); ?>">
                                            <?php echo ucfirst($appointment['status']); ?>
                                        </span>
                                    </td>
                                    <td>$<?php echo number_format($appointment['service_price'], 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Teeth Graphs -->
        <div class="card mb-4">
            <div class="card-header bg-info text-white">Teeth Graphs</div>
            <div class="card-body">
                <div class="row">
                    <?php foreach ($teeth_graphs as $graph): ?>
                        <div class="col-md-4 mb-3">
                            <div class="card">
                                <img src="<?php echo htmlspecialchars($graph['image_url']); ?>" class="card-img-top"
                                    alt="Teeth Graph">
                                <div class="card-body">
                                    <p class="card-text"><?php echo htmlspecialchars($graph['description']); ?></p>
                                    <form action="patient_details.php?patient_id=<?php echo $patient_id; ?>" method="POST"
                                        enctype="multipart/form-data">
                                        <input type="hidden" name="graph_id" value="<?php echo $graph['id']; ?>">
                                        <div class="mb-3">
                                            <label for="description_<?php echo $graph['id']; ?>" class="form-label">Update
                                                Description</label>
                                            <input type="text" class="form-control"
                                                id="description_<?php echo $graph['id']; ?>" name="description"
                                                value="<?php echo htmlspecialchars($graph['description']); ?>" required>
                                        </div>
                                        <div class="mb-3">
                                            <label for="image_<?php echo $graph['id']; ?>" class="form-label">Replace Image
                                                (optional)</label>
                                            <input type="file" class="form-control" id="image_<?php echo $graph['id']; ?>"
                                                name="image" accept="image/*">
                                        </div>
                                        <button type="submit" name="update_graph"
                                            class="btn btn-warning btn-sm">Update</button>
                                    </form>
                                    <form action="patient_details.php?patient_id=<?php echo $patient_id; ?>" method="POST"
                                        onsubmit="return confirm('Are you sure you want to delete this graph?');">
                                        <input type="hidden" name="graph_id" value="<?php echo $graph['id']; ?>">
                                        <input type="hidden" name="image_url" value="<?php echo $graph['image_url']; ?>">
                                        <button type="submit" name="delete_graph"
                                            class="btn btn-danger btn-sm mt-2">Delete</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Upload New Teeth Graph -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">Upload New Teeth Graph</div>
            <div class="card-body">
                <form action="patient_details.php?patient_id=<?php echo $patient_id; ?>" method="POST"
                    enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <input type="text" class="form-control" id="description" name="description" required>
                    </div>
                    <div class="mb-3">
                        <!-- Label for the file input field -->
                        <label for="image" class="form-label">Image</label>
                        <!-- File input control for uploading images -->
                        <!-- class="form-control" applies Bootstrap styling -->
                        <!-- id="image" links this input to the label above -->
                        <!-- name="image" is how PHP identifies this file in $_FILES['image'] -->
                        <!-- accept="image/*" restricts uploads to image files only -->
                        <!-- required makes this field mandatory before form submission -->
                        <input type="file" class="form-control" id="image" name="image" accept="image/*" required>
                    </div>
                    <!-- Submit button that triggers form submission -->
                    <!-- btn btn-primary gives it Bootstrap's blue button styling -->
                    <!-- name="upload_graph" helps PHP identify which form action to perform -->
                    <button type="submit" class="btn btn-primary" name="upload_graph">Upload</button>
                </form>
            </div>
        </div>
    </div>
    <!-- Loads custom JavaScript from the assets folder -->
    <script src="../assets/js/script.js"></script>
    <!-- Loads Bootstrap's JavaScript bundle from CDN (Content Delivery Network) -->
    <!-- This enables interactive features like dropdowns, modals, etc. -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html> <!-- Closing HTML tag - end of the document -->