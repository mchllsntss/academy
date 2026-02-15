<?php
// requested_books.php - Librarian / Admin view of requests + Pagination
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
   
    // Handle walk-in borrow action - ALWAYS set request_type to 'borrow'
    if (isset($_POST['borrow_walkin'])) {
        $book_id = (int)$_POST['book_id'];
        $user_id = (int)$_POST['user_id'];
        $request_type = 'borrow'; // Fixed value

        $book_check = $conn->query("SELECT quantity FROM books WHERE id = $book_id")->fetch_assoc();
       
        if ($book_check && $book_check['quantity'] > 0) {
            $profile_check = $conn->query("SELECT profile_id FROM users WHERE id = $user_id")->fetch_assoc();
           
            if ($profile_check) {
                $profile_id = $profile_check['profile_id'];
                $days = ($profile_id == 2) ? 7 : 30; // Student = 7, Faculty/Non-Faculty = 30
               
                $stmt = $conn->prepare("
                    INSERT INTO book_requests
                    (student_id, book_id, request_type, status, return_date)
                    VALUES (?, ?, ?, 'approved', DATE_ADD(CURDATE(), INTERVAL ? DAY))
                ");
                $stmt->bind_param("iisi", $user_id, $book_id, $request_type, $days);
               
                if ($stmt->execute()) {
                    $conn->query("UPDATE books SET quantity = quantity - 1 WHERE id = $book_id");
                    $return_date_str = date('M d, Y', strtotime("+$days days"));
                    $message = "<strong>Success!</strong> Book borrowed successfully. Return date is set to <strong>$return_date_str</strong>.";
                } else {
                    $message = "<strong>Error:</strong> " . $stmt->error;
                }
                $stmt->close();
            } else {
                $message = "<strong>Error:</strong> Member not found.";
            }
        } else {
            $message = "<strong>Error:</strong> Book is not available.";
        }
    }
}

// Pagination for Requests Table (5 per page)
$per_page = 5;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Total count
$total_requests = 0;
$count_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM book_requests r
    JOIN books b ON r.book_id = b.id
");
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_requests = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = $total_requests > 0 ? ceil($total_requests / $per_page) : 0;

// Adjust page if out of bounds
if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
    $offset = ($page - 1) * $per_page;
}

// Fetch all books for dropdown
$books = [];
$books_result = $conn->query("SELECT id, title, author, call_number, quantity FROM books WHERE quantity > 0 ORDER BY title");
if ($books_result) {
    $books = $books_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch ALL members with role
$members = [];
$members_result = $conn->query("
    SELECT
        u.id AS user_id,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username) AS member_id,
        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
        CASE
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown'
        END AS role
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE u.profile_id IN (2, 3, 4)
    ORDER BY u.first_name
");
if ($members_result) {
    $members = $members_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch paginated requests with member info + role
$requests = [];
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
    ORDER BY r.request_date DESC
    LIMIT ? OFFSET ?
");
$stmt->bind_param("ii", $per_page, $offset);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Requested Books</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        body { background-color: #f5f7fa; color: #333; line-height: 1.6; display: flex; min-height: 100vh; }
        .content-wrapper { flex: 1; margin-left: 250px; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white; padding: 20px 0; border-radius: 8px; margin-bottom: 30px; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); }
        .controls { display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; flex-wrap: wrap; gap: 15px; background-color: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05); }
        .search-box { position: relative; flex-grow: 1; max-width: 400px; }
        .search-box input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; }
        .search-box input:focus { border-color: #2e7d32; box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2); outline: none; }
        .search-box i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #7f8c8d; }
        .filters { display: flex; gap: 15px; align-items: center; }
        select { padding: 10px 15px; border-radius: 6px; border: 1px solid #ddd; font-size: 15px; cursor: pointer; }
        select:focus { border-color: #2e7d32; box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1); outline: none; }
        .btn-borrow-walkin { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 15px; display: flex; align-items: center; gap: 10px; }
        .table-container { background-color: white; border-radius: 10px; overflow: hidden; box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08); margin-bottom: 30px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        thead { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white; }
        th { padding: 18px 15px; text-align: left; font-weight: 600; font-size: 16px; }
        td { padding: 18px 15px; color: #444; }
        .requestor-info { display: flex; align-items: center; gap: 12px; }
        .avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: bold; }
        .book-title { font-weight: 500; color: #2c3e50; }
        .action-buttons { display: flex; gap: 10px; }
        .btn { padding: 8px 18px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; }
        .btn-approve { background: #1e9224; color: white; }
        .btn-reject { background: #ff0000; color: white; }
        .status-badge { padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 600; text-transform: uppercase; }
        .status-pending { background-color: #fff3cd; color: #856404; }
        .status-approved { background-color: #d4edda; color: #155724; }
        .status-rejected { background-color: #f8d7da; color: #721c24; }
        .return-date { font-size: 14px; color: #2e7d32; font-weight: 500; }
        .return-date.pending { color: #777; font-style: italic; }
        .message { padding: 12px 20px; margin: 15px 0; border-radius: 6px; border-left: 5px solid; }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error { background:#ffebee; border-color:#c62828; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0, 0, 0, 0.5); }
        .modal-content { background-color: white; margin: 5% auto; padding: 30px; border-radius: 10px; width: 90%; max-width: 600px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #eee; }
        .modal-header h2 { color: #2e7d32; margin: 0; }
        .close-modal { background: none; border: none; font-size: 28px; color: #777; cursor: pointer; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #444; }
        .form-group select, .form-group input[type="text"] { width: 100%; padding: 12px 15px; border: 1px solid #ddd; border-radius: 6px; font-size: 16px; }
        .btn-submit { background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%); color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 16px; width: 100%; }

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
            <?php if (isset($message)): ?>
                <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>
            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Search by book title, member ID, or date...">
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
                    <button class="btn-borrow-walkin" id="openBorrowModal">
                        <i class="fas fa-book-medical"></i> Borrow Book (Walk-in)
                    </button>
                </div>
            </div>
            <div class="table-container">
                <table id="requests-table">
                    <thead>
                        <tr>
                            <th>Date <i class="fas fa-sort"></i></th>
                            <th>Member ID <i class="fas fa-sort"></i></th>
                            <th>Member Name <i class="fas fa-sort"></i></th>
                            <th>Book Title <i class="fas fa-sort"></i></th>
                            <th>Type <i class="fas fa-sort"></i></th>
                            <th>Status <i class="fas fa-sort"></i></th>
                            <th>Return Date <i class="fas fa-sort"></i></th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <?php if (empty($requests)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:80px; color:#777;">
                                    <i class="fas fa-inbox" style="font-size:3.5rem; color:#ccc; display:block; margin-bottom:15px;"></i>
                                    No book requests at the moment.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($requests as $req):
                                $date = date('M d, Y H:i', strtotime($req['request_date']));
                                $return_date = $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—';
                                $return_class = $req['return_date'] ? 'return-date' : 'return-date pending';
                                $type_display = $req['request_type'] ? ucfirst($req['request_type']) : '—';
                            ?>
                                <tr>
                                    <td><?= $date ?></td>
                                    <td><?= htmlspecialchars($req['member_id'] ?: '—') ?></td>
                                    <td>
                                        <div class="requestor-info">
                                            <div class="avatar"><?= strtoupper(substr($req['member_name'] ?? 'U', 0, 1)) ?></div>
                                            <span>
                                                <?= htmlspecialchars($req['member_name'] ?: 'Unknown') ?>
                                                <small style="color:#555; font-style:italic;"> (<?= htmlspecialchars($req['role']) ?>)</small>
                                            </span>
                                        </div>
                                    </td>
                                    <td class="book-title">
                                        <?= htmlspecialchars($req['book_title']) ?>
                                        <small>(<?= htmlspecialchars($req['call_number'] ?? '—') ?>)</small>
                                    </td>
                                    <td><?= $type_display ?></td>
                                    <td><span class="status-badge status-<?= strtolower($req['status']) ?>"><?= ucfirst($req['status']) ?></span></td>
                                    <td><span class="<?= $return_class ?>"><?= $return_date ?></span></td>
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
        </div>
        <?php include '../components/footer.php'; ?>
    </div>

    <!-- Borrow Book (Walk-in) Modal -->
    <div id="borrowModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-book-medical"></i> Borrow Book (Walk-in)</h2>
                <button class="close-modal">×</button>
            </div>
            <form method="POST" id="borrowForm">
                <input type="hidden" name="borrow_walkin" value="1">
                <div class="form-group">
                    <label for="book_select"><i class="fas fa-book"></i> Select Book *</label>
                    <select name="book_id" id="book_select" required onchange="updateBookInfo()">
                        <option value="">-- Select a Book --</option>
                        <?php foreach ($books as $book): ?>
                            <option value="<?= $book['id'] ?>"
                                data-title="<?= htmlspecialchars($book['title']) ?>"
                                data-author="<?= htmlspecialchars($book['author']) ?>"
                                data-call="<?= htmlspecialchars($book['call_number']) ?>"
                                data-quantity="<?= $book['quantity'] ?>">
                                <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?> (Available: <?= $book['quantity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
               
                <div id="bookInfo" class="book-info" style="display: none; background:#f9f9f9; padding:15px; border-radius:8px; margin-bottom:20px;">
                    <p><strong>Selected Book:</strong> <span id="bookTitle"></span></p>
                    <p><strong>Author:</strong> <span id="bookAuthor"></span></p>
                    <p><strong>Call Number:</strong> <span id="bookCall"></span></p>
                    <p><strong>Available Copies:</strong> <span id="bookQuantity"></span></p>
                </div>
               
                <div class="form-group">
                    <label for="member_select"><i class="fas fa-users"></i> Select Member *</label>
                    <select name="user_id" id="member_select" required>
                        <option value="">-- Select a Member --</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= $member['user_id'] ?>">
                                <?= htmlspecialchars($member['member_id'] ?: '—') ?> -
                                <?= htmlspecialchars($member['full_name']) ?>
                                (<?= htmlspecialchars($member['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Visible Request Type (Fixed to Borrow) -->
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Request Type</label>
                    <input type="text" value="Borrow" readonly style="background-color: #e8f5e9; font-weight: bold; color: #2e7d32; border: 2px solid #2e7d32;">
                    <small style="color: #666; display: block; margin-top: 5px;">Walk-in borrow is always recorded as <strong>Borrow</strong> request type.</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Return Date (Auto-set based on role)</label>
                    <input type="text" id="returnDatePreview" value="Will be set automatically" readonly style="background-color: #f0f0f0;">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Student: 7 days<br>
                        Faculty / Non-Faculty: 30 days
                    </small>
                </div>
               
                <button type="submit" class="btn-submit">
                    <i class="fas fa-check-circle"></i> Confirm Borrow
                </button>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        const borrowModal = document.getElementById('borrowModal');
        const openModalBtn = document.getElementById('openBorrowModal');
        const closeModalBtn = document.querySelector('.close-modal');
       
        openModalBtn.addEventListener('click', () => {
            borrowModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
       
        closeModalBtn.addEventListener('click', () => {
            borrowModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        });
       
        window.addEventListener('click', (e) => {
            if (e.target === borrowModal) {
                borrowModal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
       
        function updateBookInfo() {
            const select = document.getElementById('book_select');
            const bookInfo = document.getElementById('bookInfo');
            const selectedOption = select.options[select.selectedIndex];
           
            if (select.value) {
                document.getElementById('bookTitle').textContent = selectedOption.getAttribute('data-title');
                document.getElementById('bookAuthor').textContent = selectedOption.getAttribute('data-author');
                document.getElementById('bookCall').textContent = selectedOption.getAttribute('data-call');
                document.getElementById('bookQuantity').textContent = selectedOption.getAttribute('data-quantity');
                bookInfo.style.display = 'block';
            } else {
                bookInfo.style.display = 'none';
            }
        }
        document.getElementById('member_select')?.addEventListener('change', function() {
            const text = this.options[this.selectedIndex].textContent || '';
            let days = 7;
            if (text.includes('(Faculty)') || text.includes('(Non-Faculty)')) {
                days = 30;
            }
            const returnDate = new Date();
            returnDate.setDate(returnDate.getDate() + days);
            const formatted = returnDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
            document.getElementById('returnDatePreview').value = formatted + ` (${days} days)`;
        });
       
        // Client-side Search & Filter (works on current page only)
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
                const statusCell = row.cells[5]?.textContent.toLowerCase() || '';
                const matchesStatus = status === 'all' || statusCell.includes(status);
                return matchesSearch && matchesStatus;
            });
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