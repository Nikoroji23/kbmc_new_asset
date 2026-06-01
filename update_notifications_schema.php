<?php
/**
 * Update notifications table to support additional notification types
 */
require_once 'includes/config.php';

try {
    // Add missing notification types to the ENUM
    $sql = "ALTER TABLE notifications MODIFY COLUMN type ENUM(
        'device_deployed',
        'device_returned',
        'low_stock',
        'repair_needed',
        'request_approved',
        'request_rejected',
        'warranty_expiring',
        'audit_reminder',
        'user_clearance_required',
        'user_clearance_completed',
        'voluntary_return_requested',
        'new_device_added',
        'maintenance_assigned',
        'maintenance_completed',
        'maintenance_due',
        'device_request',
        'lifespan_monitor',
        'lifespan_replace_soon',
        'lifespan_overdue',
        'lifespan_replaced',
        'lifespan_extended'
    ) NOT NULL";
    
    $pdo->exec($sql);
    echo "✓ Notifications table updated successfully!<br>";
    echo "The following notification types are now supported:<br>";
    echo "<ul>";
    echo "<li>device_deployed</li>";
    echo "<li>device_returned</li>";
    echo "<li>low_stock</li>";
    echo "<li>repair_needed</li>";
    echo "<li>request_approved</li>";
    echo "<li>request_rejected</li>";
    echo "<li>warranty_expiring</li>";
    echo "<li>audit_reminder</li>";
    echo "<li>user_clearance_required</li>";
    echo "<li>user_clearance_completed</li>";
    echo "<li>voluntary_return_requested</li>";
    echo "<li>new_device_added</li>";
    echo "<li>maintenance_assigned</li>";
    echo "<li>maintenance_completed</li>";
    echo "<li>maintenance_due</li>";
    echo "<li>device_request</li>";
    echo "<li>lifespan_monitor</li>";
    echo "<li>lifespan_replace_soon</li>";
    echo "<li>lifespan_overdue</li>";
    echo "<li>lifespan_replaced</li>";
    echo "<li>lifespan_extended</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
