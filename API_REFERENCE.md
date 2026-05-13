# Borrow System v2.0 - API Reference

## Overview
All API calls are made via POST to `/table/borrow_items.php` with CSRF token validation.

---

## Endpoints

### 1. Create Borrow Request
**Endpoint:** `POST /table/borrow_items.php`  
**Action:** `create_borrow`  
**Authorization:** Staff (automatic) or Admin

```json
{
  "action": "create_borrow",
  "csrf_token": "{{csrf_token}}",
  "accountable_id": 123,
  "borrower_employee_id": 45,
  "borrow_quantity": 2,
  "purpose": "Audit preparation",
  "expected_return_date": "2024-12-15T18:00"
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Request submitted for approval."
}
```

**Response (Admin Direct):**
```json
{
  "success": true,
  "message": "Item borrowed!"
}
```

**Status Code:** 200 OK or 400 Bad Request

---

### 2. Admin Approve Borrow Request
**Endpoint:** `POST /table/borrow_items.php`  
**Action:** `admin_approve`  
**Authorization:** Admin only

```json
{
  "action": "admin_approve",
  "csrf_token": "{{csrf_token}}",
  "borrow_id": 789
}
```

**Response:**
```json
{
  "success": true,
  "message": "Request approved. Item reserved for release."
}
```

**Effect:**
- Status: `PENDING` → `APPROVED`
- Item status: → `Reserved`
- Notification sent to staff

---

### 3. Admin Release Item
**Endpoint:** `POST /table/borrow_items.php`  
**Action:** `release_item`  
**Authorization:** Admin only

```json
{
  "action": "release_item",
  "csrf_token": "{{csrf_token}}",
  "borrow_id": 789
}
```

**Response:**
```json
{
  "success": true,
  "message": "Item released successfully."
}
```

**Effect:**
- Status: `APPROVED` → `RELEASED`
- Item status: → `Borrowed`
- Stock deducted from `accountable_items`
- Transaction logged: `OUT`
- `release_timestamp` recorded
- Notification sent to staff

---

### 4. Staff Request Return
**Endpoint:** `POST /table/borrow_items.php`  
**Action:** `return_request`  
**Authorization:** Borrower (staff)

```json
{
  "action": "return_request",
  "csrf_token": "{{csrf_token}}",
  "borrow_id": 789,
  "declared_condition": "Good",
  "damage_notes": "Minor scratches on corner"
}
```

**Request Parameters:**
- `declared_condition`: `"Good"` | `"Damaged"`
- `damage_notes`: Optional string (max 500 chars)

**Response:**
```json
{
  "success": true,
  "message": "Return request submitted. Please bring item to IT Office."
}
```

**Effect:**
- Status: `RELEASED` → `RETURN_PENDING`
- Item status: → `For Inspection`
- `return_requested_by`: Set to staff ID
- `return_requested_at`: Timestamp recorded
- Notification sent to admins

---

### 5. Admin Finalize Inspection
**Endpoint:** `POST /table/borrow_items.php`  
**Action:** `finalize_inspection`  
**Authorization:** Admin only

```json
{
  "action": "finalize_inspection",
  "csrf_token": "{{csrf_token}}",
  "borrow_id": 789,
  "actual_condition": "Good",
  "severity": "Minor",
  "estimated_cost": 5000,
  "incident_description": "Cracked screen discovered"
}
```

#### Path A: Good Condition
```json
{
  "actual_condition": "Good"
}
```

**Effect:**
- Status: `RETURN_PENDING` → `CLOSED`
- Item status: → `Available`
- Stock restored to `accountable_items`
- Transaction logged: `IN`
- Notification: "Return Accepted"

#### Path C: Damaged/Lost
```json
{
  "actual_condition": "Damaged",
  "severity": "Major",
  "estimated_cost": 5000,
  "incident_description": "Cracked screen during use"
}
```

**Parameters:**
- `severity`: `"Minor"` | `"Major"` | `"Irreparable"`
- `estimated_cost`: Decimal (0.00 format)
- `incident_description`: Required for Damaged/Lost

**Effect:**
- Incident report created in `incident_reports` table
- Item status: → `Unserviceable`
- Borrow remains `RELEASED` (for accountability)
- Notification: "Incident Reported" → Staff
- Notification: "INCIDENT_ALERT" → All Admins

**Response:**
```json
{
  "success": true,
  "message": "Return accepted."
}
```
or
```json
{
  "success": true,
  "message": "Incident report created. Staff notified."
}
```

---

## Cron Job Endpoint

### Automated Overdue Monitoring
**Endpoint:** `GET /cron/cron_overdue_check.php`  
**Trigger:** Daily at 00:01 AM

**Parameters:**
```
?token={{HASHED_CRON_TOKEN}}
```

**Response (HTTP):**
```json
{
  "success": true,
  "processed": 5
}
```

**Response (CLI):**
```
✅ Overdue monitoring completed. Processed: 5 items
```

**Automation Schedule:**

**Linux (crontab):**
```bash
1 0 * * * curl -s "http://localhost/inventory_cao_v2/cron/cron_overdue_check.php?token=YOUR_TOKEN"
```

**Windows (Task Scheduler):**
```
Program: C:\xampp\php\php.exe
Arguments: -f "C:\xampp\htdocs\inventory_cao_v2\cron\cron_overdue_check.php"
Schedule: Daily 00:01 AM
```

---

## Error Responses

### CSRF Token Invalid
```json
{
  "success": false,
  "message": "Security token mismatch."
}
```
**Status Code:** 400

### Unauthorized Action
```json
{
  "success": false,
  "message": "Unauthorized."
}
```
**Status Code:** 403

### Item Not Found
```json
{
  "success": false,
  "message": "Item not found."
}
```
**Status Code:** 404

### Insufficient Quantity
```json
{
  "success": false,
  "message": "Only 2 available."
}
```
**Status Code:** 400

### Invalid State Transition
```json
{
  "success": false,
  "message": "Not ready for release."
}
```
**Status Code:** 400

---

## Notification Types

Triggered automatically on status transitions:

| Type | Trigger | Recipient | Payload |
|------|---------|-----------|---------|
| `BORROW_APPROVED` | Admin approves | Staff | borrow_id, status, item |
| `ITEM_RELEASED` | Admin releases | Staff | borrow_id, status, item |
| `RETURN_REQUEST` | Staff requests return | Admins | borrow_id, status, item |
| `RETURN_COMPLETED` | Admin accepts (Path A) | Staff | borrow_id, status |
| `INCIDENT_REPORTED` | Admin reports damage (Path C) | Staff | borrow_id, type, cost, severity |
| `OVERDUE_NOTICE` | Item 1+ days late | Staff | days_overdue, expected_return, severity |
| `OVERDUE_ESCALATION` | Item 7+ days late | Admins | days_overdue, borrower, value |
| `INCIDENT_AUTO_ESCALATION` | Item 30+ days late | Admins | item, auto_escalated, value |

---

## Database Query Examples

### Find All Overdue Items
```sql
SELECT b.*, ii.item_name FROM borrowed_items b
JOIN inventory_items ii ON b.inventory_item_id = ii.id
WHERE b.status = 'RELEASED'
AND b.expected_return_date < NOW()
AND b.is_overdue_notified = 0;
```

### Find All Incidents This Month
```sql
SELECT * FROM incident_reports
WHERE created_at >= DATE_TRUNC('month', CURDATE())
ORDER BY severity DESC;
```

### Total Cost of Lost/Damaged Items
```sql
SELECT SUM(estimated_cost) as total_loss
FROM incident_reports
WHERE incident_type IN ('Damaged', 'Lost')
AND status = 'Open';
```

### Staff Performance Report
```sql
SELECT borrower_employee_id, 
       COUNT(*) as total_borrows,
       SUM(CASE WHEN status='CLOSED' THEN 1 ELSE 0 END) as returned_good,
       COUNT(DISTINCT ir.incident_id) as incidents,
       SUM(ir.estimated_cost) as total_damage
FROM borrowed_items b
LEFT JOIN incident_reports ir ON b.borrow_id = ir.borrow_id
GROUP BY borrower_employee_id
ORDER BY incidents DESC;
```

### Monthly Borrow Summary
```sql
SELECT DATE(borrow_date) as date,
       COUNT(*) as requests,
       SUM(quantity) as items_borrowed,
       COUNT(DISTINCT borrower_employee_id) as unique_staff
FROM borrowed_items
WHERE YEAR(borrow_date) = YEAR(CURDATE())
GROUP BY DATE(borrow_date)
ORDER BY date DESC;
```

---

## Rate Limiting & Security

- **CSRF Token:** Required on all POST requests (validate in session)
- **SQL Injection:** All queries use prepared statements
- **Access Control:** Role-based (ADMIN vs STAFF)
- **Concurrent Access:** SELECT...FOR UPDATE locks on critical sections
- **Transaction Atomicity:** All-or-nothing updates with rollback

---

## Testing

```bash
# Run comprehensive test suite
php test_borrow_system.php

# Verify database schema
php verify_schema.php

# Test cron job manually
php cron/cron_overdue_check.php

# Check syntax
php -l table/borrow_items.php
php -l cron/cron_overdue_check.php
```

---

## Version Info

**API Version:** 2.0  
**Last Updated:** 2024  
**Compatibility:** PHP 7.4+, MySQL 5.7+, Bootstrap 5

