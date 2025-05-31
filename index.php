<?php
// Start the session right at the beginning
// Sessions allow us to store user data across multiple pages
session_start();

// If user is already logged in, redirect them away from login page
// This prevents logged-in users from accessing the login page again
if (isset($_SESSION['role'])) {
    $redirect_url = 'index.php'; // Default fallback if role logic fails
    // Redirect users to different pages based on their roles
    switch ($_SESSION['role']) {
        case 'admin':
            $redirect_url = 'admin/index.php';
            break;
        case 'doctor':
            $redirect_url = 'doctors/index.php';
            break; // Adjust path if needed
        case 'patient':
            // This redirects patients to the receptionist page - might need to be updated
            $redirect_url = 'receptionist/index.php';
            break; // Adjust path if needed
        // Add other roles
    }
    // The header function sends a raw HTTP header to redirect the browser
    header("Location: " . $redirect_url);
    // The exit statement stops script execution to ensure the redirect happens
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8"> <!-- Defines the character encoding for the document -->
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <!-- Makes the page responsive on mobile devices -->
    <title>Dentaly Clinic – Login</title> <!-- Sets the page title shown in browser tabs -->
    <!-- Load Bootstrap CSS framework from a CDN (Content Delivery Network) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Load Font Awesome icons from a CDN -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Font smoothing & stack */
        /* Sets default font styling for the entire page and enables font smoothing */
        body {
            margin: 0;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            /* Smooth fonts in WebKit browsers like Chrome and Safari */
            -moz-osx-font-smoothing: grayscale;
            /* Smooth fonts in Firefox */
        }

        /* Dynamic Animated Gradient Background */
        /* Creates a centered, full-height design with animated color background */
        body.login-page {
            display: flex;
            /* Uses flexbox for centering */
            align-items: center;
            /* Centers items vertically */
            justify-content: center;
            /* Centers items horizontally */
            min-height: 100vh;
            /* Makes sure it takes the full viewport height */
            /* Richer blue gradient */
            background: linear-gradient(-45deg, #1a4a7a, #2575b8, #1e5f94, #2a8fcc);
            /* Creates a 4-color gradient */
            background-size: 400% 400%;
            /* Makes the gradient larger than needed so it can be animated */
            animation: gradientBG 20s ease infinite;
            /* Applies the animation to create moving effect */
            /* Slightly faster animation */
            overflow: hidden;
            /* Prevents scrollbars from appearing */
        }

        /* Animation that defines how the gradient background moves */
        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
                /* Start position */
            }

            50% {
                background-position: 100% 50%;
                /* Middle position */
            }

            100% {
                background-position: 0% 50%;
                /* End position (same as start to loop smoothly) */
            }
        }

        /* Glassmorphism Login Card */
        /* Creates a frosted glass effect container for the login form */
        .login-container {
            width: 100%;
            max-width: 430px;
            /* Limits the width for larger screens */
            padding: 3rem;
            /* Semi-transparent white with blur */
            background: rgba(255, 255, 255, 0.85);
            /* Semi-transparent background */
            backdrop-filter: blur(10px) saturate(180%);
            /* Creates the frosted glass effect */
            -webkit-backdrop-filter: blur(10px) saturate(180%);
            /* For Safari support */
            border: 1px solid rgba(255, 255, 255, 0.2);
            /* Light border for glass effect */
            border-radius: 0.75rem;
            /* Rounded corners */
            /* Adjusted shadow for glass effect */
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            /* Adds depth */
            text-align: center;
            animation: cardEnter 0.7s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
            /* Entrance animation */
            opacity: 0;
            /* Starts invisible for animation */
            position: relative;
            overflow: hidden;
            color: #343a40;
            /* Sets text color */
            /* Darker text for contrast on light card */
        }

        /* Animation for the card entrance effect */
        @keyframes cardEnter {
            from {
                opacity: 0;
                transform: translateY(25px) scale(0.98);
                /* Start slightly below and smaller */
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
                /* End at normal position and size */
            }
        }

        /* Logo Styling */
        .login-logo {
            margin-bottom: 2rem;
        }

        /* Tooth icon styling */
        .login-logo i {
            /* White logo icon for contrast against blue gradient background (visible through card) */
            color: #fff;
            font-size: 3.5rem;
            /* Slightly larger */
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            /* Add shadow for depth */
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            /* Bounce effect on hover */
        }

        /* Hover effect for the logo */
        .login-logo i:hover {
            transform: scale(1.15) rotate(-8deg);
            /* Enlarges and rotates the icon slightly */
        }

        /* Main heading styling */
        .login-container h3 {
            font-size: 1.7rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: #343a40;
            /* Ensure heading is dark */
        }

        /* Subtitle styling */
        .login-container .subtitle {
            font-size: 1rem;
            margin-bottom: 2.5rem;
            color: #5a6268;
            /* Slightly darker muted text */
        }

        /* Inputs refinement for Glass Card */
        /* Styles the form labels to be left-aligned */
        .form-label {
            font-weight: 500;
            color: #495057;
            display: block;
            margin-bottom: 0.4rem;
            text-align: left;
        }

        /* Container for input fields and their icons */
        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        /* Styling for the form input fields */
        .input-group .form-control {
            border-radius: 0.375rem 0 0 0.375rem;
            /* Rounds only left corners */
            border-right: 0;
            padding: 0.85rem 1.1rem;
            transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out, background-color 0.2s ease;
            border: 1px solid #ced4da;
            font-size: 0.95rem;
            background-color: rgba(255, 255, 255, 0.8);
            /* Slightly transparent input background */
        }

        /* Styling for the icon containers */
        .input-group .input-group-text {
            /* Match input style */
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid #ced4da;
            border-left: 0;
            border-radius: 0 0.375rem 0.375rem 0;
            /* Rounds only right corners */
            color: #6c757d;
            /* Keep icon color muted */
            transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out, background-color 0.2s ease;
        }

        /* Focus state for input fields */
        .form-control:focus {
            border-color: #2575b8;
            box-shadow: 0 0 0 .25rem rgba(37, 117, 184, .3);
            /* Slightly stronger focus shadow */
            background-color: #fff;
            /* Solid white on focus */
            z-index: 2;
            /* Ensures focus shadow isn't covered by adjacent elements */
        }

        /* Matches the icon container styling when the input is focused */
        .form-control:focus+.input-group-text {
            border-color: #2575b8;
            box-shadow: 0 0 0 .25rem rgba(37, 117, 184, .3);
            background-color: #fff;
            /* Solid white on focus */
            /* color: #2575b8; */
            /* Optional: Make icon blue on focus */
        }

        /* Enhanced Button */
        /* Primary button styling (blue login button) */
        .btn-primary {
            width: 100%;
            padding: .9rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            /* Solid button color matching theme */
            background-color: #2575b8;
            border: none;
            border-radius: 0.375rem;
            box-shadow: 0 4px 15px rgba(37, 117, 184, 0.3);
            /* Slightly stronger shadow */
            transition: background-color 0.3s ease, transform 0.15s ease, box-shadow 0.3s ease;
            color: #fff;
        }

        /* Hover effect for the button */
        .btn-primary:hover {
            background-color: #1e5f94;
            /* Darker solid color */
            transform: translateY(-3px);
            /* Button lifts up slightly */
            box-shadow: 0 7px 20px rgba(30, 95, 148, 0.4);
            /* Stronger hover shadow */
        }

        /* Active state (when button is being clicked) */
        .btn-primary:active {
            background-color: #1a527a;
            /* Even darker */
            transform: translateY(0) scale(.98);
            /* Button appears to be pressed down */
            box-shadow: 0 2px 10px rgba(30, 95, 148, 0.3);
        }

        /* Disabled state for the button */
        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
            /* Changes cursor to indicate button can't be clicked */
            background: #adb5bd;
            /* Grey out disabled button */
            box-shadow: none;
        }

        /* Error text */
        /* Styling for error messages */
        #error-msg {
            min-height: 1.2em;
            /* Ensures consistent spacing even when empty */
            font-size: .875rem;
            font-weight: 500;
            color: #e55353;
            /* Brighter red */
            margin-bottom: 1.5rem;
            /* More space above button */
            text-align: left;
            padding-left: 5px;
            /* Slight indent */
        }
    </style>
</head>

<body class="login-page">
    <div class="login-container">
        <div class="login-logo">
            <!-- Font Awesome tooth icon used as logo -->
            <i class="fas fa-tooth fa-3x"></i>
        </div>
        <h3>Welcome Back</h3>
        <p class="subtitle">Login to access the Dentaly Clinic portal.</p>

        <!-- Login form that submits to login_process.php -->
        <form id="loginForm" method="POST" action="auth/login_process.php">
            <!-- Input group for email with icon -->
            <div class="input-group">
                <input type="email" name="email" class="form-control" placeholder="Email address" required>
                <span class="input-group-text"><i class="fas fa-envelope fa-fw"></i></span>
            </div>
            <!-- Input group for password with icon -->
            <div class="input-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span>
            </div>

            <!-- Container for error messages -->
            <div id="error-msg">
                <?php
                // Display any login error messages from the session
                // and then remove them from the session
                if (!empty($_SESSION['login_error'])) {
                    echo htmlspecialchars($_SESSION['login_error']); // Securely outputs the error message
                    unset($_SESSION['login_error']); // Clears the error message from session
                }
                ?>
            </div>

            <!-- Login button with icon -->
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
    </div>

    <!-- Load Bootstrap JavaScript bundle from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Execute code when the DOM (page) is fully loaded
        document.addEventListener('DOMContentLoaded', function () {
            // Get references to important elements
            const loginForm = document.getElementById('loginForm');
            const errorMsgDiv = document.getElementById('error-msg');
            const submitButton = loginForm.querySelector('button[type="submit"]');

            // Add a submit event handler to the form
            loginForm.addEventListener('submit', function (event) {
                event.preventDefault(); // Prevent default form submission
                errorMsgDiv.textContent = ''; // Clear previous errors
                submitButton.disabled = true; // Disable button to prevent multiple submissions

                // Create loading spinner and change button text
                const loadingIcon = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
                const originalButtonText = submitButton.innerHTML;
                submitButton.innerHTML = loadingIcon;

                // Collect all form data (email and password)
                const formData = new FormData(loginForm);

                // Send AJAX request to login_process.php
                fetch(loginForm.action, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json()) // Parse the JSON response
                    .then(data => {
                        if (data.status === 'success' && data.redirect) {
                            // Redirect to the URL provided in the response
                            window.location.href = data.redirect;
                        } else {
                            // Display error message
                            errorMsgDiv.textContent = data.message || 'An unknown error occurred.';
                            submitButton.disabled = false; // Re-enable button
                            submitButton.innerHTML = originalButtonText; // Restore button text
                        }
                    })
                    .catch(error => {
                        // Handle any AJAX or network errors
                        console.error('Error:', error);
                        errorMsgDiv.textContent = 'An error occurred while trying to log in. Please try again.';
                        submitButton.disabled = false; // Re-enable button
                        submitButton.innerHTML = originalButtonText; // Restore button text
                    });
            });
        });
    </script>
</body>

</html>