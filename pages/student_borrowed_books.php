<?php
// student_borrowed_books.php - My Borrowed Books + History with Admin Notes + Pagination for History
session_start();
require_once '../connection/dbconnection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../pages/login.php?error=Please log in first");
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// Handle return request
$return_success = false;
$return_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book'])) {
    $request_id = (int)$_POST['request_id'];
    $stmt = $conn->prepare("
        UPDATE book_requests
        SET status = 'return_pending',
            updated_at = NOW()
        WHERE id = ? AND student_id = ? AND status IN ('approved', 'borrowed')
    ");
    $stmt->bind_param("ii", $request_id, $user_id);
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        $return_success = true;
    } else {
        $return_error = "Failed to submit return request. It might already be processed.";
    }
    $stmt->close();
}

// Fetch currently borrowed books (including return_pending so student sees it until admin confirms)
$current_borrowed = [];
$stmt_current = $conn->prepare("
    SELECT
        br.id AS request_id,
        b.title AS book_title,
        br.request_date AS borrowed_on,
        DATE_ADD(br.request_date, INTERVAL 7 DAY) AS due_date,
        br.status
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.student_id = ?
      AND br.request_type = 'borrow'
      AND br.status IN ('approved', 'borrowed', 'return_pending')
    ORDER BY br.request_date DESC
");
if ($stmt_current) {
    $stmt_current->bind_param("i", $user_id);
    $stmt_current->execute();
    $result_current = $stmt_current->get_result();
    $current_borrowed = $result_current->fetch_all(MYSQLI_ASSOC);
    $stmt_current->close();
}

// Pagination for Borrowing History (5 items per page)
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Get total history count
$count_stmt = $conn->prepare("SELECT COUNT(*) AS total FROM book_requests WHERE student_id = ? AND request_type = 'borrow'");
$count_stmt->bind_param("i", $user_id);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = $total_rows > 0 ? ceil($total_rows / $per_page) : 0;

// Adjust page if out of bounds
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Fetch history with pagination
$history = [];
$stmt_history = $conn->prepare("
    SELECT
        b.title AS book_title,
        br.status,
        br.updated_at,
        br.admin_notes
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.student_id = ?
      AND br.request_type = 'borrow'
    ORDER BY br.updated_at DESC
    LIMIT ? OFFSET ?
");
if ($stmt_history) {
    $stmt_history->bind_param("iii", $user_id, $per_page, $offset);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    $history = $result_history->fetch_all(MYSQLI_ASSOC);
    $stmt_history->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Borrowed Books - Library Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
            max-width: 1100px;
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
            width: 140px;
            height: 4px;
            background-color: #4caf50;
            border-radius: 2px;
        }
        .borrowed-card {
            background: white;
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 8px 28px rgba(46, 125, 50, 0.14);
            border-top: 5px solid #4caf50;
            margin-bottom: 40px;
        }
        .borrowed-card h2 {
            color: #2e7d32;
            margin-bottom: 28px;
            text-align: center;
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
        }
        .no-books {
            text-align: center;
            padding: 80px 20px;
            color: #777;
            font-size: 1.2rem;
        }
        .no-books i {
            font-size: 4rem;
            color: #c8e6c9;
            margin-bottom: 20px;
        }
        .books-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 12px;
        }
        .books-table th, .books-table td {
            padding: 16px 20px;
            text-align: left;
            background: #fafefa;
        }
        .books-table th {
            background: #2e7d32;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.95rem;
            letter-spacing: 0.5px;
        }
        .books-table tr {
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-radius: 12px;
            overflow: hidden;
        }
        .books-table td {
            border-top: 1px solid #e0f2e9;
            border-bottom: 1px solid #e0f2e9;
        }
        .books-table td:first-child {
            border-left: 4px solid #4caf50;
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
        }
        .books-table td:last-child {
            border-right: 4px solid #4caf50;
            border-top-right-radius: 12px;
            border-bottom-right-radius: 12px;
        }
        .status-on-time {
            color: #2e7d32;
            font-weight: 600;
        }
        .status-overdue {
            color: #d32f2f;
            font-weight: 600;
        }
        .status-return-pending {
            color: #f59e0b;
            font-weight: 600;
        }
        .return-btn {
            background: #0288d1;
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.25s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .return-btn:hover {
            background: #0277bd;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(2, 136, 209, 0.3);
        }
        .return-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .history-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .history-table th, .history-table td {
            padding: 14px 18px;
            text-align: left;
        }
        .history-table th {
            background: #2e7d32;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        .history-table tr {
            background: #fafefa;
            border-radius: 10px;
        }
        .history-table td {
            border-top: 1px solid #e0f2e9;
            border-bottom: 1px solid #e0f2e9;
        }
        .admin-notes {
            max-width: 300px;
            word-wrap: break-word;
            font-size: 0.95em;
            color: #555;
        }
        .admin-notes:empty::before {
            content: '—';
            color: #aaa;
        }

        /* Pagination Styles */
        .pagination {
            text-align: center;
            margin: 30px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 10px 16px;
            margin: 0 6px;
            background-color: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.25s;
        }
        .pagination a:hover {
            background-color: #388e3c;
            transform: translateY(-2px);
        }
        .pagination .current {
            background-color: #2e7d32;
            cursor: default;
        }

        @media (max-width: 992px) {
            .page-wrapper { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-content { padding: 20px 16px; }
        }
        @media (max-width: 768px) {
            .header-section h1 { font-size: 2.1rem; }
            .borrowed-card { padding: 28px 20px; }
        }
        @media (max-width: 600px) {
            .books-table thead, .history-table thead { display: none; }
            .books-table tr, .history-table tr { display: block; margin-bottom: 20px; border: 2px solid #ddd; border-radius: 12px; }
            .books-table td, .history-table td { display: block; text-align: right; position: relative; padding-left: 50%; border: none; border-bottom: 1px solid #e0f2e9; }
            .books-table td:before, .history-table td:before {
                content: attr(data-label);
                position: absolute;
                left: 20px;
                width: 45%;
                font-weight: 600;
                color: #2e7d32;
                text-align: left;
            }
            .books-table td:first-child, .history-table td:first-child { border-top: 4px solid #4caf50; border-radius: 12px 12px 0 0; }
            .books-table td:last-child, .history-table td:last-child { border-bottom: 4px solid #4caf50; border-radius: 0 0 12px 12px; }
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
                    <p>View all books you currently have borrowed. You can request to return them anytime.</p>
                </div>

                <!-- Currently Borrowed -->
                <div class="borrowed-card">
                    <h2><i class="fas fa-hand-holding-book"></i> Currently Borrowed</h2>
                    <?php if (empty($current_borrowed)): ?>
                        <div class="no-books">
                            <i class="fas fa-book-open"></i><br>
                            You don't have any borrowed books at the moment.<br>
                            Start browsing our collection!
                        </div>
                    <?php else: ?>
                        <table class="books-table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Borrowed On</th>
                                    <th>Due Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($current_borrowed as $book):
                                    $due_date = new DateTime($book['due_date']);
                                    $today = new DateTime();
                                    $overdue = $today > $due_date;
                                    $status_text = $book['status'] === 'return_pending' ? 'Pending Return' : ($overdue ? 'Overdue' : 'On Time');
                                    $status_class = $book['status'] === 'return_pending' ? 'status-return-pending' : ($overdue ? 'status-overdue' : 'status-on-time');
                                ?>
                                    <tr>
                                        <td data-label="Book Title" class="book-title"><?= htmlspecialchars($book['book_title']) ?></td>
                                        <td data-label="Borrowed On"><?= date('F j, Y', strtotime($book['borrowed_on'])) ?></td>
                                        <td data-label="Due Date"><?= date('F j, Y', strtotime($book['due_date'])) ?></td>
                                        <td data-label="Status" class="<?= $status_class ?>">
                                            <?= $status_text ?>
                                        </td>
                                        <td data-label="Action">
                                            <?php if ($book['status'] !== 'return_pending'): ?>
                                                <form method="POST" style="display:inline;" id="returnForm_<?= $book['request_id'] ?>">
                                                    <input type="hidden" name="request_id" value="<?= $book['request_id'] ?>">
                                                    <input type="hidden" name="return_book" value="1">
                                                    <button type="button" class="return-btn" onclick="confirmReturn(<?= $book['request_id'] ?>)">
                                                        <i class="fas fa-undo"></i> Return Book
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <button class="return-btn" disabled>Pending Return</button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Borrowing History with Pagination -->
                <div class="borrowed-card">
                    <h2><i class="fas fa-history"></i> Borrowing History</h2>
                    <?php if (empty($history)): ?>
                        <div class="no-books">
                            <i class="fas fa-history"></i><br>
                            No borrowing history yet.
                        </div>
                    <?php else: ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Status</th>
                                    <th>Updated At</th>
                                    <th>Admin Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($history as $hist):
                                    $notes = $hist['admin_notes'];
                                    $notesDisplay = $notes ? htmlspecialchars(substr($notes, 0, 80)) . (strlen($notes) > 80 ? '...' : '') : '';
                                ?>
                                    <tr>
                                        <td data-label="Book Title" class="book-title"><?= htmlspecialchars($hist['book_title']) ?></td>
                                        <td data-label="Status"><?= ucfirst(str_replace('_', ' ', $hist['status'])) ?></td>
                                        <td data-label="Updated At"><?= $hist['updated_at'] ? date('F j, Y g:i A', strtotime($hist['updated_at'])) : '—' ?></td>
                                        <td data-label="Admin Notes" class="admin-notes" title="<?= htmlspecialchars($notes ?? '') ?>">
                                            <?= $notesDisplay ?: '—' ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>

                        <!-- Pagination Controls -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination">
                                <?php if ($page > 1): ?>
                                    <a href="?page=<?= $page - 1 ?>">&laquo; Previous</a>
                                <?php endif; ?>

                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <?php if ($i == $page): ?>
                                        <span class="current"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($page < $total_pages): ?>
                                    <a href="?page=<?= $page + 1 ?>">Next &raquo;</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php include '../components/footer.php'; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function confirmReturn(requestId) {
            Swal.fire({
                title: 'Return This Book?',
                text: "Admin will verify once you submit the return request.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#4caf50',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, Return It',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('returnForm_' + requestId).submit();
                }
            });
        }
        <?php if ($return_success): ?>
            Swal.fire({
                title: 'Return Requested!',
                text: 'Your return request has been submitted. Waiting for admin confirmation.',
                icon: 'success',
                confirmButtonColor: '#4caf50',
                timer: 3200
            });
        <?php endif; ?>
        <?php if ($return_error): ?>
            Swal.fire({
                title: 'Error',
                text: '<?= addslashes($return_error) ?>',
                icon: 'error',
                confirmButtonColor: '#d32f2f'
            });
        <?php endif; ?>
    </script>
</body>
</html>