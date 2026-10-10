<?php
$host     = '127.0.0.1';
$username = 'root';
$password = ''; 
$dbname   = 'travel_projectdigabung';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Koneksi database gagal: " . $conn->connect_error);
}
?>