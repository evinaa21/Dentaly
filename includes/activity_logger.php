<?php
// Create includes/activity_logger.php
class ActivityLogger
{
    private static $conn;

    public static function init($connection)
    {
        self::$conn = $connection;
    }

    public static function log($user_id, $action, $details = null)
    {
        try {
            $stmt = self::$conn->prepare("
                INSERT INTO activity_logs (user_id, action, details, ip_address, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id,
                $action,
                json_encode($details),
                $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("Activity logging failed: " . $e->getMessage());
        }
    }
}

/**
 * Logs an activity.
 *
 * @param PDO $conn The database connection object.
 * @param int $user_id The ID of the user performing the action.
 * @param string $action The action performed (e.g., "created_appointment", "deleted_user").
 * @param string|null $details Additional details about the action.
 * @return bool True on success, false on failure.
 */
function log_activity(PDO $conn, int $user_id, string $action, ?string $details = null): bool
{
    try {
        // Consider adding IP address logging: $ip_address = $_SERVER['REMOTE_ADDR'];
        $sql = "INSERT INTO activity_logs (user_id, action, details) VALUES (:user_id, :action, :details)";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':action', $action, PDO::PARAM_STR);
        $stmt->bindParam(':details', $details, PDO::PARAM_STR);
        return $stmt->execute();
    } catch (PDOException $e) {
        // Log error: error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}
