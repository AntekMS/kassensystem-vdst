<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> | VDSt Kassensystem</title>

    <!-- Darkmode VOR dem CSS setzen, sonst kurzes Aufblitzen im falschen Theme -->
    <script>
        (function () {
            var stored = localStorage.getItem('vdst-theme');
            var theme = stored || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            document.documentElement.setAttribute('data-bs-theme', theme);
        })();
    </script>

    <link rel="icon" href="<?= base_url('favicon.ico') ?>">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- VDSt Design-System (einzige Theme-Quelle) -->
    <link href="<?= base_url('css/app.css') ?>?v=9" rel="stylesheet">

    <?= $this->renderSection('styles') ?>
</head>
<body class="app-body">

<!-- Sidebar: ab lg feste Spalte, darunter Offcanvas-Drawer -->
<aside class="offcanvas-lg offcanvas-start app-sidebar" tabindex="-1" id="appSidebar" aria-label="Hauptnavigation">
    <div class="app-sidebar-brand">
        <a href="<?= base_url('/dashboard') ?>">
            <img src="<?= base_url('img/vdst-logo.svg') ?>" alt="" class="app-sidebar-logo">
            VDSt Kassensystem
            <span>Verein deutscher Studenten zu Erlangen</span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#appSidebar" aria-label="Navigation schließen"></button>
    </div>

    <nav class="app-sidebar-nav">
        <a class="app-nav-link <?= uri_string() === 'dashboard' ? 'active' : '' ?>"
           href="<?= base_url('/dashboard') ?>">
            <i class="bi bi-speedometer2" aria-hidden="true"></i> Dashboard
        </a>
        <a class="app-nav-link <?= strpos(uri_string(), 'buchungen') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/buchungen') ?>">
            <i class="bi bi-journal-text" aria-hidden="true"></i> Kassenbuch
        </a>
        <a class="app-nav-link <?= strpos(uri_string(), 'belege') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/belege') ?>">
            <i class="bi bi-receipt" aria-hidden="true"></i> Belege
        </a>
        <a class="app-nav-link <?= strpos(uri_string(), 'schulden') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/schulden') ?>">
            <i class="bi bi-cash-coin" aria-hidden="true"></i> Schulden
        </a>
        <a class="app-nav-link <?= uri_string() === 'inventur' ? 'active' : '' ?>"
           href="<?= base_url('/inventur') ?>">
            <i class="bi bi-calculator" aria-hidden="true"></i> Inventur
        </a>
        <a class="app-nav-link <?= strpos(uri_string(), 'muster') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/muster') ?>">
            <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Muster &amp; Vorlagen
        </a>

        <div class="app-nav-group">Abrechnungen</div>
        <a class="app-nav-link app-nav-sub <?= strpos(uri_string(), 'abrechnungen/ah') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/abrechnungen/ah') ?>">
            <i class="bi bi-bank" aria-hidden="true"></i> AH² Abrechnungen
        </a>
        <a class="app-nav-link app-nav-sub <?= strpos(uri_string(), 'abrechnungen/hv') === 0 ? 'active' : '' ?>"
           href="<?= base_url('/abrechnungen/hv') ?>">
            <i class="bi bi-house" aria-hidden="true"></i> HV Abrechnungen
        </a>
    </nav>

    <div class="app-sidebar-foot">
        <span class="app-sidebar-user">
            <i class="bi bi-person-circle" aria-hidden="true"></i>
            <?= esc(session('kassenwart_name') ?? 'VDSt Kassenwart') ?>
        </span>
        <div class="d-flex align-items-center gap-2">
            <button type="button" class="app-theme-toggle js-theme-toggle" title="Darkmode umschalten"
                    aria-label="Darkmode umschalten" aria-pressed="false">
                <i class="bi bi-moon-stars" aria-hidden="true"></i>
            </button>
            <form action="<?= base_url('/auth/logout') ?>" method="post" class="d-inline"
                  onsubmit="return confirm('Wirklich abmelden?')">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-logout">
                    <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Abmelden
                </button>
            </form>
        </div>
    </div>
</aside>

<div class="app-content">
    <!-- Mobile Topbar -->
    <header class="app-topbar d-lg-none">
        <button type="button" class="app-topbar-btn" data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                aria-controls="appSidebar" title="Navigation öffnen" aria-label="Navigation öffnen">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <a class="app-topbar-brand" href="<?= base_url('/dashboard') ?>">
            <img src="<?= base_url('img/vdst-logo.svg') ?>" alt="" class="app-topbar-logo">
            VDSt Kassensystem
        </a>
        <button type="button" class="app-topbar-btn js-theme-toggle" title="Darkmode umschalten"
                aria-label="Darkmode umschalten" aria-pressed="false">
            <i class="bi bi-moon-stars" aria-hidden="true"></i>
        </button>
        <a class="app-topbar-btn app-topbar-action" href="<?= base_url('/belege/create') ?>"
           title="Beleg erfassen" aria-label="Beleg erfassen">
            <i class="bi bi-plus-lg" aria-hidden="true"></i>
        </a>
    </header>

    <!-- Flash Messages -->
    <?php if (session()->getFlashdata('success')): ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Erfolg!</strong> <?= esc(session()->getFlashdata('success')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('error')): ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Fehler!</strong> <?= esc(session()->getFlashdata('error')) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (session()->getFlashdata('errors')): ?>
        <div class="container-fluid mt-3">
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong><i class="bi bi-x-circle-fill" aria-hidden="true"></i> Validierungsfehler:</strong>
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
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Gemeinsames Kassensystem-JS -->
<script src="<?= base_url('js/app.js') ?>?v=1"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
