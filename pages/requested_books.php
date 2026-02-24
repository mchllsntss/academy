<?php
// requested_books.php - updated: main table shows ONLY pending requests
require_once '../connection/dbconnection.php';

// Handle Approve / Reject actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['request_id'], $_POST['action'])) {
        $request_id = (int)$_POST['request_id'];
        $action = $_POST['action'];
        $new_status = ($action === 'approve') ? 'approved' : 'rejected';

        if ($action === 'approve') {
            $req_check = $conn->query("SELECT request_type FROM book_requests WHERE id = $request_id")->fetch_assoc();

            if ($req_check && $req_check['request_type'] === 'borrow') {
                $stmt = $conn->prepare("UPDATE book_requests SET status = ?, return_date = DATE_ADD(CURDATE(), INTERVAL 7 DAY) WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE book_requests SET status = ? WHERE id = ?");
            }
            $stmt->bind_param("si", $new_status, $request_id);
        } else {
            $stmt = $conn->prepare("UPDATE book_requests SET status = ? WHERE id = ?");
            $stmt->bind_param("si", $new_status, $request_id);
        }

        if ($stmt->execute()) {
            if ($new_status === 'approved') {
                $req = $conn->query("SELECT book_id, request_type FROM book_requests WHERE id = $request_id")->fetch_assoc();
                if ($req && $req['request_type'] === 'borrow') {
                    $conn->query("UPDATE books SET quantity = quantity - 1 WHERE id = {$req['book_id']} AND quantity > 0");
                }
            }
            $message = "<strong>Success!</strong> Request marked as " . ucfirst($new_status) . ".";
        } else {
            $message = "<strong>Error:</strong> " . $stmt->error;
        }
        $stmt->close();
    }
}

// ────────────────────────────────────────────────
// Main table: ONLY PENDING requests (approved & rejected are hidden)
// ────────────────────────────────────────────────
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

$total_pending = 0;
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM book_requests r
    JOIN books b ON r.book_id = b.id
    WHERE r.status = 'pending'
");
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_pending = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = $total_pending > 0 ? ceil($total_pending / $per_page) : 0;

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

$pending_requests = [];
$stmt = $conn->prepare("
    SELECT
        r.id,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username) AS member_id,
        CONCAT(u.first_name, ' ', u.last_name) AS member_name,
        CASE
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown'
        END AS role,
        r.book_id,
        r.request_type,
        r.request_date,
        r.status,
        r.return_date,
        b.title AS book_title,
        b.call_number,
        b.author
    FROM book_requests r
    JOIN books b ON r.book_id = b.id
    JOIN users u ON r.student_id = u.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status = 'pending'
    ORDER BY r.request_date DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ────────────────────────────────────────────────
// History: approved, rejected, returned (unchanged from previous)
// ────────────────────────────────────────────────
$history_records = [];
$history_stmt = $conn->prepare("
    SELECT
        CONCAT(u.first_name, ' ', u.last_name) AS member_name,
        b.title AS book_title,
        r.request_date,
        r.return_date,
        r.status,
        r.request_type
    FROM book_requests r
    JOIN books b ON r.book_id = b.id
    JOIN users u ON r.student_id = u.id
    WHERE r.status IN ('approved', 'rejected', 'returned')
    ORDER BY r.request_date DESC
");
$history_stmt->execute();
$history_result = $history_stmt->get_result();
$history_records = $history_result->fetch_all(MYSQLI_ASSOC);
$history_stmt->close();

$history_json = json_encode($history_records);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Requested Books</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
    }

    body { 
        background-color: #f5f7fa; 
        color: #333; 
        line-height: 1.6; 
        display: flex; 
        min-height: 100vh; 
    }

    .content-wrapper { 
        flex: 1; 
        margin-left: 250px; 
        padding: 20px; 
    }

    .container { 
        max-width: 1400px;           /* pinaluwag para mas maganda sa malalaking screen */
        margin: 0 auto; 
        padding: 20px; 
    }

    header { 
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); 
        color: white; 
        padding: 20px 0; 
        border-radius: 8px; 
        margin-bottom: 30px; 
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); 
    }

    .controls { 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        margin-bottom: 30px; 
        flex-wrap: wrap; 
        gap: 20px; 
        background-color: white; 
        padding: 22px 25px; 
        border-radius: 12px; 
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.06); 
    }

    /* Search box - MAS MAHABA at mas komportable */
    .search-box { 
        position: relative; 
        flex-grow: 1; 
        max-width: 900px;              /* mas mahaba na tulad ng hiniling mo */
        min-width: 380px; 
    }

    .search-box input { 
        width: 100%; 
        padding: 14px 20px 14px 52px; 
        border: 1px solid #e0e0e0; 
        border-radius: 10px; 
        font-size: 16px; 
        transition: all 0.25s ease; 
    }

    .search-box input:focus { 
        border-color: #2e7d32; 
        box-shadow: 0 0 0 4px rgba(46, 125, 50, 0.18); 
        outline: none; 
    }

    .search-box i { 
        position: absolute; 
        left: 18px; 
        top: 50%; 
        transform: translateY(-50%); 
        color: #6b7280; 
        font-size: 1.25rem; 
    }

    .filters { 
        display: flex; 
        align-items: center; 
        gap: 16px; 
        flex-wrap: wrap; 
    }

    select { 
        padding: 12px 16px; 
        border-radius: 8px; 
        border: 1px solid #ddd; 
        font-size: 15px; 
        min-width: 140px; 
        background: white; 
    }

    select:focus { 
        border-color: #2e7d32; 
        outline: none; 
        box-shadow: 0 0 0 3px rgba(46,125,50,0.2); 
    }

    /* Borrow History Button - MAS MALAKI at mas prominent */
    .btn-history { 
        padding: 13px 28px; 
        border-radius: 10px; 
        background: #2e7d32; 
        color: white; 
        border: none; 
        font-size: 16px; 
        font-weight: 600; 
        cursor: pointer; 
        display: flex; 
        align-items: center; 
        gap: 10px; 
        transition: all 0.25s ease; 
    }

    .btn-history i { 
        font-size: 1.35rem; 
    }

    .btn-history:hover { 
        background: #1b5e20; 
        transform: translateY(-2px); 
        box-shadow: 0 6px 16px rgba(27, 94, 32, 0.25); 
    }

    .table-container { 
        background-color: white; 
        border-radius: 10px; 
        overflow: hidden; 
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); 
        margin-bottom: 30px; 
        overflow-x: auto; 
    }

    table { 
        width: 100%; 
        border-collapse: collapse; 
    }

    thead { 
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); 
        color: white; 
    }

    th { 
        padding: 18px 15px; 
        text-align: left; 
        font-weight: 600; 
        font-size: 16px; 
    }

    td { 
        padding: 18px 15px; 
        color: #444; 
    }

    .requestor-info { 
        display: flex; 
        align-items: center; 
        gap: 12px; 
    }

    .avatar { 
        width: 36px; 
        height: 36px; 
        border-radius: 50%; 
        background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); 
        color: white; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        font-weight: bold; 
    }

    .book-title { 
        font-weight: 500; 
        color: #2c3e50; 
    }

    .action-buttons { 
        display: flex; 
        gap: 10px; 
    }

    .btn-icon { 
        width: 36px; 
        height: 36px; 
        border-radius: 50%; 
        border: none; 
        font-size: 16px; 
        cursor: pointer; 
        display: flex; 
        align-items: center; 
        justify-content: center; 
        transition: all 0.2s; 
    }

    .btn-approve { 
        background: #28a745; 
        color: white; 
    }

    .btn-approve:hover { 
        background: #218838; 
        transform: scale(1.1); 
    }

    .btn-reject { 
        background: #dc3545; 
        color: white; 
    }

    .btn-reject:hover { 
        background: #c82333; 
        transform: scale(1.1); 
    }

    .status-badge { 
        padding: 5px 12px; 
        border-radius: 20px; 
        font-size: 13px; 
        font-weight: 600; 
        text-transform: uppercase; 
    }

    .status-pending  { background-color: #fff3cd; color: #856404; }
    .status-approved { background-color: #d4edda; color: #155724; }
    .status-rejected { background-color: #f8d7da; color: #721c24; }
    .status-returned { background-color: #cce5ff; color: #004085; }

    .return-date { 
        font-size: 14px; 
        color: #2e7d32; 
        font-weight: 500; 
    }

    .return-date.pending { 
        color: #777; 
        font-style: italic; 
    }

    .message { 
        padding: 12px 20px; 
        margin: 15px 0; 
        border-radius: 6px; 
        border-left: 5px solid; 
    }

    .message.success { 
        background:#e8f5e9; 
        border-color:#2e7d32; 
    }

    .message.error { 
        background:#ffebee; 
        border-color:#c62828; 
    }

    .pagination { 
        text-align: center; 
        margin: 25px 0; 
    }

    .pagination a, .pagination span { 
        display: inline-block; 
        padding: 8px 14px; 
        margin: 0 4px; 
        background-color: #2e7d32; 
        color: white; 
        text-decoration: none; 
        border-radius: 6px; 
        font-size: 14px; 
        min-width: 36px; 
        text-align: center; 
        cursor: pointer; 
    }

    .pagination a:hover { 
        background-color: #1b5e20; 
    }

    .pagination .current { 
        background-color: #1b5e20; 
        cursor: default; 
    }

    .pagination .dots { 
        cursor: default; 
        background: none; 
        color: #555; 
    }

    /* Modal styles */
    .modal { 
        display: none; 
        position: fixed; 
        z-index: 1000; 
        left: 0; 
        top: 0; 
        width: 100%; 
        height: 100%; 
        background-color: rgba(0,0,0,0.6); 
        overflow: auto; 
    }

    .modal-content { 
        background-color: #f8fff8; 
        margin: 4% auto; 
        padding: 0; 
        border-radius: 10px; 
        width: 90%; 
        max-width: 1200px; 
        box-shadow: 0 10px 30px rgba(0,0,0,0.25); 
        max-height: 90vh; 
        overflow-y: auto; 
        border: 1px solid #a8e6a8; 
    }

    .modal-header { 
        background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); 
        color: white; 
        padding: 20px 30px; 
        display: flex; 
        justify-content: space-between; 
        align-items: center; 
        border-radius: 10px 10px 0 0; 
        position: sticky; 
        top: 0; 
        z-index: 10; 
    }

    .modal-header h2 { 
        margin: 0; 
        font-size: 1.6rem; 
    }

    .close-modal { 
        background: none; 
        border: none; 
        color: white; 
        font-size: 32px; 
        cursor: pointer; 
    }

    .modal-body { 
        padding: 25px; 
    }

    .history-controls { 
        margin-bottom: 20px; 
        display: flex; 
        flex-wrap: wrap; 
        gap: 15px; 
        align-items: center; 
    }

    .history-search-box { 
        position: relative; 
        flex: 1; 
        min-width: 280px; 
        max-width: 500px; 
    }

    .history-search-box input { 
        width: 100%; 
        padding: 12px 20px 12px 45px; 
        border: 1px solid #a8e6a8; 
        border-radius: 6px; 
        font-size: 16px; 
    }

    .history-search-box input:focus { 
        border-color: #2e7d32; 
        box-shadow: 0 0 0 3px rgba(46,125,50,0.2); 
        outline: none; 
    }

    .history-search-box i { 
        position: absolute; 
        left: 15px; 
        top: 50%; 
        transform: translateY(-50%); 
        color: #2e7d32; 
    }

    .history-table { 
        width: 100%; 
        border-collapse: collapse; 
        margin-bottom: 20px; 
    }

    .history-table th, .history-table td { 
        padding: 14px 12px; 
        border-bottom: 1px solid #d4edda; 
        text-align: left; 
    }

    .history-table th { 
        background: #2e7d32; 
        color: white; 
        font-weight: 600; 
        position: sticky; 
        top: 0; 
        z-index: 5; 
    }

    .history-table tr:hover { 
        background: #e8f5e9; 
    }

    .no-data { 
        text-align: center; 
        padding: 80px 20px; 
        color: #666; 
        font-size: 1.2rem; 
    }

    .no-data i { 
        font-size: 4rem; 
        color: #ccc; 
        margin-bottom: 15px; 
        display: block; 
    }
</style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="container">
            <?php if ($message): ?>
                <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Search pending requests...">
                </div>
                <div class="filters">
                    <select id="date-filter">
                        <option value="recent">Most Recent</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                    <button class="btn-history" id="openHistoryModal">
                        <i class="fas fa-history"></i> Borrow History
                    </button>
                </div>
            </div>

            <div class="table-container">
                <table id="requests-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Book Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php if (empty($pending_requests)): ?>
                            <tr>
                                <td colspan="7" class="no-data">
                                    <i class="fas fa-inbox"></i><br>
                                    No current borrow requests
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pending_requests as $req): ?>
                                <tr>
                                    <td><?= date('M d, Y H:i', strtotime($req['request_date'])) ?></td>
                                    <td><?= htmlspecialchars($req['member_id'] ?: '—') ?></td>
                                    <td>
                                        <div class="requestor-info">
                                            <div class="avatar"><?= strtoupper(substr($req['member_name'] ?? 'U', 0, 1)) ?></div>
                                            <span>
                                                <?= htmlspecialchars($req['member_name'] ?: 'Unknown') ?>
                                                <small> (<?= htmlspecialchars($req['role']) ?>)</small>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="book-title">
                                        <?= htmlspecialchars($req['book_title']) ?>
                                        <small>(<?= htmlspecialchars($req['call_number'] ?? '—') ?>)</small>
                                    </td>
                                    <td><?= ucfirst($req['request_type'] ?: '—') ?></td>
                                    <td><span class="status-badge status-pending">Pending</span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn-icon btn-approve" title="Approve"><i class="fas fa-check"></i></button>
                                            </form>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn-icon btn-reject" title="Reject"><i class="fas fa-times"></i></button>
                                            </form>
                                        </div>
                                    </td>
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
        <?php include '../components/footer.php'; ?>
    </div>

    <!-- Borrow History Modal (approved, rejected, returned) - remains the same -->
    <div id="historyModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-history"></i> Borrow History (Processed Requests)</h2>
                <button class="close-modal" onclick="document.getElementById('historyModal').style.display='none'">×</button>
            </div>
            <div class="modal-body">
                <div class="history-controls">
                    <div class="history-search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="history-search" placeholder="Search by member or book title...">
                    </div>
                </div>

                <table class="history-table" id="history-table">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>Book Title</th>
                            <th>Type</th>
                            <th>Borrowed Date</th>
                            <th>Return Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody"></tbody>
                </table>

                <div class="pagination" id="history-pagination"></div>
            </div>
        </div>
    </div>

    <script>
        // History data and logic (same as before)
        const historyData = <?= $history_json ?>;

        const modal = document.getElementById('historyModal');
        const openBtn = document.getElementById('openHistoryModal');
        const searchInput = document.getElementById('history-search');
        const tbody = document.getElementById('history-tbody');
        const pagination = document.getElementById('history-pagination');

        const itemsPerPage = 10;
        let currentPage = 1;
        let filteredData = [...historyData];

        function renderTable(page = 1) {
            currentPage = page;
            tbody.innerHTML = '';

            const start = (page - 1) * itemsPerPage;
            const end = start + itemsPerPage;
            const pageItems = filteredData.slice(start, end);

            if (pageItems.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="no-data">
                            <i class="fas fa-book-open"></i><br>
                            No matching processed requests found.
                        </td>
                    </tr>`;
            } else {
                pageItems.forEach(item => {
                    const row = document.createElement('tr');
                    const statusClass = `status-${item.status.toLowerCase()}`;
                    const returnDate = item.return_date ? formatDate(item.return_date) : '—';
                    row.innerHTML = `
                        <td>${escapeHtml(item.member_name)}</td>
                        <td>${escapeHtml(item.book_title)}</td>
                        <td>${item.request_type ? item.request_type.charAt(0).toUpperCase() + item.request_type.slice(1) : '—'}</td>
                        <td>${formatDate(item.request_date)}</td>
                        <td>${returnDate}</td>
                        <td><span class="status-badge ${statusClass}">${item.status.charAt(0).toUpperCase() + item.status.slice(1)}</span></td>
                    `;
                    tbody.appendChild(row);
                });
            }

            renderPagination();
        }

        function renderPagination() {
            pagination.innerHTML = '';
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            if (totalPages <= 1) return;

            if (currentPage > 1) {
                const prev = document.createElement('a');
                prev.textContent = '« Prev';
                prev.onclick = () => renderTable(currentPage - 1);
                pagination.appendChild(prev);
            }

            const range = 2;
            let start = Math.max(1, currentPage - range);
            let end = Math.min(totalPages, currentPage + range);

            if (start > 1) {
                const first = document.createElement('a');
                first.textContent = '1';
                first.onclick = () => renderTable(1);
                pagination.appendChild(first);
                if (start > 2) {
                    const dots = document.createElement('span');
                    dots.className = 'dots';
                    dots.textContent = '...';
                    pagination.appendChild(dots);
                }
            }

            for (let i = start; i <= end; i++) {
                const link = document.createElement(i === currentPage ? 'span' : 'a');
                link.textContent = i;
                if (i !== currentPage) link.onclick = () => renderTable(i);
                else link.className = 'current';
                pagination.appendChild(link);
            }

            if (end < totalPages) {
                if (end < totalPages - 1) {
                    const dots = document.createElement('span');
                    dots.className = 'dots';
                    dots.textContent = '...';
                    pagination.appendChild(dots);
                }
                const last = document.createElement('a');
                last.textContent = totalPages;
                last.onclick = () => renderTable(totalPages);
                pagination.appendChild(last);
            }

            if (currentPage < totalPages) {
                const next = document.createElement('a');
                next.textContent = 'Next »';
                next.onclick = () => renderTable(currentPage + 1);
                pagination.appendChild(next);
            }
        }

        function applySearch() {
            const term = searchInput.value.toLowerCase().trim();
            filteredData = historyData.filter(item =>
                item.member_name.toLowerCase().includes(term) ||
                item.book_title.toLowerCase().includes(term)
            );
            currentPage = 1;
            renderTable(1);
        }

        function escapeHtml(unsafe) {
            return unsafe
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        function formatDate(dateStr) {
            return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        openBtn.addEventListener('click', () => {
            modal.style.display = 'block';
            applySearch();
        });

        modal.addEventListener('click', e => {
            if (e.target === modal) modal.style.display = 'none';
        });

        searchInput.addEventListener('input', applySearch);

        // Removed main table client-side filtering since only pending are shown now
    </script>
</body>
</html>