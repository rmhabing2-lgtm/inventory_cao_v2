<?php
require_once __DIR__ . '/../includes/session.php';
require_login();
include('conn.php'); // Use your existing database connection

// Include PhpSpreadsheet library
require 'PhpSpreadsheet/vendor/autoload.php'; // Adjusted path based on your directory structure

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Check if product IDs are selected
if (isset($_POST['product_ids']) && !empty($_POST['product_ids'])) {
    $product_ids = $_POST['product_ids'];

    // Prepare SQL query to fetch the selected products
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));
    $types = str_repeat('i', count($product_ids)); // assuming product_id is an integer
    $query = "SELECT delivery_date, product_name, company, dr_number, sales_invoice, scale_number, quantity, product_amount 
              FROM product 
              WHERE product_id IN ($placeholders)";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die("Query preparation failed: " . $conn->error);
    }

    $stmt->bind_param($types, ...$product_ids);
    $stmt->execute();
    $result = $stmt->get_result();

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set Excel headers
    $headers = ['Delivery Date', 'Product Name', 'Company', 'DR Number', 'Sales Invoice', 'Scale Number', 'Quantity (kg)', 'Amount'];
    $column = 'A'; // Starting column in Excel

    foreach ($headers as $header) {
        $sheet->setCellValue($column . '1', $header); // Fill header row
        $column++;
    }

    // Fill data into the Excel sheet
    $rowCount = 2; // Start from row 2 as row 1 is for headers
    while ($row = $result->fetch_assoc()) {
        $sheet->setCellValue('A' . $rowCount, $row['delivery_date']);
        $sheet->setCellValue('B' . $rowCount, $row['product_name']);
        $sheet->setCellValue('C' . $rowCount, $row['company']);
        $sheet->setCellValue('D' . $rowCount, $row['dr_number']);
        $sheet->setCellValue('E' . $rowCount, $row['sales_invoice']);
        $sheet->setCellValue('F' . $rowCount, $row['scale_number']);
        $sheet->setCellValue('G' . $rowCount, $row['quantity']);
        $sheet->setCellValue('H' . $rowCount, $row['product_amount']);
        $rowCount++;
    }

    // Set response headers to download the file
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="selected_products.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');

    exit;
} else {
    echo "No products selected.";
}
