<?php
// requested_books.php - Librarian / Admin view of pending requests
require_once '../connection/dbconnection.php';

// Handle Approve / Reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    $request_id = (int)$_POST['request_id'];
    $action = $_POST['action'];

    $new_status = ($action === 'approve') ? 'approved' : 'rejected';

    $stmt = $conn->prepare("UPDATE book_requests SET status = ? WHERE id = ?");
    $stmt->bind_param("si", $new_status, $request_id);
    
    if ($stmt->execute()) {
        // Optional: if approved and it's borrow → decrease book quantity
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

// Fetch all requests (no join to students)
$requests = [];
$result = $conn->query("
    SELECT 
        r.id,
        r.student_id,
        r.book_id,
        r.request_type,
        r.request_date,
        r.status,
        b.title AS book_title,
        b.call_number
    FROM book_requests r
    JOIN books b ON r.book_id = b.id
    ORDER BY r.request_date DESC
");
if ($result) {
    $requests = $result->fetch_all(MYSQLI_ASSOC);
}
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
        .main-container {
            display: flex;
            flex: 1;
        }
        .content-wrapper {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            transition: margin-left 0.3s ease;
        }
        .container {
            max-width: 1200px;
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
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }
        h1 {
            font-size: 28px;
        }
        .stats {
            display: flex;
            gap: 20px;
        }
        .stat-box {
            background-color: rgba(46, 125, 50, 0.1);
            padding: 10px 20px;
            border-radius: 6px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #4caf50;
        }
        .stat-label {
            font-size: 14px;
            color: #e8f5e9;
        }
        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }
        .search-box {
            position: relative;
            flex-grow: 1;
            max-width: 400px;
        }
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }
        .search-box input:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
            outline: none;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }
        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        select {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        select:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
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
        th i {
            margin-left: 8px;
            opacity: 0.7;
        }
        tbody tr {
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s;
        }
        tbody tr:hover {
            background-color: #f9f9f9;
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
        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .btn-approve {
            background: #1e9224;
            color: white;
        }
        .btn-approve:hover {
            background: #0e4b10;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }
        .btn-reject {
            background: #ff0000;
            color: white;
        }
        .btn-reject:hover {
            background: #b00707;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 111, 81, 0.3);
        }
        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }
        .status-approved {
            background-color: #d4edda;
            color: #155724;
        }
        .status-rejected {
            background-color: #f8d7da;
            color: #721c24;
        }
        .message {
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
        }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error   { background:#ffebee; border-color:#c62828; }
        .footer {
            text-align: center;
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 14px;
        }
        @media (max-width: 1024px) {
            .content-wrapper { margin-left: 0; padding: 15px; }
        }
        @media (max-width: 768px) {
            .header-content { flex-direction: column; gap: 20px; text-align: center; }
            .controls { flex-direction: column; align-items: stretch; }
            .search-box { max-width: 100%; }
            .filters { flex-wrap: wrap; }
            th, td { padding: 12px 10px; }
            .btn { padding: 8px 12px; font-size: 13px; }
            .content-wrapper { padding: 10px; }
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>

    <div class="content-wrapper">
        <div class="container">
            <?php if (isset($message)): ?>
                <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Search by book title, student ID, or date...">
                </div>
                <div class="filters">
                    <select id="status-filter">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                    <select id="date-filter">
                        <option value="recent">Most Recent</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="requests-table">
                    <thead>
                        <tr>
                            <th>Date <i class="fas fa-sort"></i></th>
                            <th>Student ID <i class="fas fa-sort"></i></th>
                            <th>Book Title <i class="fas fa-sort"></i></th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding:80px; color:#777;">
                                    <i class="fas fa-inbox" style="font-size:3.5rem; color:#ccc; display:block; margin-bottom:15px;"></i>
                                    No pending book requests at the moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req): 
                                $date = date('M d, Y H:i', strtotime($req['request_date']));
                                $initials = 'S' . $req['student_id']; // temporary - replace with real name when students table exists
                            ?>
                                <tr>
                                    <td><?= $date ?></td>
                                    <td>
                                        <div class="requestor-info">
                                            <div class="avatar"><?= $initials ?></div>
                                            <span>Student #<?= $req['student_id'] ?></span>
                                        </div>
                                    </td>
                                    <td class="book-title">
                                        <?= htmlspecialchars($req['book_title']) ?>
                                        <small>(<?= htmlspecialchars($req['call_number']) ?>)</small>
                                    </td>
                                    <td><?= ucfirst($req['request_type']) ?></td>
                                    <td><span class="status-badge status-<?= $req['status'] ?>"><?= ucfirst($req['status']) ?></span></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($req['status'] === 'pending'): ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-approve">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-reject">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php include '../components/footer.php'; ?>
    </div>

    <script>
        // Simple client-side search & filter
        const searchInput = document.getElementById('search-input');
        const statusFilter = document.getElementById('status-filter');
        const dateFilter = document.getElementById('date-filter');
        const rows = document.querySelectorAll('#table-body tr');

        function applyFilters() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const status = statusFilter.value;
            const dateSort = dateFilter.value;

            let visibleRows = Array.from(rows).filter(row => {
                if (!row.cells) return false;
                const text = row.textContent.toLowerCase();
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const statusCell = row.cells[4]?.textContent.toLowerCase() || '';
                const matchesStatus = status === 'all' || statusCell.includes(status);
                return matchesSearch && matchesStatus;
            });

            // Sort by date
            if (dateSort === 'oldest') {
                visibleRows.sort((a, b) => new Date(a.cells[0].textContent) - new Date(b.cells[0].textContent));
            } else {
                visibleRows.sort((a, b) => new Date(b.cells[0].textContent) - new Date(a.cells[0].textContent));
            }

            rows.forEach(row => row.style.display = 'none');
            visibleRows.forEach(row => row.style.display = '');
        }

        searchInput.addEventListener('input', applyFilters);
        statusFilter.addEventListener('change', applyFilters);
        dateFilter.addEventListener('change', applyFilters);
    </script>
</body>
</html>