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

