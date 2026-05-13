# Borrow Items Management - Integrated Improvements

## Key Features Implemented

### 1. **Admin vs. Staff Logic**
- **Admins**: Borrow requests are approved immediately and inventory is deducted right away
- **Staff**: Borrow requests create a "PENDING" status and send notifications to all admins for approval
- Staff return requests create a "RETURN_PENDING" status for admin verification

### 2. **Status Badges - Dynamic Colors**
- `PENDING` → Yellow (awaiting admin approval)
- `APPROVED` → Green (approved and active)
- `RETURN_PENDING` → Blue (return request pending admin approval)
- `RETURNED` → Gray (fully returned)
- `DENIED` → Red (request denied)

### 3. **Admin Decision Controls**
Admins see contextual action buttons based on transaction status:
- **For PENDING borrows**: "Accept" (approve) and "Deny" buttons
- **For RETURN_PENDING**: "Accept Return" button to verify and finalize return
- **For APPROVED borrows**: Staff/other roles can click "Return" to initiate return

### 4. **Notification Integration**
When staff members submit requests:
- Automatic notification records are inserted into the `notifications` table
- Each notification includes:
  - `user_id`: Admin receiving the notification
  - `actor_user_id`: Staff member who made the request
  - `type`: 'borrow_request' or 'return_request'
  - `related_id`: Borrow transaction ID
  - `payload`: JSON with reference number and custom message
- WebSocket push notifications via `push_notification_ws()` (if configured in notify_push.php)

### 5. **Refined AJAX Handlers**
All actions are handled via AJAX with proper error handling:
- `action=borrow` → Create borrow request (PENDING for staff, APPROVED for admin)
- `action=return` → Initiate return (RETURN_PENDING for staff, immediate for admin)
- `action=admin_approve` → Admin approves a pending borrow
- `action=admin_deny` → Admin denies a pending borrow
- `action=admin_approve_return` → Admin finalizes a pending return

### 6. **Inventory Transaction Logging**
Every borrow/return action is logged in `inventory_transactions` table:
- Borrow: Type 'OUT', deducts from assigned_quantity
- Return: Type 'IN', restores to assigned_quantity
- Reference numbers preserved for audit trail

### 7. **Pagination Preserved**
- Available items table supports pagination (10 items per page)
- Search functionality filters items by name or accountable person
- Current page maintained in URL parameters

## Database Schema Requirements

### notifications Table
```sql
CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  actor_user_id INT,
  type VARCHAR(50),
  related_id INT,
  payload JSON,
  is_read BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES user(id),
  FOREIGN KEY (actor_user_id) REFERENCES user(id)
);
```

### borrowed_items Table (Required Columns)
- `borrow_id` (Primary Key)
- `status` (PENDING, APPROVED, RETURN_PENDING, RETURNED, DENIED)
- `requested_by`, `requested_at` (who requested and when)
- `approved_by`, `approved_at` (who approved and when, nullable)
- `return_date`, `is_returned` (for tracking returns)

## Testing Checklist

- [ ] **Staff Borrow Request**: Create pending request, verify notification to admins
- [ ] **Admin Approve**: Admin approves pending borrow, inventory deducted
- [ ] **Admin Deny**: Admin denies pending borrow, no inventory change
- [ ] **Admin Direct Borrow**: Admin borrow creates APPROVED immediately
- [ ] **Staff Return Request**: Staff initiates return, creates RETURN_PENDING
- [ ] **Admin Approve Return**: Admin approves return, inventory restored
- [ ] **Admin Direct Return**: Admin can return immediately (no pending)
- [ ] **Pagination**: Switch pages on available items list
- [ ] **Search**: Filter items and pagination works together
- [ ] **Toast Notifications**: Success/error messages display correctly
- [ ] **Inventory Transactions**: All transactions logged in DB with correct type/quantity

## Technical Notes

- **Output Buffering**: `ob_start()` prevents headers from breaking JSON responses
- **CSRF Protection**: All POST requests require valid CSRF token
- **Transaction Safety**: Database transactions prevent race conditions during inventory updates
- **Row Locking**: `FOR UPDATE` clauses prevent concurrent modification issues
- **WebSocket Ready**: `@push_notification_ws()` calls optional for real-time notifications
