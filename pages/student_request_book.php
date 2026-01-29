<?php
// student_request_book.php - Request a Book + History of Requests
session_start();
require_once '../connection/dbconnection.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id']) || !is_numeric($_SESSION['user_id'])) {
    header("Location: ../pages/login.php?error=Please log in first");
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch student name for personalized message
$student_name = "Student";
$stmt_name = $conn->prepare("
    SELECT first_name, last_name 
    FROM students 
    WHERE user_id = ?
    LIMIT 1
");
if ($stmt_name) {
    $stmt_name->bind_param("i", $user_id);
    $stmt_name->execute();
    $res_name = $stmt_name->get_result();
    if ($row = $res_name->fetch_assoc()) {
        $student_name = htmlspecialchars(trim($row['first_name'] . ' ' . $row['last_name']));
    }
    $stmt_name->close();
}

// Handle new request submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $book_title   = trim($_POST['bookTitle'] ?? '');
    $author       = trim($_POST['bookAuthor'] ?? '');
    $isbn         = trim($_POST['bookISBN'] ?? '');
    $notes        = trim($_POST['additionalNotes'] ?? '');
    $request_date = $_POST['requestDate'] ?? date('Y-m-d');

    $errors = [];
    if (empty($book_title)) $errors[] = "Book title is required.";
    if (empty($request_date)) $errors[] = "Request date is required.";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO books_requests 
            (student_id, book_title, author, isbn, notes, request_date, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param("isssss", $user_id, $book_title, $author, $isbn, $notes, $request_date);

        if ($stmt->execute()) {
            $success_message = "Your request for <strong>\"$book_title\"</strong> has been submitted successfully!";
        } else {
            $error_message = "Error submitting request: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error_message = implode("<br>", $errors);
    }
}

// Fetch history of requests for this student only
$requests = [];
$stmt_history = $conn->prepare("
    SELECT 
        book_title,
        author,
        notes,
        request_date,
        status
    FROM books_requests 
    WHERE student_id = ?
    ORDER BY request_date DESC
");
if ($stmt_history) {
    $stmt_history->bind_param("i", $user_id);
    $stmt_history->execute();
    $result = $stmt_history->get_result();
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt_history->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request a Book - Library Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        body {
            background-color: #f5f7f0;
            color: #333;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .page-wrapper {
            display: flex;
            flex: 1;
        }
        .sidebar {
            width: 250px;
            background-color: #2e7d32;
            color: white;
            flex-shrink: 0;
        }
        .main-content {
            flex: 1;
            padding: 24px;
            background: #f5f7f0;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            width: 100%;
        }
        .header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        .header-section h1 {
            color: #2e7d32;
            font-size: 2.4rem;
            margin-bottom: 12px;
            position: relative;
            display: inline-block;
            padding-bottom: 14px;
        }
        .header-section h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 90px;
            height: 4px;
            background-color: #4caf50;
            border-radius: 2px;
        }
        .request-card, .history-card {
            background: white;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 8px 28px rgba(46, 125, 50, 0.14);
            border-top: 5px solid #4caf50;
            margin-bottom: 40px;
        }
        .request-card h2, .history-card h2 {
            color: #2e7d32;
            margin-bottom: 28px;
            text-align: center;
            font-size: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .form-group {
            margin-bottom: 24px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2e7d32;
            font-weight: 600;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .input-with-icon {
            position: relative;
        }
        .form-input {
            width: 100%;
            padding: 14px 14px 14px 48px;
            border: 2px solid #c8e6c9;
            border-radius: 8px;
            font-size: 15.5px;
            color: #2e7d32;
            transition: all 0.25s;
            background-color: #fafefa;
        }
        textarea.form-input {
            padding: 14px;
            min-height: 110px;
        }
        .form-input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.18);
            background-color: white;
        }
        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #4caf50;
            font-size: 1.25rem;
        }
        .submit-btn {
            width: 100%;
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 16px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s;
            margin-top: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .submit-btn:hover {
            background-color: #388e3c;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(56, 142, 60, 0.3);
        }
        .history-table {
            width: 100%;
            border-collapse: collapse;
        }
        .history-table th, .history-table td {
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        .history-table th {
            background: #2e7d32;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.95rem;
        }
        .history-table tr:hover {
            background: #f9fdf9;
        }
        .status-pending { color: #f59e0b; font-weight: 600; }
        .status-approved { color: #2e7d32; font-weight: 600; }
        .status-rejected { color: #c62828; font-weight: 600; }
        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: #777;
            font-size: 1.2rem;
        }
        .no-requests i {
            font-size: 3.5rem;
            color: #c8e6c9;
            margin-bottom: 15px;
        }
        @media (max-width: 992px) {
            .page-wrapper { flex-direction: column; }
            .sidebar { width: 100%; }
            .main-content { padding: 20px 16px; }
        }
        @media (max-width: 768px) {
            .header-section h1 { font-size: 2.1rem; }
            .request-card, .history-card { padding: 28px 24px; }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Sidebar -->
        <aside class="sidebar">
            <?php include '../components/student_sidebar.php'; ?>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <?php include '../components/header.php'; ?>

            <div class="container">
                <div class="header-section">
                    <h1><i class="fas fa-book-medical"></i> Request a Book</h1>
                    <p>Can't find the book you're looking for? Request it here and our librarians will do their best to acquire it for our collection.</p>
                </div>

                <div class="request-card">
                    <h2><i class="fas fa-file-alt"></i> Book Request Form</h2>

                    <?php if ($success_message): ?>
                        <div class="info-box" style="margin-bottom: 24px;">
                            <?= $success_message ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($error_message): ?>
                        <div class="info-box error" style="margin-bottom: 24px;">
                            <?= $error_message ?>
                        </div>
                    <?php endif; ?>

                    <form id="bookRequestForm" method="POST">
                        <div class="form-group">
                            <label for="requestDate"><i class="fas fa-calendar-alt"></i> Request Date</label>
                            <div class="input-with-icon">
                                <input type="date" id="requestDate" name="requestDate" class="form-input" required value="<?= date('Y-m-d') ?>" min="<?= date('Y-m-d') ?>">
                                <div class="input-icon"><i class="far fa-calendar"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookTitle"><i class="fas fa-book"></i> Book Title *</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookTitle" name="bookTitle" class="form-input" placeholder="Enter the book title you want to request" required>
                                <div class="input-icon"><i class="fas fa-book-open"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookAuthor"><i class="fas fa-user-edit"></i> Author (Optional)</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookAuthor" name="bookAuthor" class="form-input" placeholder="Enter author's name">
                                <div class="input-icon"><i class="fas fa-pen-nib"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookISBN"><i class="fas fa-barcode"></i> ISBN (Optional)</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookISBN" name="bookISBN" class="form-input" placeholder="Enter ISBN if known">
                                <div class="input-icon"><i class="fas fa-hashtag"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="additionalNotes"><i class="fas fa-sticky-note"></i> Additional Notes (Optional)</label>
                            <textarea id="additionalNotes" name="additionalNotes" class="form-input" rows="4" placeholder="Any additional information about the book request..."></textarea>
                        </div>

                        <button type="submit" name="submit_request" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Book Request
                        </button>
                    </form>
                </div>

                <!-- History of Requested Books -->
                <div class="history-card">
                    <h2><i class="fas fa-history"></i> History of Your Book Requests</h2>

                    <?php if (empty($requests)): ?>
                        <div class="no-requests">
                            <i class="fas fa-inbox"></i><br>
                            You haven't made any book requests yet.
                        </div>
                    <?php else: ?>
                        <table class="history-table">
                            <thead>
                                <tr>
                                    <th>Book Title</th>
                                    <th>Author</th>
                                    <th>Notes</th>
                                    <th>Request Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $req): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($req['book_title'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['author'] ?: '—') ?></td>
                                        <td><?= htmlspecialchars($req['notes'] ?: '—') ?></td>
                                        <td><?= $req['request_date'] ? date('F j, Y', strtotime($req['request_date'])) : '—' ?></td>
                                        <td>
                                            <?php
                                            $status = $req['status'] ?? 'pending';
                                            $class = $status === 'pending' ? 'status-pending' : 
                                                     ($status === 'approved' ? 'status-approved' : 'status-rejected');
                                            ?>
                                            <span class="<?= $class ?>"><?= ucfirst($status) ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>

            <?php include '../components/footer.php'; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dateInput = document.getElementById('requestDate');
            dateInput.value = today;
            dateInput.min = today;

            const maxDate = new Date();
            maxDate.setDate(maxDate.getDate() + 60);
            dateInput.max = maxDate.toISOString().split('T')[0];

            <?php if ($success_message): ?>
                Swal.fire({
                    title: 'Request Submitted!',
                    html: `
                        <div style="font-size: 3.5rem; color: #4caf50; margin: 20px 0;">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <p style="font-size: 1.2rem; color: #2e7d32; margin-bottom: 12px;">
                            Thank you, <?= explode(' ', '<?= $student_name ?>')[0] ?>!
                        </p>
                        <p style="color: #555; margin-bottom: 8px;">
                            <strong>"<?= htmlspecialchars(addslashes($_POST['bookTitle'] ?? '')) ?>"</strong> has been requested
                        </p>
                        <p style="color: #777; font-size: 0.95rem;">
                            Date: <?= date('F j, Y', strtotime($request_date)) ?>
                        </p>
                    `,
                    icon: 'success',
                    iconColor: '#4caf50',
                    showConfirmButton: false,
                    allowOutsideClick: true,
                    timer: 3500,
                    timerProgressBar: true,
                    background: '#f9fdf9',
                    customClass: { popup: 'swal-popup-green' }
                });
            <?php endif; ?>

            <?php if ($error_message): ?>
                Swal.fire({
                    title: 'Error',
                    html: '<?= addslashes($error_message) ?>',
                    icon: 'error',
                    confirmButtonColor: '#c62828'
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>