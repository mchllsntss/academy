<?php
// books.php
require_once '../connection/dbconnection.php';
$message = '';

// Handle delete request
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];

    // Check if book is currently borrowed or has pending requests
    $check_borrow = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM book_requests WHERE book_id = ? AND status = 'approved') as borrowed_count,
            (SELECT COUNT(*) FROM book_requests WHERE book_id = ? AND status = 'pending') as pending_count
    ");
    $check_borrow->bind_param("ii", $delete_id, $delete_id);
    $check_borrow->execute();
    $check_borrow->bind_result($borrowed_count, $pending_count);
    $check_borrow->fetch();
    $check_borrow->close();

    if ($borrowed_count > 0) {
        $message = "<strong>Error:</strong> Cannot delete this book. It is currently borrowed by " . $borrowed_count . " user(s).";
    } elseif ($pending_count > 0) {
        $message = "<strong>Error:</strong> Cannot delete this book. It has " . $pending_count . " pending request(s).";
    } else {
        // Get cover image to delete
        $stmt = $conn->prepare("SELECT cover_image FROM books WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->bind_result($cover_image);
        $stmt->fetch();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM books WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            // Delete cover image if exists
            if ($cover_image && file_exists(__DIR__ . '/' . $cover_image)) {
                unlink(__DIR__ . '/' . $cover_image);
            }
            $message = "<strong>Success!</strong> Book has been deleted.";
        } else {
            $message = "<strong>Error:</strong> Failed to delete book. " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle add/edit book
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
                
                // Delete old cover if exists and different
                if ($book_id > 0 && $current_cover && $current_cover !== $cover_image) {
                    $old_file = __DIR__ . '/' . $current_cover;
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
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
            $stmt->bind_param("sssssssisi", $call_number, $isbn, $title, $shelf_location, $author, $category, $copyright_year, $quantity, $cover_image, $book_id);
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

// Handle walk-in borrow with proper availability check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_book'])) {
    $book_id = (int)$_POST['book_id'];
    $user_id = (int)$_POST['user_id'];
    $request_type = 'borrow';

    // Start transaction
    $conn->begin_transaction();

    try {
        // Check book availability with lock
        $book_check = $conn->query("SELECT quantity FROM books WHERE id = $book_id FOR UPDATE")->fetch_assoc();
        
        if (!$book_check) {
            throw new Exception("Book not found.");
        }

        if ($book_check['quantity'] <= 0) {
            throw new Exception("Book is not available for borrowing.");
        }

        // Check if user already has this book borrowed and not returned
        $existing_borrow = $conn->query("
            SELECT COUNT(*) as count 
            FROM book_requests 
            WHERE book_id = $book_id 
            AND student_id = $user_id 
            AND status = 'approved' 
            AND return_date >= CURDATE()
        ")->fetch_assoc();

        if ($existing_borrow['count'] > 0) {
            throw new Exception("User already has this book borrowed and not yet returned.");
        }

        // Get user profile for return days
        $profile_check = $conn->query("SELECT profile_id FROM users WHERE id = $user_id")->fetch_assoc();
        
        if (!$profile_check) {
            throw new Exception("User profile not found.");
        }

        $profile_id = $profile_check['profile_id'];
        $days = ($profile_id == 2) ? 7 : 30; // Student: 7 days, Faculty/Non-Faculty: 30 days
        
        // Insert borrow request (auto-approved for walk-in)
        $stmt = $conn->prepare("
            INSERT INTO book_requests
            (student_id, book_id, request_type, status, return_date)
            VALUES (?, ?, ?, 'approved', DATE_ADD(CURDATE(), INTERVAL ? DAY))
        ");
        $stmt->bind_param("iisi", $user_id, $book_id, $request_type, $days);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create borrow record: " . $stmt->error);
        }
        $stmt->close();

        // Decrease book quantity
        $update_result = $conn->query("UPDATE books SET quantity = quantity - 1 WHERE id = $book_id");
        
        if (!$update_result) {
            throw new Exception("Failed to update book quantity.");
        }

        // Commit transaction
        $conn->commit();

        $return_date_str = date('M d, Y', strtotime("+$days days"));
        $message = "<strong>Success!</strong> Book borrowed successfully. Return date is set to <strong>$return_date_str</strong>.";

    } catch (Exception $e) {
        $conn->rollback();
        $message = "<strong>Error:</strong> " . $e->getMessage();
    }
}

// Handle return book
if (isset($_GET['return_id'])) {
    $request_id = (int)$_GET['return_id'];
    
    $conn->begin_transaction();
    
    try {
        // Get book_id from request
        $request = $conn->query("SELECT book_id FROM book_requests WHERE id = $request_id")->fetch_assoc();
        
        if (!$request) {
            throw new Exception("Request not found.");
        }
        
        // Update request status
        $update = $conn->query("UPDATE book_requests SET status = 'returned' WHERE id = $request_id");
        
        if (!$update) {
            throw new Exception("Failed to update request status.");
        }
        
        // Increase book quantity
        $increase = $conn->query("UPDATE books SET quantity = quantity + 1 WHERE id = " . $request['book_id']);
        
        if (!$increase) {
            throw new Exception("Failed to update book quantity.");
        }
        
        $conn->commit();
        $message = "<strong>Success!</strong> Book has been returned.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $message = "<strong>Error:</strong> " . $e->getMessage();
    }
}

// Pagination & Search Setup
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
if (!empty($limit_params)) {
    $stmt->bind_param($limit_types, ...$limit_params);
}
$stmt->execute();
$result = $stmt->get_result();
$books = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get all books for borrow dropdown with availability info
$all_books_for_borrow = [];
$books_query = "
    SELECT 
        b.*,
        COALESCE((SELECT COUNT(*) FROM book_requests WHERE book_id = b.id AND status = 'approved'), 0) as borrowed_count,
        COALESCE((SELECT COUNT(*) FROM book_requests WHERE book_id = b.id AND status = 'pending'), 0) as pending_count
    FROM books b
    ORDER BY b.title ASC
";
$books_result = $conn->query($books_query);
if ($books_result) {
    while ($row = $books_result->fetch_assoc()) {
        $row['available'] = $row['quantity'] - $row['borrowed_count'];
        $all_books_for_borrow[] = $row;
    }
}

// Get all members for borrow dropdown
$all_members = [];
$members_query = "
    SELECT
        u.id AS user_id,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username) AS member_id,
        CONCAT(u.first_name, ' ', u.last_name) AS full_name,
        u.first_name,
        u.last_name,
        u.username,
        CASE
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown'
        END AS role,
        u.profile_id
    FROM users u
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE u.profile_id IN (2, 3, 4)
    ORDER BY u.first_name
";
$members_result = $conn->query($members_query);
if ($members_result) {
    $all_members = $members_result->fetch_all(MYSQLI_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management - La Trinidad Academy</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- jQuery (required for Select2) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <!-- Select2 Theme -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

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
            transition: margin-left 0.3s;
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

        .btn-return {
            background: #ff9800;
            color: white;
        }
        .btn-return:hover {
            background: #f57c00;
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

        .btn-cancel {
            background: #757575;
            color: white;
        }
        .btn-cancel:hover {
            background: #616161;
        }

        .btn-save {
            background: #2e7d32;
            color: white;
        }
        .btn-save:hover {
            background: #1b5e20;
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
            background: #e8f5e9;
            color: #2e7d32;
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
        .borrow-header {
            background: #4caf50;
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
            display: flex;
            gap: 12px;
            justify-content: flex-end;
        }

        /* Print styles */
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
        input, select, textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            font-size: 1rem;
        }
        input:focus, select:focus, textarea:focus {
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
        .warning-text {
            color: #f57c00;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .success-text {
            color: #2e7d32;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge-success {
            background: #c8e6c9;
            color: #2e7d32;
        }
        .badge-warning {
            background: #fff3e0;
            color: #f57c00;
        }
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        /* Select2 Custom Styles */
        .select2-container--bootstrap-5 .select2-selection {
            min-height: 45px;
            padding: 5px;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 35px;
            font-size: 1rem;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 43px;
        }
        .select2-container--bootstrap-5 .select2-dropdown {
            border-color: #2e7d32;
        }
        .select2-container--bootstrap-5 .select2-results__option--selected {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        .select2-container--bootstrap-5 .select2-results__option--highlighted {
            background-color: #4caf50;
            color: white;
        }
        .select2-search__field:focus {
            border-color: #2e7d32 !important;
            box-shadow: 0 0 0 3px rgba(46,125,50,0.15) !important;
        }
        .book-option, .member-option {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 5px;
        }
        .book-option i, .member-option i {
            color: #2e7d32;
            width: 20px;
        }
        .book-option-details, .member-option-details {
            display: flex;
            flex-direction: column;
        }
        .book-option-title {
            font-weight: 600;
            color: #333;
        }
        .book-option-meta {
            font-size: 0.85rem;
            color: #666;
        }
        .member-option-name {
            font-weight: 600;
            color: #333;
        }
        .member-option-id {
            font-size: 0.85rem;
            color: #666;
        }
        .member-option-role {
            font-size: 0.8rem;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 5px;
        }
        .role-student { background: #e3f2fd; color: #1976d2; }
        .role-faculty { background: #e8f5e9; color: #2e7d32; }
        .role-nonfaculty { background: #fff3e0; color: #f57c00; }
        .disabled-option {
            opacity: 0.5;
        }
        .select2-container--bootstrap-5 .select2-selection--single {
            height: 45px !important;
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
                    // Check if book has pending/approved borrows
                    $status_check = $conn->prepare("
                        SELECT 
                            COUNT(*) as total_borrowed,
                            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                        FROM book_requests 
                        WHERE book_id = ? AND status IN ('pending', 'approved')
                    ");
                    $status_check->bind_param("i", $book['id']);
                    $status_check->execute();
                    $status_result = $status_check->get_result();
                    $status_data = $status_result->fetch_assoc();
                    $status_check->close();
                    
                    $borrowed_count = $status_data['total_borrowed'] ?? 0;
                    $pending_count = $status_data['pending_count'] ?? 0;
                    $available = $book['quantity'] - $borrowed_count;
                    
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
                                <p><strong>Category:</strong> <span class="category-badge"><?= htmlspecialchars($book['category']) ?></span></p>
                                <p><strong>Copyright Year:</strong> <span class="detail-year"><?= $book['copyright_year'] ?></span></p>
                                <p><strong>ISBN:</strong> <span class="detail-isbn"><?= htmlspecialchars($book['isbn'] ?: '—') ?></span></p>
                                <p><strong>Status:</strong>
                                    <span class="quantity-badge" style="
                                        background: <?= $available > 2 ? '#c8e6c9' : ($available > 0 ? '#fff9c4' : '#ffcdd2') ?>;
                                        color: <?= $available > 2 ? '#2e7d32' : ($available > 0 ? '#f57f17' : '#c62828') ?>;
                                    ">
                                        <?= $available ?> available / <?= $book['quantity'] ?> total
                                    </span>
                                </p>
                                <?php if ($pending_count > 0): ?>
                                    <p><small class="badge badge-warning"><?= $pending_count ?> pending request(s)</small></p>
                                <?php endif; ?>
                            </div>
                            <div class="action-buttons">
                                <button class="btn btn-edit" onclick="editBook(<?= $book['id'] ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if ($available > 0): ?>
                                    <button class="btn btn-borrow" onclick="quickBorrow(<?= $book['id'] ?>)">
                                        <i class="fas fa-hand-holding"></i> Borrow
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-borrow" style="background:#ccc; cursor:not-allowed;" disabled>
                                        <i class="fas fa-hand-holding"></i> Unavailable
                                    </button>
                                <?php endif; ?>
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
                        <label for="quantity">Total Quantity *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required>
                        <small>Total number of copies in library</small>
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
                    <label for="book_select"><i class="fas fa-book"></i> Search and Select Book *</label>
                    <select name="book_id" id="book_select" class="form-select book-search-dropdown" required style="width: 100%;">
                        <option value="">-- Search for a Book --</option>
                        <?php foreach ($all_books_for_borrow as $book): 
                            $available = $book['available'];
                            $status_class = $available > 0 ? 'badge-success' : 'badge-danger';
                            $status_text = $available > 0 ? "$available available" : "Not available";
                        ?>
                            <option value="<?= $book['id'] ?>" 
                                    data-title="<?= htmlspecialchars($book['title']) ?>"
                                    data-author="<?= htmlspecialchars($book['author']) ?>"
                                    data-call="<?= htmlspecialchars($book['call_number']) ?>"
                                    data-available="<?= $available ?>"
                                    data-total="<?= $book['quantity'] ?>"
                                    data-cover="<?= htmlspecialchars($book['cover_image'] ?? '') ?>"
                                    data-isbn="<?= htmlspecialchars($book['isbn'] ?? '') ?>"
                                    data-year="<?= $book['copyright_year'] ?>"
                                    data-category="<?= htmlspecialchars($book['category']) ?>"
                                    <?= $available <= 0 ? 'disabled' : '' ?>>
                                <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?> 
                                (Call No: <?= htmlspecialchars($book['call_number']) ?>) - [<?= $status_text ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Type to search by title, author, call number, ISBN, or category</small>
                </div>
                
                <div id="bookInfo" class="book-info-preview" style="display: none;">
                    <div style="display: flex; gap: 20px; align-items: start;">
                        <div id="bookCoverPreview" style="width: 80px; height: 100px; background: #f0f0f0; border-radius: 4px; overflow: hidden;">
                            <img id="bookCoverImg" src="" alt="Cover" style="width: 100%; height: 100%; object-fit: cover; display: none;">
                            <div id="bookCoverPlaceholder" style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; color: #ccc;">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                        <div style="flex: 1;">
                            <p><strong><i class="fas fa-book"></i> Title:</strong> <span id="bookTitle"></span></p>
                            <p><strong><i class="fas fa-user"></i> Author:</strong> <span id="bookAuthor"></span></p>
                            <p><strong><i class="fas fa-hashtag"></i> Call Number:</strong> <span id="bookCall"></span></p>
                            <p><strong><i class="fas fa-tags"></i> Category:</strong> <span id="bookCategory"></span></p>
                            <p><strong><i class="fas fa-calendar"></i> Year:</strong> <span id="bookYear"></span></p>
                            <p><strong><i class="fas fa-barcode"></i> ISBN:</strong> <span id="bookISBN"></span></p>
                            <p><strong><i class="fas fa-copy"></i> Available:</strong> <span id="bookAvailable"></span> / <span id="bookTotal"></span> copies</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="member_select"><i class="fas fa-users"></i> Search and Select Member *</label>
                    <select name="user_id" id="member_select" class="form-select member-search-dropdown" required style="width: 100%;">
                        <option value="">-- Search for a Member --</option>
                        <?php foreach ($all_members as $member): 
                            $role_class = '';
                            if ($member['role'] == 'Student') $role_class = 'role-student';
                            elseif ($member['role'] == 'Faculty') $role_class = 'role-faculty';
                            elseif ($member['role'] == 'Non-Faculty') $role_class = 'role-nonfaculty';
                        ?>
                            <option value="<?= $member['user_id'] ?>" 
                                    data-role="<?= htmlspecialchars($member['role']) ?>"
                                    data-member-id="<?= htmlspecialchars($member['member_id']) ?>"
                                    data-fullname="<?= htmlspecialchars($member['full_name']) ?>"
                                    data-firstname="<?= htmlspecialchars($member['first_name']) ?>"
                                    data-lastname="<?= htmlspecialchars($member['last_name']) ?>"
                                    data-username="<?= htmlspecialchars($member['username']) ?>">
                                <?= htmlspecialchars($member['member_id'] ?: '—') ?> - 
                                <?= htmlspecialchars($member['full_name']) ?>
                                (<?= htmlspecialchars($member['role']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted">Type to search by name, ID, or username</small>
                </div>
                
                <div id="memberInfo" class="book-info-preview" style="display: none; border-left-color: #1976d2;">
                    <p><strong><i class="fas fa-id-card"></i> Member ID:</strong> <span id="memberId"></span></p>
                    <p><strong><i class="fas fa-user"></i> Full Name:</strong> <span id="memberName"></span></p>
                    <p><strong><i class="fas fa-tag"></i> Role:</strong> <span id="memberRole"></span></p>
                    <p><strong><i class="fas fa-calendar-alt"></i> Borrowing Period:</strong> <span id="borrowingPeriod"></span></p>
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Request Type</label>
                    <input type="text" value="Borrow (Walk-in)" readonly style="background-color: #e8f5e9; font-weight: bold; color: #2e7d32; border: 2px solid #2e7d32;">
                </div>
                
                <div class="form-group">
                    <label><i class="fas fa-calendar-alt"></i> Return Date (Auto-set based on role)</label>
                    <input type="text" id="returnDatePreview" value="Will be set automatically" readonly style="background-color: #f0f0f0; font-weight: bold;">
                    <small style="color: #666; display: block; margin-top: 5px;">
                        Student: 7 days | Faculty / Non-Faculty: 30 days
                    </small>
                </div>
                
                <div id="borrowWarning" class="warning-text" style="display: none; margin-bottom: 15px; padding: 10px; background: #fff3e0; border-radius: 4px;">
                    <i class="fas fa-exclamation-triangle"></i> <span id="warningMessage">Please select both book and member to continue.</span>
                </div>
                
                <button type="submit" class="btn-submit" id="confirmBorrowBtn">
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
    document.body.style.overflow = 'hidden';
});

closeBtn?.addEventListener('click', () => {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
});

cancelBtn?.addEventListener('click', () => {
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';
});

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

    // Extract total quantity from status text
    const statusText = card.querySelector('.quantity-badge')?.textContent.trim() || '';
    const match = statusText.match(/available \/ (\d+) total/);
    document.getElementById('quantity').value = match ? parseInt(match[1]) : 1;

    const coverImg = card.querySelector('.book-cover img');
    if (coverImg && coverImg.src) {
        currentCoverImg.src = coverImg.src;
        currentCoverPreview.style.display = 'block';
    } else {
        currentCoverPreview.style.display = 'none';
    }
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// ==============================================
// BORROW MODAL WITH SELECT2 - FIXED VERSION
// ==============================================
const borrowModal = document.getElementById('borrowModal');
const openBorrowBtn = document.getElementById('openBorrowModal');
const closeBorrowBtn = document.getElementById('closeBorrowModal');

// Helper function to escape HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Format book options in dropdown
function formatBookOption(option) {
    if (!option.id) {
        return option.text;
    }
    
    const $option = $(option.element);
    const title = $option.data('title') || option.text;
    const author = $option.data('author') || '';
    const call = $option.data('call') || '';
    const available = $option.data('available') || 0;
    const isbn = $option.data('isbn') || '';
    
    const isDisabled = $option.is(':disabled');
    const statusClass = available > 0 ? 'badge-success' : 'badge-danger';
    const statusText = available > 0 ? `${available} available` : 'Not available';
    
    return $(`
        <div class="book-option ${isDisabled ? 'disabled-option' : ''}">
            <i class="fas fa-book"></i>
            <div class="book-option-details">
                <span class="book-option-title">${escapeHtml(title)}</span>
                <span class="book-option-meta">
                    by ${escapeHtml(author)} | Call: ${escapeHtml(call)} | ISBN: ${escapeHtml(isbn)}
                </span>
                <span>
                    <span class="badge ${statusClass}">${statusText}</span>
                </span>
            </div>
        </div>
    `);
}

function formatBookSelection(option) {
    if (!option.id) {
        return option.text;
    }
    
    const $option = $(option.element);
    const title = $option.data('title') || option.text;
    const author = $option.data('author') || '';
    const available = $option.data('available') || 0;
    
    return $(`<span><i class="fas fa-book"></i> ${escapeHtml(title)} by ${escapeHtml(author)} (${available} available)</span>`);
}

// Format member options in dropdown
function formatMemberOption(option) {
    if (!option.id) {
        return option.text;
    }
    
    const $option = $(option.element);
    const memberId = $option.data('member-id') || '';
    const fullname = $option.data('fullname') || '';
    const role = $option.data('role') || '';
    
    let roleClass = '';
    if (role === 'Student') roleClass = 'role-student';
    else if (role === 'Faculty') roleClass = 'role-faculty';
    else if (role === 'Non-Faculty') roleClass = 'role-nonfaculty';
    
    return $(`
        <div class="member-option">
            <i class="fas fa-user-graduate"></i>
            <div class="member-option-details">
                <span class="member-option-name">${escapeHtml(fullname)}</span>
                <span class="member-option-id">ID: ${escapeHtml(memberId)}</span>
                <span><span class="member-option-role ${roleClass}">${escapeHtml(role)}</span></span>
            </div>
        </div>
    `);
}

function formatMemberSelection(option) {
    if (!option.id) {
        return option.text;
    }
    
    const $option = $(option.element);
    const fullname = $option.data('fullname') || '';
    const role = $option.data('role') || '';
    
    return $(`<span><i class="fas fa-user"></i> ${escapeHtml(fullname)} (${escapeHtml(role)})</span>`);
}

// Initialize Select2 for book dropdown
function initBookSelect2() {
    if (typeof $ !== 'undefined' && $('#book_select').length) {
        $('#book_select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search for a book by title, author, call number, ISBN, or category',
            allowClear: true,
            dropdownParent: $('#borrowModal'),
            matcher: function(params, data) {
                // If there are no search terms, return all options
                if ($.trim(params.term) === '') {
                    return data;
                }

                // Custom matching to search in title, author, call number, ISBN, category
                const searchTerm = params.term.toLowerCase();
                const text = data.text.toLowerCase();
                const title = $(data.element).data('title')?.toLowerCase() || '';
                const author = $(data.element).data('author')?.toLowerCase() || '';
                const call = $(data.element).data('call')?.toLowerCase() || '';
                const isbn = $(data.element).data('isbn')?.toLowerCase() || '';
                const category = $(data.element).data('category')?.toLowerCase() || '';
                
                if (text.indexOf(searchTerm) > -1 || 
                    title.indexOf(searchTerm) > -1 || 
                    author.indexOf(searchTerm) > -1 || 
                    call.indexOf(searchTerm) > -1 || 
                    isbn.indexOf(searchTerm) > -1 || 
                    category.indexOf(searchTerm) > -1) {
                    return data;
                }
                
                return null;
            },
            templateResult: formatBookOption,
            templateSelection: formatBookSelection
        });
    }
}

// Initialize Select2 for member dropdown
function initMemberSelect2() {
    if (typeof $ !== 'undefined' && $('#member_select').length) {
        $('#member_select').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Search for a member by name, ID, or username',
            allowClear: true,
            dropdownParent: $('#borrowModal'),
            matcher: function(params, data) {
                if ($.trim(params.term) === '') {
                    return data;
                }

                const searchTerm = params.term.toLowerCase();
                const text = data.text.toLowerCase();
                const memberId = $(data.element).data('member-id')?.toLowerCase() || '';
                const fullname = $(data.element).data('fullname')?.toLowerCase() || '';
                const firstname = $(data.element).data('firstname')?.toLowerCase() || '';
                const lastname = $(data.element).data('lastname')?.toLowerCase() || '';
                const username = $(data.element).data('username')?.toLowerCase() || '';
                const role = $(data.element).data('role')?.toLowerCase() || '';
                
                if (text.indexOf(searchTerm) > -1 || 
                    memberId.indexOf(searchTerm) > -1 || 
                    fullname.indexOf(searchTerm) > -1 || 
                    firstname.indexOf(searchTerm) > -1 || 
                    lastname.indexOf(searchTerm) > -1 || 
                    username.indexOf(searchTerm) > -1 || 
                    role.indexOf(searchTerm) > -1) {
                    return data;
                }
                
                return null;
            },
            templateResult: formatMemberOption,
            templateSelection: formatMemberSelection
        });
    }
}

openBorrowBtn?.addEventListener('click', function() {
    borrowModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Small delay to ensure modal is rendered before initializing Select2
    setTimeout(function() {
        initBookSelect2();
        initMemberSelect2();
    }, 200);
});

closeBorrowBtn?.addEventListener('click', function() {
    borrowModal.style.display = 'none';
    document.body.style.overflow = 'auto';
    
    // Destroy Select2 instances to prevent memory leaks
    if (typeof $ !== 'undefined') {
        if ($('#book_select').data('select2')) {
            $('#book_select').select2('destroy');
        }
        if ($('#member_select').data('select2')) {
            $('#member_select').select2('destroy');
        }
    }
});

window.addEventListener('click', (e) => {
    if (e.target === borrowModal) {
        borrowModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        
        // Destroy Select2 instances
        if (typeof $ !== 'undefined') {
            if ($('#book_select').data('select2')) {
                $('#book_select').select2('destroy');
            }
            if ($('#member_select').data('select2')) {
                $('#member_select').select2('destroy');
            }
        }
    }
    if (e.target === modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
});

function quickBorrow(id) {
    borrowModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    setTimeout(function() {
        initBookSelect2();
        initMemberSelect2();
        
        // Set the book value
        $('#book_select').val(id).trigger('change');
        updateBookInfo();
    }, 200);
}

// Update book info when selection changes
$(document).on('change', '#book_select', function() {
    updateBookInfo();
});

// Update member info when selection changes
$(document).on('change', '#member_select', function() {
    updateMemberInfo();
    updateReturnDate();
});

function updateBookInfo() {
    const select = document.getElementById('book_select');
    const bookInfo = document.getElementById('bookInfo');
    const borrowWarning = document.getElementById('borrowWarning');
    const warningMessage = document.getElementById('warningMessage');
    
    // Get selected option from Select2
    let selectedOption = null;
    if (typeof $ !== 'undefined') {
        const select2Data = $('#book_select').select2('data')[0];
        if (select2Data && select2Data.element) {
            selectedOption = select2Data.element;
        }
    }
    
    if (!selectedOption && select.selectedIndex > -1) {
        selectedOption = select.options[select.selectedIndex];
    }
    
    if (selectedOption && selectedOption.value) {
        const title = selectedOption.getAttribute('data-title') || '';
        const author = selectedOption.getAttribute('data-author') || '';
        const call = selectedOption.getAttribute('data-call') || '';
        const available = selectedOption.getAttribute('data-available') || '0';
        const total = selectedOption.getAttribute('data-total') || '0';
        const cover = selectedOption.getAttribute('data-cover') || '';
        const isbn = selectedOption.getAttribute('data-isbn') || '—';
        const year = selectedOption.getAttribute('data-year') || '';
        const category = selectedOption.getAttribute('data-category') || '';
        
        document.getElementById('bookTitle').textContent = title;
        document.getElementById('bookAuthor').textContent = author;
        document.getElementById('bookCall').textContent = call;
        document.getElementById('bookAvailable').textContent = available;
        document.getElementById('bookTotal').textContent = total;
        document.getElementById('bookISBN').textContent = isbn;
        document.getElementById('bookYear').textContent = year;
        document.getElementById('bookCategory').textContent = category;
        
        // Handle cover image
        const coverImg = document.getElementById('bookCoverImg');
        const coverPlaceholder = document.getElementById('bookCoverPlaceholder');
        
        if (cover && cover !== '') {
            coverImg.src = cover;
            coverImg.style.display = 'block';
            coverPlaceholder.style.display = 'none';
        } else {
            coverImg.style.display = 'none';
            coverPlaceholder.style.display = 'flex';
        }
        
        bookInfo.style.display = 'block';
        
        // Check if member is selected
        const memberSelect = document.getElementById('member_select');
        if (!memberSelect.value) {
            borrowWarning.style.display = 'block';
            warningMessage.textContent = 'Please select a member to continue.';
        } else {
            borrowWarning.style.display = 'none';
        }
    } else {
        bookInfo.style.display = 'none';
    }
    updateReturnDate();
}

function updateMemberInfo() {
    const select = document.getElementById('member_select');
    const memberInfo = document.getElementById('memberInfo');
    const borrowWarning = document.getElementById('borrowWarning');
    const warningMessage = document.getElementById('warningMessage');
    
    // Get selected option from Select2
    let selectedOption = null;
    if (typeof $ !== 'undefined') {
        const select2Data = $('#member_select').select2('data')[0];
        if (select2Data && select2Data.element) {
            selectedOption = select2Data.element;
        }
    }
    
    if (!selectedOption && select.selectedIndex > -1) {
        selectedOption = select.options[select.selectedIndex];
    }
    
    if (selectedOption && selectedOption.value) {
        const memberId = selectedOption.getAttribute('data-member-id') || '';
        const fullname = selectedOption.getAttribute('data-fullname') || '';
        const role = selectedOption.getAttribute('data-role') || '';
        const days = (role === 'Student') ? 7 : 30;
        
        document.getElementById('memberId').textContent = memberId;
        document.getElementById('memberName').textContent = fullname;
        document.getElementById('memberRole').textContent = role;
        document.getElementById('borrowingPeriod').textContent = days + ' days';
        
        memberInfo.style.display = 'block';
        
        // Check if book is selected
        const bookSelect = document.getElementById('book_select');
        if (!bookSelect.value) {
            borrowWarning.style.display = 'block';
            warningMessage.textContent = 'Please select a book first.';
        } else {
            borrowWarning.style.display = 'none';
        }
    } else {
        memberInfo.style.display = 'none';
    }
}

function updateReturnDate() {
    const memberSelect = document.getElementById('member_select');
    const bookSelect = document.getElementById('book_select');
    const borrowWarning = document.getElementById('borrowWarning');
    const warningMessage = document.getElementById('warningMessage');
    
    // Get selected member
    let selectedMember = null;
    if (typeof $ !== 'undefined') {
        const select2Data = $('#member_select').select2('data')[0];
        if (select2Data && select2Data.element) {
            selectedMember = select2Data.element;
        }
    }
    
    if (!selectedMember && memberSelect.selectedIndex > -1) {
        selectedMember = memberSelect.options[memberSelect.selectedIndex];
    }
    
    const role = selectedMember ? selectedMember.getAttribute('data-role') : '';
    
    if (!bookSelect.value) {
        document.getElementById('returnDatePreview').value = 'Select book and member';
        if (memberSelect.value) {
            borrowWarning.style.display = 'block';
            warningMessage.textContent = 'Please select a book.';
        }
        return;
    }
    
    if (!memberSelect.value) {
        document.getElementById('returnDatePreview').value = 'Select member';
        borrowWarning.style.display = 'block';
        warningMessage.textContent = 'Please select a member.';
        return;
    }
    
    borrowWarning.style.display = 'none';
    
    let days = 7;
    if (role === 'Faculty' || role === 'Non-Faculty') {
        days = 30;
    }
    
    const returnDate = new Date();
    returnDate.setDate(returnDate.getDate() + days);
    const formatted = returnDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    document.getElementById('returnDatePreview').value = formatted + ` (${days} days)`;
}

// Validate borrow form before submit
document.getElementById('borrowForm')?.addEventListener('submit', function(e) {
    const bookSelect = document.getElementById('book_select');
    const memberSelect = document.getElementById('member_select');
    const borrowWarning = document.getElementById('borrowWarning');
    const warningMessage = document.getElementById('warningMessage');
    
    if (!bookSelect.value || !memberSelect.value) {
        e.preventDefault();
        borrowWarning.style.display = 'block';
        
        if (!bookSelect.value && !memberSelect.value) {
            warningMessage.textContent = 'Please select both book and member.';
        } else if (!bookSelect.value) {
            warningMessage.textContent = 'Please select a book.';
        } else {
            warningMessage.textContent = 'Please select a member.';
        }
        
        // Highlight the empty selects
        if (!bookSelect.value && typeof $ !== 'undefined') {
            $('#book_select').next('.select2').find('.select2-selection').css('border-color', '#f57c00');
        }
        if (!memberSelect.value && typeof $ !== 'undefined') {
            $('#member_select').next('.select2').find('.select2-selection').css('border-color', '#f57c00');
        }
        
        setTimeout(() => {
            if (typeof $ !== 'undefined') {
                $('#book_select').next('.select2').find('.select2-selection').css('border-color', '');
                $('#member_select').next('.select2').find('.select2-selection').css('border-color', '');
            }
        }, 3000);
    }
});

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

    // Get all books including their current status
    const cards = document.querySelectorAll('.book-card');
    let booksData = [];
    
    cards.forEach(card => {
        if (card.style.display !== 'none') {
            const title = card.querySelector('.book-title')?.textContent.trim() || '';
            const author = card.querySelector('.book-author')?.textContent.replace(/^by\s+/i, '').trim() || '';
            const callNumber = card.querySelector('.detail-call-number')?.textContent.trim() || '—';
            const category = card.querySelector('.category-badge')?.textContent.trim() || '';
            const isbn = card.querySelector('.detail-isbn')?.textContent.trim() || '—';
            const statusText = card.querySelector('.quantity-badge')?.textContent.trim() || '';
            const shelf = card.querySelector('.detail-shelf')?.textContent.trim() || '—';
            const year = card.querySelector('.detail-year')?.textContent.trim() || '';
            
            booksData.push({
                title, author, callNumber, category, isbn, statusText, shelf, year
            });
        }
    });

    let html = `
        <h1 style="color:#2e7d32; text-align:center; margin-bottom:10px;">Library Books Records</h1>
        <p style="text-align:center; color:#555; margin-bottom:5px;">Generated on: ${new Date().toLocaleString('en-PH')}</p>
        <p style="text-align:center; color:#777; margin-bottom:20px;">Total Books Displayed: ${booksData.length}</p>
        <table style="width:100%; border-collapse:collapse; font-size:0.9rem; margin-top:20px;">
            <thead>
                <tr style="background:#2e7d32; color:white;">
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Title</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Author</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Call Number</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Category</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">ISBN</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Status</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Shelf</th>
                    <th style="border:1px solid #ccc; padding:10px; text-align:left;">Year</th>
                </tr>
            </thead>
            <tbody>
    `;

    booksData.forEach(book => {
        html += `
            <tr style="border-bottom:1px solid #eee;">
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.title)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.author)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.callNumber)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.category)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.isbn)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.statusText)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.shelf)}</td>
                <td style="border:1px solid #ccc; padding:10px;">${escapeHtml(book.year)}</td>
            </tr>
        `;
    });

    html += `
            </tbody>
        </table>
    `;

    printContent.innerHTML = html;
    printModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// ==============================================
// DELETE WITH SWEETALERT2 CONFIRMATION
// ==============================================
function confirmDelete(id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this! This will also delete the book cover image.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "books.php?delete_id=" + id;
        }
    });
}

// Close modals when clicking outside
window.onclick = function(event) {
    if (event.target == modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    if (event.target == borrowModal) {
        borrowModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        if (typeof $ !== 'undefined') {
            if ($('#book_select').data('select2')) {
                $('#book_select').select2('destroy');
            }
            if ($('#member_select').data('select2')) {
                $('#member_select').select2('destroy');
            }
        }
    }
}
</script>
</body>
</html>