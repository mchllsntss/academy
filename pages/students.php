<?php
// members.php - Combined Member Management System with Pagination (5 per page)
// Database connection
if (!isset($conn)) {
    include '../connection/dbconnection.php';
}

class MemberController {
    private $conn;
    private $upload_dir = '../uploads/members/';
    private $message = '';
    private $errors = [];
    private $member_type = 'student';
    private $items_per_page = 5; // Set to 5 items per page
    
    public function __construct($conn) {
        $this->conn = $conn;
        
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    public function handleRequest() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['delete_member'])) {
                $this->deleteMember();
            } elseif (isset($_POST['update_member'])) {
                $this->updateMember();
            } elseif (isset($_POST['add_member'])) {
                $this->member_type = $_POST['member_type'] ?? 'student';
                $this->addMember();
            } elseif (isset($_POST['add_student'])) {
                $this->member_type = 'student';
                $_POST['member_type'] = 'student';
                $_POST['id_number'] = $_POST['student_id'] ?? '';
                $_POST['add_member'] = 1;
                $this->addMember();
            }
        }
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        if ($page < 1) $page = 1;
        
        return $this->getAllMembersPaginated($page);
    }
    
    private function addMember() {
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $mi = trim($_POST['middle_initial'] ?? '');
        $id_number = trim($_POST['id_number'] ?? $_POST['student_id'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $profile_image = null;
        
        $this->validateInput($first_name, $last_name, $id_number, $username, $email, $phone, $password, $confirm_pass);
        
        if (empty($this->errors)) {
            $this->checkEmailExists($email);
        }
        
        if (empty($this->errors)) {
            $this->checkUsernameExists($username);
        }
        
        if (empty($this->errors)) {
            $this->checkIdNumberExists($id_number, $this->member_type);
        }
        
        if (empty($this->errors) && !empty($_FILES['profile_image']['name'])) {
            $profile_image = $this->handleFileUpload();
        }
        
        if (empty($this->errors)) {
            $this->insertMember($first_name, $last_name, $mi, $id_number, $username, $email, $phone, $department, $password, $profile_image);
        } else {
            $this->message = implode("<br>", $this->errors);
        }
    }
    
    private function validateInput($first_name, $last_name, $id_number, $username, $email, $phone, $password, $confirm_pass) {
        if (empty($first_name)) $this->errors[] = "First Name is required.";
        if (empty($last_name)) $this->errors[] = "Last Name is required.";
        if (empty($id_number)) $this->errors[] = "ID Number is required.";
        if (empty($username)) $this->errors[] = "Username is required.";
        if (strlen($username) < 3 || strlen($username) > 20) $this->errors[] = "Username must be 3-20 characters.";
        if (empty($email)) $this->errors[] = "Email is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->errors[] = "Invalid email format.";
        if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{7,15}$/', $phone)) {
            $this->errors[] = "Invalid phone number format.";
        }
        if (empty($password)) $this->errors[] = "Password is required.";
        if ($password !== $confirm_pass) $this->errors[] = "Passwords do not match.";
        if (strlen($password) < 8) $this->errors[] = "Password must be at least 8 characters.";
    }
    
    private function checkEmailExists($email) {
        $check_email = $this->conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $this->errors[] = "Email address is already in use.";
        }
        $check_email->close();
    }
    
    private function checkUsernameExists($username) {
        $check_user = $this->conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        if ($check_user->get_result()->num_rows > 0) {
            $this->errors[] = "Username is already taken.";
        }
        $check_user->close();
    }
    
    private function checkIdNumberExists($id_number, $member_type) {
        $table = '';
        $id_field = '';
        
        switch ($member_type) {
            case 'student':
                $table = 'students';
                $id_field = 'student_id';
                break;
            case 'faculty':
                $table = 'faculty';
                $id_field = 'faculty_id';
                break;
            case 'non-faculty':
                $table = 'non_faculty';
                $id_field = 'employee_id';
                break;
        }
        
        if (!empty($table) && !empty($id_field)) {
            $check_id = $this->conn->prepare("SELECT id FROM $table WHERE $id_field = ?");
            $check_id->bind_param("s", $id_number);
            $check_id->execute();
            if ($check_id->get_result()->num_rows > 0) {
                $this->errors[] = ucfirst($member_type) . " ID number is already in use.";
            }
            $check_id->close();
        }
    }
    
    private function handleFileUpload() {
        $file_name = $_FILES['profile_image']['name'];
        $file_tmp = $_FILES['profile_image']['tmp_name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (!in_array($file_ext, $allowed)) {
            $this->errors[] = "Only JPG, JPEG, PNG & GIF files are allowed.";
            return null;
        }
        
        if ($file_size > 5242880) {
            $this->errors[] = "Image size must not exceed 5MB.";
            return null;
        }
        
        $new_file_name = strtolower($this->member_type) . '_' . uniqid() . '.' . $file_ext;
        $destination = $this->upload_dir . $new_file_name;
        
        if (move_uploaded_file($file_tmp, $destination)) {
            return 'uploads/members/' . $new_file_name;
        } else {
            $this->errors[] = "Failed to upload image.";
            return null;
        }
    }
    
    private function deleteMember() {
        $member_id = intval($_POST['member_id'] ?? 0);
        
        if ($member_id <= 0) {
            $this->message = "Invalid member ID.";
            return;
        }
        
        $checkMember = $this->conn->prepare("SELECT user_id FROM students WHERE id = ?");
        $checkMember->bind_param("i", $member_id);
        $checkMember->execute();
        $result = $checkMember->get_result();
        
        if ($result->num_rows === 0) {
            $this->message = "Member not found.";
            $checkMember->close();
            return;
        }
        
        $member = $result->fetch_assoc();
        $user_id = $member['user_id'];
        $checkMember->close();
        
        $this->conn->begin_transaction();
        try {
            $delStudent = $this->conn->prepare("DELETE FROM students WHERE id = ?");
            $delStudent->bind_param("i", $member_id);
            $delStudent->execute();
            $delStudent->close();
            
            $delUser = $this->conn->prepare("DELETE FROM users WHERE id = ?");
            $delUser->bind_param("i", $user_id);
            $delUser->execute();
            $delUser->close();
            
            $this->conn->commit();
            $this->message = "Member deleted successfully.";
        } catch (Exception $e) {
            $this->conn->rollback();
            $this->message = "Error deleting member: " . $e->getMessage();
        }
    }
    
    private function updateMember() {
        $member_id = intval($_POST['member_id'] ?? 0);
        $first_name = trim($_POST['first_name'] ?? '');
        $last_name = trim($_POST['last_name'] ?? '');
        $mi = trim($_POST['middle_initial'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';
        $profile_image = null;
        
        if ($member_id <= 0) {
            $this->message = "Invalid member ID.";
            return;
        }
        
        $checkStmt = $this->conn->prepare("SELECT user_id FROM students WHERE id = ?");
        $checkStmt->bind_param("i", $member_id);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        
        if ($result->num_rows === 0) {
            $this->message = "Member not found.";
            $checkStmt->close();
            return;
        }
        
        $student = $result->fetch_assoc();
        $user_id = $student['user_id'];
        $checkStmt->close();
        
        if (empty($first_name)) $this->errors[] = "First Name is required.";
        if (empty($last_name)) $this->errors[] = "Last Name is required.";
        if (empty($id_number)) $this->errors[] = "Student ID is required.";
        if (empty($email)) $this->errors[] = "Email is required.";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $this->errors[] = "Invalid email format.";
        if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{7,15}$/', $phone)) {
            $this->errors[] = "Invalid phone number format.";
        }
        
        if (!empty($password)) {
            if ($password !== $confirm_pass) $this->errors[] = "Passwords do not match.";
            if (strlen($password) < 8) $this->errors[] = "Password must be at least 8 characters.";
        }
        
        if (empty($this->errors) && !empty($_FILES['profile_image']['name'])) {
            $profile_image = $this->handleFileUpload();
        }
        
        if (empty($this->errors)) {
            $this->conn->begin_transaction();
            try {
                if (!empty($password)) {
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $updateUser = $this->conn->prepare("
                        UPDATE users
                        SET email = ?, phone = ?, password_hash = ?, first_name = ?, last_name = ?
                        WHERE id = ?
                    ");
                    $updateUser->bind_param("sssssi", $email, $phone, $password_hash, $first_name, $last_name, $user_id);
                } else {
                    $updateUser = $this->conn->prepare("
                        UPDATE users
                        SET email = ?, phone = ?, first_name = ?, last_name = ?
                        WHERE id = ?
                    ");
                    $updateUser->bind_param("ssssi", $email, $phone, $first_name, $last_name, $user_id);
                }
                
                if (!$updateUser->execute()) {
                    throw new Exception("Error updating user: " . $updateUser->error);
                }
                $updateUser->close();
                
                if ($profile_image) {
                    $updateStudent = $this->conn->prepare("
                        UPDATE students
                        SET student_id = ?, first_name = ?, last_name = ?, middle_initial = ?, profile_image = ?
                        WHERE id = ?
                    ");
                    $updateStudent->bind_param("sssssi", $id_number, $first_name, $last_name, $mi, $profile_image, $member_id);
                } else {
                    $updateStudent = $this->conn->prepare("
                        UPDATE students
                        SET student_id = ?, first_name = ?, last_name = ?, middle_initial = ?
                        WHERE id = ?
                    ");
                    $updateStudent->bind_param("ssssi", $id_number, $first_name, $last_name, $mi, $member_id);
                }
                
                if (!$updateStudent->execute()) {
                    throw new Exception("Error updating student: " . $updateStudent->error);
                }
                $updateStudent->close();
                
                $this->conn->commit();
                $this->message = "Member updated successfully.";
            } catch (Exception $e) {
                $this->conn->rollback();
                $this->message = "Error updating member: " . $e->getMessage();
            }
        } else {
            $this->message = implode("<br>", $this->errors);
        }
    }
    
    private function insertMember($first_name, $last_name, $mi, $id_number, $username, $email, $phone, $department, $password, $profile_image) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $profile_id = 2;
        if ($this->member_type === 'faculty') {
            $profile_id = 3;
        } elseif ($this->member_type === 'non-faculty') {
            $profile_id = 4;
        }
        
        $stmt_user = $this->conn->prepare("
            INSERT INTO users
            (username, email, first_name, last_name, phone, password_hash, profile_image, profile_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt_user->bind_param("sssssssi", $username, $email, $first_name, $last_name, $phone, $password_hash, $profile_image, $profile_id);
        
        if ($stmt_user->execute()) {
            $user_id = $this->conn->insert_id;
            
            switch ($this->member_type) {
                case 'student':
                    $this->insertStudent($user_id, $id_number, $first_name, $last_name, $mi, $profile_image);
                    break;
                case 'faculty':
                    $this->insertFaculty($user_id, $id_number, $first_name, $last_name, $mi, $department, $profile_image);
                    break;
                case 'non-faculty':
                    $this->insertNonFaculty($user_id, $id_number, $first_name, $last_name, $mi, $department, $profile_image);
                    break;
            }
            
            $type_name = ucfirst(str_replace('-', ' ', $this->member_type));
            $this->message = "Success! New {$type_name} account created.";
            
        } else {
            $this->message = "Error creating user account: " . $stmt_user->error;
        }
        $stmt_user->close();
    }
    
    private function insertStudent($user_id, $student_id, $first_name, $last_name, $mi, $profile_image) {
        $stmt = $this->conn->prepare("
            INSERT INTO students
            (user_id, student_id, first_name, last_name, middle_initial, profile_image, join_date, status)
            VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
        ");
        $stmt->bind_param("isssss", $user_id, $student_id, $first_name, $last_name, $mi, $profile_image);
        
        if ($stmt->execute()) {
            $this->message = "Success! New student account created.";
        } else {
            $this->message = "Error creating student record: " . $stmt->error;
        }
        $stmt->close();
    }
    
    private function insertFaculty($user_id, $faculty_id, $first_name, $last_name, $mi, $department, $profile_image) {
        $stmt = $this->conn->prepare("
            INSERT INTO faculty
            (user_id, faculty_id, first_name, last_name, middle_initial, department, profile_image, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("issssss", $user_id, $faculty_id, $first_name, $last_name, $mi, $department, $profile_image);
        
        if ($stmt->execute()) {
            $this->message = "Success! New faculty account created.";
        } else {
            $this->message = "Error creating faculty record: " . $stmt->error;
        }
        $stmt->close();
    }
    
    private function insertNonFaculty($user_id, $employee_id, $first_name, $last_name, $mi, $department, $profile_image) {
        $stmt = $this->conn->prepare("
            INSERT INTO non_faculty
            (user_id, employee_id, first_name, last_name, middle_initial, department, profile_image, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->bind_param("issssss", $user_id, $employee_id, $first_name, $last_name, $mi, $department, $profile_image);
        
        if ($stmt->execute()) {
            $this->message = "Success! New non-faculty staff account created.";
        } else {
            $this->message = "Error creating non-faculty record: " . $stmt->error;
        }
        $stmt->close();
    }
    
    private function getAllMembersPaginated($page = 1) {
        $offset = ($page - 1) * $this->items_per_page;
        
        $count_query = "
            SELECT COUNT(*) as total FROM (
                SELECT id FROM students
                UNION ALL
                SELECT id FROM faculty
                UNION ALL
                SELECT id FROM non_faculty
            ) as combined
        ";
        
        $count_result = $this->conn->query($count_query);
        $total_members = $count_result->fetch_assoc()['total'];
        $total_pages = ceil($total_members / $this->items_per_page);
        
        $query = "
            SELECT * FROM (
                SELECT
                    s.id,
                    s.student_id as member_id,
                    s.first_name,
                    s.last_name,
                    s.middle_initial,
                    s.profile_image,
                    s.join_date as member_since,
                    s.status,
                    s.created_at,
                    u.username,
                    u.email,
                    u.phone,
                    'student' as member_type,
                    NULL as department,
                    s.join_date as sort_date
                FROM students s
                LEFT JOIN users u ON s.user_id = u.id
                
                UNION ALL
                
                SELECT
                    f.id,
                    f.faculty_id as member_id,
                    f.first_name,
                    f.last_name,
                    f.middle_initial,
                    f.profile_image,
                    f.created_at as member_since,
                    f.status,
                    f.created_at,
                    u.username,
                    u.email,
                    u.phone,
                    'faculty' as member_type,
                    f.department,
                    f.created_at as sort_date
                FROM faculty f
                LEFT JOIN users u ON f.user_id = u.id
                
                UNION ALL
                
                SELECT
                    nf.id,
                    nf.employee_id as member_id,
                    nf.first_name,
                    nf.last_name,
                    nf.middle_initial,
                    nf.profile_image,
                    nf.created_at as member_since,
                    nf.status,
                    nf.created_at,
                    u.username,
                    u.email,
                    u.phone,
                    'non-faculty' as member_type,
                    nf.department,
                    nf.created_at as sort_date
                FROM non_faculty nf
                LEFT JOIN users u ON nf.user_id = u.id
            ) as combined
            ORDER BY last_name ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("ii", $this->items_per_page, $offset);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $members = [];
        if ($result) {
            $members = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            error_log("Database error: " . $this->conn->error);
        }
        $stmt->close();
        
        return [
            'members' => $members,
            'total_members' => $total_members,
            'total_pages' => $total_pages,
            'current_page' => $page,
            'items_per_page' => $this->items_per_page,
            'message' => $this->message
        ];
    }
    
    public function getMemberById($member_id) {
        $member = null;
        $member_id = intval($member_id);
        
        $query = "
            SELECT
                s.id,
                s.student_id as member_id,
                s.first_name,
                s.last_name,
                s.middle_initial,
                s.profile_image,
                s.join_date,
                s.status,
                u.username,
                u.email,
                u.phone
            FROM students s
            LEFT JOIN users u ON s.user_id = u.id
            WHERE s.id = ?
        ";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $member = $result->fetch_assoc();
        }
        $stmt->close();
        
        return $member;
    }
    
    public function getMessage() {
        return $this->message;
    }
}

if (isset($_POST['action']) && $_POST['action'] === 'get_member') {
    $memberController = new MemberController($conn);
    $member_id = intval($_POST['member_id'] ?? 0);
    $member = $memberController->getMemberById($member_id);
    header('Content-Type: application/json');
    if ($member) {
        echo json_encode(['success' => true, 'member' => $member]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Member not found']);
    }
    exit;
}

$memberController = new MemberController($conn);
$data = $memberController->handleRequest();
$members = $data['members'];
$total_members = $data['total_members'];
$total_pages = $data['total_pages'];
$current_page = $data['current_page'];
$items_per_page = $data['items_per_page'];
$message = $data['message'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management | Library System</title>
    <style>
        * {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-green: #2e7d32;
            --primary-green-dark: #1b5e20;
            --primary-green-light: #4caf50;
            --bg-green-light: #e8f5e9;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
        }
        
        body {
            background-color: var(--gray-50);
            min-height: 100vh;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
            border: none;
            border-radius: 0.5rem;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-green-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .message {
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
            font-size: 0.875rem;
        }
        .message.success {
            background: #e8f5e9;
            border-color: #2e7d32;
            color: #1b5e20;
        }
        .message.error {
            background: #ffebee;
            border-color: #c62828;
            color: #b71c1c;
        }
        
        .table-row:hover {
            background-color: var(--bg-green-light);
        }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--gray-200);
        }
        
        .default-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-green);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .image-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--primary-green-light);
        }
        
        .member-type-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .badge-student {
            background-color: #dbeafe;
            color: #1e40af;
        }
        
        .badge-faculty {
            background-color: #f3e8ff;
            color: #6b21a8;
        }
        
        .badge-non-faculty {
            background-color: #fef3c7;
            color: #92400e;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .print-section, .print-section * {
                visibility: visible;
            }
            .print-section {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none !important;
            }
        }
        
        #addMemberDropdown {
            transition: all 0.2s ease;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--gray-200);
        }
        
        #addMemberDropdown a {
            transition: all 0.2s ease;
            text-decoration: none;
            display: block;
        }
        
        #addMemberDropdown a:hover {
            border-left: 4px solid var(--primary-green);
            padding-left: 12px;
        }
        
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        td {
            padding: 1rem 1.5rem;
            font-size: 0.875rem;
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            transition: all 0.2s;
        }
        
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary-green);
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.1);
        }
        
        .container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
        }
        
        .grid {
            display: grid;
            gap: 1rem;
        }
        
        .grid-cols-1 {
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }
        
        .grid-cols-2 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
        
        @media (min-width: 768px) {
            .md\:grid-cols-2 {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        
        .hidden {
            display: none !important;
        }
        
        .flex {
            display: flex;
        }
        
        .items-center {
            align-items: center;
        }
        
        .justify-between {
            justify-content: space-between;
        }
        
        .space-x-3 > * + * {
            margin-left: 0.75rem;
        }
        
        .space-x-4 > * + * {
            margin-left: 1rem;
        }
        
        .mb-4 {
            margin-bottom: 1rem;
        }
        
        .mb-6 {
            margin-bottom: 1.5rem;
        }
        
        .mb-8 {
            margin-bottom: 2rem;
        }
        
        .mt-3 {
            margin-top: 0.75rem;
        }
        
        .mt-6 {
            margin-top: 1.5rem;
        }
        
        .p-6 {
            padding: 1.5rem;
        }
        
        .px-4 {
            padding-left: 1rem;
            padding-right: 1rem;
        }
        
        .py-3 {
            padding-top: 0.75rem;
            padding-bottom: 0.75rem;
        }
        
        .rounded-lg {
            border-radius: 0.5rem;
        }
        
        .rounded-xl {
            border-radius: 0.75rem;
        }
        
        .shadow-sm {
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        
        .shadow-lg {
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .text-sm {
            font-size: 0.875rem;
        }
        
        .text-xs {
            font-size: 0.75rem;
        }
        
        .text-lg {
            font-size: 1.125rem;
        }
        
        .text-xl {
            font-size: 1.25rem;
        }
        
        .text-2xl {
            font-size: 1.5rem;
        }
        
        .font-medium {
            font-weight: 500;
        }
        
        .font-bold {
            font-weight: 700;
        }
        
        .text-gray-600 {
            color: var(--gray-600);
        }
        
        .text-gray-700 {
            color: var(--gray-700);
        }
        
        .text-gray-800 {
            color: var(--gray-800);
        }
        
        .text-gray-900 {
            color: var(--gray-900);
        }
        
        .text-green-600 {
            color: var(--primary-green);
        }
        
        .text-blue-600 {
            color: #2563eb;
        }
        
        .text-red-600 {
            color: #dc2626;
        }
        
        .bg-white {
            background-color: white;
        }
        
        .bg-green-600 {
            background-color: var(--primary-green);
        }
        
        .relative {
            position: relative;
        }
        
        .absolute {
            position: absolute;
        }
        
        .fixed {
            position: fixed;
        }
        
        .inset-0 {
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
        }
        
        .inset-y-0 {
            top: 0;
            bottom: 0;
        }
        
        .top-4 {
            top: 1rem;
        }
        
        .right-4 {
            right: 1rem;
        }
        
        .left-0 {
            left: 0;
        }
        
        .right-0 {
            right: 0;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        .z-10 {
            z-index: 10;
        }
        
        .z-50 {
            z-index: 50;
        }
        
        .overflow-hidden {
            overflow: hidden;
        }
        
        .overflow-x-auto {
            overflow-x: auto;
        }
        
        .overflow-y-auto {
            overflow-y: auto;
        }
        
        .divide-y > * + * {
            border-top: 1px solid var(--gray-200);
        }
        
        .divide-gray-200 > * + * {
            border-top-color: var(--gray-200);
        }
        
        .whitespace-nowrap {
            white-space: nowrap;
        }
        
        .text-center {
            text-align: center;
        }
        
        .pointer-events-none {
            pointer-events: none;
        }
        
        .transition-all {
            transition: all 0.3s ease;
        }
        
        .duration-300 {
            transition-duration: 300ms;
        }
        
        .transform {
            transform: translate(var(--tw-translate-x), var(--tw-translate-y)) rotate(var(--tw-rotate)) skewX(var(--tw-skew-x)) skewY(var(--tw-skew-y)) scaleX(var(--tw-scale-x)) scaleY(var(--tw-scale-y));
        }
        
        .transition-opacity {
            transition: opacity 0.3s ease;
        }
        
        .transition-transform {
            transition: transform 0.3s ease;
        }
        
        .max-w-7xl {
            max-width: 80rem;
        }
        
        .mx-auto {
            margin-left: auto;
            margin-right: auto;
        }
        
        .w-full {
            width: 100%;
        }
        
        .w-32 {
            width: 8rem;
        }
        
        .h-32 {
            height: 8rem;
        }
        
        .gap-4 {
            gap: 1rem;
        }
        
        .gap-6 {
            gap: 1.5rem;
        }
        
        .border {
            border-width: 1px;
            border-style: solid;
        }
        
        .border-gray-300 {
            border-color: var(--gray-300);
        }
        
        .border-dashed {
            border-style: dashed;
        }
        
        .border-4 {
            border-width: 4px;
        }
        
        .focus\:outline-none:focus {
            outline: 2px solid transparent;
            outline-offset: 2px;
        }
        
        .focus\:ring-2:focus {
            box-shadow: 0 0 0 2px currentColor;
        }
        
        .focus\:ring-green-500:focus {
            --tw-ring-color: var(--primary-green-light);
        }
        
        .focus\:border-transparent:focus {
            border-color: transparent;
        }
        
        .hover\:bg-gray-50:hover {
            background-color: var(--gray-50);
        }
        
        .hover\:text-green-900:hover {
            color: var(--primary-green-dark);
        }
        
        .hover\:text-blue-900:hover {
            color: #1e40af;
        }
        
        .hover\:text-red-900:hover {
            color: #991b1b;
        }
        
        .hover\:bg-green-50:hover {
            background-color: var(--bg-green-light);
        }
        
        .hover\:text-green-700:hover {
            color: var(--primary-green);
        }
        
        .hover\:text-gray-600:hover {
            color: var(--gray-600);
        }
        
        .cursor-pointer {
            cursor: pointer;
        }
        
        .object-cover {
            object-fit: cover;
        }
        
        .uppercase {
            text-transform: uppercase;
        }
        
        .tracking-wider {
            letter-spacing: 0.05em;
        }
        
        .col-span-1 {
            grid-column: span 1 / span 1;
        }
        
        @media (min-width: 768px) {
            .md\:col-span-2 {
                grid-column: span 2 / span 2;
            }
        }
        
        #successToast {
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--gray-300);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--gray-400);
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-top: 2rem;
            gap: 0.5rem;
        }
        
        .pagination-item {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 40px;
            height: 40px;
            padding: 0 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-700);
            background-color: white;
            border: 1px solid var(--gray-300);
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .pagination-item:hover {
            background-color: var(--bg-green-light);
            border-color: var(--primary-green);
            color: var(--primary-green-dark);
        }
        
        .pagination-item.active {
            background-color: var(--primary-green);
            border-color: var(--primary-green);
            color: white;
        }
        
        .pagination-item.disabled {
            opacity: 0.5;
            pointer-events: none;
            background-color: var(--gray-100);
        }
        
        .showing-info {
            color: var(--gray-600);
            font-size: 0.875rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    
    <div class="min-h-screen flex flex-col">
        <div id="mainContent" class="main-content-expanded p-6 transition-all duration-300 overflow-y-auto flex-1">
            <div class="max-w-7xl mx-auto">
                <!-- Page Header -->
                <div class="mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">Member Management</h2>
                            <p class="text-gray-600">View and manage all registered members in the library system</p>
                        </div>
                        <div class="flex space-x-4">
                            <div class="relative no-print">
                                <button id="printBtn" class="border border-gray-300 rounded-lg px-6 py-3 hover:bg-gray-50 font-medium flex items-center">
                                    <i class="fas fa-print mr-2 text-gray-600"></i> Print
                                    <i class="fas fa-chevron-down ml-2 text-sm"></i>
                                </button>
                                <div id="printDropdown" class="absolute left-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 hidden">
                                    <div class="py-1">
                                        <a href="#" class="print-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 border-b border-gray-100" data-type="all">
                                            <i class="fas fa-list mr-2 text-green-600"></i>Print All Members
                                        </a>
                                        <a href="#" class="print-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 border-b border-gray-100" data-type="students">
                                            <i class="fas fa-user-graduate mr-2 text-green-600"></i>Print Students Only
                                        </a>
                                        <a href="#" class="print-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 border-b border-gray-100" data-type="faculty">
                                            <i class="fas fa-chalkboard-teacher mr-2 text-green-600"></i>Print Faculty Only
                                        </a>
                                        <a href="#" class="print-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700" data-type="non-faculty">
                                            <i class="fas fa-user-tie mr-2 text-green-600"></i>Print Non-Faculty Only
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Add New Member Button with Dropdown -->
                            <div class="relative no-print">
                                <button id="addMemberDropdownBtn" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center">
                                    <i class="fas fa-user-plus mr-2"></i> Add New Member
                                    <i class="fas fa-chevron-down ml-2 text-sm"></i>
                                </button>
                                
                                <!-- Dropdown Menu -->
                                <div id="addMemberDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10 hidden">
                                    <div class="py-1">
                                        <a href="#" class="add-member-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 border-b border-gray-100" data-type="student">
                                            <i class="fas fa-user-graduate mr-2 text-green-600"></i>Student
                                        </a>
                                        <a href="#" class="add-member-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700 border-b border-gray-100" data-type="non-faculty">
                                            <i class="fas fa-user-tie mr-2 text-green-600"></i>Non-Faculty
                                        </a>
                                        <a href="#" class="add-member-option block px-4 py-3 text-gray-700 hover:bg-green-50 hover:text-green-700" data-type="faculty">
                                            <i class="fas fa-chalkboard-teacher mr-2 text-green-600"></i>Faculty
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="message <?= strpos($message, 'Success') !== false ? 'success' : 'error' ?>">
                        <?= $message ?>
                    </div>
                <?php endif; ?>
                
                <!-- Search and Filter Bar -->
                <div class="bg-white rounded-xl shadow-sm p-6 mb-6 no-print">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="relative w-full md:w-96">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="searchMembers" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2e7d32] focus:border-transparent" placeholder="Search members by name, ID, username, email or phone...">
                        </div>
                        
                        <div class="flex space-x-4">
                            <select id="statusFilter" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#2e7d32] focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                            
                            <select id="memberTypeFilter" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#2e7d32] focus:border-transparent">
                                <option value="">All Types</option>
                                <option value="student">Student</option>
                                <option value="faculty">Faculty</option>
                                <option value="non-faculty">Non-Faculty</option>
                            </select>
                            
                            <button class="border border-gray-300 rounded-lg px-4 py-3 hover:bg-gray-50 flex items-center">
                                <i class="fas fa-filter mr-2 text-gray-600"></i> Filter
                            </button>
                            
                            <button class="border border-gray-300 rounded-lg px-4 py-3 hover:bg-gray-50 flex items-center">
                                <i class="fas fa-download mr-2 text-gray-600"></i> Export
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Showing Info -->
                <div class="showing-info no-print">
                    Showing <?= count($members) ?> of <?= $total_members ?> members (Page <?= $current_page ?> of <?= $total_pages ?>)
                </div>
                
                <!-- Printable Members Table -->
                <div id="printableTable" class="print-section">
                    <div class="hidden print:block mb-6">
                        <div class="text-center mb-4">
                            <h1 class="text-2xl font-bold text-gray-800">Library Management System</h1>
                            <h2 class="text-xl text-gray-700">Member Directory</h2>
                            <p class="text-gray-600">Generated on: <span id="printDate"></span></p>
                        </div>
                        <hr class="border-gray-300 mb-4">
                    </div>
                    
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead style="background-color: #2e7d32; color: white;">
                                    <tr>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Photo</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Member Type</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">ID Number</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Department</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Phone</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Member Since</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="membersTableBody">
                                    <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="11" class="px-6 py-4 text-center text-gray-500">
                                                No members registered yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($members as $member):
                                            $initials = strtoupper(substr($member['first_name'] ?? '', 0, 1) . substr($member['last_name'] ?? '', 0, 1));
                                            $member_type = $member['member_type'] ?? 'student';
                                            
                                            $badge_class = '';
                                            switch ($member_type) {
                                                case 'student': $badge_class = 'badge-student'; break;
                                                case 'faculty': $badge_class = 'badge-faculty'; break;
                                                case 'non-faculty': $badge_class = 'badge-non-faculty'; break;
                                            }
                                        ?>
                                            <tr class="table-row" data-member-type="<?= htmlspecialchars($member_type) ?>">
                                                <td class="px-6 py-4">
                                                    <?php if (!empty($member['profile_image']) && file_exists('../' . $member['profile_image'])): ?>
                                                        <img src="../<?= htmlspecialchars($member['profile_image']) ?>" alt="Profile" class="member-avatar">
                                                    <?php else: ?>
                                                        <div class="default-avatar"><?= $initials ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="member-type-badge <?= $badge_class ?>">
                                                        <?= ucfirst($member_type) ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($member['member_id'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars(($member['last_name'] ?? '') . ', ' . ($member['first_name'] ?? '')) ?>
                                                    <?= $member['middle_initial'] ? ' ' . htmlspecialchars($member['middle_initial']) . '.' : '' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($member['department'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($member['username'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($member['email'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($member['phone'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?= $member['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                        <?= ucfirst($member['status'] ?? 'active') ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $member['member_since'] ? date('M d, Y', strtotime($member['member_since'])) : '—' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                                    <div class="flex space-x-3">
                                                        <button class="text-green-600 hover:text-green-900 view-btn" title="View" data-id="<?= $member['id'] ?>">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="text-blue-600 hover:text-blue-900 edit-btn" title="Edit" data-id="<?= $member['id'] ?>" data-member-type="<?= htmlspecialchars($member['member_type']) ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="text-red-600 hover:text-red-900 delete-btn" title="Delete" data-id="<?= $member['id'] ?>" data-name="<?= htmlspecialchars($member['first_name'] . ' ' . $member['last_name']) ?>">
                                                            <i class="fas fa-trash-alt"></i>
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
                </div>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                <div class="pagination no-print">
                    <!-- Previous Page -->
                    <?php if ($current_page > 1): ?>
                        <a href="?page=<?= $current_page - 1 ?>" class="pagination-item">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-item disabled">
                            <i class="fas fa-chevron-left"></i>
                        </span>
                    <?php endif; ?>
                    
                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $start_page + 4);
                    
                    if ($start_page > 1) {
                        echo '<a href="?page=1" class="pagination-item">1</a>';
                        if ($start_page > 2) {
                            echo '<span class="pagination-item disabled">...</span>';
                        }
                    }
                    
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?= $i ?>" class="pagination-item <?= $i == $current_page ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="pagination-item disabled">...</span>
                        <?php endif; ?>
                        <a href="?page=<?= $total_pages ?>" class="pagination-item"><?= $total_pages ?></a>
                    <?php endif; ?>
                    
                    <!-- Next Page -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?= $current_page + 1 ?>" class="pagination-item">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php else: ?>
                        <span class="pagination-item disabled">
                            <i class="fas fa-chevron-right"></i>
                        </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Member Modal -->
    <div id="addMemberModal" class="fixed inset-0 z-50 hidden overflow-y-auto no-print">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity modal-backdrop" aria-hidden="true"></div>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-green-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-white" id="modalTitle">
                            <i class="fas fa-user-plus mr-2"></i> Add New Member
                        </h3>
                        <button id="closeModal" class="text-white hover:text-green-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                
                <div class="bg-white px-6 py-6">
                    <form id="addMemberForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_member" value="1">
                        <input type="hidden" name="member_type" id="memberType" value="student">
                        
                        <!-- Profile Picture Upload -->
                        <div class="mb-6 text-center">
                            <label class="block text-sm font-medium text-gray-700 mb-3">Profile Picture (Optional)</label>
                            <div class="mx-auto w-32 h-32 border-4 border-dashed border-gray-300 rounded-full flex items-center justify-center overflow-hidden">
                                <img id="imagePreview" src="" alt="Preview" class="hidden w-full h-full object-cover">
                                <div id="placeholder" class="text-gray-400">
                                    <i class="fas fa-camera text-4xl"></i>
                                    <p class="text-xs mt-2">Click to upload</p>
                                </div>
                            </div>
                            <input type="file" name="profile_image" id="profileImage" accept="image/*" class="mt-3 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" name="first_name" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter first name">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" name="last_name" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter last name">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Middle Initial</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-user text-gray-400"></i>
                                    </div>
                                    <input type="text" name="middle_initial" maxlength="1" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="M.I.">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2" id="idLabel">ID Number *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" name="id_number" id="idNumber" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="e.g., 2023-00123">
                                </div>
                                <p class="text-xs text-gray-500 mt-1" id="idHelp">Student ID number</p>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Department</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-building text-gray-400"></i>
                                    </div>
                                    <input type="text" name="department" id="department" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter department (optional)">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">For faculty/non-faculty only</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" name="email" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="member@example.com">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-phone text-gray-400"></i>
                                    </div>
                                    <input type="tel" name="phone" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="e.g., 09123456789">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Optional. Format: 09xxxxxxxxx or +63xxxxxxxxx</p>
                            </div>
                            
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Username *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-at text-gray-400"></i>
                                    </div>
                                    <input type="text" name="username" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Choose a username">
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Username must be unique and 3-20 characters long</p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Password *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="password" name="password" required class="pl-10 pr-10 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Enter password">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Confirm Password *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-lock text-gray-400"></i>
                                    </div>
                                    <input type="password" id="confirmPassword" name="confirm_password" required class="pl-10 pr-10 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="Confirm password">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <button type="button" class="text-gray-400 hover:text-gray-600 focus:outline-none toggle-password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">Passwords must match</p>
                            </div>
                        </div>
                        
                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="button" id="cancelModal" class="px-6 py-3 border border-gray-300 rounded-lg font-medium text-gray-700 hover:bg-gray-50">
                                Cancel
                            </button>
                            <button type="submit" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center">
                                <i class="fas fa-save mr-2"></i> Create Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto no-print">
        <div class="flex items-center justify-center min-h-screen">
            <div class="fixed inset-0 bg-black opacity-50" onclick="document.getElementById('deleteModal').classList.add('hidden')"></div>
            <div class="relative bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 text-center">Delete Member</h3>
                    <p class="mt-2 text-sm text-gray-500 text-center">Are you sure you want to delete <strong id="deleteMemberName"></strong>? This action cannot be undone.</p>
                    <div class="mt-6 flex space-x-3">
                        <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="flex-1 px-4 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50">
                            Cancel
                        </button>
                        <form id="deleteForm" method="POST" class="flex-1">
                            <input type="hidden" name="delete_member" value="1">
                            <input type="hidden" name="member_id" id="deleteMemberId" value="">
                            <button type="submit" class="w-full px-4 py-3 bg-red-600 rounded-lg text-white font-medium hover:bg-red-700">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Success Toast -->
    <div id="successToast" class="fixed top-4 right-4 z-50 hidden no-print">
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="h-10 w-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check text-white"></i>
                </div>
                <div>
                    <p class="font-medium">Success!</p>
                    <p class="text-sm">Member account created successfully.</p>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addMemberModal');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelModalBtn = document.getElementById('cancelModal');
            const form = document.getElementById('addMemberForm');
            const successToast = document.getElementById('successToast');
            const imageInput = document.getElementById('profileImage');
            const imagePreview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('placeholder');
            const memberTypeInput = document.getElementById('memberType');
            const modalTitle = document.getElementById('modalTitle');
            const idLabel = document.getElementById('idLabel');
            const idNumberInput = document.getElementById('idNumber');
            const idHelp = document.getElementById('idHelp');
            const departmentInput = document.getElementById('department');
            const departmentLabel = departmentInput.parentElement.parentElement.querySelector('label');
            
            // Handle Add Member Dropdown
            const addMemberDropdownBtn = document.getElementById('addMemberDropdownBtn');
            const addMemberDropdown = document.getElementById('addMemberDropdown');
            
            // Image preview
            imageInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        imagePreview.src = e.target.result;
                        imagePreview.classList.remove('hidden');
                        placeholder.style.display = 'none';
                    };
                    reader.readAsDataURL(file);
                } else {
                    imagePreview.classList.add('hidden');
                    placeholder.style.display = 'block';
                }
            });
            
            // Toggle password visibility
            document.querySelectorAll('.toggle-password').forEach(button => {
                button.addEventListener('click', function() {
                    const input = this.closest('.relative').querySelector('input');
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                });
            });
            
            // Toggle dropdown
            addMemberDropdownBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                addMemberDropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', (e) => {
                if (!addMemberDropdownBtn.contains(e.target) && !addMemberDropdown.contains(e.target)) {
                    addMemberDropdown.classList.add('hidden');
                }
            });
            
            // Handle dropdown option clicks
            document.querySelectorAll('.add-member-option').forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    const memberType = e.currentTarget.getAttribute('data-type');
                    
                    // Hide dropdown
                    addMemberDropdown.classList.add('hidden');
                    
                    // Set member type
                    memberTypeInput.value = memberType;
                    
                    // Update modal based on member type
                    updateModalForMemberType(memberType);
                    
                    // Show the modal
                    modal.classList.remove('hidden');
                    document.body.classList.add('overflow-hidden');
                });
            });
            
            function updateModalForMemberType(memberType) {
                let title = '';
                let icon = '';
                
                switch(memberType) {
                    case 'student':
                        title = 'Add New Student';
                        icon = 'user-graduate';
                        idLabel.textContent = 'Student ID *';
                        idNumberInput.placeholder = 'e.g., 2023-00123';
                        idHelp.textContent = 'Student ID number';
                        departmentInput.disabled = true;
                        departmentInput.value = '';
                        departmentLabel.classList.add('text-gray-400');
                        break;
                    case 'faculty':
                        title = 'Add Faculty Member';
                        icon = 'chalkboard-teacher';
                        idLabel.textContent = 'Faculty ID *';
                        idNumberInput.placeholder = 'e.g., FAC-2023-001';
                        idHelp.textContent = 'Faculty ID number';
                        departmentInput.disabled = false;
                        departmentLabel.classList.remove('text-gray-400');
                        break;
                    case 'non-faculty':
                        title = 'Add Non-Faculty Staff';
                        icon = 'user-tie';
                        idLabel.textContent = 'Employee ID *';
                        idNumberInput.placeholder = 'e.g., EMP-2023-001';
                        idHelp.textContent = 'Employee ID number';
                        departmentInput.disabled = false;
                        departmentLabel.classList.remove('text-gray-400');
                        break;
                }
                
                modalTitle.innerHTML = `<i class="fas fa-${icon} mr-2"></i>${title}`;
            }
            
            // Close modal
            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                form.reset();
                imagePreview.classList.add('hidden');
                placeholder.style.display = 'block';
                updateModalForMemberType('student');
                memberTypeInput.value = 'student';
                
                document.querySelector('#modalTitle').innerHTML = '<i class="fas fa-user-plus mr-2"></i> Add New Member';
                const submitBtn = document.querySelector('#addMemberForm button[type="submit"]');
                submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Create Account';
                
                const memberIdInput = form.querySelector('input[name="member_id"]');
                if (memberIdInput) {
                    memberIdInput.remove();
                }
                
                const usernameField = document.querySelector('input[name="username"]');
                if (usernameField) {
                    usernameField.disabled = false;
                    usernameField.classList.remove('opacity-50', 'cursor-not-allowed');
                }
            };
            
            closeModalBtn.addEventListener('click', closeModal);
            cancelModalBtn.addEventListener('click', closeModal);
            
            modal.addEventListener('click', (e) => {
                if (e.target === modal || e.target.classList.contains('modal-backdrop')) {
                    closeModal();
                }
            });
            
            // Form submission validation
            form.addEventListener('submit', (e) => {
                const password = document.getElementById('password').value;
                const confirmPassword = document.getElementById('confirmPassword').value;
                const email = document.querySelector('input[name="email"]').value;
                const phone = document.querySelector('input[name="phone"]').value;
                const idNumber = document.getElementById('idNumber').value;
                const memberIdInput = form.querySelector('input[name="member_id"]');
                const isEditing = memberIdInput && memberIdInput.value;
                
                document.querySelectorAll('.error-text').forEach(el => el.remove());
                
                let hasError = false;
                
                if (isEditing) {
                    if (password || confirmPassword) {
                        if (password !== confirmPassword) {
                            showError('confirmPassword', 'Passwords do not match!');
                            hasError = true;
                        }
                        if (password.length < 8) {
                            showError('password', 'Password must be at least 8 characters long!');
                            hasError = true;
                        }
                    }
                } else {
                    if (password !== confirmPassword) {
                        showError('confirmPassword', 'Passwords do not match!');
                        hasError = true;
                    }
                    if (password.length < 8) {
                        showError('password', 'Password must be at least 8 characters long!');
                        hasError = true;
                    }
                }
                
                if (!email.includes('@') || !email.includes('.')) {
                    showError('email', 'Please enter a valid email address!');
                    hasError = true;
                }
                
                if (phone && !/^[0-9\s\-\+\(\)]{7,15}$/.test(phone)) {
                    showError('phone', 'Please enter a valid phone number!');
                    hasError = true;
                }
                
                if (!idNumber.trim()) {
                    showError('idNumber', 'ID Number is required!');
                    hasError = true;
                }
                
                if (hasError) {
                    e.preventDefault();
                } else if (isEditing) {
                    e.target.querySelector('input[name="add_member"]').value = '0';
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_member';
                    updateInput.value = '1';
                    e.target.appendChild(updateInput);
                }
            });
            
            function showError(fieldId, message) {
                const field = document.getElementById(fieldId);
                const error = document.createElement('p');
                error.className = 'error-text text-red-500 text-xs mt-1';
                error.textContent = message;
                field.parentElement.appendChild(error);
            }
            
            <?php if (isset($message) && strpos($message, 'Success') !== false): ?>
                successToast.classList.remove('hidden');
                setTimeout(() => successToast.classList.add('hidden'), 4000);
            <?php endif; ?>
            
            // Print button with dropdown
            const printBtn = document.getElementById('printBtn');
            const printDropdown = document.getElementById('printDropdown');
            
            printBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                printDropdown.classList.toggle('hidden');
            });
            
            document.addEventListener('click', (e) => {
                if (!printBtn.contains(e.target) && !printDropdown.contains(e.target)) {
                    printDropdown.classList.add('hidden');
                }
            });
            
            document.querySelectorAll('.print-option').forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    const printType = e.currentTarget.getAttribute('data-type');
                    printDropdown.classList.add('hidden');
                    
                    const rows = document.querySelectorAll('#membersTableBody tr');
                    rows.forEach(row => {
                        row.style.display = 'table-row';
                    });
                    
                    if (printType !== 'all') {
                        rows.forEach(row => {
                            const typeCell = row.getAttribute('data-member-type');
                            if (typeCell !== printType) {
                                row.style.display = 'none';
                            }
                        });
                    }
                    
                    setTimeout(() => {
                        window.print();
                        setTimeout(() => {
                            rows.forEach(row => {
                                row.style.display = 'table-row';
                            });
                        }, 500);
                    }, 100);
                });
            });
            
            // Delete button handlers
            document.querySelectorAll('.delete-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const memberId = this.getAttribute('data-id');
                    const memberName = this.getAttribute('data-name');
                    document.getElementById('deleteMemberId').value = memberId;
                    document.getElementById('deleteMemberName').textContent = memberName;
                    document.getElementById('deleteModal').classList.remove('hidden');
                });
            });
            
            // Edit button handlers
            document.querySelectorAll('.edit-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const memberId = this.getAttribute('data-id');
                    const memberType = this.getAttribute('data-member-type');
                    
                    fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'action=get_member&member_id=' + memberId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const member = data.member;
                            
                            document.querySelector('input[name="first_name"]').value = member.first_name || '';
                            document.querySelector('input[name="last_name"]').value = member.last_name || '';
                            document.querySelector('input[name="middle_initial"]').value = member.middle_initial || '';
                            document.querySelector('input[name="id_number"]').value = member.member_id || '';
                            document.querySelector('input[name="email"]').value = member.email || '';
                            document.querySelector('input[name="phone"]').value = member.phone || '';
                            
                            const usernameField = document.querySelector('input[name="username"]');
                            usernameField.value = member.username || '';
                            usernameField.disabled = true;
                            usernameField.classList.add('opacity-50', 'cursor-not-allowed');
                            
                            document.getElementById('password').value = '';
                            document.getElementById('confirmPassword').value = '';
                            
                            const form = document.getElementById('addMemberForm');
                            let memberIdInput = form.querySelector('input[name="member_id"]');
                            if (!memberIdInput) {
                                memberIdInput = document.createElement('input');
                                memberIdInput.type = 'hidden';
                                memberIdInput.name = 'member_id';
                                form.appendChild(memberIdInput);
                            }
                            memberIdInput.value = memberId;
                            
                            const submitBtn = document.querySelector('#addMemberForm button[type="submit"]');
                            submitBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Update Member';
                            
                            document.getElementById('memberType').value = memberType;
                            updateModalForMemberType(memberType);
                            
                            modal.classList.remove('hidden');
                            document.body.classList.add('overflow-hidden');
                        } else {
                            alert('Error loading member data: ' + (data.message || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error fetching member data');
                    });
                });
            });
            
            // Set print date
            const now = new Date();
            document.getElementById('printDate').textContent = now.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            
            // Search functionality for members table
            document.getElementById('searchMembers').addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                const tableRows = document.querySelectorAll('#membersTableBody tr');
                const statusFilter = document.getElementById('statusFilter').value;
                const memberTypeFilter = document.getElementById('memberTypeFilter').value;
                
                tableRows.forEach(row => {
                    if (row.cells.length <= 1) return;
                    
                    let rowText = '';
                    for (let i = 1; i < row.cells.length - 1; i++) {
                        rowText += row.cells[i].textContent.toLowerCase() + ' ';
                    }
                    
                    const statusCell = row.cells[8];
                    const rowStatus = statusCell ? statusCell.textContent.toLowerCase().trim() : '';
                    const statusMatch = !statusFilter || rowStatus === statusFilter;
                    
                    const memberTypeCell = row.cells[1];
                    const rowMemberType = memberTypeCell ? memberTypeCell.textContent.toLowerCase().trim() : '';
                    const memberTypeMatch = !memberTypeFilter || rowMemberType === memberTypeFilter;
                    
                    const searchMatch = !searchTerm || rowText.includes(searchTerm);
                    
                    if (searchMatch && statusMatch && memberTypeMatch) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
            
            // Status and member type filter functionality
            document.getElementById('statusFilter').addEventListener('change', function() {
                document.getElementById('searchMembers').dispatchEvent(new Event('input'));
            });
            
            document.getElementById('memberTypeFilter').addEventListener('change', function() {
                document.getElementById('searchMembers').dispatchEvent(new Event('input'));
            });
            
            // Debounce function for better performance
            function debounce(func, wait) {
                let timeout;
                return function executedFunction(...args) {
                    const later = () => {
                        clearTimeout(timeout);
                        func(...args);
                    };
                    clearTimeout(timeout);
                    timeout = setTimeout(later, wait);
                };
            }
            
            document.getElementById('searchMembers').addEventListener('input', debounce(function(e) {}, 300));
            
            // Add clear search button functionality
            const searchInput = document.getElementById('searchMembers');
            const searchContainer = searchInput.parentElement;
            
            const clearBtn = document.createElement('button');
            clearBtn.type = 'button';
            clearBtn.className = 'absolute inset-y-0 right-0 pr-3 flex items-center hidden';
            clearBtn.innerHTML = '<i class="fas fa-times text-gray-400 hover:text-gray-600"></i>';
            clearBtn.addEventListener('click', function() {
                searchInput.value = '';
                searchInput.dispatchEvent(new Event('input'));
                this.classList.add('hidden');
            });
            
            searchContainer.appendChild(clearBtn);
            
            searchInput.addEventListener('input', function(e) {
                if (e.target.value.length > 0) {
                    clearBtn.classList.remove('hidden');
                } else {
                    clearBtn.classList.add('hidden');
                }
            });
        });
    </script>
</body>
</html>