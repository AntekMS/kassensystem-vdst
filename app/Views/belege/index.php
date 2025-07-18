<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Belege-Verwaltung<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Belege-Verwaltung</h1>
            <a href="<?= base_url('/belege/create') ?>" class="btn btn-vdst">
                <strong>+ Neuen Beleg hinzufügen</strong>
            </a>
        </div>

        <!-- Statistiken -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?= $stats['gesamt_belege'] ?></h3>
                        <small class="text-muted">Belege gesamt</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $stats['neue_belege'] ?></h3>
                        <small class="text-muted">Neue Belege</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= $stats['ah_berechtigt'] ?></h3>
                        <small class="text-muted">AH² berechtigt</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= $stats['hv_berechtigt'] ?></h3>
                        <small class="text-muted">HV berechtigt</small>
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
                        <label class="form-label">Kategorie</label>
                        <select name="kategorie" class="form-control">
                            <option value="">Alle</option>
                            <?php foreach($kategorien as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filter['kategorie'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Alle</option>
                            <?php foreach($status_optionen as $value => $label): ?>
                                <option value="<?= $value ?>" <?= ($filter['status'] ?? '') === $value ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Suche</label>
                        <input type="text" name="suche" value="<?= esc($filter['suche'] ?? '') ?>"
                               placeholder="Beschreibung, Bezugsquelle, Belegnummer, Notizen..." class="form-control">
                    </div>
                    <div class="col-md-1">
                        <label class="form-label">&nbsp;</label>
                        <button type="submit" class="btn btn-vdst w-100">Filter</button>
                    </div>
                </div>
                <?php if (!empty(array_filter($filter))): ?>
                    <div class="row mt-2">
                        <div class="col-12 text-end">
                            <a href="<?= base_url('/belege') ?>" class="btn btn-outline-secondary btn-sm">
                                Filter zurücksetzen
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </form>

        <!-- Belege-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst d-flex justify-content-between align-items-center">
                <strong>Belege (<?= count($belege) ?> Einträge)</strong>
                <?php if (!empty($belege)): ?>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-light btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            📊 Export
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= base_url('/belege/export/excel?' . http_build_query($filter)) ?>">
                                    📋 Nur Excel-Liste
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= base_url('/belege/export/zip?' . http_build_query($filter)) ?>">
                                    📦 Excel + alle Beleg-Dateien (ZIP)
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($belege)): ?>
                    <div class="text-center p-4">
                        <p class="text-muted">Keine Belege gefunden.</p>
                        <a href="<?= base_url('/belege/create') ?>" class="btn btn-vdst">
                            Ersten Beleg hinzufügen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Belegnummer</th>
                                <th>Datum</th>
                                <th>Beschreibung</th>
                                <th>Bezugsquelle</th>
                                <th class="text-end">Betrag</th>
                                <th>Kategorie</th>
                                <th>Status</th>
                                <th>Notizen</th> <!-- NEU: Notizen-Spalte -->
                                <th>Abrechnungen</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($belege as $beleg): ?>
                                <tr>
                                    <td>
                                        <strong>
                                            <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                               class="text-decoration-none">
                                                <?= $beleg['belegnummer'] ?>
                                            </a>
                                        </strong>
                                        <br>
                                        <small class="text-muted"><?= strtoupper($beleg['dateityp']) ?></small>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?>
                                        <?php if ($beleg['rechnungsdatum'] !== $beleg['eingabedatum']): ?>
                                            <br><small class="text-muted">
                                                Eingabe: <?= date('d.m.', strtotime($beleg['eingabedatum'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= esc(substr($beleg['beschreibung'], 0, 40)) ?></strong>
                                        <?= strlen($beleg['beschreibung']) > 40 ? '...' : '' ?>
                                    </td>
                                    <td>
                                        <?= $beleg['lieferant'] ? esc($beleg['lieferant']) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($beleg['betrag'], 2, ',', '.') ?> €</strong>
                                    </td>
                                    <td>
                                    <span class="badge bg-<?= $beleg['kategorie'] === 'normal' ? 'secondary' : 'primary' ?> badge-sm">
                                        <?php
                                        $kat_labels = [
                                            'normal' => 'Normal',
                                            'ah_berechtigt' => 'AH²',
                                            'hv_berechtigt' => 'HV'
                                        ];
                                        echo $kat_labels[$beleg['kategorie']] ?? $beleg['kategorie'];
                                        ?>
                                    </span>
                                    </td>
                                    <td>
                                    <span class="badge bg-<?= $beleg['status'] === 'erfasst' ? 'secondary' : 'info' ?> badge-sm">
                                        <?php
                                        $status_labels = [
                                            'erfasst' => 'Erfasst',
                                            'in_abrechnung' => 'In Abrechnung',
                                            'abgerechnet' => 'Abgerechnet',
                                            'bezahlt' => 'Bezahlt'
                                        ];
                                        echo $status_labels[$beleg['status']] ?? $beleg['status'];
                                        ?>
                                    </span>
                                    </td>
                                    <td style="max-width: 150px;"> <!-- NEU: Notizen-Spalte mit Größenbegrenzung -->
                                        <?php if (!empty($beleg['notizen'])): ?>
                                            <span class="text-muted"
                                                  title="<?= esc($beleg['notizen']) ?>"
                                                  style="cursor: help;">
                                                <?= esc(substr($beleg['notizen'], 0, 30)) ?>
                                                <?= strlen($beleg['notizen']) > 30 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($beleg['abrechnungen'])): ?>
                                            <?php foreach($beleg['abrechnungen'] as $abrechnung): ?>
                                                <span class="badge bg-<?= $abrechnung['typ'] === 'ah' ? 'info' : 'warning' ?> badge-sm">
                                                <?= strtoupper($abrechnung['typ']) ?>
                                            </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                               class="btn btn-outline-dark" title="Anzeigen">
                                                👁️
                                            </a>
                                            <?php if ($beleg['status'] === 'erfasst'): ?>
                                                <a href="<?= base_url('/belege/edit/' . $beleg['id']) ?>"
                                                   class="btn btn-outline-dark" title="Bearbeiten">
                                                    ✏️
                                                </a>
                                            <?php endif; ?>
                                            <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                                               class="btn btn-outline-success" title="Download">
                                                📥
                                            </a>
                                            <?php if (empty($beleg['abrechnungen']) && $beleg['status'] === 'erfasst'): ?>
                                                <a href="<?= base_url('/belege/delete/' . $beleg['id']) ?>"
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirmDelete('Beleg <?= $beleg['belegnummer'] ?> wirklich löschen? Die Datei wird ebenfalls gelöscht!')"
                                                   title="Löschen">
                                                    🗑️
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Summe (gefiltert):</th>
                                <th class="text-end">
                                    <?php
                                    $gesamtbetrag = array_sum(array_column($belege, 'betrag'));
                                    echo '<strong>' . number_format($gesamtbetrag, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                                <th colspan="5"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Filter-Selects automatisch absenden
            const filterSelects = document.querySelectorAll('select[name="kategorie"], select[name="status"]');
            filterSelects.forEach(select => {
                select.addEventListener('change', function() {
                    this.form.submit();
                });
            });

            // Beleg-Links in gleichen Tab öffnen (nicht wie bei Buchungen in neuem Tab)
            console.log('Belege-Übersicht geladen');
        });
    </script>
<?= $this->endSection() ?>