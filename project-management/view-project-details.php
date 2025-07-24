<?php
// view-project-details.php

include '../includes/database.php';

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

if (isset($_GET['proj_ID'])) {
    $proj_ID = $_GET['proj_ID'];
    $db = ConnectDB();

    // Fetch main project data along with contractor name
    $query = "SELECT p.*, c.cont_name 
              FROM projects p
              LEFT JOIN contractors c ON p.cont_ID = c.cont_ID
              WHERE p.proj_ID = ? AND p.proj_isDeleted = 0";
    $stmt = $db->prepare($query);
    if (!$stmt) {
        die('Prepare failed: ' . htmlspecialchars($db->error));
    }
    $stmt->bind_param("s", $proj_ID);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $project = $result->fetch_assoc();

        // Fetch Contract Work Suspension
        $cws_query = "SELECT * FROM `contract-work-suspension` WHERE proj_ID = ?";
        $cws_stmt = $db->prepare($cws_query);
        $cws_stmt->bind_param("s", $proj_ID);
        $cws_stmt->execute();
        $cws_result = $cws_stmt->get_result();
        $contractWorkSuspensions = [];
        while ($row = $cws_result->fetch_assoc()) {
            $contractWorkSuspensions[] = $row;
        }
        $cws_stmt->close();

        // Fetch Contract Work Resumption
        $cwr_query = "SELECT * FROM `contract-work-resumption` WHERE proj_ID = ?";
        $cwr_stmt = $db->prepare($cwr_query);
        $cwr_stmt->bind_param("s", $proj_ID);
        $cwr_stmt->execute();
        $cwr_result = $cwr_stmt->get_result();
        $contractWorkResumptions = [];
        while ($row = $cwr_result->fetch_assoc()) {
            $contractWorkResumptions[] = $row;
        }
        $cwr_stmt->close();

        // Fetch Contract Time Extension
        $cte_query = "SELECT * FROM `contract-time-extension` WHERE proj_ID = ?";
        $cte_stmt = $db->prepare($cte_query);
        $cte_stmt->bind_param("s", $proj_ID);
        $cte_stmt->execute();
        $cte_result = $cte_stmt->get_result();
        $contractTimeExtensions = [];
        while ($row = $cte_result->fetch_assoc()) {
            $contractTimeExtensions[] = $row;
        }
        $cte_stmt->close();

        // Fetch Monthly Time Suspension Report
        $mtsr_query = "SELECT * FROM `monthly-time-suspension-report` WHERE proj_ID = ?";
        $mtsr_stmt = $db->prepare($mtsr_query);
        $mtsr_stmt->bind_param("s", $proj_ID);
        $mtsr_stmt->execute();
        $mtsr_result = $mtsr_stmt->get_result();
        $monthlyTimeSuspensionReports = [];
        while ($row = $mtsr_result->fetch_assoc()) {
            $monthlyTimeSuspensionReports[] = $row;
        }
        $mtsr_stmt->close();

        // Fetch Variation Orders
        $vo_query = "SELECT * FROM `variation-orders` WHERE proj_ID = ?";
        $vo_stmt = $db->prepare($vo_query);
        $vo_stmt->bind_param("s", $proj_ID);
        $vo_stmt->execute();
        $vo_result = $vo_stmt->get_result();
        $variationOrders = [];
        while ($row = $vo_result->fetch_assoc()) {
            $variationOrders[] = $row;
        }
        $vo_stmt->close();

        // Fetch Contractor's Manpower
        $cm_query = "SELECT * FROM `contract-manpower` WHERE proj_ID = ?";
        $cm_stmt = $db->prepare($cm_query);
        $cm_stmt->bind_param("s", $proj_ID);
        $cm_stmt->execute();
        $cm_result = $cm_stmt->get_result();
        $contractManpower = $cm_result->fetch_assoc();
        $cm_stmt->close();

        // Fetch Implementing Office Manpower
        $iom_query = "SELECT * FROM `implementing-office-manpower` WHERE proj_ID = ?";
        $iom_stmt = $db->prepare($iom_query);
        $iom_stmt->bind_param("s", $proj_ID);
        $iom_stmt->execute();
        $iom_result = $iom_stmt->get_result();
        $implementingOfficeManpower = $iom_result->fetch_assoc();
        $iom_stmt->close();

        // Close main project statement
        $stmt->close();
        $db->close();

        // Determine if there are predetermined unworkable days
        $hasUnworkableDays = ($project['proj_unwork_days'] > 0) ? true : false;
        $unworkableDays = $project['proj_unwork_days'];

        // Begin outputting the HTML content
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <title>Project Document</title>
            <style>
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 20px;
                }
                table, th, td {
                    border: 1px solid black;
                }
                th, td {
                    padding: 8px;
                    text-align: left;
                }
                h1, h2, h3 {
                    text-align: center;
                }
                .checkbox-group {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .checkbox-group input {
                    margin-right: 5px;
                }
                .disabled-checkbox {
                    pointer-events: none;
                }
            </style>
        </head>
        <body>

        <h1>Department of Public Works and Highways</h1>
        <h2>Second District Engineering Office</h2>
        <h3>Payawin, Gubat, Sorsogon</h3>

        <h2>Project Contract ID: <?= getValue($project['proj_ID']); ?></h2>
        <ul>
            <li>Component ID: <?= getValue($project['proj_comp_ID']); ?></li>
            <li>Contract Name: <?= getValue($project['proj_cont_name']); ?></li>
            <li>Contract Location: <?= getValue($project['proj_cont_loc']); ?></li>
            <li>Contractor: <?= getValue($project['cont_name']); ?></li>
            <li>Contract Amount: <?= getValue(number_format($project['proj_cont_amt'], 2)); ?></li>
            <li>Contract Duration: <?= getValue($project['proj_cont_duration']); ?> days</li>
            <li>
                Predetermined unworkable days:
                <div class="checkbox-group">
                    <label>
                        <input type="checkbox" <?= $hasUnworkableDays ? 'checked' : ''; ?> disabled class="disabled-checkbox">
                        Yes
                    </label>
                    <label>
                        <input type="checkbox" <?= !$hasUnworkableDays ? 'checked' : ''; ?> disabled class="disabled-checkbox">
                        No
                    </label>
                </div>
                <?php if ($hasUnworkableDays): ?>
                    <p><strong>Number of Unworkable Days:</strong> <?= getValue($unworkableDays); ?> days</p>
                <?php endif; ?>
            </li>
        </ul>

        <h3>Contract Dates</h3>
        <ul>
            <li>Notice of Award: <?= formatDate($project['proj_NOA']); ?></li>
            <li>Notice to Proceed: <?= formatDate($project['proj_NOP']); ?></li>
            <li>Effectivity Date: <?= formatDate($project['proj_effect_date']); ?></li>
            <li>Expiry Date: <?= formatDate($project['proj_expiry_date']); ?></li>
        </ul>

        <h3>Contract Work Suspension</h3>
        <?php if (!empty($contractWorkSuspensions)): ?>
            <?php foreach ($contractWorkSuspensions as $cws): ?>
                <ul>
                    <li>Date of Letter Request: <?= formatDate($cws['cws_lr_date']); ?></li>
                    <li>Reason for the Request: <?= getValue($cws['cws_reason']); ?></li>
                    <li>Number of Suspended Days: <?= getValue($cws['cws_susp_days']); ?></li>
                    <li>Date of Approval: <?= formatDate($cws['cws_approved_date']); ?></li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No Contract Work Suspension records found.</p>
        <?php endif; ?>

        <h3>Contract Work Resumption</h3>
        <?php if (!empty($contractWorkResumptions)): ?>
            <?php foreach ($contractWorkResumptions as $cwr): ?>
                <ul>
                    <li>Date of Letter Request: <?= formatDate($cwr['cwr_lr_date']); ?></li>
                    <li>Reason for the Request: <?= getValue($cwr['cwr_reason']); ?></li>
                    <li>Number of Suspended Days: <?= getValue($cwr['cwr_susp_days']); ?></li>
                    <li>Date of Approval: <?= formatDate($cwr['cwr_approved_date']); ?></li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No Contract Work Resumption records found.</p>
        <?php endif; ?>

        <h3>Contract Time Extension</h3>
        <?php if (!empty($contractTimeExtensions)): ?>
            <?php foreach ($contractTimeExtensions as $cte): ?>
                <ul>
                    <li>Date of Letter Request: <?= formatDate($cte['cte_lr_date']); ?></li>
                    <li>Reason for the Request: <?= getValue($cte['cte_reason']); ?></li>
                    <li>Number of Extended Days: <?= getValue($cte['cte_ext_days']); ?></li>
                    <li>Date of Approval: <?= formatDate($cte['cte_approved_date']); ?></li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No Contract Time Extension records found.</p>
        <?php endif; ?>

        <h3>Monthly Time Suspension Report</h3>
        <?php if (!empty($monthlyTimeSuspensionReports)): ?>
            <?php foreach ($monthlyTimeSuspensionReports as $mtsr): ?>
                <ul>
                    <li>Date of Letter Request: <?= formatDate($mtsr['mtsr_lr_date']); ?></li>
                    <li>Reason for the Request: <?= getValue($mtsr['mtsr_reason']); ?></li>
                    <li>Number of Suspended Days: <?= getValue($mtsr['mtsr_susp_days']); ?></li>
                    <li>Date of Approval: <?= formatDate($mtsr['mtsr_approved_date']); ?></li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No Monthly Time Suspension Report records found.</p>
        <?php endif; ?>

        <h3>Variation Orders</h3>
        <?php if (!empty($variationOrders)): ?>
            <?php foreach ($variationOrders as $index => $vo): ?>
                <h4>VO<?= $index + 1; ?></h4>
                <ul>
                    <li>Date of Request: <?= formatDate($vo['vo_date_request']); ?></li>
                    <li>Reason for the Request: <?= getValue($vo['vo_reason']); ?></li>
                    <li>Change in Amount: <?= getValue(number_format($vo['vo_amt_change'], 2)); ?></li>
                    <li>Date of Approval: <?= formatDate($vo['vo_approval_date']); ?></li>
                </ul>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No Variation Orders found.</p>
        <?php endif; ?>

        <h3>Contractor's Manpower</h3>
        <?php if ($contractManpower): ?>
            <table>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>PRC/ME ID No.</th>
                    <th>Date of Expiry</th>
                </tr>
                <tr>
                    <td>Authorized Managing Officer</td>
                    <td><?= getValue($contractManpower['cm_am_officer']); ?></td>
                    <td>N/A</td>
                    <td>N/A</td>
                </tr>
                <tr>
                    <td>Project Manager</td>
                    <td><?= getValue($contractManpower['cm_pm_name']); ?></td>
                    <td><?= getValue($contractManpower['cm_pm_prc_me_ID']); ?></td>
                    <td><?= formatDate($contractManpower['cm_pm_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Project Engineer</td>
                    <td><?= getValue($contractManpower['cm_pe_name']); ?></td>
                    <td><?= getValue($contractManpower['cm_pe_prc_me_ID']); ?></td>
                    <td><?= formatDate($contractManpower['cm_pe_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Materials Engineer</td>
                    <td><?= getValue($contractManpower['cm_me_name']); ?></td>
                    <td><?= getValue($contractManpower['cm_me_prc_me_ID']); ?></td>
                    <td><?= formatDate($contractManpower['cm_me_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Construction Foreman</td>
                    <td><?= getValue($contractManpower['cm_const_foreman']); ?></td>
                    <td>N/A</td>
                    <td>N/A</td>
                </tr>
                <tr>
                    <td>Construction Safety & Health Officer</td>
                    <td><?= getValue($contractManpower['cm_csh_officer']); ?></td>
                    <td>N/A</td>
                    <td>N/A</td>
                </tr>
            </table>
        <?php else: ?>
            <p>No Contractor's Manpower records found.</p>
        <?php endif; ?>

        <h3>Implementing Office Manpower</h3>
        <?php if ($implementingOfficeManpower): ?>
            <table>
                <tr>
                    <th></th>
                    <th>Name</th>
                    <th>PRC/ME ID No.</th>
                    <th>Date of Expiry</th>
                </tr>
                <tr>
                    <td>Project Engineer</td>
                    <td><?= getValue($implementingOfficeManpower['iom_pe_name']); ?></td>
                    <td><?= getValue($implementingOfficeManpower['iom_pe_prc_me_ID']); ?></td>
                    <td><?= formatDate($implementingOfficeManpower['iom_pe_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Project Inspector</td>
                    <td><?= getValue($implementingOfficeManpower['iom_pi_name']); ?></td>
                    <td><?= getValue($implementingOfficeManpower['iom_pi_prc_me_ID']); ?></td>
                    <td><?= formatDate($implementingOfficeManpower['iom_pi_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Materials Engineer</td>
                    <td><?= getValue($implementingOfficeManpower['iom_me_name']); ?></td>
                    <td><?= getValue($implementingOfficeManpower['iom_me_prc_me_ID']); ?></td>
                    <td><?= formatDate($implementingOfficeManpower['iom_me_expiry']); ?></td>
                </tr>
                <tr>
                    <td>Materials-In-Charge</td>
                    <td><?= getValue($implementingOfficeManpower['iom_mic_name']); ?></td>
                    <td><?= getValue($implementingOfficeManpower['iom_mic_prc_me_ID']); ?></td>
                    <td><?= formatDate($implementingOfficeManpower['iom_mic_expiry']); ?></td>
                </tr>
            </table>
        <?php else: ?>
            <p>No Implementing Office Manpower records found.</p>
        <?php endif; ?>
        </body>
        </html>
        <?php
    } else {
        echo '<p>Project details not found.</p>';
    }
} else {
    echo '<p>Invalid Project ID.</p>';
}
?>
