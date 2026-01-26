<!-- PHP includes will go here -->
<?php include '../components/header.php'; ?>
<?php include '../components/sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f9f5;
            color: #333;
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px; /* Same as sidebar width */
            padding: 20px;
            transition: margin-left 0.3s;
        }
        
        .header {
            background-color: white;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #2e7d32;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #2e7d32;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .container {
            max-width: 1300px;
            margin: 0 auto;
        }
        
        .add-book-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .search-section {
            flex-grow: 1;
            margin-bottom: 0;
        }
        
        .search-container {
            position: relative;
            width: 100%;
            max-width: 500px;
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
            border: 1px solid #ccc;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: white;
        }
        
        #searchInput:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-add {
            background-color: #2e7d32;
            color: white;
        }
        
        .btn-add:hover {
            background-color: #1b5e20;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(46, 125, 50, 0.3);
        }
        
        .books-table {
            width: 100%;
            background-color: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead {
            background-color: #2e7d32;
            color: white;
        }
        
        th {
            padding: 18px 15px;
            text-align: left;
            font-weight: 600;
        }
        
        tbody tr {
            border-bottom: 1px solid #e8f5e9;
        }
        
        tbody tr:nth-child(even) {
            background-color: #f8fdf8;
        }
        
        tbody tr:hover {
            background-color: #e8f5e9;
        }
        
        td {
            padding: 16px 15px;
        }
        
        .category-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            text-align: center;
            min-width: 80px;
        }
        
        .category-fiction {
            background-color: #e3f2fd;
            color: #1565c0;
        }
        
        .category-non-fiction {
            background-color: #f3e5f5;
            color: #7b1fa2;
        }
        
        .category-science {
            background-color: #e8f5e9;
            color: #2e7d32;
        }
        
        .category-technology {
            background-color: #fff3e0;
            color: #ef6c00;
        }
        
        .category-literature {
            background-color: #fce4ec;
            color: #c2185b;
        }
        
        .category-history {
            background-color: #e0f2f1;
            color: #00695c;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-edit {
            background-color: #ffb74d;
            color: #5d4037;
            border: none;
        }
        
        .btn-edit:hover {
            background-color: #ffa726;
        }
        
        .btn-delete {
            background-color: #ef5350;
            color: white;
            border: none;
        }
        
        .btn-delete:hover {
            background-color: #e53935;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background-color: white;
            width: 90%;
            max-width: 650px;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        
        .modal-header {
            background-color: #2e7d32;
            color: white;
            padding: 20px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: white;
            font-size: 1.8rem;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2e7d32;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 6px;
            border: 1px solid #ccc;
            font-size: 1rem;
            transition: border 0.3s;
        }
        
        .form-group input:focus, .form-group select:focus {
            border-color: #2e7d32;
            outline: none;
            box-shadow: 0 0 0 2px rgba(46, 125, 50, 0.2);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
        }
        
        .form-row .form-group {
            flex: 1;
        }
        
        .modal-footer {
            padding: 20px 30px;
            background-color: #f5f9f5;
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .btn-save {
            background-color: #2e7d32;
            color: white;
        }
        
        .btn-save:hover {
            background-color: #1b5e20;
        }
        
        .btn-cancel {
            background-color: #9e9e9e;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #757575;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
            }
            
            .books-table {
                overflow-x: auto;
                display: block;
            }
            
            table {
                min-width: 1100px;
            }
        }
        
        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .user-info {
                align-self: flex-end;
            }
            
            .add-book-section {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-container {
                max-width: 100%;
            }
            
            .btn-add {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
  
    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="header">
        
        <div class="container">
            <div class="add-book-section">
                <div class="search-section">
                    <div class="search-container">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search books by title, author, category, or ISBN...">
                    </div>
                </div>
                <button class="btn btn-add" id="addBookBtn">
                    <i class="fas fa-plus-circle"></i> Add New Book
                </button>
            </div>
            
            <div class="books-table">
                <table>
                    <thead>
                        <tr>
                            <th>Call Number</th>
                            <th>Book Title</th>
                            <th>Author</th>
                            <th>Category</th>
                            <th>Copyright Year</th>
                            <th>ISBN</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="booksTableBody">
                        <!-- Books will be populated here -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>
    
    <!-- Add/Edit Book Modal -->
    <div class="modal" id="bookModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-book-medical"></i> <span id="modalTitle">Add New Book</span></h2>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            
            <div class="modal-body">
                <form id="bookForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="callNumber">Call Number *</label>
                            <input type="text" id="callNumber" name="callNumber" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="isbn">ISBN *</label>
                            <input type="text" id="isbn" name="isbn" required>
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
                                <option value="">Select a category</option>
                                <option value="Fiction">Fiction</option>
                                <option value="Non-Fiction">Non-Fiction</option>
                                <option value="Science">Science</option>
                                <option value="Technology">Technology</option>
                                <option value="Literature">Literature</option>
                                <option value="History">History</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="copyrightYear">Copyright Year *</label>
                            <input type="number" id="copyrightYear" name="copyrightYear" min="1900" max="2030" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity *</label>
                            <input type="number" id="quantity" name="quantity" min="1" required>
                        </div>
                    </div>
                </form>
            </div>
             
            <div class="modal-footer">
                <button class="btn btn-cancel" id="cancelBtn">Cancel</button>
                <button class="btn btn-save" id="saveBookBtn">
                    <i class="fas fa-save"></i> Save Book
                </button>
            </div>
        </div>
        
    </div>
    
    <script>
        // Sample book data with categories
        let books = [
            {
                id: 1,
                callNumber: "QA76.73.J39",
                title: "JavaScript: The Definitive Guide",
                author: "David Flanagan",
                category: "Technology",
                copyrightYear: 2020,
                isbn: "978-1491952023",
                quantity: 5
            },
            {
                id: 2,
                callNumber: "PS3563.A722",
                title: "The Hobbit",
                author: "J.R.R. Tolkien",
                category: "Fiction",
                copyrightYear: 1937,
                isbn: "978-0547928227",
                quantity: 8
            },
            {
                id: 3,
                callNumber: "PR6039.O32",
                title: "1984",
                author: "George Orwell",
                category: "Fiction",
                copyrightYear: 1949,
                isbn: "978-0451524935",
                quantity: 6
            },
            {
                id: 4,
                callNumber: "PS3511.A86",
                title: "The Great Gatsby",
                author: "F. Scott Fitzgerald",
                category: "Literature",
                copyrightYear: 1925,
                isbn: "978-0743273565",
                quantity: 4
            },
            {
                id: 5,
                callNumber: "PR4551.A2",
                title: "Pride and Prejudice",
                author: "Jane Austen",
                category: "Literature",
                copyrightYear: 1813,
                isbn: "978-1503290563",
                quantity: 7
            },
            {
                id: 6,
                callNumber: "QH366.2.D36",
                title: "The Origin of Species",
                author: "Charles Darwin",
                category: "Science",
                copyrightYear: 1859,
                isbn: "978-0451529060",
                quantity: 3
            },
            {
                id: 7,
                callNumber: "D16.8.H57",
                title: "A Short History of Nearly Everything",
                author: "Bill Bryson",
                category: "History",
                copyrightYear: 2003,
                isbn: "978-0767908184",
                quantity: 9
            }
        ];
        
        // Available categories with their colors
        const categoryColors = {
            "Fiction": "category-fiction",
            "Non-Fiction": "category-non-fiction",
            "Science": "category-science",
            "Technology": "category-technology",
            "Literature": "category-literature",
            "History": "category-history",
            "Other": "category-non-fiction"
        };
        
        // DOM Elements
        const booksTableBody = document.getElementById('booksTableBody');
        const bookModal = document.getElementById('bookModal');
        const addBookBtn = document.getElementById('addBookBtn');
        const closeModal = document.getElementById('closeModal');
        const cancelBtn = document.getElementById('cancelBtn');
        const saveBookBtn = document.getElementById('saveBookBtn');
        const bookForm = document.getElementById('bookForm');
        const modalTitle = document.getElementById('modalTitle');
        
        // Current book being edited (null for new book)
        let currentBookId = null;
        
        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            renderBooks();
            
            // Event Listeners
            addBookBtn.addEventListener('click', openAddBookModal);
            closeModal.addEventListener('click', closeBookModal);
            cancelBtn.addEventListener('click', closeBookModal);
            saveBookBtn.addEventListener('click', saveBook);
            
            // Add search input event listener
            const searchInput = document.getElementById('searchInput');
            searchInput.addEventListener('input', function() {
                searchBooks(this.value);
            });
            
            // Close modal when clicking outside of it
            window.addEventListener('click', function(event) {
                if (event.target === bookModal) {
                    closeBookModal();
                }
            });
            
            // Highlight active menu item
            document.querySelectorAll('.sidebar-menu a').forEach(item => {
                item.addEventListener('click', function() {
                    document.querySelectorAll('.sidebar-menu a').forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Close sidebar on mobile after selection
                    if (window.innerWidth <= 992) {
                        sidebar.classList.remove('active');
                    }
                });
            });
        });
        
        // Render books to the table
        function renderBooks(bookList = books) {
            booksTableBody.innerHTML = '';
            
            if (bookList.length === 0) {
                booksTableBody.innerHTML = `
                    <tr>
                        <td colspan="8" style="text-align: center; padding: 40px; color: #666;">
                            <i class="fas fa-book-open" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #ccc;"></i>
                            No books found. Add your first book!
                        </td>
                    </tr>
                `;
                return;
            }
            
            bookList.forEach(book => {
                const row = document.createElement('tr');
                const categoryClass = categoryColors[book.category] || "category-non-fiction";
                
                row.innerHTML = `
                    <td>${book.callNumber}</td>
                    <td><strong>${book.title}</strong></td>
                    <td>${book.author}</td>
                    <td>
                        <span class="category-badge ${categoryClass}">
                            ${book.category}
                        </span>
                    </td>
                    <td>${book.copyrightYear}</td>
                    <td>${book.isbn}</td>
                    <td>
                        <span class="quantity-badge" style="
                            background-color: ${book.quantity > 5 ? '#c8e6c9' : (book.quantity > 2 ? '#fff9c4' : '#ffcdd2')};
                            color: ${book.quantity > 5 ? '#2e7d32' : (book.quantity > 2 ? '#f57f17' : '#c62828')};
                            padding: 5px 10px;
                            border-radius: 20px;
                            font-weight: 600;
                            display: inline-block;
                        ">
                            ${book.quantity} ${book.quantity === 1 ? 'copy' : 'copies'}
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="btn btn-edit" onclick="editBook(${book.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn btn-delete" onclick="deleteBook(${book.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </td>
                `;
                booksTableBody.appendChild(row);
            });
        }
        
        // Search functionality
        function searchBooks(searchTerm) {
            const filteredBooks = books.filter(book => 
                book.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
                book.author.toLowerCase().includes(searchTerm.toLowerCase()) ||
                book.category.toLowerCase().includes(searchTerm.toLowerCase()) ||
                book.isbn.includes(searchTerm) ||
                book.callNumber.toLowerCase().includes(searchTerm.toLowerCase())
            );
            
            renderBooks(filteredBooks);
        }
        
        // Open modal for adding a new book
        function openAddBookModal() {
            currentBookId = null;
            modalTitle.textContent = "Add New Book";
            bookForm.reset();
            document.getElementById('category').value = "";
            bookModal.style.display = "flex";
        }
        
        // Open modal for editing an existing book
        function editBook(id) {
            const book = books.find(b => b.id === id);
            if (!book) return;
            
            currentBookId = id;
            modalTitle.textContent = "Edit Book";
            
            // Fill form with book data
            document.getElementById('callNumber').value = book.callNumber;
            document.getElementById('title').value = book.title;
            document.getElementById('author').value = book.author;
            document.getElementById('category').value = book.category;
            document.getElementById('copyrightYear').value = book.copyrightYear;
            document.getElementById('isbn').value = book.isbn;
            document.getElementById('quantity').value = book.quantity;
            
            bookModal.style.display = "flex";
        }
        
        // Close the modal
        function closeBookModal() {
            bookModal.style.display = "none";
            bookForm.reset();
            currentBookId = null;
        }
        
        // Save book (add new or update existing)
        function saveBook() {
            // Validate form
            if (!bookForm.checkValidity()) {
                alert("Please fill in all required fields correctly.");
                return;
            }
            
            const bookData = {
                callNumber: document.getElementById('callNumber').value,
                title: document.getElementById('title').value,
                author: document.getElementById('author').value,
                category: document.getElementById('category').value,
                copyrightYear: document.getElementById('copyrightYear').value,
                isbn: document.getElementById('isbn').value,
                quantity: parseInt(document.getElementById('quantity').value)
            };
            
            if (currentBookId === null) {
                // Add new book
                const newId = books.length > 0 ? Math.max(...books.map(b => b.id)) + 1 : 1;
                bookData.id = newId;
                books.push(bookData);
            } else {
                // Update existing book
                const index = books.findIndex(b => b.id === currentBookId);
                if (index !== -1) {
                    bookData.id = currentBookId;
                    books[index] = bookData;
                }
            }
            
            renderBooks();
            closeBookModal();
            
            // Show success message
            alert(`Book "${bookData.title}" has been ${currentBookId === null ? 'added' : 'updated'} successfully!`);
        }
        
        // Delete a book
        function deleteBook(id) {
            if (!confirm("Are you sure you want to delete this book?")) {
                return;
            }
            
            const bookTitle = books.find(b => b.id === id)?.title;
            books = books.filter(book => book.id !== id);
            
            renderBooks();
            
            // Show success message
            alert(`Book "${bookTitle}" has been deleted successfully!`);
        }
    </script>

   

</body>
</html>