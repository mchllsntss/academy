<?php
// student_sidebar.php
// Start session kung hindi pa na-start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../connection/dbconnection.php';

// Default values
$student_name = "Student";
$profile_image = null;

// Kunin ang student info kung logged in
if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT 
            first_name,
            last_name,
            profile_image
        FROM students 
        WHERE user_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($student = $result->fetch_assoc()) {
        $student_name = htmlspecialchars(trim($student['first_name'] . ' ' . $student['last_name']));
        if (!empty($student['profile_image']) && file_exists('../' . $student['profile_image'])) {
            $profile_image = '../' . htmlspecialchars($student['profile_image']);
        }
    }
    $stmt->close();
}
?>

<!-- Modern Green Sidebar with Dynamic Welcome -->
<style>
    /* Main content margin kapag bukas ang sidebar */
    .main-content-expanded {
        margin-left: 256px;
        transition: margin-left 0.3s ease;
    }

    /* Sidebar Styling */
    #sidebar {
        background: linear-gradient(145deg, #2e7d32, #1b5e20);
        border-right: 1px solid rgba(255, 255, 255, 0.15);
        box-shadow: 0 0 20px rgba(46, 125, 50, 0.4);
        position: fixed;
        top: 0;
        left: 0;
        width: 256px;
        height: 100vh;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        overflow-y: auto;
        transition: transform 0.3s ease;
        color: white;
    }

    /* Collapsed state (para sa mobile) */
    #sidebar.close-sidebar {
        transform: translateX(-100%);
    }

    /* Logo & Welcome Section */
    .logo-container {
        padding: 2rem 1rem;
        text-align: center;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .student-welcome {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 1rem;
        margin-top: 1.5rem;
        padding: 0 1rem;
    }

    .student-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid rgba(255,255,255,0.4);
    }

    .default-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #4caf50;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 1.6rem;
        border: 3px solid rgba(255,255,255,0.4);
    }

    .welcome-text {
        text-align: left;
    }

    .welcome-text h3 {
        font-size: 1.3rem;
        font-weight: 700;
        margin: 0;
    }

    .welcome-text p {
        font-size: 0.9rem;
        opacity: 0.9;
        margin-top: 0.3rem;
    }

    /* Navigation Items */
    .nav-item {
        display: flex;
        align-items: center;
        padding: 1rem 1.5rem;
        color: rgba(255,255,255,0.9);
        text-decoration: none;
        transition: all 0.3s ease;
        margin: 0.3rem 1rem;
        border-radius: 12px;
        font-weight: 500;
    }

    .nav-item:hover {
        background: rgba(255,255,255,0.15);
        color: white;
        transform: translateX(8px);
    }

    .nav-item.active {
        background: rgba(255,255,255,0.25);
        color: white;
        font-weight: 600;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }

    .nav-icon {
        font-size: 1.3rem;
        margin-right: 1rem;
        width: 24px;
        text-align: center;
    }

    /* Toggle Button (Mobile) */
    #openSidebarBtn {
        position: fixed;
        top: 1rem;
        left: 1rem;
        z-index: 1100;
        background: linear-gradient(90deg, #2e7d32, #4caf50);
        color: white;
        border: none;
        border-radius: 12px;
        padding: 0.8rem 1rem;
        box-shadow: 0 4px 15px rgba(46,125,50,0.5);
        cursor: pointer;
        transition: all 0.3s ease;
        display: none;
    }

    #openSidebarBtn:hover {
        transform: scale(1.08);
        box-shadow: 0 6px 20px rgba(46,125,50,0.6);
    }

    /* Logout Button */
    .logout-container {
        margin-top: auto;
        padding: 1.5rem;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .logout-btn {
        display: flex;
        align-items: center;
        width: 100%;
        padding: 1rem 1.5rem;
        color: rgba(255,255,255,0.85);
        background: rgba(255,87,87,0.1);
        border: 1px solid rgba(255,87,87,0.3);
        border-radius: 12px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    .logout-btn:hover {
        background: rgba(255,87,87,0.3);
        color: white;
        transform: translateX(5px);
        box-shadow: 0 4px 15px rgba(255,87,87,0.3);
    }

    /* Scrollbar */
    #sidebar::-webkit-scrollbar {
        width: 6px;
    }
    #sidebar::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.05);
    }
    #sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 3px;
    }
    #sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255,255,255,0.5);
    }

    /* Responsive - Mobile */
    @media (max-width: 1024px) {
        #sidebar {
            transform: translateX(-100%);
        }
        #sidebar:not(.close-sidebar) {
            transform: translateX(0);
        }
        #openSidebarBtn {
            display: block;
        }
        .main-content-expanded {
            margin-left: 0;
        }
    }
</style>

<!-- Toggle Button (Mobile) -->
<button id="openSidebarBtn" class="fixed top-4 left-4 z-40 p-3 rounded-lg shadow-lg transition-all duration-300">
    <i class="fas fa-bars text-lg"></i>
</button>

<!-- Sidebar -->
<div id="sidebar" class="text-white rounded-r-2xl shadow-xl overflow-y-auto">
    <!-- Logo & Dynamic Welcome -->
    <div class="logo-container">
        <img src="../images/logo.png" alt="La Trinidad Academy Logo" class="h-20 w-20 object-contain mx-auto mb-4">
        <div class="student-welcome">
            <?php if ($profile_image): ?>
                <img src="<?= $profile_image ?>" alt="Profile" class="student-avatar">
            <?php else: ?>
                <div class="default-avatar">
                    <?= strtoupper(substr($student_name, 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div class="welcome-text">
                <h3>Welcome, <?= $student_name ?>!</h3>
                <p>Happy Reading!</p>
            </div>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="mt-8 px-4">
        <a href="../pages/student_books.php" class="nav-item" data-page="student_books">
            <i class="fas fa-book nav-icon"></i>
            <span>Books</span>
        </a>
        <a href="../pages/student_request_book.php" class="nav-item" data-page="student_request_book">
            <i class="fas fa-hand-paper nav-icon"></i>
            <span>Request Book</span>
        </a>
        <a href="../pages/student_borrowed_books.php" class="nav-item" data-page="student_borrowed_books">
            <i class="fas fa-book-reader nav-icon"></i>
            <span>Borrowed Books</span>
        </a>
        <!-- Pwede ka magdagdag ng iba pang links dito -->
    </nav>

    <!-- Logout at Bottom -->
    <div class="logout-container">
        <a href="../controller/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt mr-3"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<script>
    // DOM Elements
    const sidebar = document.getElementById('sidebar');
    const openBtn = document.getElementById('openSidebarBtn');
    const mainContent = document.querySelector('.main-content-expanded') || document.querySelector('.main-content');
    const navItems = document.querySelectorAll('#sidebar .nav-item');

    // Toggle Sidebar (for mobile)
    openBtn.addEventListener('click', () => {
        sidebar.classList.toggle('close-sidebar');
        openBtn.classList.toggle('hidden');
        if (mainContent) {
            mainContent.style.marginLeft = sidebar.classList.contains('close-sidebar') ? '0' : '256px';
        }
    });

    // Set active navigation item
    function setActiveNav() {
        const currentPage = window.location.pathname.split('/').pop().replace('.php', '');
        
        navItems.forEach(item => {
            const page = item.getAttribute('data-page');
            if (page === currentPage) {
                item.classList.add('active');
            } else {
                item.classList.remove('active');
            }
        });
    }

    // Run on page load
    document.addEventListener('DOMContentLoaded', () => {
        setActiveNav();

        // Highlight on click (before navigation)
        navItems.forEach(item => {
            item.addEventListener('click', () => {
                navItems.forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
    });

    // Update active on back/forward navigation
    window.addEventListener('popstate', setActiveNav);
</script>