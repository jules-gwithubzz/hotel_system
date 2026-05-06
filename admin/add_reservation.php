<?php
include '../auth.php';
include '../functions.php';
include '../connect_db.php';

if (!isAdmin() && !isStaff()) {
    header("Location: ../login.php");
    exit();
}

$today  = date('Y-m-d');
$guests = $conn->query("SELECT guest_id, full_name FROM guests ORDER BY full_name");
$rooms  = $conn->query(
    "SELECT room_id, room_number, room_type, price
     FROM rooms
     ORDER BY room_number"
);
$errors  = [];
$success = '';
$vals    = ['guest_id' => '', 'room_id' => '', 'check_in' => '', 'check_out' => ''];

if (isset($_POST['add'])) {
    $guest_id  = intval($_POST['guest_id']  ?? 0);
    $room_id   = intval($_POST['room_id']   ?? 0);
    $check_in  = trim($_POST['check_in']    ?? '');
    $check_out = trim($_POST['check_out']   ?? '');

    $vals = ['guest_id' => $guest_id, 'room_id' => $room_id, 'check_in' => $check_in, 'check_out' => $check_out];

    // Validation
    if (!$guest_id)  $errors['guest_id']  = "Please select a guest.";
    if (!$room_id)   $errors['room_id']   = "Please select a room.";
    if (empty($check_in))  $errors['check_in']  = "Check-in date is required.";
    if (empty($check_out)) $errors['check_out'] = "Check-out date is required.";
    if ($check_in && $check_out && $check_out <= $check_in)
        $errors['check_out'] = "Check-out must be after check-in.";
    if ($check_in && $check_in < date('Y-m-d'))
        $errors['check_in'] = "Check-in date cannot be in the past.";

    if (empty($errors)) {
        // Check for overlapping reservations on the chosen dates
        $overlap = $conn->prepare(
            "SELECT 1 FROM reservations
             WHERE room_id   = ?
               AND check_in  < ?
               AND check_out > ?
             LIMIT 1"
        );
        $overlap->bind_param("iss", $room_id, $check_out, $check_in);
        $overlap->execute();
        $overlap->store_result();
        if ($overlap->num_rows > 0) {
            $errors['room_id'] = "This room is already booked for the selected dates.";
        }
        $overlap->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO reservations (guest_id, room_id, check_in, check_out) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $guest_id, $room_id, $check_in, $check_out);
        if ($stmt->execute()) {
            // Only mark occupied if check-in is today or earlier
            if ($check_in <= date('Y-m-d')) {
                $upd = $conn->prepare("UPDATE rooms SET status='occupied' WHERE room_id=?");
                $upd->bind_param("i", $room_id);
                $upd->execute();
                $upd->close();
            }
            $success = "Reservation added successfully!";
            $vals = ['guest_id' => '', 'room_id' => '', 'check_in' => '', 'check_out' => ''];
            // Refresh room list — show all rooms
            $rooms = $conn->query(
                "SELECT room_id, room_number, room_type, price
                 FROM rooms
                 ORDER BY room_number"
            );
        } else {
            $errors['db'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Reservation</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body>
<video id="bg-video" autoplay muted loop playsinline>
  <source src="../images/22.mp4" type="video/mp4">
</video>

<?php include '../navbar.php'; ?>

<div class="cw-page" style="max-width:620px;">

  <div class="page-title">
    <i class="bi bi-calendar-plus"></i>
    <h2>New Reservation</h2>
  </div>

  <div class="cw-card">

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($errors['db'])): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['db'] ?></div>
    <?php endif; ?>

    <form method="POST" id="resForm" novalidate>

      <div class="form-group">
        <label class="form-label" for="guest_id"><i class="bi bi-person"></i> Guest <span style="color:var(--danger)">*</span></label>
        <select class="form-select <?= isset($errors['guest_id']) ? 'is-invalid' : '' ?>" id="guest_id" name="guest_id" required>
          <option value="">— Select Guest —</option>
          <?php
          $guests->data_seek(0);
          while ($g = $guests->fetch_assoc()):
            $sel = ($vals['guest_id'] == $g['guest_id']) ? 'selected' : '';
          ?>
            <option value="<?= $g['guest_id'] ?>" <?= $sel ?>><?= htmlspecialchars($g['full_name']) ?></option>
          <?php endwhile; ?>
        </select>
        <?php if (isset($errors['guest_id'])): ?>
          <div class="form-error visible"><?= $errors['guest_id'] ?></div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="room_id"><i class="bi bi-door-open"></i> Available Room <span style="color:var(--danger)">*</span></label>
        <select class="form-select <?= isset($errors['room_id']) ? 'is-invalid' : '' ?>" id="room_id" name="room_id" required>
          <option value="">— Select Room —</option>
          <?php
          $rooms->data_seek(0);
          while ($r = $rooms->fetch_assoc()):
            $sel = ($vals['room_id'] == $r['room_id']) ? 'selected' : '';
          ?>
            <option value="<?= $r['room_id'] ?>" <?= $sel ?>>
              Room <?= htmlspecialchars($r['room_number']) ?> — <?= htmlspecialchars($r['room_type']) ?> (₱<?= number_format($r['price'],2) ?>)
            </option>
          <?php endwhile; ?>
        </select>
        <?php if (isset($errors['room_id'])): ?>
          <div class="form-error visible"><?= $errors['room_id'] ?></div>
        <?php endif; ?>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
        <div class="form-group">
          <label class="form-label" for="check_in"><i class="bi bi-calendar"></i> Check-In <span style="color:var(--danger)">*</span></label>
          <input class="form-input <?= isset($errors['check_in']) ? 'is-invalid' : '' ?>"
            type="date" id="check_in" name="check_in"
            value="<?= $vals['check_in'] ?>"
            min="<?= date('Y-m-d') ?>" required>
          <?php if (isset($errors['check_in'])): ?>
            <div class="form-error visible"><?= $errors['check_in'] ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label" for="check_out"><i class="bi bi-calendar-check"></i> Check-Out <span style="color:var(--danger)">*</span></label>
          <input class="form-input <?= isset($errors['check_out']) ? 'is-invalid' : '' ?>"
            type="date" id="check_out" name="check_out"
            value="<?= $vals['check_out'] ?>"
            min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
          <?php if (isset($errors['check_out'])): ?>
            <div class="form-error visible"><?= $errors['check_out'] ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Duration preview -->
      <div id="duration-info" style="color:var(--gold); font-size:0.85rem; margin-bottom:1rem; min-height:1.4em;"></div>

      <div style="display:flex; gap:0.75rem; margin-top:0.5rem;">
        <button type="submit" name="add" class="btn-gold">
          <i class="bi bi-calendar-check"></i> Confirm Reservation
        </button>
        <a href="<?= isAdmin() ? 'admin_dashboard.php' : '../staff/staff_dashboard.php' ?>" class="btn-ghost">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>

    </form>
  </div>
</div>

<script>
// Live duration preview
function updateDuration() {
  const ci = document.getElementById('check_in').value;
  const co = document.getElementById('check_out').value;
  const info = document.getElementById('duration-info');
  if (ci && co) {
    const d1 = new Date(ci), d2 = new Date(co);
    const nights = Math.round((d2 - d1) / 86400000);
    if (nights > 0) info.innerHTML = '<i class="bi bi-moon-stars"></i> ' + nights + ' night' + (nights > 1 ? 's' : '');
    else info.innerHTML = '<span style="color:var(--danger)">Check-out must be after check-in</span>';
  } else {
    info.innerHTML = '';
  }
}

document.getElementById('check_in').addEventListener('change', function() {
  const co = document.getElementById('check_out');
  if (this.value) {
    const next = new Date(this.value);
    next.setDate(next.getDate() + 1);
    co.min = next.toISOString().split('T')[0];
  }
  updateDuration();
});
document.getElementById('check_out').addEventListener('change', updateDuration);

// Validation
document.getElementById('resForm').addEventListener('submit', function(e) {
  let valid = true;
  document.querySelectorAll('.form-error').forEach(el => el.classList.remove('visible'));

  const guest = document.getElementById('guest_id');
  const room  = document.getElementById('room_id');
  const ci    = document.getElementById('check_in');
  const co    = document.getElementById('check_out');

  if (!guest.value) { guest.classList.add('is-invalid'); valid = false; }
  if (!room.value)  { room.classList.add('is-invalid');  valid = false; }
  if (!ci.value)    { ci.classList.add('is-invalid');    valid = false; }
  if (!co.value)    { co.classList.add('is-invalid');    valid = false; }
  if (ci.value && co.value && co.value <= ci.value) { co.classList.add('is-invalid'); valid = false; }

  if (!valid) e.preventDefault();
});
</script>
</body>
</html>