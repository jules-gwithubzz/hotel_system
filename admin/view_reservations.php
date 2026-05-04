<?php
include '../auth.php';
include '../functions.php';
include '../connect_db.php';

// Search / filter
$search = trim($_GET['search'] ?? '');
$filter_status = trim($_GET['status'] ?? '');

// Pagination
$per_page = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

// Build WHERE clause safely
$where_parts = [];
$params = [];
$types  = '';

if ($search !== '') {
    $like = '%' . $search . '%';
    $where_parts[] = "(g.full_name LIKE ? OR rm.room_number LIKE ?)";
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}

// Today for status calculation
$today = date('Y-m-d');
if ($filter_status === 'active') {
    $where_parts[] = "(r.check_in <= ? AND r.check_out >= ?)";
    $params[] = $today; $params[] = $today;
    $types .= 'ss';
} elseif ($filter_status === 'upcoming') {
    $where_parts[] = "r.check_in > ?";
    $params[] = $today;
    $types .= 's';
} elseif ($filter_status === 'past') {
    $where_parts[] = "r.check_out < ?";
    $params[] = $today;
    $types .= 's';
}

$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Count total
$count_sql = "SELECT COUNT(*) AS t FROM reservations r
              JOIN guests g ON r.guest_id = g.guest_id
              JOIN rooms rm ON r.room_id = rm.room_id
              $where_sql";
$count_stmt = $conn->prepare($count_sql);
if ($params) { $count_stmt->bind_param($types, ...$params); }
$count_stmt->execute();
$total = $count_stmt->get_result()->fetch_assoc()['t'];
$count_stmt->close();
$total_pages = max(1, ceil($total / $per_page));

// Fetch data
$data_sql = "SELECT r.reservation_id, g.full_name, rm.room_number, rm.room_type,
                    r.check_in, r.check_out, rm.price
             FROM reservations r
             JOIN guests g  ON r.guest_id = g.guest_id
             JOIN rooms rm  ON r.room_id  = rm.room_id
             $where_sql
             ORDER BY r.check_in DESC
             LIMIT ? OFFSET ?";

$all_params  = array_merge($params, [$per_page, $offset]);
$all_types   = $types . 'ii';
$data_stmt = $conn->prepare($data_sql);
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$result = $data_stmt->get_result();
$data_stmt->close();

function reservationStatus($check_in, $check_out, $today) {
    if ($check_out < $today)  return ['Past',     'occupied'];
    if ($check_in  > $today)  return ['Upcoming', 'available'];
    return ['Active', 'staff'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reservations — Cool Waves Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body>

<?php include '../navbar.php'; ?>

<div class="cw-page">

  <div class="page-title">
    <i class="bi bi-list-check"></i>
    <h2>Reservations</h2>
  </div>

  <div class="cw-card">
    <div class="cw-card-header">
      <span class="cw-card-title"><i class="bi bi-table"></i> All Reservations
        <span style="color:var(--text-muted); font-size:0.9rem; font-family:'DM Sans',sans-serif; font-weight:400;">
          (<?= $total ?> total)
        </span>
      </span>
      <a href="add_reservation.php" class="btn-gold btn-sm">
        <i class="bi bi-plus-lg"></i> New
      </a>
    </div>

    <!-- Search & filter bar -->
    <form method="GET" class="search-bar">
      <div class="search-input-wrap">
        <i class="bi bi-search"></i>
        <input class="form-input" type="text" name="search"
          placeholder="Search guest or room…"
          value="<?= htmlspecialchars($search) ?>">
      </div>
      <select class="form-select" name="status" style="max-width:160px;">
        <option value="">All Status</option>
        <option value="active"   <?= $filter_status==='active'   ? 'selected':'' ?>>Active</option>
        <option value="upcoming" <?= $filter_status==='upcoming' ? 'selected':'' ?>>Upcoming</option>
        <option value="past"     <?= $filter_status==='past'     ? 'selected':'' ?>>Past</option>
      </select>
      <button type="submit" class="btn-gold"><i class="bi bi-funnel"></i> Filter</button>
      <?php if ($search || $filter_status): ?>
        <a href="view_reservations.php" class="btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
      <?php endif; ?>
    </form>

    <div class="cw-table-wrap">
      <table class="cw-table">
        <thead>
          <tr>
            <th>#</th>
            <th>Guest</th>
            <th>Room</th>
            <th>Type</th>
            <th>Check In</th>
            <th>Check Out</th>
            <th>Nights</th>
            <th>Total</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($result->num_rows === 0): ?>
            <tr><td colspan="9">
              <div class="empty-state">
                <i class="bi bi-calendar-x"></i>
                <p>No reservations found<?= $search ? " for \"" . htmlspecialchars($search) . "\"" : '' ?>.</p>
              </div>
            </td></tr>
          <?php else: $n = $offset + 1; while ($row = $result->fetch_assoc()):
            [$status, $statusClass] = reservationStatus($row['check_in'], $row['check_out'], $today);
            $nights = max(0, (strtotime($row['check_out']) - strtotime($row['check_in'])) / 86400);
            $total_price = $nights * $row['price'];
          ?>
            <tr>
              <td style="color:var(--text-muted)"><?= $n++ ?></td>
              <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
              <td><?= htmlspecialchars($row['room_number']) ?></td>
              <td style="color:var(--text-muted); font-size:0.82rem"><?= htmlspecialchars($row['room_type']) ?></td>
              <td><?= htmlspecialchars($row['check_in']) ?></td>
              <td><?= htmlspecialchars($row['check_out']) ?></td>
              <td><?= intval($nights) ?></td>
              <td>₱<?= number_format($total_price, 2) ?></td>
              <td><span class="badge-status <?= $statusClass ?>"><?= $status ?></span></td>
            </tr>
          <?php endwhile; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
         href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">
        <i class="bi bi-chevron-left"></i>
      </a>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a class="page-btn <?= $i === $page ? 'active' : '' ?>"
           href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
      <a class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
         href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($filter_status) ?>">
        <i class="bi bi-chevron-right"></i>
      </a>
    </div>
    <?php endif; ?>

  </div>
</div>
</body>
</html>
