<?php
$host = 'localhost';
$db = '';
$user = '';
$pass = 'YOUR_DB_PASSWORD';


$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    file_put_contents('php://stderr', "Connection failed: " . $conn->connect_error . "\n");
    die("Connection failed: " . $conn->connect_error);
}
