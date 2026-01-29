<?php
// admin_book_requests.php - Manage Pending + Full History (with ID column)
session_start();
require_once '../connection/dbconnection.php';

// Simple admin check (palitan mo ng proper role check later)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) { // admin = user_id 1
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

// Fetch PENDING requests only (main table - with ID)
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

// Fetch ALL requests (for history section)
$all_requests = [];
$stmt_all = $conn->prepare("
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
    ORDER BY br.created_at DESC
");
if ($stmt_all) {
    $stmt_all->execute();
    $result_all = $stmt_all->get_result();
    $all_requests = $result_all->fetch_all(MYSQLI_ASSOC);
    $stmt_all->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Book Requests</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f7f0; color:#333; min-height:100vh; }
       
        .app-wrapper { display:flex; min-height:100vh; }
        .sidebar {
            width:250px;
            background:#2e7d32;
            color:white;
            position:fixed;
            height:100vh;
            overflow-y:auto;
            z-index:1000;
            box-shadow:2px 0 10px rgba(0,0,0,0.15);
        }
        .main-content {
            margin-left:250px;
            flex:1;
            padding:30px;
        }
        .container { max-width:1400px; margin:0 auto; }
        .header { text-align:center; margin-bottom:40px; }
        .header h1 { color:#2e7d32; font-size:2.5rem; margin-bottom:10px; }
        .header p { color:#555; font-size:1.1rem; }
        .message {
            padding:15px;
            margin:25px 0;
            border-radius:8px;
            text-align:center;
            font-weight:600;
        }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:5px solid #4caf50; }
        .message.error { background:#ffebee; color:#c62828; border-left:5px solid #c62828; }
        .search-box {
            margin-bottom:25px;
            max-width:500px;
        }
        .search-box input {
            width:100%;
            padding:14px 20px 14px 50px;
            border:2px solid #c8e6c9;
            border-radius:10px;
            font-size:1rem;
            background:#fff url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%232e7d32" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>') no-repeat 15px center;
        }
        .table-container {
            overflow-x:auto;
            border-radius:12px;
            box-shadow:0 4px 15px rgba(0,0,0,0.1);
            background:white;
            margin-bottom:40px;
        }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:16px 20px; text-align:left; }
        th { background:#2e7d32; color:white; font-weight:600; text-transform:uppercase; font-size:0.95rem; }
        tr { border-bottom:1px solid #eee; transition:background 0.2s; }
        tr:hover { background:#f9fdf9; }
        .status-pending { color:#f59e0b; font-weight:600; }
        .status-approved { color:#2e7d32; font-weight:600; }
        .status-rejected { color:#c62828; font-weight:600; }
        .action-btn {
            padding:8px 14px;
            border:none;
            border-radius:6px;
            cursor:pointer;
            font-weight:600;
            margin-right:8px;
            transition:all 0.2s;
        }
        .approve-btn { background:#4caf50; color:white; }
        .approve-btn:hover { background:#43a047; }
        .reject-btn { background:#f44336; color:white; }
        .reject-btn:hover { background:#e53935; }
        .btn-disabled { background:#ccc !important; cursor:not-allowed; }
        .no-data {
            text-align:center;
            padding:60px 20px;
            color:#777;
            font-size:1.2rem;
        }
        .no-data i { font-size:3.5rem; color:#c8e6c9; margin-bottom:15px; }
        .history-section {
            background:white;
            border-radius:12px;
            padding:25px;
            box-shadow:0 4px 15px rgba(0,0,0,0.1);
        }
        .history-section h2 {
            color:#2e7d32;
            margin-bottom:20px;
            font-size:1.8rem;
        }
        @media (max-width:992px) {
            .sidebar { transform:translateX(-100%); }
            .main-content { margin-left:0; }
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>  <!-- Admin sidebar -->

    <div class="app-wrapper">
        <div class="main-content">
            <div class="container">
                <div class="header">
                    <h1><i class="fas fa-book-medical"></i> Manage Book Requests</h1>
                    <p>Approve or reject pending student book requests</p>
                </div>

                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search by student ID (e.g., 2025-12314) or book title...">
                </div>

                <div class="table-container">
                    <table id="requestsTable">
                        <thead>
                            <tr>
                                <th>ID</th> <!-- Balik na yung ID column -->
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Book Title</th>
                                <th>Author</th>
                                <th>ISBN</th>
                                <th>Notes</th>
                                <th>Request Date</th>
                                <th>Status</th>
                                <th>Created At</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($pending_requests)): ?>
                                <tr>
                                    <td colspan="11" class="no-data">
                                        <i class="fas fa-inbox"></i><br>
                                        No pending book requests.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($pending_requests as $req): ?>
                                    <tr data-search="<?= strtolower($req['student_name'] . ' ' . $req['book_title'] . ' ' . $req['display_student_id']) ?>">
                                        <td><?= $req['id'] ?></td> <!-- ID column balik na -->
                                        <td><?= htmlspecialchars($req['display_student_id'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['student_name'] ?: 'Unknown') ?></td>
                                        <td><?= htmlspecialchars($req['book_title']) ?></td>
                                        <td><?= htmlspecialchars($req['author'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['isbn'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['notes'] ?: '—') ?></td>
                                        <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                                        <td><span class="status-pending">Pending</span></td>
                                        <td><?= date('M d, Y g:i A', strtotime($req['created_at'])) ?></td>
                                        <td>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="action-btn approve-btn">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="action-btn reject-btn">
                                                    <i class="fas fa-times"></i> Reject
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Message - lalabas dito pag may action -->
                <?php if ($message): ?>
                    <div class="message <?= strpos($message, 'successfully') !== false ? 'success' : 'error' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>

                <!-- History Section - Lahat ng requests (pending, approved, rejected) -->
                <div class="history-section">
                    <h2><i class="fas fa-history"></i> History of All Book Requests</h2>
                    <div class="table-container">
                        <table>
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
                                <?php if (empty($all_requests)): ?>
                                    <tr>
                                        <td colspan="7" class="no-data">
                                            <i class="fas fa-inbox"></i><br>
                                            No request history yet.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($all_requests as $req): ?>
                                        <tr>
                                            <td><?= $req['id'] ?></td>
                                            <td><?= htmlspecialchars($req['display_student_id'] ?: '—') ?></td>
                                            <td><?= htmlspecialchars($req['student_name'] ?: 'Unknown') ?></td>
                                            <td><?= htmlspecialchars($req['book_title']) ?></td>
                                            <td><?= date('M d, Y', strtotime($req['request_date'])) ?></td>
                                            <td>
                                                <span class="status-<?= $req['status'] ?>">
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
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Live search for main table (pending only)
        document.getElementById('searchInput').addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('#requestsTable tbody tr');
            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // Confirm before approve/reject
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const action = this.querySelector('[name="action"]').value;
                const msg = action === 'approve' ? 'approve' : 'reject';
                if (!confirm(`Are you sure you want to ${msg} this request?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>