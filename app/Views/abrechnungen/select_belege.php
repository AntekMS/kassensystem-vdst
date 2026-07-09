<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Belege auswählen: <?= esc($abrechnung['titel']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h1 class="page-title">Belege auswählen</h1>
                <h4 class="text-muted"><?= esc($abrechnung['titel']) ?></h4>
            </div>
            <div>
                <a href="<?= base_url('/abrechnungen/' . $typ) ?>" class="btn btn-outline-secondary">
                    ← Abrechnungen-Übersicht
                </a>
                <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>"
                   class="btn btn-info">
                    👁️ Zur Vorschau
                </a>

                <?php if (count($zugeordnete_belege) > 0): ?>
                    <!-- Export-Optionen -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-success dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            📊 Export
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>">
                                    📊 Excel-Export
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>">
                                    📁 ZIP-Archiv
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Status und Summe -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card card-vdst">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <strong>Status:</strong>
                                <span class="badge bg-<?= $abrechnung['status'] === 'entwurf' ? 'secondary' : 'warning' ?> ms-2">
                                <?= ucfirst($abrechnung['status']) ?>
                            </span>
                            </div>
                            <div class="col-md-3">
                                <strong>Belege:</strong>
                                <span class="badge bg-dark ms-2" id="anzahl-belege">
                                <?= count($zugeordnete_belege) ?> ausgewählt
                            </span>
                            </div>
                            <div class="col-md-4">
                                <strong>Gesamtsumme:</strong>
                                <span class="badge bg-success ms-2" id="gesamtsumme" style="font-size: 1.1em;">
                                <?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €
                            </span>
                            </div>
                            <div class="col-md-2 text-end">
                                <?php if ($abrechnung['status'] === 'entwurf' && count($zugeordnete_belege) > 0): ?>
                                    <button class="btn btn-warning btn-sm" onclick="markAsAusstehend()">
                                        📤 Ausstehend markieren
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <?php if ($typ === 'hv'): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <strong>HV-Begründung</strong>
                        </div>
                        <div class="card-body">
                            <form id="begruendungForm" method="post" action="<?= base_url('/abrechnungen/hv/update/' . $abrechnung['id']) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="titel" value="<?= esc($abrechnung['titel'], 'attr') ?>">
                                <input type="hidden" name="notizen" value="<?= esc($abrechnung['notizen'] ?? '', 'attr') ?>">
                            <textarea name="begruendung" class="form-control" rows="3"
                                      placeholder="Begründung für Heimverein..."><?= esc($abrechnung['begruendung'] ?? '') ?></textarea>
                                <button type="submit" class="btn btn-outline-warning btn-sm mt-2">Speichern</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Verfügbare Belege -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Verfügbare <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Belege (<?= count($verfuegbare_belege) ?>)</strong>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                        <?php if (empty($verfuegbare_belege)): ?>
                            <div class="text-center p-4">
                                <p class="text-muted">Keine verfügbaren Belege gefunden.</p>
                                <small class="text-muted">
                                    Nur Belege mit Kategorie "<?= $typ === 'ah' ? 'ah_berechtigt' : 'hv_berechtigt' ?>"
                                    und Status "erfasst" werden angezeigt.
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($verfuegbare_belege as $beleg): ?>
                                    <div class="list-group-item beleg-item"
                                         data-beleg-id="<?= $beleg['id'] ?>"
                                         data-betrag="<?= $beleg['betrag'] ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?= esc($beleg['belegnummer']) ?></strong>
                                                    <span class="text-end">
                                                    <strong><?= number_format($beleg['betrag'], 2, ',', '.') ?> €</strong>
                                                </span>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?> |
                                                    <?= esc($beleg['lieferant'] ?: 'Kein Lieferant') ?>
                                                </div>
                                                <div class="mt-1">
                                                    <?= esc(substr($beleg['beschreibung'], 0, 80)) ?>
                                                    <?= strlen($beleg['beschreibung']) > 80 ? '...' : '' ?>
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <button class="btn btn-success btn-sm add-beleg-btn"
                                                        data-beleg-id="<?= $beleg['id'] ?>"
                                                        title="Zur Abrechnung hinzufügen" aria-label="Zur Abrechnung hinzufügen">
                                                    <span aria-hidden="true">➡️</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Ausgewählte Belege -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header" style="background-color: var(--vdst-rot); color: white;">
                        <strong>Ausgewählte Belege (<?= count($zugeordnete_belege) ?>)</strong>
                    </div>
                    <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;" id="zugeordnete-belege">
                        <?php if (empty($zugeordnete_belege)): ?>
                            <div class="text-center p-4" id="keine-belege-text">
                                <p class="text-muted">Noch keine Belege ausgewählt.</p>
                                <small class="text-muted">
                                    Wählen Sie Belege aus der linken Liste aus.
                                </small>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach($zugeordnete_belege as $beleg): ?>
                                    <div class="list-group-item zugeordneter-beleg"
                                         data-beleg-id="<?= $beleg['id'] ?>"
                                         data-betrag="<?= $beleg['betrag'] ?>">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?= esc($beleg['belegnummer']) ?></strong>
                                                    <span class="text-end">
                                                    <strong><?= number_format($beleg['betrag'], 2, ',', '.') ?> €</strong>
                                                </span>
                                                </div>
                                                <div class="text-muted small">
                                                    <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?> |
                                                    <?= esc($beleg['lieferant'] ?: 'Kein Lieferant') ?>
                                                </div>
                                                <div class="mt-1">
                                                    <?= esc(substr($beleg['beschreibung'], 0, 80)) ?>
                                                    <?= strlen($beleg['beschreibung']) > 80 ? '...' : '' ?>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    Hinzugefügt: <?= date('d.m.Y H:i', strtotime($beleg['hinzugefuegt_am'])) ?>
                                                </div>
                                            </div>
                                            <div class="ms-3">
                                                <button class="btn btn-danger btn-sm remove-beleg-btn"
                                                        data-beleg-id="<?= $beleg['id'] ?>"
                                                        title="Aus Abrechnung entfernen" aria-label="Aus Abrechnung entfernen">
                                                    <span aria-hidden="true">❌</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        const abrechnungId = <?= (int) $abrechnung['id'] ?>;
        const abrechnungTyp = '<?= $typ ?>';
        const baseUrl = '<?= base_url() ?>';
        const csrfToken = '<?= csrf_token() ?>';
        // Der Hash rotiert bei jedem POST; jede JSON-Antwort liefert den neuen mit
        let csrfHash = '<?= csrf_hash() ?>';

        document.addEventListener('DOMContentLoaded', function() {
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('add-beleg-btn')) {
                    sendeBelegAktion('addBeleg', e.target.dataset.belegId);
                }
                if (e.target.classList.contains('remove-beleg-btn')) {
                    sendeBelegAktion('removeBeleg', e.target.dataset.belegId);
                }
            });
        });

        // Beleg hinzufügen/entfernen (AJAX), danach Seite neu laden
        function sendeBelegAktion(aktion, belegId) {
            const formData = new FormData();
            formData.append('beleg_id', belegId);
            formData.append(csrfToken, csrfHash);

            fetch(`${baseUrl}/abrechnungen/${abrechnungTyp}/${aktion}/${abrechnungId}`, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.csrf_hash) {
                        csrfHash = data.csrf_hash;
                    }

                    if (data.success) {
                        showMessage(data.message, 'success');
                        setTimeout(() => location.reload(), 500);
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    showMessage('Fehler bei der Beleg-Zuordnung', 'error');
                });
        }

        // Status auf "Ausstehend" setzen
        function markAsAusstehend() {
            if (confirm('Abrechnung als "Ausstehend" markieren? Sie kann dann nicht mehr bearbeitet werden.')) {
                const formData = new FormData();
                formData.append('status', 'ausstehend');
                formData.append(csrfToken, csrfHash);

                fetch(`${baseUrl}/abrechnungen/${abrechnungTyp}/changeStatus/${abrechnungId}`, {
                    method: 'POST',
                    body: formData
                })
                    .then(() => location.reload());
            }
        }
    </script>
<?= $this->endSection() ?>