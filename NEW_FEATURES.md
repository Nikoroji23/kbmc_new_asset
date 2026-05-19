# KBMC Asset Management - New Features Implementation
**Date: May 19, 2026**

## Overview
Four new features have been implemented to enhance asset tracking, maintenance management, and user experience:

1. **Email Notifications — send reminders for pending repairs and maintenance**
2. **Asset Serial Number Tracking — store and search by manufacturer serial numbers**
3. **Color-Coded Dashboard — visual status indicators (green=good, yellow=maintenance needed, red=failed)**
4. **User Asset Dashboard — show employees what assets are assigned to them**

---

## Implementation Details

### 1. Email Notifications for Maintenance & Repairs

**Database Tables Created:**
- `maintenance_schedules` — tracks preventive/corrective maintenance schedules
- `email_notifications` — queue system for sending reminder emails
- `maintenance_reminders_sent` — audit trail of sent reminders

**Files Added:**
- `maintenance_reminders.php` — IT/Admin page to schedule and manage maintenance
- `api_send_maintenance_reminder.php` — API endpoint to send maintenance reminder emails
- `feature_updates.sql` — SQL schema for new tables

**Features:**
- Schedule preventive maintenance for devices
- Set reminders for upcoming maintenance (7, 14, 30 days configurable)
- Send email reminders to assigned IT staff
- Mark maintenance as completed and auto-schedule next due date (6 months from now)
- Track which reminders have been sent

**How to Use:**
1. Go to **Maintenance** → **Add Maintenance Schedule**
2. Select device, maintenance type, and due date
3. Assign to IT staff member (optional)
4. System will alert when due in next 7 days
5. Click "Send Reminder" to email the assigned staff
6. Mark as "Completed" when done

**Email Content:**
- Device asset tag and model
- Maintenance type and description
- Due date
- Link to device details
- Customizable via `emailTemplate()` function

---

### 2. Asset Serial Number Tracking

**Database Enhancements:**
- Added index on `devices.serial_number` for faster searches
- Added index on `devices.status` and `devices.location` for multi-field queries

**Files Added:**
- `device_search.php` — search interface for finding devices by serial number
- New functions in `includes/functions.php`:
  - `searchDeviceBySerialNumber($serialNumber)` — single device lookup
  - `searchDevicesBySerialOrAsset($searchTerm)` — multi-field search (20 results max)

**Features:**
- Search by serial number, asset tag, brand, or model
- Instant results with device status and assignment
- Shows current user assignment
- Link to full device details

**How to Use:**
1. Go to **Tools & Search** → **Search Devices**
2. Enter serial number, asset tag, brand, or model
3. Results display with status, assignment, and location
4. Click "Details" to view full device history

---

### 3. Color-Coded Dashboard

**Database Table Created:**
- `device_status_colors` — maps device status to color, icon, and display label

**UI Enhancements:**
- Dashboard shows color-coded asset status overview with visual bars
- Each status (in_stock, deployed, under_repair, etc.) has:
  - Color indicator
  - Icon
  - Count and percentage
  - Progress bar
- Status badges throughout the app (green for good, orange for repair, red for issues)

**Color Scheme:**
- 🟢 **In Stock** (#27ae60) — Available inventory
- 🔵 **Deployed** (#3498db) — Assigned to users
- 🟡 **Under Repair** (#f39c12) — Being serviced
- 🟠 **Pending Inspection** (#e67e22) — Awaiting QA
- ⚫ **Retired** (#95a5a6) — End-of-life
- 🔴 **Disposed** (#7f8c8d) — Discarded
- ❌ **Rejected** (#e74c3c) — Failed inspection

**Files Updated:**
- `dashboard.php` — Added color-coded asset status overview section
- `includes/functions.php`:
  - `getStatusColor($status)` — returns color code, icon, label
  - `getStatusBadgeHtml($status)` — generates HTML badge

**How to Use:**
- Dashboard automatically displays all statuses with color coding
- Click on device to see status badge with icon
- Hover over status bar for percentage breakdown

---

### 4. User Asset Dashboard

**Database Table Created:**
- `user_preferences` — user dashboard settings and notification preferences

**Files Added:**
- `user_asset_dashboard.php` — Employee view of assigned devices
- `api_report_device_issue.php` — API endpoint for issue reporting
- New functions in `includes/functions.php`:
  - `getEmployeeAssignedDevices($employeeId)` — gets active devices
  - `getEmployeeDeviceStats($employeeId)` — device count summary
  - `getDeviceAssignmentHistory($deviceId)` — assignment audit trail

**Features:**
- **Dashboard Stats Cards:**
  - Total devices assigned
  - Active & functional devices
  - Devices under repair
  - Pending repairs

- **Device List:**
  - All assigned devices with status
  - Maintenance schedule status (on schedule vs. overdue)
  - Pending repairs count per device
  - Quick actions: View details, Report issue

- **Report Device Issue Modal:**
  - Issue description (required)
  - Severity level (low, medium, high, critical)
  - Optional file attachment (images/PDF, max 5MB)
  - Automatically creates repair ticket
  - Notifies IT staff via email + system notification
  - Escalates critical issues to high priority

**How to Use:**
1. Go to **Tools & Search** → **My Devices**
2. View all devices assigned to you
3. Click "Report Issue" button to report a problem
4. Fill in issue description and severity
5. Optionally attach evidence (screenshot, photo)
6. Click "Report Issue"
7. IT team receives notification and will follow up

**Notifications Generated:**
- User gets confirmation message
- IT staff gets email + system notification with device details
- Repair record created automatically in the system

---

## Database Setup

**Run the SQL schema:**
```sql
-- Import feature_updates.sql in phpMyAdmin or CLI:
mysql -u root -p kbmc_asset_db < feature_updates.sql
```

**Tables Created:**
1. `maintenance_schedules` — maintenance task scheduling
2. `email_notifications` — email queue and history
3. `maintenance_reminders_sent` — audit trail
4. `user_preferences` — user settings
5. `device_status_colors` — status to color mapping

---

## Navigation Updates

New menu items added to sidebar:
- **Tools & Search** section:
  - Search Devices (all users)
  - My Devices (all users)
  - Maintenance (IT/Admin only)

Badge indicators:
- Maintenance badge shows count of tasks due in next 7 days
- Red highlight if any are overdue

---

## API Endpoints

### Send Maintenance Reminder
- **Endpoint:** `POST api_send_maintenance_reminder.php`
- **Auth:** IT Staff or Admin
- **Payload:** `{maintenance_id: <id>}`
- **Returns:** `{success: true, message: "Email sent to..."}`

### Report Device Issue
- **Endpoint:** `POST api_report_device_issue.php`
- **Auth:** Logged in employee
- **Fields:**
  - `device_id` (required)
  - `issue_description` (required)
  - `severity` (low/medium/high/critical)
  - `attachment` (optional, max 5MB)
- **Returns:** `{success: true, message: "Issue reported..."}`

---

## Email Integration

**For email reminders to work:**
1. Configure `includes/PHPMailer/email_config.php` with SMTP credentials
2. Set `BASE_URL` in `includes/config.php` for reset links in emails
3. Ensure `isEmailConfigured()` returns true
4. Call `sendPendingEmailNotifications()` from a cron job or scheduled task

**Cron Job Example (every 5 minutes):**
```bash
*/5 * * * * php /path/to/send_maintenance_reminders_cron.php
```

*Optional: Create `send_maintenance_reminders_cron.php` to automate email sending*

---

## Files Summary

**New Files Created:**
1. `feature_updates.sql` — Database schema
2. `device_search.php` — Device search UI
3. `user_asset_dashboard.php` — Employee device dashboard
4. `maintenance_reminders.php` — Maintenance scheduler (IT/Admin)
5. `api_report_device_issue.php` — Issue reporting API
6. `api_send_maintenance_reminder.php` — Send reminder API
7. `NEW_FEATURES.md` (this file) — Documentation

**Files Modified:**
1. `includes/functions.php` — Added 10+ new helper functions
2. `includes/header.php` — Added new navigation items
3. `dashboard.php` — Added color-coded asset status overview
4. `feature_updates.sql` — Created new database tables

---

## Testing Checklist

- [ ] Database schema imported successfully
- [ ] Navigation menu shows new items
- [ ] Device search returns results by serial number
- [ ] Employee can view assigned devices on My Devices page
- [ ] Employee can report device issue with attachment
- [ ] IT staff receives email notification for new repair
- [ ] IT staff can create maintenance schedule
- [ ] Maintenance reminder email sends successfully
- [ ] Dashboard shows color-coded status overview
- [ ] Color badges display correctly throughout app
- [ ] All status indicators and counts are accurate

---

## Future Enhancements

- [ ] Bulk maintenance scheduling for multiple devices
- [ ] Predictive maintenance recommendations based on device age
- [ ] Maintenance history charts and analytics
- [ ] Mobile app for submitting device issues in field
- [ ] Integration with external maintenance ticketing system
- [ ] SMS reminders for critical maintenance
- [ ] Device health scoring based on repair history
- [ ] Cost-per-device analysis for depreciation

---

## Support

For issues or questions:
1. Check `/SYSTEM_FLOWCHART.md` for system architecture
2. Review SQL schema in `feature_updates.sql`
3. Check email configuration in `includes/PHPMailer/email_config.php`
4. Review helper functions in `includes/functions.php`

---

**Last Updated:** May 19, 2026
**Version:** 1.0
