<?php
session_start();
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Acess-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1;mode-block");
header("X-XSS-Type-Options: nosniff");

$jsonResponse = array("ResultCode" =>0, "ResultDesc" => "Accepted");

echo json_encode($jsonResponse)

?>