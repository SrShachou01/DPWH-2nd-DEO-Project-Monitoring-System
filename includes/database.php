<?php
const HOST = '127.0.0.1:3306';
const DB_USER = 'u733311923_dpwh';
const DB_PWD = 'DPWHsecond1';
const DB_NAME = 'u733311923_dpwhpms';

function ConnectDB() {
    $db = new mysqli(HOST, DB_USER, DB_PWD, DB_NAME);

    if ($db->connect_error) {
        echo '<h1>Unable to Establish Connection</h1>';
        exit;
    }
    return $db;
}
?>
