<?php
// generate-pdf-pd.php

require '../fpdf/fpdf.php'; // Ensure the path is correct
include '../includes/database.php'; // Ensure the path is correct

session_start();
$db = ConnectDB();

// Set error reporting (optional but recommended for debugging)
// Note: In production, it's better to log errors instead of displaying them.
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', '../pages/nigga.txt');

// Function to detect AJAX requests
function isAjax() {
    return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

header('Content-Type: text/html; charset=utf-8');
// Function to send JSON response and exit
function sendJsonResponse($response) {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isAjax()) {
        sendJsonResponse(['status' => 'error', 'message' => 'Unauthorized access. Please log in.']);
    } else {
        header("Location: ../index.php");
        exit();
    }
}

// Retrieve Project ID from GET parameters
$proj_ID = isset($_GET['proj_ID']) ? $_GET['proj_ID'] : null;

// Validate Project ID
if ($proj_ID === null) {
    if (isAjax()) {
        sendJsonResponse(['status' => 'error', 'message' => 'Invalid project ID.']);
    } else {
        header("Location: ../projects.php?status=error&message=Invalid project ID.");
        exit();
    }
}

// Function to handle SQL preparation and execution with error checking
function fetchData($db, $query, $param_type, $param_value) {
    $stmt = $db->prepare($query);
    if (!$stmt) {
        die("Prepare failed: (" . $db->errno . ") " . $db->error);
    }
    if ($param_type && $param_value) {
        $stmt->bind_param($param_type, $param_value);
    }
    if (!$stmt->execute()) {
        die("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    $result = $stmt->get_result();
    if (!$result) {
        die("Get result failed: (" . $stmt->errno . ") " . $stmt->error);
    }
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch Project Details
$projectQuery = "
    SELECT p.*, c.cont_name 
    FROM projects p
    LEFT JOIN project_contractors pc ON p.proj_ID = pc.proj_ID
    LEFT JOIN contractors c ON pc.cont_ID = c.cont_ID
    WHERE p.proj_ID = ? AND p.proj_isDeleted = 0
";

$projectResult = fetchData($db, $projectQuery, 's', $proj_ID);
$projectDetails = count($projectResult) > 0 ? $projectResult[0] : null;

if (!$projectDetails) {
    if (isAjax()) {
        sendJsonResponse(['status' => 'error', 'message' => 'No project details found.']);
    } else {
        header("Location: ../projects.php?status=error&message=No project details found.");
        exit();
    }
}

// Fetch related data
$cws_query = "SELECT * FROM `contract-work-suspension` WHERE proj_ID = ?";
$contractWorkSuspensions = fetchData($db, $cws_query, 's', $proj_ID);

$cwr_query = "SELECT * FROM `contract-work-resumption` WHERE proj_ID = ?";
$contractWorkResumptions = fetchData($db, $cwr_query, 's', $proj_ID);

$cte_query = "SELECT * FROM `contract-time-extension` WHERE proj_ID = ?";
$contractTimeExtensions = fetchData($db, $cte_query, 's', $proj_ID);

$mtsr_query = "SELECT * FROM `monthly-time-suspension-report` WHERE proj_ID = ?";
$monthlyTimeSuspensionReports = fetchData($db, $mtsr_query, 's', $proj_ID);

$vo_query = "SELECT * FROM `variation-orders` WHERE proj_ID = ?";
$variationOrders = fetchData($db, $vo_query, 's', $proj_ID);

$cm_query = "SELECT * FROM `contract-manpower` WHERE proj_ID = ?";
$contractorsManpower = fetchData($db, $cm_query, 's', $proj_ID);
$contractManpower = count($contractorsManpower) > 0 ? $contractorsManpower[0] : null;

$iom_query = "SELECT * FROM `implementing-office-manpower` WHERE proj_ID = ?";
$implementingOfficeManpower = fetchData($db, $iom_query, 's', $proj_ID);
$implementingOfficeManpower = count($implementingOfficeManpower) > 0 ? $implementingOfficeManpower[0] : null;

// Calculate Total Contract Duration
$totalContractDuration = $projectDetails['proj_cont_duration'];

if ($projectDetails['proj_unwork_days'] > 0) {
    $totalContractDuration += $projectDetails['proj_unwork_days'];
}

$sumCTEDays = 0;
foreach ($contractTimeExtensions as $cte) {
    $sumCTEDays += $cte['cte_ext_days'];
}
$totalContractDuration += $sumCTEDays;

// Close the database connection
$db->close();

// Function to format dates
function formatDate($date) {
    if ($date && $date !== '0000-00-00') {
        return date("F d, Y", strtotime($date));
    }
    return 'N/A';
}

// Function to safely retrieve data or return 'N/A'
function getValue($value) {
    return $value !== null && $value !== '' ? htmlspecialchars($value) : 'N/A';
}

// Initialize PDF with enhanced table handling
class PDF extends FPDF {
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
$pdf = new PDF();
$pdf->AliasNbPages(); // Needed for total page numbers
$pdf->SetMargins(10, 10, 10); // Set left, top, and right margins to 10 units
$pdf->AddPage();
$pdf->SetAutoPageBreak(true, 20);

// Set Font for Project Contract ID
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, "Project Contract ID: " . getValue($projectDetails['proj_ID']), 0, 1, 'C');

// ---------------------- Project Details Section ----------------------
$projectInfo = [
    "Component ID" => getValue($projectDetails['proj_comp_ID']),
    // Fix for the contract name
    "Contract Name" => getValue($projectDetails['proj_cont_name']), // Directly keep the en dash
    "Contract Location" => getValue($projectDetails['proj_cont_loc']),
    "Contractor" => getValue($projectDetails['cont_name']),
    "Contract Amount" => "PHP " . getValue(number_format($projectDetails['proj_cont_amt'], 2)),
    "Contract Duration" => getValue($totalContractDuration) . " days (Includes Extension Days)",
    "Predetermined Unworkable Days" => $projectDetails['proj_unwork_days'] > 0 ? getValue($projectDetails['proj_unwork_days']) . " days" : "No unworkable days"
];

// Prepare data for table
$projectData = [];
foreach ($projectInfo as $key => $value) {
    $projectData[] = [$key, $value];
}

// Define column widths (Total = 190)
$w_project = [60, 130];

// Define alignment array
$align_project = ['C', 'C']; // Center alignment for headers

// Define sub-headers
$subHeaders_project = ['Field', 'Details'];

// Add Project Details table
addSubsectionTable(
    $pdf,
    'Project Details',
    $subHeaders_project,
    $projectData,
    $w_project,
    $align_project
);

// ---------------------- Contract Dates Section ----------------------
$contractDates = [
    "Notice of Award" => formatDate($projectDetails['proj_NOA']),
    "Notice to Proceed" => formatDate($projectDetails['proj_NOP']),
    "Effectivity Date" => formatDate($projectDetails['proj_effect_date']),
    "Expiry Date" => formatDate($projectDetails['proj_expiry_date'])
];

// Prepare data for table
$contractData = [];
foreach ($contractDates as $key => $value) {
    $contractData[] = [$key, $value];
}

// Define column widths (Total = 190)
$w_contract = [60, 130];

// Define alignment array
$align_contract = ['C', 'C']; // Center alignment for headers

// Define sub-headers
$subHeaders_contract = ['Date Type', 'Date'];

// Add Contract Dates table
addSubsectionTable(
    $pdf,
    'Contract Dates',
    $subHeaders_contract,
    $contractData,
    $w_contract,
    $align_contract
);

// ---------------------- Contract Work Suspension Section ----------------------
$cws_subHeaders = ['Date of Letter Request', 'Reason for the Request', 'Suspended Days', 'Extension Days', 'Expiry Date'];

// Prepare data
$cwsData = [];
if (!empty($contractWorkSuspensions)) {
    foreach ($contractWorkSuspensions as $cws) {
        $cwsData[] = [
            formatDate($cws['cws_lr_date']),
            getValue($cws['cws_reason']),
            getValue($cws['cws_susp_days']) . " Days",
            getValue($cws['cws_ext_days']) . " Days",
            formatDate($cws['cws_expiry_date'])
        ];
    }
} else {
    // Add a row with placeholders to maintain column structure
    $cwsData[] = ["No records found.", "", "", "", ""];
}

// Define column widths (Total = 190)
$w_cws = [45, 55, 30, 30, 30];

// Define alignment array
$align_cws = ['C', 'C', 'C', 'C', 'C'];

// Add Contract Work Suspension table
addSubsectionTable(
    $pdf,
    'Contract Work Suspension',
    $cws_subHeaders,
    $cwsData,
    $w_cws,
    $align_cws
);

// ---------------------- Contract Work Resumption Section ----------------------
$cwr_subHeaders = ['Date of Letter Request', 'Reason for the Request', 'Resumed Days', 'Date of Approval'];

// Prepare data
$cwrData = [];
if (!empty($contractWorkResumptions)) {
    foreach ($contractWorkResumptions as $cwr) {
        $cwrData[] = [
            formatDate($cwr['cwr_lr_date']),
            getValue($cwr['cwr_reason']),
            getValue($cwr['cwr_susp_days']) . " Days",
            formatDate($cwr['cwr_approved_date'])
        ];
    }
} else {
    // Add a row with placeholders to maintain column structure
    $cwrData[] = ["No records found.", "", "", ""];
}

// Define column widths (Total = 190)
$w_cwr = [45, 80, 30, 35];

// Define alignment array
$align_cwr = ['C', 'C', 'C', 'C'];

// Add Contract Work Resumption table
addSubsectionTable(
    $pdf,
    'Contract Work Resumption',
    $cwr_subHeaders,
    $cwrData,
    $w_cwr,
    $align_cwr
);

// ---------------------- Contract Time Extension Section ----------------------
$cte_subHeaders = ['Date of Letter Request', 'Reason for the Request', 'Extended Days', 'Date of Approval'];

// Prepare data
$cteData = [];
if (!empty($contractTimeExtensions)) {
    foreach ($contractTimeExtensions as $cte) {
        $cteData[] = [
            formatDate($cte['cte_lr_date']),
            getValue($cte['cte_reason']),
            getValue($cte['cte_ext_days']) . " Days",
            formatDate($cte['cte_approved_date'])
        ];
    }
} else {
    // Add a row with placeholders to maintain column structure
    $cteData[] = ["No records found.", "", "", ""];
}

// Define column widths (Total = 190)
$w_cte = [45, 80, 30, 35];

// Define alignment array
$align_cte = ['C', 'C', 'C', 'C'];

// Add Contract Time Extension table
addSubsectionTable(
    $pdf,
    'Contract Time Extension',
    $cte_subHeaders,
    $cteData,
    $w_cte,
    $align_cte
);

// ---------------------- Monthly Time Suspension Report Section ----------------------
$mtsr_subHeaders = ['Date of Letter Request', 'Reason for the Request', 'Suspended Days', 'Date of Approval'];

// Prepare data
$mtsrData = [];
if (!empty($monthlyTimeSuspensionReports)) {
    foreach ($monthlyTimeSuspensionReports as $mtsr) {
        $mtsrData[] = [
            formatDate($mtsr['mtsr_lr_date']),
            getValue($mtsr['mtsr_reason']),
            getValue($mtsr['mtsr_susp_days']) . " Days",
            formatDate($mtsr['mtsr_approved_date'])
        ];
    }
} else {
    // Add a row with placeholders to maintain column structure
    $mtsrData[] = ["No records found.", "", "", ""];
}

// Define column widths (Total = 190)
$w_mtsr = [45, 80, 30, 35];

// Define alignment array
$align_mtsr = ['C', 'C', 'C', 'C'];

// Add Monthly Time Suspension Report table
addSubsectionTable(
    $pdf,
    'Monthly Time Suspension Report',
    $mtsr_subHeaders,
    $mtsrData,
    $w_mtsr,
    $align_mtsr
);

// ---------------------- Variation Orders Section ----------------------
$vo_subHeaders = ['Date of Request', 'Reason for the Request', 'Change in Amount', 'Revised Cost', 'Extension Days', 'Expiry Date'];

// Prepare data
$voData = [];
if (!empty($variationOrders)) {
    foreach ($variationOrders as $vo) {
        $voData[] = [
            formatDate($vo['vo_date_request']),
            getValue($vo['vo_reason']),
            "PHP " . getValue(number_format($vo['vo_amt_change'], 2)),
            "PHP " . getValue(number_format($vo['vo_revised_cost'], 2)),
            getValue($vo['vo_ext_days']) . " Days",
            formatDate($vo['vo_expiry_date'])
        ];
    }
} else {
    // Add a row with placeholders to maintain column structure
    $voData[] = ["No records found.", "", "", "", "", ""];
}

// Define column widths (Total = 190)
$w_vo = [30, 40, 35, 30, 30, 25];

// Define alignment array
$align_vo = ['C', 'C', 'C', 'C', 'C', 'C'];

// Add Variation Orders table
addSubsectionTable(
    $pdf,
    'Variation Orders',
    $vo_subHeaders,
    $voData,
    $w_vo,
    $align_vo
);


// ---------------------- Contractor's Manpower Section ----------------------
if ($contractManpower) {
    // Define sub-headers without 'Date of Expiry'
    $subHeaders_cm = ['Role', 'Name', 'PRC/ME ID No.'];

    // Prepare data without expiry dates
    $cmData = [
        ['Authorized Managing Officer', getValue($contractManpower['cm_am_officer']), 'N/A'],
        ['Project Manager', getValue($contractManpower['cm_pm_name']), getValue($contractManpower['cm_pm_prc_me_ID'])],
        ['Project Engineer', getValue($contractManpower['cm_pe_name']), getValue($contractManpower['cm_pe_prc_me_ID'])],
        ['Materials Engineer', getValue($contractManpower['cm_me_name']), getValue($contractManpower['cm_me_prc_me_ID'])],
        ['Construction Foreman', getValue($contractManpower['cm_const_foreman']), 'N/A'],
        ['Construction Safety & Health Officer', getValue($contractManpower['cm_csh_officer']), 'N/A']
    ];

    // Define column widths (Total = 190)
    $w_cm = [50, 60, 80]; // Adjusted to remove the fourth column

    // Define alignment array
    $align_cm = ['C', 'C', 'C']; // Center alignment for headers

    // Add Contractor's Manpower table
    addSubsectionTable(
        $pdf,
        "Contractor's Manpower",
        $subHeaders_cm,
        $cmData,
        $w_cm,
        $align_cm
    );
} else {
    // Define sub-headers without 'Date of Expiry'
    $subHeaders_cm_empty = ['Role', 'Name', 'PRC/ME ID No.'];

    // Define data
    $cmData_empty = ["No Contractor's Manpower records found.", "", ""];

    // Define column widths (Total = 190)
    $w_cm_empty = [50, 60, 80];

    // Define alignment array
    $align_cm_empty = ['C', 'C', 'C'];

    // Add Contractor's Manpower table with no data
    addSubsectionTable(
        $pdf,
        "Contractor's Manpower",
        $subHeaders_cm_empty,
        [$cmData_empty],
        $w_cm_empty,
        $align_cm_empty
    );
}
$pdf->Ln(5);

// ---------------------- Implementing Office Manpower Section ----------------------
if ($implementingOfficeManpower) {
    // Define sub-headers without 'Date of Expiry'
    $subHeaders_iom = ['Role', 'Name', 'PRC/ME ID No.'];

    // Prepare data without expiry dates
    $iomData = [
        ['Project Engineer', getValue($implementingOfficeManpower['iom_pe_name']), getValue($implementingOfficeManpower['iom_pe_prc_me_ID'])],
        ['Project Inspector', getValue($implementingOfficeManpower['iom_pi_name']), getValue($implementingOfficeManpower['iom_pi_prc_me_ID'])],
        ['Project Inspector (PCMA)', getValue($implementingOfficeManpower['iom_pi_pcma_name']), getValue($implementingOfficeManpower['iom_pi_pcma_prc_me_ID'])],
        ['Materials Engineer', getValue($implementingOfficeManpower['iom_me_name']), getValue($implementingOfficeManpower['iom_me_prc_me_ID'])],
        ['Materials-In-Charge', getValue($implementingOfficeManpower['iom_mic_name']), getValue($implementingOfficeManpower['iom_mic_prc_me_ID'])]
    ];

    // Define column widths (Total = 190)
    $w_iom = [50, 60, 80]; // Adjusted to remove the fourth column

    // Define alignment array
    $align_iom = ['C', 'C', 'C']; // Center alignment for headers

    // Add Implementing Office Manpower table
    addSubsectionTable(
        $pdf,
        "Implementing Office Manpower",
        $subHeaders_iom,
        $iomData,
        $w_iom,
        $align_iom
    );
} else {
    // Define sub-headers without 'Date of Expiry'
    $subHeaders_iom_empty = ['Role', 'Name', 'PRC/ME ID No.'];

    // Define data
    $iomData_empty = ["No Implementing Office Manpower records found.", "", ""];

    // Define column widths (Total = 190)
    $w_iom_empty = [50, 60, 80];

    // Define alignment array
    $align_iom_empty = ['C', 'C', 'C'];

    // Add Implementing Office Manpower table with no data
    addSubsectionTable(
        $pdf,
        "Implementing Office Manpower",
        $subHeaders_iom_empty,
        [$iomData_empty],
        $w_iom_empty,
        $align_iom_empty
    );
}




// ---------------------- Save PDF ----------------------
$filename = "uploads/project-details/project_details_{$proj_ID}.pdf";
$pdfOutputPath = "../" . $filename;

// Ensure the directory exists
$directory = dirname($pdfOutputPath);
if (!is_dir($directory)) {
    if (!mkdir($directory, 0755, true)) {
        if (isAjax()) {
            sendJsonResponse(['status' => 'error', 'message' => 'Failed to create directories.']);
        } else {
            die("Failed to create directories...");
        }
    }
}

// Save the PDF file
$pdf->Output('F', $pdfOutputPath);

// Check if PDF was successfully created
if (file_exists($pdfOutputPath)) {
    // Determine the base URL dynamically
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base_url = $protocol . $host;

    // Adjust the file URL based on your directory structure
    // Assuming 'uploads' is directly under the web root
    $pdfURL = $base_url . '/' . $filename;

    if (isAjax()) {
        sendJsonResponse(['status' => 'success', 'file_url' => $pdfURL]);
    } else {
        // Redirect with file link after PDF is generated
        header("Location: ../pages/projects.php?status=success&message=PDF generated successfully.&file=" . urlencode($filename) . "&proj_ID=" . urlencode($proj_ID));
    }
} else {
    if (isAjax()) {
        sendJsonResponse(['status' => 'error', 'message' => 'Failed to generate PDF.']);
    } else {
        // Redirect back with error
        header("Location: ../pages/projects.php?status=error&message=Failed to generate PDF.");
    }
}
exit();
?>
