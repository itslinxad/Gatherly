<?php
require_once '../../../config/database.php';
require_once '../../../src/services/functions.php';

requireEmployee();

$db = new Database();
$conn = $db->getConnection();

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Get all inventory data
$sql = "
    SELECT 
        i.name AS ingredient_name,
        inv.quantity,
        inv.unit,
        inv.reorder_level,
        inv.last_restocked,
        CASE 
            WHEN inv.quantity = 0 THEN 'Out of Stock'
            WHEN inv.quantity <= inv.reorder_level THEN 'Low Stock'
            ELSE 'In Stock'
        END AS status
    FROM ingredients i
    JOIN inventory inv ON i.id = inv.ingredient_id
    ORDER BY i.name
";

$stmt = $conn->query($sql);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    // CSV Export
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_export_' . date('Y-m-d_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Add headers
    fputcsv($output, ['Ingredient Name', 'Quantity', 'Unit', 'Reorder Level', 'Status', 'Last Restocked']);

    // Add data
    foreach ($inventory as $item) {
        fputcsv($output, [
            $item['ingredient_name'],
            $item['quantity'],
            $item['unit'],
            $item['reorder_level'],
            $item['status'],
            $item['last_restocked'] ? date('M d, Y H:i', strtotime($item['last_restocked'])) : 'Never'
        ]);
    }

    fclose($output);
    exit;
}

if ($format === 'pdf') {
    // PDF Export
    define('FPDF_FONTPATH', '../../../src/fpdf_fonts/');
    require_once '../../../src/fpdf.php';

    $pdf = new FPDF('L', 'mm', 'A4'); // Landscape orientation
    $pdf->AddPage();

    // Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, 'Bros Cafe - Inventory Report', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 5, 'Generated on: ' . date('F d, Y h:i A'), 0, 1, 'C');
    $pdf->Ln(5);

    // Table header
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(59, 130, 246); // Blue background
    $pdf->SetTextColor(255, 255, 255); // White text
    $pdf->SetDrawColor(59, 130, 246); // Blue border

    // Column widths
    $colWidths = [80, 30, 20, 30, 35, 50];

    $pdf->Cell($colWidths[0], 8, 'Ingredient Name', 1, 0, 'L', true);
    $pdf->Cell($colWidths[1], 8, 'Quantity', 1, 0, 'C', true);
    $pdf->Cell($colWidths[2], 8, 'Unit', 1, 0, 'C', true);
    $pdf->Cell($colWidths[3], 8, 'Reorder Level', 1, 0, 'C', true);
    $pdf->Cell($colWidths[4], 8, 'Status', 1, 0, 'C', true);
    $pdf->Cell($colWidths[5], 8, 'Last Restocked', 1, 1, 'C', true);

    // Table data
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0); // Black text
    $fill = false;

    foreach ($inventory as $item) {
        // Set status color
        if ($item['status'] === 'Out of Stock') {
            $pdf->SetFillColor(254, 202, 202); // Red
        } elseif ($item['status'] === 'Low Stock') {
            $pdf->SetFillColor(254, 243, 199); // Yellow
        } else {
            $pdf->SetFillColor(240, 240, 240); // Light gray
        }

        $lastRestocked = $item['last_restocked'] ? date('M d, Y H:i', strtotime($item['last_restocked'])) : 'Never';

        $pdf->Cell($colWidths[0], 7, $item['ingredient_name'], 1, 0, 'L', true);
        $pdf->Cell($colWidths[1], 7, $item['quantity'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 7, $item['unit'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 7, $item['reorder_level'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[4], 7, $item['status'], 1, 0, 'C', true);
        $pdf->Cell($colWidths[5], 7, $lastRestocked, 1, 1, 'C', true);

        $fill = !$fill;
    }

    // Summary
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 10);
    $totalItems = count($inventory);
    $outOfStock = count(array_filter($inventory, fn($item) => $item['status'] === 'Out of Stock'));
    $lowStock = count(array_filter($inventory, fn($item) => $item['status'] === 'Low Stock'));

    $pdf->Cell(0, 6, 'Summary: Total Items: ' . $totalItems . ' | Out of Stock: ' . $outOfStock . ' | Low Stock: ' . $lowStock, 0, 1);

    // Output PDF
    $pdf->Output('D', 'inventory_report_' . date('Y-m-d_His') . '.pdf');
    exit;
}

// If format not supported
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Unsupported format']);
