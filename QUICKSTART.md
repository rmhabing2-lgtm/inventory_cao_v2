# 🚀 Borrow System v2.0 - Quick Start Guide

## 30-Second Overview

The inventory borrow system has been completely **rebuilt for LGU/COA compliance**. It now handles the complete lifecycle of borrowing items with automated incident reporting for damage/loss accountability.

**Status: ✅ PRODUCTION READY**

---

## What Changed?

### Before (v1.x)
- Individual action handlers scattered throughout code
- Manual stock updates
- No incident tracking
- Manual overdue checking

### After (v2.0)
- ✅ Centralized state machine (`transitionBorrowStatus()`)
- ✅ Atomic transactions with locks
- ✅ Automatic incident reporting for damage/loss
- ✅ Automated cron job for overdue escalation
- ✅ Full audit trail in notifications table
- ✅ COA-compliant financial accountability

---

## File Locations

```
📂 Workspace Root: c:\xampp\htdocs\inventory_cao_v2\

Core Files:
├── table/borrow_items.php           ← Main UI (1,200+ lines)
├── cron/cron_overdue_check.php      ← Daily auto-monitoring
└── migrate_v2_schema.php            ← Database schema (7 migrations)

Documentation:
├── IMPLEMENTATION_SUMMARY.md        ← Start here
├── BORROW_SYSTEM_GUIDE.md          ← Complete reference
├── API_REFERENCE.md                ← Endpoint docs
└── STATUS.php                       ← File status checker

Testing:
├── test_borrow_system.php          ← Run tests (18/18 pass ✓)
└── verify_schema.php               ← Schema validator
```

---

## Quick Start (5 Minutes)

### 1. Verify Database Schema
```bash
php c:\xampp\htdocs\inventory_cao_v2\verify_schema.php
```
Expected output: All columns present ✓

### 2. Run Tests
```bash
php c:\xampp\htdocs\inventory_cao_v2\test_borrow_system.php
```
Expected output: 18/18 TESTS PASSED ✓

### 3. Access the UI
```
http://localhost/inventory_cao_v2/table/borrow_items.php
```

### 4. Try a Workflow
1. **Request**: Click "Request Borrow" → Select item, employee, quantity, purpose
2. **Approve**: Admin clicks "Approve"
3. **Release**: Admin clicks "Release" (stock deducts automatically)
4. **Return**: Staff clicks "Return" → Declares condition
5. **Inspect**: Admin clicks "Inspect" → Selects "Good" or "Damaged"
   - **Good** → Item back to stock (Path A)
   - **Damaged** → Incident report created + financial tracking (Path C)

---

## 6-Phase Lifecycle

```
Phase 1: PENDING (Staff submits request)
   ↓ [Admin approves]
Phase 2: APPROVED (Item reserved)
   ↓ [Admin releases]
Phase 3: RELEASED (Item in borrower's possession)
   ↓ [Cron monitors overdue]
Phase 4: MONITORING (Overdue checks via cron)
   ↓ [Staff requests return]
Phase 5: RETURN_PENDING (Awaiting inspection)
   ↓ [Admin inspects]
   ├─ CLOSED (Good condition) → Back to Available
   └─ INCIDENT (Damaged/Lost) → Unserviceable + Financial Tracking
```

---

## Key Endpoints

All requests POST to: `/table/borrow_items.php`

| Action | Description |
|--------|-------------|
| `create_borrow` | Request item |
| `admin_approve` | Approve request |
| `release_item` | Release for use (stock deducts) |
| `return_request` | Request return |
| `finalize_inspection` | Inspect & finalize (Path A/C) |

See [API_REFERENCE.md](API_REFERENCE.md) for complete endpoint documentation.

---

## Automated Features

### Cron Job (Daily at 00:01 AM)
```bash
php cron/cron_overdue_check.php
```

**What it does:**
1. Finds items 1+ days overdue → Notifies staff
2. Finds items 7+ days overdue → Escalates to admins
3. Finds items 30+ days overdue → Auto-creates incident report

**Schedule (choose one):**

**Linux:**
```bash
crontab -e
# Add: 1 0 * * * curl -s "http://localhost/inventory_cao_v2/cron/cron_overdue_check.php"
```

**Windows Task Scheduler:**
- Action: `C:\xampp\php\php.exe -f C:\xampp\htdocs\inventory_cao_v2\cron\cron_overdue_check.php`
- Schedule: Daily at 00:01 AM

---

## Database Schema (What's New)

### `borrowed_items` Table (5 new columns)
- `expected_return_date` - When item should return
- `purpose` - Why it was borrowed
- `release_timestamp` - When physically handed over
- `release_condition` - Borrower's declared condition
- `is_overdue_notified` - Cron job flag

### `inventory_items` Table (1 new column)
- `item_status` - Enum: Available, Reserved, Borrowed, For Inspection, Unserviceable

### `incident_reports` Table (NEW)
Tracks damage/loss events for financial accountability:
- Borrow ID
- Incident type (Damaged/Lost)
- Severity (Minor/Major/Irreparable)
- Estimated cost (₱)
- Description & findings

---

## Common Tasks

### Check Overdue Items
```sql
SELECT b.*, ii.item_name FROM borrowed_items b
JOIN inventory_items ii ON b.inventory_item_id = ii.id
WHERE b.status = 'RELEASED'
AND b.expected_return_date < NOW()
ORDER BY b.expected_return_date;
```

### View Incident Reports
```sql
SELECT * FROM incident_reports
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY created_at DESC;
```

### Total Loss Amount
```sql
SELECT SUM(estimated_cost) as total_loss
FROM incident_reports
WHERE status = 'Open';
```

### Staff Borrow History
```sql
SELECT * FROM borrowed_items
WHERE borrower_employee_id = 123
ORDER BY borrow_date DESC;
```

---

## Troubleshooting

### Tests Fail?
1. Verify database: `php verify_schema.php`
2. Check all columns added: See migration output
3. Run migrations again: `php migrate_v2_schema.php`

### Cron Not Running?
1. Test manually: `php cron/cron_overdue_check.php`
2. Check Task Scheduler (Windows) or crontab (Linux)
3. Verify file permissions

### Stock Not Deducting?
1. Check `accountable_items.assigned_quantity` column exists
2. Verify item was actually released (status = RELEASED)
3. Check `inventory_transactions` table for log entries

---

## Testing Checklist

- [ ] Create borrow request
- [ ] Approve & release item (verify stock reduced)
- [ ] Request return
- [ ] Inspect (Path A - Good)
- [ ] Verify item back to stock
- [ ] Create new borrow & request return
- [ ] Inspect (Path C - Damaged)
- [ ] Verify incident_reports entry created
- [ ] Run cron job manually
- [ ] Check notifications table for events

---

## Next Steps

1. **Review** [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md) (5 min)
2. **Test** `php test_borrow_system.php` (2 min)
3. **Try** the web interface (10 min)
4. **Schedule** cron job (5 min)
5. **Deploy** to production

---

## Support

- **Complete Guide**: [BORROW_SYSTEM_GUIDE.md](BORROW_SYSTEM_GUIDE.md)
- **API Docs**: [API_REFERENCE.md](API_REFERENCE.md)
- **Deployment**: [IMPLEMENTATION_SUMMARY.md](IMPLEMENTATION_SUMMARY.md)
- **Status Check**: `php STATUS.php`

---

**Version**: 2.0  
**Status**: ✅ Production Ready  
**Compliance**: LGU/COA  
**Test Pass Rate**: 18/18 (100%)

