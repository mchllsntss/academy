<?php
// returned_books.php - Admin: Current Borrows & Return History + Overdue Fine + Damage Option
session_start();
require_once '../connection/dbconnection.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../pages/login.php?error=Access denied");
    exit;
}

define('FINE_PER_DAY', 5.00); // ₱5 per day overdue

// Handle Returned / Not Returned via POST from modal
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'], $_POST['admin_notes'])) {
    $request_id     = (int)$_POST['request_id'];
    $action         = $_POST['action']; // 'return_approve' or 'return_reject'
    $admin_notes    = trim($_POST['admin_notes'] ?? '');
    $is_damaged     = isset($_POST['is_damaged']) && $_POST['is_damaged'] === '1';
    $damage_fine    = $is_damaged ? (float)($_POST['damage_fine'] ?? 0) : 0.00;

    // Get current data
    $stmt = $conn->prepare("
        SELECT status, book_id, return_date 
        FROM book_requests 
        WHERE id = ?
    ");
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
        $due_date = $current['return_date'] ? new DateTime($current['return_date']) : null;
        $new_status = null;
        $do_increment = false;
        $overdue_fine = 0.00;
        $total_fine   = 0.00;
        $valid_action = false;

        if ($action === 'return_approve' && in_array($current_status, ['approved', 'return_pending'])) {
            $new_status = 'returned';
            $do_increment = true;
            $valid_action = true;

            // Calculate overdue fine
            if ($due_date) {
                $today = new DateTime();
                $interval = $today->diff($due_date);
                if ($interval->invert) { // overdue
                    $overdue_fine = $interval->days * FINE_PER_DAY;
                }
            }

            $total_fine = $overdue_fine + $damage_fine;

            // Append damage info to notes
            if ($is_damaged) {
                $damage_note = "Damaged book reported – additional fine ₱" . number_format($damage_fine, 2);
                $admin_notes = $admin_notes ? $admin_notes . "\n" . $damage_note : $damage_note;
            }
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
                    SET status = ?,
                        admin_notes = ?,
                        fine = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param("ssdi", $new_status, $admin_notes, $total_fine, $request_id);
                $stmt->execute();
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
                    if ($total_fine > 0) {
                        $msg = $is_damaged ? " (may damage + overdue)" : " (overdue)";
                        $message = "<strong>Tagumpay!</strong> Naibalik na ang libro — May multa na ₱" . number_format($total_fine, 2) . $msg . ".";
                    } else {
                        $message = "<strong>Tagumpay!</strong> Na-confirm na <strong>naibalik na</strong> ang libro (walang multa).";
                    }
                } else {
                    $message = "<strong>Tagumpay!</strong> Na-confirm na <strong>hindi pa naibalik</strong> ang libro.";
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
// 1. Active Borrows (approved + return_pending)
// ────────────────────────────────────────────────
$active_requests = [];
$sql_active = "
    SELECT
        r.id, r.status, r.return_date,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS member_name,
        CASE WHEN u.profile_id = 2 THEN 'Student'
             WHEN u.profile_id = 3 THEN 'Faculty'
             WHEN u.profile_id = 4 THEN 'Non-Faculty'
             ELSE 'Unknown' END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date,
        r.updated_at
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
    while ($row = $result_active->fetch_assoc()) {
        $fine_display = 0;
        $days_overdue = 0;
        if ($row['return_date']) {
            $due = new DateTime($row['return_date']);
            $today = new DateTime();
            if ($today > $due) {
                $interval = $today->diff($due);
                $days_overdue = $interval->days;
                $fine_display = $days_overdue * FINE_PER_DAY;
            }
        }
        $row['days_overdue'] = $days_overdue;
        $row['fine_display'] = $fine_display;
        $active_requests[] = $row;
    }
}

// ────────────────────────────────────────────────
// 2. Return History (returned only) + Pagination
// ────────────────────────────────────────────────
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$total_history = $conn->query("SELECT COUNT(*) AS total FROM book_requests WHERE status = 'returned'")
                     ->fetch_assoc()['total'] ?? 0;
$total_pages = $total_history > 0 ? ceil($total_history / $per_page) : 0;

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$history_requests = [];
$sql_history = "
    SELECT
        r.id,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS member_name,
        CASE WHEN u.profile_id = 2 THEN 'Student'
             WHEN u.profile_id = 3 THEN 'Faculty'
             WHEN u.profile_id = 4 THEN 'Non-Faculty'
             ELSE 'Unknown' END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date,
        r.return_date,
        r.updated_at AS returned_date,
        r.admin_notes,
        r.fine
    FROM book_requests r
    JOIN users u ON r.student_id = u.id
    JOIN books b ON r.book_id = b.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status = 'returned'
    ORDER BY r.updated_at DESC
    LIMIT ?, ?
";
$stmt_history = $conn->prepare($sql_history);
$stmt_history->bind_param("ii", $offset, $per_page);
$stmt_history->execute();
$history_requests = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_history->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Admin - Borrows & Returns</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f7fa; color:#333; min-height:100vh; }
        .content-wrapper { margin-left:250px; padding:25px; flex:1; }
        .container { max-width:1440px; margin:0 auto; }
        header { background:linear-gradient(135deg,#2e7d32 0%,#1b5e20 100%); color:white; padding:20px; border-radius:8px; margin-bottom:25px; text-align:center; }
        h1 { font-size:28px; margin-bottom:8px; }
        h2 { font-size:24px; color:#1b5e20; margin:40px 0 16px; border-left:5px solid #2e7d32; padding-left:12px; }
        .table-container { background:white; border-radius:10px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,0.08); margin-bottom:35px; overflow-x:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:14px 16px; text-align:left; }
        thead { background:#2e7d32; color:white; }
        th { font-weight:600; }
        tbody tr:hover { background:#f9f9f9; }
        .btn { padding:8px 16px; border-radius:6px; border:none; font-weight:600; cursor:pointer; }
        .btn-approve { background:#2e7d32; color:white; }
        .btn-approve:hover { background:#1b5e20; }
        .btn-reject { background:#ef6c00; color:white; }
        .btn-reject:hover { background:#d84315; }
        .status-badge { padding:6px 12px; border-radius:20px; font-size:13px; font-weight:600; }
        .status-borrowed { background:#e3f2fd; color:#1565c0; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-returned { background:#e8f5e9; color:#2e7d32; }
        .fine-warning { color:#d32f2f; font-weight:600; }
        .fine-zero { color:#555; }
        .role-tag { color:#555; font-style:italic; font-size:0.92em; }
        .message { padding:12px 20px; margin:15px 0 25px; border-radius:6px; border-left:5px solid #2e7d32; background:#e8f5e9; }
        .message.error { border-left:5px solid #c62828; background:#ffebee; }
        .no-data { text-align:center; padding:70px 20px; color:#777; font-size:1.2rem; }
        .no-data i { font-size:3.5rem; color:#ddd; margin-bottom:15px; }
        .modal { display:none; position:fixed; inset:0; z-index:1000; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; }
        .modal-content { background:white; padding:28px; border-radius:10px; width:90%; max-width:560px; box-shadow:0 10px 30px rgba(0,0,0,0.25); }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; }
        .modal-header h3 { margin:0; color:#1b5e20; }
        .close { font-size:32px; cursor:pointer; color:#777; }
        .close:hover { color:#111; }
        textarea, input[type="number"] { width:100%; padding:10px; border:1px solid #ccc; border-radius:6px; font-size:14px; }
        textarea { min-height:100px; resize:vertical; }
        .damage-section { margin-top:16px; padding:12px; background:#fff8e1; border-radius:6px; border:1px solid #ffe082; }
        .damage-section.hidden { display:none; }
        .checkbox-label { display:flex; align-items:center; gap:8px; font-weight:500; margin-bottom:12px; }
        .modal-footer { margin-top:24px; text-align:right; }
        .pagination { text-align:center; margin:45px 0; }
        .pagination a, .pagination span { display:inline-block; padding:10px 18px; margin:0 5px; background:#2e7d32; color:white; text-decoration:none; border-radius:8px; font-weight:600; }
        .pagination a:hover { background:#1b5e20; }
        .pagination .current { background:#1b5e20; cursor:default; }
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

            <!-- ACTIVE BORROWS -->
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
                            <th>Overdue Fee</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($active_requests)): ?>
                            <tr><td colspan="8" class="no-data">
                                <i class="fas fa-inbox"></i><br>Walang active borrow o pending return ngayon.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($active_requests as $req): ?>
                                <?php
                                $status_display = $req['status'] === 'approved' ? 'Borrowed' : 'Return Pending';
                                $status_class   = $req['status'] === 'approved' ? 'status-borrowed' : 'status-pending';
                                $display_name   = htmlspecialchars($req['member_name'] ?: '—');
                                if ($req['role'] !== 'Unknown') $display_name .= ' <span class="role-tag">(' . htmlspecialchars($req['role']) . ')</span>';
                                $modal_name = htmlspecialchars($req['member_name'] ?: '—') . ($req['role'] !== 'Unknown' ? ' (' . $req['role'] . ')' : '');
                                $fee_class  = $req['fine_display'] > 0 ? 'fine-warning' : 'fine-zero';
                                $fee_text   = $req['fine_display'] > 0 ? '₱' . number_format($req['fine_display'], 2) : '—';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['member_id']) ?></td>
                                    <td><?= $display_name ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['borrow_date'])) ?></td>
                                    <td><?= $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—' ?></td>
                                    <td><span class="status-badge <?= $status_class ?>"><?= $status_display ?></span></td>
                                    <td class="<?= $fee_class ?>"><strong><?= $fee_text ?></strong></td>
                                    <td>
                                        <button class="btn btn-approve btn-modal"
                                            onclick="openModal(<?= $req['id'] ?>, 'approve', '<?= addslashes($modal_name) ?>', '<?= addslashes($req['book_title']) ?>')">
                                            <i class="fas fa-check"></i> Returned
                                        </button>
                                        <button class="btn btn-reject btn-modal"
                                            onclick="openModal(<?= $req['id'] ?>, 'reject', '<?= addslashes($modal_name) ?>', '<?= addslashes($req['book_title']) ?>')">
                                            <i class="fas fa-times"></i> Not Returned
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- RETURN HISTORY -->
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
                            <th>Fine (₱)</th>
                            <th>Admin Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_requests)): ?>
                            <tr><td colspan="8" class="no-data">
                                <i class="fas fa-history"></i><br>Walang return history pa.
                            </td></tr>
                        <?php else: ?>
                            <?php foreach ($history_requests as $req): ?>
                                <?php
                                $display_name = htmlspecialchars($req['member_name'] ?: '—');
                                if ($req['role'] !== 'Unknown') $display_name .= ' <span class="role-tag">(' . htmlspecialchars($req['role']) . ')</span>';
                                $notes = $req['admin_notes'] ? htmlspecialchars(substr($req['admin_notes'],0,80)) . (strlen($req['admin_notes'])>80?'...':'') : '—';
                                $fine_txt = $req['fine'] > 0 ? number_format($req['fine'], 2) : '—';
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($req['member_id']) ?></td>
                                    <td><?= $display_name ?></td>
                                    <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['borrow_date'])) ?></td>
                                    <td><?= date('M d, Y H:i', strtotime($req['returned_date'])) ?></td>
                                    <td><?= $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—' ?></td>
                                    <td><strong><?= $fine_txt ?></strong></td>
                                    <td title="<?= htmlspecialchars($req['admin_notes'] ?: '') ?>"><?= $notes ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>">&laquo; Prev</a><?php endif; ?>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    <?php if ($page < $total_pages): ?><a href="?page=<?= $page+1 ?>">Next &raquo;</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODAL -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Confirm Action</h3>
                <span class="close" onclick="closeModal()">×</span>
            </div>
            <div class="modal-body">
                <p><strong>Member:</strong> <span id="modalStudent"></span></p>
                <p><strong>Book:</strong> <span id="modalBook"></span></p>
                <p><strong>Action:</strong> <span id="modalAction"></span></p>

                <div id="damageSection" class="damage-section hidden">
                    <label class="checkbox-label">
                        <input type="checkbox" id="damageCheckbox" name="is_damaged" value="1">
                        Book is damaged / may sira
                    </label>
                    <label for="damageFine">Additional fine for damage (₱):</label>
                    <input type="number" id="damageFine" name="damage_fine" min="0" step="0.01" value="0.00" disabled>
                </div>

                <label for="adminNotes"><strong>Admin Notes / Reason:</strong></label>
                <textarea name="admin_notes" id="adminNotes" placeholder="Optional: remarks, dahilan, kondisyon ng libro..."></textarea>
            </div>
            <div class="modal-footer">
                <button class="btn" onclick="closeModal()">Cancel</button>
                <form id="modalForm" method="POST" style="display:inline;">
                    <input type="hidden" name="request_id" id="modalRequestId">
                    <input type="hidden" name="action" id="modalActionInput">
                    <input type="hidden" name="admin_notes" id="modalNotesHidden">
                    <input type="hidden" name="is_damaged" id="modalIsDamaged" value="0">
                    <input type="hidden" name="damage_fine" id="modalDamageFine" value="0">
                    <button type="submit" id="modalConfirmBtn" class="btn">Confirm</button>
                </form>
            </div>
        </div>
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
        const modal = document.getElementById('actionModal');
        const damageSection = document.getElementById('damageSection');
        const damageCheckbox = document.getElementById('damageCheckbox');
        const damageFineInput = document.getElementById('damageFine');

        function openModal(requestId, action, member, book) {
            document.getElementById('modalRequestId').value = requestId;
            document.getElementById('modalActionInput').value = 'return_' + action;
            document.getElementById('modalStudent').textContent = member;
            document.getElementById('modalBook').textContent = book;

            // Reset damage fields
            damageCheckbox.checked = false;
            damageFineInput.value = "0.00";
            damageFineInput.disabled = true;
            damageSection.classList.add('hidden');

            if (action === 'approve') {
                document.getElementById('modalTitle').textContent = 'Confirm Returned';
                document.getElementById('modalAction').textContent = 'RETURNED';
                document.getElementById('modalConfirmBtn').textContent = 'Confirm Returned';
                document.getElementById('modalConfirmBtn').className = 'btn btn-approve';
                damageSection.classList.remove('hidden'); // show damage option only for Returned
            } else {
                document.getElementById('modalTitle').textContent = 'Confirm Not Returned';
                document.getElementById('modalAction').textContent = 'NOT RETURNED';
                document.getElementById('modalConfirmBtn').textContent = 'Confirm Not Returned';
                document.getElementById('modalConfirmBtn').className = 'btn btn-reject';
            }

            document.getElementById('adminNotes').value = '';
            modal.style.display = 'flex';
        }

        damageCheckbox.addEventListener('change', function() {
            damageFineInput.disabled = !this.checked;
            document.getElementById('modalIsDamaged').value = this.checked ? '1' : '0';
            if (!this.checked) damageFineInput.value = "0.00";
        });

        damageFineInput.addEventListener('input', function() {
            document.getElementById('modalDamageFine').value = this.value;
        });

        function closeModal() {
            modal.style.display = 'none';
        }

        document.getElementById('modalForm').addEventListener('submit', function(e) {
            document.getElementById('modalNotesHidden').value = document.getElementById('adminNotes').value;
            const action = document.getElementById('modalActionInput').value;
            const msg = action.includes('approve') ? 'i-confirm na RETURNED' : 'i-confirm na NOT RETURNED';
            if (!confirm(`Sigurado ka bang gusto mong ${msg} ang record?`)) {
                e.preventDefault();
            }
        });

        window.onclick = function(event) {
            if (event.target === modal) closeModal();
        };
    </script>
</body>
</html>