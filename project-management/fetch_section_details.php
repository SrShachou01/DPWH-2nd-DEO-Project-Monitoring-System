<?php
include '../includes/database.php';

$db = ConnectDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section_ID = $_POST['section_ID'];
    $section_type = $_POST['section_type'];

    $labelMappings = [
        'contract-work-suspension' => [
            'cws_code' => ['label' => 'Work Suspension Code', 'type' => 'text'],
            'cws_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
            'cws_reason' => ['label' => 'Reason of Suspension', 'type' => 'text'],
            'cws_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
            'cws_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
        ],
        'contract-work-resumption' => [
            'cwr_code' => ['label' => 'Work Resumption Code', 'type' => 'text'],
            'cwr_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
            'cwr_reason' => ['label' => 'Reason of Resumption', 'type' => 'text'],
            'cwr_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
            'cwr_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
        ],
        'contract-time-extension' => [
            'cte_code' => ['label' => 'Time Extension Code', 'type' => 'text'],
            'cte_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
            'cte_reason' => ['label' => 'Reason of Time Extension', 'type' => 'text'],
            'cte_ext_days' => ['label' => 'Extension Days', 'type' => 'days'],
            'cte_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
        ],
        'monthly-time-suspension-report' => [
            'mtsr_code' => ['label' => 'Monthly Time Suspension Report Code', 'type' => 'text'],
            'mtsr_lr_date' => ['label' => 'Letter Request Date', 'type' => 'date'],
            'mtsr_reason' => ['label' => 'Reason for Monthly Time Suspension Report', 'type' => 'text'],
            'mtsr_susp_days' => ['label' => 'Suspended Days', 'type' => 'days'],
            'mtsr_approved_date' => ['label' => 'Date Approved', 'type' => 'date'],
        ],
        'variation-orders' => [
            'vo_code' => ['label' => 'Variation Order Code', 'type' => 'text'],
            'vo_date_request' => ['label' => 'Request Date', 'type' => 'date'],
            'vo_reason' => ['label' => 'Reason for Variation Order', 'type' => 'text'],
            'vo_amt_change' => ['label' => 'Amount Change for Variation Order', 'type' => 'amount'],
            'vo_approval_date' => ['label' => 'Date of Approval', 'type' => 'date'],
        ],
        'contract-manpower' => [
            'cm_am_officer' => ['label' => 'Authorizing Manager Officer Name', 'type' => 'text'],
            'cm_pm_name' => ['label' => 'Project Manager Name', 'type' => 'text'],
            'cm_pm_prc_me_ID' => ['label' => 'Project Manager PRC/ME ID', 'type' => 'text'],
            'cm_pm_expiry' => ['label' => 'Project Manager\'s Expiry Date', 'type' => 'date'],
            'cm_pe_name' => ['label' => 'Project Engineer Name', 'type' => 'text'],
            'cm_pe_prc_me_ID' => ['label' => 'Project Engineer\'s PRC/ME ID', 'type' => 'text'],
            'cm_pe_expiry' => ['label' => 'Project Engineer\'s Expiry Date', 'type' => 'date'],
            'cm_me_name' => ['label' => 'Mechanical Engineer Name', 'type' => 'text'],
            'cm_me_prc_me_ID' => ['label' => 'Mechanical Engineer\'s PRC/ME ID', 'type' => 'text'],
            'cm_me_expiry' => ['label' => 'Mechanical Engineer\'s Expiry Date', 'type' => 'date'],
            'cm_const_foreman' => ['label' => 'Construction Foreman', 'type' => 'text'],
            'cm_csh_officer' => ['label' => 'Construction Safety and Health Officer Name', 'type' => 'text'],
        ],
        'implementing-office-manpower' => [
            'iom_pe_name' => ['label' => 'Project Engineer Name', 'type' => 'text'],
            'iom_pe_prc_me_ID' => ['label' => 'Project Engineer\'s PRC/ME ID', 'type' => 'text'],
            'iom_pe_expiry' => ['label' => 'Project Engineer\'s Expiry Date', 'type' => 'date'],
            'iom_pi_name' => ['label' => 'Project Inspector Name', 'type' => 'text'],
            'iom_pi_prc_me_ID' => ['label' => 'Project Inspector\'s PRC/ME ID', 'type' => 'text'],
            'iom_pi_expiry' => ['label' => 'Project Inspector\'s Expiry Date', 'type' => 'date'],
            'iom_me_name' => ['label' => 'Materials Engineer Name', 'type' => 'text'],
            'iom_me_prc_me_ID' => ['label' => 'Materials Engineer\'s PRC/ME ID', 'type' => 'text'],
            'iom_me_expiry' => ['label' => 'Materials Engineer\'s Expiry Date', 'type' => 'date'],
            'iom_mic_name' => ['label' => 'Materials-In-Charge Name', 'type' => 'text'],
            'iom_mic_prc_me_ID' => ['label' => 'Materials-In-Charge\'s PRC/ME ID', 'type' => 'text'],
            'iom_mic_expiry' => ['label' => 'Materials-In-Charge\'s Expiry Date', 'type' => 'date'],
        ],
        'final-completion' => [
            'fc_ir_date' => ['label' => 'Final Completion Inspection Report Date', 'type' => 'date'],
            'fc_coc_date' => ['label' => 'Certificate of Completion Date', 'type' => 'date'],
            'fc_coa_date' => ['label' => 'Certificate of Acceptance Date', 'type' => 'date'],
        ],
    ];

    // Map section types to table names
    $sectionTypeToTableName = [
        'Contract Work Suspension' => 'contract-work-suspension',
        'Contract Work Resumption' => 'contract-work-resumption',
        'Contract Time Extension' => 'contract-time-extension',
        'Monthly Time Suspension Report' => 'monthly-time-suspension-report',
        'Variation Order' => 'variation-orders',
        'Contract Manpower' => 'contract-manpower',
        'Implementing Office Manpower' => 'implementing-office-manpower',
        'Final Completion' => 'final-completion',
    ];

    if (!isset($sectionTypeToTableName[$section_type])) {
        echo 'Invalid section type.';
        exit();
    }

    $tableName = $sectionTypeToTableName[$section_type];
    $mapping = $labelMappings[$tableName];

    // Determine the primary key field based on section type
    $primaryKeyField = '';

    switch ($section_type) {
        case 'Contract Work Suspension':
            $primaryKeyField = 'cws_code';
            break;
        case 'Contract Work Resumption':
            $primaryKeyField = 'cwr_code';
            break;
        case 'Contract Time Extension':
            $primaryKeyField = 'cte_code';
            break;
        case 'Monthly Time Suspension Report':
            $primaryKeyField = 'mtsr_code';
            break;
        case 'Variation Order':
            $primaryKeyField = 'vo_code';
            break;
        case 'Contract Manpower':
            $primaryKeyField = 'cm_mp_ID';
            break;
        case 'Implementing Office Manpower':
            $primaryKeyField = 'iom_ID';
            break;
        case 'Final Completion':
            $primaryKeyField = 'fc_ID';
            break;
        default:
            echo 'Invalid section type.';
            exit();
    }

    $query = "SELECT * FROM `$tableName` WHERE `$primaryKeyField` = ?";
    $stmt = $db->prepare($query);
    $stmt->bind_param('s', $section_ID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Display the section details using label mappings
        foreach ($mapping as $field => $fieldInfo) {
            if (isset($row[$field])) {
                $label = $fieldInfo['label'];
                $value = htmlspecialchars($row[$field]);

                // Format value based on type
                switch ($fieldInfo['type']) {
                    case 'date':
                        $value = date('F j, Y', strtotime($value));
                        break;
                    case 'amount':
                        $value = '₱' . number_format($value, 2);
                        break;
                    case 'days':
                        $value .= ' days';
                        break;
                    // Add more types as needed
                }

                echo "<p><strong>" . htmlspecialchars($label) . ":</strong> " . $value . "</p>";
            }
        }
    } else {
        echo 'No details found for this section.';
    }
}
?>
