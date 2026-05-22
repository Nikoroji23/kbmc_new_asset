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
        document.addEventListener('DOMContentLoaded', function () {
            var notifToggle = document.getElementById('notifToggle');
            var notifDropdown = document.getElementById('notifDropdown');

            if (notifToggle && notifDropdown) {
                notifToggle.addEventListener('click', function (event) {
                    event.stopPropagation();
                    notifDropdown.classList.toggle('show');
                });

                document.addEventListener('click', function (event) {
                    if (!notifDropdown.contains(event.target) && event.target !== notifToggle) {
                        notifDropdown.classList.remove('show');
                    }
                });

                notifDropdown.querySelectorAll('.notif-item').forEach(function (item) {
                    item.addEventListener('click', function () {
                        var id = this.dataset.id;
                        var url = this.dataset.url || 'notifications.php';
                        if (id) {
                            fetch('mark_notification_read.php?id=' + encodeURIComponent(id), {
                                headers: { 'X-Requested-With': 'XMLHttpRequest' }
                            }).finally(function () {
                                window.location.href = url;
                            });
                        } else {
                            window.location.href = url;
                        }
                    });
                });
            }
        });

        function handleNotificationClick(element) {
            var id = element.dataset.id;
            var url = element.dataset.url || 'requests.php';
            if (id) {
                fetch('mark_notification_read.php?id=' + encodeURIComponent(id), {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).finally(function () {
                    window.location.href = url;
                });
            } else {
                window.location.href = url;
            }
        }
    </script>
<?php ob_end_flush(); ?>
</body>
</html>
