<?php
// student_books_view.php
session_start(); // if you use sessions for logged-in student
require_once '../connection/dbconnection.php';

// Assume student is logged in - replace with your actual auth logic
$student_id = $_SESSION['student_id'] ?? 1; // example - CHANGE THIS to real student ID

$message = '';

// Handle Reserve / Borrow request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $request_type = $_POST['action'] === 'reserve' ? 'reserve' : 'borrow';

    if ($book_id > 0 && $student_id > 0) {
        // Check if book exists and has copies
        $check = $conn->prepare("SELECT quantity FROM books WHERE id = ?");
        $check->bind_param("i", $book_id);
        $check->execute();
        $result = $check->get_result();
        $book = $result->fetch_assoc();
        $check->close();

        if ($book && $book['quantity'] > 0) {
            // Insert request
            $stmt = $conn->prepare("
                INSERT INTO book_requests (student_id, book_id, request_type)
                VALUES (?, ?, ?)
            ");
            $stmt->bind_param("iis", $student_id, $book_id, $request_type);
            if ($stmt->execute()) {
                $message = "Success! Your " . ucfirst($request_type) . " request has been submitted.";
            } else {
                $message = "Error: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $message = "Book not available or does not exist.";
        }
    } else {
        $message = "Please log in to request a book.";
    }
}

// Fetch available books
$books = [];
$result = $conn->query("
    SELECT
        id,
        call_number,
        title,
        shelf_location,
        author,
        category,
        copyright_year AS year,
        isbn,
        quantity
    FROM books
    WHERE quantity > 0
    ORDER BY title ASC
");
if ($result) {
    $books = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f7f0; color:#333; display:flex; min-height:100vh; }
        .sidebar-container { width:250px; background:#2e7d32; color:white; min-height:100vh; position:fixed; }
        .main-content { flex:1; margin-left:250px; padding:20px; }
        .container { max-width:1400px; margin:0 auto; }
        .header { margin-bottom:30px; text-align:center; }
        .header h1 { color:#2e7d32; font-size:2.5rem; border-bottom:3px solid #4caf50; display:inline-block; padding-bottom:10px; }
        .search-container { background:#fff; border-radius:10px; padding:20px; box-shadow:0 4px 12px rgba(46,125,50,0.1); margin-bottom:25px; border-left:5px solid #4caf50; }
        .search-box { display:flex; gap:15px; flex-wrap:wrap; }
        .search-input { flex:1; min-width:300px; padding:12px 15px; border:2px solid #c8e6c9; border-radius:6px; font-size:16px; }
        .search-input:focus { outline:none; border-color:#2e7d32; box-shadow:0 0 0 3px rgba(76,175,80,0.2); }
        .search-btn { background:#4caf50; color:white; border:none; border-radius:6px; padding:12px 25px; font-size:16px; cursor:pointer; display:flex; align-items:center; gap:8px; }
        .search-btn:hover { background:#388e3c; }
        .filter-select { padding:12px 15px; border:2px solid #c8e6c9; border-radius:6px; font-size:16px; background:white; }
        .table-container { background:white; border-radius:10px; overflow:hidden; box-shadow:0 5px 15px rgba(0,0,0,0.05); overflow-x:auto; }
        table { width:100%; border-collapse:collapse; min-width:1200px; }
        thead { background:#2e7d32; color:white; }
        th, td { padding:16px 15px; text-align:left; }
        th { font-weight:600; }
        tbody tr { border-bottom:1px solid #e0e0e0; }
        tbody tr:hover { background:#f1f8e9; }
        .quantity-indicator { padding:5px 12px; border-radius:999px; font-size:0.9rem; font-weight:600; display:inline-block; }
        .quantity-high   { background:#e8f5e9; color:#2e7d32; }
        .quantity-low    { background:#fff3e0; color:#ef6c00; }
        .quantity-zero   { background:#ffebee; color:#c62828; }
        .action-buttons { display:flex; gap:10px; flex-wrap:wrap; }
        .btn { padding:8px 16px; border-radius:6px; border:none; cursor:pointer; font-size:0.9rem; font-weight:600; transition:all 0.2s; display:flex; align-items:center; gap:6px; }
        .btn-reserve { background:#ffb74d; color:#5d4037; }
        .btn-reserve:hover:not(:disabled) { background:#ffa726; }
        .btn-borrow { background:#4fc3f7; color:#01579b; }
        .btn-borrow:hover:not(:disabled) { background:#29b6f6; }
        .btn:disabled { opacity:0.6; cursor:not-allowed; }
        .message { padding:12px 20px; margin:15px 0; border-radius:6px; }
        .message.success { background:#e8f5e9; color:#2e7d32; border-left:5px solid #2e7d32; }
        .message.error   { background:#ffebee; color:#c62828; border-left:5px solid #c62828; }
        @media (max-width:992px) { .main-content { margin-left:0; } }
        @media (max-width:768px) { .search-box { flex-direction:column; } .search-input { min-width:100%; } }
    </style>
</head>
<body>

    <!-- Sidebar -->
    <div class="sidebar-container">
        <?php include '../components/student_sidebar.php'; ?>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-container">
            <?php include '../components/header.php'; ?>
        </div>

        <div class="container">
            <?php if ($message): ?>
                <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- Search & Filter -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" id="searchInput" placeholder="Search by book title, author, ISBN, or call number...">
                    <select class="filter-select" id="categoryFilter">
                        <option value="">All Categories</option>
                        <option value="Fiction">Fiction</option>
                        <option value="Science">Science</option>
                        <option value="History">History</option>
                        <option value="Technology">Technology</option>
                        <option value="Biography">Biography</option>
                        <option value="Other">Other</option>
                    </select>
                    <button class="search-btn" id="searchBtn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>

            <!-- Book Table -->
            <div class="table-container">
                <table id="bookTable">
                    <thead>
                        <tr>
                            <th>Call Number</th>
                            <th>Book Title</th>
                            <th>Book Shelf Number</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Copyright Year</th>
                            <th>ISBN</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookTableBody">
                        <?php if (empty($books)): ?>
                            <tr>
                                <td colspan="9" style="text-align:center; padding:80px; color:#777;">
                                    <i class="fas fa-book-open" style="font-size:3.5rem; color:#ccc; display:block; margin-bottom:15px;"></i>
                                    No books found in the library collection.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book):
                                $qty = (int)$book['quantity'];
                                $qtyClass = $qty >= 5 ? 'quantity-high' : ($qty >= 1 ? 'quantity-low' : 'quantity-zero');
                            ?>
                                <tr>
                                    <td><?= htmlspecialchars($book['call_number'] ?? '—') ?></td>
                                    <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                    <td><?= htmlspecialchars($book['shelf_location'] ?? '—') ?></td>
                                    <td><?= htmlspecialchars($book['author']) ?></td>
                                    <td><?= htmlspecialchars($book['category']) ?></td>
                                    <td><?= $book['year'] ?></td>
                                    <td><?= htmlspecialchars($book['isbn'] ?: '—') ?></td>
                                    <td>
                                        <span class="quantity-indicator <?= $qtyClass ?>">
                                            <?= $qty ?> available
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to RESERVE this book?');">
                                                <input type="hidden" name="action" value="reserve">
                                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                <button type="submit" class="btn btn-reserve <?= $qty === 0 ? 'disabled' : '' ?>"
                                                        <?= $qty === 0 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-calendar-check"></i> Reserve
                                                </button>
                                            </form>
                                            <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to BORROW this book?');">
                                                <input type="hidden" name="action" value="borrow">
                                                <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                                <button type="submit" class="btn btn-borrow <?= $qty === 0 ? 'disabled' : '' ?>"
                                                        <?= $qty === 0 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-book-open"></i> Borrow
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

            <!-- Pagination placeholder -->
            <div class="pagination" id="pagination" style="display: <?= count($books) > 10 ? 'flex' : 'none' ?>;">
                <!-- Will be populated by JS if needed -->
            </div>
        </div>

        <?php include '../components/footer.php'; ?>
    </div>

    <script>
        // Client-side search & filter (unchanged)
        const searchInput = document.getElementById('searchInput');
        const categoryFilter = document.getElementById('categoryFilter');
        const searchBtn = document.getElementById('searchBtn');
        const tableBody = document.getElementById('bookTableBody');
        const rows = tableBody.querySelectorAll('tr');

        function filterBooks() {
            const searchTerm = searchInput.value.toLowerCase().trim();
            const selectedCat = categoryFilter.value;
            rows.forEach(row => {
                if (!row.cells) return;
                const text = row.textContent.toLowerCase();
                const categoryCell = row.cells[4]?.textContent.toLowerCase() || '';
                const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                const matchesCategory = selectedCat === '' || categoryCell.includes(selectedCat.toLowerCase());
                row.style.display = (matchesSearch && matchesCategory) ? '' : 'none';
            });
        }

        searchBtn.addEventListener('click', filterBooks);
        searchInput.addEventListener('keyup', e => { if (e.key === 'Enter') filterBooks(); });
        categoryFilter.addEventListener('change', filterBooks);
    </script>
</body>
</html>