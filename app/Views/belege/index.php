<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Belege-Verwaltung<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <h1 class="page-title mb-0">Belege</h1>
            <a href="<?= base_url('/belege/create') ?>" class="btn btn-vdst">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Beleg erfassen
            </a>
        </div>

        <!-- Statistiken -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['gesamt_belege'] ?></div>
                        <div class="stat-tile-label">Belege gesamt</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['neue_belege'] ?></div>
                        <div class="stat-tile-label">Neue Belege</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['ah_berechtigt'] ?></div>
                        <div class="stat-tile-label">AH² berechtigt</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['hv_berechtigt'] ?></div>
                        <div class="stat-tile-label">HV berechtigt</div>
                    </div>
                </div>
            </div>
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
                    <label for="filter_kategorie" class="form-label">Kategorie</label>
                    <select id="filter_kategorie" name="kategorie" class="form-select js-autosubmit">
                        <option value="">Alle</option>
                        <?php foreach($kategorien as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($filter['kategorie'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="filter_status" class="form-label">Status</label>
                    <select id="filter_status" name="status" class="form-select js-autosubmit">
                        <option value="">Alle</option>
                        <?php foreach($status_optionen as $value => $label): ?>
                            <option value="<?= $value ?>" <?= ($filter['status'] ?? '') === $value ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label for="filter_suche" class="form-label">Suche</label>
                    <input type="text" id="filter_suche" name="suche" value="<?= esc($filter['suche'] ?? '') ?>"
                           placeholder="Beschreibung, Bezugsquelle, Belegnummer, Notizen..." class="form-control">
                </div>
                <div class="col-12 col-md-1">
                    <button type="submit" class="btn btn-outline-vdst w-100">Filter</button>
                </div>
            </div>
            <?php if (!empty(array_filter($filter))): ?>
                <div class="text-end mt-2">
                    <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst btn-sm">
                        Filter zurücksetzen
                    </a>
                </div>
            <?php endif; ?>
        </form>

        <!-- Belege-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst d-flex justify-content-between align-items-center">
                <strong>Belege (<?= count($belege) ?> Einträge)</strong>
                <?php if (!empty($belege)): ?>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-vdst btn-sm dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download" aria-hidden="true"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="<?= base_url('/belege/export/excel?' . http_build_query($filter)) ?>">
                                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Nur Excel-Liste
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="<?= base_url('/belege/export/zip?' . http_build_query($filter)) ?>">
                                    <i class="bi bi-file-earmark-zip" aria-hidden="true"></i> Excel + alle Beleg-Dateien (ZIP)
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($belege)): ?>
                    <div class="empty-state">
                        <i class="bi bi-receipt" aria-hidden="true"></i>
                        <p>Keine Belege gefunden.</p>
                        <a href="<?= base_url('/belege/create') ?>" class="btn btn-vdst">
                            Ersten Beleg hinzufügen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-stack mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Belegnummer</th>
                                <th>Datum</th>
                                <th>Beschreibung</th>
                                <th class="d-none d-xl-table-cell">Bezugsquelle</th>
                                <th class="text-end">Betrag</th>
                                <th>Kategorie</th>
                                <th>Status</th>
                                <th class="d-none d-xl-table-cell">Notizen</th>
                                <th class="d-none d-xl-table-cell">Abrechnungen</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($belege as $beleg): ?>
                                <tr>
                                    <td data-label="Beleg">
                                        <strong>
                                            <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                               class="text-decoration-none">
                                                <?= esc($beleg['belegnummer']) ?>
                                            </a>
                                        </strong>
                                        <small class="text-muted d-none d-lg-inline"><br><?= strtoupper($beleg['dateityp']) ?></small>
                                    </td>
                                    <td data-label="Datum">
                                        <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?>
                                        <?php if ($beleg['rechnungsdatum'] !== $beleg['eingabedatum']): ?>
                                            <small class="text-muted d-none d-lg-inline">
                                                <br>Eingabe: <?= date('d.m.', strtotime($beleg['eingabedatum'])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Beschreibung">
                                        <strong><?= esc(substr($beleg['beschreibung'], 0, 40)) ?></strong>
                                        <?= strlen($beleg['beschreibung']) > 40 ? '...' : '' ?>
                                    </td>
                                    <td data-label="Bezugsquelle" class="d-none d-xl-table-cell">
                                        <?= $beleg['lieferant'] ? esc($beleg['lieferant']) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td data-label="Betrag" class="text-end">
                                        <strong><?= number_format($beleg['betrag'], 2, ',', '.') ?> €</strong>
                                    </td>
                                    <td data-label="Kategorie">
                                        <span class="<?= kategorie_badge_class($beleg['kategorie']) ?>">
                                            <?= kategorie_label($beleg['kategorie']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Status">
                                        <span class="<?= beleg_status_badge_class($beleg['status']) ?>">
                                            <?= beleg_status_label($beleg['status']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Notizen" class="d-none d-xl-table-cell spalte-notizen">
                                        <?php if (!empty($beleg['notizen'])): ?>
                                            <span class="text-muted" title="<?= esc($beleg['notizen']) ?>">
                                                <?= esc(substr($beleg['notizen'], 0, 30)) ?>
                                                <?= strlen($beleg['notizen']) > 30 ? '...' : '' ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Abrechnungen" class="d-none d-xl-table-cell">
                                        <?php if (!empty($beleg['abrechnungen'])): ?>
                                            <?php foreach($beleg['abrechnungen'] as $abrechnung): ?>
                                                <span class="badge-status badge-status-outline">
                                                    <?= strtoupper($abrechnung['typ']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center stack-actions">
                                        <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                           class="btn-icon" title="Anzeigen" aria-label="Beleg anzeigen">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                            <span class="d-lg-none">Anzeigen</span>
                                        </a>
                                        <?php if ($beleg['status'] === 'erfasst'): ?>
                                            <a href="<?= base_url('/belege/edit/' . $beleg['id']) ?>"
                                               class="btn-icon" title="Bearbeiten" aria-label="Beleg bearbeiten">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="d-lg-none">Bearbeiten</span>
                                            </a>
                                        <?php endif; ?>
                                        <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                                           class="btn-icon" title="Download" aria-label="Beleg herunterladen">
                                            <i class="bi bi-download" aria-hidden="true"></i>
                                            <span class="d-lg-none">Datei</span>
                                        </a>
                                        <?php if (empty($beleg['abrechnungen']) && $beleg['status'] === 'erfasst'): ?>
                                            <form method="post" class="d-inline stack-form"
                                                  action="<?= base_url('/belege/delete/' . $beleg['id']) ?>"
                                                  onsubmit="return confirmDelete('Beleg <?= esc($beleg['belegnummer'], 'js') ?> wirklich löschen? Die Datei wird ebenfalls gelöscht!')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-icon btn-icon-danger" title="Löschen" aria-label="Beleg löschen">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                    <span class="d-lg-none">Löschen</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <!-- Colspans je Breakpoint: unter xl sind Bezugsquelle/Notizen/Abrechnungen ausgeblendet -->
                                <th colspan="4" class="text-end d-none d-xl-table-cell">Summe (gefiltert):</th>
                                <th colspan="3" class="text-end d-none d-lg-table-cell d-xl-none">Summe (gefiltert):</th>
                                <th class="text-end">
                                    <?php
                                    $gesamtbetrag = array_sum(array_column($belege, 'betrag'));
                                    echo '<strong>' . number_format($gesamtbetrag, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                                <th colspan="5" class="d-none d-xl-table-cell"></th>
                                <th colspan="3" class="d-none d-lg-table-cell d-xl-none"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="summe-mobile d-lg-none m-3">
                        <span>Summe (gefiltert)</span>
                        <span><?= number_format(array_sum(array_column($belege, 'betrag')), 2, ',', '.') ?> €</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <!-- Filter-Selects werden über die Klasse js-autosubmit in public/js/app.js gebunden -->
<?= $this->endSection() ?>
