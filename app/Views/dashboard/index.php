<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">VDSt Kassensystem - Übersicht</h1>

        <!-- Kontostand-Übersicht -->
        <div class="row mb-4">
            <?php
            $gesamtsaldo = 0;
            foreach($kontostaende as $konto => $daten):
                $gesamtsaldo += $daten['saldo'];
                ?>
                <div class="col-md-3">
                    <div class="card kontostand-card">
                        <div class="card-header">
                            <?= $this->include('buchungen/components/konto_name', ['konto' => $konto]) ?>
                        </div>
                        <div class="card-body text-center">
                            <h3 class="<?= $daten['saldo'] >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                                <?= number_format($daten['saldo'], 2, ',', '.') ?> €
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Gesamtsaldo -->
            <div class="col-md-3">
                <div class="card border-3" style="border-color: var(--vdst-rot) !important;">
                    <div class="card-header text-center" style="background-color: var(--vdst-rot); color: white;">
                        <strong>GESAMTSALDO</strong>
                    </div>
                    <div class="card-body text-center">
                        <h2 class="<?= $gesamtsaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                            <?= number_format($gesamtsaldo, 2, ',', '.') ?> €
                        </h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schnellzugriff -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card card-vdst">
                    <div class="card-header">
                        <strong>Schnellzugriff</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-vdst w-100 mb-2">
                                    <strong>+ Neue Buchung</strong>
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst w-100 mb-2">
                                    📖 Kassenbuch öffnen
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst w-100 mb-2">
                                    📄 Belege verwalten
                                </a>
                            </div>
                            <div class="col-md-3">
                                <a href="<?= base_url('/abrechnungen/ah') ?>" class="btn btn-outline-vdst w-100 mb-2">
                                    📊 Abrechnungen
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Statistiken -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Belege</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Gesamt:</td>
                                <td class="text-end"><strong><?= $beleg_stats['gesamt_belege'] ?></strong></td>
                            </tr>
                            <tr>
                                <td>Neue (erfasst):</td>
                                <td class="text-end"><strong><?= $beleg_stats['neue_belege'] ?></strong></td>
                            </tr>
                            <tr>
                                <td>In Abrechnung:</td>
                                <td class="text-end"><?= $beleg_stats['in_abrechnung'] ?></td>
                            </tr>
                            <tr>
                                <td>AH² berechtigt:</td>
                                <td class="text-end"><?= $beleg_stats['ah_berechtigt'] ?></td>
                            </tr>
                            <tr>
                                <td>HV berechtigt:</td>
                                <td class="text-end"><?= $beleg_stats['hv_berechtigt'] ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Buchungen</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>Heute:</td>
                                <td class="text-end"><strong><?= $buchung_stats['buchungen_heute'] ?></strong></td>
                            </tr>
                            <tr>
                                <td>Diesen Monat:</td>
                                <td class="text-end"><strong><?= $buchung_stats['buchungen_monat'] ?></strong></td>
                            </tr>
                            <tr>
                                <td>Ohne Beleg:</td>
                                <td class="text-end"><?= $buchung_stats['ohne_beleg'] ?></td>
                            </tr>
                            <tr>
                                <td colspan="2">
                                    <?php if (!empty($buchung_stats['letzte_buchung'])): ?>
                                        <small class="text-muted">
                                            Letzte: <?= date('d.m.Y', strtotime($buchung_stats['letzte_buchung']['buchungsdatum'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Abrechnungen</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm mb-0">
                            <tr>
                                <td>AH² Entwürfe:</td>
                                <td class="text-end"><?= $ah_stats['entwuerfe'] ?></td>
                            </tr>
                            <tr>
                                <td>AH² Ausstehend:</td>
                                <td class="text-end"><strong><?= $ah_stats['ausstehend'] ?></strong></td>
                            </tr>
                            <tr>
                                <td>HV Entwürfe:</td>
                                <td class="text-end"><?= $hv_stats['entwuerfe'] ?></td>
                            </tr>
                            <tr>
                                <td>HV Ausstehend:</td>
                                <td class="text-end"><strong><?= $hv_stats['ausstehend'] ?></strong></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktuelle Infos -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Letzte 5 Buchungen</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($neueste_buchungen)): ?>
                            <div class="p-3 text-center text-muted">
                                Noch keine Buchungen vorhanden.
                            </div>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <?php foreach($neueste_buchungen as $buchung): ?>
                                    <tr>
                                        <td style="width: 80px;">
                                            <small><?= date('d.m.', strtotime($buchung['buchungsdatum'])) ?></small>
                                        </td>
                                        <td>
                                            <?= esc(substr($buchung['beschreibung'], 0, 40)) ?><?= strlen($buchung['beschreibung']) > 40 ? '...' : '' ?>
                                        </td>
                                        <td class="text-end" style="width: 80px;">
                                    <span class="<?= $buchung['buchungsart'] === 'einnahme' ? 'text-success' : 'text-danger' ?>">
                                        <?= $buchung['buchungsart'] === 'einnahme' ? '+' : '-' ?><?= number_format($buchung['betrag'], 0, ',', '.') ?> €
                                    </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst btn-sm">
                            Alle Buchungen anzeigen
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Letzte 5 Belege</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($neueste_belege)): ?>
                            <div class="p-3 text-center text-muted">
                                Noch keine Belege vorhanden.
                            </div>
                        <?php else: ?>
                            <table class="table table-sm mb-0">
                                <?php foreach($neueste_belege as $beleg): ?>
                                    <tr>
                                        <td style="width: 100px;">
                                            <small><?= $beleg['belegnummer'] ?></small>
                                        </td>
                                        <td>
                                            <?= esc(substr($beleg['beschreibung'], 0, 30)) ?><?= strlen($beleg['beschreibung']) > 30 ? '...' : '' ?>
                                        </td>
                                        <td class="text-end" style="width: 80px;">
                                            <?= number_format($beleg['betrag'], 0, ',', '.') ?> €
                                        </td>
                                        <td style="width: 60px;">
                                    <span class="badge bg-<?= $beleg['status'] === 'erfasst' ? 'secondary' : 'primary' ?> badge-sm">
                                        <?= $beleg['status'] ?>
                                    </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </table>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst btn-sm">
                            Alle Belege anzeigen
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- System-Info -->
        <?php if (!empty($alerts)): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header" style="background-color: var(--vdst-rot); color: white;">
                            <strong>⚠️ Wichtige Hinweise</strong>
                        </div>
                        <div class="card-body">
                            <?php foreach($alerts as $alert): ?>
                                <div class="alert alert-<?= $alert['type'] ?> alert-sm">
                                    <?= $alert['message'] ?>
                                    <?php if (!empty($alert['link'])): ?>
                                        <a href="<?= base_url($alert['link']) ?>" class="btn btn-outline-dark btn-sm ms-2">
                                            Anzeigen
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-Refresh der Zahlen alle 5 Minuten (optional)
            // setInterval(function() {
            //     location.reload();
            // }, 300000);
        });
    </script>
<?= $this->endSection() ?>