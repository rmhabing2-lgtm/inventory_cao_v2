<?php
/**
 * Incident Report PDF Generator
 * Purpose: Generate formal LGU/COA-compliant incident reports for property accountability
 * Usage: print_incident.php?id={incident_id}
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/session.php';

// Verify user is admin
if ($userRole !== 'ADMIN') {
    http_response_code(403);
    die("Unauthorized access. Admin role required.");
}

$incident_id = intval($_GET['id'] ?? 0);
if (!$incident_id) {
    die("Invalid incident ID.");
}

// Fetch incident details
$query = $conn->prepare("
    SELECT ir.*, 
           bi.reference_no, bi.borrow_date, bi.expected_return_date,
           ii.item_name, ii.property_number, ii.value_amount,
           e.FIRSTNAME, e.LASTNAME, e.POSITION, d.DEPT_NAME,
           admin.FIRSTNAME as admin_first, admin.LASTNAME as admin_last
    FROM incident_reports ir
    JOIN borrowed_items bi ON ir.borrow_id = bi.borrow_id
    JOIN inventory_items ii ON bi.inventory_item_id = ii.id
    LEFT JOIN cao_employee e ON bi.borrower_employee_id = e.ID
    LEFT JOIN department d ON e.DEPARTMENT = d.ID
    LEFT JOIN cao_employee admin ON ir.reported_by = admin.ID
    WHERE ir.id = ?
");
$query->bind_param("i", $incident_id);
$query->execute();
$data = $query->get_result()->fetch_assoc();

if (!$data) {
    die("Incident report not found.");
}

// Calculate days overdue
$days_overdue = round((time() - strtotime($data['expected_return_date'])) / 86400);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Incident Report - <?= htmlspecialchars($data['reference_no']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Arial', 'Segoe UI', sans-serif;
            color: #333;
            line-height: 1.6;
            padding: 40px;
            background: #f5f5f5;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #000;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .header p {
            font-size: 12px;
            color: #666;
        }
        
        .report-title {
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin: 15px 0;
            color: #d32f2f;
        }
        
        .section {
            margin-bottom: 20px;
        }
        
        .section-title {
            background: #e0e0e0;
            padding: 8px 12px;
            font-weight: bold;
            font-size: 13px;
            text-transform: uppercase;
            border-left: 4px solid #d32f2f;
            margin-bottom: 10px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 12px;
        }
        
        .info-label {
            font-weight: bold;
            width: 35%;
        }
        
        .info-value {
            width: 65%;
        }
        
        .description-box {
            border: 1px solid #ccc;
            padding: 12px;
            min-height: 80px;
            font-size: 12px;
            background: #fafafa;
            margin-bottom: 15px;
        }
        
        .alert-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 12px;
            margin: 15px 0;
            font-size: 12px;
            border-radius: 4px;
        }
        
        .alert-box strong {
            color: #d32f2f;
        }
        
        .signature-section {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
        }
        
        .signature-box {
            width: 35%;
            text-align: center;
            padding-top: 20px;
        }
        
        .sig-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
        }
        
        .sig-label {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .sig-sublabel {
            font-size: 10px;
            color: #666;
        }
        
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ccc;
            font-size: 10px;
            color: #999;
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        @media print {
            body {
                padding: 0;
                background: none;
            }
            .container {
                box-shadow: none;
                max-width: 100%;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Local Government Unit - CAO</h1>
            <h2>Property & Asset Management Division</h2>
            <p class="report-title">Incident & Accountability Report</p>
        </div>

        <!-- Filing Information -->
        <div class="section">
            <div class="section-title">Filing Information</div>
            <div class="info-row">
                <span class="info-label">Report ID:</span>
                <span class="info-value"><strong><?= htmlspecialchars($data['reference_no']) ?></strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">Date Filed:</span>
                <span class="info-value"><?= date('F d, Y', strtotime($data['created_at'])) ?> at <?= date('H:i A', strtotime($data['created_at'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="info-value"><span class="badge badge-danger"><?= strtoupper($data['status']) ?></span></span>
            </div>
        </div>

        <!-- Staff/Accountable Officer Information -->
        <div class="section">
            <div class="section-title">Accountable Officer Information</div>
            <div class="info-row">
                <span class="info-label">Name:</span>
                <span class="info-value"><?= htmlspecialchars(($data['FIRSTNAME'] ?? '') . ' ' . ($data['LASTNAME'] ?? '')) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Position:</span>
                <span class="info-value"><?= htmlspecialchars($data['POSITION'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Department/Office:</span>
                <span class="info-value"><?= htmlspecialchars($data['DEPT_NAME'] ?? 'N/A') ?></span>
            </div>
        </div>

        <!-- Property/Item Details -->
        <div class="section">
            <div class="section-title">Property Details</div>
            <div class="info-row">
                <span class="info-label">Item Name:</span>
                <span class="info-value"><?= htmlspecialchars($data['item_name']) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Property Number:</span>
                <span class="info-value"><?= htmlspecialchars($data['property_number'] ?? 'N/A') ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Item Value:</span>
                <span class="info-value">₱ <?= number_format($data['value_amount'] ?? 0, 2) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Borrow Date:</span>
                <span class="info-value"><?= date('F d, Y', strtotime($data['borrow_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Expected Return Date:</span>
                <span class="info-value"><?= date('F d, Y', strtotime($data['expected_return_date'])) ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Days Overdue:</span>
                <span class="info-value"><strong><?= $days_overdue ?> days</strong></span>
            </div>
        </div>

        <!-- Incident Details -->
        <div class="section">
            <div class="section-title">Incident Classification</div>
            <div class="info-row">
                <span class="info-label">Incident Type:</span>
                <span class="info-value"><span class="badge badge-danger"><?= strtoupper($data['incident_type']) ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-label">Severity Level:</span>
                <span class="info-value"><span class="badge badge-warning"><?= htmlspecialchars($data['severity']) ?></span></span>
            </div>
            <div class="info-row">
                <span class="info-label">Estimated Cost/Loss:</span>
                <span class="info-value"><strong>₱ <?= number_format($data['estimated_cost'], 2) ?></strong></span>
            </div>
        </div>

        <!-- Description of Incident -->
        <div class="section">
            <div class="section-title">Description & Findings</div>
            <div class="description-box">
                <?= nl2br(htmlspecialchars($data['description'])) ?>
            </div>
        </div>

        <!-- Alert Box -->
        <div class="alert-box">
            <strong>⚠️ Accountability Notice:</strong> As per LGU and Commission on Audit (COA) regulations, 
            the accountable officer listed above is responsible for this incident. This document serves as formal 
            evidence for salary deductions, property write-offs, or further administrative action.
        </div>

        <!-- Signature Section -->
        <div class="signature-section">
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label"><?= htmlspecialchars(($data['FIRSTNAME'] ?? '') . ' ' . ($data['LASTNAME'] ?? '')) ?></div>
                <div class="sig-sublabel">Accountable Officer / Borrower</div>
            </div>
            
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label"><?= htmlspecialchars(($data['admin_first'] ?? 'ADMIN') . ' ' . ($data['admin_last'] ?? 'REVIEW')) ?></div>
                <div class="sig-sublabel">Property Custodian / Inspector</div>
            </div>
            
            <div class="signature-box">
                <div class="sig-line"></div>
                <div class="sig-label">DEPARTMENT HEAD</div>
                <div class="sig-sublabel">Approved By</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p>This is an official record generated by the Property Management System on <?= date('F d, Y \a\t H:i:s') ?></p>
            <p>For inquiries, contact the CAO Property Management Division</p>
        </div>
    </div>

    <script>
        // Auto-print on load
        window.addEventListener('load', function() {
            setTimeout(() => window.print(), 500);
        });
    </script>
</body>
</html>