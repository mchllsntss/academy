<!DOCTYPE html>
<html lang="en">
   
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System</title>
    <?php
    include '../components/header.php';
    ?>
    <style>
        .sidebar-collapsed {
            width: 64px !important;
        }
        .sidebar-collapsed .nav-text {
            display: none !important;
        }
        .sidebar-collapsed .logo-text {
            display: none !important;
        }
        .sidebar-collapsed .nav-icon {
            margin-right: 0 !important;
            justify-content: center !important;
        }
        .main-content-expanded {
            margin-left: 256px;
            transition: margin-left 0.3s ease;
        }
        .main-content-collapsed {
            margin-left: 64px;
            transition: margin-left 0.3s ease;
        }
        .close-sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s ease;
        }
        
        /* Glossy green sidebar */
        #sidebar {
            background: linear-gradient(145deg, rgba(72, 187, 120, 0.9), rgba(56, 161, 105, 0.9)),
                        linear-gradient(to bottom, rgba(255, 255, 255, 0.2) 0%, rgba(255, 255, 255, 0.1) 50%, rgba(255, 255, 255, 0) 100%);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        #sidebar .nav-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(5px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #sidebar .nav-item:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .logo-container {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .logo-container:hover {
            transform: scale(1.05);
        }
        
        .logo-container:active {
            transform: scale(0.95);
        }
    </style>
</head>
 
<body class="bg-gray-50">
    <!-- Toggle Button (visible when sidebar is closed) -->
    <button id="openSidebarBtn" class="fixed top-4 left-4 z-40 bg-green-600 text-white p-2 rounded-lg shadow-lg hover:bg-green-700 transition-all duration-300 hidden">
        <i class="fas fa-bars text-lg"></i>
    </button>

    
    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 w-64 text-white rounded-r-2xl shadow-xl z-30 transition-all duration-300">
       <!-- Header with controls -->
                <div class="flex items-center justify-between h-16 bg-green-700 bg-opacity-50 rounded-tr-2xl px-4">
                    <div class="flex items-center logo-container" id="logoMinimizeBtn">
                        <div class="flex items-center justify-center space-x-3">
                            <img src="../images/logo.png" alt="La Trinidad Academy Logo" class="h-10 w-10 object-contain">
                            <div class="logo-text text-left">
                                <span class="text-lg font-bold block leading-tight">La Trinidad Academy</span>
                                <span class="text-xs font-normal block">Library System</span>
                            </div>
                        </div>
                    </div>
                    <!-- Removed minimize and close buttons -->
                </div>
        
        <!-- Navigation -->
        <nav class="mt-10 px-4">
            <!-- <a href="../pages/login.php" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-sign-in-alt nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Login</span>
            </a> -->
            <a href="../pages/dashboard.php" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-tachometer-alt nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="../pages/students.php" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-user nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Students</span>
            </a>
            <a href="#" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-cog nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Settings</span>
            </a>
            <a href="#" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-book-open nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Books</span>
            </a>
            <a href="#" class="flex items-center py-3 px-4 text-white hover:bg-white hover:bg-opacity-20 transition-all duration-300 rounded-lg mb-2 nav-item">
                <i class="fas fa-users nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Members</span>
            </a>
            
        </nav>
        
        <!-- Expand button (visible when minimized) -->
        <div id="expandBtnContainer" class="absolute bottom-4 left-0 right-0 flex justify-center hidden">
            <button id="expandBtn" class="p-2 bg-white bg-opacity-20 text-white rounded-full hover:bg-opacity-30 transition-colors duration-200">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
    </div>

    
            
        </div>
    </div>

    <script>
        // Get DOM elements
        const sidebar = document.getElementById('sidebar');
        const logoMinimizeBtn = document.getElementById('logoMinimizeBtn');
        const expandBtn = document.getElementById('expandBtn');
        const expandBtnContainer = document.getElementById('expandBtnContainer');
        const openSidebarBtn = document.getElementById('openSidebarBtn');
        const mainContent = document.getElementById('mainContent');
        const toggleDemoBtn = document.getElementById('toggleDemoBtn');
        
        // State variables
        let isMinimized = false;
        let isClosed = false;
        
        // Minimize sidebar when clicking on logo
        logoMinimizeBtn.addEventListener('click', () => {
            if (isClosed) return; // Don't minimize if sidebar is closed
            
            if (isMinimized) {
                // Expand sidebar
                sidebar.classList.remove('sidebar-collapsed');
                mainContent.classList.remove('main-content-collapsed');
                mainContent.classList.add('main-content-expanded');
                expandBtnContainer.classList.add('hidden');
                isMinimized = false;
            } else {
                // Minimize sidebar
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.remove('main-content-expanded');
                mainContent.classList.add('main-content-collapsed');
                expandBtnContainer.classList.remove('hidden');
                isMinimized = true;
            }
            saveSidebarState();
        });
        
        // Close sidebar (now done via demo button only)
        function closeSidebar() {
            sidebar.classList.add('close-sidebar');
            openSidebarBtn.classList.remove('hidden');
            isClosed = true;
            
            // Remove margin from main content
            mainContent.classList.remove('main-content-expanded', 'main-content-collapsed');
            mainContent.style.marginLeft = '0';
            saveSidebarState();
        }
        
        // Expand sidebar from minimized state
        expandBtn.addEventListener('click', () => {
            sidebar.classList.remove('sidebar-collapsed');
            mainContent.classList.remove('main-content-collapsed');
            mainContent.classList.add('main-content-expanded');
            expandBtnContainer.classList.add('hidden');
            isMinimized = false;
            saveSidebarState();
        });
        
        // Open sidebar when closed
        openSidebarBtn.addEventListener('click', () => {
            sidebar.classList.remove('close-sidebar');
            openSidebarBtn.classList.add('hidden');
            mainContent.classList.add('main-content-expanded');
            isClosed = false;
            
            // Reset to expanded state if it was minimized before closing
            if (isMinimized) {
                sidebar.classList.remove('sidebar-collapsed');
                expandBtnContainer.classList.add('hidden');
                isMinimized = false;
            }
            saveSidebarState();
        });
        
        // Demo toggle button
        toggleDemoBtn.addEventListener('click', () => {
            if (isClosed) {
                // If closed, open it
                sidebar.classList.remove('close-sidebar');
                openSidebarBtn.classList.add('hidden');
                mainContent.classList.add('main-content-expanded');
                isClosed = false;
            } else if (isMinimized) {
                // If minimized, expand it
                sidebar.classList.remove('sidebar-collapsed');
                mainContent.classList.remove('main-content-collapsed');
                mainContent.classList.add('main-content-expanded');
                expandBtnContainer.classList.add('hidden');
                isMinimized = false;
            } else {
                // If expanded, minimize it
                sidebar.classList.add('sidebar-collapsed');
                mainContent.classList.remove('main-content-expanded');
                mainContent.classList.add('main-content-collapsed');
                expandBtnContainer.classList.remove('hidden');
                isMinimized = true;
            }
            saveSidebarState();
        });
        
        // Store sidebar state in localStorage for persistence
        function saveSidebarState() {
            const state = {
                isMinimized: isMinimized,
                isClosed: isClosed
            };
            localStorage.setItem('sidebarState', JSON.stringify(state));
        }
        
        function loadSidebarState() {
            const savedState = localStorage.getItem('sidebarState');
            if (savedState) {
                const state = JSON.parse(savedState);
                isMinimized = state.isMinimized;
                isClosed = state.isClosed;
                
                if (isClosed) {
                    sidebar.classList.add('close-sidebar');
                    openSidebarBtn.classList.remove('hidden');
                    mainContent.classList.remove('main-content-expanded', 'main-content-collapsed');
                    mainContent.style.marginLeft = '0';
                } else if (isMinimized) {
                    sidebar.classList.add('sidebar-collapsed');
                    mainContent.classList.add('main-content-collapsed');
                    expandBtnContainer.classList.remove('hidden');
                }
            }
        }
        
        // Load saved state on page load
        window.addEventListener('DOMContentLoaded', loadSidebarState);
        
        // Save state when changes occur
        logoMinimizeBtn.addEventListener('click', saveSidebarState);
        expandBtn.addEventListener('click', saveSidebarState);
        openSidebarBtn.addEventListener('click', saveSidebarState);
        toggleDemoBtn.addEventListener('click', saveSidebarState);
    </script>
</body>
</html>