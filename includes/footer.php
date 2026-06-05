        </main>

        <!-- Footer -->
        <footer class="app-footer">
            <div class="footer-left">
                <span class="footer-logo">KBMC</span> Device Arrival & Asset Management System
            </div>
            <div class="footer-right">
                &copy; <?php echo date('Y'); ?> Kitchen Beauty Marketing Corporation. All rights reserved.
            </div>
        </footer>
    </div>

    <!-- Global modal for assigned-user details (used when clicking legacy it_user_details.php links) -->
    <div id="globalAssignedUserModal" class="modal-overlay" style="display:none;">
        <div class="modal-box" style="max-width:960px;width:95%;">
            <div class="modal-header" style="display:flex;justify-content:space-between;align-items:center;gap:10px;">
                <h3><i class="fas fa-id-card"></i> Employee Details</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <button type="button" class="btn btn-primary" id="globalAssignedUserPDF" style="display:flex;align-items:center;gap:6px;">
                        <i class="fas fa-file-pdf"></i> PDF
                    </button>
                    <button class="modal-close btn btn-outline" id="globalAssignedUserClose">&times;</button>
                </div>
            </div>
            <div class="modal-body" id="globalAssignedUserBody" style="padding:20px;">
                <p style="text-align:center;color:#999;padding:30px;"><i class="fas fa-spinner fa-spin"></i> Loading...</p>
            </div>
        </div>
    </div>

    <!-- Toast Notification Container (for popup alerts) -->
    <div id="toastContainer" style="
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        max-width: 400px;
        pointer-events: none;
    "></div>

    <script src="assets/js/main.js"></script>
    <script src="assets/js/it_user_modal.js"></script>
    <script>
        /**
         * Toast Notification System
         * Shows popup alerts for real-time events
         */
        function showToastNotification(title, message, type = 'info', duration = 5000, action = null) {
            var container = document.getElementById('toastContainer');
            if (!container) return;

            var toast = document.createElement('div');
            var bgColor, borderColor, icon;
            
            switch(type) {
                case 'success':
                    bgColor = '#d4edda';
                    borderColor = '#28a745';
                    icon = 'check-circle';
                    break;
                case 'error':
                    bgColor = '#f8d7da';
                    borderColor = '#dc3545';
                    icon = 'exclamation-circle';
                    break;
                case 'warning':
                    bgColor = '#fff3cd';
                    borderColor = '#ffc107';
                    icon = 'exclamation-triangle';
                    break;
                case 'danger':
                    bgColor = '#f8d7da';
                    borderColor = '#e74c3c';
                    icon = 'bell';
                    break;
                case 'info':
                default:
                    bgColor = '#d1ecf1';
                    borderColor = '#17a2b8';
                    icon = 'info-circle';
            }

            toast.style.cssText = `
                background: ${bgColor};
                border-left: 4px solid ${borderColor};
                padding: 15px;
                border-radius: 6px;
                margin-bottom: 10px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                pointer-events: auto;
                animation: slideInRight 0.3s ease;
                display: flex;
                align-items: flex-start;
                gap: 12px;
            `;

            var iconEl = document.createElement('i');
            iconEl.className = 'fas fa-' + icon;
            iconEl.style.cssText = 'flex-shrink: 0; margin-top: 2px; font-size: 16px;';

            var contentDiv = document.createElement('div');
            contentDiv.style.cssText = 'flex: 1;';

            var titleEl = document.createElement('strong');
            titleEl.textContent = title;
            titleEl.style.cssText = 'display: block; margin-bottom: 4px;';

            var msgEl = document.createElement('p');
            msgEl.textContent = message;
            msgEl.style.cssText = 'margin: 0; font-size: 13px; color: #555;';

            contentDiv.appendChild(titleEl);
            contentDiv.appendChild(msgEl);

            if (action && action.text && action.url) {
                var actionLink = document.createElement('a');
                actionLink.href = action.url;
                actionLink.textContent = action.text;
                actionLink.style.cssText = 'display: block; margin-top: 8px; color: ' + borderColor + '; font-weight: 600; text-decoration: none;';
                contentDiv.appendChild(actionLink);
            }

            toast.appendChild(iconEl);
            toast.appendChild(contentDiv);

            container.appendChild(toast);

            // Auto-remove after duration
            if (duration > 0) {
                setTimeout(function() {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(function() {
                        container.removeChild(toast);
                    }, 300);
                }, duration);
            }
        }

        /**
         * Poll for new notifications every 15 seconds
         */
        function pollForNotifications() {
            fetch('get_new_notifications.php', {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success && data.notifications && data.notifications.length > 0) {
                    data.notifications.forEach(function(notif) {
                        // Get notification icon and color based on type
                        var title = notif.title || 'Notification';
                        var message = notif.message || 'You have a new notification';
                        var type = 'info';

                        // Determine notification type for styling
                        if (notif.type.includes('approval') || notif.type.includes('it_user')) {
                            type = 'warning';
                        } else if (notif.type.includes('success')) {
                            type = 'success';
                        } else if (notif.type.includes('error') || notif.type.includes('reject')) {
                            type = 'error';
                        }

                        // Get URL for the notification action
                        var actionUrl = '';
                        if (notif.type === 'it_user_pending_approval') {
                            actionUrl = 'approve_it_users.php';
                        }

                        showToastNotification(
                            title,
                            message,
                            type,
                            6000,
                            actionUrl ? { text: 'View Now', url: actionUrl } : null
                        );
                    });
                }
            })
            .catch(function(error) {
                console.log('Notification poll error:', error);
            });
        }

        // Start polling for notifications when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                pollForNotifications();
                setInterval(pollForNotifications, 15000); // Poll every 15 seconds
            });
        } else {
            pollForNotifications();
            setInterval(pollForNotifications, 15000);
        }

        // CSS animations for toast
        var style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from {
                    opacity: 0;
                    transform: translateX(100px);
                }
                to {
                    opacity: 1;
                    transform: translateX(0);
                }
            }
            @keyframes slideOutRight {
                from {
                    opacity: 1;
                    transform: translateX(0);
                }
                to {
                    opacity: 0;
                    transform: translateX(100px);
                }
            }
            #toastContainer {
                pointer-events: none;
            }
            #toastContainer > * {
                pointer-events: auto;
            }
        `;
        document.head.appendChild(style);
    </script>
    <script>
        // Setup notification handlers with guaranteed timing
        function setupNotificationHandlers() {
            var notifToggle = document.getElementById('notifToggle');
            var notifDropdown = document.getElementById('notifDropdown');

            if (notifToggle && notifDropdown) {
                // Toggle dropdown on bell button click
                notifToggle.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    notifDropdown.classList.toggle('show');
                });

                // Close dropdown when clicking outside
                document.addEventListener('click', function (event) {
                    if (!notifDropdown.contains(event.target) && event.target !== notifToggle && !notifToggle.contains(event.target)) {
                        notifDropdown.classList.remove('show');
                    }
                });

                // Handle clicks on notification items in the dropdown
                notifDropdown.addEventListener('click', function(event) {
                    var notifItem = event.target.closest('.notif-item');
                    if (notifItem) {
                        event.stopPropagation();
                        handleNotificationClick(notifItem);
                    }
                });
            }

            // Also handle static notification list items (.notif-clickable)
            var notifClickables = document.querySelectorAll('.notif-clickable');
            notifClickables.forEach(function(item) {
                item.addEventListener('click', function(event) {
                    if (event.target.closest('a')) return;
                    handleNotificationClick(this);
                });
            });
        }

        // Run immediately if DOM is ready, otherwise wait
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', setupNotificationHandlers);
        } else {
            setupNotificationHandlers();
        }
    </script>
<?php ob_end_flush(); ?>
</body>
</html>
