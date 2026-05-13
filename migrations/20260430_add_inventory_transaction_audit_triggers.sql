DELIMITER $$

-- ─── TRIGGER: After INSERT on inventory_transactions ───────────────────────
CREATE TRIGGER trg_inv_txn_after_insert
AFTER INSERT ON inventory_transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        user_id,
        user_name,
        module,
        action,
        record_id,
        old_data,
        new_data,
        created_at
    )
    SELECT
        NEW.user_id,
        COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System'),
        'inventory_transactions',
        'CREATE',
        NEW.transaction_id,
        NULL,
        JSON_OBJECT(
            'inventory_item_id', NEW.inventory_item_id,
            'transaction_type',  NEW.transaction_type,
            'quantity',          NEW.quantity,
            'unit_cost',         NEW.unit_cost,
            'reference_no',      NEW.reference_no,
            'remarks',           NEW.remarks
        ),
        NOW()
    FROM dual
    LEFT JOIN user u ON u.id = NEW.user_id;

    INSERT INTO system_logs (user_id, action_type, description, created_at)
    SELECT
        NEW.user_id,
        'INVENTORY_CREATE',
        CONCAT(
            'User ',
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), CONCAT('ID:', NEW.user_id), 'System'),
            ' created inventory transaction #', NEW.transaction_id,
            ' (', NEW.transaction_type, ', qty: ', NEW.quantity, ')'
        ),
        NOW()
    FROM dual
    LEFT JOIN user u ON u.id = NEW.user_id;
END$$


-- ─── TRIGGER: After UPDATE on inventory_transactions ───────────────────────
CREATE TRIGGER trg_inv_txn_after_update
AFTER UPDATE ON inventory_transactions
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (
        user_id,
        user_name,
        module,
        action,
        record_id,
        old_data,
        new_data,
        created_at
    )
    SELECT
        NEW.user_id,
        COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System'),
        'inventory_transactions',
        'UPDATE',
        NEW.transaction_id,
        JSON_OBJECT(
            'inventory_item_id', OLD.inventory_item_id,
            'transaction_type',  OLD.transaction_type,
            'quantity',          OLD.quantity,
            'unit_cost',         OLD.unit_cost,
            'reference_no',      OLD.reference_no,
            'remarks',           OLD.remarks
        ),
        JSON_OBJECT(
            'inventory_item_id', NEW.inventory_item_id,
            'transaction_type',  NEW.transaction_type,
            'quantity',          NEW.quantity,
            'unit_cost',         NEW.unit_cost,
            'reference_no',      NEW.reference_no,
            'remarks',           NEW.remarks
        ),
        NOW()
    FROM dual
    LEFT JOIN user u ON u.id = NEW.user_id;

    INSERT INTO system_logs (user_id, action_type, description, created_at)
    SELECT
        NEW.user_id,
        'INVENTORY_UPDATE',
        CONCAT(
            'User ',
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), CONCAT('ID:', NEW.user_id), 'System'),
            ' updated inventory transaction #', NEW.transaction_id
        ),
        NOW()
    FROM dual
    LEFT JOIN user u ON u.id = NEW.user_id;
END$$

DELIMITER ;
