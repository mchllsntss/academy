<?php
// books.php
require_once '../connection/dbconnection.php';
$message = '';

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

    // Handle cover image upload
    $cover_image = '';
    $current_cover = '';
    if ($book_id > 0) {
        // Get current cover for edit
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
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        $file_name = $_FILES['cover_image']['name'];
        $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $new_filename = 'book_' . ($book_id ?: 'new') . '_' . time() . '.' . $ext;
            $target = $upload_dir . $new_filename;
            if (move_uploaded_file($_FILES['cover_image']['tmp_name'], $target)) {
                $cover_image = '../uploads/books/' . $new_filename;
                // Delete old cover if exists and different
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

    // If no new image uploaded, keep the existing one (for edit) or empty (for add)
    if (empty($errors)) {
        if ($book_id === 0) {
            // Insert
            $stmt = $conn->prepare("
                INSERT INTO books
                (call_number, isbn, title, shelf_location, author, category, copyright_year, quantity, cover_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssssis", $call_number, $isbn, $title, $shelf_location, $author, $category, $copyright_year, $quantity, $cover_image);
        } else {
            // Update
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

// Pagination & Search Setup
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = trim($_GET['search'] ?? '');
$query_string = $search !== '' ? '&search=' . urlencode($search) : '';

// Build WHERE clause and parameters
$where_clause = '';
$param_types = '';
$params = [];

if ($search !== '') {
    $where_clause = " WHERE title LIKE ? OR author LIKE ? OR isbn LIKE ? OR category LIKE ? OR call_number LIKE ?";
    $like = "%$search%";
    $params = [$like, $like, $like, $like, $like];
    $param_types = 'sssss';
}

// Count total books (with search)
$count_sql = "SELECT COUNT(*) AS total FROM books" . $where_clause;
$stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$count_result = $stmt->get_result();
$total_row = $count_result->fetch_assoc();
$total_books = $total_row['total'];
$stmt->close();

$total_pages = $total_books > 0 ? ceil($total_books / $per_page) : 1;
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;

// Fetch current page books
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - La Trinidad Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header h1 {
            color: #2e7d32;
            font-size: 1.7rem;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .add-book-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .search-form {
            display: flex;
            gap: 12px;
            flex: 1;
            max-width: 680px;
        }
        .search-container {
            position: relative;
            flex: 1;
        }
        .search-container i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
        }
        #searchInput {
            width: 100%;
            padding: 12px 14px 12px 44px;
            border: 1px solid #ccc;
            border-radius: 8px;
            font-size: 1rem;
        }
        #searchInput:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.15);
        }
        .btn {
            padding: 11px 22px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-add {
            background: #2e7d32;
            color: white;
            font-size: 1rem;
        }
        .btn-add:hover { background: #1b5e20; }
        .btn-edit {
            background: #ffb74d;
            color: #5d4037;
        }
        .btn-edit:hover { background: #ffa726; }
        .btn-delete {
            background: #ef5350;
            color: white;
        }
        .btn-delete:hover { background: #e53935; }
        .btn-cancel { background: #ccc; color: #333; }
        .btn-save { background: #2e7d32; color: white; }

        /* Books Grid (card browsing view) */
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
        /* Modal */
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
            max-width: 780px;
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

        /* Pagination */
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
        .pagination span:not(.current):not(.disabled) {
            padding: 10px 8px;
            background: transparent;
            box-shadow: none;
        }
        .result-info {
            text-align: center;
            margin: 20px 0;
            color: #555;
            font-size: 1.1rem;
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

        <div class="add-book-section">
            <form method="GET" action="" class="search-form">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" id="searchInput" placeholder="Search by title, author, category, ISBN, call number..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <button type="submit" class="btn btn-add">
                    <i class="fas fa-search"></i> Search
                </button>
            </form>
            <button class="btn btn-add" id="addBookBtn">
                <i class="fas fa-plus"></i> Add New Book
            </button>
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
                    $catSlug = strtolower(str_replace([' ', '&', ','], '-', $book['category']));
                ?>
                    <div class="book-card" data-id="<?= $book['id'] ?>">
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
                                <p><strong>Category:</strong> <span class="category-badge category-<?= $catSlug ?>"><?= htmlspecialchars($book['category']) ?></span></p>
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
                                <button class="btn btn-delete" onclick="if(confirm('Delete this book?')) alert('Delete not implemented yet');">
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

<!-- Modal -->
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

<script>
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

// Preview uploaded image (new or replacement)
coverInput.addEventListener('change', function() {
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

    // Current cover preview
    const coverImg = card.querySelector('.book-cover img');
    if (coverImg && coverImg.src) {
        currentCoverImg.src = coverImg.src;
        currentCoverPreview.style.display = 'block';
    } else {
        currentCoverPreview.style.display = 'none';
    }
    modal.style.display = 'flex';
}
</script>
</body>
</html>