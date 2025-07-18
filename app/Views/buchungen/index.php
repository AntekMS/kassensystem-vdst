<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Kassenbuch<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">Kassenbuch</h1>

        <!-- Kontostand-Übersicht -->
        <div class="row mb-4">
            <?php
            $gesamtsaldo = 0;
            $banksaldo = 0; // Ohne Barkasse
            foreach($kontostaende as $konto => $daten):
                $gesamtsaldo += $daten['saldo'];
                if ($konto !== 'barkasse') {
                    $banksaldo += $daten['saldo'];
                }
                ?>
                <div class="col-md-3">
                    <div class="card kontostand-card">
                        <div class="card-header">
                            <?php
                            $kontoNamen = [
                                'aktivenkasse' => 'Aktivenkasse',
                                'getraenkekasse' => 'Getränkekasse',
                                'barkasse' => 'Barkasse'
                            ];
                            echo $kontoNamen[$konto] ?? ucfirst($konto);
                            ?>
                        </div>
                        <div class="card-body text-center">
                            <div class="row">
                                <div class="col-12">
                                    <small class="text-muted">Einnahmen</small><br>
                                    <strong class="text-success">+<?= number_format($daten['einnahmen'], 2, ',', '.') ?> €</strong>
                                </div>
                            </div>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <small class="text-muted">Ausgaben</small><br>
                                    <strong class="text-danger">-<?= number_format($daten['ausgaben'], 2, ',', '.') ?> €</strong>
                                </div>
                            </div>
                            <hr>
                            <div class="row">
                                <div class="col-12">
                                    <h4 class="<?= $daten['saldo'] >= 0 ? 'saldo-positiv' : 'saldo-negativ' ?>">
                                        <?= number_format($daten['saldo'], 2, ',', '.') ?> €
                                    </h4>
                                    <small class="text-muted">Saldo</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Gesamtsaldo mit Toggle -->
            <div class="col-md-3">
                <div class="card border-3" style="border-color: var(--vdst-rot) !important;">
                    <div class="card-header text-center" style="background-color: var(--vdst-rot); color: white; padding: 0.5rem;">
                        <div class="d-flex justify-content-between align-items-center">
                            <strong id="saldo-title">BANK-SALDO</strong>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="barkasse-toggle"
                                       style="background-color: rgba(255,255,255,0.3); border-color: white;">
                                <label class="form-check-label text-white" for="barkasse-toggle" style="font-size: 0.75em;">
                                    +Bar
                                </label>
                            </div>
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

        <!-- Aktionen und Filter -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center">
                    <!-- Neue Buchung Button -->
                    <div>
                        <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-vdst btn-lg">
                            <strong>+ Neue Buchung</strong>
                        </a>

                        <!-- Export-Dropdown -->
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-vdst dropdown-toggle" data-bs-toggle="dropdown">
                                📊 Export
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item" href="<?= base_url('/buchungen/exportExcel?' . http_build_query($filter)) ?>">
                                        📋 Kassenbuch (Original-Format)
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= base_url('/buchungen/export/excel?' . http_build_query($filter)) ?>">
                                        📝 Nur Buchungen-Liste
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="<?= base_url('/buchungen/export/zip?' . http_build_query($filter)) ?>">
                                        📦 Kassenbuch + Buchungen + Belege (ZIP)
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="text-end">
                        <small class="text-muted">
                            Buchungen heute: <strong><?= $stats['buchungen_heute'] ?></strong> |
                            Diesen Monat: <strong><?= $stats['buchungen_monat'] ?></strong> |
                            Ohne Beleg: <strong><?= $stats['ohne_beleg'] ?></strong>
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <form method="get" class="card card-vdst mb-4">
            <div class="card-header">
                <strong>Filter & Suche</strong>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">
                        <label class="form-label">Von Datum</label>
                        <input type="date" name="datum_von" value="<?= $filter['datum_von'] ?? '' ?>"
                               class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Bis Datum</label>
                        <input type="date" name="datum_bis" value="<?= $filter['datum_bis'] ?? '' ?>"
                               class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Konto</label>
                        <select name="konto_typ" class="form-control">
                            <option value="">Alle Konten</option>
                            <option value="aktivenkasse" <?= ($filter['konto_typ'] ?? '') === 'aktivenkasse' ? 'selected' : '' ?>>
                                Aktivenkasse
                            </option>
                            <option value="getraenkekasse" <?= ($filter['konto_typ'] ?? '') === 'getraenkekasse' ? 'selected' : '' ?>>
                                Getränkekasse
                            </option>
                            <option value="barkasse" <?= ($filter['konto_typ'] ?? '') === 'barkasse' ? 'selected' : '' ?>>
                                Barkasse
                            </option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Art</label>
                        <select name="buchungsart" class="form-control">
                            <option value="">Alle</option>
                            <option value="einnahme" <?= ($filter['buchungsart'] ?? '') === 'einnahme' ? 'selected' : '' ?>>
                                Einnahme
                            </option>
                            <option value="ausgabe" <?= ($filter['buchungsart'] ?? '') === 'ausgabe' ? 'selected' : '' ?>>
                                Ausgabe
                            </option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Suche</label>
                        <input type="text" name="suche" value="<?= esc($filter['suche'] ?? '') ?>"
                               placeholder="Beschreibung, Lieferant, Belegnummer, Notizen..." class="form-control">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-vdst w-100">Filter</button>
                    </div>
                </div>
                <?php if (!empty(array_filter($filter))): ?>
                    <div class="row mt-2">
                        <div class="col-12 text-end">
                            <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-secondary btn-sm">
                                Filter zurücksetzen
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Kassenbuch-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Kassenbuch-Einträge (<?= count($buchungen) ?> Einträge)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($buchungen)): ?>
                    <div class="text-center p-4">
                        <p class="text-muted">Keine Buchungen gefunden.</p>
                        <a href="<?= base_url('/buchungen/create') ?>" class="btn btn-vdst">
                            Erste Buchung erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
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
                                    <td>
                                        <strong><?= date('d.m.Y', strtotime($buchung['buchungsdatum'])) ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= esc($buchung['beschreibung']) ?></strong>
                                        <?php if (!empty($buchung['notizen'])): ?>
                                            <br><small class="text-muted"><?= esc($buchung['notizen']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($buchung['belegnummer'])): ?>
                                            <a href="<?= base_url('/belege/show/' . $buchung['beleg_id']) ?>"
                                               target="_blank" class="btn btn-outline-dark btn-sm">
                                                📄 <?= $buchung['belegnummer'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Kein Beleg</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                    <span class="badge bg-secondary">
                                        <?php
                                        $kontoNamen = [
                                            'aktivenkasse' => 'Aktivenkasse',
                                            'getraenkekasse' => 'Getränkekasse',
                                            'barkasse' => 'Barkasse'
                                        ];
                                        echo $kontoNamen[$buchung['konto_typ']] ?? ucfirst($buchung['konto_typ']);
                                        ?>
                                    </span>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($buchung['buchungsart'] === 'einnahme'): ?>
                                            <strong class="text-success">
                                                +<?= number_format($buchung['betrag'], 2, ',', '.') ?> €
                                            </strong>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ($buchung['buchungsart'] === 'ausgabe'): ?>
                                            <strong class="text-danger">
                                                -<?= number_format($buchung['betrag'], 2, ',', '.') ?> €
                                            </strong>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('/buchungen/edit/' . $buchung['id']) ?>"
                                               class="btn btn-outline-dark" title="Bearbeiten">
                                                ✏️
                                            </a>
                                            <a href="<?= base_url('/buchungen/delete/' . $buchung['id']) ?>"
                                               class="btn btn-outline-danger"
                                               onclick="return confirmDelete('Buchung wirklich löschen?')"
                                               title="Löschen">
                                                🗑️
                                            </a>
                                        </div>
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

            // Filter-Form automatisch absenden bei Änderung der Selects
            const filterSelects = document.querySelectorAll('select[name="konto_typ"], select[name="buchungsart"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            // Barkasse-Toggle Funktionalität
            const barkasseToggle = document.getElementById('barkasse-toggle');
            const saldoTitle = document.getElementById('saldo-title');
            const saldoBetrag = document.getElementById('saldo-betrag');
            const saldoUntertitel = document.getElementById('saldo-untertitel');

            if (barkasseToggle && saldoTitle && saldoBetrag && saldoUntertitel) {
                barkasseToggle.addEventListener('change', function() {
                    if (this.checked) {
                        // Gesamtsaldo anzeigen (mit Barkasse)
                        saldoTitle.textContent = 'GESAMTSALDO';
                        saldoBetrag.textContent = saldoDaten.gesamtsaldoFormatiert;
                        saldoUntertitel.textContent = 'Mit Barkasse';

                        // Saldo-Farbe anpassen
                        saldoBetrag.className = saldoDaten.gesamtsaldo >= 0 ? 'saldo-positiv' : 'saldo-negativ';
                    } else {
                        // Bank-Saldo anzeigen (ohne Barkasse)
                        saldoTitle.textContent = 'BANK-SALDO';
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