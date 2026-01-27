<style>
        /* Remove all sidebar collapse related styles */
        .main-content-expanded {
            margin-left: 256px;
            transition: margin-left 0.3s ease;
        }
        
        /* Green sidebar styling */
        #sidebar {
            background: linear-gradient(145deg, #2e7d32, #2e7d32, #2e7d32);
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 0 15px rgba(46, 125, 50, 0.3);
            display: flex;
            flex-direction: column;
        }
        
        /* Header with white background and green text */
        #sidebar .flex.items-center.justify-between {
            background: transparent !important;
            border-top-right-radius: 1rem;
        }
        
        /* Logo container - transparent background */
        .logo-container {
            background: transparent !important;
            padding: 0;
            width: 100%;
        }
        
        /* Green text for logo */
        .logo-text {
            color: white !important;
        }
        
        .logo-text span:first-child {
            color: white;
            font-weight: 700;
        }
        
        .logo-text span:last-child {
            color: rgba(255, 255, 255, 0.9);
        }
        
        /* Logo image styling - transparent background */
        .logo-container img {
            border-radius: 8px;
            background: transparent;
            padding: 0;
        }
        
        #sidebar .nav-item {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 8px;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        #sidebar .nav-item:hover {
            background: rgba(255, 255, 255, 0.25);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateX(5px);
        }
        
        /* Active link styling */
        #sidebar .nav-item.active {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Navigation icon styling */
        .nav-icon {
            color: #e8f5e9;
            font-size: 1.1rem;
        }
        
        /* Open sidebar button */
        #openSidebarBtn {
            background: linear-gradient(to right, #2e7d32, #2e7d32);
            color: white;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.4);
        }
        
        #openSidebarBtn:hover {
            background: linear-gradient(to right, #1b5e20, #2e7d32);
            transform: scale(1.05);
        }
        
        /* Scrollbar styling */
        #sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        #sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
        }
        
        #sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 3px;
        }
        
        #sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.4);
        }
        
        /* Bottom right logout button styling */
        .logout-container {
            margin-top: auto;
            padding: 20px;
        }
        
        .logout-btn {
            color: rgba(255, 255, 255, 0.8);
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .logout-btn:hover {
            color: white;
            background: rgba(255, 87, 87, 0.3);
            border-color: rgba(255, 87, 87, 0.5);
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(255, 87, 87, 0.2);
        }
        
        .logout-btn i {
            font-size: 1.2rem;
            margin-right: 12px;
            width: 24px;
            text-align: center;
        }
        
        .logout-text {
            font-size: 1rem;
            font-weight: 500;
        }
    </style>
    <!-- Toggle Button (visible when sidebar is closed) -->
    <button id="openSidebarBtn" class="fixed top-4 left-4 z-40 p-3 rounded-lg shadow-lg transition-all duration-300 hidden">
        <i class="fas fa-bars text-lg"></i>
    </button>

    <!-- Sidebar -->
    <div id="sidebar" class="fixed inset-y-0 left-0 w-64 text-white rounded-r-2xl shadow-xl z-30 transition-all duration-300 overflow-y-auto">
       <!-- Header with transparent background -->
       <div class="flex items-center justify-center h-24 px-4 rounded-tr-2xl">
           <div class="flex flex-col items-center justify-center logo-container">
               <img src="../images/logo.png" alt="La Trinidad Academy Logo" class="h-20 w-20 object-contain mb-2 mt-28">
               <div class="logo-text text-center">
                   <span class="text-xl font-bold block leading-tight">La Trinidad Academy</span>
                   <span class="text-sm font-normal block">Welcome Student!</span>
               </div>
           </div>
       </div>
        
        <!-- Navigation -->
        <nav class="mt-28 px-4">
            <a href="../pages/student_books.php" class="flex items-center py-3 px-4 transition-all duration-300 rounded-lg mb-2 nav-item" data-page="dashboard">
                <i class="fas fa-tachometer-alt nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Book</span>
            </a>
            <a href="../pages/student_request_book.php" class="flex items-center py-3 px-4 transition-all duration-300 rounded-lg mb-2 nav-item" data-page="students">
                <i class="fas fa-user nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Request Book</span>
            </a>
            <a href="../pages/student_borrowed_books.php" class="flex items-center py-3 px-4 transition-all duration-300 rounded-lg mb-2 nav-item" data-page="borrowed_books">
                <i class="fas fa-book nav-icon mr-3 text-lg"></i>
                <span class="nav-text">Borrowed Books</span>
            </a>
        </nav>
        
        <!-- Logout Button Container at Bottom -->
        <div class="logout-container px-4">
            <a href="../controller/logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span class="logout-text">Logout</span>
            </a>
        </div>
    </div>

    <script>
        // Get DOM elements
        const sidebar = document.getElementById('sidebar');
        const openSidebarBtn = document.getElementById('openSidebarBtn');
        const mainContent = document.getElementById('mainContent');
        const navItems = document.querySelectorAll('#sidebar .nav-item');
        
        // State variable for closed state only
        let isClosed = false;
        
        // Set active navigation item based on current page - FIXED VERSION
        function setActiveNavItem() {
            // Get current page filename
            const currentPage = window.location.pathname.split('/').pop();
            
            // Clean the page name - remove .php extension and any query parameters
            const cleanCurrentPage = currentPage.replace('.php', '').split('?')[0];
            
            console.log('Current page cleaned:', cleanCurrentPage);
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href) {
                    // Get the page name from href
                    const hrefPage = href.split('/').pop();
                    // Clean the href page name
                    const cleanHrefPage = hrefPage.replace('.php', '').split('?')[0];
                    
                    console.log('Checking:', cleanHrefPage, 'against', cleanCurrentPage);
                    
                    // Use exact match for the cleaned page names
                    if (cleanHrefPage === cleanCurrentPage) {
                        item.classList.add('active');
                        console.log('✓ Active item:', cleanHrefPage);
                    } else {
                        item.classList.remove('active');
                    }
                }
            });
        }
        
        // Alternative method using data-page attribute (more reliable)
        function setActiveNavItemByDataAttribute() {
            // Get current page filename
            const currentPage = window.location.pathname.split('/').pop();
            // Clean the page name
            const cleanCurrentPage = currentPage.replace('.php', '').split('?')[0];
            
            console.log('Setting active nav item for:', cleanCurrentPage);
            
            navItems.forEach(item => {
                const pageAttribute = item.getAttribute('data-page');
                if (pageAttribute === cleanCurrentPage) {
                    item.classList.add('active');
                    console.log('✓ Active via data-page:', pageAttribute);
                } else {
                    item.classList.remove('active');
                }
            });
        }
        
        // Close sidebar function
        function closeSidebar() {
            sidebar.classList.add('close-sidebar');
            openSidebarBtn.classList.remove('hidden');
            isClosed = true;
            
            // Remove margin from main content
            if (mainContent) {
                mainContent.classList.remove('main-content-expanded');
                mainContent.style.marginLeft = '0';
            }
            saveSidebarState();
        }
        
        // Open sidebar when closed
        openSidebarBtn.addEventListener('click', () => {
            sidebar.classList.remove('close-sidebar');
            openSidebarBtn.classList.add('hidden');
            if (mainContent) {
                mainContent.classList.add('main-content-expanded');
            }
            isClosed = false;
            saveSidebarState();
        });
        
        // Store sidebar state in localStorage for persistence
        function saveSidebarState() {
            const state = {
                isClosed: isClosed
            };
            localStorage.setItem('sidebarState', JSON.stringify(state));
        }
        
        function loadSidebarState() {
            const savedState = localStorage.getItem('sidebarState');
            if (savedState) {
                const state = JSON.parse(savedState);
                isClosed = state.isClosed;
                
                if (isClosed) {
                    sidebar.classList.add('close-sidebar');
                    openSidebarBtn.classList.remove('hidden');
                    if (mainContent) {
                        mainContent.classList.remove('main-content-expanded');
                        mainContent.style.marginLeft = '0';
                    }
                }
            }
        }
        
        // Load saved state on page load and set active nav item
        document.addEventListener('DOMContentLoaded', () => {
            loadSidebarState();
            // Use the data-attribute method for more reliable matching
            setActiveNavItemByDataAttribute();
            
            // Also run the href method for backward compatibility
            setActiveNavItem();
        });
        
        // Save state when sidebar is opened/closed
        openSidebarBtn.addEventListener('click', saveSidebarState);
        
        // Update active nav item when clicked (immediate feedback)
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                // Don't prevent default - let the link work normally
                console.log('Nav item clicked:', item.getAttribute('data-page'));
                
                // Update active state immediately
                navItems.forEach(i => i.classList.remove('active'));
                item.classList.add('active');
                
                // Save the active state to sessionStorage for immediate feedback
                const pageName = item.getAttribute('data-page');
                sessionStorage.setItem('lastActivePage', pageName);
            });
        });
        
        // Check sessionStorage for last active page on page load
        window.addEventListener('pageshow', () => {
            const lastActivePage = sessionStorage.getItem('lastActivePage');
            if (lastActivePage) {
                navItems.forEach(item => {
                    if (item.getAttribute('data-page') === lastActivePage) {
                        item.classList.add('active');
                    }
                });
            }
        });
    </script>
</body>
