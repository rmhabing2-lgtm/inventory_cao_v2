# TODO — Borrow Return Modal + Data Movement Transparency

## Step 1 — Confirm desired action wiring
- [x] Use a dedicated POST action for the modal confirm button: `admin_direct_return_move_new` (new action name, same logic with improved mapping/logging).

## Step 2 — UI changes (borrow_items.php)

- [ ] Rename/label modal confirm button to “Return(new)”
- [ ] Wire modal submit to `action=admin_direct_return_move_new`

## Step 3 — Server logic changes (borrow_items.php)
- [ ] Implement `admin_direct_return_move_new`:
  - [ ] Update only accountable_items fields allowed for movement (assigned_quantity, serial_number merge if applicable)
  - [ ] Never overwrite snapshot fields (created_by_*, last_updated_by_*, last_updated_at, etc.)
  - [ ] Move/merge only based on borrow data selected id

## Step 4 — Logging/audit transparency
- [ ] Ensure inventory_transactions, audit_logs, system_logs are updated with:
  - [ ] borrow_id, accountable_id, inventory_item_id, qty, reference_no/ref, and serials
  - [ ] old borrowed_items details preserved for audit_logs

## Step 5 — Modal field completeness
- [ ] Ensure borrow_items_api.php returns all fields listed in the requirement (diff vs current)

## Step 6 — Test
- [ ] Manual test return flow (ADMIN/MANAGER)
- [ ] Validate DB effects: accountable_items updated correctly; borrowed_items deleted; logs created

