<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "db_hotel";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Auto-release rooms whose reservation has fully passed
$conn->query("
    UPDATE rooms r
    LEFT JOIN reservations res
        ON res.room_id = r.room_id
        AND res.check_out >= CURDATE()
    SET r.status = 'available'
    WHERE r.status = 'occupied'
      AND res.reservation_id IS NULL
");

// Auto-occupy rooms whose check-in date has arrived today
$conn->query("
    UPDATE rooms r
    JOIN reservations res
        ON res.room_id = r.room_id
    SET r.status = 'occupied'
    WHERE res.check_in <= CURDATE()
      AND res.check_out >= CURDATE()
");
?>