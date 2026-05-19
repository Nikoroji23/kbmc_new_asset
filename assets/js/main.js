/**
 * KBMC Asset Management - Main JavaScript
 * Fixed: PDF Export, Notifications, Mobile Sidebar
 */

document.addEventListener('DOMContentLoaded', function() {
    // ============================================================
    // SIDEBAR TOGGLE (Mobile)
    // ============================================================
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    const sidebarClose = document.getElementById('sidebarClose');

    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    if (sidebarClose && sidebar) {
        sidebarClose.addEventListener('click', () => {
            sidebar.classList.remove('show');
        });
    }

    // ============================================================
    // NOTIFICATION DROPDOWN
    // ============================================================
    const notifToggle = document.getElementById('notifToggle');
    const notifDropdown = document.getElementById('notifDropdown');

    if (notifToggle && notifDropdown) {
        notifToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            notifDropdown.classList.toggle('show');
        });

        document.addEventListener('click', (e) => {
            if (!notifDropdown.contains(e.target) && e.target !== notifToggle) {
                notifDropdown.classList.remove('show');
            }
        });
    }

    // ============================================================
    // NOTIFICATION ITEM CLICK HANDLER (Header Dropdown)
    // ============================================================
    document.querySelectorAll('.notif-item').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            const notifId = this.dataset.id;
            const notifUrl = this.dataset.url;

            if (notifId) {
                // Mark as read via AJAX
                fetch('mark_notification_read.php?id=' + notifId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Remove unread styling
                            this.classList.remove('unread');

                            // Update badge count
                            const badge = document.querySelector('.notif-badge');
                            if (badge) {
                                const count = parseInt(badge.textContent) - 1;
                                if (count <= 0) {
                                    badge.remove();
                                } else {
                                    badge.textContent = count;
                                }
                            }

                            // Navigate to the relevant page
                            if (notifUrl && notifUrl !== '#') {
                                window.location.href = notifUrl;
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Notification error:', err);
                        // Still navigate even if marking as read fails
                        if (notifUrl && notifUrl !== '#') {
                            window.location.href = notifUrl;
                        }
                    });
            }
        });
    });

    // ============================================================
    // AUTO-HIDE FLASH ALERT
    // ============================================================
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(() => {
            flashAlert.style.transition = 'opacity 0.5s ease';
            flashAlert.style.opacity = '0';
            setTimeout(() => flashAlert.remove(), 500);
        }, 5000);
    }

    // ============================================================
    // TABS
    // ============================================================
    document.querySelectorAll('.tabs').forEach(tabContainer => {
        const tabBtns = tabContainer.querySelectorAll('.tab-btn');
        const tabContents = tabContainer.parentElement.querySelectorAll('.tab-content');

        tabBtns.forEach((btn, index) => {
            btn.addEventListener('click', () => {
                tabBtns.forEach(b => b.classList.remove('active'));
                tabContents.forEach(c => c.classList.remove('active'));
                btn.classList.add('active');
                if (tabContents[index]) tabContents[index].classList.add('active');
            });
        });
    });

    // ============================================================
    // MODAL TRIGGERS
    // ============================================================
    document.querySelectorAll('[data-modal]').forEach(trigger => {
        trigger.addEventListener('click', () => {
            const modalId = trigger.dataset.modal;
            const modal = document.getElementById(modalId);
            if (modal) modal.classList.add('show');
        });
    });

    document.querySelectorAll('.modal-close, .modal-overlay').forEach(el => {
        el.addEventListener('click', (e) => {
            if (e.target === el || el.classList.contains('modal-close')) {
                el.closest('.modal-overlay').classList.remove('show');
            }
        });
    });

    // ============================================================
    // DELETE CONFIRMATION
    // ============================================================
    document.querySelectorAll('.delete-confirm').forEach(btn => {
        btn.addEventListener('click', (e) => {
            if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                e.preventDefault();
            }
        });
    });

    // ============================================================
    // PRINT FUNCTIONALITY
    // ============================================================
    document.querySelectorAll('.btn-print').forEach(btn => {
        btn.addEventListener('click', () => window.print());
    });
});

// ============================================================
// CSV EXPORT HELPER (Global)
// ============================================================
window.exportToCSV = function(filename, headers, rows) {
    const csv = [headers, ...rows].map(row =>
        row.map(cell => `"${(cell || '').toString().replace(/"/g, '""')}"`).join(',')
    ).join('\n');

    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(link.href);
};

// ============================================================
// PDF EXPORT HELPER (Global) - FIXED VERSION
// ============================================================
window.exportToPDF = function(title, headers, rows, filename) {
    // Check if jsPDF is available
    if (typeof window.jspdf === 'undefined' || typeof window.jspdf.jsPDF === 'undefined') {
        console.error('jsPDF library not loaded. Please check your internet connection or CDN links.');
        alert('PDF export failed. The PDF library is not available. Please check your internet connection.');
        return;
    }

    try {
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('p', 'mm', 'a4');

        // Add title
        doc.setFontSize(18);
        doc.setTextColor(217, 35, 46); // KBMC Red
        doc.text('KBMC Asset Management', 14, 20);

        doc.setFontSize(14);
        doc.setTextColor(44, 62, 80); // Dark blue
        doc.text(title, 14, 30);

        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text('Generated on: ' + new Date().toLocaleDateString(), 14, 38);

        // Check if autoTable plugin is available
        if (typeof doc.autoTable === 'function') {
            doc.autoTable({
                head: [headers],
                body: rows,
                startY: 45,
                theme: 'grid',
                styles: { 
                    fontSize: 9, 
                    cellPadding: 2,
                    font: 'helvetica'
                },
                headStyles: { 
                    fillColor: [217, 35, 46], 
                    textColor: 255,
                    fontStyle: 'bold'
                },
                alternateRowStyles: { 
                    fillColor: [254, 248, 248] 
                },
                margin: { top: 45, left: 14, right: 14 }
            });
        } else {
            // Fallback: manual table drawing if autoTable not loaded
            console.warn('autoTable plugin not available, using fallback');
            let y = 45;
            const colWidth = 180 / headers.length;

            // Draw headers
            doc.setFillColor(217, 35, 46);
            doc.setTextColor(255, 255, 255);
            doc.setFontSize(9);
            headers.forEach((header, i) => {
                doc.rect(14 + i * colWidth, y, colWidth, 8, 'F');
                doc.text(String(header), 16 + i * colWidth, y + 5);
            });
            y += 8;

            // Draw rows
            doc.setTextColor(0, 0, 0);
            rows.forEach(row => {
                row.forEach((cell, i) => {
                    doc.rect(14 + i * colWidth, y, colWidth, 7);
                    doc.text(String(cell || '').substring(0, 20), 16 + i * colWidth, y + 5);
                });
                y += 7;
            });
        }

        // Add footer
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(128, 128, 128);
            doc.text(
                `Page ${i} of ${pageCount} | KBMC Asset Management System`,
                doc.internal.pageSize.getWidth() / 2,
                doc.internal.pageSize.getHeight() - 10,
                { align: 'center' }
            );
        }

        doc.save(filename);
    } catch (error) {
        console.error('PDF Export Error:', error);
        alert('PDF export failed: ' + error.message);
    }
};

// ============================================================
// NOTIFICATION CLICK HANDLER (Global - for notifications.php page)
// ============================================================
window.handleNotificationClick = function(element) {
    const notifId = element.dataset.id;
    const notifUrl = element.dataset.url;

    if (notifId) {
        fetch('mark_notification_read.php?id=' + notifId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.classList.remove('unread');
                    element.style.background = 'transparent';

                    const badge = document.querySelector('.notif-badge');
                    if (badge) {
                        const count = parseInt(badge.textContent) - 1;
                        if (count <= 0) badge.remove();
                        else badge.textContent = count;
                    }

                    // Navigate to the relevant page
                    if (notifUrl && notifUrl !== '#') {
                        window.location.href = notifUrl;
                    }
                }
            })
            .catch(err => {
                console.error('Notification error:', err);
                // Still navigate even if marking as read fails
                if (notifUrl && notifUrl !== '#') {
                    window.location.href = notifUrl;
                }
            });
    }
};

// ============================================================
// SIDEBAR TOGGLE FOR DESKTOP
// ============================================================
window.toggleSidebar = function() {
    const sidebar = document.getElementById('sidebar');
    const wrapper = document.querySelector('.main-wrapper');
    sidebar.classList.toggle('collapsed');
    if (sidebar.classList.contains('collapsed')) {
        wrapper.style.marginLeft = '70px';
    } else {
        wrapper.style.marginLeft = '280px';
    }
};

// ============================================================
// REAL-TIME NOTIFICATION POPUP SYSTEM
// ============================================================
let lastNotificationCheck = null;

window.showNotificationToast = function(title, message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        console.warn('Toast container not found');
        return;
    }

    const toast = document.createElement('div');
    const toastId = 'toast-' + Date.now();
    toast.id = toastId;
    
    const bgColor = type === 'device_request' ? '#e8f5e9' : 
                   type === 'approved' ? '#c8e6c9' : 
                   type === 'rejected' ? '#ffcdd2' : '#e3f2fd';
    const borderColor = type === 'device_request' ? '#4caf50' : 
                       type === 'approved' ? '#4caf50' : 
                       type === 'rejected' ? '#f44336' : '#2196f3';
    const icon = type === 'device_request' ? 'hand-paper' : 
                type === 'approved' ? 'check-circle' : 
                type === 'rejected' ? 'times-circle' : 'info-circle';
    
    toast.innerHTML = `
        <div style="
            background: ${bgColor};
            border-left: 4px solid ${borderColor};
            padding: 16px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 10px;
            animation: slideIn 0.3s ease-out;
            max-width: 400px;
        ">
            <i class="fas fa-${icon}" style="
                color: ${borderColor};
                font-size: 20px;
                margin-top: 2px;
                flex-shrink: 0;
            "></i>
            <div style="flex: 1;">
                <div style="font-weight: 600; color: #333; margin-bottom: 4px; font-size: 14px;">
                    ${title}
                </div>
                <div style="color: #666; font-size: 13px; line-height: 1.4;">
                    ${message}
                </div>
            </div>
            <button onclick="document.getElementById('${toastId}').remove()" style="
                background: none;
                border: none;
                color: #999;
                cursor: pointer;
                font-size: 18px;
                padding: 0;
                margin: -2px -4px 0 0;
                flex-shrink: 0;
            ">×</button>
        </div>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto-remove after 6 seconds
    setTimeout(() => {
        if (document.getElementById(toastId)) {
            toast.style.animation = 'slideOut 0.3s ease-in';
            setTimeout(() => toast.remove(), 300);
        }
    }, 6000);
};

window.pollForNotifications = function() {
    // Only poll if not on login/logout pages
    if (document.body.classList.contains('login-page') || 
        document.body.classList.contains('logout-page')) {
        return;
    }

    fetch('get_new_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(notif => {
                    // Determine type for styling
                    let toastType = 'info';
                    if (notif.type === 'device_request') toastType = 'device_request';
                    else if (notif.type === 'request_approved') toastType = 'approved';
                    else if (notif.type === 'request_rejected') toastType = 'rejected';
                    
                    window.showNotificationToast(notif.title, notif.message, toastType);
                });

                // Update notification badge
                if (data.unread_count > 0) {
                    const badge = document.querySelector('.notif-badge');
                    if (badge) {
                        badge.textContent = data.unread_count;
                    } else {
                        const notifBell = document.getElementById('notifToggle');
                        if (notifBell && !notifBell.querySelector('.notif-badge')) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'notif-badge';
                            newBadge.textContent = data.unread_count;
                            notifBell.appendChild(newBadge);
                        }
                    }
                }
            }
        })
        .catch(err => console.log('Notification poll error:', err));
};

// Start polling for notifications every 10 seconds
document.addEventListener('DOMContentLoaded', function() {
    // Check for notifications on page load
    window.pollForNotifications();
    
    // Poll every 10 seconds
    setInterval(window.pollForNotifications, 10000);
});

// ============================================================
// TOAST CONTAINER SETUP (if not exists)
// ============================================================
if (!document.getElementById('toastContainer')) {
    const toastContainer = document.createElement('div');
    toastContainer.id = 'toastContainer';
    toastContainer.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        max-width: 500px;
    `;
    document.body.appendChild(toastContainer);
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(400px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    @keyframes slideOut {
        from {
            opacity: 1;
            transform: translateX(0);
        }
        to {
            opacity: 0;
            transform: translateX(400px);
        }
    }
`;
document.head.appendChild(style);