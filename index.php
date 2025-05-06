<?php
// Start the session right at the beginning
session_start();

// If user is already logged in, redirect them away from login page
if (isset($_SESSION['role'])) {
    $redirect_url = 'index.php'; // Default fallback if role logic fails
    switch ($_SESSION['role']) {
        case 'admin':
            $redirect_url = 'admin/index.php';
            break;
        case 'doctor':
            $redirect_url = 'doctors/index.php';
            break; // Adjust path if needed
        case 'patient':
            $redirect_url = 'receptionist/index.php';
            break; // Adjust path if needed
        // Add other roles
    }
    header("Location: " . $redirect_url);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Dentaly Clinic – Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Font smoothing & stack */
        body {
            margin: 0;
            font-family: "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* Dynamic Animated Gradient Background */
        body.login-page {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            /* Richer blue gradient */
            background: linear-gradient(-45deg, #1a4a7a, #2575b8, #1e5f94, #2a8fcc);
            background-size: 400% 400%;
            animation: gradientBG 20s ease infinite;
            /* Slightly faster animation */
            overflow: hidden;
        }

        @keyframes gradientBG {
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
        }

        /* Glassmorphism Login Card */
        .login-container {
            width: 100%;
            max-width: 430px;
            padding: 3rem;
            /* Semi-transparent white with blur */
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(10px) saturate(180%);
            -webkit-backdrop-filter: blur(10px) saturate(180%);
            border: 1px solid rgba(255, 255, 255, 0.2);
            /* Light border for glass effect */
            border-radius: 0.75rem;
            /* Adjusted shadow for glass effect */
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.25);
            text-align: center;
            animation: cardEnter 0.7s cubic-bezier(0.25, 0.8, 0.25, 1) forwards;
            opacity: 0;
            position: relative;
            overflow: hidden;
            color: #343a40;
            /* Darker text for contrast on light card */
        }

        @keyframes cardEnter {
            from {
                opacity: 0;
                transform: translateY(25px) scale(0.98);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Logo Styling */
        .login-logo {
            margin-bottom: 2rem;
        }

        .login-logo i {
            /* White logo icon for contrast against blue gradient background (visible through card) */
            color: #fff;
            font-size: 3.5rem;
            /* Slightly larger */
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            /* Add shadow for depth */
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
        }

        .login-logo i:hover {
            transform: scale(1.15) rotate(-8deg);
        }

        .login-container h3 {
            font-size: 1.7rem;
            font-weight: 600;
            margin-bottom: 0.6rem;
            color: #343a40;
            /* Ensure heading is dark */
        }

        .login-container .subtitle {
            font-size: 1rem;
            margin-bottom: 2.5rem;
            color: #5a6268;
            /* Slightly darker muted text */
        }

        /* Inputs refinement for Glass Card */
        .form-label {
            font-weight: 500;
            color: #495057;
            display: block;
            margin-bottom: 0.4rem;
            text-align: left;
        }

        .input-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .input-group .form-control {
            border-radius: 0.375rem 0 0 0.375rem;
            border-right: 0;
            padding: 0.85rem 1.1rem;
            transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out, background-color 0.2s ease;
            border: 1px solid #ced4da;
            font-size: 0.95rem;
            background-color: rgba(255, 255, 255, 0.8);
            /* Slightly transparent input background */
        }

        .input-group .input-group-text {
            /* Match input style */
            background-color: rgba(255, 255, 255, 0.8);
            border: 1px solid #ced4da;
            border-left: 0;
            border-radius: 0 0.375rem 0.375rem 0;
            color: #6c757d;
            /* Keep icon color muted */
            transition: border-color .2s ease-in-out, box-shadow .2s ease-in-out, background-color 0.2s ease;
        }

        .form-control:focus {
            border-color: #2575b8;
            box-shadow: 0 0 0 .25rem rgba(37, 117, 184, .3);
            /* Slightly stronger focus shadow */
            background-color: #fff;
            /* Solid white on focus */
            z-index: 2;
        }

        .form-control:focus+.input-group-text {
            border-color: #2575b8;
            box-shadow: 0 0 0 .25rem rgba(37, 117, 184, .3);
            background-color: #fff;
            /* Solid white on focus */
            /* color: #2575b8; */
            /* Optional: Make icon blue on focus */
        }

        /* Enhanced Button */
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

        .btn-primary:hover {
            background-color: #1e5f94;
            /* Darker solid color */
            transform: translateY(-3px);
            box-shadow: 0 7px 20px rgba(30, 95, 148, 0.4);
            /* Stronger hover shadow */
        }

        .btn-primary:active {
            background-color: #1a527a;
            /* Even darker */
            transform: translateY(0) scale(.98);
            box-shadow: 0 2px 10px rgba(30, 95, 148, 0.3);
        }

        .btn-primary:disabled {
            opacity: .6;
            cursor: not-allowed;
            background: #adb5bd;
            /* Grey out disabled button */
            box-shadow: none;
        }

        /* Error text */
        #error-msg {
            min-height: 1.2em;
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
            <i class="fas fa-tooth fa-3x"></i>
        </div>
        <h3>Welcome Back</h3>
        <p class="subtitle">Login to access the Dentaly Clinic portal.</p>

        <form id="loginForm" method="POST" action="auth/login_process.php">
            <div class="input-group">
                <input type="email" name="email" class="form-control" placeholder="Email address" required>
                <span class="input-group-text"><i class="fas fa-envelope fa-fw"></i></span>
            </div>
            <div class="input-group">
                <input type="password" name="password" class="form-control" placeholder="Password" required>
                <span class="input-group-text"><i class="fas fa-lock fa-fw"></i></span>
            </div>

            <div id="error-msg">
                <?php
                if (!empty($_SESSION['login_error'])) {
                    echo htmlspecialchars($_SESSION['login_error']);
                    unset($_SESSION['login_error']);
                }
                ?>
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const loginForm = document.getElementById('loginForm');
            const errorMsgDiv = document.getElementById('error-msg');
            const submitButton = loginForm.querySelector('button[type="submit"]');

            loginForm.addEventListener('submit', function (event) {
                event.preventDefault(); // Prevent default form submission
                errorMsgDiv.textContent = ''; // Clear previous errors
                submitButton.disabled = true; // Disable button to prevent multiple submissions
                const loadingIcon = '<i class="fas fa-spinner fa-spin me-2"></i> Logging in...';
                const originalButtonText = submitButton.innerHTML;
                submitButton.innerHTML = loadingIcon;


                const formData = new FormData(loginForm);

                fetch(loginForm.action, {
                    method: 'POST',
                    body: formData
                })
                    .then(response => response.json())
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