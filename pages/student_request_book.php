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

        /* Layout: sidebar + main content */
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
            max-width: 620px;           /* <-- narrower card – feels better */
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

        .header-section p {
            color: #555;
            font-size: 1.05rem;
            max-width: 520px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .request-card {
            background: white;
            border-radius: 16px;
            padding: 36px 32px;
            box-shadow: 0 8px 28px rgba(46, 125, 50, 0.14);
            border-top: 5px solid #4caf50;
        }

        .request-card h2 {
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

        .info-box {
            background-color: #e8f5e9;
            border-radius: 10px;
            padding: 18px;
            margin-top: 28px;
            border-left: 5px solid #4caf50;
            font-size: 0.96rem;
        }

        /* SweetAlert styling */
        .swal-popup-green {
            border-top: 5px solid #4caf50 !important;
            border-radius: 16px !important;
        }
        .swal2-timer-progress-bar {
            background-color: #4caf50 !important;
        }

        /* ────────────────────────────────────────
           Responsive
        ──────────────────────────────────────── */
        @media (max-width: 992px) {
            .page-wrapper {
                flex-direction: column;
            }
            .sidebar {
                width: 100%;
            }
            .main-content {
                padding: 20px 16px;
            }
        }

        @media (max-width: 768px) {
            .header-section h1 { font-size: 2.1rem; }
            .request-card { padding: 28px 24px; }
            .container { max-width: 100%; }
        }

        @media (max-width: 480px) {
            .header-section h1 { font-size: 1.8rem; }
            .request-card { padding: 24px 20px; }
            .form-input, textarea.form-input { font-size: 15px; padding-left: 45px; }
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

                    <form id="bookRequestForm">
                        <div class="form-group">
                            <label for="requestDate"><i class="fas fa-calendar-alt"></i> Request Date</label>
                            <div class="input-with-icon">
                                <input type="date" id="requestDate" class="form-input" required>
                                <div class="input-icon"><i class="far fa-calendar"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="studentName"><i class="fas fa-user-graduate"></i> Student Name</label>
                            <div class="input-with-icon">
                                <input type="text" id="studentName" class="form-input" placeholder="Enter your full name" required>
                                <div class="input-icon"><i class="fas fa-user"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookTitle"><i class="fas fa-book"></i> Book Title</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookTitle" class="form-input" placeholder="Enter the book title you want to request" required>
                                <div class="input-icon"><i class="fas fa-book-open"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookAuthor"><i class="fas fa-user-edit"></i> Author (Optional)</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookAuthor" class="form-input" placeholder="Enter author's name">
                                <div class="input-icon"><i class="fas fa-pen-nib"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="bookISBN"><i class="fas fa-barcode"></i> ISBN (Optional)</label>
                            <div class="input-with-icon">
                                <input type="text" id="bookISBN" class="form-input" placeholder="Enter ISBN if known">
                                <div class="input-icon"><i class="fas fa-hashtag"></i></div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="additionalNotes"><i class="fas fa-sticky-note"></i> Additional Notes (Optional)</label>
                            <textarea id="additionalNotes" class="form-input" rows="4" placeholder="Any additional information about the book request..."></textarea>
                        </div>

                        <button type="submit" class="submit-btn">
                            <i class="fas fa-paper-plane"></i> Submit Book Request
                        </button>
                    </form>
                </div>
            </div>

            <?php include '../components/footer.php'; ?>
        </main>

    </div> <!-- end page-wrapper -->

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
        });

        document.getElementById('bookRequestForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const studentName = document.getElementById('studentName').value.trim();
            const bookTitle   = document.getElementById('bookTitle').value.trim();
            const requestDate = document.getElementById('requestDate').value;

            if (!studentName || !bookTitle) {
                Swal.fire({
                    title: 'Required Fields Missing',
                    text: 'Please enter your name and the book title.',
                    icon: 'warning',
                    confirmButtonColor: '#4caf50'
                });
                return;
            }

            const dateObj = new Date(requestDate);
            const formattedDate = dateObj.toLocaleDateString('en-US', {
                year: 'numeric', month: 'long', day: 'numeric'
            });

            Swal.fire({
                title: 'Request Submitted!',
                html: `
                    <div style="font-size: 3.5rem; color: #4caf50; margin: 20px 0;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <p style="font-size: 1.2rem; color: #2e7d32; margin-bottom: 12px;">
                        Thank you, ${studentName.split(' ')[0]}!
                    </p>
                    <p style="color: #555; margin-bottom: 8px;">
                        <strong>"${bookTitle}"</strong> has been requested
                    </p>
                    <p style="color: #777; font-size: 0.95rem;">
                        Date: ${formattedDate}
                    </p>
                `,
                icon: 'success',
                iconColor: '#4caf50',
                showConfirmButton: false,
                showCloseButton: false,
                allowOutsideClick: false,
                allowEscapeKey: false,
                timer: 2800,
                timerProgressBar: true,
                background: '#f9fdf9',
                customClass: { popup: 'swal-popup-green' }
            });

            setTimeout(() => {
                document.getElementById('bookRequestForm').reset();
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('requestDate').value = today;
                document.getElementById('studentName').focus();
            }, 400);
        });
    </script>
</body>
</html>