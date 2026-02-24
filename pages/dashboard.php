<?php
// dashboard.php
session_start();
require_once '../connection/dbconnection.php';   // this gives you $conn (mysqli)

// ─── Fetch Dashboard Statistics ─────────────────────────────────

// 1. Total Books (sum of quantity)
$result = mysqli_query($conn, "SELECT SUM(quantity) AS total FROM books");
$row = mysqli_fetch_assoc($result);
$total_books = $row['total'] ?? 0;

// 2. Total Users by type + grand total
$result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE profile_id = 2");
$row = mysqli_fetch_assoc($result);
$total_students = $row['cnt'] ?? 0;

$result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE profile_id = 3");
$row = mysqli_fetch_assoc($result);
$total_faculty = $row['cnt'] ?? 0;

$result = mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE profile_id = 4");
$row = mysqli_fetch_assoc($result);
$total_nonfaculty = $row['cnt'] ?? 0;

// Total users = students + faculty + non-faculty
$total_users = $total_students + $total_faculty + $total_nonfaculty;

// 3. Currently Borrowed
$query = "
    SELECT COUNT(*) AS cnt 
    FROM book_requests 
    WHERE request_type = 'borrow' 
    AND status IN ('approved', 'borrowed')
    AND (return_date IS NULL OR return_date > CURDATE())
";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$current_borrowed = $row['cnt'] ?? 0;

// 4. Books Returned This Week
$query = "
    SELECT COUNT(*) AS cnt 
    FROM book_requests 
    WHERE request_type = 'borrow' 
    AND status = 'returned'
    AND YEARWEEK(updated_at, 1) = YEARWEEK(CURDATE(), 1)
";
$result = mysqli_query($conn, $query);
$row = mysqli_fetch_assoc($result);
$returned_this_week = $row['cnt'] ?? 0;

// For chart - last 7 days borrowed & returned counts
$borrowed_last7 = [];
$returned_last7 = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));

    // Borrowed that day
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS cnt 
        FROM book_requests 
        WHERE request_type = 'borrow' 
        AND status IN ('approved','borrowed')
        AND DATE(request_date) = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    $borrowed_last7[] = (int)$count;
    mysqli_stmt_close($stmt);

    // Returned that day
    $stmt = mysqli_prepare($conn, "
        SELECT COUNT(*) AS cnt 
        FROM book_requests 
        WHERE request_type = 'borrow' 
        AND status = 'returned'
        AND DATE(updated_at) = ?
    ");
    mysqli_stmt_bind_param($stmt, "s", $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_bind_result($stmt, $count);
    mysqli_stmt_fetch($stmt);
    $returned_last7[] = (int)$count;
    mysqli_stmt_close($stmt);
}

// Placeholder percentage change
$total_books_last_month = 1200;
$change_books = $total_books > 0 ? round((($total_books - $total_books_last_month) / $total_books_last_month) * 100, 1) : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Management System Dashboard</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
        <div id="mainContent" class="main-content-expanded p-6 transition-all duration-300 overflow-y-auto flex-1">
            <div class="max-w-7xl mx-auto">
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
                                <p class="text-3xl font-bold text-green-600"><?= number_format($total_books) ?></p>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i><?= $change_books ?>%</span> from last month
                                </p>
                            </div>
                            <div class="bg-green-100 p-3 rounded-full">
                                <i class="fas fa-book text-green-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Users</h3>
                                <p class="text-3xl font-bold text-blue-600"><?= number_format($total_users) ?></p>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i>8%</span> from last month
                                </p>
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
                                <p class="text-3xl font-bold text-purple-600"><?= number_format($current_borrowed) ?></p>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span class="text-red-500"><i class="fas fa-arrow-down mr-1"></i>3%</span> from last week
                                </p>
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
                                <p class="text-3xl font-bold text-indigo-600"><?= number_format($returned_this_week) ?></p>
                                <p class="text-sm text-gray-500 mt-1">
                                    <span class="text-green-500"><i class="fas fa-arrow-up mr-1"></i>5%</span> this week
                                </p>
                            </div>
                            <div class="bg-indigo-100 p-3 rounded-full">
                                <i class="fas fa-sign-in-alt text-indigo-600 text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Chart + User Type Breakdown -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
                    <!-- Borrowing Activity Chart -->
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">Borrowing Activity – Last 7 Days</h3>
                        <div class="chart-container">
                            <canvas id="activityChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- User Types Breakdown (replaced Library Actions) -->
                    <div class="card bg-white p-6 rounded-xl shadow-md">
                        <h3 class="text-xl font-bold text-gray-800 mb-6">User Distribution</h3>
                        <div class="space-y-4">
                            <div class="flex items-center p-4 bg-blue-50 rounded-lg">
                                <div class="bg-blue-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-user-graduate text-white text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 text-lg">Students</h4>
                                    <p class="text-2xl font-bold text-blue-700"><?= number_format($total_students) ?></p>
                                    <p class="text-sm text-gray-600">Students</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-indigo-50 rounded-lg">
                                <div class="bg-indigo-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-chalkboard-teacher text-white text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 text-lg">Faculty</h4>
                                    <p class="text-2xl font-bold text-indigo-700"><?= number_format($total_faculty) ?></p>
                                    <p class="text-sm text-gray-600">Faculty</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center p-4 bg-purple-50 rounded-lg">
                                <div class="bg-purple-600 p-3 rounded-lg mr-4">
                                    <i class="fas fa-user-tie text-white text-xl"></i>
                                </div>
                                <div>
                                    <h4 class="font-medium text-gray-800 text-lg">Non-Faculty</h4>
                                    <p class="text-2xl font-bold text-purple-700"><?= number_format($total_nonfaculty) ?></p>
                                    <p class="text-sm text-gray-600">Non-Faculty</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('activityChart').getContext('2d');
            const activityChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    datasets: [
                        {
                            label: 'Books Borrowed',
                            data: <?= json_encode($borrowed_last7) ?>,
                            borderColor: '#4f46e5',
                            backgroundColor: 'rgba(79, 70, 229, 0.1)',
                            borderWidth: 2,
                            fill: true,
                            tension: 0.4
                        },
                        {
                            label: 'Books Returned',
                            data: <?= json_encode($returned_last7) ?>,
                            borderColor: '#2e7d32',
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