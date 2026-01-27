<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowed Books - Library Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #f5f7f0;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .page-wrapper {
            display: flex;
            flex: 1;
        }

        .sidebar {
            width: 250px;
            background-color: #2e7d32;
            color: white;
            flex-shrink: 0;
        }

        .main-content {
            flex: 1;
            padding: 24px;
            background: #f5f7f0;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }

        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }

        .header-section h1 {
            color: #2e7d32;
            font-size: 2.4rem;
            margin-bottom: 12px;
            position: relative;
            display: inline-block;
            padding-bottom: 14px;
        }

        .header-section h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 120px;
            height: 4px;
            background-color: #4caf50;
            border-radius: 2px;
        }

        /* Borrowed Books Card */
        .borrowed-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 28px rgba(46, 125, 50, 0.14);
            border-top: 5px solid #4caf50;
        }

        .borrowed-card h2 {
            color: #2e7d32;
            margin-bottom: 24px;
            text-align: center;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }

        .no-books {
            text-align: center;
            padding: 60px 20px;
            color: #777;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .no-books i {
            font-size: 3.5rem;
            color: #c8e6c9;
            margin-bottom: 20px;
        }

        /* Table Styles */
        .books-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
        }

        .books-table th,
        .books-table td {
            padding: 16px 18px;
            text-align: left;
            background: #fafefa;
        }

        .books-table th {
            background: #2e7d32;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
        }

        .books-table tr {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 10px;
            overflow: hidden;
        }

        .books-table td {
            border-top: 1px solid #e0f2e9;
            border-bottom: 1px solid #e0f2e9;
        }

        .books-table td:first-child {
            border-left: 4px solid #4caf50;
            border-top-left-radius: 10px;
            border-bottom-left-radius: 10px;
        }

        .books-table td:last-child {
            border-right: 4px solid #4caf50;
            border-top-right-radius: 10px;
            border-bottom-right-radius: 10px;
        }

        .status-overdue {
            color: #d32f2f;
            font-weight: 600;
        }

        .status-on-time {
            color: #2e7d32;
            font-weight: 600;
        }

        .book-title {
            font-weight: 600;
            color: #2e7d32;
        }

        @media (max-width: 992px) {
            .page-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
            }
            .main-content {
                padding: 20px 16px;
            }
        }

        @media (max-width: 768px) {
            .header-section h1 { font-size: 2.1rem; }
            .borrowed-card { padding: 28px 20px; }
        }

        @media (max-width: 600px) {
            .books-table thead { display: none; }
            .books-table tr {
                display: block;
                margin-bottom: 20px;
            }
            .books-table td {
                display: block;
                text-align: right;
                position: relative;
                padding-left: 50%;
                border: none;
                border-bottom: 1px solid #e0f2e9;
            }
            .books-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 18px;
                width: 45%;
                font-weight: 600;
                color: #2e7d32;
                text-align: left;
            }
            .books-table td:first-child {
                border-left: none;
                border-top: 4px solid #4caf50;
                border-radius: 10px 10px 0 0;
            }
            .books-table td:last-child {
                border-right: none;
                border-bottom: 4px solid #4caf50;
                border-radius: 0 0 10px 10px;
            }
        }
    </style>
</head>
<body>

    <div class="page-wrapper">

        <!-- Sidebar -->
        <aside class="sidebar">
            <?php include '../components/student_sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php include '../components/header.php'; ?>

            <div class="container">
                <div class="header-section">
                    <h1><i class="fas fa-book-reader"></i> My Borrowed Books</h1>
                    <p>View all books you currently have borrowed, including borrow date and due date.</p>
                </div>

                <div class="borrowed-card">
                    <h2><i class="fas fa-hand-holding-book"></i> Currently Borrowed</h2>

                    <!-- Example: when there are no books -->
                    <!-- 
                    <div class="no-books">
                        <i class="far fa-bookmark"></i>
                        <p>You don't have any borrowed books at the moment.<br>
                        Start browsing our collection!</p>
                    </div>
                    -->

                    <!-- Table with borrowed books -->
                    <table class="books-table">
                        <thead>
                            <tr>
                                <th>Book Title</th>
                                <th>Borrowed On</th>
                                <th>Due Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- These rows would come from PHP / database -->
                            <tr>
                                <td data-label="Book Title" class="book-title">Clean Code: A Handbook of Agile Software Craftsmanship</td>
                                <td data-label="Borrowed On">January 15, 2026</td>
                                <td data-label="Due Date">February 5, 2026</td>
                                <td data-label="Status"><span class="status-on-time">On Time</span></td>
                            </tr>

                            <tr>
                                <td data-label="Book Title" class="book-title">The Pragmatic Programmer (20th Anniversary Edition)</td>
                                <td data-label="Borrowed On">January 10, 2026</td>
                                <td data-label="Due Date">January 31, 2026</td>
                                <td data-label="Status"><span class="status-on-time">On Time</span></td>
                            </tr>

                            <tr>
                                <td data-label="Book Title" class="book-title">Introduction to Algorithms (3rd Edition)</td>
                                <td data-label="Borrowed On">December 20, 2025</td>
                                <td data-label="Due Date">January 20, 2026</td>
                                <td data-label="Status"><span class="status-overdue">Overdue</span></td>
                            </tr>

                            <!-- Add more rows dynamically with PHP loop -->
                        </tbody>
                    </table>
                </div>
            </div>

            
        </main>

    </div>
<?php include '../components/footer.php'; ?>
</body>
</html>