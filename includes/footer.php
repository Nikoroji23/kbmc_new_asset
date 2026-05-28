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

    <script src="assets/js/main.js"></script>
    <script>
        // Setup event delegation for notification items that may be added dynamically
        document.addEventListener('DOMContentLoaded', function () {
            console.log('DOMContentLoaded: Setting up notification handlers');
            
            var notifToggle = document.getElementById('notifToggle');
            var notifDropdown = document.getElementById('notifDropdown');

            if (notifToggle && notifDropdown) {
                console.log('✓ Notification dropdown found');
                
                notifToggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    notifDropdown.classList.toggle('show');
                    console.log('Notification dropdown toggled');
                });

                document.addEventListener('click', function (event) {
                    if (!notifDropdown.contains(event.target) && event.target !== notifToggle) {
                        notifDropdown.classList.remove('show');
                    }
                });

                // Event delegation: handle clicks on dynamically loaded notif-items
                notifDropdown.addEventListener('click', function(event) {
                    var notifItem = event.target.closest('.notif-item');
                    if (notifItem) {
                        console.log('🔔 Dropdown notification clicked');
                        event.stopPropagation();
                        handleNotificationClick(notifItem);
                    }
                });
            }

            // Also handle static notification list items (.notif-clickable)
            var notifClickables = document.querySelectorAll('.notif-clickable');
            console.log('Found', notifClickables.length, 'static notification items');
            notifClickables.forEach(function(item) {
                item.addEventListener('click', function(event) {
                    console.log('🔔 Static notification clicked');
                    if (event.target.closest('a')) return; // Don't intercept links
                    handleNotificationClick(this);
                });
            });
        });
    </script>
<?php ob_end_flush(); ?>
</body>
</html>
