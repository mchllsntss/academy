<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Library Book Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar Styles */
        .sidebar-container {
            width: 250px;
            background-color: #2e7d32;
            color: white;
            min-height: 100vh;
        }
        
        /* Main Content Area */
        .main-content {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }
        
        /* Header Section */
        .header {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .header h1 {
            color: #2e7d32;
            font-size: 2.5rem;
            margin-bottom: 10px;
            border-bottom: 3px solid #4caf50;
            display: inline-block;
            padding-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 1.1rem;
        }
        
        /* Search Bar */
        .search-container {
            background-color: #ffffff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(46, 125, 50, 0.1);
            margin-bottom: 25px;
            border-left: 5px solid #4caf50;
        }
        
        .search-box {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 15px;
            border: 2px solid #c8e6c9;
            border-radius: 6px;
            font-size: 16px;
            color: #2e7d32;
            transition: all 0.3s;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2e7d32;
            box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.2);
        }
        
        .search-btn {
            background-color: #4caf50;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 12px 25px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .search-btn:hover {
            background-color: #388e3c;
        }
        
        .filter-select {
            padding: 12px 15px;
            border: 2px solid #c8e6c9;
            border-radius: 6px;
            font-size: 16px;
            color: #2e7d32;
            background-color: white;
        }
        
        /* Stats */
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .stat-card {
            background-color: #e8f5e9;
            border-radius: 8px;
            padding: 15px 20px;
            flex: 1;
            min-width: 200px;
            text-align: center;
            border-left: 4px solid #4caf50;
        }
        
        .stat-card h3 {
            color: #2e7d32;
            font-size: 1.8rem;
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        /* Table */
        .table-container {
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        thead {
            background-color: #2e7d32;
            color: white;
        }
        
        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 1rem;
            user-select: none;
        }
        
        tbody tr {
            border-bottom: 1px solid #e0e0e0;
            transition: background-color 0.2s;
        }
        
        tbody tr:hover {
            background-color: #f1f8e9;
        }
        
        td {
            padding: 16px 15px;
            color: #555;
        }
        
        /* Quantity indicator */
        .quantity-indicator {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .quantity-high {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .quantity-low {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .quantity-zero {
            background-color: #ffebee;
            color: #c62828;
        }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            border: none;
            cursor: pointer;
            font-size: 0.85rem;
            font-weight: 600;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-reserve {
            background-color: #ffb74d;
            color: #5d4037;
        }
        
        .btn-reserve:hover {
            background-color: #ffa726;
        }
        
        .btn-borrow {
            background-color: #4fc3f7;
            color: #01579b;
        }
        
        .btn-borrow:hover {
            background-color: #29b6f6;
        }
        
        .btn-disabled {
            background-color: #e0e0e0;
            color: #9e9e9e;
            cursor: not-allowed;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .page-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #c8e6c9;
            background-color: white;
            color: #2e7d32;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .page-btn:hover {
            background-color: #e8f5e9;
        }
        
        .page-btn.active {
            background-color: #4caf50;
            color: white;
            border-color: #4caf50;
        }
        
        .page-btn:disabled {
            background-color: #f5f5f5;
            color: #bdbdbd;
            cursor: not-allowed;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            body {
                flex-direction: column;
            }
            
            .sidebar-container {
                width: 100%;
                min-height: auto;
            }
            
            .main-content {
                padding: 15px;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }
            
            .search-box {
                flex-direction: column;
            }
            
            .search-input {
                min-width: 100%;
            }
            
            .stat-card {
                min-width: 100%;
            }
            
            th, td {
                padding: 12px 10px;
            }
            
            .container {
                padding: 0 10px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar-container">
        <?php include '../components/student_sidebar.php'; ?>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header-container">
            <?php include '../components/header.php'; ?>
        </div>
        
        <div class="container">
            <!-- Search Bar -->
            <div class="search-container">
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search by book title, author, ISBN, or call number...">
                    <select class="filter-select">
                        <option value="">All Categories</option>
                        <option value="Fiction">Fiction</option>
                        <option value="Science">Science</option>
                        <option value="History">History</option>
                        <option value="Technology">Technology</option>
                        <option value="Biography">Biography</option>
                    </select>
                    <button class="search-btn">
                        <i class="fas fa-search"></i> Search
                    </button>
                </div>
            </div>
            
            <!-- Book Table -->
            <div class="table-container">
                <table id="bookTable">
                    <thead>
                        <tr>
                            <th>Call Number</th>
                            <th>Book Title</th>
                            <th>Book Shelve Number</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Copyright Year</th>
                            <th>ISBN</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="bookTableBody">
                        <!-- Book rows will be generated by JavaScript -->
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="page-btn" id="prevPage"><i class="fas fa-chevron-left"></i></button>
                <button class="page-btn active">1</button>
                <button class="page-btn">2</button>
                <button class="page-btn">3</button>
                <button class="page-btn">4</button>
                <button class="page-btn">5</button>
                <button class="page-btn" id="nextPage"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer-container">
            <?php include '../components/footer.php'; ?>
        </div>
    </div>

    <script>
        // Sample book data
        const books = [
            {
                callNumber: "FIC-2023-001",
                title: "The Silent Forest",
                shelf: "A-12",
                author: "Margaret Atwood",
                category: "Fiction",
                year: 2023,
                isbn: "978-0-123456-47-0",
                quantity: 5
            },
            {
                callNumber: "SCI-2021-045",
                title: "Quantum Physics for Beginners",
                shelf: "B-07",
                author: "Brian Greene",
                category: "Science",
                year: 2021,
                isbn: "978-1-234567-89-0",
                quantity: 3
            },
            {
                callNumber: "HIS-2019-112",
                title: "Ancient Civilizations",
                shelf: "C-22",
                author: "David Graeber",
                category: "History",
                year: 2019,
                isbn: "978-0-987654-32-1",
                quantity: 7
            },
            {
                callNumber: "TEC-2022-033",
                title: "Artificial Intelligence: A Modern Approach",
                shelf: "D-15",
                author: "Stuart Russell",
                category: "Technology",
                year: 2022,
                isbn: "978-1-135792-46-8",
                quantity: 4
            },
            {
                callNumber: "BIO-2020-078",
                title: "The Wright Brothers",
                shelf: "E-09",
                author: "David McCullough",
                category: "Biography",
                year: 2020,
                isbn: "978-1-246813-57-9",
                quantity: 2
            },
            {
                callNumber: "FIC-2018-156",
                title: "Midnight Library",
                shelf: "A-18",
                author: "Matt Haig",
                category: "Fiction",
                year: 2018,
                isbn: "978-0-753549-82-6",
                quantity: 0
            },
            {
                callNumber: "SCI-2023-011",
                title: "The Gene: An Intimate History",
                shelf: "B-03",
                author: "Siddhartha Mukherjee",
                category: "Science",
                year: 2023,
                isbn: "978-1-473612-34-5",
                quantity: 6
            },
            {
                callNumber: "HIS-2021-089",
                title: "Sapiens: A Brief History of Humankind",
                shelf: "C-11",
                author: "Yuval Noah Harari",
                category: "History",
                year: 2021,
                isbn: "978-0-099590-08-4",
                quantity: 9
            },
            {
                callNumber: "TEC-2023-005",
                title: "Clean Code: A Handbook of Agile Software Craftsmanship",
                shelf: "D-21",
                author: "Robert C. Martin",
                category: "Technology",
                year: 2023,
                isbn: "978-0-132357-08-4",
                quantity: 1
            },
            {
                callNumber: "BIO-2019-067",
                title: "Becoming",
                shelf: "E-14",
                author: "Michelle Obama",
                category: "Biography",
                year: 2019,
                isbn: "978-1-524796-31-9",
                quantity: 8
            }
        ];

        // Initialize variables
        let currentPage = 1;
        const booksPerPage = 8;

        // Function to render book table
        function renderBookTable(bookList = books) {
            const tableBody = document.getElementById('bookTableBody');
            tableBody.innerHTML = '';
            
            // Calculate start and end index for current page
            const startIndex = (currentPage - 1) * booksPerPage;
            const endIndex = Math.min(startIndex + booksPerPage, bookList.length);
            
            // Create table rows for current page
            for (let i = startIndex; i < endIndex; i++) {
                const book = bookList[i];
                const row = document.createElement('tr');
                
                // Determine quantity indicator class
                let quantityClass = 'quantity-high';
                if (book.quantity === 0) {
                    quantityClass = 'quantity-zero';
                } else if (book.quantity <= 2) {
                    quantityClass = 'quantity-low';
                }
                
                // Determine if buttons should be disabled
                const reserveDisabled = book.quantity === 0;
                const borrowDisabled = book.quantity === 0;
                
                row.innerHTML = `
                    <td>${book.callNumber}</td>
                    <td><strong>${book.title}</strong></td>
                    <td>${book.shelf}</td>
                    <td>${book.author}</td>
                    <td><span class="quantity-indicator">${book.category}</span></td>
                    <td>${book.year}</td>
                    <td>${book.isbn}</td>
                    <td><span class="quantity-indicator ${quantityClass}">${book.quantity} available</span></td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-reserve ${reserveDisabled ? 'btn-disabled' : ''}" ${reserveDisabled ? 'disabled' : ''}>
                                <i class="fas fa-calendar-check"></i> Reserve
                            </button>
                            <button class="btn btn-borrow ${borrowDisabled ? 'btn-disabled' : ''}" ${borrowDisabled ? 'disabled' : ''}>
                                <i class="fas fa-book-open"></i> Borrow
                            </button>
                        </div>
                    </td>
                `;
                
                tableBody.appendChild(row);
            }
            
            // Update pagination
            updatePagination(bookList.length);
        }

        // Function to update pagination
        function updatePagination(totalBooks) {
            const totalPages = Math.ceil(totalBooks / booksPerPage);
            const paginationContainer = document.querySelector('.pagination');
            let paginationHTML = `
                <button class="page-btn" id="prevPage" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
            `;
            
            // Create page buttons
            for (let i = 1; i <= totalPages; i++) {
                paginationHTML += `
                    <button class="page-btn ${i === currentPage ? 'active' : ''}" data-page="${i}">
                        ${i}
                    </button>
                `;
            }
            
            paginationHTML += `
                <button class="page-btn" id="nextPage" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            `;
            
            paginationContainer.innerHTML = paginationHTML;
            
            // Add event listeners to page buttons
            document.querySelectorAll('.page-btn[data-page]').forEach(btn => {
                btn.addEventListener('click', () => {
                    currentPage = parseInt(btn.getAttribute('data-page'));
                    renderBookTable();
                });
            });
            
            // Add event listeners to prev/next buttons
            document.getElementById('prevPage').addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderBookTable();
                }
            });
            
            document.getElementById('nextPage').addEventListener('click', () => {
                if (currentPage < totalPages) {
                    currentPage++;
                    renderBookTable();
                }
            });
        }

        // Function to handle search
        function handleSearch() {
            const searchInput = document.querySelector('.search-input');
            const categoryFilter = document.querySelector('.filter-select');
            const searchTerm = searchInput.value.toLowerCase();
            const selectedCategory = categoryFilter.value;
            
            const filteredBooks = books.filter(book => {
                // Check search term
                const matchesSearch = searchTerm === '' || 
                    book.title.toLowerCase().includes(searchTerm) ||
                    book.author.toLowerCase().includes(searchTerm) ||
                    book.isbn.toLowerCase().includes(searchTerm) ||
                    book.callNumber.toLowerCase().includes(searchTerm);
                
                // Check category filter
                const matchesCategory = selectedCategory === '' || book.category === selectedCategory;
                
                return matchesSearch && matchesCategory;
            });
            
            // Reset to first page when searching
            currentPage = 1;
            
            // Render filtered books
            renderBookTable(filteredBooks);
        }

        // Initialize the page
        document.addEventListener('DOMContentLoaded', () => {
            // Render initial book table
            renderBookTable();
            
            // Add event listener to search button
            document.querySelector('.search-btn').addEventListener('click', handleSearch);
            
            // Add event listener to search input (for enter key)
            document.querySelector('.search-input').addEventListener('keyup', (e) => {
                if (e.key === 'Enter') {
                    handleSearch();
                }
            });
            
            // Add event listener to category filter
            document.querySelector('.filter-select').addEventListener('change', handleSearch);
            
            // Add event listeners to action buttons (delegated to table body)
            document.getElementById('bookTableBody').addEventListener('click', (e) => {
                const target = e.target;
                const row = target.closest('tr');
                
                if (target.classList.contains('btn-reserve') && !target.disabled) {
                    const bookTitle = row.cells[1].textContent;
                    alert(`Book "${bookTitle}" has been reserved successfully!`);
                }
                
                if (target.classList.contains('btn-borrow') && !target.disabled) {
                    const bookTitle = row.cells[1].textContent;
                    alert(`Book "${bookTitle}" has been borrowed successfully!`);
                }
            });
        });
    </script>
    
</body>
</html>