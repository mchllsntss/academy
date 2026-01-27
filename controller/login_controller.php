     <?php
// login_controller.php - Backend for handling login authentication
include '../connection/dbconnection.php';
// Start session to manage user login state
session_start();

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    
    // Basic validation
    if (empty($user) || empty($pass)) {
        // Redirect back with error
        header("Location: ../pages/login.php?error=Please fill in all fields");
        exit;
    }
    
    // Prepare statement to prevent SQL injection
    $stmt = mysqli_prepare($conn, "SELECT id, password_hash, profile_id FROM users WHERE username = ?");
    mysqli_stmt_bind_param($stmt, "s", $user);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $stored_hash = $row['password_hash'];
        
        // Verify password
        if (password_verify($pass, $stored_hash)) {
            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['username'] = $user;
            $_SESSION['profile_id'] = $row['profile_id'];
            $_SESSION['logged_in'] = true;
            
            // Redirect based on user level
            if ($row['profile_id'] == 1) {
                // Admin
                header("Location: ../pages/dashboard.php");
            } else {
                // Student
                header("Location: ../pages/student_books.php");
            }
            exit;
        } else {
            // Redirect back with error
            header("Location: ../pages/login.php?error=Invalid username or password");
            exit;
        }
    } else {
        // Redirect back with error
        header("Location: ../pages/login.php?error=Invalid username or password");
        exit;
    }

    
    
    mysqli_stmt_close($stmt);
    // Close connection
    mysqli_close($conn);
} else {
    // If not a POST request, redirect to login
    header("Location: ../pages/login.php");
    exit;
}
?>