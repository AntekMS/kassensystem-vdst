<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> | VDSt Kassensystem</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- VDSt Custom CSS -->
    <style>
        :root {
            --vdst-schwarz: #000000;
            --vdst-weiss: #ffffff;
            --vdst-rot: #dc143c;
            --vdst-grau: #f8f9fa;
            --vdst-dunkelgrau: #343a40;
        }

        body {
            background-color: var(--vdst-grau);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* VDSt Navigation */
        .navbar-vdst {
            background-color: var(--vdst-schwarz) !important;
            border-bottom: 3px solid var(--vdst-rot);
        }

        .navbar-vdst .navbar-brand {
            color: var(--vdst-weiss) !important;
            font-weight: bold;
            font-size: 1.5rem;
        }

        .navbar-vdst .nav-link {
            color: var(--vdst-weiss) !important;
            transition: color 0.3s ease;
        }

        .navbar-vdst .nav-link:hover,
        .navbar-vdst .nav-link.active {
            color: var(--vdst-rot) !important;
        }

        /* Session Info */
        .session-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .session-time {
            font-size: 0.85rem;
            color: #ccc;
        }

        .btn-logout {
            background: var(--vdst-rot);
            border: none;
            color: var(--vdst-weiss);
            padding: 0.25rem 0.75rem;
            border-radius: 4px;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-logout:hover {
            background: #b71c1c;
            color: var(--vdst-weiss);
        }

        /* Content Area */
        .main-content {
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        /* VDSt Buttons */
        .btn-vdst {
            background-color: var(--vdst-schwarz);
            border-color: var(--vdst-schwarz);
            color: var(--vdst-weiss);
        }

        .btn-vdst:hover {
            background-color: var(--vdst-rot);
            border-color: var(--vdst-rot);
            color: var(--vdst-weiss);
        }

        .btn-outline-vdst {
            border-color: var(--vdst-schwarz);
            color: var(--vdst-schwarz);
        }

        .btn-outline-vdst:hover {
            background-color: var(--vdst-schwarz);
            border-color: var(--vdst-schwarz);
            color: var(--vdst-weiss);
        }

        /* Table Headers */
        .table-vdst {
            --bs-table-bg: var(--vdst-schwarz);
            --bs-table-color: var(--vdst-weiss);
        }

        /* Cards */
        .card-vdst {
            border: 2px solid var(--vdst-schwarz);
        }

        .card-vdst .card-header {
            background-color: var(--vdst-schwarz);
            color: var(--vdst-weiss);
            border-bottom: 1px solid var(--vdst-rot);
        }

        /* Alert Styles */
        .alert-vdst {
            background-color: var(--vdst-rot);
            border-color: var(--vdst-rot);
            color: var(--vdst-weiss);
        }

        /* Kontostand Cards */
        .kontostand-card {
            border: 2px solid var(--vdst-schwarz);
            background-color: var(--vdst-weiss);
        }

        .kontostand-card .card-header {
            background-color: var(--vdst-schwarz);
            color: var(--vdst-weiss);
            font-weight: bold;
            text-align: center;
        }

        .saldo-positiv {
            color: #28a745 !important;
            font-weight: bold;
        }

        .saldo-negativ {
            color: var(--vdst-rot) !important;
            font-weight: bold;
        }

        /* Page Title */
        .page-title {
            color: var(--vdst-schwarz);
            border-bottom: 2px solid var(--vdst-rot);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }

        /* Session Timeout Warning */
        .session-warning {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 1050;
            max-width: 300px;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .session-info {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-end;
            }

            .session-time {
                font-size: 0.75rem;
            }

            .navbar-nav {
                text-align: center;
            }
        }
    </style>

    <?= $this->renderSection('styles') ?>
</head>
<body>
<!-- VDSt Navigation -->
<nav class="navbar navbar-expand-lg navbar-vdst">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?= base_url('/dashboard') ?>">
            VDSt Kassensystem
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" style="border-color: var(--vdst-rot);">
            <span style="color: var(--vdst-weiss);">☰</span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?= uri_string() === 'dashboard' ? 'active' : '' ?>"
                       href="<?= base_url('/dashboard') ?>">
                        📊 Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(uri_string(), 'buchungen') === 0 ? 'active' : '' ?>"
                       href="<?= base_url('/buchungen') ?>">
                        📖 Kassenbuch
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= strpos(uri_string(), 'belege') === 0 ? 'active' : '' ?>"
                       href="<?= base_url('/belege') ?>">
                        📄 Belege
                    </a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= strpos(uri_string(), 'abrechnungen') === 0 ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown">
                        📋 Abrechnungen
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="<?= base_url('/abrechnungen/ah') ?>">🏛️ AH² Abrechnungen</a></li>
                        <li><a class="dropdown-item" href="<?= base_url('/abrechnungen/hv') ?>">🏠 HV Abrechnungen</a></li>
                    </ul>
                </li>
            </ul>

            <!-- Session Info und Logout -->
            <div class="session-info">
                <span class="navbar-text text-white">
                    <strong>👤 <?= session('kassenwart_name') ?? 'VDSt Kassenwart' ?></strong>
                </span>
                <span class="session-time" id="sessionTime">
                    <!-- Wird per JavaScript gefüllt -->
                </span>
                <a href="<?= base_url('/auth/logout') ?>"
                   class="btn btn-logout"
                   onclick="return confirm('Wirklich abmelden?')">
                    🚪 Abmelden
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Session Timeout Warning -->
<div id="sessionWarning" class="session-warning" style="display: none;">
    <div class="alert alert-warning alert-dismissible">
        <strong>⏰ Session läuft ab!</strong><br>
        Ihre Sitzung läuft in <span id="warningTime"></span> Minuten ab.
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        <div class="mt-2">
            <button class="btn btn-sm btn-warning" onclick="refreshSession()">
                🔄 Session verlängern
            </button>
        </div>
    </div>
</div>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ Erfolg!</strong> <?= session()->getFlashdata('success') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Fehler!</strong> <?= session()->getFlashdata('error') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('errors')): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Validierungsfehler:</strong>
            <ul class="mb-0 mt-2">
                <?php foreach (session()->getFlashdata('errors') as $error): ?>
                    <li><?= $error ?></li>
                <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<!-- Main Content -->
<main class="main-content">
    <?= $this->renderSection('content') ?>
</main>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JS -->
<script>
    // Session Management
    let sessionStartTime = <?= session('login_time') ?? time() ?> * 1000; // Convert to milliseconds
    const sessionTimeout = 0.5 * 60 * 60 * 1000; // 0.5 hours in milliseconds
    const warningTime = 5 * 60 * 1000; // Show warning 5 minutes before timeout

    // Einfache Bestätigungsdialoge
    function confirmDelete(message = 'Sind Sie sicher, dass Sie diesen Eintrag löschen möchten?') {
        return confirm(message);
    }

    // Session-Zeit anzeigen
    function updateSessionTime() {
        const now = new Date().getTime();
        const elapsed = now - sessionStartTime;
        const remaining = sessionTimeout - elapsed;

        if (remaining <= 0) {
            // Session abgelaufen
            alert('Ihre Sitzung ist abgelaufen. Sie werden zur Anmeldung weitergeleitet.');
            window.location.href = '<?= base_url('/auth/login') ?>';
            return;
        }

        const hours = Math.floor(remaining / (1000 * 60 * 60));
        const minutes = Math.floor((remaining % (1000 * 60 * 60)) / (1000 * 60));

        const sessionTimeElement = document.getElementById('sessionTime');
        if (sessionTimeElement) {
            if (hours > 0) {
                sessionTimeElement.textContent = `⏰ ${hours}h ${minutes}m`;
            } else {
                sessionTimeElement.textContent = `⏰ ${minutes}m`;

                // Warnung färben wenn weniger als 30 Minuten
                if (minutes < 30) {
                    sessionTimeElement.style.color = '#ff6b6b';
                } else {
                    sessionTimeElement.style.color = '#ccc';
                }
            }
        }

        // Warning anzeigen wenn weniger als 15 Minuten verbleiben
        if (remaining <= warningTime && remaining > 0) {
            showSessionWarning(Math.ceil(remaining / (1000 * 60)));
        }
    }

    // Session Warning anzeigen
    function showSessionWarning(minutesLeft) {
        const warningElement = document.getElementById('sessionWarning');
        const warningTimeElement = document.getElementById('warningTime');

        if (warningElement && warningTimeElement) {
            warningTimeElement.textContent = minutesLeft;
            warningElement.style.display = 'block';
        }
    }

    // Session verlängern
    function refreshSession() {
        fetch('<?= base_url('/auth/refresh') ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    sessionStartTime = new Date().getTime();
                    document.getElementById('sessionWarning').style.display = 'none';

                    // Success-Message anzeigen
                    showAlert('✅ Session verlängert! Sie sind für weitere 8 Stunden angemeldet.', 'success');
                } else {
                    alert('Session konnte nicht verlängert werden. Bitte melden Sie sich erneut an.');
                    window.location.href = '<?= base_url('/auth/login') ?>';
                }
            })
            .catch(error => {
                console.error('Session refresh error:', error);
                alert('Fehler beim Verlängern der Session.');
            });
    }

    // Alert Helper Function
    function showAlert(message, type = 'info') {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        const mainContent = document.querySelector('.main-content');
        mainContent.insertBefore(alertDiv, mainContent.firstChild);

        // Auto-hide nach 3 Sekunden
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 20000);
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        // Session-Zeit sofort aktualisieren und dann jede Minute
        updateSessionTime();
        setInterval(updateSessionTime, 60000); // Update every minute

        // Auto-hide Flash-Messages
        const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                if (alert && alert.parentNode) {
                    const bsAlert = new bootstrap.Alert(alert);
                    if (bsAlert) {
                        bsAlert.close();
                    }
                }
            }, 20000);
        });

        // Activity Detection für automatische Session-Verlängerung
        let lastActivity = new Date().getTime();
        const activityEvents = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];

        activityEvents.forEach(event => {
            document.addEventListener(event, function() {
                const now = new Date().getTime();
                // Nur alle 5 Minuten bei Aktivität Session refreshen
                if (now - lastActivity > 5 * 60 * 1000) {
                    lastActivity = now;

                    // Stille Session-Verlängerung bei Aktivität
                    fetch('<?= base_url('/auth/refresh') ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    }).then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                sessionStartTime = new Date().getTime();
                                // Session Warning verstecken falls sichtbar
                                const warning = document.getElementById('sessionWarning');
                                if (warning) {
                                    warning.style.display = 'none';
                                }
                                // Session-Zeit-Farbe zurücksetzen
                                const sessionTime = document.getElementById('sessionTime');
                                if (sessionTime) {
                                    sessionTime.style.color = '#ccc';
                                }
                            }
                        }).catch(error => {
                        console.log('Background session refresh failed:', error);
                    });
                }
            }, true);
        });
    });

    // Format currency inputs
    function formatCurrency(input) {
        let value = input.value.replace(/[^\d,.-]/g, '');
        value = value.replace(',', '.');
        input.value = value;
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
        // Ctrl+Q = Quick Logout
        if (e.ctrlKey && e.key === 'q') {
            e.preventDefault();
            if (confirm('Wirklich abmelden? (Ctrl+Q)')) {
                window.location.href = '<?= base_url('/auth/logout') ?>';
            }
        }

        // Ctrl+D = Dashboard
        if (e.ctrlKey && e.key === 'd') {
            e.preventDefault();
            window.location.href = '<?= base_url('/dashboard') ?>';
        }

        // Ctrl+B = Buchungen/Kassenbuch
        if (e.ctrlKey && e.key === 'b') {
            e.preventDefault();
            window.location.href = '<?= base_url('/buchungen') ?>';
        }

        // Ctrl+E = Belege
        if (e.ctrlKey && e.key === 'e') {
            e.preventDefault();
            window.location.href = '<?= base_url('/belege') ?>';
        }
    });

    // Console Info für Entwickler
    console.log('🏛️ VDSt Kassensystem geladen');
    console.log('⌨️ Keyboard Shortcuts: Ctrl+D (Dashboard), Ctrl+B (Buchungen), Ctrl+E (Belege), Ctrl+Q (Logout)');
</script>

<?= $this->renderSection('scripts') ?>
</body>
</html>