<?php
session_start();
include('../config/db.php');

// Set header to indicate JSON response
header('Content-Type: application/json');

// Default response structure
$response = ['status' => 'error', 'message' => 'Invalid request method.'];

// Define the base URL for your project (needed for absolute redirect URLs)
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$base_path = '/Dentaly/'; // Adjust if your project is in a different subfolder or root
$base_url = $protocol . $host . $base_path;


// Check if form data is submitted via POST
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;

    if (!$email || !$password) {
        $response['message'] = 'Email and password are required.';
    } else {
        try {
            // Prepare the query to fetch user data
            $query = "SELECT id, name, email, password, role FROM users WHERE email = :email LIMIT 1"; // Select necessary fields
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check if user exists and password matches
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID for security
                session_regenerate_id(true);

                // Store user info in session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_name'] = $user['name']; // Store name for display

                // Determine redirect URL based on role
                $redirect_url = $base_url . 'index.php'; // Default fallback
                switch ($user['role']) {
                    case 'admin':
                        $redirect_url = $base_url . 'admin/index.php';
                        break;
                    case 'doctor':
                        $redirect_url = $base_url . 'doctors/index.php';
                        break;
                    case 'receptionist': // Corrected typo if applicable
                        $redirect_url = $base_url . 'receptionist/index.php';
                        break;

                    // Add other roles as needed
                }

                // Set success response
                $response = [
                    'status' => 'success',
                    'message' => 'Login successful!',
                    'redirect' => $redirect_url
                ];

            } else {
                // Set error response for invalid credentials
                $response['message'] = 'Invalid email or password.';
            }
        } catch (PDOException $e) {
            // Handle potential database errors
            // error_log("Login DB Error: " . $e->getMessage()); // Log error for debugging
            $response['message'] = 'A database error occurred. Please try again later.';
        }
    }
}

// Send the JSON response
echo json_encode($response);
exit; // Terminate script after sending response
?>