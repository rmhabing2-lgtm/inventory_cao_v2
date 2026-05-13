<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "inventory_cao";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
