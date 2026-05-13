-- Generated SQL INSERTs for inventory_transactions
-- Date: 2026-01-29
-- Note: inventory_item_id is looked up by item_name; if not found, 0 is used as fallback.

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Airconditioner' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0001', 'In use', '2020-08-03 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Computer Laptop' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0002', 'Ongoing use', '2023-11-30 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop Computer' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0003', 'Active', '2023-11-06 00:00:00');

-- Transfer treated as OUT (stock moved/assigned)
INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop Computer' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0004', 'Defective', '2023-11-06 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop Computer' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0005', 'Reserved', '2023-11-06 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Airconditioner' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0006', 'Active', '2023-07-12 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Router Board' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0007', 'Active', '2023-06-22 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'UPS' LIMIT 1), 0), 'OUT', 1, 'REF-2019-0008', '', '2019-10-11 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop Computer' LIMIT 1), 0), 'OUT', 1, 'REF-2017-0009', '', '2017-02-01 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Printer' LIMIT 1), 0), 'OUT', 1, 'REF-2015-0010', '', '2015-03-10 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop Computer' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0011', 'Active', '2023-11-06 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Vehicle' LIMIT 1), 0), 'OUT', 1, 'REF-2023-0012', 'Active', '2023-06-09 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Television' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0013', 'Active', '2020-08-03 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Airconditioner' LIMIT 1), 0), 'OUT', 1, 'REF-2019-0014', 'Active', '2019-10-17 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Airconditioner' LIMIT 1), 0), 'OUT', 1, 'REF-2019-0015', 'Active', '2019-10-17 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Conference Table' LIMIT 1), 0), 'OUT', 1, 'REF-2022-0016', '', '2022-04-13 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0017', '', '2020-09-04 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Desktop' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0018', 'Active', '2020-09-04 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Projector' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0019', '', '2020-08-12 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Airconditioner' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0020', 'Active', '2020-08-03 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Television' LIMIT 1), 0), 'OUT', 1, 'REF-2020-0021', 'Active', '2020-01-24 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Aircondition' LIMIT 1), 0), 'OUT', 1, 'REF-2019-0022', 'Active', '2019-10-17 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Printer' LIMIT 1), 0), 'OUT', 1, 'REF-2017-0023', 'Turnover to GSO dated JAN. 2024', '2017-01-26 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Printer' LIMIT 1), 0), 'OUT', 1, 'REF-2015-0024', 'Active', '2015-03-10 00:00:00');

INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date)
VALUES (COALESCE((SELECT id FROM inventory_items WHERE item_name = 'Computer Desktop' LIMIT 1), 0), 'OUT', 1, 'REF-2015-0025', 'Part of computer set', '2015-02-26 00:00:00');
