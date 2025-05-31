<?php
// Define page title *before* including header
$page_title = 'Client Search & History';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user has permission (receptionist can access this)
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'receptionist'])) {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-search me-2 text-primary"></i> Client Search</h2>
    <a href="add_patient.php" class="btn btn-success"><i class="fas fa-user-plus me-1"></i> Add New Patient</a>
</div>

<!-- Search Card -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-filter me-2"></i> Find a Patient</h5>
    </div>
    <div class="card-body">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="searchInput" class="form-control form-control-lg"
                placeholder="Search by Name or Contact Number...">
            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" title="Clear Search"><i
                    class="fas fa-times"></i></button>
        </div>
        <small class="form-text text-muted">Start typing to see results.</small>
    </div>
</div>

<!-- Search Results Area -->
<div class="card shadow-sm">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="fas fa-users me-2"></i> Search Results</h5>
        <!-- Loading Spinner -->
        <div id="loadingSpinner" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
    <div class="card-body p-0">
        <div id="resultsList" class="list-group list-group-flush">
            <!-- Initial message -->
            <div class="list-group-item text-center text-muted" id="initialMessage">
                Please enter a search term above.
            </div>
            <!-- Dynamic results will be appended here -->
        </div>
    </div>
</div>

<!-- JavaScript for Live Search -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('searchInput');
        const resultsList = document.getElementById('resultsList');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const initialMessage = document.getElementById('initialMessage');
        const clearSearchBtn = document.getElementById('clearSearchBtn');
        let searchTimeout;

        function performSearch() {
            const query = searchInput.value.trim();

            // Clear previous results and hide initial message
            resultsList.innerHTML = '';
            if (initialMessage) initialMessage.style.display = 'none';

            if (query.length < 2) {
                if (query.length === 0 && initialMessage) {
                    initialMessage.style.display = 'block';
                    resultsList.appendChild(initialMessage);
                } else if (query.length > 0) {
                    resultsList.innerHTML = '<div class="list-group-item text-center text-muted">Please enter at least 2 characters.</div>';
                }
                loadingSpinner.style.display = 'none';
                return;
            }

            loadingSpinner.style.display = 'inline-block';

            fetch(`search_clients.php?query=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(clients => {
                    loadingSpinner.style.display = 'none';
                    if (clients.length > 0) {
                        clients.forEach(client => {
                            const item = document.createElement('a');
                            item.href = `client_details.php?client_id=${client.id}`;
                            item.className = 'list-group-item list-group-item-action d-flex justify-content-between align-items-center';

                            const nameSpan = document.createElement('span');
                            nameSpan.innerHTML = `<i class="fas fa-user me-2 text-muted"></i> ${highlightSearchTerm(client.full_name, query)}`;

                            const contactSpan = document.createElement('span');
                            contactSpan.className = 'text-muted small me-3';
                            contactSpan.innerHTML = `<i class="fas fa-phone me-1"></i> ${highlightSearchTerm(client.contact_number, query)}`;

                            const viewButton = document.createElement('span');
                            viewButton.className = 'badge bg-primary rounded-pill';
                            viewButton.innerHTML = 'View Details <i class="fas fa-arrow-right ms-1"></i>';

                            const leftDiv = document.createElement('div');
                            leftDiv.appendChild(nameSpan);
                            leftDiv.appendChild(contactSpan);

                            item.appendChild(leftDiv);
                            item.appendChild(viewButton);
                            resultsList.appendChild(item);
                        });
                    } else {
                        resultsList.innerHTML = '<div class="list-group-item text-center text-muted">No clients found matching your search.</div>';
                    }
                })
                .catch(error => {
                    loadingSpinner.style.display = 'none';
                    console.error('Search Error:', error);
                    resultsList.innerHTML = '<div class="list-group-item text-center text-danger">An error occurred during the search. Please try again.</div>';
                });
        }

        // Function to highlight search terms
        function highlightSearchTerm(text, term) {
            if (!text) return '';
            const terms = term.split(' ').filter(t => t.length > 0);
            let highlightedText = text;
            terms.forEach(t => {
                const regex = new RegExp(`(${t.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&')})`, 'gi');
                highlightedText = highlightedText.replace(regex, '<strong class="text-primary">$1</strong>');
            });
            return highlightedText;
        }

        searchInput.addEventListener('input', function () {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(performSearch, 300);
        });

        clearSearchBtn.addEventListener('click', function () {
            searchInput.value = '';
            resultsList.innerHTML = '';
            if (initialMessage) {
                initialMessage.style.display = 'block';
                resultsList.appendChild(initialMessage);
            }
            loadingSpinner.style.display = 'none';
            searchInput.focus();
        });
    });
</script>

<?php include '../includes/footer.php'; ?>