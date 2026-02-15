<?php
// returned_books.php - Admin: Current Borrows & Return History with Role Display + Modal + Pagination on History
session_start();
require_once '../connection/dbconnection.php';
// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../pages/login.php?error=Access denied");
    exit;
}
// Handle Returned / Not Returned via POST from modal
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'], $_POST['admin_notes'])) {
    $request_id   = (int)$_POST['request_id'];
    $action       = $_POST['action']; // 'return_approve' or 'return_reject'
    $admin_notes  = trim($_POST['admin_notes']);
    // Get current status and book_id
    $stmt = $conn->prepare("SELECT status, book_id FROM book_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();
    if (!$current) {
        $message = "<strong>Error:</strong> Request not found.";
    } else {
        $current_status = $current['status'];
        $book_id = (int)$current['book_id'];
        $new_status = null;
        $do_increment = false;
        $valid_action = false;
        if ($action === 'return_approve' && in_array($current_status, ['approved', 'return_pending'])) {
            $new_status = 'returned';
            $do_increment = true;
            $valid_action = true;
        } elseif ($action === 'return_reject' && in_array($current_status, ['approved', 'return_pending'])) {
            $new_status = 'approved';
            $do_increment = false;
            $valid_action = true;
        }
        if ($valid_action) {
            $conn->begin_transaction();
            try {
                // Update book_requests
                $stmt = $conn->prepare("
                    UPDATE book_requests
                    SET status = ?, admin_notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ssi", $new_status, $admin_notes, $request_id);
                $stmt->execute();
                if ($stmt->affected_rows === 0) {
                    throw new Exception("No changes made to the request.");
                }
                $stmt->close();
                // Increment quantity if returned
                if ($do_increment) {
                    $stmt = $conn->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
                    $stmt->bind_param("i", $book_id);
                    $stmt->execute();
                    if ($stmt->affected_rows === 0) {
                        throw new Exception("Failed to increment book quantity.");
                    }
                    $stmt->close();
                }
                $conn->commit();
                if ($action === 'return_approve') {
                    $message = "<strong>Tagumpay!</strong> Na-confirm na <strong>naibalik na</strong> ang libro.";
                } else {
                    $message = "<strong>Tagumpay!</strong> Na-confirm na <strong>hindi pa naibalik</strong> ang libro (nanatiling borrowed).";
                }
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<strong>Error:</strong> " . htmlspecialchars($e->getMessage());
            }
        } else {
            $message = "<strong>Error:</strong> Invalid action for current status.";
        }
    }
}
// ────────────────────────────────────────────────
// 1. Active Borrows (approved + return_pending) - NO PAGINATION (usually not too many)
// ────────────────────────────────────────────────
$active_requests = [];
$sql_active = "
    SELECT
        r.id,
        r.status,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS member_name,
        CASE
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown'
        END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date,
        r.return_date
    FROM book_requests r
    JOIN users u ON r.student_id = u.id
    JOIN books b ON r.book_id = b.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status IN ('approved', 'return_pending')
    ORDER BY r.request_date DESC
";
$result_active = $conn->query($sql_active);
if ($result_active) {
    $active_requests = $result_active->fetch_all(MYSQLI_ASSOC);
}
// ────────────────────────────────────────────────
// 2. Return History (returned only) + PAGINATION (5 per page)
// ────────────────────────────────────────────────
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Get total count
$total_history = 0;
$count_query = $conn->query("SELECT COUNT(*) AS total FROM book_requests WHERE status = 'returned'");
if ($count_query) {
    $total_history = $count_query->fetch_assoc()['total'];
}
$total_pages = $total_history > 0 ? ceil($total_history / $per_page) : 0;

// Adjust page if out of bounds
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Fetch paginated history
$history_requests = [];
$sql_history = "
    SELECT
        r.id,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, ''))) AS member_name,
        CASE
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown'
        END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date,
        r.return_date,
        r.updated_at AS returned_date,
        r.admin_notes
    FROM book_requests r
    JOIN users u ON r.student_id = u.id
    JOIN books b ON r.book_id = b.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status = 'returned'
    ORDER BY r.updated_at DESC
    LIMIT $offset, $per_page
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
        .container { max-width:1400px; margin:0 auto; }
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
        th, td { padding:14px 15px; text-align:left; }
        thead { background:#2e7d32; color:white; }
        th { font-weight:600; }
        tbody tr:hover { background:#f9f9f9; }
        .btn { padding:8px 16px; border-radius:6px; border:none; font-weight:600; cursor:pointer; transition:0.2s; }
        .btn-approve { background:#2e7d32; color:white; }
        .btn-approve:hover { background:#1b5e20; }
        .btn-reject  { background:#ef6c00; color:white; }
        .btn-reject:hover  { background:#d84315; }
        .status-badge { padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; }
        .status-borrowed { background:#e3f2fd; color:#1565c0; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-returned  { background:#e8f5e9; color:#2e7d32; }
        .role-tag { color:#555; font-style:italic; font-size:0.9em; }
        .message { padding:12px 20px; margin:15px 0 25px; border-radius:6px; border-left:5px solid #2e7d32; background:#e8f5e9; }
        .message.error { border-left:5px solid #c62828; background:#ffebee; }
        .no-data { text-align:center; padding:60px 20px; color:#777; font-size:1.15rem; }
        .no-data i { font-size:3rem; color:#ccc; margin-bottom:12px; }
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 10px;
            width: 90%;
            max-width: 520px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.25);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .modal-header h3 { margin: 0; color: #1b5e20; }
        .close { font-size: 28px; cursor: pointer; color: #777; }
        .close:hover { color: #333; }
        .modal-body p { margin: 8px 0; }
        .modal-body strong { color: #2e7d32; }
        textarea {
            width: 100%;
            min-height: 100px;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            resize: vertical;
            font-size: 14px;
        }
        .modal-footer {
            margin-top: 20px;
            text-align: right;
        }
        .btn-modal { padding: 10px 20px; margin-left: 10px; }

        /* Pagination */
        .pagination {
            text-align: center;
            margin: 40px 0;
        }
        .pagination a, .pagination span {
            display: inline-block;
            padding: 10px 18px;
            margin: 0 6px;
            background-color: #2e7d32;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.25s;
        }
        .pagination a:hover {
            background-color: #1b5e20;
            transform: translateY(-2px);
        }
        .pagination .current {
            background-color: #1b5e20;
            cursor: default;
        }
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
            <!-- TABLE 1: ACTIVE (Borrowed + Pending Return) -->
            <h2>1. Current Borrows & Pending Returns</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Book Title</th>
                            <th>Borrowed On</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($active_requests)): ?>
                            <tr><td colspan="7" class="no-data">
                                <i class="fas fa-inbox"></i><br>
                                Walang active na borrow o pending return ngayon.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($active_requests as $req): ?>
                                <?php
                                $status_display = $req['status'] === 'approved' ? 'Borrowed' : 'Return Pending';
                                $status_class = $req['status'] === 'approved' ? 'status-borrowed' : 'status-pending';
                                $display_name = htmlspecialchars($req['member_name'] ?: '—');
                                if ($req['role'] !== 'Unknown') {
                                    $display_name .= ' <span class="role-tag">(' . htmlspecialchars($req['role']) . ')</span>';
                                }
                                $modal_name = htmlspecialchars($req['member_name'] ?: '—');
                                if ($req['role'] !== 'Unknown') {
                                    $modal_name .= ' (' . $req['role'] . ')';
                                }
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['member_id']) ?></td>
                                    <td><?= $display_name ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['borrow_date'])) ?></td>
                                    <td><?= $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—' ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $status_display ?></span></td>
                                    <td>
                                        <button class="btn btn-approve btn-modal"
                                            onclick="openModal(<?= $req['id'] ?>, 'approve', '<?= htmlspecialchars(addslashes($modal_name)) ?>', '<?= htmlspecialchars(addslashes($req['book_title'])) ?>')">
                                            <i class="fas fa-check"></i> Returned
                                        </button>
                                        <button class="btn btn-reject btn-modal"
                                            onclick="openModal(<?= $req['id'] ?>, 'reject', '<?= htmlspecialchars(addslashes($modal_name)) ?>', '<?= htmlspecialchars(addslashes($req['book_title'])) ?>')">
                                            <i class="fas fa-times"></i> Not Returned
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <!-- TABLE 2: RETURN HISTORY with Pagination -->
            <h2>2. Return History</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Book Title</th>
                            <th>Borrowed On</th>
                            <th>Returned On</th>
                            <th>Due Date</th>
                            <th>Admin Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_requests)): ?>
                            <tr><td colspan="7" class="no-data">
                                <i class="fas fa-history"></i><br>
                                Walang return history pa.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($history_requests as $req): ?>
                                <?php
                                $display_name = htmlspecialchars($req['member_name'] ?: '—');
                                if ($req['role'] !== 'Unknown') {
                                    $display_name .= ' <span class="role-tag">(' . htmlspecialchars($req['role']) . ')</span>';
                                }
                                $notesDisplay = $req['admin_notes'] ? htmlspecialchars(substr($req['admin_notes'], 0, 80)) . (strlen($req['admin_notes']) > 80 ? '...' : '') : '—';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['member_id']) ?></td>
                                    <td><?= $display_name ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['borrow_date'])) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['returned_date'])) ?></td>
                                    <td><?= $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—' ?></td>
                                    <td title="<?= htmlspecialchars($req['admin_notes'] ?: '') ?>"><?= $notesDisplay ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination for History -->
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
        </div>
    </div>
    <!-- MODAL -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirm Action</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p><strong>Member:</strong> <span id="modalStudent"></span></p>
                <p><strong>Book:</strong> <span id="modalBook"></span></p>
                <p><strong>Action:</strong> <span id="modalAction"></span></p>
                <label for="adminNotes"><strong>Admin Notes / Reason:</strong></label>
                <textarea name="admin_notes" id="adminNotes" placeholder="Optional: maglagay ng remarks o paliwanag..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Cancel</button>
                <form id="modalForm" method="POST" style="display:inline;">
                    <input type="hidden" name="request_id" id="modalRequestId">
                    <input type="hidden" name="action" id="modalActionInput">
                    <input type="hidden" name="admin_notes" id="modalNotesHidden">
                    <button type="submit" id="modalConfirmBtn" class="btn">Confirm</button>
                </form>
            </div>
        </div>
    </div>
    <?php include '../components/footer.php'; ?>
    <script>
        const modal = document.getElementById('actionModal');
        function openModal(requestId, action, member, book) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalActionInput').value = 'return_' + action;
            document.getElementById('modalStudent').textContent = member;
            document.getElementById('modalBook').textContent = book;
            if (action === 'approve') {
                document.getElementById('modalTitle').textContent = 'Confirm Returned';
                document.getElementById('modalAction').textContent = 'RETURNED';
                document.getElementById('modalConfirmBtn').textContent = 'Confirm Returned';
                document.getElementById('modalConfirmBtn').className = 'btn btn-approve';
            } else {
                document.getElementById('modalTitle').textContent = 'Confirm Not Returned';
                document.getElementById('modalAction').textContent = 'NOT RETURNED';
                document.getElementById('modalConfirmBtn').textContent = 'Confirm Not Returned';
                document.getElementById('modalConfirmBtn').className = 'btn btn-reject';
            }
            document.getElementById('adminNotes').value = '';
            modal.style.display = 'flex';
        }
        function closeModal() {
            modal.style.display = 'none';
        }
        document.getElementById('modalForm').addEventListener('submit', function(e) {
            document.getElementById('modalNotesHidden').value = document.getElementById('adminNotes').value;
            const action = document.getElementById('modalActionInput').value;
            const msg = action.includes('approve') ? 'i-confirm na RETURNED' : 'i-confirm na NOT RETURNED';
            if (!confirm(`Sigurado ka bang gusto mong ${msg} ang libro?`)) {
                e.preventDefault();
            }
        });
        window.onclick = function(event) {
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>