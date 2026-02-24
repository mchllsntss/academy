<?php
// admin_book_requests.php - updated: history shows only approved & rejected
session_start();
require_once '../connection/dbconnection.php';

// Simple admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../pages/login.php?error=Admin access only");
    exit;
}

// Handle approve/reject
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve') {
        $stmt = $conn->prepare("UPDATE books_requests SET status = 'approved' WHERE id = ? AND status = 'pending'");
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE books_requests SET status = 'rejected' WHERE id = ? AND status = 'pending'");
    }

    if (isset($stmt)) {
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $message = "Request " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully!";
        } else {
            $message = "Failed to update request.";
        }
        $stmt->close();
    }
}

// Fetch PENDING requests (unchanged)
$pending_requests = [];
$stmt_pending = $conn->prepare("
    SELECT
        br.id,
        s.student_id AS display_student_id,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        br.book_title,
        br.author,
        br.isbn,
        br.notes,
        br.request_date,
        br.status,
        br.created_at
    FROM books_requests br
    LEFT JOIN students s ON br.student_id = s.user_id
    WHERE br.status = 'pending'
    ORDER BY br.created_at DESC
");
if ($stmt_pending) {
    $stmt_pending->execute();
    $result_pending = $stmt_pending->get_result();
    $pending_requests = $result_pending->fetch_all(MYSQLI_ASSOC);
    $stmt_pending->close();
}

// Pagination for HISTORY (only approved + rejected)
$per_page = 8;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$total_history = 0;
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total 
    FROM books_requests 
    WHERE status IN ('approved', 'rejected')
");
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_history = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = $total_history > 0 ? ceil($total_history / $per_page) : 0;

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$history_requests = [];
$stmt_history = $conn->prepare("
    SELECT
        br.id,
        s.student_id AS display_student_id,
        CONCAT(s.first_name, ' ', s.last_name) AS student_name,
        br.book_title,
        br.author,
        br.isbn,
        br.notes,
        br.request_date,
        br.status,
        br.created_at
    FROM books_requests br
    LEFT JOIN students s ON br.student_id = s.user_id
    WHERE br.status IN ('approved', 'rejected')
    ORDER BY br.created_at DESC
    LIMIT ? OFFSET ?
");
if ($stmt_history) {
    $stmt_history->bind_param("ii", $per_page, $offset);
    $stmt_history->execute();
    $result_history = $stmt_history->get_result();
    $history_requests = $result_history->fetch_all(MYSQLI_ASSOC);
    $stmt_history->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Book Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f8fafc; color:#1e293b; }
        .app-wrapper { display:flex; min-height:100vh; }
        .main-content { margin-left:250px; flex:1; padding:2rem; }
        .container { max-width:1400px; margin:0 auto; }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        .header h1 { color: #15803d; font-size: 2.1rem; }

        .history-toggle {
            background: #15803d;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.8rem 1.3rem;
            font-size: 1rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 0.6rem;
            transition: all 0.2s;
        }
        .history-toggle:hover { background: #166534; transform: translateY(-1px); }

        .search-container {
            margin: 1.2rem 0 1.8rem;
            position: relative;
            max-width: 420px;
        }
        .search-container input {
            width: 100%;
            padding: 12px 16px 12px 48px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            background: white url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>') no-repeat 16px center;
            background-size: 20px;
        }
        .search-container input:focus {
            outline: none;
            border-color: #15803d;
            box-shadow: 0 0 0 3px rgba(21,128,61,0.15);
        }

        .message {
            padding: 1rem;
            margin: 1rem 0 2rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }
        .message.success { background:#dcfce7; color:#166534; border-left:5px solid #22c55e; }
        .message.error   { background:#fee2e2; color:#b91c1c; border-left:5px solid #ef4444; }

        .table-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2.5rem;
        }
        table { width:100%; border-collapse:collapse; }
        th, td { padding: 14px 18px; text-align:left; }
        th {
            background: #15803d;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
        }
        tr { border-bottom:1px solid #f1f5f9; }
        tr:hover { background:#f8fafc; }

        .status-pending  { color:#d97706; font-weight:600; }
        .status-approved { color:#15803d; font-weight:600; }
        .status-rejected { color:#b91c1c; font-weight:600; }

        .action-buttons { display: flex; gap: 0.6rem; }
        .action-btn {
            width: 38px;
            height: 38px;
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .action-btn.approve { background: #22c55e; }
        .action-btn.reject  { background: #ef4444; }
        .action-btn:hover.approve { background: #16a34a; transform: scale(1.08); }
        .action-btn:hover.reject  { background: #dc2626; transform: scale(1.08); }

        .no-data {
            text-align:center;
            padding: 5rem 1rem;
            color: #64748b;
            font-size: 1.3rem;
        }
        .no-data i { font-size: 3.8rem; color:#e2e8f0; margin-bottom:1rem; }

        .history-section {
            display: none;
            background: white;
            border-radius: 12px;
            padding: 1.8rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-top: 2.5rem;
        }
        .history-section.active { display: block; }

        .history-section h2 {
            color: #15803d;
            margin-bottom: 1.4rem;
            font-size: 1.7rem;
        }

        .pagination {
            text-align: center;
            margin: 2.5rem 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 10px 16px;
            margin: 0 5px;
            background: #15803d;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }
        .pagination a:hover { background: #166534; }
        .pagination .current { background: #14532d; cursor: default; }

        @media (max-width: 992px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>

    <div class="app-wrapper">
        <div class="main-content">
            <div class="container">

                <div class="header">
                    <h1><i class="fas fa-book-medical"></i> Book Requests</h1>
                    <button class="history-toggle" id="toggleHistory">
                        <i class="fas fa-history"></i> View History
                    </button>
                </div>

                <?php if ($message): ?>
                    <div class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- PENDING REQUESTS -->
                <div class="search-container">
                    <input type="text" id="searchPending" placeholder="Search pending requests (student ID, name, book title)...">
                </div>

                <div class="table-container">
                    <table id="pendingTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_requests)): ?>
                                <tr>
                                    <td colspan="8" class="no-data">
                                        <i class="fas fa-inbox"></i><br>
                                        No pending requests at the moment.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr class="search-row" 
                                        data-search="<?= strtolower($req['display_student_id'] . ' ' . $req['student_name'] . ' ' . $req['book_title']) ?>">
                                        <td><?= $req['id'] ?></td>
                                        <td><?= htmlspecialchars($req['display_student_id'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['student_name'] ?: 'Unknown') ?></td>
                                        <td><?= htmlspecialchars($req['book_title']) ?></td>
                                        <td><?= htmlspecialchars($req['author'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['isbn'] ?: '—') ?></td>
                                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="action-btn approve" title="Approve">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="action-btn reject" title="Reject">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- HISTORY SECTION (only approved & rejected) -->
                <div class="history-section" id="historySection">
                    <h2><i class="fas fa-history"></i> Request History (Approved & Rejected)</h2>

                    <div class="search-container">
                        <input type="text" id="searchHistory" placeholder="Search history (student ID, name, book title)...">
                    </div>

                    <div class="table-container">
                        <table id="historyTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Book Title</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($history_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">
                                            <i class="fas fa-inbox"></i><br>
                                            No approved or rejected requests yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($history_requests as $req): ?>
                                        <tr class="search-row"
                                            data-search="<?= strtolower($req['display_student_id'] . ' ' . $req['student_name'] . ' ' . $req['book_title']) ?>">
                                            <td><?= $req['id'] ?></td>
                                            <td><?= htmlspecialchars($req['display_student_id'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($req['student_name'] ?: 'Unknown') ?></td>
                                            <td><?= htmlspecialchars($req['book_title']) ?></td>
                                            <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                                            <td>
                                                <span class="status-<?= strtolower($req['status']) ?>">
                                                    <?= ucfirst($req['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?= $page - 1 ?>">&laquo; Prev</a>
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
                </div>

            </div>
        </div>
    </div>

    <script>
        // Toggle History
        document.getElementById('toggleHistory').addEventListener('click', function() {
            const section = document.getElementById('historySection');
            section.classList.toggle('active');
            this.innerHTML = section.classList.contains('active') 
                ? '<i class="fas fa-history"></i> Hide History' 
                : '<i class="fas fa-history"></i> View History';
        });

        // Live search - Pending
        const searchPending = document.getElementById('searchPending');
        if (searchPending) {
            searchPending.addEventListener('input', function() {
                const filter = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#pendingTable .search-row');
                rows.forEach(row => {
                    const text = row.getAttribute('data-search') || '';
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }

        // Live search - History
        const searchHistory = document.getElementById('searchHistory');
        if (searchHistory) {
            searchHistory.addEventListener('input', function() {
                const filter = this.value.toLowerCase().trim();
                const rows = document.querySelectorAll('#historyTable .search-row');
                rows.forEach(row => {
                    const text = row.getAttribute('data-search') || '';
                    row.style.display = text.includes(filter) ? '' : 'none';
                });
            });
        }

        // Confirm approve/reject
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('[name="action"]').value;
                const msg = action === 'approve' ? 'APPROVE' : 'REJECT';
                if (!confirm(`Are you sure you want to ${msg} this request?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>