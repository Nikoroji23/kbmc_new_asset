# Asset Import System - Verification Checklist

Use this checklist to verify everything is working correctly.

## ✅ Pre-Import Verification

### System Setup
- [ ] Database is running (XAMPP MySQL active)
- [ ] All tables exist (device_types, devices, users, device_assignments)
- [ ] Admin user account is active and logged in
- [ ] CSV file is properly formatted

### File Preparation
- [ ] CSV file has all 18 columns in correct order
- [ ] Column headers match exactly (NAME, DEPARTMENT, PC NAME, IP ADRESS, etc.)
- [ ] File is saved as .csv format (not .xlsx)
- [ ] File size is under 5MB
- [ ] At least one employee record in file

---

## 🔧 After System Setup

### Navigate to Import Page
- [ ] Go to `http://localhost/kbmc_new_asset/import_assets.php`
- [ ] Page loads without errors
- [ ] Admin Dashboard has "Import Assets" button (if logged in as admin)
- [ ] Import form displays correctly

### Test with Sample File
- [ ] Sample file exists: `sample_assets_import.csv`
- [ ] Upload sample file through import page
- [ ] Import completes successfully
- [ ] Review import summary:
  - [ ] "Users Created" shows a number (e.g., 8)
  - [ ] "Devices Created" shows a number
  - [ ] "Assignments Created" shows a number

---

## 👤 Verify User Accounts Created

### Check Manage Users Page
1. [ ] Go to Admin Dashboard → Manage Users
2. [ ] Find newly imported employees in list
3. [ ] Verify each has:
   - [ ] Correct full name
   - [ ] Auto-generated email (firstname.lastname@kbmc.com)
   - [ ] Auto-generated Employee ID (5-digit number)
   - [ ] Department assigned
   - [ ] Role set to "employee"
   - [ ] Status "active"

### Test User Login
1. [ ] Log out from admin account
2. [ ] Go to login page: `http://localhost/kbmc_new_asset/`
3. [ ] Try logging in with:
   - Email: One of the newly created emails
   - Password: `password`
4. [ ] Verify successful login
5. [ ] Verify user can see "My Devices" in navigation

---

## 🖥️ Verify Devices Created

### Check All Devices Page
1. [ ] Go to Admin Dashboard (as admin)
2. [ ] Click "All Devices" or go to Devices page
3. [ ] Search for newly imported devices by asset tag
4. [ ] Verify devices show:
   - [ ] Asset tag matches CSV data (e.g., KBM-IT-000691)
   - [ ] Device type is correct (Monitor, Keyboard, Mouse, etc.)
   - [ ] Status is "deployed"
   - [ ] Created date is recent

### Verify Device Assignments
1. [ ] Go to one employee's profile
2. [ ] Check "My Assigned Devices" section
3. [ ] Verify all valid assets appear (skip N/A and KBM-IT-00)
4. [ ] Each device should show:
   - [ ] Asset tag
   - [ ] Device type
   - [ ] Assignment date

---

## ✏️ Test Profile Editing

### Test Email Editing
1. [ ] Log in as newly created employee
2. [ ] Go to My Profile
3. [ ] Find Email field
4. [ ] Verify it's now EDITABLE (not disabled)
5. [ ] Try changing to different email: test@example.com
6. [ ] Click "Update Profile"
7. [ ] Verify change saved successfully
8. [ ] Try logging in with new email

### Test Employee ID Editing
1. [ ] In My Profile, find Employee ID field
2. [ ] Verify it's now EDITABLE (not disabled)
3. [ ] Try changing Employee ID
4. [ ] Click "Update Profile"
5. [ ] Verify change saved

### Test Phone/Contact Editing
1. [ ] Find Contact (Phone) field in profile
2. [ ] Verify it's EDITABLE
3. [ ] Add phone number: 555-1234
4. [ ] Click "Update Profile"
5. [ ] Verify change saved

### Test Password Change
1. [ ] In My Profile, enter current password: `password`
2. [ ] Enter new password: `newpassword123`
3. [ ] Click "Update Profile"
4. [ ] Log out
5. [ ] Try logging in with new password
6. [ ] Verify new password works

---

## 📋 Test My Devices Page

### View Assigned Devices
1. [ ] Log in as employee user
2. [ ] Click "My Devices" in navigation (or go to user_asset_dashboard.php)
3. [ ] Page displays without errors
4. [ ] Statistics cards show:
   - [ ] Total Devices Assigned (should match CSV assets)
   - [ ] Active & Functional (should be same as total)
   - [ ] Under Repair (likely 0 initially)
   - [ ] Pending Repairs (likely 0 initially)

### Verify Device List
1. [ ] Device table displays all assigned devices
2. [ ] Each device shows:
   - [ ] Asset Tag
   - [ ] Device Type
   - [ ] Brand & Model (from database)
   - [ ] Status (should be "deployed")
   - [ ] Assigned Date
   - [ ] Maintenance status
   - [ ] "View" button

### Test Device Details Link
1. [ ] Click "View" on any device
2. [ ] Device detail page loads
3. [ ] Shows complete device information

---

## 🔍 Verify Data Integrity

### Test Duplicate Prevention
1. [ ] Try importing same CSV file again
2. [ ] System should show:
   - [ ] Users created: 0 (already exist)
   - [ ] Devices created: 0 (already exist)
   - [ ] Assignments: may create if different
3. [ ] No duplicate errors on users or devices

### Test Invalid Asset Handling
1. [ ] Check CSV for "N/A" or "KBM-IT-00" entries
2. [ ] These should NOT create devices
3. [ ] Verify in error list or excluded items

### Test Empty Field Handling
1. [ ] Rows with empty asset columns should skip those assets
2. [ ] Employee account should still be created
3. [ ] Only valid assets link to employee

---

## 📊 Admin Dashboard Verification

### Check Updated Dashboard
1. [ ] Go to Admin Dashboard
2. [ ] Verify stats updated:
   - [ ] Active Users count increased
   - [ ] Total Devices count increased
   - [ ] Employees count increased
3. [ ] Import Assets button visible and clickable
4. [ ] All other buttons still working

### Check Audit Logs
1. [ ] Go to Admin Dashboard → Audit Logs
2. [ ] Search for recent import activity
3. [ ] Should see entries for:
   - [ ] Users created
   - [ ] Devices created
   - [ ] Assignments created
   - [ ] User actions (profile updates, etc.)

---

## 🎯 Full Import Test Scenario

Complete this end-to-end test:

1. [ ] **Prepare:** Create test CSV with 3-5 employees
2. [ ] **Import:** Upload through import_assets.php
3. [ ] **Verify:** Check users created in database
4. [ ] **Login:** Test with one employee account
5. [ ] **Update:** Change email, phone, password
6. [ ] **Check:** View "My Devices"
7. [ ] **Logout:** Verify new password works on re-login
8. [ ] **Admin:** Check Audit Logs show all activity

---

## 🐛 Troubleshooting

### If import fails:
- [ ] Check CSV column order is exact
- [ ] Verify CSV is in correct format (can open in Excel)
- [ ] Check file encoding is UTF-8
- [ ] Look for error messages in import summary

### If devices don't appear:
- [ ] Check asset tags are not "N/A" or "KBM-IT-00"
- [ ] Verify device types exist (Monitor, Keyboard, etc.)
- [ ] Check database device_types table has entries

### If profile editing doesn't work:
- [ ] Clear browser cache and reload
- [ ] Check for JavaScript errors in console
- [ ] Verify database columns updated in code

### If "My Devices" shows nothing:
- [ ] Check device_assignments table has entries
- [ ] Verify devices are linked to correct user
- [ ] Check device status is "deployed"

---

## 📝 Sign-Off

Once all checks pass, mark complete:

- **Date Tested:** _____________
- **Tested By:** _____________
- **All Tests Passed:** [ ] Yes  [ ] No
- **Issues Found:** _________________________________

---

## ✅ Final Status

When all items are checked:

**System is ready for production use! ✅**

Users can now:
- ✓ Import employees and assets
- ✓ Access assigned devices
- ✓ Edit their profile information
- ✓ Change passwords securely
- ✓ View device assignments

---

Date: May 25, 2026
Version: 1.0
