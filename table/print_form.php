<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
require_once 'config.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    die("Invalid Request: No ID provided.");
}

// Fetch the specific accountable item details
$query = "
    SELECT 
        ai.*, 
        ii.item_name,
        ii.item_status
    FROM accountable_items ai
    INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id
    WHERE ai.id = ? AND ai.is_deleted = 0
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    die("Record not found or has been deleted.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print PAR - <?= htmlspecialchars($data['are_mr_ics_num']) ?></title>
    <style>
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
        }
        body {
            font-family: 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            background: #fff;
            margin: 40px auto;
            max-width: 800px;
            padding: 20px;
            border: 1px solid #eee;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h2 { margin: 5px 0; text-transform: uppercase; font-size: 16pt; }
        .header p { margin: 0; font-size: 10pt; }
        
        .doc-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            font-size: 14pt;
            margin: 20px 0;
        }

        .meta-info {
            width: 100%;
            margin-bottom: 20px;
        }
        .meta-info td { padding: 4px 0; }
        .label { width: 150px; font-weight: bold; }
        .underline { border-bottom: 1px solid #000; display: inline-block; min-width: 200px; }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th, .items-table td {
            border: 1px solid #000;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .items-table th { background: #f2f2f2; text-align: center; }

        .signatures {
            margin-top: 60px;
            display: flex;
            justify-content: space-between;
        }
        .sig-box {
            width: 45%;
            text-align: center;
        }
        .sig-line {
            border-top: 1px solid #000;
            margin-top: 50px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .sig-sub { font-size: 10pt; }

        .footer-note {
            margin-top: 50px;
            font-size: 9pt;
            font-style: italic;
            border-top: 1px solid #ccc;
            padding-top: 10px;
        }
    </style>
</head>
<body>

    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px;">
            Click to Print
        </button>
    </div>

    <div class="header">
        <p>Republic of the Philippines</p>
        <h2>City Accountant's Office</h2>
        <p>General Santos City</p>
    </div>

    <div class="doc-title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>

    <table class="meta-info">
        <tr>
            <td class="label">Entity Name:</td>
            <td colspan="3">Local Government Unit of General Santos City - City Accountant's Office</td>
        </tr>
        <tr>
            <td class="label">Fund Cluster:</td>
            <td>General Fund</td>
            <td class="label" style="text-align:right">PAR No.:</td>
            <td style="padding-left: 10px;"><?= htmlspecialchars($data['are_mr_ics_num']) ?></td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50px;">Qty</th>
                <th style="width: 80px;">Unit</th>
                <th>Description</th>
                <th>Property Number</th>
                <th>Acq. Date</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="text-align: center;"><?= (int)$data['assigned_quantity'] ?></td>
                <td style="text-align: center;">Unit</td>
                <td>
                    <strong><?= htmlspecialchars($data['item_name']) ?></strong><br>
                    <small>
                        Serial: <?= htmlspecialchars($data['serial_number'] ?: 'N/A') ?><br>
                        PO No: <?= htmlspecialchars($data['po_number'] ?: 'N/A') ?><br>
                        Status: <?= htmlspecialchars($data['condition_status']) ?><br>
                        Remarks: <?= htmlspecialchars($data['remarks']) ?>
                    </small>
                </td>
                <td style="text-align: center;"><?= htmlspecialchars($data['property_number'] ?: '—') ?></td>
                <td style="text-align: center;"><?= date('Y-m-d', strtotime($data['date_assigned'])) ?></td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="sig-box">
            <p style="text-align: left;">Received by:</p>
            <div class="sig-line"><?= htmlspecialchars($data['person_name']) ?></div>
            <div class="sig-sub">Signature Over Printed Name</div>
            <div class="sig-sub">End-User / Recipient</div>
        </div>
        
        <div class="sig-box">
            <p style="text-align: left;">Issued by:</p>
            <div class="sig-line">PROPERTY OFFICER NAME</div>
            <div class="sig-sub">Signature Over Printed Name</div>
            <div class="sig-sub">Admin Division</div>
        </div>
    </div>

    <div class="footer-note">
        This document serves as an official record of accountability. Any loss or damage due to negligence must be reported immediately to the City Accountant's Office.
    </div>

</body>
</html>