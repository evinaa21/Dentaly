<?php
session_start();
header('Content-Type: application/json');

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

// Include database configuration
// Adjust the path if your db.php is located elsewhere relative to the ajax folder
if (file_exists('../config/db.php')) {
    include '../config/db.php';
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database configuration not found.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$notifications = [];
$unread_count = 0;

try {
    // Fetch unread notifications (e.g., latest 10)
    $stmt = $conn->prepare("SELECT id, message, link, created_at FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total unread count
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
    $stmt_count->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_count->execute();
    $unread_count = $stmt_count->fetchColumn();

    echo json_encode([
        'status' => 'success',
        'unread_count' => $unread_count,
        'items' => $notifications
    ]);

} catch (PDOException $e) {
    // Log error if needed: error_log("Fetch Notifications DB Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Could not fetch notifications.']);
}
?>