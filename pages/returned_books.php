<?php
// returned_books.php - Admin: Pending Returns + Full Return History
// + Increments books.quantity when return is APPROVED
session_start();
require_once '../connection/dbconnection.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../pages/login.php?error=Access denied");
    exit;
}

// Handle Approve / Reject Return
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action     = $_POST['action'];

    if ($action === 'return_approve') {
        $new_status = 'returned';
    } elseif ($action === 'return_reject') {
        $new_status = 'borrowed';
    } else {
        $new_status = null;
    }

    if ($new_status) {
        $conn->begin_transaction();

        try {
            // 1. Update book_requests status
            $stmt = $conn->prepare("UPDATE book_requests SET status = ?, updated_at = NOW() WHERE id = ? AND status = 'return_pending'");
            $stmt->bind_param("si", $new_status, $request_id);
            $stmt->execute();
            $updated = $stmt->affected_rows > 0;
            $stmt->close();

            if (!$updated) {
                throw new Exception("Hindi na-update ang request (hindi return_pending o walang binago).");
            }

            // 2. If APPROVED → return book → +1 sa quantity
            if ($action === 'return_approve') {
                // Get book_id
                $stmt = $conn->prepare("SELECT book_id FROM book_requests WHERE id = ?");
                $stmt->bind_param("i", $request_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $stmt->close();

                if (!$row || empty($row['book_id'])) {
                    throw new Exception("Hindi makita ang book_id.");
                }

                $book_id = (int)$row['book_id'];

                // Increment quantity
                $stmt = $conn->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
                $stmt->bind_param("i", $book_id);
                $stmt->execute();
                $book_updated = $stmt->affected_rows > 0;
                $stmt->close();

                if (!$book_updated) {
                    throw new Exception("Hindi na-increment ang quantity (baka hindi umiiral ang book ID).");
                }
            }

            $conn->commit();

            $msg_extra = ($action === 'return_approve') ? " Binayaran na ang libro (+1 sa quantity)." : "";
            $message = "<strong>Tagumpay!</strong> Na-update ang status sa <strong>" . ucfirst($new_status) . "</strong>.$msg_extra";

        } catch (Exception $e) {
            $conn->rollback();
            $message = "<strong>Error:</strong> " . $e->getMessage();
        }
    }
}

// ────────────────────────────────────────────────
// 1. Pending Return Requests
// ────────────────────────────────────────────────
$pending_requests = [];
$sql_pending = "
    SELECT
        r.id,
        s.student_id AS custom_student_id,
        CONCAT(s.first_name, ' ', COALESCE(s.middle_initial, ''), ' ', s.last_name) AS student_name,
        b.title AS book_title,
        r.updated_at AS returned_date,
        r.status
    FROM book_requests r
    LEFT JOIN students s ON r.student_id = s.user_id
    LEFT JOIN books b ON r.book_id = b.id
    WHERE r.status = 'return_pending'
    ORDER BY r.updated_at DESC
";
$result_pending = $conn->query($sql_pending);
if ($result_pending) {
    $pending_requests = $result_pending->fetch_all(MYSQLI_ASSOC);
}

// ────────────────────────────────────────────────
// 2. Return History
// ────────────────────────────────────────────────
$history_requests = [];
$sql_history = "
    SELECT
        r.id,
        s.student_id AS custom_student_id,
        CONCAT(s.first_name, ' ', COALESCE(s.middle_initial, ''), ' ', s.last_name) AS student_name,
        b.title AS book_title,
        r.updated_at AS last_updated,
        r.status
    FROM book_requests r
    LEFT JOIN students s ON r.student_id = s.user_id
    LEFT JOIN books b ON r.book_id = b.id
    WHERE r.status IN ('borrowed', 'return_pending', 'returned')
    ORDER BY r.updated_at DESC
";
$result_history = $conn->query($sql_history);
if ($result_history) {
    $history_requests = $result_history->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Admin - Return Requests & History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f7fa; color:#333; min-height:100vh; }
        .content-wrapper { margin-left:250px; padding:25px; flex:1; }
        .container { max-width:1300px; margin:0 auto; }
        header {
            background:linear-gradient(135deg,#2e7d32 0%,#1b5e20 100%);
            color:white;
            padding:20px 0;
            border-radius:8px;
            margin-bottom:25px;
            text-align:center;
        }
        h1 { font-size:28px; margin-bottom:8px; }
        h2 { font-size:24px; color:#1b5e20; margin:35px 0 15px; border-left:5px solid #2e7d32; padding-left:12px; }
        .table-container { background:white; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.07); margin-bottom:30px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:15px; text-align:left; }
        thead { background:#2e7d32; color:white; }
        th { font-weight:600; }
        tbody tr:hover { background:#f9f9f9; }
        .action-buttons { display:flex; gap:10px; flex-wrap:wrap; }
        .btn { padding:8px 16px; border-radius:6px; border:none; font-weight:600; cursor:pointer; transition:0.2s; }
        .btn-approve { background:#2e7d32; color:white; }
        .btn-approve:hover { background:#1b5e20; }
        .btn-reject  { background:#ef6c00; color:white; }
        .btn-reject:hover  { background:#d84315; }
        .status-badge { padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; }
        .status-borrowed  { background:#fff3cd; color:#856404; }
        .status-pending   { background:#e3f2fd; color:#1565c0; font-weight:700; }
        .status-returned  { background:#e8f5e9; color:#2e7d32; }
        .message { padding:12px 20px; margin:15px 0 25px; border-radius:6px; border-left:5px solid #2e7d32; background:#e8f5e9; }
        .message.error { border-left:5px solid #c62828; background:#ffebee; }
        .no-data { text-align:center; padding:60px 20px; color:#777; font-size:1.15rem; }
        .no-data i { font-size:3rem; color:#ccc; margin-bottom:12px; }
    </style>
</head>
<body>

    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="container">

            <header>
                <h1>Return Requests & History</h1>
            </header>

            <?php if (!empty($message)): ?>
                <div class="message <?= strpos($message, 'Error') !== false ? 'error' : '' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- TABLE 1: PENDING -->
            <h2>1. Pending Return Requests</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Book Title</th>
                            <th>Request Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pending_requests)): ?>
                            <tr><td colspan="6" class="no-data">
                                <i class="fas fa-inbox"></i><br>
                                Walang pending return requests ngayon.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $req): ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['custom_student_id'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($req['student_name'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= $req['returned_date'] ? date('M d, Y H:i', strtotime($req['returned_date'])) : '—' ?></td>
                                    <td><span class="status-badge status-pending">Return Pending</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="return_approve">
                                                <button type="submit" class="btn btn-approve">
                                                    <i class="fas fa-check"></i> Approve
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="return_reject">
                                                <button type="submit" class="btn btn-reject">
                                                    <i class="fas fa-times"></i> Reject
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

            <!-- TABLE 2: HISTORY -->
            <h2>2. Return History</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>Book Title</th>
                            <th>Last Updated</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_requests)): ?>
                            <tr><td colspan="5" class="no-data">
                                <i class="fas fa-history"></i><br>
                                Walang history pa sa return/borrowed books.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($history_requests as $req): ?>
                                <?php
                                $statusClass = match($req['status']) {
                                    'borrowed'      => 'status-borrowed',
                                    'return_pending'=> 'status-pending',
                                    'returned'      => 'status-returned',
                                    default         => ''
                                };
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['custom_student_id'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($req['student_name'] ?: '—') ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= $req['last_updated'] ? date('M d, Y H:i', strtotime($req['last_updated'])) : '—' ?></td>
                                    <td>
                                        <span class="status-badge <?= $statusClass ?>">
                                            <?= str_replace('_', ' ', ucwords($req['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', e => {
                const action = form.querySelector('[name="action"]').value;
                const msg = action === 'return_approve' ? 'approve' : 'reject';
                if (!confirm(`Sigurado ka bang gusto mong ${msg} ang return request?`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>