<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
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
                                        $statusLabels = [
                                            'entwurf' => 'Entwurf',
                                            'ausstehend' => 'Ausstehend',
                                            'eingereicht' => 'Eingereicht',
                                            'bezahlt' => 'Bezahlt'
                                        ];
                                        ?>
                                        <span class="badge bg-<?= $statusColors[$abrechnung['status']] ?> badge-sm">
                                        <?= $statusLabels[$abrechnung['status']] ?>
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
                                                📄 Belege
                                            </a>
                                            <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>"
                                               class="btn btn-outline-info" title="Vorschau">
                                                👁️ Vorschau
                                            </a>
                                            <?php if ($abrechnung['anzahl_belege'] > 0): ?>
                                                <a href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>"
                                                   class="btn btn-success" title="Excel-Export">
                                                    📊 Excel
                                                </a>
                                            <?php endif; ?>
                                            <?php if ($abrechnung['status'] === 'entwurf'): ?>
                                                <a href="<?= base_url('/abrechnungen/' . $typ . '/delete/' . $abrechnung['id']) ?>"
                                                   class="btn btn-outline-danger"
                                                   onclick="return confirmDelete('Abrechnung wirklich löschen?')"
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

        <!-- Status-Change Modal für Quick-Updates -->
        <div class="modal fade" id="statusModal" tabindex="-1">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Status ändern</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="statusForm" method="post">
                        <div class="modal-body">
                            <input type="hidden" id="statusAbrechnungId" name="abrechnung_id">
                            <div class="mb-3">
                                <label class="form-label">Neuer Status:</label>
                                <select name="status" class="form-control" required>
                                    <option value="entwurf">Entwurf</option>
                                    <option value="ausstehend">Ausstehend</option>
                                    <option value="eingereicht">Eingereicht</option>
                                    <option value="bezahlt">Bezahlt</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-vdst">Status ändern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        // Status-Change Modal
        function changeStatus(abrechnungId, currentStatus) {
            document.getElementById('statusAbrechnungId').value = abrechnungId;
            document.getElementById('statusForm').action =
                '<?= base_url('/abrechnungen/' . $typ . '/changeStatus/') ?>' + abrechnungId;

            // Aktuellen Status vorauswählen
            const statusSelect = document.querySelector('#statusModal select[name="status"]');
            statusSelect.value = currentStatus;

            // Modal anzeigen
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        document.addEventListener('DOMContentLoaded', function() {
            // Status-Badge Klicks für Quick-Change
            document.querySelectorAll('.badge[data-status]').forEach(badge => {
                badge.style.cursor = 'pointer';
                badge.addEventListener('click', function() {
                    const abrechnungId = this.dataset.abrechnungId;
                    const currentStatus = this.dataset.status;
                    changeStatus(abrechnungId, currentStatus);
                });
            });
        });
    </script>
<?= $this->endSection() ?>