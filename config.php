<?php

$conn = new mysqli(
    "localhost",
    "root",
    "",
    "cafe_pos"
);

if ($conn->connect_error) {
    die("Ket noi that bai");
}

$conn->set_charset("utf8mb4");
