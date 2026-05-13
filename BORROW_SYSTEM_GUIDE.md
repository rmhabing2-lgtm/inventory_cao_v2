# 📋 Inventory Borrow System - Implementation Guide (v2.0)
## LGU/COA-Compliant Lifecycle Management

---

## 1. Architecture Overview

### State Machine (6 Phases)
```
Phase 1: DRAFT/PENDING
    ↓
Phase 2: APPROVED (Item Reserved)
    ↓
Phase 3: RELEASED (Item Borrowed + Out)
    ↓
Phase 4: MONITORING (Overdue checks via cron)
    ↓ (Staff declares condition)
Phase 5: RETURN_PENDING (Awaiting inspection)
    ↓ (Admin inspects)
    ├─ Path A: CLOSED (Good condition) → Item back to Available
    └─ Path C: INCIDENT REPORT (Damaged/Lost) → Unserviceable + Financial Liability

Phase 6: EXPIRED (Auto-escalation after 30+ days)
```

### Inventory Item Status Tracking
- **Available** - Ready for borrowing
- **Reserved** - Approved for borrowing (Phase 2)
- **Borrowed** - Currently with staff (Phase 3)
- **For Inspection** - Pending inspection (Phase 5)
- **Unserviceable** - Damaged/Lost (Phase 6C)

---

## 2. Database Schema (Post-Migration)

### Table: `borrowed_items`
**New Columns (v2.0):**
```sql
- expected_return_date DATETIME       -- When item should return
- purpose VARCHAR(500)                -- Why item was borrowed
- release_condition VARCHAR(50)       -- Borrower's declared condition
- release_timestamp DATETIME          -- When physically handed over
- is_overdue_notified BOOLEAN         -- Cron job flag
- item_status ENUM (see above)        -- Linked to inventory_items
```

**Key Fields Used:**
- `status` - Main lifecycle state
- `reference_no` - Transaction ID (BORROW-YYYYMMDDHHmmss-XXXX)
- `decision_remarks` - Admin/system notes

### Table: `incident_reports` (New - Created via Migration 7)
```sql
CREATE TABLE incident_reports (
    incident_id INT PRIMARY KEY AUTO_INCREMENT,
    borrow_id INT NOT NULL,
    reported_by INT,                  -- Admin ID
    incident_type ENUM('Damaged','Lost') NOT NULL,
    severity ENUM('Minor','Major','Irreparable') NOT NULL,
    description TEXT,
    estimated_cost DECIMAL(12,2),
    status ENUM('Open','Resolved','Pending') DEFAULT 'Open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (borrow_id) REFERENCES borrowed_items(borrow_id)
);
```

### Table: `inventory_items`
**New Column:**
```sql
- item_status ENUM('Available','Reserved','Borrowed','For Inspection','Unserviceable') 
  DEFAULT 'Available'
```

---

## 3. File Structure

### Core Files
- **`table/borrow_items.php`** (REBUILT)
  - 1,200+ lines with full lifecycle management
  - Modals: Borrow Request, Return Request, Inspection (Path A/C)
  - CSRF protection, prepared statements, transaction handling
  - Real-time progress visualization

- **`cron/cron_overdue_check.php`** (NEW)
  - Runs daily at 00:01 AM
  - Identifies overdue items, notifies staff/admins
  - Auto-escalates to incident after 30 days
  - CLI + HTTP trigger support

- **`migrate_v2_schema.php`** (EXECUTED ✓)
  - Applied all 7 migrations
  - incident_reports table created
  - Columns added to borrowed_items & inventory_items

---

## 4. Workflow Examples

### Scenario 1: Normal Borrow-Return (Path A)
```
1. Staff requests item via modal
   → Status: PENDING (if not admin)
   
2. Admin reviews & clicks "Approve"
   → transitionBorrowStatus(..., 'APPROVED', ...)
   → Item status: Reserved
   → Notification: "Approved"
   
3. Admin clicks "Release"
   → transitionBorrowStatus(..., 'RELEASED', ...)
   → Stock deducted from accountable_items
   → Transaction logged (OUT)
   → Item status: Borrowed
   
4. Staff clicks "Return" after 7 days
   → Status: RETURN_PENDING
   → Item status: For Inspection
   
5. Admin opens inspection modal, selects "Good"
   → Path A triggered
   → Stock added back
   → Transaction logged (IN)
   → Status: CLOSED
   → Item status: Available
   → Notification: "Return Accepted"
```

### Scenario 2: Damage Discovery (Path C)
```
1-4. [Same as Path A steps 1-4]

5. Admin opens inspection modal, selects "Damaged"
   → Path C triggered
   → Incident report created with:
     * Severity: Major
     * Estimated cost: ₱5,000
     * Description: "Cracked screen"
   → Item status: Unserviceable
   → Borrow remains RELEASED for accountability
   → Notification: "Incident Reported" → Staff
   → Notification: "Incident Alert" → All Admins
```

### Scenario 3: Automatic Overdue Escalation
```
Day 1: Item released (expected return: Jan 10)
...
Day 8: Cron runs at 00:01 AM
   → Finds item 1 day overdue
   → Notifies borrower: "OVERDUE_NOTICE"
   → Logs to notifications table
   
Day 15: Cron runs again
   → 5 days overdue, >7 day threshold met
   → Notifies borrower: "OVERDUE_ESCALATION"
   → Notifies admins: "ESCALATION" alert
   
Day 31: Cron runs
   → 21 days overdue, 30+ day threshold met
   → Creates incident_report (type='Lost', severity='Irreparable')
   → Item status: Unserviceable
   → Notifies admins: "INCIDENT_AUTO_ESCALATION"
   → COA compliance: Financial liability recorded
```

---

## 5. API Endpoints (POST to `table/borrow_items.php`)

### Create Borrow Request
```javascript
action: 'create_borrow'
csrf_token: '<?= $_SESSION['csrf_token'] ?>'
accountable_id: 123
borrower_employee_id: 45
borrow_quantity: 2
purpose: 'Audit preparation'
expected_return_date: '2024-12-15T18:00'
```
**Returns:** `{success: true, message: "..."}`

### Admin Approve
```javascript
action: 'admin_approve'
csrf_token: '<?= $_SESSION['csrf_token'] ?>'
borrow_id: 789
```

### Release Item
```javascript
action: 'release_item'
borrow_id: 789
```
**Effect:** Deducts stock, logs transaction, sends notification

### Staff Return Request
```javascript
action: 'return_request'
borrow_id: 789
declared_condition: 'Good' | 'Damaged'
damage_notes: 'Minor scratches on corner'
```

### Admin Finalize Inspection
```javascript
action: 'finalize_inspection'
borrow_id: 789
actual_condition: 'Good' | 'Damaged' | 'Lost'
severity: 'Minor' | 'Major' | 'Irreparable'       // if Damaged/Lost
estimated_cost: 5000                              // if Damaged/Lost
incident_description: 'Screen cracked during use' // if Damaged/Lost
```

---

## 6. Cron Job Setup

### Linux (crontab)
```bash
# Run daily at 00:01 AM
1 0 * * * curl -s "http://localhost/inventory_cao_v2/cron/cron_overdue_check.php?token=YOUR_SECRET_KEY"
```

### Windows (Task Scheduler)
```powershell
# Action: Start a program
Program: C:\xampp\php\php.exe
Arguments: -f "C:\xampp\htdocs\inventory_cao_v2\cron\cron_overdue_check.php"

# Schedule: Daily at 00:01 AM
```

### Testing Cron Job
```bash
# CLI (direct execution)
php c:\xampp\htdocs\inventory_cao_v2\cron\cron_overdue_check.php

# HTTP (requires valid token)
curl "http://localhost/inventory_cao_v2/cron/cron_overdue_check.php?token=YOUR_HASHED_TOKEN"
```

---

## 7. Key Functions Reference

### `transitionBorrowStatus(&$conn, $borrow_id, $newStatus, $adminId, $remarks)`
**Purpose:** Centralized state machine handler

**Parameters:**
- `$conn` - DB connection reference
- `$borrow_id` - Borrow transaction ID
- `$newStatus` - Target status (APPROVED, RELEASED, RETURN_PENDING, CLOSED, EXPIRED)
- `$adminId` - User ID performing action
- `$remarks` - Optional admin notes

**Handles:**
1. Validates state transitions
2. Updates inventory_items.item_status based on phase
3. Records timestamps & decision_remarks
4. Triggers notifications automatically
5. All within transaction with rollback

**Example:**
```php
try {
    transitionBorrowStatus($conn, 789, 'APPROVED', $userId, 'Approved for audit');
} catch (Exception $e) {
    // Rollback handled internally
}
```

### `make_safe_payload($data)`
**Purpose:** Convert nested arrays to JSON safely (handles non-UTF-8)

**Returns:** JSON string suitable for notifications table

---

## 8. Audit Trail & Compliance

### Tracked Events (Via Notifications)
- ✓ Request submission
- ✓ Admin approval/denial
- ✓ Item release (with timestamp)
- ✓ Return request
- ✓ Inspection completion
- ✓ Incident creation (damage/loss)
- ✓ Overdue escalations

### Incident Report - Financial Accountability
**Created when:**
- Item returned damaged (admin inspection)
- Item returned lost (admin inspection)
- Item >30 days overdue (auto-escalated cron)

**Fields Captured:**
- Borrow reference
- Borrower name
- Item details & original value
- Damage/loss description
- Severity level
- Estimated replacement cost
- Report timestamp
- Admin notes

**COA Compliance:**
- Full audit trail in notifications table
- Incident_reports table for accountability
- No manual deletion possible (immutable by design)
- Searchable by staff member for performance reviews

---

## 9. Testing Checklist

- [ ] Create borrow request (staff)
- [ ] Approve request (admin)
- [ ] Release item & verify stock deduction
- [ ] Request return (staff)
- [ ] Inspect & accept (admin, Path A)
- [ ] Inspect & report damage (admin, Path C)
- [ ] Verify incident_reports entry
- [ ] Run cron job manually & check notifications
- [ ] Verify overdue escalation (mock future date)
- [ ] Check notification table for all events
- [ ] Verify CSRF protection (fail with invalid token)
- [ ] Test concurrent releases (transaction locks)
- [ ] Validate JSON payloads in notifications

---

## 10. Troubleshooting

### Notifications not sending?
- Check `notify_push.php` is included
- Verify `notifications` table exists
- Check for JSON errors in make_safe_payload()

### Cron job not running?
- Verify task scheduler is active (Windows) or crontab entry (Linux)
- Check error_logs in `logs/` directory
- Test manually: `php cron/cron_overdue_check.php`

### Item status not updating?
- Ensure migration 7 was applied (incident_reports table)
- Check borrowed_items.status matches enum values
- Verify transitionBorrowStatus() transaction completion

### Incident report not created?
- Check expected_return_date is set correctly
- Verify item_status column added to inventory_items table
- Test Path C flow manually via inspection modal

---

## 11. Future Enhancements

1. **Email Notifications** - Integrate notify_push with SMTP
2. **Mobile App** - REST API for field staff
3. **Analytics Dashboard** - Borrow patterns, loss frequency, cost trends
4. **Automated Recovery** - SMS reminders to borrowers
5. **Repair Tracking** - Link incident reports to repair workflow

---

**Last Updated:** 2024 | **Phase:** 11 (LGU/COA Compliance)
**Maintained by:** IT Inventory Team
