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

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"
                aria-controls="navbarNav" aria-expanded="false" aria-label="Navigation ein-/ausblenden"
                style="border-color: var(--vdst-rot);">
            <span style="color: var(--vdst-weiss);" aria-hidden="true">☰</span>
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
                <li class="nav-item">
                    <a class="nav-link <?= strpos(uri_string(), 'schulden') === 0 ? 'active' : '' ?>"
                       href="<?= base_url('/schulden') ?>">
                        💰 Schulden
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= uri_string() === 'inventur' ? 'active' : '' ?>"
                       href="<?= base_url('/inventur') ?>">
                        🧮 Inventur
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
                    <strong>👤 <?= esc(session('kassenwart_name') ?? 'VDSt Kassenwart') ?></strong>
                </span>
                <form action="<?= base_url('/auth/logout') ?>" method="post" class="d-inline"
                      onsubmit="return confirm('Wirklich abmelden?')">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-logout">
                        🚪 Abmelden
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>

<!-- Flash Messages -->
<?php if (session()->getFlashdata('success')): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <strong>✅ Erfolg!</strong> <?= esc(session()->getFlashdata('success')) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
    <div class="container-fluid mt-3">
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>❌ Fehler!</strong> <?= esc(session()->getFlashdata('error')) ?>
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
                    <li><?= esc($error) ?></li>
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

<!-- Gemeinsames Kassensystem-JS -->
<script src="<?= base_url('js/app.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>