<?php
// students.php - Student Management with Email, Phone & Profile Picture
require_once '../connection/dbconnection.php';

// Create uploads folder if not exists
$upload_dir = '../uploads/students/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$message = '';

// Handle form submission (Add New Student)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $first_name     = trim($_POST['first_name'] ?? '');
    $last_name      = trim($_POST['last_name'] ?? '');
    $mi             = trim($_POST['middle_initial'] ?? '');
    $student_id     = trim($_POST['student_id'] ?? '');
    $username       = trim($_POST['username'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');          // NEW: Phone
    $password       = $_POST['password'] ?? '';
    $confirm_pass   = $_POST['confirm_password'] ?? '';
    $profile_image  = null;

    $errors = [];
    if (empty($first_name))      $errors[] = "First Name is required.";
    if (empty($last_name))       $errors[] = "Last Name is required.";
    if (empty($student_id))      $errors[] = "Student ID is required.";
    if (empty($username))        $errors[] = "Username is required.";
    if (strlen($username) < 3 || strlen($username) > 20) $errors[] = "Username must be 3-20 characters.";
    if (empty($email))           $errors[] = "Email is required.";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email format.";
    if (!empty($phone) && !preg_match('/^[0-9\s\-\+\(\)]{7,15}$/', $phone)) {
        $errors[] = "Invalid phone number format.";
    }
    if (empty($password))        $errors[] = "Password is required.";
    if ($password !== $confirm_pass) $errors[] = "Passwords do not match.";
    if (strlen($password) < 8)   $errors[] = "Password must be at least 8 characters.";

    // Check if email already exists
    if (empty($errors)) {
        $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check_email->bind_param("s", $email);
        $check_email->execute();
        if ($check_email->get_result()->num_rows > 0) {
            $errors[] = "Email address is already in use.";
        }
        $check_email->close();
    }

    // Check if username already exists
    if (empty($errors)) {
        $check_user = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_user->bind_param("s", $username);
        $check_user->execute();
        if ($check_user->get_result()->num_rows > 0) {
            $errors[] = "Username is already taken.";
        }
        $check_user->close();
    }

    // Handle profile picture upload
    if (!empty($_FILES['profile_image']['name'])) {
        $file_name = $_FILES['profile_image']['name'];
        $file_tmp  = $_FILES['profile_image']['tmp_name'];
        $file_size = $_FILES['profile_image']['size'];
        $file_ext  = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed = ['jpg', 'jpeg', 'png'];
        if (!in_array($file_ext, $allowed)) {
            $errors[] = "Only JPG, JPEG & PNG files are allowed.";
        }
        if ($file_size > 2097152) { // 2MB
            $errors[] = "Image size must not exceed 2MB.";
        }

        if (empty($errors)) {
            $new_file_name = 'stud_' . uniqid() . '.' . $file_ext;
            $destination = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $destination)) {
                $profile_image = 'uploads/students/' . $new_file_name;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    }

    if (empty($errors)) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // 1. Insert into users table (now with email & phone)
        $stmt_user = $conn->prepare("
            INSERT INTO users 
            (username, email, first_name, last_name, phone, password_hash, profile_image)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt_user->bind_param("sssssss", $username, $email, $first_name, $last_name, $phone, $password_hash, $profile_image);

        if ($stmt_user->execute()) {
            $user_id = $conn->insert_id;

            // 2. Insert into students table
            $stmt_student = $conn->prepare("
                INSERT INTO students 
                (user_id, student_id, first_name, last_name, middle_initial, profile_image, join_date, status)
                VALUES (?, ?, ?, ?, ?, ?, CURDATE(), 'active')
            ");
            $stmt_student->bind_param("isssss", $user_id, $student_id, $first_name, $last_name, $mi, $profile_image);

            if ($stmt_student->execute()) {
                $message = "Success! New student account created.";
            } else {
                $message = "Error creating student record: " . $stmt_student->error;
            }
            $stmt_student->close();
        } else {
            $message = "Error creating user account: " . $stmt_user->error;
        }
        $stmt_user->close();
    } else {
        $message = implode("<br>", $errors);
    }
}

// Fetch all students (now includes email & phone)
$students = [];
$result = $conn->query("
    SELECT 
        s.id,
        s.student_id,
        s.first_name,
        s.last_name,
        s.middle_initial,
        s.profile_image,
        s.join_date,
        s.status,
        s.created_at,
        u.username,
        u.email,
        u.phone
    FROM students s
    LEFT JOIN users u ON s.user_id = u.id
    ORDER BY s.last_name ASC
");
if ($result) {
    $students = $result->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Students Management | Library System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { font-family: 'Inter', sans-serif; }
        
        :root {
            --primary-green: #2e7d32;
            --primary-green-dark: #1b5e20;
            --primary-green-light: #4caf50;
            --bg-green-light: #e8f5e9;
        }
        
        .btn-primary {
            background-color: var(--primary-green);
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover { background-color: var(--primary-green-dark); }
        
        .message {
            padding: 12px 20px;
            margin: 15px 0;
            border-radius: 6px;
            border-left: 5px solid;
        }
        .message.success { background:#e8f5e9; border-color:#2e7d32; }
        .message.error   { background:#ffebee; border-color:#c62828; }
        
        .table-row:hover { background-color: #e8f5e9; }
        
        .status-active {
            background-color: #d1fae5;
            color: #065f46;
        }
        
        .status-inactive {
            background-color: #fee2e2;
            color: #991b1b;
        }
        
        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            background: #e0e0e0;
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
            font-size: 16px;
        }
        
        .image-preview {
            margin-top: 10px;
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #4caf50;
        }
        
        @media print {
            body * { visibility: hidden; }
            .print-section, .print-section * { visibility: visible; }
            .print-section { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
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
                            <h2 class="text-2xl font-bold text-gray-800">Student Management</h2>
                            <p class="text-gray-600">View and manage all registered students in the library system</p>
                        </div>
                        <div class="flex space-x-4">
                            <button id="printBtn" class="border border-gray-300 rounded-lg px-6 py-3 hover:bg-gray-50 font-medium flex items-center no-print">
                                <i class="fas fa-print mr-2 text-gray-600"></i> Print List
                            </button>
                            <button id="addStudentBtn" class="btn-primary px-6 py-3 rounded-lg font-medium flex items-center no-print">
                                <i class="fas fa-user-plus mr-2"></i> Add New Student
                            </button>
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
                            <input type="text" id="searchStudents" class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-[#2e7d32] focus:border-transparent" placeholder="Search students by name, ID, username, email or phone...">
                        </div>
                        
                        <div class="flex space-x-4">
                            <select id="statusFilter" class="border border-gray-300 rounded-lg px-4 py-3 focus:outline-none focus:ring-2 focus:ring-[#2e7d32] focus:border-transparent">
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
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

                <!-- Printable Students Table -->
                <div id="printableTable" class="print-section">
                    <div class="hidden print:block mb-6">
                        <div class="text-center mb-4">
                            <h1 class="text-2xl font-bold text-gray-800">Library Management System</h1>
                            <h2 class="text-xl text-gray-700">Student Directory</h2>
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
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Student ID</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Name</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Phone</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Books Borrowed</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Join Date</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider">Date Created</th>
                                        <th class="px-6 py-4 text-left text-xs font-medium text-white uppercase tracking-wider no-print">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200" id="studentsTableBody">
                                    <?php if (empty($students)): ?>
                                        <tr>
                                            <td colspan="11" class="px-6 py-4 text-center text-gray-500">
                                                No students registered yet.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($students as $student):
                                            $initials = strtoupper(substr($student['first_name'] ?? '', 0, 1) . substr($student['last_name'] ?? '', 0, 1));
                                        ?>
                                            <tr class="table-row">
                                                <td class="px-6 py-4">
                                                    <?php if (!empty($student['profile_image']) && file_exists('../' . $student['profile_image'])): ?>
                                                        <img src="../<?= htmlspecialchars($student['profile_image']) ?>" alt="Profile" class="student-avatar">
                                                    <?php else: ?>
                                                        <div class="default-avatar"><?= $initials ?></div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                    <?= htmlspecialchars($student['student_id'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars(($student['last_name'] ?? '') . ', ' . ($student['first_name'] ?? '')) ?>
                                                    <?= $student['middle_initial'] ? ' ' . htmlspecialchars($student['middle_initial']) . '.' : '' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($student['username'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($student['email'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= htmlspecialchars($student['phone'] ?? '—') ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">0</td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?= $student['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                                        <?= ucfirst($student['status'] ?? 'active') ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= $student['join_date'] ? date('M d, Y', strtotime($student['join_date'])) : '—' ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                    <?= date('M d, Y', strtotime($student['created_at'])) ?>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium no-print">
                                                    <div class="flex space-x-3">
                                                        <button class="text-green-600 hover:text-green-900" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                        <button class="text-blue-600 hover:text-blue-900" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="text-red-600 hover:text-red-900" title="Delete">
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
            </div>
        </div>
    </div>

    <!-- Add Student Modal -->
    <div id="addStudentModal" class="fixed inset-0 z-50 hidden overflow-y-auto no-print">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity modal-backdrop" aria-hidden="true"></div>
           
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-green-600 px-6 py-4">
                    <div class="flex justify-between items-center">
                        <h3 class="text-lg font-medium text-white">
                            <i class="fas fa-user-plus mr-2"></i> Add New Student
                        </h3>
                        <button id="closeModal" class="text-white hover:text-green-200">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
               
                <div class="bg-white px-6 py-6">
                    <form id="addStudentForm" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="add_student" value="1">

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
                                <label class="block text-sm font-medium text-gray-700 mb-2">Student ID *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-id-card text-gray-400"></i>
                                    </div>
                                    <input type="text" name="student_id" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="e.g., 2023-00123">
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <i class="fas fa-envelope text-gray-400"></i>
                                    </div>
                                    <input type="email" name="email" required class="pl-10 pr-4 py-3 w-full border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent" placeholder="student@example.com">
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

    <!-- Success Toast -->
    <div id="successToast" class="fixed top-4 right-4 z-50 hidden no-print">
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg max-w-sm">
            <div class="flex items-center">
                <div class="h-10 w-10 bg-green-500 rounded-full flex items-center justify-center mr-3">
                    <i class="fas fa-check text-white"></i>
                </div>
                <div>
                    <p class="font-medium">Success!</p>
                    <p class="text-sm">Student account created successfully.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('addStudentModal');
            const addStudentBtn = document.getElementById('addStudentBtn');
            const closeModalBtn = document.getElementById('closeModal');
            const cancelModalBtn = document.getElementById('cancelModal');
            const form = document.getElementById('addStudentForm');
            const successToast = document.getElementById('successToast');
            const imageInput = document.getElementById('profileImage');
            const imagePreview = document.getElementById('imagePreview');
            const placeholder = document.getElementById('placeholder');

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

            // Open modal
            addStudentBtn.addEventListener('click', () => {
                modal.classList.remove('hidden');
                document.body.classList.add('overflow-hidden');
            });

            // Close modal
            const closeModal = () => {
                modal.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
                form.reset();
                imagePreview.classList.add('hidden');
                placeholder.style.display = 'block';
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

                if (password !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match!');
                    return;
                }
                if (password.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long!');
                    return;
                }
                if (!email.includes('@') || !email.includes('.')) {
                    e.preventDefault();
                    alert('Please enter a valid email address!');
                    return;
                }
                if (phone && !/^[0-9\s\-\+\(\)]{7,15}$/.test(phone)) {
                    e.preventDefault();
                    alert('Please enter a valid phone number!');
                    return;
                }
            });

            // Show success toast
            <?php if (isset($message) && strpos($message, 'Success') !== false): ?>
                successToast.classList.remove('hidden');
                setTimeout(() => successToast.classList.add('hidden'), 4000);
            <?php endif; ?>

            // Print button
            document.getElementById('printBtn').addEventListener('click', () => window.print());
        });
    </script>
</body>
</html>