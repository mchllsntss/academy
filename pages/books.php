<?php
// books.php
require_once '../connection/dbconnection.php';
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_book') {
    $call_number    = trim($_POST['callNumber'] ?? '');
    $isbn           = trim($_POST['isbn'] ?? '');
    $title          = trim($_POST['title'] ?? '');
    $author         = trim($_POST['author'] ?? '');
    $category       = trim($_POST['category'] ?? '');
    $copyright_year = (int)($_POST['copyrightYear'] ?? 0);
    $quantity       = (int)($_POST['quantity'] ?? 0);
    $book_id        = (int)($_POST['book_id'] ?? 0);
    $errors = [];
    if (empty($call_number))    $errors[] = "Call number / Shelf is required.";
    if (empty($title))          $errors[] = "Book title is required.";
    if (empty($author))         $errors[] = "Author is required.";
    if (empty($category))       $errors[] = "Category is required.";
    if ($copyright_year < 1900 || $copyright_year > 2035) $errors[] = "Invalid copyright year.";
    if ($quantity < 1)          $errors[] = "Quantity must be at least 1.";
    if (empty($errors)) {
        if ($book_id === 0) {
            $stmt = $conn->prepare("
                INSERT INTO books (call_number, isbn, title, author, category, copyright_year, quantity)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param("sssssii", $call_number, $isbn, $title, $author, $category, $copyright_year, $quantity);
            if ($stmt->execute()) {
                $message = "<strong>Success!</strong> Book added: " . htmlspecialchars($title);
            } else {
                $message = "<strong>Error:</strong> " . $stmt->error;
            }
            $stmt->close();
        } else {
            $stmt = $conn->prepare("
                UPDATE books SET call_number=?, isbn=?, title=?, author=?, category=?, copyright_year=?, quantity=?
                WHERE id = ?
            ");
            $stmt->bind_param("sssssiii", $call_number, $isbn, $title, $author, $category, $copyright_year, $quantity, $book_id);
            if ($stmt->execute()) {
                $message = "<strong>Success!</strong> Book updated.";
            } else {
                $message = "<strong>Error:</strong> " . $stmt->error;
            }
            $stmt->close();
        }
    } else {
        $message = "<strong>Please fix:</strong><br>• " . implode("<br>• ", $errors);
    }
}
$books = [];
$result = $conn->query("SELECT * FROM books ORDER BY title ASC");
if ($result) {
    $books = $result->fetch_all(MYSQLI_ASSOC);
}
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
            margin-left: 260px; /* adjust to your sidebar width */
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
        .search-container {
            position: relative;
            width: 100%;
            max-width: 480px;
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
        .books-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,0.08);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead {
            background: #2e7d32;
            color: white;
        }
        th, td {
            padding: 14px 16px;
            text-align: left;
        }
        th {
            font-weight: 600;
            white-space: nowrap;
        }
        tbody tr {
            border-bottom: 1px solid #e8f5e9;
        }
        tbody tr:nth-child(even) {
            background: #f9fdf9;
        }
        tbody tr:hover {
            background: #e8f5e9;
        }
        .category-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
        .category-fiction     { background:#e3f2fd; color:#1565c0; }
        .category-non-fiction { background:#f3e5f5; color:#7b1fa2; }
        .category-science     { background:#e8f5e9; color:#2e7d32; }
        .category-technology  { background:#fff3e0; color:#ef6c00; }
        .category-literature  { background:#fce4ec; color:#c2185b; }
        .category-history     { background:#e0f2f1; color:#00695c; }
        .category-other       { background:#e0e0e0; color:#424242; }
        .quantity-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .btn-edit {
            background: #ffb74d;
            color: #5d4037;
            border: none;
        }
        .btn-edit:hover { background: #ffa726; }
        .btn-delete {
            background: #ef5350;
            color: white;
            border: none;
        }
        .btn-delete:hover { background: #e53935; }
        /* Modal styles */
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
            max-width: 720px;
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
        .message {
            padding: 12px 16px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
        }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error   { background:#ffebee; border-color:#c62828; }
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
            <div class="search-container">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search books by title, author, category, or ISBN...">
            </div>
            <button class="btn btn-add" id="addBookBtn">
                <i class="fas fa-plus"></i> Add New Book
            </button>
        </div>
        <div class="books-table">
            <table>
                <thead>
                    <tr>
                        <th>Call Number</th>
                        <th>Book Title</th>
                        <th>Shelf Location</th>
                        <th>Author</th>
                        <th>Category</th>
                        <th>Copyright Year</th>
                        <th>ISBN</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="booksTableBody">
                    <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="9" style="text-align:center; padding:80px 20px; color:#666;">
                                <i class="fas fa-book-open fa-4x" style="color:#ccc; margin-bottom:20px; display:block;"></i>
                                No books found.<br>Add your first book!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($books as $book):
                            $catClass = strtolower(str_replace(' ', '-', $book['category']));
                        ?>
                            <tr data-id="<?= $book['id'] ?>">
                                <td><?= htmlspecialchars($book['call_number'] ?? '—') ?></td>
                                <td><strong><?= htmlspecialchars($book['title']) ?></strong></td>
                                <td><?= htmlspecialchars($book['shelf_location'] ?? '—') ?></td>
                                <td><?= htmlspecialchars($book['author']) ?></td>
                                <td>
                                    <span class="category-badge category-<?= $catClass ?>">
                                        <?= htmlspecialchars($book['category']) ?>
                                    </span>
                                </td>
                                <td><?= $book['copyright_year'] ?></td>
                                <td><?= htmlspecialchars($book['isbn'] ?: '—') ?></td>
                                <td>
                                    <span class="quantity-badge" style="
                                        background: <?= $book['quantity'] >= 5 ? '#c8e6c9' : ($book['quantity'] >= 2 ? '#fff9c4' : '#ffcdd2') ?>;
                                        color: <?= $book['quantity'] >= 5 ? '#2e7d32' : ($book['quantity'] >= 2 ? '#f57f17' : '#c62828') ?>;
                                    ">
                                        <?= $book['quantity'] ?> <?= $book['quantity'] == 1 ? 'copy' : 'copies' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit" onclick="editBook(<?= $book['id'] ?>)">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-delete" onclick="if(confirm('Delete this book?')) alert('Delete not implemented yet');">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
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
            <form id="bookForm" method="POST">
                <input type="hidden" name="action" value="save_book">
                <input type="hidden" name="book_id" id="book_id" value="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="callNumber">Call Number / Shelf *</label>
                        <input type="text" id="callNumber" name="callNumber" required>
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN</label>
                        <input type="text" id="isbn" name="isbn">
                    </div>
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
addBtn?.addEventListener('click', () => {
    titleEl.textContent = 'Add New Book';
    form.reset();
    document.getElementById('book_id').value = '';
    modal.style.display = 'flex';
});
closeBtn?.addEventListener('click', () => modal.style.display = 'none');
cancelBtn?.addEventListener('click', () => modal.style.display = 'none');

function editBook(id) {
    const row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row) return;
    titleEl.textContent = 'Edit Book';
    
    document.getElementById('book_id').value      = id;
    document.getElementById('callNumber').value   = row.cells[0].textContent.trim();
    document.getElementById('title').value        = row.cells[1].textContent.trim();
    document.getElementById('author').value       = row.cells[3].textContent.trim();
    document.getElementById('category').value     = row.cells[4].querySelector('.category-badge')?.textContent.trim() || '';
    document.getElementById('copyrightYear').value = row.cells[5].textContent.trim();
    document.getElementById('isbn').value         = row.cells[6].textContent.trim() === '—' ? '' : row.cells[6].textContent.trim();
    document.getElementById('quantity').value     = parseInt(row.cells[7].textContent.trim()) || 1;
    
    modal.style.display = 'flex';
}

// Client-side search
document.getElementById('searchInput')?.addEventListener('input', function() {
    const term = this.value.toLowerCase().trim();
    document.querySelectorAll('#booksTableBody tr[data-id]').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});
</script>
</body>
</html>