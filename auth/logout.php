<?php
// filepath: c:\xampp\htdocs\Dentaly\auth\logout.php

session_start(); // Start the session

// Destroy all session data
session_unset();
session_destroy();

// Redirect to the login page
header("Location: ../index.php");
exit;