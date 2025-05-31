<?php
include '../config/db.php'; // Include database connection

// Define page title *before* including header
$page_title = 'Client History Search';
include '../includes/header.php'; // Include the header with the sidebar

// Check if the user has permission (e.g., admin or receptionist)
if (!in_array($_SESSION['role'], ['admin', 'receptionist', 'doctor'])) { // Adjust roles as needed
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// No need for PHP search logic here anymore, it's handled by AJAX
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><?php echo htmlspecialchars($page_title); ?></h2>
    <!-- Optional: Add a button to view all clients if needed -->
    <!-- <a href="manage_clients.php" class="btn btn-outline-secondary"><i class="fas fa-users me-2"></i>View All Clients</a> -->
</div>

<!-- Search Card -->
<!-- This card contains the search input field for clients -->
<!-- shadow-sm adds a subtle shadow, mb-4 adds margin-bottom spacing -->
<div class="card shadow-sm mb-4">
    <div class="card-header">
        <!-- Font Awesome search icon with right margin (me-2) -->
        <i class="fas fa-search me-2"></i> Find Client by Name or Contact Number
    </div>
    <div class="card-body">
        <!-- input-group combines the search icon and input field together -->
        <div class="input-group">
            <!-- This span creates the search icon container on the left side of the input field -->
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <!-- 
                The main search input field:
                - form-control applies Bootstrap styling
                - form-control-lg makes it larger than default
                - id="searchInput" allows JavaScript to target this element
             -->
            <input type="text" id="searchInput" class="form-control form-control-lg"
                placeholder="Start typing name or contact number...">
        </div>
    </div>
</div>

<!-- Search Results Area -->
<!-- This card displays search results after user types in the search field -->
<div class="card shadow-sm">
    <div class="card-header">
        <!-- Font Awesome list icon with right margin -->
        <i class="fas fa-list me-2"></i> Search Results
    </div>
    <!-- The searchResultsContainer holds both the spinner and results -->
    <div class="card-body" id="searchResultsContainer">
        <!-- Loading Spinner -->
        <!-- This spinner shows while the AJAX request is loading -->
        <!-- style="display: none;" hides it initially until search is triggered -->
        <div id="loadingSpinner" class="text-center my-3" style="display: none;">
            <!-- Bootstrap spinner component with primary (blue) color -->
            <div class="spinner-border text-primary" role="status">
                <!-- Screen readers will read this text, but it's hidden visually -->
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
        <!-- Results List -->
        <!-- This div will be populated with search results by JavaScript -->
        <div id="resultsList">
            <!-- Default message when no search has been performed yet -->
            <p class="text-muted text-center">Enter a name or contact number above to search for clients.</p>
            <!-- Dynamic results will be appended here -->
        </div>
    </div>
</div>

<!-- JavaScript for Live Search -->
<!-- This script powers the real-time searching functionality -->
<script>
    // DOMContentLoaded ensures the HTML is fully loaded before running the script
    document.addEventListener('DOMContentLoaded', function () {
        // Get references to important DOM elements using their IDs
        const searchInput = document.getElementById('searchInput');
        const resultsList = document.getElementById('resultsList');
        const loadingSpinner = document.getElementById('loadingSpinner');
        const resultsContainer = document.getElementById('searchResultsContainer'); // Get the card body
        let searchTimeout;

        searchInput.addEventListener('input', function () {
            const query = searchInput.value.trim();

            // Clear previous timeout
            clearTimeout(searchTimeout);

            if (query.length < 2) { // Only search if query is at least 2 characters
                resultsList.innerHTML = '<p class="text-muted text-center">Enter at least 2 characters to search.</p>';
                loadingSpinner.style.display = 'none';
                return;
            }

            // Set a timeout to wait briefly after user stops typing
            searchTimeout = setTimeout(() => {
                loadingSpinner.style.display = 'block'; // Show loading spinner
                resultsList.innerHTML = ''; // Clear previous results immediately

                fetch(`search_clients.php?query=${encodeURIComponent(query)}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(`HTTP error! status: ${response.status}`);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loadingSpinner.style.display = 'none'; // Hide loading spinner

                        if (!Array.isArray(data)) {
                            throw new Error('Invalid data format received from server.');
                        }

                        if (data.length === 0) {
                            resultsList.innerHTML = '<div class="alert alert-warning text-center">No clients found matching your search.</div>';
                            return;
                        }

                        const listGroup = document.createElement('ul');
                        listGroup.className = 'list-group list-group-flush'; // Use flush for no borders inside card

                        data.forEach(client => {
                            const listItem = document.createElement('li');
                            listItem.className = 'list-group-item d-flex justify-content-between align-items-center';

                            // Highlight search term (simple example)
                            const nameHtml = client.full_name.replace(new RegExp(query, 'gi'), '<strong class="text-primary">$&</strong>');
                            const contactHtml = client.contact_number.replace(new RegExp(query, 'gi'), '<strong class="text-primary">$&</strong>');

                            listItem.innerHTML = `
                            <span>
                                <i class="fas fa-user me-2 text-secondary"></i> ${nameHtml}
                                <small class="text-muted ms-2">(Contact: ${contactHtml})</small>
                            </span>
                            <a href="client_details.php?client_id=${client.id}" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-eye me-1"></i> View Details
                            </a>
                        `;
                            listGroup.appendChild(listItem);
                        });
                        resultsList.appendChild(listGroup);
                    })
                    .catch(error => {
                        loadingSpinner.style.display = 'none'; // Hide loading spinner
                        resultsList.innerHTML = '<div class="alert alert-danger text-center">Error fetching search results. Please try again later.</div>';
                        console.error('Error fetching search results:', error);
                    });
            }, 300); // Wait 300ms after typing stops
        });
    });
</script>

<?php
include '../includes/footer.php'; // Includes closing tags and global scripts
?>