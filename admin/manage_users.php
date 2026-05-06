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
$vals    = ['username' => '', 'role' => 'staff'];

// Add user — hashed password
if (isset($_POST['add'])) {
    $user = trim($_POST['username'] ?? '');
    $pass = trim($_POST['password'] ?? '');
    $role = in_array($_POST['role'] ?? '', ['admin','staff']) ? $_POST['role'] : 'staff';

    $vals = ['username' => htmlspecialchars($user), 'role' => $role];

    if (empty($user))          $errors['username'] = "Username is required.";
    elseif (strlen($user) < 3) $errors['username'] = "Username must be at least 3 characters.";
    elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $user))
                               $errors['username'] = "Only letters, numbers, and underscores allowed.";

    if (empty($pass))          $errors['password'] = "Password is required.";
    elseif (strlen($pass) < 6) $errors['password'] = "Password must be at least 6 characters.";

    // Check duplicate username
    if (empty($errors['username'])) {
        $chk = $conn->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $chk->bind_param("s", $user);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) $errors['username'] = "Username already exists.";
        $chk->close();
    }

    if (empty($errors)) {
        $hashed = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $user, $hashed, $role);
        if ($stmt->execute()) {
            $success = "User <strong>" . htmlspecialchars($user) . "</strong> added successfully!";
            $vals = ['username' => '', 'role' => 'staff'];
        } else {
            $errors['db'] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
}

// Delete user — cannot delete yourself
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id === intval($_SESSION['user_id'])) {
        $delete_error = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $del_id);
        $stmt->execute();
        $stmt->close();
        $success = "User deleted successfully.";
    }
}

// Search & pagination
$search   = trim($_GET['search'] ?? '');
$per_page = 8;
$page     = max(1, intval($_GET['page'] ?? 1));
$offset   = ($page - 1) * $per_page;

$where_sql = $search ? "WHERE username LIKE ? OR role LIKE ?" : '';
$params    = $search ? ['%'.$search.'%', '%'.$search.'%'] : [];
$types     = $search ? 'ss' : '';

$cnt_stmt = $conn->prepare("SELECT COUNT(*) AS t FROM users $where_sql");
if ($params) $cnt_stmt->bind_param($types, ...$params);
$cnt_stmt->execute();
$total = $cnt_stmt->get_result()->fetch_assoc()['t'];
$cnt_stmt->close();
$total_pages = max(1, ceil($total / $per_page));

$all_params = array_merge($params, [$per_page, $offset]);
$all_types  = $types . 'ii';
$data_stmt = $conn->prepare("SELECT id, username, role FROM users $where_sql ORDER BY id LIMIT ? OFFSET ?");
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$users = $data_stmt->get_result();
$data_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Users — Cool Waves Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="../style.css">
</head>
<body>
<video id="bg-video" autoplay muted loop playsinline>
  <source src="../images/22.mp4" type="video/mp4">
</video>

<?php include '../navbar.php'; ?>

<div class="cw-page">

  <div class="page-title">
    <i class="bi bi-people"></i>
    <h2>Manage Users</h2>
  </div>

  <div style="display:grid; grid-template-columns:340px 1fr; gap:1.5rem; align-items:start;">

    <!-- Add User Form -->
    <div class="cw-card">
      <div class="cw-card-header" style="margin-bottom:1.25rem;">
        <span class="cw-card-title"><i class="bi bi-person-plus"></i> Add User</span>
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

      <form method="POST" id="userForm" novalidate>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-at"></i> Username <span style="color:var(--danger)">*</span></label>
          <input class="form-input <?= isset($errors['username']) ? 'is-invalid':'' ?>"
            type="text" name="username"
            placeholder="letters, numbers, _"
            value="<?= $vals['username'] ?>" required>
          <?php if (isset($errors['username'])): ?>
            <div class="form-error visible"><?= $errors['username'] ?></div>
          <?php else: ?>
            <div class="form-error" id="err-uname">Username is required (min 3 chars).</div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-lock"></i> Password <span style="color:var(--danger)">*</span></label>
          <div style="position:relative;">
            <input class="form-input <?= isset($errors['password']) ? 'is-invalid':'' ?>"
              type="password" id="newPass" name="password"
              placeholder="Min 6 characters" required>
            <button type="button" onclick="toggleNewPass()" style="position:absolute;right:0.9rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem;">
              <i class="bi bi-eye" id="newPassIcon"></i>
            </button>
          </div>
          <?php if (isset($errors['password'])): ?>
            <div class="form-error visible"><?= $errors['password'] ?></div>
          <?php else: ?>
            <div class="form-error" id="err-pass">Password must be at least 6 characters.</div>
          <?php endif; ?>
        </div>

        <div class="form-group">
          <label class="form-label"><i class="bi bi-shield"></i> Role</label>
          <select class="form-select" name="role">
            <option value="staff" <?= $vals['role']==='staff'?'selected':'' ?>>Staff</option>
            <option value="admin" <?= $vals['role']==='admin'?'selected':'' ?>>Admin</option>
          </select>
        </div>

        <button type="submit" name="add" class="btn-gold" style="width:100%; justify-content:center;">
          <i class="bi bi-person-plus"></i> Add User
        </button>
      </form>
    </div>

    <!-- Users Table -->
    <div class="cw-card">
      <div class="cw-card-header">
        <span class="cw-card-title"><i class="bi bi-table"></i>All Users
           <span style="color:var(--text-muted); font-size:0.9rem; font-family:'DM Sans',sans-serif; font-weight:400;">
             (<?= $total ?> total)
           </span>
          <span style="color:var(--text-muted); font-size:0.9rem; font-family:'DM Sans',sans-serif; font-weight:400;">(<?= $total ?>)</span>
        </span>
      </div>

      <form method="GET" class="search-bar" style="margin-bottom:1rem;">
        <div class="search-input-wrap">
          <i class="bi bi-search"></i>
          <input class="form-input" type="text" name="search"
            placeholder="Search username or role…"
            value="<?= htmlspecialchars($search) ?>">
        </div>
        <button type="submit" class="btn-gold"><i class="bi bi-funnel"></i> Search</button>
        <?php if ($search): ?>
          <a href="manage_users.php" class="btn-ghost"><i class="bi bi-x-lg"></i> Clear</a>
        <?php endif; ?>
      </form>

      <div class="cw-table-wrap">
        <table class="cw-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Username</th>
              <th>Role</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($users->num_rows === 0): ?>
              <tr><td colspan="4">
                <div class="empty-state">
                  <i class="bi bi-people"></i>
                  <p>No users found.</p>
                </div>
              </td></tr>
            <?php else: while ($u = $users->fetch_assoc()): ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $u['id'] ?></td>
                <td>
                  <strong><?= htmlspecialchars($u['username']) ?></strong>
                  <?php if ($u['id'] == $_SESSION['user_id']): ?>
                    <span style="color:var(--gold); font-size:0.75rem; margin-left:0.35rem;">(you)</span>
                  <?php endif; ?>
                </td>
                <td><span class="badge-status <?= $u['role'] ?>"><?= ucfirst($u['role']) ?></span></td>
                <td>
                  <?php if ($u['id'] != $_SESSION['user_id']): ?>
                    <a href="#" class="btn-danger-sm"
                       onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>'); return false;">
                      <i class="bi bi-trash"></i> Delete
                    </a>
                  <?php else: ?>
                    <span style="color:var(--text-muted); font-size:0.8rem;">—</span>
                  <?php endif; ?>
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
    <h4><i class="bi bi-exclamation-triangle-fill"></i> Delete User?</h4>
    <p id="confirmMsg">Are you sure?</p>
    <div class="confirm-actions">
      <a id="confirmYes" href="#" class="btn-danger-sm" style="padding:0.5rem 1.25rem; font-size:0.875rem;">
        <i class="bi bi-trash"></i> Delete
      </a>
      <button onclick="document.getElementById('confirmOverlay').classList.remove('show')" class="btn-ghost">Cancel</button>
    </div>
  </div>
</div>

<script>
function confirmDeleteUser(id, name) {
  document.getElementById('confirmMsg').textContent = 'Delete user "' + name + '"? This cannot be undone.';
  document.getElementById('confirmYes').href = '?delete=' + id + '&search=<?= urlencode($search) ?>&page=<?= $page ?>';
  document.getElementById('confirmOverlay').classList.add('show');
}
document.getElementById('confirmOverlay').addEventListener('click', function(e) {
  if (e.target === this) this.classList.remove('show');
});

function toggleNewPass() {
  const p = document.getElementById('newPass');
  const i = document.getElementById('newPassIcon');
  p.type = p.type === 'password' ? 'text' : 'password';
  i.className = p.type === 'password' ? 'bi bi-eye' : 'bi bi-eye-slash';
}

document.getElementById('userForm').addEventListener('submit', function(e) {
  let valid = true;
  const uname = this.elements['username'];
  const pass  = document.getElementById('newPass');
  document.getElementById('err-uname').classList.remove('visible');
  document.getElementById('err-pass').classList.remove('visible');
  uname.classList.remove('is-invalid'); pass.classList.remove('is-invalid');

  if (!uname.value.trim() || uname.value.trim().length < 3) {
    uname.classList.add('is-invalid');
    document.getElementById('err-uname').classList.add('visible');
    valid = false;
  }
  if (!pass.value || pass.value.length < 6) {
    pass.classList.add('is-invalid');
    document.getElementById('err-pass').classList.add('visible');
    valid = false;
  }
  if (!valid) e.preventDefault();
});
</script>
</body>
</html>
