<?php
include '../auth.php';
include '../functions.php';
include '../connect_db.php';

if (!isStaff()) {
    header("Location: ../login.php");
    exit();
}

$guestCount       = $conn->query("SELECT COUNT(*) AS t FROM guests")->fetch_assoc()['t'];
$roomCount        = $conn->query("SELECT COUNT(*) AS t FROM rooms")->fetch_assoc()['t'];
$reservationCount = $conn->query("SELECT COUNT(*) AS t FROM reservations")->fetch_assoc()['t'];
$availableRooms   = $conn->query("SELECT COUNT(*) AS t FROM rooms WHERE status='available'")->fetch_assoc()['t'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Dashboard — Cool Waves Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body>

<?php include '../navbar.php'; ?>

<div class="cw-page">
  <div class="page-title">
    <i class="bi bi-speedometer2"></i>
    <h2>Staff Dashboard</h2>
  </div>

  <div class="grid-4" style="margin-bottom:2rem;">
    <div class="stat-card gold">
      <div class="stat-icon"><i class="bi bi-people-fill"></i></div>
      <div class="stat-value"><?= $guestCount ?></div>
      <div class="stat-label">Total Guests</div>
    </div>
    <div class="stat-card blue">
      <div class="stat-icon"><i class="bi bi-door-open-fill"></i></div>
      <div class="stat-value"><?= $roomCount ?></div>
      <div class="stat-label">Total Rooms</div>
    </div>
    <div class="stat-card green">
      <div class="stat-icon"><i class="bi bi-calendar-check-fill"></i></div>
      <div class="stat-value"><?= $reservationCount ?></div>
      <div class="stat-label">Reservations</div>
    </div>
    <div class="stat-card gold">
      <div class="stat-icon"><i class="bi bi-check-circle-fill"></i></div>
      <div class="stat-value"><?= $availableRooms ?></div>
      <div class="stat-label">Available Rooms</div>
    </div>
  </div>

  <div class="cw-card">
    <div class="cw-card-header">
      <span class="cw-card-title"><i class="bi bi-grid-1x2"></i> Quick Actions</span>
    </div>
    <div class="grid-3">
      <a href="../admin/add_guest.php" class="action-card">
        <i class="bi bi-person-plus-fill"></i>
        <span>Add Guest</span>
      </a>
      <a href="../admin/add_reservation.php" class="action-card">
        <i class="bi bi-calendar-plus-fill"></i>
        <span>New Reservation</span>
      </a>
      <a href="../admin/view_reservations.php" class="action-card">
        <i class="bi bi-list-check"></i>
        <span>View Reservations</span>
      </a>
    </div>
  </div>
</div>
</body>
</html>
