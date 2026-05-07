<?php
include '../auth.php';
include '../functions.php';
include '../connect_db.php';

if (!isAdmin() && !isStaff()) {
    header("Location: ../login.php");
    exit();
}

$errors  = [];
$success = '';
$vals    = ['name' => '', 'contact' => '', 'email' => ''];

if (isset($_POST['add'])) {
    $name    = trim($_POST['name']    ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email   = trim($_POST['email']   ?? '');

    // Validation
    if (empty($name))                        $errors['name']    = "Full name is required.";
    elseif (strlen($name) < 2)               $errors['name']    = "Name must be at least 2 characters.";
    elseif (!preg_match("/^[\p{L}\s\-'.]+$/u", $name)) $errors['name'] = "Name must contain letters only (no numbers or special characters).";

    if (!empty($contact) && !preg_match('/^[\d\s\+\-\(\)]{7,20}$/', $contact))
                                             $errors['contact'] = "Enter a valid contact number.";

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL))
                                             $errors['email']   = "Enter a valid email address.";

    $vals = ['name' => htmlspecialchars($name), 'contact' => htmlspecialchars($contact), 'email' => htmlspecialchars($email)];

    if (empty($errors)) {
        $stmt = $conn->prepare("INSERT INTO guests (full_name, contact, email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $name, $contact, $email);
        if ($stmt->execute()) {
            $success = "Guest <strong>" . htmlspecialchars($name) . "</strong> added successfully!";
            $vals = ['name' => '', 'contact' => '', 'email' => ''];
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
<title>Add Guest — Cool Waves Hotel</title>
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
    <i class="bi bi-person-plus"></i>
    <h2>Add Guest</h2>
  </div>

  <div class="cw-card">

    <?php if ($success): ?>
      <div class="alert alert-success"><i class="bi bi-check-circle-fill"></i> <?= $success ?></div>
    <?php endif; ?>
    <?php if (isset($errors['db'])): ?>
      <div class="alert alert-danger"><i class="bi bi-exclamation-circle-fill"></i> <?= $errors['db'] ?></div>
    <?php endif; ?>

    <form method="POST" id="guestForm" novalidate>

      <div class="form-group">
        <label class="form-label" for="name"><i class="bi bi-person"></i> Full Name <span style="color:var(--danger)">*</span></label>
        <input class="form-input <?= isset($errors['name']) ? 'is-invalid' : '' ?>"
          type="text" id="name" name="name"
          placeholder="e.g. Juan Dela Cruz"
          value="<?= $vals['name'] ?>" required>
        <?php if (isset($errors['name'])): ?>
          <div class="form-error visible"><?= $errors['name'] ?></div>
        <?php else: ?>
          <div class="form-error" id="err-name">Full name is required.</div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="contact"><i class="bi bi-telephone"></i> Contact Number</label>
        <input class="form-input <?= isset($errors['contact']) ? 'is-invalid' : '' ?>"
          type="tel" id="contact" name="contact"
          placeholder="e.g. 09XX-XXX-XXXX"
          value="<?= $vals['contact'] ?>">
        <?php if (isset($errors['contact'])): ?>
          <div class="form-error visible"><?= $errors['contact'] ?></div>
        <?php else: ?>
          <div class="form-error" id="err-contact"></div>
        <?php endif; ?>
      </div>

      <div class="form-group">
        <label class="form-label" for="email"><i class="bi bi-envelope"></i> Email Address</label>
        <input class="form-input <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
          type="email" id="email" name="email"
          placeholder="e.g. guest@email.com"
          value="<?= $vals['email'] ?>">
        <?php if (isset($errors['email'])): ?>
          <div class="form-error visible"><?= $errors['email'] ?></div>
        <?php else: ?>
          <div class="form-error" id="err-email"></div>
        <?php endif; ?>
      </div>

      <div style="display:flex; gap:0.75rem; margin-top:1.5rem;">
        <button type="submit" name="add" class="btn-gold">
          <i class="bi bi-person-check"></i> Add Guest
        </button>
        <a href="<?= isAdmin() ? 'admin_dashboard.php' : '../staff/staff_dashboard.php' ?>" class="btn-ghost">
          <i class="bi bi-arrow-left"></i> Back
        </a>
      </div>

    </form>
  </div>
</div>

<script>
document.getElementById('guestForm').addEventListener('submit', function(e) {
  let valid = true;
  const name    = document.getElementById('name');
  const contact = document.getElementById('contact');
  const email   = document.getElementById('email');

  // Reset
  [name, contact, email].forEach(f => { f.classList.remove('is-invalid','is-valid'); });
  document.querySelectorAll('.form-error').forEach(el => el.classList.remove('visible'));

  if (!name.value.trim() || name.value.trim().length < 2) {
    name.classList.add('is-invalid');
    document.getElementById('err-name').textContent = 'Full name is required.';
    document.getElementById('err-name').classList.add('visible');
    valid = false;
  } else if (!/^[\p{L}\s\-'.]+$/u.test(name.value.trim())) {
    name.classList.add('is-invalid');
    document.getElementById('err-name').textContent = 'Name must contain letters only (no numbers or special characters).';
    document.getElementById('err-name').classList.add('visible');
    valid = false;
  } else { name.classList.add('is-valid'); }

  const contactVal = contact.value.trim();
  if (contactVal && !/^[\d\s\+\-\(\)]{7,20}$/.test(contactVal)) {
    contact.classList.add('is-invalid');
    const errC = document.getElementById('err-contact');
    errC.textContent = 'Enter a valid contact number.';
    errC.classList.add('visible');
    valid = false;
  } else if (contactVal) { contact.classList.add('is-valid'); }

  const emailVal = email.value.trim();
  if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
    email.classList.add('is-invalid');
    const errE = document.getElementById('err-email');
    errE.textContent = 'Enter a valid email address.';
    errE.classList.add('visible');
    valid = false;
  } else if (emailVal) { email.classList.add('is-valid'); }

  if (!valid) e.preventDefault();
});
</script>
</body>
</html>
