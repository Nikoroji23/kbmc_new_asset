/**
 * KBMC Asset Management - Global UI behaviors
 * This file is the primary JavaScript bundle for sidebar and general UI interactions.
 */

document.addEventListener('DOMContentLoaded', function () {
    var menuToggle = document.getElementById('menuToggle');
    var sidebarClose = document.getElementById('sidebarClose');
    var sidebar = document.getElementById('sidebar');

    function toggleSidebar() {
        if (!sidebar) return;
        sidebar.classList.toggle('collapsed');
    }

    if (menuToggle) {
        menuToggle.addEventListener('click', function () {
            toggleSidebar();
        });
    }

    if (sidebarClose) {
        sidebarClose.addEventListener('click', function () {
            if (!sidebar) return;
            sidebar.classList.add('collapsed');
        });
    }

    function applyResponsiveSidebar() {
        if (!sidebar) return;
        if (window.innerWidth <= 900) {
            sidebar.classList.add('collapsed');
        }
    }

    applyResponsiveSidebar();
    window.addEventListener('resize', applyResponsiveSidebar);

    document.querySelectorAll('.delete-confirm').forEach(function (button) {
        button.addEventListener('click', function (event) {
            if (!confirm('Are you sure you want to delete this item?')) {
                event.preventDefault();
            }
        });
    });
});
