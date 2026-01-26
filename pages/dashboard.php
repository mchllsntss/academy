<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System Dashboard</title>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            overflow-x: hidden;
        }
        
        .card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    <div class="min-h-screen flex flex-col">        
        <!-- Dashboard Content -->
        <div id="mainContent" class="main-content-expanded p-6 transition-all duration-300 overflow-y-auto flex-1">
            <div class="max-w-7xl mx-auto">
                <!-- Welcome Message -->
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-gray-800 mb-2">Library Management Dashboard</h1>
                    <p class="text-gray-600">Overview of library statistics and activities</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Books</h3>
                                <p class="text-3xl font-bold text-green-600">1,254</p>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i>12%</span> from last month</p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-book text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Students</h3>
                                <p class="text-3xl font-bold text-blue-600">342</p>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i>8%</span> from last month</p>
                            </div>
                            <div class="bg-blue-100 p-3 rounded-full">
                                <i class="fas fa-users text-blue-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Books Borrowed</h3>
                                <p class="text-3xl font-bold text-purple-600">89</p>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-red-500"><i class="fas fa-arrow-down mr-1"></i>3%</span> from last week</p>
                            </div>
                            <div class="bg-purple-100 p-3 rounded-full">
                                <i class="fas fa-sign-out-alt text-purple-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Books Returned</h3>
                                <p class="text-3xl font-bold text-indigo-600">76</p>
                                <p class="text-sm text-gray-500 mt-1"><span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i>5%</span> from last week</p>
                            </div>
                            <div class="bg-indigo-100 p-3 rounded-full">
                                <i class="fas fa-sign-in-alt text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Additional Info -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Borrowing Activity Chart -->
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Borrowing Activity This Week</h3>
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Library Actions</h3>
                        <div class="space-y-4">
                            <div class="flex items-center p-4 bg-indigo-50 rounded-lg">
                                <div class="bg-indigo-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-plus text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Add New Book</h4>
                                    <p class="text-sm text-gray-600">Add a new book to the library catalog</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-green-50 rounded-lg">
                                <div class="bg-green-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-user-plus text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Register Student</h4>
                                    <p class="text-sm text-gray-600">Register a new student member</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-blue-50 rounded-lg">
                                <div class="bg-blue-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-exchange-alt text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Borrow Book</h4>
                                    <p class="text-sm text-gray-600">Process a book borrowing transaction</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-purple-50 rounded-lg">
                                <div class="bg-purple-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-print text-white"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800">Generate Report</h4>
                                    <p class="text-sm text-gray-600">Generate monthly library report</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Info -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Summary Section -->
                <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-8">
                </div>
            </div>
        </div>
        
        <!-- Simple Footer -->
        <footer class="bg-white border-t py-4 px-6 mt-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-gray-600 text-sm">© 2023 Library Management System. All rights reserved.</p>
                    <p class="text-gray-500 text-sm mt-2 md:mt-0">Dashboard Version 2.1 • Last updated: Today, 11:45 AM</p>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // Initialize chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: 'Books Borrowed',
                            data: [12, 19, 15, 25, 22, 30, 28],
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Books Returned',
                            data: [8, 12, 10, 18, 15, 20, 16],
                            borderColor: '#10b981',
                            backgroundColor: 'rgba(16, 185, 129, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                display: true,
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
    </script>
</body>
</html>