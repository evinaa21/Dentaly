<?php
// Define page title *before* including header
$page_title = 'Search Patient History';
include '../includes/header.php'; // Include the shared header with the sidebar

// Check if the user is actually a doctor
if ($_SESSION['role'] !== 'doctor') {
    echo '<div class="alert alert-danger m-3">Access Denied. You do not have permission to view this page.</div>';
    include '../includes/footer.php';
    exit;
}

// No need to fetch patients here anymore, AJAX will handle it.
?>

<!-- Page Title -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="m-0"><i class="fas fa-user-clock me-2 text-info"></i> Search Patient History</h2>
</div>

<!-- Search Input -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-search me-2"></i> Find Patient</h5>
    </div>
    <div class="card-body">
        <div class="input-group">
            <span class="input-group-text"><i class="fas fa-search"></i></span>
            <input type="text" id="patientSearchInput" class="form-control form-control-lg"
                placeholder="Start typing patient name or contact number...">
        </div>
        <div id="search-spinner" class="text-center mt-2" style="display: none;">
            <div class="spinner-border spinner-border-sm text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>
</div>

<!-- Search Results Area -->
<div class="card shadow-sm">
    <div class="card-header bg-light">
        <h5 class="mb-0"><i class="fas fa-list-alt me-2"></i> Results</h5>
    </div>
    <div class="card-body">
        <ul id="patientResultsList" class="list-group">
            <li id="results-placeholder" class="list-group-item text-muted">Enter a search term above to find patients
                associated with you.</li>
            <!-- Results will be loaded here by JavaScript -->
        </ul>
    </div>
</div>


<!-- JavaScript for AJAX Search -->
<script>
    const searchInput = document.getElementById('patientSearchInput');
    const resultsList = document.getElementById('patientResultsList');
    const placeholder = document.getElementById('results-placeholder');
    const spinner = document.getElementById('search-spinner');
    let searchTimeout; // To debounce requests

    searchInput.addEventListener('input', function () {
        const query = this.value.trim();

        // Clear previous timeout
        clearTimeout(searchTimeout);

        // Clear results immediately if query is short
        if (query.length < 2) { // Minimum characters to trigger search
            resultsList.innerHTML = ''; // Clear previous results
            resultsList.appendChild(placeholder); // Show placeholder
            placeholder.textContent = 'Please enter at least 2 characters.';
            spinner.style.display = 'none';
            return;
        }

        placeholder.textContent = 'Searching...'; // Update placeholder text
        spinner.style.display = 'block'; // Show spinner

        // Set a new timeout to wait briefly after user stops typing
        searchTimeout = setTimeout(() => {
            fetch(`../ajax/search_doctor_patients.php?query=${encodeURIComponent(query)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(result => {
                    resultsList.innerHTML = ''; // Clear previous results/placeholder
                    spinner.style.display = 'none'; // Hide spinner

                    if (result.status === 'success' && result.data.length > 0) {
                        result.data.forEach(patient => {
                            const li = document.createElement('li');
                            li.className = 'list-group-item d-flex justify-content-between align-items-center';

                            // Patient Name and Contact
                            const patientInfo = document.createElement('span');
                            patientInfo.textContent = `${patient.first_name} ${patient.last_name} (${patient.contact_number || 'N/A'})`;

                            // View History Button
                            const viewButton = document.createElement('a');
                            viewButton.href = `patient_details.php?patient_id=${patient.id}`; // Link to the details page
                            viewButton.className = 'btn btn-sm btn-outline-primary';
                            viewButton.innerHTML = '<i class="fas fa-eye me-1"></i> View History';

                            li.appendChild(patientInfo);
                            li.appendChild(viewButton);
                            resultsList.appendChild(li);
                        });
                    } else if (result.status === 'success' && result.data.length === 0) {
                        const li = document.createElement('li');
                        li.className = 'list-group-item text-warning';
                        li.textContent = `No patients found matching "${query}" associated with you.`;
                        resultsList.appendChild(li);
                    } else {
                        // Handle errors reported by the AJAX script itself
                        const li = document.createElement('li');
                        li.className = 'list-group-item text-danger';
                        li.textContent = `Search Error: ${result.message || 'Unknown error'}`;
                        resultsList.appendChild(li);
                    }
                })
                .catch(error => {
                    console.error('Fetch Error:', error);
                    resultsList.innerHTML = ''; // Clear previous results
                    spinner.style.display = 'none'; // Hide spinner
                    const li = document.createElement('li');
                    li.className = 'list-group-item text-danger';
                    li.textContent = 'Error communicating with the server. Please try again.';
                    resultsList.appendChild(li);
                });
        }, 300); // Debounce time in milliseconds (e.g., 300ms)
    });
</script>

<?php
include '../includes/footer.php'; // Include the shared footer
?>