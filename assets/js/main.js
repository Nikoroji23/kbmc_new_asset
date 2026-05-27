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

    // Simple modal system: open by [data-modal] and close by [data-dismiss="modal"] or clicking overlay
    function openModalById(id) {
        if (!id) return;
        var modal = document.getElementById(id);
        if (!modal) return;
        modal.classList.add('show');
        modal.style.display = 'flex';
    }

    function closeModalElement(el) {
        if (!el) return;
        var modal = el.closest('.modal-overlay');
        if (!modal) return;
        modal.classList.remove('show');
        modal.style.display = 'none';
    }

    // Attach openers
    document.querySelectorAll('[data-modal]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            var target = btn.getAttribute('data-modal');
            openModalById(target);
        });
    });

    // Attach dismiss buttons
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            closeModalElement(btn);
        });
    });

    // Close when clicking overlay background
    document.querySelectorAll('.modal-overlay').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.classList.remove('show');
                modal.style.display = 'none';
            }
        });
    });

    // Expose helper for inline calls from templates
    window.closeViewUserModal = function () { closeModalElement(document.getElementById('viewUserModal') || null); };
});
