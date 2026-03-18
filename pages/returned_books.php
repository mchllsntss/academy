<?php
// returned_books.php - Admin: Current Borrows & Return History + Overdue Fine + Damage + Not Returned option
// UI from first code, Working upload logic from second code

session_start();
require_once '../connection/dbconnection.php';

// Admin check
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != 1) {
    header("Location: ../pages/login.php?error=Access denied");
    exit;
}

define('FINE_PER_DAY', 5.00); // ₱5 per day overdue

// === CREATE UPLOAD DIRECTORY WITH FULL PERMISSIONS ===
$upload_dir = '../uploads/returns/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
    chmod($upload_dir, 0777);
}

// Handle Returned / Not Returned via POST from modal
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['action'])) {
    
    $request_id     = (int)$_POST['request_id'];
    $action         = $_POST['action']; // 'return_approve' o 'return_reject'
    $admin_notes    = trim($_POST['admin_notes'] ?? '');
    $is_damaged     = isset($_POST['is_damaged']) && $_POST['is_damaged'] === '1';
    $damage_fine    = $is_damaged ? (float)($_POST['damage_fine'] ?? 0) : 0.00;

    // === WORKING FILE UPLOAD HANDLING ===
    $attachment = '';
    
    // Check if file was uploaded
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] == 0) {
        
        $file_name = $_FILES['attachment']['name'];
        $file_tmp = $_FILES['attachment']['tmp_name'];
        $file_size = $_FILES['attachment']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions
        $allowed = array('jpg', 'jpeg', 'png', 'gif');
        
        if (in_array($file_ext, $allowed)) {
            // Check file size (max 2MB)
            if ($file_size <= 2097152) {
                
                // Create unique filename
                $new_filename = 'return_' . $request_id . '_' . time() . '.' . $file_ext;
                $destination = $upload_dir . $new_filename;
                
                // Move the file
                if (move_uploaded_file($file_tmp, $destination)) {
                    $attachment = $new_filename;
                    // Do NOT add photo reference to notes
                } else {
                    $message = "<strong>Error:</strong> Failed to move uploaded file.";
                }
            } else {
                $message = "<strong>Error:</strong> File too large. Max 2MB only.";
            }
        } else {
            $message = "<strong>Error:</strong> Invalid file type. JPG, PNG, GIF only.";
        }
    }

    // Get current data
    $stmt = $conn->prepare("SELECT status, book_id, return_date FROM book_requests WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $current = $result->fetch_assoc();
    $stmt->close();

    if (!$current) {
        $message = "<strong>Error:</strong> Request not found.";
    } else {
        $current_status = $current['status'];
        $book_id = (int)$current['book_id'];
        $due_date = $current['return_date'] ? new DateTime($current['return_date']) : null;
        $new_status   = null;
        $do_increment = false;
        $overdue_fine = 0.00;
        $total_fine   = 0.00;
        $valid_action = false;

        if ($action === 'return_approve' && in_array($current_status, ['approved', 'return_pending'])) {
            $new_status   = 'returned';
            $do_increment = true;
            $valid_action = true;
            if ($due_date) {
                $today = new DateTime();
                $interval = $today->diff($due_date);
                if ($interval->invert) {
                    $overdue_fine = $interval->days * FINE_PER_DAY;
                }
            }
            $total_fine = $overdue_fine + $damage_fine;
            if ($is_damaged) {
                $damage_note = "Damaged book – fine ₱" . number_format($damage_fine, 2);
                $admin_notes = $admin_notes ? $admin_notes . "\n" . $damage_note : $damage_note;
            }
        }
        elseif ($action === 'return_reject' && in_array($current_status, ['approved', 'return_pending'])) {
            $new_status   = 'not_returned';
            $do_increment = false;
            $valid_action = true;
            $total_fine   = 0.00;
            if (empty($admin_notes)) {
                $admin_notes = "Hindi naibalik ng borrower.";
            }
        }

        if ($valid_action) {
            $conn->begin_transaction();
            try {
                
                // UPDATE DATABASE WITH ATTACHMENT
                $sql = "UPDATE book_requests SET 
                        status = ?, 
                        admin_notes = ?, 
                        fine = ?, 
                        attachment = ?, 
                        updated_at = NOW() 
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                
                // If no attachment, set to NULL
                if (empty($attachment)) {
                    $attachment = null;
                }
                
                $stmt->bind_param("ssdsi", $new_status, $admin_notes, $total_fine, $attachment, $request_id);
                
                if (!$stmt->execute()) {
                    throw new Exception("Database error: " . $stmt->error);
                }
                
                $stmt->close();

                if ($do_increment) {
                    $stmt = $conn->prepare("UPDATE books SET quantity = quantity + 1 WHERE id = ?");
                    $stmt->bind_param("i", $book_id);
                    $stmt->execute();
                    $stmt->close();
                }

                $conn->commit();
                
                // Success message with attachment info
                if (!empty($attachment)) {
                    $message = "SUCCESS! Record updated with photo: " . $attachment;
                } else {
                    $message = "SUCCESS! Record updated successfully.";
                }
                
                header("Location: returned_books.php?success=1&msg=" . urlencode($message));
                exit;
                
            } catch (Exception $e) {
                $conn->rollback();
                $message = "<strong>Error:</strong> " . $e->getMessage();
            }
        }
    }
}

// Success message from redirect
if (isset($_GET['success'])) {
    $message = isset($_GET['msg']) ? urldecode($_GET['msg']) : "<strong>Tagumpay!</strong> Na-update ang record.";
}

// ────────────────────────────────────────────────
// 1. Active Borrows
// ────────────────────────────────────────────────
$active_requests = [];
$sql_active = "
    SELECT
        r.id, r.status, r.return_date,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS member_name,
        u.profile_id,
        CASE 
            WHEN u.profile_id = 1 THEN 'Administrator'
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown' 
        END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date
    FROM book_requests r
    JOIN users u ON r.student_id = u.id
    JOIN books b ON r.book_id = b.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status IN ('approved', 'return_pending')
    ORDER BY r.request_date DESC
";
$result_active = $conn->query($sql_active);
if ($result_active) {
    while ($row = $result_active->fetch_assoc()) {
        $fine_display = 0;
        if ($row['return_date']) {
            $due = new DateTime($row['return_date']);
            $today = new DateTime();
            if ($today > $due) {
                $interval = $today->diff($due);
                $fine_display = $interval->days * FINE_PER_DAY;
            }
        }
        $row['fine_display'] = $fine_display;
        $active_requests[] = $row;
    }
}

// ────────────────────────────────────────────────
// 2. Return History - DIRECT DATABASE CHECK
// ────────────────────────────────────────────────
$per_page = 10;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $per_page;

// Get total count
$count_result = $conn->query("SELECT COUNT(*) as total FROM book_requests WHERE status IN ('returned', 'not_returned')");
$total_history = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_history / $per_page);

// Get history records
$history_requests = [];
$sql_history = "
    SELECT
        r.id,
        r.status,
        COALESCE(s.student_id, f.faculty_id, nf.employee_id, u.username, '—') AS member_id,
        TRIM(CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) AS member_name,
        u.profile_id,
        CASE 
            WHEN u.profile_id = 1 THEN 'Administrator'
            WHEN u.profile_id = 2 THEN 'Student'
            WHEN u.profile_id = 3 THEN 'Faculty'
            WHEN u.profile_id = 4 THEN 'Non-Faculty'
            ELSE 'Unknown' 
        END AS role,
        b.title AS book_title,
        r.request_date AS borrow_date,
        r.return_date,
        r.updated_at AS action_date,
        r.admin_notes,
        r.fine,
        r.attachment
    FROM book_requests r
    JOIN users u ON r.student_id = u.id
    JOIN books b ON r.book_id = b.id
    LEFT JOIN students s ON u.id = s.user_id
    LEFT JOIN faculty f ON u.id = f.user_id
    LEFT JOIN non_faculty nf ON u.id = nf.user_id
    WHERE r.status IN ('returned', 'not_returned')
    ORDER BY r.updated_at DESC
    LIMIT ?, ?
";
$stmt = $conn->prepare($sql_history);
$stmt->bind_param("ii", $offset, $per_page);
$stmt->execute();
$history_requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// DEBUG: Check if attachment exists in database
$debug_sql = "SELECT id, attachment FROM book_requests WHERE attachment IS NOT NULL AND attachment != ''";
$debug_result = $conn->query($debug_sql);
$attachments_found = [];
while ($row = $debug_result->fetch_assoc()) {
    $attachments_found[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Admin - Returns Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Segoe UI',sans-serif; }
        body { background:#f5f7fa; }
        .content-wrapper { margin-left:250px; padding:25px; }
        .container { max-width:1400px; margin:0 auto; }
        header { background:#2e7d32; color:white; padding:20px; border-radius:8px; margin-bottom:25px; }
        h1 { font-size:28px; }
        h2 { color:#1b5e20; margin:30px 0 15px; border-left:5px solid #2e7d32; padding-left:12px; }
        .table-container { background:white; border-radius:10px; overflow-x:auto; box-shadow:0 2px 8px rgba(0,0,0,0.1); margin-bottom:30px; }
        table { width:100%; border-collapse:collapse; }
        th { background:#2e7d32; color:white; padding:12px; text-align:left; }
        td { padding:12px; border-bottom:1px solid #eee; vertical-align:middle; }
        tr:hover { background:#f5f5f5; }
        .btn { padding:6px 12px; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size:13px; }
        .btn-approve { background:#2e7d32; color:white; }
        .btn-approve:hover { background:#1b5e20; }
        .btn-reject { background:#c62828; color:white; }
        .btn-reject:hover { background:#b71c1c; }
        .status-badge { padding:4px 8px; border-radius:12px; font-size:12px; font-weight:600; }
        .status-borrowed { background:#e3f2fd; color:#1565c0; }
        .status-pending { background:#fff3cd; color:#856404; }
        .status-returned { background:#e8f5e9; color:#2e7d32; }
        .status-not-returned { background:#ffebee; color:#c62828; }
        .profile-badge { 
            display:inline-block; 
            padding:2px 8px; 
            border-radius:12px; 
            font-size:11px; 
            font-weight:600;
            margin-left:5px;
        }
        .profile-admin { background:#ff9800; color:#fff; }
        .profile-student { background:#2196f3; color:#fff; }
        .profile-faculty { background:#9c27b0; color:#fff; }
        .profile-nonfaculty { background:#009688; color:#fff; }
        .profile-unknown { background:#9e9e9e; color:#fff; }
        .message { padding:12px; background:#e8f5e9; border-left:5px solid #2e7d32; margin:15px 0; border-radius:4px; }
        .message.error { background:#ffebee; border-left-color:#c62828; }
        
        /* MODAL STYLES - IMPROVED */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(3px);
        }
        
        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h3 {
            font-size: 20px;
            color: #333;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .modal-header h3 i {
            color: #2e7d32;
        }
        
        .close {
            font-size: 28px;
            cursor: pointer;
            color: #999;
            transition: color 0.2s;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .info-panel {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            border-left: 4px solid #2e7d32;
        }
        
        .info-row {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-row:last-child {
            margin-bottom: 0;
        }
        
        .info-label {
            width: 100px;
            font-weight: 600;
            color: #666;
        }
        
        .info-value {
            flex: 1;
            color: #333;
            font-weight: 500;
        }
        
        .info-value.approve {
            color: #2e7d32;
        }
        
        .info-value.reject {
            color: #c62828;
        }
        
        .damage-section {
            background: #fff8e1;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            border: 1px solid #ffe082;
            display: none;
        }
        
        .damage-section.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .damage-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .damage-header label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            color: #856404;
            cursor: pointer;
        }
        
        .damage-header input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .fine-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ffe082;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        
        .fine-input:focus {
            outline: none;
            border-color: #ffb300;
        }
        
        .fine-input:disabled {
            background: #f5f5f5;
            border-color: #ddd;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
        }
        
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group textarea:focus {
            outline: none;
            border-color: #2e7d32;
        }
        
        .file-upload {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: border-color 0.2s;
            cursor: pointer;
            margin-bottom: 10px;
        }
        
        .file-upload:hover {
            border-color: #2e7d32;
            background: #f1f8e9;
        }
        
        .file-upload i {
            font-size: 40px;
            color: #999;
            margin-bottom: 10px;
        }
        
        .file-upload p {
            color: #666;
            font-size: 14px;
        }
        
        .file-upload small {
            color: #999;
            font-size: 12px;
        }
        
        .file-name {
            margin-top: 10px;
            font-size: 13px;
            color: #2e7d32;
            display: none;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 20px 25px;
            border-top: 1px solid #eee;
        }
        
        .modal-footer .btn {
            padding: 10px 20px;
            font-size: 14px;
        }
        
        .btn-cancel {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-cancel:hover {
            background: #e0e0e0;
        }
        
        .attachment-thumb { 
            width: 50px; 
            height: 50px; 
            object-fit: cover; 
            border-radius: 4px; 
            cursor: pointer; 
            border: 1px solid #ddd; 
            transition: transform 0.2s;
        }
        .attachment-thumb:hover { 
            transform: scale(1.1); 
            box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
        }
        .lightbox { 
            display:none; 
            position:fixed; 
            top:0; 
            left:0; 
            width:100%; 
            height:100%; 
            background:rgba(0,0,0,0.9); 
            justify-content:center; 
            align-items:center; 
            z-index:2000; 
        }
        .lightbox img { 
            max-width:90%; 
            max-height:90%; 
            border:3px solid white; 
            border-radius:4px; 
        }
        .close-lightbox { 
            position:absolute; 
            top:20px; 
            right:30px; 
            color:white; 
            font-size:40px; 
            cursor:pointer; 
        }
        .pagination { 
            text-align:center; 
            margin:30px 0; 
        }
        .pagination a, .pagination span { 
            display:inline-block; 
            padding:8px 16px; 
            margin:0 4px; 
            background:#2e7d32; 
            color:white; 
            text-decoration:none; 
            border-radius:4px; 
        }
        .pagination span {
            background: #1b5e20;
        }
        .debug-box { 
            background:#f0f0f0; 
            padding:10px; 
            margin:10px 0; 
            font-size:12px; 
            border:1px solid #ccc; 
            display:none; 
        }
        .member-info {
            display: flex;
            flex-direction: column;
        }
        .member-id {
            font-weight: 600;
            color: #2e7d32;
        }
        .member-name {
            font-size: 14px;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container">
            <header>
                <h1><i class="fas fa-book-return" style="margin-right:10px;"></i> Return Requests & History</h1>
            </header>
            
            <?php if (!empty($message)): ?>
                <div class="message <?= strpos($message, 'Error') !== false ? 'error' : '' ?>">
                    <i class="fas <?= strpos($message, 'Error') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>" style="margin-right:8px;"></i>
                    <?= $message ?>
                </div>
            <?php endif; ?>

            <!-- DEBUG INFO - REMOVE AFTER TESTING -->
            <div class="debug-box">
                <strong>Upload Directory:</strong> <?= $upload_dir ?><br>
                <strong>Is Writable:</strong> <?= is_writable($upload_dir) ? 'Yes' : 'No' ?><br>
                <strong>Files in Database:</strong> <?= count($attachments_found) ?><br>
                <?php foreach($attachments_found as $a): ?>
                    File: <?= $a['attachment'] ?> (ID: <?= $a['id'] ?>)<br>
                <?php endforeach; ?>
                <button onclick="this.parentElement.style.display='none'">Hide</button>
            </div>

            <!-- ACTIVE BORROWS -->
            <h2><i class="fas fa-clock" style="margin-right:8px; color:#2e7d32;"></i> Current Borrows & Pending Returns</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Member Name & Type</th>
                            <th>Book</th>
                            <th>Borrowed</th>
                            <th>Due</th>
                            <th>Status</th>
                            <th>Fine</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($active_requests)): ?>
                            <tr><td colspan="8" style="text-align:center; padding:50px;"><i class="fas fa-inbox" style="font-size:24px; color:#ccc; margin-bottom:10px; display:block;"></i>No active borrows</td></tr>
                        <?php else: ?>
                            <?php foreach ($active_requests as $req): ?>
                            <tr>
                                <td><span class="member-id"><?= htmlspecialchars($req['member_id']) ?></span></td>
                                <td>
                                    <div class="member-info">
                                        <span class="member-name"><?= htmlspecialchars($req['member_name']) ?></span>
                                        <span class="profile-badge 
                                            <?php 
                                                switch($req['profile_id']) {
                                                    case 1: echo 'profile-admin'; break;
                                                    case 2: echo 'profile-student'; break;
                                                    case 3: echo 'profile-faculty'; break;
                                                    case 4: echo 'profile-nonfaculty'; break;
                                                    default: echo 'profile-unknown';
                                                }
                                            ?>">
                                            <?= htmlspecialchars($req['role']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($req['book_title']) ?></td>
                                <td><?= date('M d, Y', strtotime($req['borrow_date'])) ?></td>
                                <td><?= $req['return_date'] ? date('M d, Y', strtotime($req['return_date'])) : '—' ?></td>
                                <td><span class="status-badge <?= $req['status'] == 'approved' ? 'status-borrowed' : 'status-pending' ?>"><?= $req['status'] == 'approved' ? 'Borrowed' : 'Return Pending' ?></span></td>
                                <td><?= $req['fine_display'] > 0 ? '₱' . number_format($req['fine_display'],2) : '—' ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-approve" onclick="openModal(<?= $req['id'] ?>, 'approve', '<?= addslashes($req['member_name']) ?>', '<?= addslashes($req['book_title']) ?>', '<?= addslashes($req['member_id']) ?>')"><i class="fas fa-check-circle"></i> Returned</button>
                                        <button class="btn btn-reject" onclick="openModal(<?= $req['id'] ?>, 'reject', '<?= addslashes($req['member_name']) ?>', '<?= addslashes($req['book_title']) ?>', '<?= addslashes($req['member_id']) ?>')"><i class="fas fa-times-circle"></i> Not Returned</button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- RETURN HISTORY -->
            <h2><i class="fas fa-history" style="margin-right:8px; color:#2e7d32;"></i> Return History</h2>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Status</th>
                            <th>Member ID</th>
                            <th>Member Name & Type</th>
                            <th>Book</th>
                            <th>Borrowed</th>
                            <th>Returned</th>
                            <th>Fine</th>
                            <th>Notes</th>
                            <th>Attachment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history_requests)): ?>
                            <tr><td colspan="9" style="text-align:center; padding:50px;"><i class="fas fa-history" style="font-size:24px; color:#ccc; margin-bottom:10px; display:block;"></i>No history yet</td></tr>
                        <?php else: ?>
                            <?php foreach ($history_requests as $req): ?>
                            <tr>
                                <td><span class="status-badge <?= $req['status'] == 'returned' ? 'status-returned' : 'status-not-returned' ?>"><?= $req['status'] == 'returned' ? 'Returned' : 'Not Returned' ?></span></td>
                                <td><span class="member-id"><?= htmlspecialchars($req['member_id']) ?></span></td>
                                <td>
                                    <div class="member-info">
                                        <span class="member-name"><?= htmlspecialchars($req['member_name']) ?></span>
                                        <span class="profile-badge 
                                            <?php 
                                                switch($req['profile_id']) {
                                                    case 1: echo 'profile-admin'; break;
                                                    case 2: echo 'profile-student'; break;
                                                    case 3: echo 'profile-faculty'; break;
                                                    case 4: echo 'profile-nonfaculty'; break;
                                                    default: echo 'profile-unknown';
                                                }
                                            ?>">
                                            <?= htmlspecialchars($req['role']) ?>
                                        </span>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($req['book_title']) ?></td>
                                <td><?= date('M d, Y', strtotime($req['borrow_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($req['action_date'])) ?></td>
                                <td><?= $req['fine'] > 0 ? '₱' . number_format($req['fine'],2) : '—' ?></td>
                                <td><small><?= htmlspecialchars($req['admin_notes'] ?? '—') ?></small></td>
                                <td>
                                    <?php if (!empty($req['attachment'])): ?>
                                        <?php 
                                        $img_path = '../uploads/returns/' . $req['attachment'];
                                        if (file_exists($img_path)): 
                                        ?>
                                            <img src="<?= $img_path ?>?t=<?= time() ?>" class="attachment-thumb" onclick="openLightbox('<?= $img_path ?>')" title="<?= $req['attachment'] ?>">
                                        <?php else: ?>
                                            <span style="color:red;"><i class="fas fa-exclamation-triangle"></i> Missing</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#999;">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for($i=1; $i<=$total_pages; $i++): ?>
                    <?php if($i == $page): ?>
                        <span><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ACTION MODAL - IMPROVED DESIGN -->
    <div id="actionModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-check-circle"></i> <span>Confirm Action</span></h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data" id="modalForm">
                <div class="modal-body">
                    <div class="info-panel">
                        <div class="info-row">
                            <span class="info-label">Member ID:</span>
                            <span class="info-value" id="modalMemberId"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Member:</span>
                            <span class="info-value" id="modalMember"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Book:</span>
                            <span class="info-value" id="modalBook"></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Action:</span>
                            <span class="info-value" id="modalActionText"></span>
                        </div>
                    </div>
                    
                    <!-- Damage Section -->
                    <div id="damageSection" class="damage-section">
                        <div class="damage-header">
                            <label>
                                <input type="checkbox" id="damageCheckbox" name="is_damaged" value="1">
                                <i class="fas fa-exclamation-triangle"></i> Mark as damaged
                            </label>
                        </div>
                        <input type="number" id="damageFine" name="damage_fine" class="fine-input" placeholder="Enter damage fine amount (₱)" min="0" step="0.01" disabled>
                    </div>
                    
                    <!-- Admin Notes -->
                    <div class="form-group">
                        <label for="adminNotes"><i class="fas fa-pen"></i> Admin Notes</label>
                        <textarea name="admin_notes" id="adminNotes" placeholder="Add any notes about this transaction..."></textarea>
                    </div>
                    
                    <!-- File Upload -->
                    <div class="form-group">
                        <label><i class="fas fa-camera"></i> Attachment (Optional)</label>
                        <div class="file-upload" onclick="document.getElementById('attachmentInput').click()">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <p>Click to upload or drag and drop</p>
                            <small>Max 2MB. JPG, PNG, GIF only.</small>
                            <div id="fileName" class="file-name"></div>
                        </div>
                        <input type="file" name="attachment" id="attachmentInput" accept="image/*" style="display: none;" onchange="updateFileName(this)">
                    </div>
                    
                    <input type="hidden" name="request_id" id="requestId">
                    <input type="hidden" name="action" id="actionInput">
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" class="btn" id="modalSubmit"><i class="fas fa-check"></i> Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div id="lightbox" class="lightbox" onclick="closeLightbox()">
        <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
        <img id="lightboxImg" src="" alt="">
    </div>

    <?php include '../components/footer.php'; ?>

    <script>
        // Modal functions
        function openModal(id, action, member, book, memberId) {
            document.getElementById('requestId').value = id;
            document.getElementById('actionInput').value = 'return_' + action;
            document.getElementById('modalMember').textContent = member;
            document.getElementById('modalMemberId').textContent = memberId;
            document.getElementById('modalBook').textContent = book;
            
            if (action === 'approve') {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-check-circle" style="color:#2e7d32;"></i> <span style="color:#2e7d32;">Confirm Returned</span>';
                document.getElementById('modalActionText').innerHTML = '<span style="color:#2e7d32; font-weight:600;"><i class="fas fa-check"></i> RETURNED</span>';
                document.getElementById('modalSubmit').className = 'btn btn-approve';
                document.getElementById('damageSection').classList.add('show');
            } else {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-times-circle" style="color:#c62828;"></i> <span style="color:#c62828;">Confirm Not Returned</span>';
                document.getElementById('modalActionText').innerHTML = '<span style="color:#c62828; font-weight:600;"><i class="fas fa-times"></i> NOT RETURNED</span>';
                document.getElementById('modalSubmit').className = 'btn btn-reject';
                document.getElementById('damageSection').classList.remove('show');
            }
            
            document.getElementById('adminNotes').value = '';
            document.getElementById('attachmentInput').value = '';
            document.getElementById('fileName').style.display = 'none';
            document.getElementById('fileName').textContent = '';
            document.getElementById('damageCheckbox').checked = false;
            document.getElementById('damageFine').disabled = true;
            document.getElementById('damageFine').value = '';
            
            document.getElementById('actionModal').style.display = 'flex';
        }
        
        function closeModal() {
            document.getElementById('actionModal').style.display = 'none';
        }
        
        // Damage checkbox
        document.getElementById('damageCheckbox')?.addEventListener('change', function() {
            document.getElementById('damageFine').disabled = !this.checked;
            if (this.checked) {
                document.getElementById('damageFine').focus();
            }
        });
        
        // File upload handler
        function updateFileName(input) {
            const fileNameDiv = document.getElementById('fileName');
            if (input.files.length > 0) {
                const file = input.files[0];
                if (file.size > 2 * 1024 * 1024) {
                    alert('File too large! Max 2MB only.');
                    input.value = '';
                    fileNameDiv.style.display = 'none';
                    return;
                }
                fileNameDiv.textContent = 'Selected: ' + file.name;
                fileNameDiv.style.display = 'block';
            } else {
                fileNameDiv.style.display = 'none';
            }
        }
        
        // Lightbox
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src + '?t=' + new Date().getTime();
            document.getElementById('lightbox').style.display = 'flex';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').style.display = 'none';
        }
        
        // Form validation
        document.getElementById('modalForm').addEventListener('submit', function(e) {
            const action = document.getElementById('actionInput').value;
            const notes = document.getElementById('adminNotes').value.trim();
            
            if (action === 'return_reject' && notes === '') {
                if (!confirm('No admin notes added. Continue anyway?')) {
                    e.preventDefault();
                    return;
                }
            }
            
            if (!confirm('Are you sure you want to proceed?')) {
                e.preventDefault();
            }
        });
        
        // Close modal when clicking outside
        window.onclick = function(e) {
            if (e.target == document.getElementById('actionModal')) {
                closeModal();
            }
            if (e.target == document.getElementById('lightbox')) {
                closeLightbox();
            }
        }
        
        // Prevent modal from closing when clicking inside
        document.querySelector('.modal-content')?.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>