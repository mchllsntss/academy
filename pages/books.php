<?php
// books.php
require_once '../connection/dbconnection.php';
$message = '';

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Optional: Check if book is currently borrowed
    $check_borrow = $conn->prepare("SELECT COUNT(*) FROM book_requests WHERE book_id = ? AND status = 'approved'");
    $check_borrow->bind_param("i", $delete_id);
    $check_borrow->execute();
    $check_borrow->bind_result($borrowed_count);
    $check_borrow->fetch();
    $check_borrow->close();

    if ($borrowed_count > 0) {
        $message = "<strong>Error:</strong> Cannot delete this book. It is currently borrowed by " . $borrowed_count . " user(s).";
    } else {
        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            $message = "<strong>Success!</strong> Book has been deleted.";
        } else {
            $message = "<strong>Error:</strong> Failed to delete book. " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle add/edit book (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_book') {
    $call_number    = trim($_POST['callNumber'] ?? '');
    $isbn           = trim($_POST['isbn'] ?? '');
    $title          = trim($_POST['title'] ?? '');
    $shelf_location = trim($_POST['shelfLocation'] ?? '');
    $author         = trim($_POST['author'] ?? '');
    $category       = trim($_POST['category'] ?? '');
    $copyright_year = (int)($_POST['copyrightYear'] ?? 0);
    $quantity       = (int)($_POST['quantity'] ?? 0);
    $book_id        = (int)($_POST['book_id'] ?? 0);

    $errors = [];

    if (empty($call_number))    $errors[] = "Call number is required.";
    if (empty($title))          $errors[] = "Book title is required.";
    if (empty($author))         $errors[] = "Author is required.";
    if (empty($category))       $errors[] = "Category is required.";
    if ($copyright_year < 1900 || $copyright_year > 2035) $errors[] = "Invalid copyright year.";
    if ($quantity < 1)          $errors[] = "Quantity must be at least 1.";

    $cover_image = '';
    $current_cover = '';
    if ($book_id > 0) {
        $stmt = $conn->prepare("SELECT cover_image FROM books WHERE id = ?");
        $stmt->bind_param("i", $book_id);
        $stmt->execute();
        $stmt->bind_result($current_cover);
        $stmt->fetch();
        $stmt->close();
        $cover_image = $current_cover ?? '';
    }

    if (isset($_FILES['cover_image']) && $_FILES['cover_image']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = __DIR__ . '/../uploads/books/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
        $file_name = $_FILES['cover_image']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_filename = 'book_' . ($book_id ?: 'new') . '_' . time() . '.' . $ext;
            $target = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) {
                $cover_image = '../uploads/books/' . $new_filename;
                if ($book_id > 0 && $current_cover && $current_cover !== $cover_image && file_exists(__DIR__ . '/' . $current_cover)) {
                    unlink(__DIR__ . '/' . $current_cover);
                }
            } else {
                $errors[] = "Failed to upload image.";
            }
        } else {
            $errors[] = "Invalid image format. Use JPG, PNG or GIF.";
        }
    }

    if (empty($errors)) {
        if ($book_id === 0) {
            $stmt = $conn->prepare("
                INSERT INTO books
                (call_number, isbn, title, shelf_location, author, category, copyright_year, quantity, cover_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssis", $call_number, $isbn, $title, $shelf_location, $author, $category, $copyright_year, $quantity, $cover_image);
        } else {
            $stmt = $conn->prepare("
                UPDATE books
                SET call_number=?, isbn=?, title=?, shelf_location=?, author=?, category=?, copyright_year=?, quantity=?, cover_image=?
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssiisi", $call_number, $isbn, $title, $shelf_location, $author, $category, $copyright_year, $quantity, $cover_image, $book_id);
        }

        if ($stmt->execute()) {
            $message = "<strong>Success!</strong> Book " . ($book_id === 0 ? "added: " . htmlspecialchars($title) : "updated.");
        } else {
            $message = "<strong>Error:</strong> " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "<strong>Please fix:</strong><br>• " . implode("<br>• ", $errors);
    }
}

// Handle walk-in borrow (same as before)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_book'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = (int)$_POST['user_id'];
    $request_type = 'borrow';

    $book_check = $conn->query("SELECT quantity FROM books WHERE id = $book_id")->fetch_assoc();
    
    if ($book_check && $book_check['quantity'] > 0) {
        $profile_check = $conn->query("SELECT profile_id FROM users WHERE id = $user_id")->fetch_assoc();
        
        if ($profile_check) {
            $profile_id = $profile_check['profile_id'];
            $days = ($profile_id == 2) ? 7 : 30;
            
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
            $message = "<strong>Error:</strong> User profile not found.";
        }
    } else {
        $message = "<strong>Error:</strong> Book is not available.";
    }
}

// Pagination & Search Setup (same as before)
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = trim($_GET['search'] ?? '');
$query_string = $search !== '' ? '&search=' . urlencode($search) : '';

$where_clause = '';
$param_types = '';
$params = [];

if ($search !== '') {
    $where_clause = " WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ? OR call_number LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like, $like, $like];
    $param_types = 'sssss';
}

$count_sql = "SELECT COUNT(*) AS total FROM books" . $where_clause;
$stmt = $conn->prepare($count_sql);
if (!empty($params)) $stmt->bind_param($param_types, ...$params);
$stmt->execute();
$count_result = $stmt->get_result();
$total_row = $count_result->fetch_assoc();
$total_books = $total_row['total'];
$stmt->close();

$total_pages = $total_books > 0 ? ceil($total_books / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

$sql = "SELECT * FROM books" . $where_clause . " ORDER BY title ASC LIMIT ? OFFSET ?";
$limit_types = $param_types . 'ii';
$limit_params = $params;
$limit_params[] = $per_page;
$limit_params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($limit_types, ...$limit_params);
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - La Trinidad Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- SweetAlert2 CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f9f5;
            color: #333;
        }
        .main-content {
            margin-left: 260px;
            padding: 25px;
        }
        .header {
            background: white;
            padding: 18px 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }
        .header h1 {
            color: #2e7d32;
            font-size: 1.7rem;
            margin: 0;
        }
        .container { max-width: 1400px; margin: 0 auto; }

        .controls-bar {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            margin-bottom: 28px;
            align-items: center;
        }

        .search-container {
            position: relative;
            flex: 1;
            min-width: 320px;
        }
        .search-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
        }
        #liveSearch {
            width: 100%;
            padding: 12px 14px 12px 48px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }
        #liveSearch:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.15);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.25s;
            font-size: 1rem;
            min-width: 180px;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        }

        .btn-add, .btn-walkin, .btn-print {
            background: #2e7d32;
            color: white;
        }
        .btn-add:hover, .btn-walkin:hover, .btn-print:hover {
            background: #1b5e20;
            transform: translateY(-1px);
        }

        .btn-borrow {
            background: #4caf50;
            color: white;
        }
        .btn-borrow:hover {
            background: #43a047;
            transform: translateY(-1px);
        }

        .btn-submit {
            background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%);
            color: white;
            width: 100%;
            padding: 14px 24px;
            font-size: 1.1rem;
        }
        .btn-submit:hover {
            background: linear-gradient(135deg, #4caf50 0%, #388e3c 100%);
        }

        .btn-edit {
            background: #1976d2;
            color: white;
        }
        .btn-edit:hover {
            background: #1565c0;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: #d32f2f;
            color: white;
        }
        .btn-delete:hover {
            background: #b71c1c;
            transform: translateY(-1px);
        }

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
        .no-books-message, .no-results-message {
            grid-column: 1 / -1;
            text-align: center;
            padding: 80px 20px;
            color: #666;
        }
        .no-books-message i, .no-results-message i {
            color: #ccc;
            margin-bottom: 20px;
        }
        .category-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .quantity-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
        }
        .modal {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.55);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 100%;
            max-width: 980px;
            max-height: 92vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .modal-header {
            background: #2e7d32;
            color: white;
            padding: 18px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }
        .modal-body { padding: 24px; }
        .modal-footer {
            padding: 16px 24px;
            background: #f8f9fa;
            text-align: right;
            position: sticky;
            bottom: 0;
            z-index: 10;
        }

        /* Print styles - only records will appear when printing */
        @media print {
            body * { visibility: hidden; }
            #printModal, #printModal * { visibility: visible; }
            #printModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .modal-header, .modal-footer, .close-modal { display: none !important; }
            .modal-content { box-shadow: none; max-height: none; overflow: visible; }
        }

        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: 600; color: #2e7d32; }
        input, select {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        input:focus, select:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.2);
        }
        .form-row { display: flex; gap: 20px; flex-wrap: wrap; }
        .form-row > .form-group { flex: 1; min-width: 220px; }
        small { color: #666; font-size: 0.85rem; }
        .message {
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
        }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error   { background:#ffebee; border-color:#c62828; }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .pagination a, .pagination span {
            padding: 10px 16px;
            border-radius: 8px;
            text-decoration: none;
            background: white;
            color: #333;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        .pagination a:hover {
            background: #2e7d32;
            color: white;
        }
        .pagination .current {
            background: #2e7d32;
            color: white;
            font-weight: bold;
        }
        .pagination .disabled {
            color: #aaa;
            box-shadow: none;
            cursor: not-allowed;
        }
        .result-info {
            text-align: center;
            margin: 20px 0;
            color: #555;
            font-size: 1.1rem;
        }
        .book-info-preview {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #4caf50;
        }
        .book-info-preview p {
            margin: 5px 0;
        }
        .book-info-preview strong {
            color: #2e7d32;
        }
    </style>
</head>
<body>
<?php include '../components/header.php'; ?>
<?php include '../components/sidebar.php'; ?>

<main class="main-content">
    <div class="header">
        <h1><i class="fas fa-book"></i> Book Management</h1>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="controls-bar">
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="liveSearch" placeholder="Search by title, author, category, ISBN, call number..." value="<?= htmlspecialchars($search) ?>">
            </div>

            <div class="action-buttons">
                <button class="btn btn-add" id="addBookBtn">
                    <i class="fas fa-plus"></i> Add New Book
                </button>
                <button class="btn btn-walkin" id="openBorrowModal">
                    <i class="fas fa-hand-holding"></i> Borrow Book (Walk-in)
                </button>
                <button class="btn btn-print" onclick="printAllBooks()">
                    <i class="fas fa-print"></i> Print All Books
                </button>
            </div>
        </div>

        <?php if ($search !== ''): ?>
            <div class="result-info">
                Showing results for "<?= htmlspecialchars($search) ?>" (<?= $total_books ?> found)
            </div>
        <?php endif; ?>

        <div class="books-grid" id="booksGrid">
            <?php if (empty($books)): ?>
                <div class="no-books-message">
                    <i class="fas fa-book-open fa-5x"></i>
                    <h2>No books found.</h2>
                    <p>
                        <?php if ($search !== ''): ?>
                            No books matching "<?= htmlspecialchars($search) ?>". Try a different search term.
                        <?php else: ?>
                            Add your first book to get started!
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                <?php foreach ($books as $book):
                    $searchable = strtolower($book['title'] . ' ' . $book['author'] . ' ' . $book['category'] . ' ' . ($book['isbn'] ?? '') . ' ' . ($book['call_number'] ?? ''));
                ?>
                    <div class="book-card" data-id="<?= $book['id'] ?>" data-search="<?= htmlspecialchars($searchable) ?>">
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
                                <p><strong>Call Number:</strong> <span class="detail-call-number"><?= htmlspecialchars($book['call_number'] ?? '—') ?></span></p>
                                <p><strong>Shelf Location:</strong> <span class="detail-shelf"><?= htmlspecialchars($book['shelf_location'] ?? '—') ?></span></p>
                                <p><strong>Category:</strong> <span class="category-badge category-<?= strtolower(str_replace([' ', '&', ','], '-', $book['category'])) ?>"><?= htmlspecialchars($book['category']) ?></span></p>
                                <p><strong>Copyright Year:</strong> <span class="detail-year"><?= $book['copyright_year'] ?></span></p>
                                <p><strong>ISBN:</strong> <span class="detail-isbn"><?= htmlspecialchars($book['isbn'] ?: '—') ?></span></p>
                                <p><strong>Quantity:</strong>
                                    <span class="quantity-badge" style="
                                        background: <?= $book['quantity'] >= 5 ? '#c8e6c9' : ($book['quantity'] >= 2 ? '#fff9c4' : '#ffcdd2') ?>;
                                        color: <?= $book['quantity'] >= 5 ? '#2e7d32' : ($book['quantity'] >= 2 ? '#f57f17' : '#c62828') ?>;
                                    ">
                                        <?= $book['quantity'] ?> <?= $book['quantity'] == 1 ? 'copy' : 'copies' ?>
                                    </span>
                                </p>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-edit" onclick="editBook(<?= $book['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button class="btn btn-borrow" onclick="quickBorrow(<?= $book['id'] ?>)">
                                    <i class="fas fa-hand-holding"></i> Borrow
                                </button>
                                <button class="btn btn-delete" onclick="confirmDelete(<?= $book['id'] ?>)">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $query_string ?>"><i class="fas fa-chevron-left"></i> Previous</a>
                <?php else: ?>
                    <span class="disabled"><i class="fas fa-chevron-left"></i> Previous</span>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 3);
                $end_page = min($total_pages, $page + 3);

                if ($start_page > 1) {
                    echo '<a href="?page=1' . $query_string . '">1</a>';
                    if ($start_page > 2) echo '<span>...</span>';
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        echo '<a href="?page=' . $i . $query_string . '">' . $i . '</a>';
                    }
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) echo '<span>...</span>';
                    echo '<a href="?page=' . $total_pages . $query_string . '">' . $total_pages . '</a>';
                }
                ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $query_string ?>">Next <i class="fas fa-chevron-right"></i></a>
                <?php else: ?>
                    <span class="disabled">Next <i class="fas fa-chevron-right"></i></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<!-- Add/Edit Book Modal -->
<div class="modal" id="bookModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-book"></i> <span id="modalTitle">Add New Book</span></h2>
            <button class="close-modal" id="closeModal">×</button>
        </div>
        <div class="modal-body">
            <form id="bookForm" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_book">
                <input type="hidden" name="book_id" id="book_id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="callNumber">Call Number *</label>
                        <input type="text" id="callNumber" name="callNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn">
                    </div>
                </div>
                <div class="form-group">
                    <label for="shelfLocation">Shelf Location</label>
                    <input type="text" id="shelfLocation" name="shelfLocation">
                </div>
                <div class="form-group">
                    <label for="title">Book Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="author">Author *</label>
                        <input type="text" id="author" name="author" required>
                    </div>
                    <div class="form-group">
                        <label for="category">Category *</label>
                        <select id="category" name="category" required>
                            <option value="">— Select Category —</option>
                            <option value="Generalities">001-099 Generalities</option>
                            <option value="Philosophy">100-199 Philosophy</option>
                            <option value="Religion">200-299 Religion</option>
                            <option value="Social Science">300-399 Social Science</option>
                            <option value="Languages">400-499 Languages</option>
                            <option value="Natural Science">500-599 Natural Science</option>
                            <option value="Applied Science">600-699 Applied Science</option>
                            <option value="Arts and Recreation">700-799 Arts and Recreation</option>
                            <option value="Literature">800-899 Literature</option>
                            <option value="Geography and History">900-999 Geography and History</option>
                            <option value="Biography and Collective Biography">92 and 920 Biography and Collective Biography</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="copyrightYear">Copyright Year *</label>
                        <input type="number" id="copyrightYear" name="copyrightYear" min="1900" max="2035" required>
                    </div>
                    <div class="form-group">
                        <label for="quantity">Quantity / Stock *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="cover_image">Book Cover Image</label>
                    <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/gif">
                    <small>Optional • Recommended: JPG, PNG, GIF (max 2MB suggested)</small>
                </div>
                <div id="currentCoverPreview" style="margin-top:15px; display:none;">
                    <p><strong>Current / Preview Cover:</strong></p>
                    <img id="currentCoverImg" src="" alt="Cover preview" style="max-width:300px; max-height:400px; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1);">
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-cancel" id="cancelBtn">Cancel</button>
            <button type="submit" form="bookForm" class="btn btn-save">
                <i class="fas fa-save"></i> Save Book
            </button>
        </div>
    </div>
</div>

<!-- Borrow Book (Walk-in) Modal -->
<div id="borrowModal" class="modal">
    <div class="modal-content">
        <div class="modal-header borrow-header">
            <h2><i class="fas fa-hand-holding"></i> Borrow Book (Walk-in)</h2>
            <button class="close-modal" id="closeBorrowModal">×</button>
        </div>
        <div class="modal-body">
            <form method="POST" id="borrowForm">
                <input type="hidden" name="borrow_book" value="1">
                
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
                
                <div id="bookInfo" class="book-info-preview" style="display: none;">
                    <p><strong><i class="fas fa-book"></i> Selected Book:</strong> <span id="bookTitle"></span></p>
                    <p><strong><i class="fas fa-user"></i> Author:</strong> <span id="bookAuthor"></span></p>
                    <p><strong><i class="fas fa-hashtag"></i> Call Number:</strong> <span id="bookCall"></span></p>
                    <p><strong><i class="fas fa-copy"></i> Available Copies:</strong> <span id="bookQuantity"></span></p>
                </div>
                
                <div class="form-group">
                    <label for="member_select"><i class="fas fa-users"></i> Select Member *</label>
                    <select name="user_id" id="member_select" required onchange="updateReturnDate()">
                        <option value="">-- Select a Member --</option>
                        <?php foreach ($members as $member): ?>
                            <option value="<?= $member['user_id'] ?>" data-role="<?= htmlspecialchars($member['role']) ?>">
                                <?= htmlspecialchars($member['member_id'] ?: '—') ?> - 
                                <?= htmlspecialchars($member['full_name']) ?>
                                (<?= htmlspecialchars($member['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
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
</div>

<!-- Print Modal -->
<div class="modal" id="printModal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-print"></i> Books Records</h2>
            <button class="close-modal" onclick="document.getElementById('printModal').style.display='none'">×</button>
        </div>
        <div class="modal-body" id="printContent" style="padding:24px;">
            <!-- Content filled by JS -->
        </div>
        <div class="modal-footer">
            <button onclick="window.print()" class="btn btn-save">
                <i class="fas fa-print"></i> Print Now
            </button>
            <button onclick="document.getElementById('printModal').style.display='none'" class="btn btn-cancel">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// ==============================================
// BOOK MODAL & EDIT FUNCTION
// ==============================================
const modal = document.getElementById('bookModal');
const addBtn = document.getElementById('addBookBtn');
const closeBtn = document.getElementById('closeModal');
const cancelBtn = document.getElementById('cancelBtn');
const titleEl = document.getElementById('modalTitle');
const form = document.getElementById('bookForm');
const currentCoverPreview = document.getElementById('currentCoverPreview');
const currentCoverImg = document.getElementById('currentCoverImg');
const coverInput = document.getElementById('cover_image');

addBtn?.addEventListener('click', () => {
    titleEl.textContent = 'Add New Book';
    form.reset();
    document.getElementById('book_id').value = '';
    currentCoverPreview.style.display = 'none';
    modal.style.display = 'flex';
});

closeBtn?.addEventListener('click', () => modal.style.display = 'none');
cancelBtn?.addEventListener('click', () => modal.style.display = 'none');

coverInput?.addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            currentCoverImg.src = e.target.result;
            currentCoverPreview.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
});

function editBook(id) {
    const card = document.querySelector(`.book-card[data-id="${id}"]`);
    if (!card) return;

    titleEl.textContent = 'Edit Book';
    document.getElementById('book_id').value = id;
    document.getElementById('callNumber').value = card.querySelector('.detail-call-number')?.textContent.trim() || '';
    document.getElementById('shelfLocation').value = card.querySelector('.detail-shelf')?.textContent.trim() || '';
    document.getElementById('title').value = card.querySelector('.book-title')?.textContent.trim() || '';
    document.getElementById('author').value = card.querySelector('.book-author')?.textContent.replace(/^by\s+/i, '').trim() || '';
    document.getElementById('category').value = card.querySelector('.category-badge')?.textContent.trim() || '';
    document.getElementById('copyrightYear').value = card.querySelector('.detail-year')?.textContent.trim() || '';
    document.getElementById('isbn').value = (card.querySelector('.detail-isbn')?.textContent.trim() === '—' ? '' : card.querySelector('.detail-isbn')?.textContent.trim());

    const qtyBadge = card.querySelector('.quantity-badge');
    document.getElementById('quantity').value = qtyBadge ? parseInt(qtyBadge.textContent.trim()) || 1 : 1;

    const coverImg = card.querySelector('.book-cover img');
    if (coverImg && coverImg.src) {
        currentCoverImg.src = coverImg.src;
        currentCoverPreview.style.display = 'block';
    } else {
        currentCoverPreview.style.display = 'none';
    }
    modal.style.display = 'flex';
}

// ==============================================
// BORROW MODAL
// ==============================================
const borrowModal = document.getElementById('borrowModal');
const openBorrowBtn = document.getElementById('openBorrowModal');
const closeBorrowBtn = document.getElementById('closeBorrowModal');

openBorrowBtn?.addEventListener('click', () => {
    borrowModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
});

closeBorrowBtn?.addEventListener('click', () => {
    borrowModal.style.display = 'none';
    document.body.style.overflow = 'auto';
});

window.addEventListener('click', (e) => {
    if (e.target === borrowModal) {
        borrowModal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    if (e.target === modal) {
        modal.style.display = 'none';
    }
});

function quickBorrow(id) {
    const bookSelect = document.getElementById('book_select');
    if (bookSelect) {
        bookSelect.value = id;
        updateBookInfo();
    }
    borrowModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

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
    updateReturnDate();
}

function updateReturnDate() {
    const memberSelect = document.getElementById('member_select');
    const selectedOption = memberSelect.options[memberSelect.selectedIndex];
    const role = selectedOption ? selectedOption.getAttribute('data-role') : '';
    
    let days = 7;
    if (role === 'Faculty' || role === 'Non-Faculty') {
        days = 30;
    }
    
    const returnDate = new Date();
    returnDate.setDate(returnDate.getDate() + days);
    const formatted = returnDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('returnDatePreview').value = formatted + ` (${days} days)`;
}

// ==============================================
// LIVE SEARCH
// ==============================================
const liveSearch = document.getElementById('liveSearch');
const booksGrid = document.getElementById('booksGrid');

if (liveSearch && booksGrid) {
    liveSearch.addEventListener('input', function() {
        const filter = this.value.toLowerCase().trim();
        const cards = booksGrid.querySelectorAll('.book-card');
        let hasVisible = false;

        cards.forEach(card => {
            const text = card.getAttribute('data-search') || '';
            if (text.includes(filter)) {
                card.style.display = '';
                hasVisible = true;
            } else {
                card.style.display = 'none';
            }
        });

        let noResults = booksGrid.querySelector('.no-results-message');
        if (!noResults) {
            noResults = document.createElement('div');
            noResults.className = 'no-results-message';
            noResults.innerHTML = `
                <i class="fas fa-search fa-5x" style="color:#ddd; margin-bottom:20px;"></i>
                <h2>No matching books found</h2>
                <p>Try different keywords or clear the search.</p>
            `;
            booksGrid.appendChild(noResults);
        }

        noResults.style.display = (hasVisible || filter === '') ? 'none' : 'block';
    });

    if (liveSearch.value.trim() !== '') {
        liveSearch.dispatchEvent(new Event('input'));
    }
}

// ==============================================
// PRINT MODAL
// ==============================================
function printAllBooks() {
    const printModal = document.getElementById('printModal');
    const printContent = document.getElementById('printContent');

    let html = `
        <h1 style="color:#2e7d32; text-align:center; margin-bottom:10px;">Library Books Records</h1>
        <p style="text-align:center; color:#555; margin-bottom:20px;">Generated on: ${new Date().toLocaleString('en-PH')}</p>
        <p style="text-align:center; color:#777; margin-bottom:20px;">Total Books: <?= $total_books ?></p>
        <table style="width:100%; border-collapse:collapse; font-size:0.95rem; margin-top:20px;">
            <thead>
                <tr style="background:#2e7d32; color:white;">
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Title</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Author</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Call Number</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Category</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">ISBN</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Qty</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Shelf</th>
                    <th style="border:1px solid #ccc; padding:12px; text-align:left;">Year</th>
                </tr>
            </thead>
            <tbody>
    `;

    <?php foreach ($books as $book): ?>
        html += `
            <tr style="border-bottom:1px solid #eee;">
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['title'])) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['author'])) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['call_number'] ?? '—')) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['category'])) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['isbn'] ?: '—')) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= $book['quantity'] ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= addslashes(htmlspecialchars($book['shelf_location'] ?? '—')) ?></td>
                <td style="border:1px solid #ccc; padding:12px;"><?= $book['copyright_year'] ?></td>
            </tr>
        `;
    <?php endforeach; ?>

    html += `
            </tbody>
        </table>
    `;

    printContent.innerHTML = html;
    printModal.style.display = 'flex';
}

// ==============================================
// DELETE WITH SWEETALERT2 CONFIRMATION
// ==============================================
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            // Redirect to delete
            window.location.href = "books.php?delete_id=" + id;
        }
    });
}
</script>
</body>
</html>