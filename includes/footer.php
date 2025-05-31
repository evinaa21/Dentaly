</div> <!-- /content-wrapper -->
</main> <!-- /main-content -->
</div>

<!-- Footer -->
<footer class="footer text-center py-3 mt-auto">
    <div class="container">
        <span class="text-muted">&copy; 2025 Dentaly. All rights reserved.</span>
    </div>
</footer>

<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- Custom JS -->
<script src="../assets/js/script.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Mobile sidebar toggle
        const sidebar = document.getElementById('sidebarMenu');
        const mobileToggle = document.querySelector('[data-bs-target="#sidebarMenu"]');

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(e.target) && !mobileToggle?.contains(e.target)) {
                    sidebar.classList.remove('show');
                }
            }
        });

        // Active navigation highlighting
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll('.nav-link');

        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentPath.split('/').pop()) {
                link.classList.add('active');
            }
        });

        // Auto-dismiss alerts
        const alerts = document.querySelectorAll('[data-auto-dismiss]');
        alerts.forEach(alert => {
            const delay = parseInt(alert.getAttribute('data-auto-dismiss')) || 5000;
            setTimeout(() => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, delay);
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Confirm delete functionality
        const confirmDeleteForms = document.querySelectorAll('.confirm-delete-form');
        confirmDeleteForms.forEach(form => {
            form.addEventListener('submit', function (e) {
                const button = form.querySelector('.confirm-delete');
                const message = button?.dataset.message || 'Are you sure you want to delete this item?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        });

        // Smooth scrolling for internal links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Loading states for buttons
        const loadingButtons = document.querySelectorAll('[data-loading]');
        loadingButtons.forEach(button => {
            button.addEventListener('click', function () {
                const originalText = this.innerHTML;
                this.innerHTML = '<span class="loading-spinner"></span> Loading...';
                this.disabled = true;

                // Re-enable after form submission or timeout
                setTimeout(() => {
                    this.innerHTML = originalText;
                    this.disabled = false;
                }, 3000);
            });
        });
    });
</script>
</body>

</html>