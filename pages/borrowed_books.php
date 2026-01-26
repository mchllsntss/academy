<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Borrowing System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f7fa;
            color: #333;
            line-height: 1.6;
            min-height: 100vh;
        }
        
        .app-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 250px; /* Adjust based on sidebar width */
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        /* Content Container */
        .content-container {
            max-width: 1400px;
            margin: 0 auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        /* Content Header */
        .content-header {
            background: linear-gradient(135deg, #2e7d32, #2e7d32);
            color: white;
            padding: 25px 30px;
        }
        
        .content-header h1 {
            font-size: 2.2rem;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .content-header p {
            opacity: 0.9;
            font-size: 1.1rem;
            margin-left: 45px;
        }
        
        .app-content {
            display: flex;
            flex-wrap: wrap;
            padding: 30px;
            gap: 30px;
        }
        
        .form-section {
            flex: 1;
            min-width: 300px;
            background-color: #f9f9f9;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border-top: 4px solid #2e7d32;
        }
        
        .list-section {
            flex: 2;
            min-width: 500px;
        }
        
        .list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .list-title {
            color: #2e7d32;
            padding-bottom: 10px;
            border-bottom: 2px solid #eaeaea;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .search-container {
            position: relative;
            width: 100%;
            max-width: 300px;
        }
        
        .search-container i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #2e7d32;
            z-index: 1;
        }
        
        #searchInput {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border-radius: 6px;
            border: 1px solid #ddd;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        #searchInput:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        .form-title i, .list-title i {
            color: #2e7d32;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
        }
        
        input[type="text"],
        input[type="date"] {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus,
        input[type="date"]:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        .save-btn {
            background: linear-gradient(to right, #2e7d32, #2e7d32);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            margin-top: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .save-btn:hover {
            background: linear-gradient(to right, #43a047, #1b5e20);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .save-btn:active {
            transform: translateY(0);
        }
        
        .save-btn:disabled {
            background: #cccccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #eaeaea;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
        }
        
        thead {
            background-color: #2e7d32;
            color: white;
        }
        
        th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        tbody tr {
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        td {
            padding: 15px;
            color: #555;
        }
        
        .actions {
            display: flex;
            gap: 10px;
        }
        
        .edit-btn, .delete-btn {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .edit-btn {
            background-color: #ff9800;
            color: white;
        }
        
        .edit-btn:hover {
            background-color: #f57c00;
        }
        
        .delete-btn {
            background-color: #f44336;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        
        .status-overdue {
            color: #f44336;
            font-weight: 600;
            background-color: rgba(244, 67, 54, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .status-ontime {
            color: #2e7d32;
            font-weight: 600;
            background-color: rgba(46, 125, 50, 0.1);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-list {
            text-align: center;
            padding: 40px 20px;
            color: #777;
            font-style: italic;
            background-color: #fafafa;
        }
        
        .empty-list i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
            color: #2e7d32;
        }
        
        .student-id {
            font-family: monospace;
            background-color: #f5f5f5;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
            border: 1px solid #e0e0e0;
        }
        
        .date-inputs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        @media (max-width: 900px) {
            .app-content {
                flex-direction: column;
            }
            
            .form-section, .list-section {
                min-width: 100%;
            }
            
            .date-inputs {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .list-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-container {
                max-width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .content-header h1 {
                font-size: 1.8rem;
            }
            
            .app-content {
                padding: 15px;
            }
            
            th, td {
                padding: 12px 8px;
                font-size: 0.9rem;
            }
            
            .actions {
                flex-direction: column;
                gap: 5px;
            }
            
            .edit-btn, .delete-btn {
                font-size: 0.8rem;
                padding: 6px 10px;
            }
        }
        
        /* Placeholder for sidebar */
        .sidebar-placeholder {
            width: 250px;
            background: linear-gradient(180deg, #1b5e20, #2e7d32);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        
        .sidebar-placeholder .sidebar-content {
            padding: 20px;
        }
        
        .sidebar-placeholder h3 {
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-placeholder ul {
            list-style: none;
        }
        
        .sidebar-placeholder li {
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .sidebar-placeholder li:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-placeholder li.active {
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .sidebar-placeholder i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        /* Placeholder for header */
        .header-placeholder {
            background: linear-gradient(135deg, #1b5e20, #2e7d32);
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-placeholder .logo {
            font-size: 1.5rem;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-placeholder .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .header-placeholder .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        /* Alert messages */
        .alert {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #2e7d32;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #f44336;
        }
        
        .alert i {
            font-size: 1.2rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar-placeholder {
                width: 0;
                overflow: hidden;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .header-placeholder {
                padding: 15px;
            }
            
            .header-placeholder .logo span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- PHP includes will go here -->
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    
    <div class="app-container">
        <div class="main-content">
            <div class="content-container">
                <div class="content-header">
                    <h1><i class="fas fa-book-reader"></i> Book Borrowing System</h1>
                    <p>Manage student book borrowing with ease</p>
                </div>
                
                <!-- Success/Error Messages Area -->
                <div id="messageArea" style="display: none;"></div>
                
                <div class="app-content">
                    <section class="form-section">
                        <h2 class="form-title"><i class="fas fa-plus-circle"></i> Borrow a Book</h2>
                        <form id="borrowForm">
                            <div class="form-group">
                                <label for="studentName"><i class="fas fa-user"></i> Student Name *</label>
                                <input type="text" id="studentName" placeholder="Enter student's full name" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="studentId"><i class="fas fa-id-card"></i> Student ID Number *</label>
                                <input type="text" id="studentId" placeholder="Enter student ID" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="bookTitle"><i class="fas fa-book"></i> Book Title *</label>
                                <input type="text" id="bookTitle" placeholder="Enter book title" required>
                            </div>
                            
                            <div class="date-inputs">
                                <div class="form-group">
                                    <label for="borrowedDate"><i class="fas fa-calendar-plus"></i> Borrowed Date *</label>
                                    <input type="date" id="borrowedDate" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="dueDate"><i class="fas fa-calendar-check"></i> Due Date *</label>
                                    <input type="date" id="dueDate" required>
                                </div>
                            </div>
                            
                            <button type="submit" class="save-btn" id="saveBtn">
                                <i class="fas fa-save"></i> Save Borrowing Record
                            </button>
                        </form>
                    </section>
                    
                    <section class="list-section">
                        <div class="list-header">
                            <h2 class="list-title"><i class="fas fa-list"></i> Borrowing Records</h2>
                            <div class="search-container">
                                <i class="fas fa-search"></i>
                                <input type="text" id="searchInput" placeholder="Search records...">
                            </div>
                        </div>
                        
                        <div class="table-container">
                            <table id="recordsTable">
                                <thead>
                                    <tr>
                                        <th>Student Name</th>
                                        <th>Student ID</th>
                                        <th>Book Title</th>
                                        <th>Borrowed Date</th>
                                        <th>Due Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="recordsBody">
                                    <!-- Records will be inserted here by JavaScript -->
                                </tbody>
                            </table>
                            <div id="emptyMessage" class="empty-list">
                                <i class="fas fa-book-open"></i>
                                <p>No borrowing records found. Add your first record using the form.</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Initialize date inputs with today's date and 14 days from now
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date().toISOString().split('T')[0];
            const dueDate = new Date();
            dueDate.setDate(dueDate.getDate() + 14);
            const dueDateFormatted = dueDate.toISOString().split('T')[0];
            
            document.getElementById('borrowedDate').value = today;
            document.getElementById('dueDate').value = dueDateFormatted;
            
            // Load existing records from localStorage
            loadRecords();
            
            // Set up form submission
            document.getElementById('borrowForm').addEventListener('submit', saveRecord);
            
            // Set up search functionality
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function() {
                searchRecords(this.value);
            });
            
            // Hide demo header and sidebar if PHP includes are present
            checkForPHPIncludes();
        });
        
        // Variable to store all records for search functionality
        let allRecords = [];
        
        // Check if PHP includes are present (for demo purposes)
        function checkForPHPIncludes() {
            // This is a demo function - in real implementation, PHP would handle this
            // For demo, we'll show both the PHP includes and our demo placeholders
        }
        
        // Show message function
        function showMessage(message, type = 'success') {
            const messageArea = document.getElementById('messageArea');
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            const alertClass = type === 'success' ? 'alert-success' : 'alert-error';
            
            messageArea.innerHTML = `
                <div class="alert ${alertClass}">
                    <i class="fas ${icon}"></i>
                    <span>${message}</span>
                </div>
            `;
            
            messageArea.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                messageArea.style.display = 'none';
            }, 5000);
        }
        
        // Save record function
        function saveRecord(e) {
            e.preventDefault();
            
            // Get form values
            const studentName = document.getElementById('studentName').value.trim();
            const studentId = document.getElementById('studentId').value.trim();
            const bookTitle = document.getElementById('bookTitle').value.trim();
            const borrowedDate = document.getElementById('borrowedDate').value;
            const dueDate = document.getElementById('dueDate').value;
            
            // Validate inputs
            if (!studentName || !studentId || !bookTitle || !borrowedDate || !dueDate) {
                showMessage('Please fill in all required fields.', 'error');
                return;
            }
            
            // Validate dates
            if (new Date(dueDate) <= new Date(borrowedDate)) {
                showMessage('Due date must be after borrowed date.', 'error');
                return;
            }
            
            // Create record object
            const record = {
                id: Date.now(), // Unique ID based on timestamp
                studentName,
                studentId,
                bookTitle,
                borrowedDate,
                dueDate
            };
            
            // Save to localStorage
            let records = JSON.parse(localStorage.getItem('libraryRecords')) || [];
            records.push(record);
            localStorage.setItem('libraryRecords', JSON.stringify(records));
            
            // Clear form
            document.getElementById('borrowForm').reset();
            
            // Reset dates to defaults
            const today = new Date().toISOString().split('T')[0];
            const dueDateDefault = new Date();
            dueDateDefault.setDate(dueDateDefault.getDate() + 14);
            const dueDateFormatted = dueDateDefault.toISOString().split('T')[0];
            
            document.getElementById('borrowedDate').value = today;
            document.getElementById('dueDate').value = dueDateFormatted;
            
            // Reload records
            loadRecords();
            
            // Show success message
            showMessage('Record saved successfully!', 'success');
        }
        
        // Load records from localStorage
        function loadRecords() {
            const records = JSON.parse(localStorage.getItem('libraryRecords')) || [];
            allRecords = records; // Store all records for search functionality
            displayRecords(records);
        }
        
        // Display records in the table
        function displayRecords(records) {
            const recordsBody = document.getElementById('recordsBody');
            const emptyMessage = document.getElementById('emptyMessage');
            
            // Clear table body
            recordsBody.innerHTML = '';
            
            if (records.length === 0) {
                emptyMessage.style.display = 'block';
                return;
            }
            
            emptyMessage.style.display = 'none';
            
            // Add each record to the table
            records.forEach(record => {
                const row = document.createElement('tr');
                row.dataset.id = record.id;
                
                // Calculate status
                const today = new Date();
                const dueDate = new Date(record.dueDate);
                const isOverdue = dueDate < today;
                const statusText = isOverdue ? 'Overdue' : 'On Time';
                const statusClass = isOverdue ? 'status-overdue' : 'status-ontime';
                const statusIcon = isOverdue ? 'fa-exclamation-circle' : 'fa-check-circle';
                
                // Format dates for display
                const borrowedDateFormatted = formatDate(record.borrowedDate);
                const dueDateFormatted = formatDate(record.dueDate);
                
                row.innerHTML = `
                    <td>${record.studentName}</td>
                    <td><span class="student-id">${record.studentId}</span></td>
                    <td>${record.bookTitle}</td>
                    <td>${borrowedDateFormatted}</td>
                    <td>${dueDateFormatted}</td>
                    <td><span class="${statusClass}"><i class="fas ${statusIcon}"></i> ${statusText}</span></td>
                    <td>
                        <div class="actions">
                            <button class="edit-btn" onclick="editRecord(${record.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="delete-btn" onclick="deleteRecord(${record.id})">
                                <i class="fas fa-trash-alt"></i> Delete
                            </button>
                        </div>
                    </td>
                `;
                
                recordsBody.appendChild(row);
            });
        }
        
        // Search records function
        function searchRecords(searchTerm) {
            if (!searchTerm.trim()) {
                // If search is empty, show all records
                displayRecords(allRecords);
                return;
            }
            
            const searchTermLower = searchTerm.toLowerCase();
            
            const filteredRecords = allRecords.filter(record => 
                record.studentName.toLowerCase().includes(searchTermLower) ||
                record.studentId.toLowerCase().includes(searchTermLower) ||
                record.bookTitle.toLowerCase().includes(searchTermLower) ||
                formatDate(record.borrowedDate).toLowerCase().includes(searchTermLower) ||
                formatDate(record.dueDate).toLowerCase().includes(searchTermLower)
            );
            
            displayRecords(filteredRecords);
        }
        
        // Edit record function
        function editRecord(id) {
            const records = JSON.parse(localStorage.getItem('libraryRecords')) || [];
            const record = records.find(r => r.id === id);
            
            if (!record) return;
            
            // Populate form with record data
            document.getElementById('studentName').value = record.studentName;
            document.getElementById('studentId').value = record.studentId;
            document.getElementById('bookTitle').value = record.bookTitle;
            document.getElementById('borrowedDate').value = record.borrowedDate;
            document.getElementById('dueDate').value = record.dueDate;
            
            // Change button text
            const saveBtn = document.getElementById('saveBtn');
            saveBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Update Record';
            
            // Remove existing event listener and add update listener
            const form = document.getElementById('borrowForm');
            form.removeEventListener('submit', saveRecord);
            
            form.addEventListener('submit', function updateRecord(e) {
                e.preventDefault();
                
                // Get updated values
                const updatedRecord = {
                    id: record.id,
                    studentName: document.getElementById('studentName').value.trim(),
                    studentId: document.getElementById('studentId').value.trim(),
                    bookTitle: document.getElementById('bookTitle').value.trim(),
                    borrowedDate: document.getElementById('borrowedDate').value,
                    dueDate: document.getElementById('dueDate').value
                };
                
                // Update in localStorage
                const recordIndex = records.findIndex(r => r.id === id);
                if (recordIndex !== -1) {
                    records[recordIndex] = updatedRecord;
                    localStorage.setItem('libraryRecords', JSON.stringify(records));
                }
                
                // Reset form and button
                form.reset();
                
                // Reset dates to defaults
                const today = new Date().toISOString().split('T')[0];
                const dueDateDefault = new Date();
                dueDateDefault.setDate(dueDateDefault.getDate() + 14);
                const dueDateFormatted = dueDateDefault.toISOString().split('T')[0];
                
                document.getElementById('borrowedDate').value = today;
                document.getElementById('dueDate').value = dueDateFormatted;
                
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Borrowing Record';
                
                // Remove update listener and re-add save listener
                form.removeEventListener('submit', updateRecord);
                form.addEventListener('submit', saveRecord);
                
                // Reload records
                loadRecords();
                
                showMessage('Record updated successfully!', 'success');
            });
        }
        
        // Delete record function
        function deleteRecord(id) {
            if (!confirm('Are you sure you want to delete this borrowing record?')) {
                return;
            }
            
            let records = JSON.parse(localStorage.getItem('libraryRecords')) || [];
            records = records.filter(record => record.id !== id);
            localStorage.setItem('libraryRecords', JSON.stringify(records));
            
            // Reload records
            loadRecords();
            
            showMessage('Record deleted successfully!', 'success');
        }
        
        // Helper function to format dates
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        }
    </script>

    <?php include '../components/footer.php'; ?>

</body>
</html>