</div> <!-- Closing main-content -->

<!-- jQuery FIRST -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
    integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
    crossorigin="anonymous"></script>
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
    crossorigin="anonymous"></script>
<!-- Select2 JS -->
<!-- Make sure jQuery is loaded BEFORE Select2 -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"
    integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Custom Sidebar Toggle Script -->
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.querySelector('.sidebar-toggle');
        const mainContent = document.querySelector('.main-content');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('sidebar-active'); // Optional: Adjust main content margin
            });
        }

        // Initialize Select2 for elements with the class (if needed globally)
        $('.select2').select2({
            theme: 'bootstrap-5'
        });

        // Initialize Bootstrap Tooltips globally
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // --- Notification Polling ---
        const notificationBadge = document.getElementById('notification-badge');
        const notificationCountSpan = document.getElementById('notification-count');
        const notificationListDiv = document.getElementById('notification-list');
        const pollingInterval = 30000; // Poll every 30 seconds (adjust as needed)

        function fetchNotifications() {
            fetch('../ajax/fetch_notifications.php') // Endpoint to be created
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'success') {
                        updateNotificationUI(data.unread_count, data.notifications);
                    } else {
                        console.error("Error fetching notifications:", data.message);
                        // Optionally display an error indicator?
                    }
                })
                .catch(error => {
                    console.error('Failed to fetch notifications:', error);
                    // Optionally display an error indicator?
                });
        }

        function updateNotificationUI(unreadCount, notifications) {
            // Update badge count
            if (unreadCount > 0) {
                notificationCountSpan.textContent = unreadCount;
                notificationBadge.style.display = 'inline-block';
            } else {
                notificationBadge.style.display = 'none';
            }

            // Update dropdown list
            notificationListDiv.innerHTML = ''; // Clear existing items
            if (notifications && notifications.length > 0) {
                notifications.forEach(noti => {
                    const listItem = document.createElement('li');
                    const link = document.createElement('a');
                    link.className = `dropdown-item notification-item ${!noti.is_read ? 'unread' : ''}`;
                    link.href = noti.link || '#'; // Use link from notification or fallback
                    link.dataset.notificationId = noti.id; // Store ID for marking as read

                    // Basic formatting - customize as needed
                    link.innerHTML = `
                        <div>${noti.message}</div>
                        <div class="small text-muted">${formatTimeAgo(noti.created_at)}</div>
                    `;

                    // Add event listener to mark as read on click (optional)
                    link.addEventListener('click', handleNotificationClick);

                    listItem.appendChild(link);
                    notificationListDiv.appendChild(listItem);
                });
            } else {
                const noNotiItem = document.createElement('li');
                noNotiItem.innerHTML = '<a class="dropdown-item text-muted" href="#">No new notifications</a>';
                notificationListDiv.appendChild(noNotiItem);
            }
        }

        function handleNotificationClick(event) {
            const link = event.currentTarget;
            const notificationId = link.dataset.notificationId;

            // Mark as read via AJAX (only if it's unread)
            if (link.classList.contains('unread')) {
                markNotificationAsRead(notificationId, link);
            }
            // Allow default link behavior unless prevented
            // event.preventDefault(); // Uncomment if you want to handle navigation purely via JS after marking read
        }

        function markNotificationAsRead(id, element) {
            fetch('../ajax/mark_notification_read.php', { // Endpoint to be created
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ notification_id: id })
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        // Visually mark as read immediately
                        if (element) element.classList.remove('unread');
                        // Optionally update the count immediately or wait for next poll
                        fetchNotifications(); // Re-fetch to update count accurately
                    } else {
                        console.error("Failed to mark notification as read:", data.message);
                    }
                })
                .catch(error => console.error("Error marking notification read:", error));
        }

        // Helper function to format time (replace with a more robust library if needed)
        function formatTimeAgo(timestamp) {
            const now = new Date();
            const past = new Date(timestamp.replace(' ', 'T') + 'Z'); // Adjust for UTC if needed, assumes Y-m-d H:i:s format
            const diffInSeconds = Math.floor((now - past) / 1000);
            const diffInMinutes = Math.floor(diffInSeconds / 60);
            const diffInHours = Math.floor(diffInMinutes / 60);
            const diffInDays = Math.floor(diffInHours / 24);

            if (diffInSeconds < 60) return `${diffInSeconds}s ago`;
            if (diffInMinutes < 60) return `${diffInMinutes}m ago`;
            if (diffInHours < 24) return `${diffInHours}h ago`;
            if (diffInDays === 1) return `Yesterday`;
            if (diffInDays < 7) return `${diffInDays}d ago`;
            return past.toLocaleDateString(); // Older than a week, show date
        }


        // Initial fetch and start polling
        fetchNotifications();
        setInterval(fetchNotifications, pollingInterval);

        // --- End Notification Polling ---

    });
</script>

</body>

</html>