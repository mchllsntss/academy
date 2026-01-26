<!-- Footer Component -->
<style>
    .page-container {
        display: flex;
        flex-direction: column;
        min-height: 100vh;
        position: relative;
    }
    
    .main-content {
        flex: 1;
    }
    
    .site-footer {
        background: linear-gradient(135deg, #e8f5e9 0%, #c8e6c9 100%);
        border-top: 1px solid rgba(76, 175, 80, 0.2);
        box-shadow: 0 -2px 10px rgba(76, 175, 80, 0.1);
        padding: 15px 0;
        width: 100%;
        z-index: 10;
    }
    
    .footer-content {
        max-width: 1200px;
        margin: 0 auto;
        text-align: center;
    }
    
    .footer-text {
        color: #2e7d32;
        font-weight: 600;
        font-size: 0.9rem;
        margin: 0;
    }
    
    .footer-copyright {
        color: #2e7d32;
        font-size: 0.75rem;
        margin: 5px 0 0 0;
    }
</style>

<footer class="site-footer">
    <div class="footer-content">
        <p class="footer-text">Library Management System v1.0</p>
        <p class="footer-copyright">© <?php echo date('Y'); ?> La Trinidad Academy</p>
    </div>
</footer>