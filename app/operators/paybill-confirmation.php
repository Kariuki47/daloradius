<?php

session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Acess-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1;mode-block");
header("X-XSS-Type-Options: nosniff");

$response=array();
$disabled_groupname = 'daloRADIUS-Disabled-Users';
$logAction = "";
$logDebugSQL = "";
$log = "visited page: ";

$rawData=file_get_contents("php://input");
$data=json_decode($rawData, true);

$jsonResponse = array("ResultCode" =>0, "ResultDesc" => "Accepted");

$log = fopen('/var/log/mpesa/C2B_' . date("Y-m-d") . '.json', "a");
fwrite($log, json_encode($data) . "\n");
fclose($log);

$transID = $data['TransID'];
$transactionType = $data['TransactionType'];
$transTime = $data['TransTime'];
$transAmount = $data['TransAmount'];
$businessShortCode = $data['BusinessShortCode'];
$billRefNumber = $data['BillRefNumber'];
$invoiceNumber = $data['InvoiceNumber'];
$orgAccountBalance = $data['OrgAccountBalance'];
$thirdPartyTransID = $data['ThirdPartyTransID'];
$MSISDN = $data['MSISDN'];
$firstName = $data['FirstName'];
$middleName = $data['MiddleName'];
$lastName = $data['LastName'];

include('../common/includes/db_open.php');
                
$sql = sprintf("SELECT COUNT(id) FROM %s WHERE txn_id='%s'", 'billing_mpesa', $dbSocket->escapeSimple($transID));

$res = $dbSocket->query($sql);
$logDebugSQL .= "$sql;\n";
                
$exists = $res->fetchrow()[0] > 0;

if (!$exists) {
    $sql = sprintf("INSERT INTO %s (txn_type, txn_id, payment_date, payment_amount, business_shortcode, ref_no, inv_no, business_acc_balance, third_party_trans_ID, payer_phone, first_name, middle_name, last_name, payment_status)
                                            VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')", 'billing_mpesa',
                                   $dbSocket->escapeSimple($transactionType), $dbSocket->escapeSimple($transID), $dbSocket->escapeSimple($transTime),
                                   $dbSocket->escapeSimple($transAmount), $dbSocket->escapeSimple($businessShortCode), $dbSocket->escapeSimple($billRefNumber),
                                   $dbSocket->escapeSimple($invoiceNumber), $dbSocket->escapeSimple($orgAccountBalance), $dbSocket->escapeSimple($thirdPartyTransID),
                                   $dbSocket->escapeSimple($MSISDN), $dbSocket->escapeSimple($firstName), $dbSocket->escapeSimple($middleName), $dbSocket->escapeSimple($lastName), 'Completed');
    $res = $dbSocket->query($sql);
    $logDebugSQL .= "$sql;\n";

    if (!DB::isError($res)) {
        $logAction .= "Successfully added a new MPESA transaction Reference: [$billRefNumber]";

        $phone = preg_replace('/\s+/', '', $billRefNumber);
        $valid_phone = substr($phone, -9);

        $validRef = array();
        $validRef[] = $dbSocket->escapeSimple($billRefNumber);
        $validRef[] = $dbSocket->escapeSimple(preg_replace('/\s+/', '', $billRefNumber));
        $validRef[] = $dbSocket->escapeSimple(substr($phone, -9));
        $validRef[] = $dbSocket->escapeSimple('+254' . $valid_phone);
        $validRef[] = $dbSocket->escapeSimple('254' . $valid_phone);
        $validRef[] = $dbSocket->escapeSimple('0' . $valid_phone);

        $sql = sprintf("SELECT username, billdue FROM %s WHERE phone IN ('%s') OR phone_2 IN ('%s')", 'userbillinfo', implode("', '", $validRef), implode("', '", $validRef));
        $res = $dbSocket->query($sql);
        $logDebugSQL .= "$sql;\n";

        list(
            $username,
            $billdue
        ) = $res->fetchRow();

        if($username && intval($transAmount) > $billdue){
            $sql = sprintf("DELETE FROM %s WHERE username = '%s' AND groupname='%s'", 
                           'radusergroup', $username, $disabled_groupname);
            $res = $dbSocket->query($sql);
            
            // return message
            if (DB::isError($res)) {
                $logAction .= sprintf('Failed to enable username: [%s] paid transaction ID: %s.', $username, $transID);
            } else {
                $logAction .= sprintf('Enabled username: [%s] paid transaction ID: %s.', $username, $transID);
            }
        }
        
    } else {
        // it seems that mpesa could not be added
        $f = "Failed to add a new MPESA transaction Reference: [%s] to database";
        $logAction .= sprintf($f, $billRefNumber);
    }

}

include('../common/includes/db_close.php');

include('include/config/logging.php');

echo json_encode($jsonResponse);

?>