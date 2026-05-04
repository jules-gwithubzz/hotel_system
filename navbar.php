<?php
// Shared navbar — include after session/auth
$isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$username = htmlspecialchars($_SESSION['username'] ?? 'User');
$role     = $_SESSION['role'] ?? 'staff';
// Detect current file for active link
$current = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function navLink($href, $icon, $label, $current) {
    $base = basename($href);
    $active = ($base === $current) ? 'active' : '';
    return "<li><a href=\"$href\" class=\"$active\"><i class=\"bi bi-$icon\"></i> $label</a></li>";
}

// Base path: admin/ pages need ../ prefix
$base = ($currentDir === 'admin' || $currentDir === 'staff') ? '../' : '';
?>
<nav class="cw-navbar">
  <a href="<?= $base ?>home.php" class="cw-brand">
    <i class="bi bi-building"></i> Cool Waves Hotel
  </a>

  <button class="cw-toggler" id="navToggle"><i class="bi bi-list"></i></button>

  <ul class="cw-nav-links" id="navLinks">
    <?php if ($isAdmin): ?>
      <?= navLink($base.'admin/admin_dashboard.php', 'speedometer2', 'Dashboard', $current) ?>
    <?php else: ?>
      <?= navLink($base.'staff/staff_dashboard.php', 'speedometer2', 'Dashboard', $current) ?>
    <?php endif; ?>
    <?= navLink($base.'admin/add_guest.php',         'person-plus',  'Add Guest',      $current) ?>
    <?= navLink($base.'admin/add_reservation.php',   'calendar-plus','Reservation',    $current) ?>
    <?= navLink($base.'admin/view_reservations.php', 'list-check',   'Reservations',   $current) ?>
    <?php if ($isAdmin): ?>
      <?= navLink($base.'admin/manage_rooms.php',    'door-open',    'Rooms',          $current) ?>
      <?= navLink($base.'admin/manage_users.php',    'people',       'Users',          $current) ?>
    <?php endif; ?>
  </ul>

  <div class="cw-nav-right" id="navRight">
    <div class="cw-user-badge">
      <i class="bi bi-person-circle"></i>
      <span><?= $username ?></span>
      <span class="role-pill <?= $role === 'staff' ? 'staff' : '' ?>"><?= ucfirst($role) ?></span>
    </div>
    <a href="<?= $base ?>logout.php" class="btn-logout">
      <i class="bi bi-box-arrow-right"></i> Logout
    </a>
  </div>
</nav>
<script>
document.getElementById('navToggle').addEventListener('click', function() {
  document.getElementById('navLinks').classList.toggle('open');
  document.getElementById('navRight').classList.toggle('open');
});
</script>
