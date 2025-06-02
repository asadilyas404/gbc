<?php
$username = 'ROYAL_GBC';
$password = 'RoYalgbc14t';
$connection_string = '192.168.10.51:1521/ROYALERP';

$conn = oci_connect($username, $password, $connection_string);

if (!$conn) {
    $e = oci_error();
    echo "❌ Oracle connection failed: " . $e['message'];
} else {
    echo "✅ Oracle connection successful!";
    oci_close($conn);
}
