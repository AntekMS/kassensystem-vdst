<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <h1 class="page-title mb-0">
                <?= $typ === 'ah' ? 'AH² Abrechnungen' : 'Heimverein Abrechnungen' ?>
            </h1>
            <div class="d-flex flex-wrap gap-2">
                <!-- Typ-Wechsel -->
                <div class="btn-group">
                    <a href="<?= base_url('/abrechnungen/ah') ?>"
                       class="btn btn-outline-vdst <?= $typ === 'ah' ? 'active' : '' ?>"
                       <?= $typ === 'ah' ? 'aria-current="page"' : '' ?>>
                        AH²
                    </a>
                    <a href="<?= base_url('/abrechnungen/hv') ?>"
                       class="btn btn-outline-vdst <?= $typ === 'hv' ? 'active' : '' ?>"
                       <?= $typ === 'hv' ? 'aria-current="page"' : '' ?>>
                        HV
                    </a>
                </div>

                <!-- Neue Abrechnung -->
                <a href="<?= base_url('/abrechnungen/' . $typ . '/create') ?>" class="btn btn-vdst">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Neue <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung
                </a>
            </div>
        </div>

        <!-- Statistiken -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['entwuerfe'] ?></div>
                        <div class="stat-tile-label">Entwürfe</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['ausstehend'] ?></div>
                        <div class="stat-tile-label">Ausstehend</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= $stats['eingereicht'] ?></div>
                        <div class="stat-tile-label">Eingereicht</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-tile h-100">
                    <div class="card-body">
                        <div class="stat-tile-value"><?= count($abrechnungen) ?></div>
                        <div class="stat-tile-label">Gesamt</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Abrechnungen-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen (<?= count($abrechnungen) ?> Einträge)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($abrechnungen)): ?>
                    <div class="empty-state">
                        <i class="bi bi-clipboard-data" aria-hidden="true"></i>
                        <p>Noch keine <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen erstellt.</p>
                        <a href="<?= base_url('/abrechnungen/' . $typ . '/create') ?>" class="btn btn-vdst">
                            Erste Abrechnung erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Monat</th>
                                <th>Titel</th>
                                <th class="d-none d-lg-table-cell">Erstellt</th>
                                <th>Status</th>
                                <th class="text-center d-none d-lg-table-cell">Belege</th>
                                <th class="text-end">Gesamtsumme</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($abrechnungen as $abrechnung): ?>
                                <tr>
                                    <td>
                                        <strong><?= $abrechnung['abrechnungsmonat'] ?></strong>
                                    </td>
                                    <td>
                                        <strong><?= esc($abrechnung['titel']) ?></strong>
                                    </td>
                                    <td class="d-none d-lg-table-cell">
                                        <?= date('d.m.Y', strtotime($abrechnung['erstellt_am'])) ?>
                                    </td>
                                    <td>
                                        <span class="<?= abrechnung_status_badge_class($abrechnung['status']) ?>">
                                            <?= abrechnung_status_label($abrechnung['status']) ?>
                                        </span>
                                    </td>
                                    <td class="text-center d-none d-lg-table-cell">
                                        <span class="badge-status badge-status-neutral">
                                            <?= $abrechnung['anzahl_belege'] ?> Belege
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €</strong>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex flex-wrap justify-content-center gap-1">
                                            <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                                               class="btn btn-outline-vdst btn-sm" title="Belege verwalten">
                                                <i class="bi bi-receipt" aria-hidden="true"></i> Belege
                                            </a>
                                            <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>"
                                               class="btn btn-outline-vdst btn-sm" title="Vorschau">
                                                <i class="bi bi-eye" aria-hidden="true"></i> Vorschau
                                            </a>

                                            <?php if ($abrechnung['anzahl_belege'] > 0): ?>
                                                <!-- Export-Dropdown -->
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-outline-vdst btn-sm dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false" title="Export-Optionen">
                                                        <i class="bi bi-download" aria-hidden="true"></i> Export
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item"
                                                               href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>">
                                                                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel-Datei
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item"
                                                               href="<?= base_url('/abrechnungen/' . $typ . '/exportPdf/' . $abrechnung['id']) ?>">
                                                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF-Rechnung
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item"
                                                               href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>">
                                                                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i> ZIP-Archiv (alle Belege)
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array($abrechnung['status'], ['entwurf', 'ausstehend'])): ?>
                                                <form method="post" class="d-inline"
                                                      action="<?= base_url('/abrechnungen/' . $typ . '/delete/' . $abrechnung['id']) ?>"
                                                      onsubmit="return confirmDelete('Abrechnung <?= esc($abrechnung['titel'], 'js') ?> wirklich löschen? Die Beleg-Zuordnungen werden entfernt, die Belege bleiben erhalten.')">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="Abrechnung löschen" aria-label="Abrechnung löschen">
                                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <!-- Colspans je Breakpoint: unter lg sind Erstellt/Belege ausgeblendet -->
                                <th colspan="5" class="text-end d-none d-lg-table-cell">Gesamtsumme aller Abrechnungen:</th>
                                <th colspan="3" class="text-end d-lg-none">Gesamtsumme:</th>
                                <th class="text-end">
                                    <?php
                                    $gesamtsumme = array_sum(array_column($abrechnungen, 'gesamtsumme'));
                                    echo '<strong>' . number_format($gesamtsumme, 2, ',', '.') . ' €</strong>';
                                    ?>
                                </th>
                                <th></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
<?= $this->endSection() ?>
