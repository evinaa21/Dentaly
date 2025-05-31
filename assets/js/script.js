$(document).ready(function () {
    $('#loginForm').on('submit', function (e) {
        e.preventDefault();

        var email = $('#email').val();
        var password = $('#password').val();
        var errorMsg = $('#error-msg');

        errorMsg.hide().text('');

        $.ajax({
            type: 'POST',
            url: 'auth/login_process.php',
            data: { email: email, password: password },
            success: function (response) {
                if (response === 'admin') {
                    window.location.href = './admin/index.php';
                } else if (response === 'doctor') {
                    window.location.href = './doctors/index.php';
                } else if (response === 'receptionist') {
                    window.location.href = './receptionist/index.php';
                } else {
                    errorMsg.show().text(response);
                }
            },
            error: function () {
                errorMsg.show().text('Something went wrong, please try again.');
            }
        });
    });
});

const searchInput = document.getElementById('searchInput');
const resultsList = document.getElementById('resultsList');
const searchResults = document.getElementById('searchResults');
const loadingSpinner = document.getElementById('loadingSpinner');

searchInput.addEventListener('input', function () {
    const query = searchInput.value.trim();

    if (query.length === 0) {
        resultsList.innerHTML = '';
        searchResults.style.display = 'none';
        return;
    }

    // Show loading spinner
    loadingSpinner.style.display = 'block';

    // Fetch results dynamically
    fetch(`search_clients.php?query=${encodeURIComponent(query)}`)
        .then(response => response.json())
        .then(data => {
            // Hide loading spinner
            loadingSpinner.style.display = 'none';

            // Clear previous results
            resultsList.innerHTML = '';

            if (data.length > 0) {
                data.forEach(client => {
                    const card = document.createElement('div');
                    card.className = 'col-md-4 mb-3';
                    card.innerHTML = `
                                <div class="card shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">${highlightMatch(client.full_name, query)}</h5>
                                        <p class="card-text">
                                            <i class="fas fa-phone-alt text-primary"></i> ${highlightMatch(client.contact_number, query)}
                                        </p>
                                        <a href="client_details.php?client_id=${client.id}" class="btn btn-primary btn-sm">View History</a>
                                    </div>
                                </div>
                            `;
                    resultsList.appendChild(card);
                });
                searchResults.style.display = 'block';
            } else {
                resultsList.innerHTML = '<div class="col-12 text-center">No clients found.</div>';
            }
        })
        .catch(error => {
            console.error('Error fetching search results:', error);
            loadingSpinner.style.display = 'none';
        });
});

// Highlight matching text
function highlightMatch(text, query) {
    const regex = new RegExp(`(${query})`, 'gi');
    return text.replace(regex, '<span class="highlight">$1</span>');
}

// Enable Bootstrap tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Show modal when "Add New Patient" is selected in a dropdown with id="patient_id"
const patientIdDropdown = document.getElementById('patient_id');
if (patientIdDropdown) { // Check if the element actually exists
    patientIdDropdown.addEventListener('change', function () {
        if (this.value === 'new') {
            const newPatientModalElement = document.getElementById('newPatientModal');
            if (newPatientModalElement) { // Check if the modal element exists
                var newPatientModal = new bootstrap.Modal(newPatientModalElement);
                newPatientModal.show();
            }
            this.value = ''; // Reset dropdown selection
        }
    });
}

// Global JavaScript utilities
document.addEventListener('DOMContentLoaded', function () {
    // Sidebar Toggle for mobile (if not handled by Bootstrap's JS directly for your specific toggle button)
    const sidebarToggle = document.querySelector('.sidebar-toggle'); // Use a class that you put on your toggle button
    const sidebar = document.querySelector('.sidebar'); // Use a class that is on your sidebar element
    const body = document.body;

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show'); // 'show' class to display sidebar
            body.classList.toggle('sidebar-open'); // Optional: for overlay or content adjustments
        });
    }

    // Close sidebar if clicking on overlay (if you implement an overlay)
    body.addEventListener('click', function(event) {
        if (body.classList.contains('sidebar-open') && !sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
            sidebar.classList.remove('show');
            body.classList.remove('sidebar-open');
        }
    });


    // Initialize Bootstrap Tooltips everywhere
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-dismiss alerts after a few seconds
    const autoDismissAlerts = document.querySelectorAll('.alert-dismissible[data-auto-dismiss]');
    autoDismissAlerts.forEach(alert => {
        const delay = parseInt(alert.getAttribute('data-auto-dismiss'), 10) || 5000;
        setTimeout(() => {
            new bootstrap.Alert(alert).close();
        }, delay);
    });



    // Initialize Select2 on all select elements with the 'select2' class
    $('.select2').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // Auto-dismiss alerts with fade effect
    const alertsWithAutoDismiss = document.querySelectorAll('.alert[data-auto-dismiss]');
    alertsWithAutoDismiss.forEach(alert => {
        const delay = parseInt(alert.getAttribute('data-auto-dismiss'));
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            } else {
                alert.remove();
            }
        }, delay);
    });

    // General confirm dialog for delete actions
    const confirmDeleteButtons = document.querySelectorAll('.confirm-delete');
    confirmDeleteButtons.forEach(button => {
        button.addEventListener('click', function(event) {
            const message = this.dataset.message || 'Are you sure you want to delete this item? This action cannot be undone.';
            if (!confirm(message)) {
                event.preventDefault();
            }
        });
    });
});

// Function to dynamically show a Bootstrap alert
// type: 'success', 'danger', 'warning', 'info'
// message: The message to display
// containerSelector: CSS selector for the container where the alert should be appended
function showAlert(type, message, containerSelector = '.main-content', autoDismissDelay = 5000) {
    const container = document.querySelector(containerSelector);
    if (!container) {
        console.error('Alert container not found:', containerSelector);
        return;
    }

    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.setAttribute('role', 'alert');
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

    // Prepend to container or append, based on preference
    if (container.firstChild) {
        container.insertBefore(alertDiv, container.firstChild);
    } else {
        container.appendChild(alertDiv);
    }


    if (autoDismissDelay > 0) {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getInstance(alertDiv);
            if (bsAlert) {
                bsAlert.close();
            } else {
                // Fallback if instance not found (e.g., element removed by other means)
                alertDiv.remove();
            }
        }, autoDismissDelay);
    }
}

