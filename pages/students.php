<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management | Library System</title>

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary-green: #10b981;
            --primary-green-dark: #059669;
            --primary-green-light: #a7f3d0;
            --bg-green-light: #f0fdf4;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-green-dark);
        }
        
        .bg-green-light {
            background-color: var(--bg-green-light);
        }
        
        .border-green {
            border-color: var(--primary-green-light);
        }
        
        .text-primary-green {
            color: var(--primary-green);
        }
        
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .search-box:focus {
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .success-badge {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .table-row:hover {
            background-color: #f0fdf4;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    <div class="min-h-screen flex flex-col">        
        <!-- Main Content -->
        <div id="mainContent" class="main-content-expanded p-6 transition-all duration-300 overflow-y-auto flex-1">
            <div class="max-w-7xl mx-auto">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-2xl font-bold text-gray-800">Student Management</h2>
                        <p class="text-gray-600">View and manage all registered students in the library system</p>
                    </div>
                    <div class="flex space-x-4">
                        <button id="printBtn" class="border border-gray-300 rounded-lg px-6 py-3 hover:bg-gray-50 font-medium flex items-center no-print">
                            <i class="fas fa-print mr-2 text-gray-600"></i> Print List
                        </button>
                        <button id="addStudentBtn" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center no-print">
                            <i class="fas fa-user-plus mr-2"></i> Add New Student
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter Bar -->
            <div class="bg-white rounded-xl shadow-sm p-6 mb-6 no-print">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="relative w-full md:w-96">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-gray-400"></i>
                        </div>
                        <input type="text" id="searchStudents" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Search students by name, ID, or username...">
                    </div>
                    
                    <div class="flex space-x-4">
                        <select class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        
                        <button class="border border-gray-300 rounded-lg px-4 py-3 hover:bg-gray-50 flex items-center">
                            <i class="fas fa-filter mr-2 text-gray-600"></i> Filter
                        </button>
                        
                        <button class="border border-gray-300 rounded-lg px-4 py-3 hover:bg-gray-50 flex items-center">
                            <i class="fas fa-download mr-2 text-gray-600"></i> Export
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Printable Students Table -->
            <div id="printableTable" class="print-section">
                <!-- Print Header -->
                <div class="hidden print:block mb-6">
                    <div class="text-center mb-4">
                        <h1 class="text-2xl font-bold text-gray-800">Library Management System</h1>
                        <h2 class="text-xl text-gray-700">Student Directory</h2>
                        <p class="text-gray-600">Generated on: <span id="printDate"></span></p>
                        <p class="text-gray-600">Academic Year: <span id="printYear"></span></p>
                    </div>
                    <hr class="border-gray-300 mb-4">
                </div>
                
                <!-- Students Table -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-green-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Student ID
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Name
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Username
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Books Borrowed
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Status
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Join Date
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider">
                                        Date Created
                                    </th>
                                    <th class="px-6 py-4 text-left text-xs font-medium text-gray-700 uppercase tracking-wider no-print">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200" id="studentsTableBody">
                                <!-- Students will be populated here by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="px-6 py-4 bg-green-50 border-t border-gray-200 no-print">
                        <div class="flex items-center justify-between">
                            <div class="text-sm text-gray-700">
                                Showing <span class="font-medium">1</span> to <span class="font-medium">10</span> of <span class="font-medium">342</span> students
                            </div>
                            <div class="flex space-x-2">
                                <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Previous
                                </button>
                                <button class="px-3 py-2 border border-green-500 rounded-md text-sm font-medium text-white btn-primary">
                                    1
                                </button>
                                <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    2
                                </button>
                                <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    3
                                </button>
                                <button class="px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Print Summary -->
                    <div class="hidden print:block p-6 border-t border-gray-300">
                        <div class="flex justify-between">
                            <div>
                                <p class="text-sm text-gray-600">Total Students: <span id="printTotalStudents" class="font-medium"></span></p>
                                <p class="text-sm text-gray-600">Active Students: <span id="printActiveStudents" class="font-medium"></span></p>
                                <p class="text-sm text-gray-600">Inactive Students: <span id="printInactiveStudents" class="font-medium"></span></p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600">Generated by: Library Admin</p>
                                <p class="text-sm text-gray-600">Page 1 of 1</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>
    </div>
    
    <!-- Add Student Modal -->
    <div id="addStudentModal" class="fixed inset-0 z-50 hidden overflow-y-auto no-print">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 transition-opacity modal-backdrop" aria-hidden="true"></div>
            
            <!-- Modal Content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <!-- Modal Header -->
                <div class="bg-green-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-white">
                            <i class="fas fa-user-plus mr-2"></i> Add New Student
                        </h3>
                        <button id="closeModal" class="text-white hover:text-green-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="bg-white px-6 py-6">
                    <form id="addStudentForm">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- First Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter first name">
                                </div>
                            </div>
                            
                            <!-- Last Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter last name">
                                </div>
                            </div>
                            
                            <!-- Middle Initial -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Middle Initial</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" maxlength="1" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="M.I.">
                                </div>
                            </div>
                            
                            <!-- Student ID -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Student ID *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="e.g., 2023-00123">
                                </div>
                            </div>
                            
                            <!-- Username -->
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-at text-gray-400"></i>
                                    </div>
                                    <input type="text" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Choose a username">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Username must be unique and 3-20 characters long</p>
                            </div>
                            
                            <!-- Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="password" required class="pl-10 pr-10 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter password">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <div class="flex items-center mb-1">
                                        <div class="h-1 w-1/4 bg-gray-200 rounded mr-1"></div>
                                        <div class="h-1 w-1/4 bg-gray-200 rounded mr-1"></div>
                                        <div class="h-1 w-1/4 bg-gray-200 rounded mr-1"></div>
                                        <div class="h-1 w-1/4 bg-gray-200 rounded"></div>
                                    </div>
                                    <p class="text-xs text-gray-500">Password strength: <span class="font-medium">Weak</span></p>
                                </div>
                            </div>
                            
                            <!-- Confirm Password -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="confirmPassword" required class="pl-10 pr-10 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Confirm password">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Passwords must match</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelModal" class="px-6 py-3 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center">
                                <i class="fas fa-save mr-2"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Print Options Modal -->
    <div id="printOptionsModal" class="fixed inset-0 z-50 hidden overflow-y-auto no-print">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Modal Backdrop -->
            <div class="fixed inset-0 transition-opacity modal-backdrop" aria-hidden="true"></div>
            
            <!-- Modal Content -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                <!-- Modal Header -->
                <div class="bg-green-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-white">
                            <i class="fas fa-print mr-2"></i> Print Options
                        </h3>
                        <button id="closePrintModal" class="text-white hover:text-green-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Modal Body -->
                <div class="bg-white px-6 py-6">
                    <form id="printOptionsForm">
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Select Academic Year</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-calendar-alt text-gray-400"></i>
                                </div>
                                <select id="yearSelect" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent">
                                    <option value="all">All Years</option>
                                    <option value="2023-2024">2023-2024</option>
                                    <option value="2022-2023">2022-2023</option>
                                    <option value="2021-2022">2021-2022</option>
                                    <option value="2020-2021">2020-2021</option>
                                    <option value="2019-2020">2019-2020</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Print Options</label>
                            <div class="space-y-2">
                                <div class="flex items-center">
                                    <input type="checkbox" id="includeSummary" checked class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="includeSummary" class="ml-2 block text-sm text-gray-700">
                                        Include summary statistics
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="includeHeaders" checked class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="includeHeaders" class="ml-2 block text-sm text-gray-700">
                                        Include headers and footers
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input type="checkbox" id="activeOnly" class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                    <label for="activeOnly" class="ml-2 block text-sm text-gray-700">
                                        Active students only
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-3">
                            <button type="button" id="cancelPrintModal" class="px-6 py-3 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center">
                                <i class="fas fa-print mr-2"></i> Print
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Toast -->
    <div id="successToast" class="fixed top-4 right-4 z-50 hidden no-print">
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="h-10 w-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check text-white"></i>
                </div>
                <div>
                    <p class="font-medium">Success!</p>
                    <p class="text-sm">Student account created successfully.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Sample student data with dateCreated field
        const students = [
            {
                id: "2023-00123",
                firstName: "John",
                lastName: "Doe",
                mi: "A",
                username: "john.doe",
                booksBorrowed: 3,
                status: "active",
                joinDate: "2023-09-15",
                dateCreated: "2023-09-10"
            },
            {
                id: "2023-00124",
                firstName: "Jane",
                lastName: "Smith",
                mi: "B",
                username: "jane.smith",
                booksBorrowed: 0,
                status: "active",
                joinDate: "2023-10-22",
                dateCreated: "2023-10-15"
            },
            {
                id: "2023-00125",
                firstName: "Robert",
                lastName: "Johnson",
                mi: "C",
                username: "robert.j",
                booksBorrowed: 5,
                status: "active",
                joinDate: "2023-08-10",
                dateCreated: "2023-08-01"
            },
            {
                id: "2022-00101",
                firstName: "Sarah",
                lastName: "Williams",
                mi: "M",
                username: "sarah.w",
                booksBorrowed: 2,
                status: "active",
                joinDate: "2022-11-05",
                dateCreated: "2022-11-01"
            },
            {
                id: "2022-00102",
                firstName: "Michael",
                lastName: "Brown",
                mi: "D",
                username: "michael.b",
                booksBorrowed: 7,
                status: "inactive",
                joinDate: "2022-07-18",
                dateCreated: "2022-07-10"
            },
            {
                id: "2023-00128",
                firstName: "Emily",
                lastName: "Davis",
                mi: "L",
                username: "emily.d",
                booksBorrowed: 1,
                status: "active",
                joinDate: "2023-10-30",
                dateCreated: "2023-10-25"
            },
            {
                id: "2021-00101",
                firstName: "David",
                lastName: "Miller",
                mi: "R",
                username: "david.m",
                booksBorrowed: 4,
                status: "active",
                joinDate: "2021-09-28",
                dateCreated: "2021-09-20"
            },
            {
                id: "2023-00130",
                firstName: "Lisa",
                lastName: "Wilson",
                mi: "K",
                username: "lisa.w",
                booksBorrowed: 0,
                status: "active",
                joinDate: "2023-11-12",
                dateCreated: "2023-11-05"
            },
            {
                id: "2022-00103",
                firstName: "James",
                lastName: "Taylor",
                mi: "P",
                username: "james.t",
                booksBorrowed: 6,
                status: "active",
                joinDate: "2022-08-25",
                dateCreated: "2022-08-15"
            },
            {
                id: "2023-00132",
                firstName: "Amanda",
                lastName: "Anderson",
                mi: "S",
                username: "amanda.a",
                booksBorrowed: 2,
                status: "active",
                joinDate: "2023-10-15",
                dateCreated: "2023-10-10"
            }
        ];

        // Get academic year from date
        function getAcademicYear(dateString) {
            const date = new Date(dateString);
            const year = date.getFullYear();
            const nextYear = year + 1;
            return `${year}-${nextYear}`;
        }

        // Filter students by year
        function filterStudentsByYear(selectedYear) {
            if (selectedYear === "all") {
                return students;
            }
            
            return students.filter(student => {
                const academicYear = getAcademicYear(student.dateCreated);
                return academicYear === selectedYear;
            });
        }

        // Get print statistics
        function getPrintStatistics(filteredStudents) {
            const total = filteredStudents.length;
            const active = filteredStudents.filter(s => s.status === "active").length;
            const inactive = filteredStudents.filter(s => s.status === "inactive").length;
            
            return {
                total,
                active,
                inactive
            };
        }

        // Update print summary
        function updatePrintSummary(filteredStudents) {
            const stats = getPrintStatistics(filteredStudents);
            document.getElementById('printTotalStudents').textContent = stats.total;
            document.getElementById('printActiveStudents').textContent = stats.active;
            document.getElementById('printInactiveStudents').textContent = stats.inactive;
        }

        // Populate students table
        function populateStudentsTable(studentsList = students) {
            const tbody = document.getElementById('studentsTableBody');
            tbody.innerHTML = '';
            
            studentsList.forEach(student => {
                const row = document.createElement('tr');
                row.className = 'table-row';
                
                row.innerHTML = `
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        ${student.id}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${student.lastName}, ${student.firstName} ${student.mi ? student.mi + '.' : ''}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${student.username}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <div class="flex items-center">
                            <span class="font-medium mr-2">${student.booksBorrowed}</span>
                            ${student.booksBorrowed > 5 ? 
                                '<span class="text-xs px-2 py-1 rounded-full bg-yellow-100 text-yellow-800 print:hidden">Limit</span>' : 
                                ''}
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-3 py-1 text-xs font-medium rounded-full ${student.status === 'active' ? 'status-active' : 'status-inactive'}">
                            ${student.status === 'active' ? 'Active' : 'Inactive'}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${formatDate(student.joinDate)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${formatDate(student.dateCreated)} (${getAcademicYear(student.dateCreated)})
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                        <div class="flex space-x-2">
                            <button class="text-green-600 hover:text-green-900">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="text-blue-600 hover:text-blue-900">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="text-red-600 hover:text-red-900">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            // Update print summary with current filtered data
            updatePrintSummary(studentsList);
        }

        // Format date function
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        }

        // Set print date and year
        function setPrintInfo(selectedYear = 'All Years') {
            const now = new Date();
            const printDateElement = document.getElementById('printDate');
            const printYearElement = document.getElementById('printYear');
            
            printDateElement.textContent = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            printYearElement.textContent = selectedYear;
        }

        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            populateStudentsTable();
            setPrintInfo();
            
            const modal = document.getElementById('addStudentModal');
            const addStudentBtn = document.getElementById('addStudentBtn');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelModalBtn = document.getElementById('cancelModal');
            const form = document.getElementById('addStudentForm');
            const successToast = document.getElementById('successToast');
            
            // Print modal elements
            const printModal = document.getElementById('printOptionsModal');
            const printBtn = document.getElementById('printBtn');
            const closePrintModalBtn = document.getElementById('closePrintModal');
            const cancelPrintModalBtn = document.getElementById('cancelPrintModal');
            const printOptionsForm = document.getElementById('printOptionsForm');
            const yearSelect = document.getElementById('yearSelect');
            
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.relative').querySelector('input');
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                });
            });
            
            // Open add student modal
            addStudentBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });
            
            // Open print options modal
            printBtn.addEventListener('click', () => {
                printModal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });
            
            // Close modals
            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                form.reset();
            };
            
            const closePrintModal = () => {
                printModal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            };
            
            closeModalBtn.addEventListener('click', closeModal);
            cancelModalBtn.addEventListener('click', closeModal);
            closePrintModalBtn.addEventListener('click', closePrintModal);
            cancelPrintModalBtn.addEventListener('click', closePrintModal);
            
            // Close modals when clicking outside
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
                    closeModal();
                }
            });
            
            printModal.addEventListener('click', (e) => {
                if (e.target === printModal || e.target.classList.contains('modal-backdrop')) {
                    closePrintModal();
                }
            });
            
            // Form submission (add student)
            form.addEventListener('submit', (e) => {
                e.preventDefault();
                
                // Basic form validation
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match!');
                    return;
                }
                
                if (password.length < 8) {
                    alert('Password must be at least 8 characters long!');
                    return;
                }
                
                // In a real application, you would send data to the server here
                console.log('Student account creation submitted');
                
                // Show success toast
                successToast.classList.remove('hidden');
                
                // Close modal after 1 second
                setTimeout(() => {
                    closeModal();
                }, 1000);
                
                // Hide toast after 3 seconds
                setTimeout(() => {
                    successToast.classList.add('hidden');
                }, 3000);
            });
            
            // Print options form submission
            printOptionsForm.addEventListener('submit', (e) => {
                e.preventDefault();
                
                const selectedYear = yearSelect.value;
                const includeSummary = document.getElementById('includeSummary').checked;
                const includeHeaders = document.getElementById('includeHeaders').checked;
                const activeOnly = document.getElementById('activeOnly').checked;
                
                // Filter students based on selected year
                let filteredStudents = filterStudentsByYear(selectedYear);
                
                // Filter active only if selected
                if (activeOnly) {
                    filteredStudents = filteredStudents.filter(student => student.status === 'active');
                }
                
                // Update print info with selected year
                const yearDisplay = selectedYear === 'all' ? 'All Years' : selectedYear;
                setPrintInfo(yearDisplay);
                
                // Update table for printing
                populateStudentsTable(filteredStudents);
                
                // Close modal
                closePrintModal();
                
                // Wait a moment for DOM updates, then trigger print
                setTimeout(() => {
                    window.print();
                }, 100);
                
                // Reset table to full dataset after printing
                setTimeout(() => {
                    populateStudentsTable(students);
                    setPrintInfo();
                }, 500);
            });
            
            // Search functionality
            document.getElementById('searchStudents').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#studentsTableBody tr');
                
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            });
        });
    </script>
</body>
</html>