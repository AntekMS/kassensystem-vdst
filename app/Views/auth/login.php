<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anmeldung | VDSt Kassensystem</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- VDSt Styling -->
    <style>
        :root {
            --vdst-schwarz: #000000;
            --vdst-weiss: #ffffff;
            --vdst-rot: #dc143c;
            --vdst-grau: #f8f9fa;
        }

        body {
            background: linear-gradient(135deg, var(--vdst-grau) 0%, #e9ecef 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: var(--vdst-weiss);
            border: 3px solid var(--vdst-schwarz);
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            max-width: 450px;
            width: 100%;
        }

        .login-header {
            background: var(--vdst-schwarz);
            color: var(--vdst-weiss);
            text-align: center;
            padding: 2rem;
            border-radius: 7px 7px 0 0;
            border-bottom: 3px solid var(--vdst-rot);
        }

        .login-header h1 {
            margin: 0;
            font-size: 1.8rem;
            font-weight: bold;
        }

        .login-header p {
            margin: 0.5rem 0 0 0;
            color: #ccc;
            font-size: 0.95rem;
        }

        .login-body {
            padding: 2.5rem;
        }

        .form-control {
            border: 2px solid #ddd;
            border-radius: 5px;
            padding: 0.75rem;
            font-size: 1.1rem;
            transition: border-color 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--vdst-rot);
            box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
        }

        .btn-vdst {
            background: var(--vdst-schwarz);
            border: none;
            color: var(--vdst-weiss);
            padding: 0.75rem 2rem;
            font-size: 1.1rem;
            font-weight: bold;
            border-radius: 5px;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-vdst:hover {
            background: var(--vdst-rot);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 20, 60, 0.3);
        }

        .vdst-logo {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .vdst-logo::before {
            content: "🏛️";
            font-size: 3rem;
            display: block;
            margin-bottom: 0.5rem;
        }

        .alert {
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }

        .system-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
            color: #666;
            font-size: 0.85rem;
        }

        /* Password Toggle */
        .password-toggle {
            position: relative;
        }

        .password-toggle-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 0;
        }

        .password-toggle-btn:hover {
            color: var(--vdst-rot);
        }
    </style>
</head>
<body>
<div class="login-container">
    <!-- Header -->
    <div class="login-header">
        <h1>VDSt Kassensystem</h1>
        <p>Verein deutscher Studenten</p>
    </div>

    <!-- Login Form -->
    <div class="login-body">
        <div class="vdst-logo"></div>

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
                        👁️
                    </button>
                </div>
                <small class="text-muted">
                    Geben Sie das Master-Passwort für das Kassensystem ein.
                </small>
            </div>

            <button type="submit" class="btn btn-vdst">
                🔐 Anmelden
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
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleBtn.textContent = '🙈';
            } else {
                passwordInput.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        });

        // Form Submission mit Loading-State
        const form = document.getElementById('loginForm');
        const submitBtn = form.querySelector('button[type="submit"]');

        form.addEventListener('submit', function() {
            submitBtn.innerHTML = '🔄 Wird überprüft...';
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