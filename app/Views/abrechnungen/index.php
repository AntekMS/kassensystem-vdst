<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <h1 class="page-title">
                <?= $typ === 'ah' ? 'AH² Abrechnungen' : 'Heimverein Abrechnungen' ?>
            </h1>
            <div>
                <!-- Typ-Wechsel -->
                <div class="btn-group me-3">
                    <a href="<?= base_url('/abrechnungen/ah') ?>"
                       class="btn <?= $typ === 'ah' ? 'btn-vdst' : 'btn-outline-vdst' ?>">
                        AH² Abrechnungen
                    </a>
                    <a href="<?= base_url('/abrechnungen/hv') ?>"
                       class="btn <?= $typ === 'hv' ? 'btn-vdst' : 'btn-outline-vdst' ?>">
                        HV Abrechnungen
                    </a>
                </div>

                <!-- Neue Abrechnung -->
                <a href="<?= base_url('/abrechnungen/' . $typ . '/create') ?>" class="btn btn-vdst">
                    <strong>+ Neue <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung</strong>
                </a>
            </div>
        </div>

        <!-- Statistiken -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-secondary"><?= $stats['entwuerfe'] ?></h3>
                        <small class="text-muted">Entwürfe</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= $stats['ausstehend'] ?></h3>
                        <small class="text-muted">Ausstehend</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= $stats['eingereicht'] ?></h3>
                        <small class="text-muted">Eingereicht</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= count($abrechnungen) ?></h3>
                        <small class="text-muted">Gesamt</small>
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
                    <div class="text-center p-4">
                        <p class="text-muted">Noch keine <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen erstellt.</p>
                        <a href="<?= base_url('/abrechnungen/' . $typ . '/create') ?>" class="btn btn-vdst">
                            Erste Abrechnung erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Monat</th>
                                <th>Titel</th>
                                <th>Erstellt</th>
                                <th>Status</th>
                                <th class="text-center">Belege</th>
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
                                    <td>
                                        <?= date('d.m.Y', strtotime($abrechnung['erstellt_am'])) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'entwurf' => 'secondary',
                                            'ausstehend' => 'warning',
                                            'eingereicht' => 'info',
                                            'bezahlt' => 'success'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $statusColors[$abrechnung['status']] ?> badge-sm">
                                        <?= abrechnung_status_label($abrechnung['status']) ?>
                                    </span>
                                    </td>
                                    <td class="text-center">
                                    <span class="badge bg-dark">
                                        <?= $abrechnung['anzahl_belege'] ?> Belege
                                    </span>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €</strong>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                                               class="btn btn-outline-dark" title="Belege verwalten">
                                                <i class="bi bi-receipt" aria-hidden="true"></i> Belege
                                            </a>
                                            <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>"
                                               class="btn btn-outline-info" title="Vorschau">
                                                <i class="bi bi-eye" aria-hidden="true"></i> Vorschau
                                            </a>

                                            <?php if ($abrechnung['anzahl_belege'] > 0): ?>
                                                <!-- Export-Dropdown -->
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-success dropdown-toggle"
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
                                                    <button type="submit" class="btn btn-outline-danger btn-sm" title="Abrechnung löschen">
                                                        <i class="bi bi-trash" aria-hidden="true"></i> Löschen
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
                                <th colspan="5" class="text-end">Gesamtsumme aller Abrechnungen:</th>
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