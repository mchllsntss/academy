<?php
// login_controller.php - Secure Login Handler
require_once '../connection/dbconnection.php';

// Start session
session_start();

// Prevent direct access or non-POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../pages/login.php?error=Invalid request");
    exit;
}

// Get and sanitize form data
$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

// Basic validation
if (empty($username) || empty($password)) {
    header("Location: ../pages/login.php?error=Please fill in all fields");
    exit;
}

// Prepare secure query
$stmt = $conn->prepare("
    SELECT 
        id, 
        username, 
        password_hash, 
        profile_id,
        first_name,
        last_name
    FROM users 
    WHERE username = ?
    LIMIT 1
");

if (!$stmt) {
    header("Location: ../pages/login.php?error=Database error. Please try again.");
    exit;
}

$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../pages/login.php?error=Invalid username or password");
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    header("Location: ../pages/login.php?error=Invalid username or password");
    exit;
}

// Successful login - set session variables
$_SESSION['user_id']     = $user['id'];
$_SESSION['username']    = $user['username'];
$_SESSION['profile_id']  = $user['profile_id'] ?? 0; // 0 if NULL
$_SESSION['logged_in']   = true;

// Optional: Store user name for welcome messages
$_SESSION['full_name']   = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

// Optional: Set role-based session (if you add role column later)
// $_SESSION['role'] = ($user['profile_id'] == 1) ? 'admin' : 'student';

// Redirect based on profile_id
if ($user['profile_id'] == 1) {
    // Admin / Librarian
    header("Location: ../pages/dashboard.php");
} else {
    // Regular student (profile_id NULL or other values)
    $_SESSION['student_id'] = $user['id']; // Use user id as student reference
    header("Location: ../pages/student_books.php");
}

exit;
?>