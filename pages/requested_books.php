<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library - Requested Books</title>
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
            display: flex;
            min-height: 100vh;
        }

        .main-container {
            display: flex;
            flex: 1;
        }

        .content-wrapper {
            flex: 1;
            margin-left: 250px; /* Adjust based on sidebar width */
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        header {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
            padding: 20px 0;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 30px;
        }

        h1 {
            font-size: 28px;
        }

        .stats {
            display: flex;
            gap: 20px;
        }

        .stat-box {
            background-color: rgba(46, 125, 50, 0.1);
            padding: 10px 20px;
            border-radius: 6px;
            text-align: center;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-value {
            font-size: 22px;
            font-weight: bold;
            color: #4caf50;
        }

        .stat-label {
            font-size: 14px;
            color: #e8f5e9;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
            background-color: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .search-box {
            position: relative;
            flex-grow: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.2);
            outline: none;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        .filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        select {
            padding: 10px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            background-color: white;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        select:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.1);
        }

        .table-container {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            margin-bottom: 30px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #2e7d32 0%, #1b5e20 100%);
            color: white;
        }

        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 16px;
        }

        th i {
            margin-left: 8px;
            opacity: 0.7;
        }

        tbody tr {
            border-bottom: 1px solid #f1f1f1;
            transition: background-color 0.2s;
        }

        tbody tr:hover {
            background-color: #f9f9f9;
        }

        td {
            padding: 18px 15px;
            color: #444;
        }

        .requestor-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4caf50 0%, #2e7d32 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .book-title {
            font-weight: 500;
            color: #2c3e50;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-stored {
            background: #1e9224;
            color: white;
        }

        .btn-stored:hover {
            background: #0e4b10;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
        }

        .btn-not-stored {
           background: #ff0000;
            color: white;
        }

        .btn-not-stored:hover {
            background: #b00707;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(231, 111, 81, 0.3);
        }

        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none !important;
            box-shadow: none !important;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-stored {
            background-color: #e8f5e9;
            color: #2e7d32;
        }

        .status-not-stored {
            background-color: #fadbd8;
            color: #c0392b;
        }

        .footer {
            text-align: center;
            margin-top: 30px;
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Sidebar adjustment for smaller screens */
        @media (max-width: 1024px) {
            .content-wrapper {
                margin-left: 0;
                padding: 15px;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                max-width: 100%;
            }
            
            .filters {
                flex-wrap: wrap;
            }
            
            th, td {
                padding: 12px 10px;
            }
            
            .btn {
                padding: 8px 12px;
                font-size: 13px;
            }
            
            .content-wrapper {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include '../components/header.php'; ?>
    <?php include '../components/sidebar.php'; ?>
    
    <div class="content-wrapper">
        <div class="container">
            <div class="controls">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-input" placeholder="Search by book title, requestor, or date...">
                </div>
                <div class="filters">
                    <select id="status-filter">
                        <option value="all">All Status</option>
                        <option value="stored">Stored</option>
                        <option value="not-stored">Not Stored</option>
                    </select>
                    <select id="date-filter">
                        <option value="recent">Most Recent</option>
                        <option value="oldest">Oldest First</option>
                    </select>
                </div>
            </div>

            <div class="table-container">
                <table id="requests-table">
                    <thead>
                        <tr>
                            <th>Date <i class="fas fa-sort"></i></th>
                            <th>Requestor <i class="fas fa-sort"></i></th>
                            <th>Book Title <i class="fas fa-sort"></i></th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <!-- Table rows will be generated by JavaScript -->
                    </tbody>
                </table>
            </div>            
        </div>
        <?php include '../components/footer.php'; ?>
    </div>

    <script>
        // Sample data for requested books
        const requestedBooks = [
            { id: 1, date: "2023-10-15", requestor: "John Smith", bookTitle: "The Great Gatsby", status: "stored" },
            { id: 2, date: "2023-10-18", requestor: "Emma Johnson", bookTitle: "To Kill a Mockingbird", status: "stored" },
            { id: 3, date: "2023-10-20", requestor: "Michael Brown", bookTitle: "1984", status: "not-stored" },
            { id: 4, date: "2023-10-22", requestor: "Sarah Davis", bookTitle: "Pride and Prejudice", status: "stored" },
            { id: 5, date: "2023-10-23", requestor: "David Wilson", bookTitle: "The Catcher in the Rye", status: "not-stored" },
            { id: 6, date: "2023-10-25", requestor: "Lisa Anderson", bookTitle: "Brave New World", status: "stored" },
            { id: 7, date: "2023-10-26", requestor: "Robert Taylor", bookTitle: "The Hobbit", status: "stored" },
            { id: 8, date: "2023-10-28", requestor: "Maria Garcia", bookTitle: "Fahrenheit 451", status: "not-stored" },
            { id: 9, date: "2023-10-29", requestor: "James Miller", bookTitle: "Moby Dick", status: "stored" },
            { id: 10, date: "2023-10-30", requestor: "Jennifer Lee", bookTitle: "War and Peace", status: "stored" },
            { id: 11, date: "2023-11-01", requestor: "Thomas Clark", bookTitle: "Crime and Punishment", status: "not-stored" },
            { id: 12, date: "2023-11-02", requestor: "Patricia Lewis", bookTitle: "The Odyssey", status: "stored" }
        ];

        // Initialize with all books
        let filteredBooks = [...requestedBooks];

        // Function to render the table
        function renderTable(books) {
            const tableBody = document.getElementById('table-body');
            tableBody.innerHTML = '';
            
            books.forEach(book => {
                const row = document.createElement('tr');
                
                // Format date
                const dateObj = new Date(book.date);
                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric' 
                });
                
                // Get initials for avatar
                const initials = book.requestor.split(' ').map(name => name[0]).join('');
                
                // Determine status badge
                const statusClass = book.status === 'stored' ? 'status-stored' : 'status-not-stored';
                const statusText = book.status === 'stored' ? 'Stored' : 'Not Stored';
                
                row.innerHTML = `
                    <td>${formattedDate}</td>
                    <td>
                        <div class="requestor-info">
                            <div class="avatar">${initials}</div>
                            <span>${book.requestor}</span>
                        </div>
                    </td>
                    <td class="book-title">${book.bookTitle}</td>
                    <td><span class="status-badge ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-stored" onclick="markAsStored(${book.id})" ${book.status === 'stored' ? 'disabled' : ''}>
                                <i class="fas fa-check"></i> Stored
                            </button>
                            <button class="btn btn-not-stored" onclick="markAsNotStored(${book.id})" ${book.status === 'not-stored' ? 'disabled' : ''}>
                                <i class="fas fa-times"></i> Not Stored
                            </button>
                        </div>
                    </td>
                `;
                
                tableBody.appendChild(row);
            });
            
            // Update statistics
            updateStatistics(books);
        }

        // Function to update statistics
        function updateStatistics(books) {
            const totalRequests = books.length;
            const storedBooks = books.filter(book => book.status === 'stored').length;
            const pendingBooks = books.filter(book => book.status === 'not-stored').length;
            
            document.getElementById('total-requests').textContent = totalRequests;
            document.getElementById('stored-books').textContent = storedBooks;
            document.getElementById('pending-books').textContent = pendingBooks;
        }

        // Function to mark a book as stored
        function markAsStored(id) {
            const bookIndex = requestedBooks.findIndex(book => book.id === id);
            if (bookIndex !== -1) {
                requestedBooks[bookIndex].status = 'stored';
                
                // Reapply filters and render
                applyFilters();
                
                // Show confirmation
                showNotification(`Book marked as stored successfully!`, 'success');
            }
        }

        // Function to mark a book as not stored
        function markAsNotStored(id) {
            const bookIndex = requestedBooks.findIndex(book => book.id === id);
            if (bookIndex !== -1) {
                requestedBooks[bookIndex].status = 'not-stored';
                
                // Reapply filters and render
                applyFilters();
                
                // Show confirmation
                showNotification(`Book marked as not stored!`, 'warning');
            }
        }

        // Function to apply filters
        function applyFilters() {
            const searchTerm = document.getElementById('search-input').value.toLowerCase();
            const statusFilter = document.getElementById('status-filter').value;
            const dateFilter = document.getElementById('date-filter').value;
            
            filteredBooks = requestedBooks.filter(book => {
                // Apply search filter
                const matchesSearch = 
                    book.requestor.toLowerCase().includes(searchTerm) ||
                    book.bookTitle.toLowerCase().includes(searchTerm) ||
                    book.date.includes(searchTerm);
                
                // Apply status filter
                const matchesStatus = 
                    statusFilter === 'all' || 
                    book.status === statusFilter;
                
                return matchesSearch && matchesStatus;
            });
            
            // Apply date sorting
            filteredBooks.sort((a, b) => {
                if (dateFilter === 'recent') {
                    return new Date(b.date) - new Date(a.date);
                } else {
                    return new Date(a.date) - new Date(b.date);
                }
            });
            
            renderTable(filteredBooks);
        }

        // Function to show notification
        function showNotification(message, type = 'success') {
            // Create notification element
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? '#2e7d32' : '#e76f51';
            
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background-color: ${bgColor};
                color: white;
                padding: 15px 25px;
                border-radius: 6px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
                z-index: 1000;
                font-weight: 600;
                animation: slideIn 0.3s ease-out;
                display: flex;
                align-items: center;
                gap: 10px;
            `;
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            notification.innerHTML = `<i class="${icon}"></i> ${message}`;
            document.body.appendChild(notification);
            
            // Remove notification after 3 seconds
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // Add CSS for animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);

        // Initialize table on page load
        document.addEventListener('DOMContentLoaded', () => {
            renderTable(requestedBooks);
            
            // Set up event listeners for filters
            document.getElementById('search-input').addEventListener('input', applyFilters);
            document.getElementById('status-filter').addEventListener('change', applyFilters);
            document.getElementById('date-filter').addEventListener('change', applyFilters);
        });
    </script>
</body>
</html>