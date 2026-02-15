<?php
session_start();
require_once '../connection/dbconnection.php';

$student_id = $_SESSION['student_id'] ?? 1; // CHANGE THIS to real student ID
$message = '';

// Handle Reserve / Borrow request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $book_id = (int)($_POST['book_id'] ?? 0);
    $request_type = $_POST['action'] === 'reserve' ? 'reserve' : 'borrow';
   
    if ($book_id > 0 && $student_id > 0) {
        $check = $conn->prepare("SELECT quantity FROM books WHERE id = ?");
        $check->bind_param("i", $book_id);
        $check->execute();
        $result = $check->get_result();
        $book = $result->fetch_assoc();
        $check->close();
      
        if ($book && $book['quantity'] > 0) {
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

// Pagination & Search Parameters
$per_page = 8;
$search = trim($_GET['search'] ?? '');
$category = $_GET['category'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));

// Build WHERE conditions
$where_conditions = [];
$bind_types = '';
$bind_params = [];

if ($search !== '') {
    $like = "%$search%";
    $where_conditions[] = "(title LIKE ? OR author LIKE ? OR call_number LIKE ? OR isbn LIKE ?)";
    $bind_params[] = $like;
    $bind_params[] = $like;
    $bind_params[] = $like;
    $bind_params[] = $like;
    $bind_types .= 'ssss';
}
if ($category !== '') {
    $where_conditions[] = "category = ?";
    $bind_params[] = $category;
    $bind_types .= 's';
}

$where_clause = 'quantity > 0';
if (!empty($where_conditions)) {
    $where_clause .= ' AND ' . implode(' AND ', $where_conditions);
}

// Count total books
$count_query = "SELECT COUNT(*) AS total FROM books WHERE $where_clause";
$count_stmt = $conn->prepare($count_query);
if ($bind_types !== '') {
    $count_stmt->bind_param($bind_types, ...$bind_params);
}
$count_stmt->execute();
$total_books = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = $total_books > 0 ? ceil($total_books / $per_page) : 1;

if ($page > $total_pages && $total_pages > 0) {
    $page = $total_pages;
}
$offset = ($page - 1) * $per_page;

// Fetch paginated books
$fetch_types = $bind_types . 'ii';
$fetch_params = $bind_params;
$fetch_params[] = $offset;
$fetch_params[] = $per_page;

$fetch_query = "
    SELECT
        id,
        call_number,
        title,
        shelf_location,
        author,
        category,
        copyright_year AS year,
        isbn,
        quantity,
        cover_image
    FROM books
    WHERE $where_clause
    ORDER BY title ASC
    LIMIT ?, ?
";
$fetch_stmt = $conn->prepare($fetch_query);
$fetch_stmt->bind_param($fetch_types, ...$fetch_params);
$fetch_stmt->execute();
$result = $fetch_stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$fetch_stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Catalog - Student View</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f9f5; color:#333; display:flex; min-height:100vh; }
        .sidebar-container { width:250px; background:#2e7d32; color:white; min-height:100vh; position:fixed; }
        .main-content { flex:1; margin-left:250px; padding:25px; }
        .container { max-width:1400px; margin:0 auto; }

        .header { margin-bottom:30px; text-align:center; }
        .header h1 { color:#2e7d32; font-size:2.5rem; border-bottom:3px solid #4caf50; display:inline-block; padding-bottom:10px; }

        .search-container {
            background:#fff;
            border-radius:10px;
            padding:20px;
            box-shadow:0 4px 12px rgba(46,125,50,0.1);
            margin-bottom:25px;
            border-left:5px solid #4caf50;
        }
        .search-box { display:flex; gap:15px; flex-wrap:wrap; align-items:center; }
        .search-input {
            flex:1; min-width:300px; padding:12px 15px; border:2px solid #c8e6c9;
            border-radius:6px; font-size:16px;
        }
        .search-input:focus { outline:none; border-color:#2e7d32; box-shadow:0 0 0 3px rgba(76,175,80,0.2); }
        .filter-select { padding:12px 15px; border:2px solid #c8e6c9; border-radius:6px; font-size:16px; background:white; min-width:220px; }
        .search-btn {
            background:#4caf50; color:white; border:none; border-radius:6px;
            padding:12px 25px; font-size:16px; cursor:pointer; display:flex; align-items:center; gap:8px;
        }
        .search-btn:hover { background:#388e3c; }

        /* Books Grid */
        .books-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
            margin-top: 20px;
        }
        .book-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        .book-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }
        .book-cover {
            height: 320px;
            background: #f0f0f0;
            position: relative;
        }
        .book-cover img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .placeholder-cover {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #bbb;
            font-size: 4rem;
        }
        .book-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .book-title {
            font-size: 1.3rem;
            color: #2e7d32;
            margin-bottom: 8px;
        }
        .book-author {
            font-size: 1rem;
            color: #555;
            margin-bottom: 16px;
        }
        .book-details p {
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        .action-buttons {
            margin-top: auto;
            padding-top: 16px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .no-books-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        .no-books-message i {
            color: #ccc;
            margin-bottom: 20px;
        }

        /* Category badges */
        .category-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .category-generalities { background:#f3e5f5; color:#7b1fa2; }
        .category-philosophy { background:#e8f5e9; color:#2e7d32; }
        .category-religion { background:#fff3e0; color:#ef6c00; }
        .category-social-science { background:#e3f2fd; color:#1565c0; }
        .category-languages { background:#fce4ec; color:#c2185b; }
        .category-natural-science { background:#e0f2f1; color:#00695c; }
        .category-applied-science { background:#fff3e0; color:#ef6c00; }
        .category-arts-and-recreation { background:#f3e5f5; color:#7b1fa2; }
        .category-literature { background:#fce4ec; color:#c2185b; }
        .category-geography-and-history { background:#e0f2f1; color:#00695c; }
        .category-biography-and-collective-biography { background:#e8f5e9; color:#2e7d32; }

        .quantity-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }

        /* Buttons */
        .btn {
            padding: 11px 22px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1rem;
        }
        .btn-reserve {
            background: #ffb74d;
            color: #5d4037;
        }
        .btn-reserve:hover { background: #ffa726; }
        .btn-borrow {
            background: #4fc3f7;
            color: white;
        }
        .btn-borrow:hover { background: #29b6f6; }

        .message {
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
        }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error { background:#ffebee; border-color:#c62828; }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 40px 0;
            flex-wrap: wrap;
            font-size: 1.1rem;
        }
        .pagination a {
            padding: 10px 16px;
            background: #4caf50;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            transition: background 0.3s;
        }
        .pagination a:hover {
            background: #388e3c;
        }
        .pagination .current {
            padding: 10px 16px;
            background: #2e7d32;
            color: white;
            border-radius: 8px;
            font-weight: bold;
        }
        .pagination .dots {
            padding: 0 8px;
            color: #999;
        }
        .page-info {
            text-align: center;
            margin: 20px 0;
            color: #555;
            font-size: 1.1rem;
        }

        @media (max-width:992px) {
            .main-content { margin-left:-/widgets; }
            .sidebar-container { position:relative; width:100%; min-height:auto; }
        }
        @media (max-width:768px) {
            .search-box { flex-direction:column; }
            .search-input { min-width:100%; }
            .books-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>
    <div class="sidebar-container">
        <?php include '../components/student_sidebar.php'; ?>
    </div>

    <div class="main-content">
        <div class="container">
            <?php if ($message): ?>
                <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <div class="search-container">
                <form method="get" class="search-box">
                    <input type="text" class="search-input" id="searchInput" name="search" placeholder="Search by book title, author, ISBN, or call number..." value="<?= htmlspecialchars($search) ?>">
                    <select class="filter-select" id="categoryFilter" name="category">
                        <option value="" <?= $category === '' ? 'selected' : '' ?>>All Categories</option>
                        <option value="Generalities" <?= $category === 'Generalities' ? 'selected' : '' ?>>001-099 Generalities</option>
                        <option value="Philosophy" <?= $category === 'Philosophy' ? 'selected' : '' ?>>100-199 Philosophy</option>
                        <option value="Religion" <?= $category === 'Religion' ? 'selected' : '' ?>>200-299 Religion</option>
                        <option value="Social Science" <?= $category === 'Social Science' ? 'selected' : '' ?>>300-399 Social Science</option>
                        <option value="Languages" <?= $category === 'Languages' ? 'selected' : '' ?>>400-499 Languages</option>
                        <option value="Natural Science" <?= $category === 'Natural Science' ? 'selected' : '' ?>>500-599 Natural Science</option>
                        <option value="Applied Science" <?= $category === 'Applied Science' ? 'selected' : '' ?>>600-699 Applied Science</option>
                        <option value="Arts and Recreation" <?= $category === 'Arts and Recreation' ? 'selected' : '' ?>>700-799 Arts and Recreation</option>
                        <option value="Literature" <?= $category === 'Literature' ? 'selected' : '' ?>>800-899 Literature</option>
                        <option value="Geography and History" <?= $category === 'Geography and History' ? 'selected' : '' ?>>900-999 Geography and History</option>
                        <option value="Biography and Collective Biography" <?= $category === 'Biography and Collective Biography' ? 'selected' : '' ?>>92 and 920 Biography and Collective Biography</option>
                    </select>
                    <input type="hidden" name="page" value="1">
                    <button type="submit" class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </form>
            </div>

            <?php if ($total_books > 0): ?>
                <div class="page-info">
                    Showing <?= $offset + 1 ?> - <?= $offset + count($books) ?> of <?= $total_books ?> available books
                </div>
            <?php endif; ?>

            <div class="books-grid">
                <?php if (empty($books)): ?>
                    <div class="no-books-message">
                        <i class="fas fa-book-open fa-5x"></i>
                        <h2>No books found.</h2>
                        <?php if ($search !== '' || $category !== ''): ?>
                            <p>No books match your search criteria. Try adjusting your search or filters.</p>
                        <?php else: ?>
                            <p>No available books at the moment. Check back later.</p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($books as $book):
                        $qty = (int)$book['quantity'];
                        $qtyText = $qty . ' ' . ($qty == 1 ? 'copy' : 'copies');
                        $qtyBg = $qty >= 5 ? '#c8e6c9' : ($qty >= 2 ? '#fff9c4' : '#ffcdd2');
                        $qtyColor = $qty >= 5 ? '#2e7d32' : ($qty >= 2 ? '#f57f17' : '#c62828');
                        $catSlug = strtolower(str_replace(' ', '-', $book['category']));
                    ?>
                        <div class="book-card">
                            <div class="book-cover">
                                <?php if (!empty($book['cover_image'])): ?>
                                    <img src="<?= htmlspecialchars($book['cover_image']) ?>" alt="Cover of <?= htmlspecialchars($book['title']) ?>">
                                <?php else: ?>
                                    <div class="placeholder-cover">
                                        <i class="fas fa-book"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="book-info">
                                <h3 class="book-title"><?= htmlspecialchars($book['title']) ?></h3>
                                <p class="book-author">by <?= htmlspecialchars($book['author']) ?></p>
                                <div class="book-details">
                                    <p><strong>Call Number:</strong> <?= htmlspecialchars($book['call_number'] ?? '—') ?></p>
                                    <p><strong>Shelf Location:</strong> <?= htmlspecialchars($book['shelf_location'] ?? '—') ?></p>
                                    <p><strong>Category:</strong> <span class="category-badge category-<?= $catSlug ?>"><?= htmlspecialchars($book['category']) ?></span></p>
                                    <p><strong>Copyright Year:</strong> <?= $book['year'] ?? '—' ?></p>
                                    <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn'] ?? '—') ?></p>
                                    <p><strong>Quantity:</strong>
                                        <span class="quantity-badge" style="background: <?= $qtyBg ?>; color: <?= $qtyColor ?>;">
                                            <?= $qtyText ?>
                                        </span>
                                    </p>
                                </div>
                                <div class="action-buttons">
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="reserve">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <button type="submit" class="btn btn-reserve" onclick="return confirm('Are you sure you want to RESERVE this book?');">
                                            <i class="fas fa-calendar-check"></i> Reserve
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="borrow">
                                        <input type="hidden" name="book_id" value="<?= $book['id'] ?>">
                                        <button type="submit" class="btn btn-borrow" onclick="return confirm('Are you sure you want to BORROW this book?');">
                                            <i class="fas fa-book-open"></i> Borrow
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>">&laquo; Previous</a>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $page - 3);
                    $end_page = min($total_pages, $page + 3);
                    if ($start_page > 1): ?>
                        <a href="?page=1&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>">1</a>
                        <?php if ($start_page > 2): ?><span class="dots">...</span><?php endif; ?>
                    <?php endif; ?>

                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?= $i ?></span>
                        <?php else: ?>
                            <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>"><?= $i ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?><span class="dots">...</span><?php endif; ?>
                        <a href="?page=<?= $total_pages ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>"><?= $total_pages ?></a>
                    <?php endif; ?>

                    <?php if ($page < $total_pages): ?>
                        <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&category=<?= urlencode($category) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php include '../components/footer.php'; ?>
    </div>
</body>
</html>