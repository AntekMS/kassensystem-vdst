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
                                                <!-- Export-Dropdown -->
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="btn btn-success dropdown-toggle"
                                                            data-bs-toggle="dropdown" aria-expanded="false" title="Export-Optionen">
                                                        📊 Export
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item"
                                                               href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>">
                                                                📊 Excel-Datei
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item"
                                                               href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>">
                                                                📁 ZIP-Archiv (alle Belege)
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array($abrechnung['status'], ['entwurf', 'ausstehend'])): ?>
                                                <a href="<?= base_url('/abrechnungen/' . $typ . '/delete/' . $abrechnung['id']) ?>"
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirmDelete('<?= esc($abrechnung['titel']) ?>', '<?= $abrechnung['status'] ?>')"
                                                   title="Abrechnung löschen">
                                                    🗑️ Löschen
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

            // Export-Download Feedback
            initializeExportFeedback();
        });

        function initializeExportFeedback() {
            // ZIP-Download mit Loading-Indikator
            const zipLinks = document.querySelectorAll('a[href*="/downloadZip/"]');

            zipLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    showLoadingToast('ZIP-Archiv wird erstellt...', 'Das kann einen Moment dauern.');

                    setTimeout(() => {
                        hideLoadingToast();
                        showSuccessToast('ZIP-Download gestartet!', 'Das Archiv wurde erstellt und der Download gestartet.');
                    }, 2000);
                });
            });

            // Excel-Download Feedback
            const excelLinks = document.querySelectorAll('a[href*="/exportExcel/"]');

            excelLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    showSuccessToast('Excel-Export gestartet!', 'Die Datei wird heruntergeladen.');
                });
            });
        }

        function showLoadingToast(title, message) {
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="loadingToast" class="toast show" role="alert">
                        <div class="toast-header bg-info text-white">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <strong class="me-auto">${title}</strong>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', toastHtml);
        }

        function hideLoadingToast() {
            const loadingToast = document.getElementById('loadingToast');
            if (loadingToast) {
                loadingToast.remove();
            }
        }

        function showSuccessToast(title, message) {
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="3000">
                        <div class="toast-header bg-success text-white">
                            <strong class="me-auto">${title}</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', toastHtml);
        }
            // Verbesserte Lösch-Bestätigung
            function confirmDelete(titel, status) {
            const statusText = status === 'entwurf' ? 'Entwurf' : 'Ausstehende Abrechnung';
            const message = `${statusText} "${titel}" wirklich löschen?\n\n` +
            `⚠️ ACHTUNG:\n` +
            `• Alle Beleg-Zuordnungen werden entfernt\n` +
            `• Die Belege selbst bleiben erhalten\n` +
            `• Diese Aktion kann nicht rückgängig gemacht werden\n\n` +
            `Fortfahren?`;

            return confirm(message);
        }

            // Zusätzliche Debugging-Funktion für AJAX-Requests
            function debugRemoveBeleg(belegId, abrechnungId, typ) {
            console.log('removeBeleg Debug:', {
                belegId: belegId,
                abrechnungId: abrechnungId,
                typ: typ,
                url: `${baseUrl}/abrechnungen/${typ}/removeBeleg/${abrechnungId}`
            });
        }

            // Erweiterte removeBelegFromAbrechnung Funktion mit besserem Error-Handling
            function removeBelegFromAbrechnung(belegId) {
            // Debug-Info
            debugRemoveBeleg(belegId, abrechnungId, abrechnungTyp);

            const formData = new FormData();
            formData.append('beleg_id', belegId);
            // CSRF-Token falls verfügbar
            if (typeof csrfToken !== 'undefined' && typeof csrfHash !== 'undefined') {
            formData.append(csrfToken, csrfHash);
        }

            const url = `${baseUrl}/abrechnungen/${abrechnungTyp}/removeBeleg/${abrechnungId}`;

            fetch(url, {
            method: 'POST',
            body: formData,
            headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
        })
            .then(response => {
            console.log('Response Status:', response.status);
            if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
            return response.json();
        })
            .then(data => {
            console.log('Response Data:', data);

            if (data.success) {
            // Beleg aus DOM entfernen
            const belegElement = document.querySelector(`.zugeordneter-beleg[data-beleg-id="${belegId}"]`);
            if (belegElement) {
            belegElement.remove();
        }

            // UI aktualisieren
            updateGesamtsumme(data.neue_gesamtsumme);
            updateBelegAnzahl();

            showMessage(data.message, 'success');

            // Seite nach kurzer Verzögerung neu laden um verfügbare Belege zu aktualisieren
            setTimeout(() => {
            location.reload();
        }, 1000);
        } else {
            showMessage(data.message || 'Unbekannter Fehler beim Entfernen', 'error');
        }
        })
            .catch(error => {
            console.error('Fetch Error:', error);
            showMessage(`Fehler beim Entfernen des Belegs: ${error.message}`, 'error');
        });
        }
    </script>
<?= $this->endSection() ?>