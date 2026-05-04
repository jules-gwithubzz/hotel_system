<?php
include '../auth.php';
include '../functions.php';
include '../connect_db.php';

if (!isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$errors  = [];
$success = '';
$vals    = ['number' => '', 'type' => '', 'price' => ''];

// Add room
if (isset($_POST['add'])) {
    $num   = trim($_POST['number'] ?? '');
    $type  = trim($_POST['type']   ?? '');
    $price = trim($_POST['price']  ?? '');

    $vals = ['number' => htmlspecialchars($num), 'type' => htmlspecialchars($type), 'price' => htmlspecialchars($price)];

    if (empty($num))               $errors['number'] = "Room number is required.";
    if (empty($type))              $errors['type']   = "Room type is required.";
    if (!is_numeric($price) || $price <= 0) $errors['price'] = "Enter a valid price (e.g. 1500).";

    // Check duplicate room number
    if (empty($errors)) {
        $chk = $conn->prepare("SELECT room_id FROM rooms WHERE room_number = ? LIMIT 1");
        $chk->bind_param("s", $num);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors['number'] = "Room number already exists.";
        $chk->close();
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO rooms (room_number, room_type, price) VALUES (?, ?, ?)");
        $stmt->bind_param("ssd", $num, $type, $price);
        if ($stmt->execute()) {
            $success = "Room <strong>$num</strong> added successfully!";
            $vals = ['number' => '', 'type' => '', 'price' => ''];
        } else {
            $errors['db'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Delete room (with confirmation via GET param + CSRF-lite token)
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    // Check if room has active reservations
    $chk = $conn->prepare("SELECT reservation_id FROM reservations WHERE room_id = ? LIMIT 1");
    $chk->bind_param("i", $id);
    $chk->execute();
    if ($chk->get_result()->num_rows > 0) {
        $delete_error = "Cannot delete: room has existing reservations.";
    } else {
        $del = $conn->prepare("DELETE FROM rooms WHERE room_id = ?");
        $del->bind_param("i", $id);
        $del->execute();
        $del->close();
        $success = "Room deleted successfully.";
    }
    $chk->close();
}

// Search & pagination
$search   = trim($_GET['search'] ?? '');
$per_page = 8;
$page     = max(1, intval($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where_sql = $search ? "WHERE room_number LIKE ? OR room_type LIKE ?" : '';
$params    = $search ? ['%'.$search.'%', '%'.$search.'%'] : [];
$types     = $search ? 'ss' : '';

$cnt_stmt = $conn->prepare("SELECT COUNT(*) AS t FROM rooms $where_sql");
if ($params) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total = $cnt_stmt->get_result()->fetch_assoc()['t'];
$cnt_stmt->close();
$total_pages = max(1, ceil($total / $per_page));

$today = date('Y-m-d');
$all_params = array_merge([$today, $today], $params, [$per_page, $offset]);
$all_types  = 'ss' . $types . 'ii';
$data_stmt = $conn->prepare(
    "SELECT r.*,
            IF(COUNT(res.reservation_id) > 0, 'occupied', 'available') AS computed_status
     FROM rooms r
     LEFT JOIN reservations res
            ON res.room_id = r.room_id
           AND res.check_in  <= ?
           AND res.check_out >  ?
     " . ($where_sql ? str_replace('WHERE ', 'WHERE r.', $where_sql) : '') . "
     GROUP BY r.room_id
     ORDER BY r.room_number
     LIMIT ? OFFSET ?"
);
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$rooms = $data_stmt->get_result();
$data_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Rooms — Cool Waves Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body>

<?php include '../navbar.php'; ?>

<div class="cw-page">

  <div class="page-title">
    <i class="bi bi-door-open"></i>
    <h2>Manage Rooms</h2>
  </div>

  <div style="display:grid; grid-template-columns: 340px 1fr; gap:1.5rem; align-items:start;">

    <!-- Add Room Form -->
    <div class="cw-card">
      <div class="cw-card-header" style="margin-bottom:1.25rem;">
        <span class="cw-card-title"><i class="bi bi-plus-circle"></i> Add Room</span>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
      <?php endif; ?>
      <?php if (isset($delete_error)): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= $delete_error ?></div>
      <?php endif; ?>
      <?php if (isset($errors['db'])): ?>
        <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['db'] ?></div>
      <?php endif; ?>

      <form method="POST" id="roomForm" novalidate>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-hash"></i> Room Number <span style="color:var(--danger)">*</span></label>
          <input class="form-input <?= isset($errors['number']) ? 'is-invalid':'' ?>"
            type="text" name="number" placeholder="e.g. 101"
            value="<?= $vals['number'] ?>" required>
          <?php if (isset($errors['number'])): ?>
            <div class="form-error visible"><?= $errors['number'] ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-tag"></i> Room Type <span style="color:var(--danger)">*</span></label>
          <select class="form-select <?= isset($errors['type']) ? 'is-invalid':'' ?>" name="type">
            <option value="">— Select Type —</option>
            <?php foreach(['Standard', 'Deluxe', 'Suite', 'Presidential'] as $t): ?>
              <option value="<?= $t ?>" <?= $vals['type'] === $t ? 'selected':'' ?>><?= $t ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['type'])): ?>
            <div class="form-error visible"><?= $errors['type'] ?></div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-currency-exchange"></i> Price / Night (₱) <span style="color:var(--danger)">*</span></label>
          <input class="form-input <?= isset($errors['price']) ? 'is-invalid':'' ?>"
            type="number" name="price" placeholder="e.g. 1500" min="1"
            value="<?= $vals['price'] ?>" required>
          <?php if (isset($errors['price'])): ?>
            <div class="form-error visible"><?= $errors['price'] ?></div>
          <?php endif; ?>
        </div>

        <button type="submit" name="add" class="btn-gold" style="width:100%; justify-content:center;">
          <i class="bi bi-plus-lg"></i> Add Room
        </button>
      </form>
    </div>

    <!-- Rooms List -->
    <div class="cw-card">
      <div class="cw-card-header">
        <span class="cw-card-title"><i class="bi bi-table"></i> All Rooms
          <span style="color:var(--text-muted); font-size:0.9rem; font-family:'DM Sans',sans-serif; font-weight:400;">(<?= $total ?>)</span>
        </span>
      </div>

      <form method="GET" class="search-bar" style="margin-bottom:1rem;">
        <div class="search-input-wrap">
          <i class="bi bi-search"></i>
          <input class="form-input" type="text" name="search"
            placeholder="Search room number or type…"
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-gold"><i class="bi bi-funnel"></i> Search</button>
        <?php if ($search): ?>
          <a href="manage_rooms.php" class="btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
        <?php endif; ?>
      </form>

      <div class="cw-table-wrap">
        <table class="cw-table">
          <thead>
            <tr>
              <th>Room #</th>
              <th>Type</th>
              <th>Price/Night</th>
              <th>Status</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rooms->num_rows === 0): ?>
              <tr><td colspan="5">
                <div class="empty-state">
                  <i class="bi bi-door-closed"></i>
                  <p>No rooms found.</p>
                </div>
              </td></tr>
            <?php else: while ($r = $rooms->fetch_assoc()): ?>
              <tr>
                <td><strong><?= htmlspecialchars($r['room_number']) ?></strong></td>
                <td><?= htmlspecialchars($r['room_type']) ?></td>
                <td>₱<?= number_format($r['price'], 2) ?></td>
                <td><span class="badge-status <?= $r['computed_status'] ?>"><?= ucfirst($r['computed_status']) ?></span></td>
                <td>
                  <a href="#" class="btn-danger-sm"
                     onclick="confirmDelete(<?= $r['room_id'] ?>, '<?= htmlspecialchars($r['room_number']) ?>'); return false;">
                    <i class="bi bi-trash"></i> Delete
                  </a>
                </td>
              </tr>
            <?php endwhile; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_pages > 1): ?>
      <div class="pagination">
        <a class="page-btn <?= $page<=1?'disabled':'' ?>"
           href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-left"></i></a>
        <?php for ($i=1; $i<=$total_pages; $i++): ?>
          <a class="page-btn <?= $i===$page?'active':'' ?>"
             href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
        <?php endfor; ?>
        <a class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"
           href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>"><i class="bi bi-chevron-right"></i></a>
      </div>
      <?php endif; ?>

    </div>
  </div>
</div>

<!-- Delete confirm dialog -->
<div class="confirm-overlay" id="confirmOverlay">
  <div class="confirm-box">
    <h4><i class="bi bi-exclamation-triangle-fill"></i> Delete Room?</h4>
    <p id="confirmMsg">Are you sure you want to delete this room?</p>
    <div class="confirm-actions">
      <a id="confirmYes" href="#" class="btn-danger-sm" style="padding:0.5rem 1.25rem; font-size:0.875rem;">
        <i class="bi bi-trash"></i> Delete
      </a>
      <button onclick="document.getElementById('confirmOverlay').classList.remove('show')" class="btn-ghost">
        Cancel
      </button>
    </div>
  </div>
</div>

<script>
function confirmDelete(id, num) {
  document.getElementById('confirmMsg').textContent = 'Delete Room ' + num + '? This cannot be undone.';
  document.getElementById('confirmYes').href = '?delete=' + id + '&search=<?= urlencode($search) ?>&page=<?= $page ?>';
  document.getElementById('confirmOverlay').classList.add('show');
}
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('show');
});

// Client-side form validation
document.getElementById('roomForm').addEventListener('submit', function(e) {
  let valid = true;
  const num   = this.elements['number'];
  const type  = this.elements['type'];
  const price = this.elements['price'];
  if (!num.value.trim())   { num.classList.add('is-invalid');   valid = false; }
  if (!type.value)         { type.classList.add('is-invalid');  valid = false; }
  if (!price.value || isNaN(price.value) || parseFloat(price.value) <= 0)
                           { price.classList.add('is-invalid'); valid = false; }
  if (!valid) e.preventDefault();
});
</script>
</body>
</html>
