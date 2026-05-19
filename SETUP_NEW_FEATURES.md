# 🚀 Quick Setup Guide - New Features

Follow these steps to activate the 4 new features in your KBMC Asset Management system.

## Step 1: Import Database Schema

1. Open **phpMyAdmin** → select your `kbmc_asset_db` database
2. Click **SQL** tab
3. Copy all contents of `feature_updates.sql`
4. Paste into SQL editor and click **Go**
5. You should see "✓ Query successful" messages

**Alternatively via command line:**
```bash
cd C:\xampp\htdocs\kbmc_new_asset
mysql -u root -p kbmc_asset_db < feature_updates.sql
```

---

## Step 2: Verify New Files Exist

Check that these files are in the root directory:
- ✓ `device_search.php` — Device search by serial number
- ✓ `user_asset_dashboard.php` — Employee device dashboard  
- ✓ `maintenance_reminders.php` — Maintenance scheduler
- ✓ `api_report_device_issue.php` — Issue reporting API
- ✓ `api_send_maintenance_reminder.php` — Send reminder API
- ✓ `feature_updates.sql` — Database schema
- ✓ `NEW_FEATURES.md` — Complete documentation

---

## Step 3: Check Navigation Updates

1. Log in to the system
2. Check sidebar for new menu items:
   - **Tools & Search** section with:
     - ✓ Search Devices
     - ✓ My Devices
   - **Maintenance** (IT/Admin only)

---

## Step 4: Test Each Feature

### ✅ Test 1: Color-Coded Dashboard
1. Go to **Dashboard**
2. Scroll down to see "Asset Status Overview (Color-Coded)"
3. You should see colored boxes for each status (green, blue, orange, red, etc.)

### ✅ Test 2: Device Serial Number Search
1. Go to **Tools & Search** → **Search Devices**
2. Try searching by:
   - Serial number (e.g., from an existing device)
   - Asset tag (e.g., KBMC-LAP-001)
   - Brand/model (e.g., Dell, Lenovo)
3. Results should display with status badges

### ✅ Test 3: User Asset Dashboard
1. Log in as a regular **employee** (not admin/IT staff)
2. Go to **Tools & Search** → **My Devices**
3. You should see:
   - Stats cards (Total, Active, Under Repair, Pending)
   - Table of your assigned devices
   - "Report Issue" button for each device
4. Click **Report Issue** on any device:
   - Fill in description
   - Select severity level
   - Optionally attach a file
   - Click "Report Issue"
5. You should get a success message

### ✅ Test 4: Email Notifications & Maintenance
1. Log in as **IT Staff** or **Admin**
2. Go to **Maintenance**
3. Click **Add Maintenance Schedule**:
   - Select a device
   - Choose maintenance type
   - Pick a due date (today or tomorrow)
   - Assign to IT staff member
   - Click "Create Schedule"
4. Back on maintenance page, you should see it listed
5. Click **Send Reminder** button:
   - Email will be queued to the assigned IT staff member
   - They'll receive a notification

---

## Step 5: Configure Email (Optional but Recommended)

For email notifications to actually send:

1. Open `includes/PHPMailer/email_config.php`
2. Update with your Gmail or SMTP credentials:
   ```php
   $GMAIL_ADDRESS = 'your-email@gmail.com';
   $GMAIL_PASSWORD = 'your-app-password'; // Use App Password, not your password
   ```
3. Set `BASE_URL` in `includes/config.php`:
   ```php
   define('BASE_URL', 'http://localhost/kbmc_new_asset');
   ```
4. Test by creating a maintenance schedule and sending a reminder

---

## Step 6: Create Sample Data (Optional)

To test with realistic data:

1. Add a maintenance schedule:
   - Device: Any laptop/computer
   - Type: Preventive
   - Due: Tomorrow
   - Assign to: You (IT staff)

2. Report an issue as an employee:
   - Go to My Devices
   - Report Issue on any device
   - Wait for admin to see notification

3. Search for a device:
   - Use the serial number of an existing device
   - Verify search finds it

---

## ✅ All Set!

Your system now has:

| Feature | Location | Who Uses It |
|---------|----------|-------------|
| 📊 Color-Coded Dashboard | Dashboard | Everyone |
| 🔍 Device Search by Serial # | Tools & Search → Search Devices | Everyone |
| 📱 My Devices Dashboard | Tools & Search → My Devices | Employees |
| 🔧 Maintenance Scheduler | Maintenance (in sidebar) | IT/Admin |
| 📧 Email Reminders | Built-in, triggers automatically | IT Staff |

---

## Troubleshooting

### "Table already exists" error
- This is normal if you've imported before
- The SQL includes `IF NOT EXISTS` clauses
- You can safely ignore these messages

### New menu items not showing
- Refresh the page (Ctrl+F5)
- Check that you're logged in
- Clear browser cache

### Emails not sending
- Verify `includes/PHPMailer/email_config.php` is configured
- Check `BASE_URL` in `includes/config.php`
- Ensure Gmail credentials are correct
- Gmail requires "App Password" for SMTP, not your regular password

### Device search returns nothing
- Try searching with at least 2 characters
- Make sure the device exists in the system
- Check device's serial number is accurate

---

## Next Steps

1. **Add more devices** to test with
2. **Assign devices to employees** via Deployments
3. **Schedule regular maintenance** for your equipment
4. **Review reports** to track asset health
5. **Set up email reminders** for automatic notifications

---

**Questions?** Check `NEW_FEATURES.md` for detailed feature documentation.

**Last Updated:** May 19, 2026
