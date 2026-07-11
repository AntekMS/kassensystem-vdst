<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Kassenbuch<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">Kassenbuch</h1>

        <!-- Kontostand-Übersicht -->
        <div class="row g-3 mb-4">
            <?php
            $gesamtsaldo = 0;
            $banksaldo = 0; // Ohne Barkasse
            foreach($kontostaende as $konto => $daten):
                $gesamtsaldo += $daten['saldo'];
                if ($konto !== 'barkasse') {
                    $banksaldo += $daten['saldo'];
                }
                ?>
                <div class="col-6 col-lg-3">
                    <div class="card kontostand-card h-100">
                        <div class="card-header">
                            <?= konto_label($konto) ?>
                        </div>
                        <div class="card-body text-center">
                            <div class="konto-detail">
                                <small class="text-muted">Einnahmen</small>
                                <strong class="text-success">+<?= number_format($daten['einnahmen'], 2, ',', '.') ?> €</strong>
                            </div>
                            <div class="konto-detail">
                                <small class="text-muted">Ausgaben</small>
                                <strong class="text-danger">-<?= number_format($daten['ausgaben'], 2, ',', '.') ?> €</strong>
                            </div>
                            <hr>
                            <h4 class="<?= $daten['saldo'] >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                                <?= number_format($daten['saldo'], 2, ',', '.') ?> €
                            </h4>
                            <small class="text-muted">Saldo</small>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Gesamtsaldo mit Toggle -->
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card kontostand-total h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span id="saldo-title">Bank-Saldo</span>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="barkasse-toggle">
                            <label class="form-check-label" for="barkasse-toggle">
                                +Bar
                            </label>
                        </div>
                    </div>
                    <div class="card-body text-center">
                        <h2 id="saldo-betrag" class="<?= $banksaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                            <?= number_format($banksaldo, 2, ',', '.') ?> €
                        </h2>
                        <small class="text-muted" id="saldo-untertitel">Ohne Barkasse</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-vdst">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Neue Buchung
                </a>

                <!-- Export-Dropdown -->
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-vdst dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="bi bi-download" aria-hidden="true"></i> Export
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a class="dropdown-item" href="<?= base_url('/buchungen/exportExcel?' . http_build_query($filter)) ?>">
                                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Kassenbuch (Excel)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="<?= base_url('/buchungen/export/zip?' . http_build_query($filter)) ?>">
                                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i> Kassenbuch + Belege (ZIP)
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Quick Stats -->
            <small class="text-muted">
                Buchungen heute: <strong><?= $stats['buchungen_heute'] ?></strong> |
                Diesen Monat: <strong><?= $stats['buchungen_monat'] ?></strong> |
                Ohne Beleg: <strong><?= $stats['ohne_beleg'] ?></strong>
            </small>
        </div>

        <!-- Filter -->
        <form method="get" class="filter-bar mb-4">
            <div class="row g-2 align-items-end">
                <div class="col-6 col-md-2">
                    <label for="filter_datum_von" class="form-label">Von Datum</label>
                    <input type="date" id="filter_datum_von" name="datum_von" value="<?= esc($filter['datum_von'] ?? '', 'attr') ?>"
                           class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter_datum_bis" class="form-label">Bis Datum</label>
                    <input type="date" id="filter_datum_bis" name="datum_bis" value="<?= esc($filter['datum_bis'] ?? '', 'attr') ?>"
                           class="form-control">
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter_konto_typ" class="form-label">Konto</label>
                    <select id="filter_konto_typ" name="konto_typ" class="form-select js-autosubmit">
                        <option value="">Alle Konten</option>
                        <?php foreach(konto_optionen() as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($filter['konto_typ'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter_buchungsart" class="form-label">Art</label>
                    <select id="filter_buchungsart" name="buchungsart" class="form-select js-autosubmit">
                        <option value="">Alle</option>
                        <option value="einnahme" <?= ($filter['buchungsart'] ?? '') === 'einnahme' ? 'selected' : '' ?>>
                            Einnahme
                        </option>
                        <option value="ausgabe" <?= ($filter['buchungsart'] ?? '') === 'ausgabe' ? 'selected' : '' ?>>
                            Ausgabe
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="filter_suche" class="form-label">Suche</label>
                    <input type="text" id="filter_suche" name="suche" value="<?= esc($filter['suche'] ?? '') ?>"
                           placeholder="Beschreibung, Lieferant, Belegnummer, Notizen..." class="form-control">
                </div>
                <div class="col-12 col-md-1">
                    <button type="submit" class="btn btn-outline-vdst w-100">Filter</button>
                </div>
            </div>
            <?php if (!empty(array_filter($filter))): ?>
                <div class="text-end mt-2">
                    <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst btn-sm">
                        Filter zurücksetzen
                    </a>
                </div>
            <?php endif; ?>
        </form>

        <!-- Kassenbuch-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Kassenbuch-Einträge (<?= count($buchungen) ?> Einträge)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($buchungen)): ?>
                    <div class="empty-state">
                        <i class="bi bi-journal-text" aria-hidden="true"></i>
                        <p>Keine Buchungen gefunden.</p>
                        <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-vdst">
                            Erste Buchung erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-stack mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Datum</th>
                                <th>Beschreibung</th>
                                <th>Beleg</th>
                                <th>Konto</th>
                                <th class="text-end">Einnahme</th>
                                <th class="text-end">Ausgabe</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($buchungen as $buchung): ?>
                                <tr>
                                    <td data-label="Datum">
                                        <strong><?= date('d.m.Y', strtotime($buchung['buchungsdatum'])) ?></strong>
                                    </td>
                                    <td data-label="Beschreibung">
                                        <strong><?= esc($buchung['beschreibung']) ?></strong>
                                        <?php if (!empty($buchung['notizen'])): ?>
                                            <br><small class="text-muted"><?= esc($buchung['notizen']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Beleg" <?= empty($buchung['belegnummer']) ? 'class="stack-leer"' : '' ?>>
                                        <?php if (!empty($buchung['belegnummer'])): ?>
                                            <a href="<?= base_url('/belege/show/' . $buchung['beleg_id']) ?>"
                                               target="_blank" class="btn btn-outline-vdst btn-sm">
                                                <i class="bi bi-receipt" aria-hidden="true"></i> <?= esc($buchung['belegnummer']) ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Kein Beleg</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Konto">
                                        <span class="badge-status badge-status-neutral">
                                            <?= konto_label($buchung['konto_typ']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Einnahme" class="text-end <?= $buchung['buchungsart'] !== 'einnahme' ? 'stack-leer' : '' ?>">
                                        <?php if ($buchung['buchungsart'] === 'einnahme'): ?>
                                            <strong class="text-success">
                                                +<?= number_format($buchung['betrag'], 2, ',', '.') ?> €
                                            </strong>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Ausgabe" class="text-end <?= $buchung['buchungsart'] !== 'ausgabe' ? 'stack-leer' : '' ?>">
                                        <?php if ($buchung['buchungsart'] === 'ausgabe'): ?>
                                            <strong class="text-danger">
                                                -<?= number_format($buchung['betrag'], 2, ',', '.') ?> €
                                            </strong>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center stack-actions">
                                        <a href="<?= base_url('/buchungen/edit/' . $buchung['id']) ?>"
                                           class="btn-icon" title="Bearbeiten" aria-label="Buchung bearbeiten">
                                            <i class="bi bi-pencil" aria-hidden="true"></i>
                                            <span class="d-lg-none">Bearbeiten</span>
                                        </a>
                                        <form method="post" class="d-inline stack-form"
                                              action="<?= base_url('/buchungen/delete/' . $buchung['id']) ?>"
                                              onsubmit="return confirmDelete('Buchung wirklich löschen?')">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn-icon btn-icon-danger" title="Löschen" aria-label="Buchung löschen">
                                                <i class="bi bi-trash" aria-hidden="true"></i>
                                                <span class="d-lg-none">Löschen</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Summen (gefiltert):</th>
                                <th class="text-end">
                                    <?php
                                    $summeEinnahmen = array_sum(array_map(fn($b) => $b['buchungsart'] === 'einnahme' ? $b['betrag'] : 0, $buchungen));
                                    echo '<strong class="text-success">+' . number_format($summeEinnahmen, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                                <th class="text-end">
                                    <?php
                                    $summeAusgaben = array_sum(array_map(fn($b) => $b['buchungsart'] === 'ausgabe' ? $b['betrag'] : 0, $buchungen));
                                    echo '<strong class="text-danger">-' . number_format($summeAusgaben, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                                <th class="text-center">
                                    <?php
                                    $saldoGefiltert = $summeEinnahmen - $summeAusgaben;
                                    $saldoClass = $saldoGefiltert >= 0 ? 'saldo-positiv' : 'saldo-negativ';
                                    echo '<strong class="' . $saldoClass . '">' . number_format($saldoGefiltert, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="summe-mobile flex-column gap-1 d-lg-none m-3">
                        <div class="d-flex justify-content-between w-100">
                            <span class="fw-normal">Einnahmen (gefiltert)</span>
                            <span class="text-success">+<?= number_format($summeEinnahmen, 2, ',', '.') ?> €</span>
                        </div>
                        <div class="d-flex justify-content-between w-100">
                            <span class="fw-normal">Ausgaben (gefiltert)</span>
                            <span class="text-danger">-<?= number_format($summeAusgaben, 2, ',', '.') ?> €</span>
                        </div>
                        <div class="d-flex justify-content-between w-100">
                            <span>Saldo</span>
                            <span class="<?= $saldoClass ?>"><?= number_format($saldoGefiltert, 2, ',', '.') ?> €</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript-Daten -->
    <script>
        // Saldo-Daten für JavaScript
        const saldoDaten = {
            banksaldo: <?= $banksaldo ?>,
            gesamtsaldo: <?= $gesamtsaldo ?>,
            banksaldoFormatiert: '<?= number_format($banksaldo, 2, ',', '.') ?> €',
            gesamtsaldoFormatiert: '<?= number_format($gesamtsaldo, 2, ',', '.') ?> €'
        };
    </script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        // Kassenbuch-spezifische Funktionen
        document.addEventListener('DOMContentLoaded', function() {
            // Beleg-Links in neuem Tab öffnen
            const belegLinks = document.querySelectorAll('a[href*="/belege/show/"]');
            belegLinks.forEach(link => {
                link.setAttribute('target', '_blank');
            });

            // Filter-Selects werden über die Klasse js-autosubmit in public/js/app.js gebunden

            // Barkasse-Toggle Funktionalität
            const barkasseToggle = document.getElementById('barkasse-toggle');
            const saldoTitle = document.getElementById('saldo-title');
            const saldoBetrag = document.getElementById('saldo-betrag');
            const saldoUntertitel = document.getElementById('saldo-untertitel');

            if (barkasseToggle && saldoTitle && saldoBetrag && saldoUntertitel) {
                barkasseToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Gesamtsaldo anzeigen (mit Barkasse)
                        saldoTitle.textContent = 'Gesamtsaldo';
                        saldoBetrag.textContent = saldoDaten.gesamtsaldoFormatiert;
                        saldoUntertitel.textContent = 'Mit Barkasse';

                        // Saldo-Farbe anpassen
                        saldoBetrag.className = saldoDaten.gesamtsaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ';
                    } else {
                        // Bank-Saldo anzeigen (ohne Barkasse)
                        saldoTitle.textContent = 'Bank-Saldo';
                        saldoBetrag.textContent = saldoDaten.banksaldoFormatiert;
                        saldoUntertitel.textContent = 'Ohne Barkasse';

                        // Saldo-Farbe anpassen
                        saldoBetrag.className = saldoDaten.banksaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ';
                    }
                });

                // Tooltip für bessere UX
                barkasseToggle.setAttribute('title', 'Barkasse in Gesamtsaldo ein-/ausblenden');
            }
        });
    </script>
<?= $this->endSection() ?>
