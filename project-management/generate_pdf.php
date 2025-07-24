<?php
// generate_pdf.php

require '../fpdf/fpdf.php'; // Ensure the path is correct
include '../includes/database.php'; // Ensure the path is correct

session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

// Retrieve parameters from GET
$section_ID = isset($_GET['section_ID']) ? $_GET['section_ID'] : null;
$section_type = isset($_GET['section_type']) ? $_GET['section_type'] : null;
$download = isset($_GET['download']) ? true : false;
$view = isset($_GET['view']) ? true : false;

// Validate parameters
if ($section_ID === null || $section_type === null) {
    echo "Invalid request. Section ID and Type are required.";
    exit();
}

// Define a mapping from section type to database column and table
$sectionMappings = [
    "Contract Work Suspension" => [
        "column" => "cws_code",
        "table" => "contract-work-suspension"
    ],
    "Contract Work Resumption" => [
        "column" => "cwr_code",
        "table" => "contract-work-resumption"
    ],
    "Contract Time Extension" => [
        "column" => "cte_code",
        "table" => "contract-time-extension"
    ],
    "Monthly Time Suspension Report" => [
        "column" => "mtsr_code",
        "table" => "monthly-time-suspension-report"
    ],
    "Variation Order" => [
        "column" => "vo_code",
        "table" => "variation-orders"
    ],
    "Contract Manpower" => [
        "column" => "cm_mp_ID",
        "table" => "contract-manpower"
    ],
    "Implementing Office Manpower" => [
        "column" => "iom_ID",
        "table" => "implementing-office-manpower"
    ],
    "Final Completion" => [
        "column" => "fc_ID",
        "table" => "final-completion"
        // Attachments are removed, so no attachment fields
    ]
];

// Check if the section type exists in the mapping
if (!array_key_exists($section_type, $sectionMappings)) {
    echo "Unknown section type.";
    exit();
}

// Retrieve the mapping details
$mapping = $sectionMappings[$section_type];
$column = $mapping['column'];
$table = $mapping['table'];

// Fetch section details
$query = "SELECT * FROM `$table` WHERE `$column` = ?";
$stmt = $db->prepare($query);
if (!$stmt) {
    die("Prepare failed: (" . $db->errno . ") " . $db->error);
}
$stmt->bind_param('s', $section_ID);
$stmt->execute();
$result = $stmt->get_result();
$sectionDetails = $result->fetch_assoc();
$stmt->close();

if (!$sectionDetails) {
    echo "No records found for the specified section.";
    exit();
}

// Define label mappings for each table
$labelMappings = [
    'contract-work-suspension' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'cws_code' => ['label' => 'Work Suspension Code', 'type' => 'text'],
        'cws_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
        'cws_reason' => ['label' => 'Reason of Suspension', 'type' => 'text'],
        'cws_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
        'cws_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
    ],
    'contract-work-resumption' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'cwr_code' => ['label' => 'Work Resumption Code', 'type' => 'text'],
        'cwr_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
        'cwr_reason' => ['label' => 'Reason of Resumption', 'type' => 'text'],
        'cwr_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
        'cwr_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
    ],
    'contract-time-extension' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'cte_code' => ['label' => 'Time Extension Code', 'type' => 'text'],
        'cte_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
        'cte_reason' => ['label' => 'Reason of Time Extension', 'type' => 'text'],
        'cte_ext_days' => ['label' => 'Extension Days', 'type' => 'days'],
        'cte_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
    ],
    'monthly-time-suspension-report' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'mtsr_code' => ['label' => 'Monthly Time Suspension Report Code', 'type' => 'text'],
        'mtsr_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
        'mtsr_reason' => ['label' => 'Reason for Monthly Time Suspension Report', 'type' => 'text'],
        'mtsr_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
        'mtsr_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
    ],
    'variation-orders' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'vo_code' => ['label' => 'Variation Order Code', 'type' => 'text'],
        'vo_date_request' => ['label' => 'Request Date', 'type' => 'date'],
        'vo_reason' => ['label' => 'Reason for Variation Order', 'type' => 'text'],
        'vo_amt_change' => ['label' => 'Amount Change for Variation Order', 'type' => 'amount'],
        'vo_approval_date' => ['label' => 'Date of Approval', 'type' => 'date'],
    ],
    'contract-manpower' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'cm_am_officer' => ['label' => 'Authorizing Manager Officer Name', 'type' => 'text'],
        'cm_pm_name' => ['label' => 'Project Manager Name', 'type' => 'text'],
        'cm_pm_prc_me_ID' => ['label' => 'Project Manager PRC/ME ID', 'type' => 'text'],
        // 'cm_pm_expiry' => ['label' => 'Project Manager\'s Expiry Date', 'type' => 'date'], // Removed
        'cm_pe_name' => ['label' => 'Project Engineer Name', 'type' => 'text'],
        'cm_pe_prc_me_ID' => ['label' => 'Project Engineer\'s PRC/ME ID', 'type' => 'text'],
        // 'cm_pe_expiry' => ['label' => 'Project Engineer\'s Expiry Date', 'type' => 'date'], // Removed
        'cm_me_name' => ['label' => 'Mechanical Engineer Name', 'type' => 'text'],
        'cm_me_prc_me_ID' => ['label' => 'Mechanical Engineer\'s PRC/ME ID', 'type' => 'text'],
        // 'cm_me_expiry' => ['label' => 'Mechanical Engineer\'s Expiry Date', 'type' => 'date'], // Removed
        'cm_const_foreman' => ['label' => 'Construction Foreman', 'type' => 'text'],
        'cm_csh_officer' => ['label' => 'Construction Safety and Health Officer Name', 'type' => 'text'],
    ],
    'implementing-office-manpower' => [
        'proj_ID' => ['label' => 'Project ID', 'type' => 'text'],
        'iom_pe_name' => ['label' => 'Project Engineer Name', 'type' => 'text'],
        'iom_pe_prc_me_ID' => ['label' => 'Project Engineer\'s PRC/ME ID', 'type' => 'text'],
        // 'iom_pe_expiry' => ['label' => 'Project Engineer\'s Expiry Date', 'type' => 'date'], // Removed
        'iom_pi_name' => ['label' => 'Project Inspector Name', 'type' => 'text'],
        'iom_pi_prc_me_ID' => ['label' => 'Project Inspector\'s PRC/ME ID', 'type' => 'text'],
        // 'iom_pi_expiry' => ['label' => 'Project Inspector\'s Expiry Date', 'type' => 'date'], // Removed
        'iom_me_name' => ['label' => 'Materials Engineer Name', 'type' => 'text'],
        'iom_me_prc_me_ID' => ['label' => 'Materials Engineer\'s PRC/ME ID', 'type' => 'text'],
        // 'iom_me_expiry' => ['label' => 'Materials Engineer\'s Expiry Date', 'type' => 'date'], // Removed
        'iom_mic_name' => ['label' => 'Materials-In-Charge Name', 'type' => 'text'],
        'iom_mic_prc_me_ID' => ['label' => 'Materials-In-Charge\'s PRC/ME ID', 'type' => 'text'],
        // 'iom_mic_expiry' => ['label' => 'Materials-In-Charge\'s Expiry Date', 'type' => 'date'], // Removed
    ],
    'final-completion' => [
        'fc_ir_date' => ['label' => 'Final Completion Inspection Report Date', 'type' => 'date'],
        'fc_coc_date' => ['label' => 'Certificate of Completion Date', 'type' => 'date'],
        'fc_coa_date' => ['label' => 'Certificate of Acceptance Date', 'type' => 'date'],
    ],
];

// Get labels for the current table
$labels = isset($labelMappings[$table]) ? $labelMappings[$table] : [];

// Initialize PDF with enhanced table handling
class PDFSection extends FPDF {
    // Header
    function Header() {
        // Path to the logo image
        $logoPath = '../images/dpwh-icon.png';

        // Check if the logo image exists
        if (!file_exists($logoPath)) {
            die("Error: Logo image not found at '$logoPath'. Please ensure the image exists and is accessible.");
        }

        // Define margin values
        $leftMargin = 10;
        $rightMargin = 10;

        // Logo
        $logoWidth = 20; // Reduced width of the logo
        $logoHeight = 20; // Reduced height of the logo
        $logoXPosition = $leftMargin; // Left margin
        $logoYPosition = 10; // Top margin

        $this->Image($logoPath, $logoXPosition, $logoYPosition, $logoWidth, $logoHeight);

        // Set font for header text
        $this->SetFont('Arial', 'B', 14);

        // Reset X position to margin
        $this->SetXY($leftMargin, $logoYPosition);

        // Centered Title
        $this->Cell(0, 7, 'Department of Public Works and Highways', 0, 1, 'C');
        $this->SetFont('Arial', '', 12);
        $this->Cell(0, 7, 'Second District Engineering Office', 0, 1, 'C');
        $this->Cell(0, 7, 'Payawin, Gubat, Sorsogon', 0, 1, 'C');

        // Draw line under header
        $lineY = $this->GetY() + 5;
        $this->Line($leftMargin, $lineY, $this->GetPageWidth() - $rightMargin, $lineY);
        $this->Ln(10);
    }

    // Footer
    function Footer() {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Set font for footer
        $this->SetFont('Arial', 'I', 8);
        
        // Calculate width for left and right cells
        $pageWidth = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $halfWidth = $pageWidth / 2;

        // Left side: Page X of Y
        $this->Cell($halfWidth, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'L');

        // Right side: Generated by DPWH Monitoring System
        $this->Cell($halfWidth, 10, 'Generated by DPWH Monitoring System', 0, 0, 'R');
    }

    /**
     * AddTableHeader handles a single row of sub-headers.
     * @param array $subHeaders - Array of sub-header titles.
     * @param array $w - Array of column widths.
     * @param array $align - Array of alignments for each column.
     */
    function AddTableHeader($subHeaders, $w, $align = []) {
        // Set fill color for header (unchanged)
        $this->SetFillColor(100, 149, 237); // Cornflower Blue
        $this->SetDrawColor(0, 0, 0); // Black border color
        $this->SetFont('Arial', 'B', 10); // Bold font for header
        $this->SetTextColor(255, 255, 255); // White text

        // Calculate height based on number of header lines (assuming single line)
        $headerHeight = 8;

        // Draw each header cell with a border
        foreach ($subHeaders as $i => $header) {
            $this->Cell($w[$i], $headerHeight, $header, 1, 0, isset($align[$i]) ? $align[$i] : 'C', true);
        }
        $this->Ln();
    }

    /**
     * AddTableRowData handles word wrapping and dynamic row height.
     * Ensures that each cell's content aligns correctly within its column.
     * @param array $data - Array of cell data.
     * @param array $w - Array of column widths.
     * @param array $align - Array of alignments for each column.
    */
function AddTableRowData($data, $w, $align = []) {
    $this->SetFont('Arial', '', 12); // Adjusted font size
    $this->SetTextColor(0, 0, 0); // Black text

    // Define the new color #CBC6C4
    $rowColor = [203, 198, 196];

    // Calculate the maximum number of lines for the row
    $nb = 0;
    for ($i = 0; $i < count($data); $i++) {
        $nb = max($nb, $this->NbLines($w[$i], $data[$i]));
    }
    $h = 6 * $nb; // 6 is the line height

    // Issue a page break first if needed
    $this->CheckPageBreak($h);

    // Set fill color for the row
    $this->SetFillColor($rowColor[0], $rowColor[1], $rowColor[2]);

    // Set draw color for borders
    $this->SetDrawColor(0, 0, 0); // Black border

    // Draw cell borders and fill
    $x = $this->GetX();
    $y = $this->GetY();
    foreach ($data as $i => $cell) {
        $a = isset($align[$i]) ? $align[$i] : 'L'; // Default to left alignment
        $this->Rect($x, $y, $w[$i], $h, 'DF'); // Fill and Draw the cell with borders
        $this->MultiCell($w[$i], 6, $cell, 0, $a, false); // No additional border
        $x += $w[$i];
        $this->SetXY($x, $y);
    }

    // Move to the next line
    $this->Ln($h);
}


    /**
     * Compute the number of lines a MultiCell of width w will take
     * @param float $w - Width of the cell
     * @param string $txt - Text content
     * @return int - Number of lines
     */
    function NbLines($w, $txt) {
        // Calculate the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 and $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += isset($cw[$c]) ? $cw[$c] : 0;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }

    function CheckPageBreak($h) {
        if ($this->GetY() + $h > $this->PageBreakTrigger)
            $this->AddPage($this->CurOrientation);
    }

    /**
     * Add Subsection Tables
     * @param string $title - Title of the subsection
     * @param array $subHeaders - Sub-headers for the table
     * @param array $data - Data rows for the table
     * @param array $w - Column widths
     * @param array $align - Column alignments
     */
    function AddSubsectionTable($title, $subHeaders, $data, $w, $align = []) {
        // Add section title
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, $title, 0, 1, 'L');

        // Add table headers
        $this->AddTableHeader($subHeaders, $w, $align);

        // Add table data
        foreach ($data as $row) {
            $this->AddTableRowData($row, $w, $align);
        }

        $this->Ln(5);
    }
}

/**
 * Function to Add Subsections as Tables with Separate Sub-Headers
 * @param PDF $pdf - FPDF instance
 * @param string $title - Section title
 * @param array $subHeaders - Array containing the sub-header titles
 * @param array $data - Array of data rows
 * @param array $w - Array of column widths
 * @param array $align - Array of alignments for each column
 */
function addSubsectionTable($pdf, $title, $subHeaders, $data, $w, $align = []) {
    $pdf->AddSubsectionTable($title, $subHeaders, $data, $w, $align);
}

// Create PDF instance
$pdf = new PDFSection();
$pdf->AliasNbPages(); // Needed for total page numbers
$pdf->SetMargins(10, 10, 10); // Set left, top, and right margins to 10 units
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Add Section Title
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, $section_type, 0, 1, 'C');
$pdf->Ln(2); // Small space after title

// Add Section ID
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 10, "Section ID: " . getValue($section_ID), 0, 1, 'C');
$pdf->Ln(5); // Small space after section ID

// Prepare data for table
$tableData = [];
foreach ($labels as $field => $info) {
    if (isset($sectionDetails[$field])) {
        $label = $info['label'];
        $type = $info['type'];
        $value = $sectionDetails[$field];

        // Handle formatting based on type
        if ($type == 'date') {
            $formattedValue = formatDate($value);
        } elseif ($type == 'amount') {
            $formattedValue = "₱" . number_format($value, 2);
        } elseif ($type == 'days') {
            $formattedValue = number_format($value) . " Days";
        } else {
            $formattedValue = htmlspecialchars($value);
        }

        $tableData[] = [$label, $formattedValue];
    }
}

// Define column widths
$w_section = [60, 130];

// Add Subsection Table
addSubsectionTable(
    $pdf,
    "{$section_type} Details",
    ['Field', 'Details'],
    $tableData,
    $w_section,
    ['C', 'C'] // Center alignment
);

// ---------------------- Save PDF ----------------------
$folderName = strtolower(str_replace(' ', '_', $section_type));
$uploadPath = "../uploads/$folderName";

// Ensure the directory exists
if (!is_dir($uploadPath)) {
    if (!mkdir($uploadPath, 0755, true)) {
        die("Failed to create directories...");
    }
}

// Sanitize section type for filename (replace spaces with underscores)
$sanitizedSectionType = strtolower(str_replace(' ', '_', $section_type));
$filename = "{$sanitizedSectionType}_{$section_ID}.pdf";
$pdfOutputPath = "$uploadPath/$filename";

// Check if PDF already exists; generate if it doesn't
if (!file_exists($pdfOutputPath)) {
    // Create PDF instance
    $pdf = new PDFSection();
    $pdf->SetMargins(10, 10, 10); // Set left, top, and right margins to 10 units
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // Add Section Title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->Cell(0, 10, $section_type, 0, 1, 'C');
    $pdf->Ln(2); // Small space after title

    // Add Section ID
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, "Section ID: " . getValue($section_ID), 0, 1, 'C');
    $pdf->Ln(5); // Small space after section ID

    // Prepare data for table
    $tableData = [];
    foreach ($labels as $field => $info) {
        if (isset($sectionDetails[$field])) {
            $label = $info['label'];
            $type = $info['type'];
            $value = $sectionDetails[$field];

            // Handle formatting based on type
            if ($type == 'date') {
                $formattedValue = formatDate($value);
            } elseif ($type == 'amount') {
                $formattedValue = "₱" . number_format($value, 2);
            } elseif ($type == 'days') {
                $formattedValue = number_format($value) . " Days";
            } else {
                $formattedValue = htmlspecialchars($value);
            }

            $tableData[] = [$label, $formattedValue];
        }
    }

    // Define column widths
    $w_section = [60, 130];

    // Add Subsection Table
    addSubsectionTable(
        $pdf,
        "{$section_type} Details",
        ['Field', 'Details'],
        $tableData,
        $w_section,
        ['C', 'C'] // Center alignment
    );

    // Save the PDF file
    $pdf->Output('F', $pdfOutputPath);
}

// Handle PDF view if requested
if ($view) {
    if (file_exists($pdfOutputPath)) {
        // Output the PDF content
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($filename) . '"');
        readfile($pdfOutputPath);
        exit();
    } else {
        echo "PDF file not found.";
        exit();
    }
} elseif ($download) {
    // Handle PDF download if requested
    if (file_exists($pdfOutputPath)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        readfile($pdfOutputPath);
        exit();
    } else {
        echo "PDF file not found.";
        exit();
    }
} else {
    // For AJAX requests or other calls, output JSON
    echo json_encode(['status' => 'success', 'message' => 'PDF generated successfully.']);
    exit();
}
?>
