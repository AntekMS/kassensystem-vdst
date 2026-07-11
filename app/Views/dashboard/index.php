<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <h1 class="page-title">Übersicht</h1>

        <!-- 1. Kontostände (mobil 2×2, ab lg nebeneinander) -->
        <div class="row g-3 mb-4">
            <?php
            $gesamtsaldo = 0;
            foreach($kontostaende as $konto => $daten):
                $gesamtsaldo += $daten['saldo'];
                ?>
                <div class="col-6 col-lg-3">
                    <div class="card kontostand-card h-100">
                        <div class="card-header">
                            <?= konto_label($konto) ?>
                        </div>
                        <div class="card-body text-center">
                            <h3 class="<?= $daten['saldo'] >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                                <?= number_format($daten['saldo'], 2, ',', '.') ?> €
                            </h3>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Gesamtsaldo mit Barkasse-Umschalter -->
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card kontostand-total h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span id="saldo-titel">Gesamtsaldo</span>
                        <button type="button" class="btn-icon" id="toggle-barkasse"
                                title="Mit/Ohne Barkasse" aria-label="Gesamtsaldo mit oder ohne Barkasse anzeigen">
                            <i class="bi bi-arrow-left-right" aria-hidden="true"></i>
                        </button>
                    </div>
                    <div class="card-body text-center">
                        <h2 id="gesamtsaldo-betrag" class="<?= $gesamtsaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                            <?= number_format($gesamtsaldo, 2, ',', '.') ?> €
                        </h2>
                        <small id="saldo-hinweis" class="text-muted" style="display: none;">
                            ohne Barkasse
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Primäraktionen -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= base_url('/belege/create') ?>" class="btn btn-vdst">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Beleg erfassen
            </a>
            <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-outline-vdst">
                <i class="bi bi-journal-plus" aria-hidden="true"></i> Neue Buchung
            </a>
            <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst">
                <i class="bi bi-journal-text" aria-hidden="true"></i> Kassenbuch
            </a>
            <a href="<?= base_url('/abrechnungen/ah') ?>" class="btn btn-outline-vdst">
                <i class="bi bi-clipboard-data" aria-hidden="true"></i> Abrechnungen
            </a>
        </div>

        <!-- 3. Letzte Bewegungen -->
        <div class="row g-3 mb-4">
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Letzte Buchungen</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($neueste_buchungen)): ?>
                            <div class="p-4 text-center text-muted">
                                Noch keine Buchungen vorhanden.
                            </div>
                        <?php else: ?>
                            <table class="table table-sm mini-tabelle mb-0">
                                <?php foreach($neueste_buchungen as $buchung): ?>
                                    <tr>
                                        <td class="spalte-datum">
                                            <small><?= date('d.m.', strtotime($buchung['buchungsdatum'])) ?></small>
                                        </td>
                                        <td>
                                            <?= esc(substr($buchung['beschreibung'], 0, 40)) ?><?= strlen($buchung['beschreibung']) > 40 ? '...' : '' ?>
                                        </td>
                                        <td class="text-end spalte-betrag">
                                            <span class="<?= $buchung['buchungsart'] === 'einnahme' ? 'text-success' : 'text-danger' ?>">
                                                <?= $buchung['buchungsart'] === 'einnahme' ? '+' : '-' ?><?= number_format($buchung['betrag'], 2, ',', '.') ?> €
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

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header">
                        <strong>Neueste Belege</strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($neueste_belege)): ?>
                            <div class="p-4 text-center text-muted">
                                Noch keine Belege vorhanden.
                            </div>
                        <?php else: ?>
                            <table class="table table-sm mini-tabelle mb-0">
                                <?php foreach($neueste_belege as $beleg): ?>
                                    <tr>
                                        <td class="spalte-beleg">
                                            <small><?= esc($beleg['belegnummer']) ?></small>
                                        </td>
                                        <td>
                                            <?= esc(substr($beleg['beschreibung'], 0, 30)) ?><?= strlen($beleg['beschreibung']) > 30 ? '...' : '' ?>
                                        </td>
                                        <td class="text-end spalte-betrag">
                                            <?= number_format($beleg['betrag'], 2, ',', '.') ?> €
                                        </td>
                                        <td class="spalte-status">
                                            <span class="badge-status <?= $beleg['status'] === 'erfasst' ? 'badge-status-neutral' : 'badge-status-rot' ?>">
                                                <?= beleg_status_label($beleg['status']) ?>
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

        <!-- 4. Zahlen kompakt -->
        <div class="row g-3">
            <div class="col-6 col-lg">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $beleg_stats['gesamt_belege'] ?></div>
                        <div class="stat-tile-label">Belege · <?= $beleg_stats['neue_belege'] ?> neu, <?= $beleg_stats['in_abrechnung'] ?> in Abrechnung</div>
                        <a href="<?= base_url('/belege') ?>" class="stretched-link" aria-label="Belege öffnen"></a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $buchung_stats['gesamt_buchungen'] ?? '–' ?></div>
                        <div class="stat-tile-label">Buchungen · <?= $buchung_stats['buchungen_monat'] ?> diesen Monat</div>
                        <a href="<?= base_url('/buchungen') ?>" class="stretched-link" aria-label="Kassenbuch öffnen"></a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $ah_stats['ausstehend'] + $hv_stats['ausstehend'] ?></div>
                        <div class="stat-tile-label">Abrechnungen ausstehend · <?= $ah_stats['entwuerfe'] + $hv_stats['entwuerfe'] ?> Entwürfe</div>
                        <a href="<?= base_url('/abrechnungen/ah') ?>" class="stretched-link" aria-label="Abrechnungen öffnen"></a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value <?= $schulden_offen > 0 ? 'text-danger' : '' ?>"><?= formatiere_betrag($schulden_offen) ?></div>
                        <div class="stat-tile-label">Offene Forderungen</div>
                        <a href="<?= base_url('/schulden') ?>" class="stretched-link" aria-label="Schuldenliste öffnen"></a>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value <?= $inventur_gesamt >= 0 ? '' : 'text-danger' ?>"><?= formatiere_betrag($inventur_gesamt) ?></div>
                        <div class="stat-tile-label">Inventur-Bestand</div>
                        <a href="<?= base_url('/inventur') ?>" class="stretched-link" aria-label="Inventur öffnen"></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Gesamtsaldo mit/ohne Barkasse Toggle
            const toggleButton = document.getElementById('toggle-barkasse');
            const saldoTitel = document.getElementById('saldo-titel');
            const saldoBetrag = document.getElementById('gesamtsaldo-betrag');
            const saldoHinweis = document.getElementById('saldo-hinweis');

            // Kontostände aus PHP übernehmen
            const kontostaende = <?= json_encode($kontostaende) ?>;
            let mitBarkasse = true;

            // Gesamtsaldo berechnen
            function berechneGesamtsaldo(inklBarkasse = true) {
                let summe = 0;
                for (const [konto, daten] of Object.entries(kontostaende)) {
                    if (!inklBarkasse && konto === 'barkasse') {
                        continue; // Nur die Barkasse ausschließen
                    }
                    summe += parseFloat(daten.saldo) || 0;
                }
                return summe;
            }

            // Anzeige aktualisieren
            function updateAnzeige() {
                const saldo = berechneGesamtsaldo(mitBarkasse);
                const formatierterBetrag = new Intl.NumberFormat('de-DE', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                }).format(saldo) + ' €';

                saldoBetrag.textContent = formatierterBetrag;
                saldoBetrag.className = saldo >= 0 ? 'saldo-positiv' : 'saldo-negativ';

                if (mitBarkasse) {
                    saldoTitel.textContent = 'Gesamtsaldo';
                    saldoHinweis.style.display = 'none';
                } else {
                    saldoTitel.textContent = 'Saldo ohne Bar';
                    saldoHinweis.style.display = 'block';
                }
            }

            // Toggle Event
            toggleButton.addEventListener('click', function() {
                mitBarkasse = !mitBarkasse;
                updateAnzeige();

                // Speichere Einstellung im localStorage
                localStorage.setItem('dashboard_mit_barkasse', mitBarkasse);
            });

            // Gespeicherte Einstellung laden
            const gespeicherteEinstellung = localStorage.getItem('dashboard_mit_barkasse');
            if (gespeicherteEinstellung !== null) {
                mitBarkasse = gespeicherteEinstellung === 'true';
                updateAnzeige();
            }
        });
    </script>
<?= $this->endSection() ?>
