<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung | VDSt Kassensystem</title>

    <link rel="icon" href="<?= base_url('favicon.ico') ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- VDSt Design-System (einzige Theme-Quelle) -->
    <link href="<?= base_url('css/app.css') ?>?v=2" rel="stylesheet">
</head>
<body class="login-page">
<div class="login-container">
    <!-- Header -->
    <div class="login-header">
        <h1>VDSt Kassensystem</h1>
        <p>Verein deutscher Studenten zu Erlangen</p>
    </div>

    <!-- Login Form -->
    <div class="login-body">
        <!-- Error Messages -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger">
                <strong>Fehler:</strong> <?= esc($error) ?>
            </div>
        <?php endif; ?>

        <!-- Success Messages -->
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success">
                <?= esc(session()->getFlashdata('success')) ?>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('/auth/authenticate') ?>" method="post" id="loginForm">
            <?= csrf_field() ?>

            <div class="mb-4">
                <label for="password" class="form-label">
                    <strong>Kassenwart-Passwort</strong>
                </label>
                <div class="password-toggle">
                    <input type="password"
                           class="form-control"
                           id="password"
                           name="password"
                           placeholder="Passwort eingeben..."
                           required
                           autofocus>
                    <button type="button" class="password-toggle-btn" id="togglePassword" aria-label="Passwort anzeigen oder verbergen">
                        <i class="bi bi-eye" aria-hidden="true"></i>
                    </button>
                </div>
                <small class="text-muted">
                    Geben Sie das Master-Passwort für das Kassensystem ein.
                </small>
            </div>

            <button type="submit" class="btn btn-vdst w-100">
                <i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Anmelden
            </button>
        </form>

        <!-- System Info -->
        <div class="system-info">
            <strong>VDSt Kassensystem</strong><br>
            Digitales Kassenbuch und Abrechnungssystem<br>
            <small>Version 1.0 | CodeIgniter 4</small>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password Toggle
        const passwordInput = document.getElementById('password');
        const toggleBtn = document.getElementById('togglePassword');

        toggleBtn.addEventListener('click', function() {
            const icon = toggleBtn.querySelector('.bi');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });

        // Form Submission mit Loading-State
        const form = document.getElementById('loginForm');
        const submitBtn = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function() {
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Wird überprüft...';
            submitBtn.disabled = true;
        });

        // Auto-hide alerts nach 5 Sekunden
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 300);
            }, 5000);
        });

        // Enter-Taste Submit
        passwordInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                form.submit();
            }
        });
    });
</script>
</body>
</html>
