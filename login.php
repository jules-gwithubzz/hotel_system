<?php
session_start();
include 'connect_db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: home.php");
    exit();
}

$error = '';
$username_val = '';

if (isset($_POST['login'])) {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    $username_val = htmlspecialchars($u);

    if (empty($u) || empty($p)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $u);
        $stmt->execute();
        $result = $stmt->get_result();
        $user   = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($p, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']  = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];
            header("Location: home.php");
            exit();
        } else {
            $error = "Invalid username or password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login - Hotel </title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  .login-footer { text-align:center; margin-top:1.5rem; color:var(--text-muted); font-size:0.78rem; }
  .show-pass-btn { position:absolute; right:0.9rem; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--text-muted); cursor:pointer; font-size:1rem; padding:0; transition:color .2s; }
  .show-pass-btn:hover { color:var(--gold); }
  .pass-wrap { position:relative; }
  .pass-wrap .form-input { padding-right:2.8rem; }
</style>
</head>
<body>

<div class="login-wrap">
  <div class="login-card">
    <div class="login-logo">
      <i class="bi bi-building"></i>
      <h1>Prisma</h1>
      <p>Admin &amp; Staff Portal</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle-fill"></i> <?= $error ?>
      </div>
    <?php endif; ?>

    <form method="POST" id="loginForm" novalidate>
      <div class="form-group">
        <label class="form-label" for="username"><i class="bi bi-person"></i> Username</label>
        <input class="form-input <?= $error ? 'is-invalid' : '' ?>" type="text" id="username" name="username"
          placeholder="Enter your username" value="<?= $username_val ?>" autocomplete="username" required>
        <div class="form-error" id="err-user">Username is required.</div>
      </div>

      <div class="form-group">
        <label class="form-label" for="password"><i class="bi bi-lock"></i> Password</label>
        <div class="pass-wrap">
          <input class="form-input <?= $error ? 'is-invalid' : '' ?>" type="password" id="password" name="password"
            placeholder="Enter your password" autocomplete="current-password" required>
          <button type="button" class="show-pass-btn" id="togglePass">
            <i class="bi bi-eye" id="eyeIcon"></i>
          </button>
        </div>
        <div class="form-error" id="err-pass">Password is required.</div>
      </div>

      <button type="submit" name="login" class="btn-gold" style="width:100%; justify-content:center; margin-top:0.5rem; padding:0.75rem;">
        <i class="bi bi-box-arrow-in-right"></i> Sign In
      </button>
    </form>

    <div class="login-footer">&copy; <?= date('Y') ?> Cool Waves Hotel Management System</div>
  </div>
</div>

<script>
document.getElementById('loginForm').addEventListener('submit', function(e) {
  let valid = true;
  const user = document.getElementById('username');
  const pass = document.getElementById('password');
  const errUser = document.getElementById('err-user');
  const errPass = document.getElementById('err-pass');
  errUser.classList.remove('visible'); errPass.classList.remove('visible');
  user.classList.remove('is-invalid'); pass.classList.remove('is-invalid');
  if (!user.value.trim()) { errUser.classList.add('visible'); user.classList.add('is-invalid'); valid = false; }
  if (!pass.value.trim()) { errPass.classList.add('visible'); pass.classList.add('is-invalid'); valid = false; }
  if (!valid) e.preventDefault();
});
document.getElementById('togglePass').addEventListener('click', function() {
  const p = document.getElementById('password');
  const icon = document.getElementById('eyeIcon');
  if (p.type === 'password') { p.type = 'text'; icon.className = 'bi bi-eye-slash'; }
  else { p.type = 'password'; icon.className = 'bi bi-eye'; }
});
</script>
</body>
</html>
