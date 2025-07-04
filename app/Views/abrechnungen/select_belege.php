<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Belege auswählen: <?= $abrechnung['titel'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
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
                    <a href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>"
                       class="btn btn-success">
                        📊 Excel-Export
                    </a>
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
                                                    <strong><?= $beleg['belegnummer'] ?></strong>
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
                                                        title="Zur Abrechnung hinzufügen">
                                                    ➡️
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
                                                    <strong><?= $beleg['belegnummer'] ?></strong>
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
                                                        title="Aus Abrechnung entfernen">
                                                    ❌
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
        const abrechnungId = <?= $abrechnung['id'] ?>;
        const abrechnungTyp = '<?= $typ ?>';
        const baseUrl = '<?= base_url() ?>';
        const csrfToken = '<?= csrf_token() ?>';
        const csrfHash = '<?= csrf_hash() ?>';

        document.addEventListener('DOMContentLoaded', function() {
            // Beleg hinzufügen
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('add-beleg-btn')) {
                    const belegId = e.target.dataset.belegId;
                    addBelegToAbrechnung(belegId);
                }
            });

            // Beleg entfernen
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('remove-beleg-btn')) {
                    const belegId = e.target.dataset.belegId;
                    removeBelegFromAbrechnung(belegId);
                }
            });
        });

        // Beleg zur Abrechnung hinzufügen
        function addBelegToAbrechnung(belegId) {
            const formData = new FormData();
            formData.append('beleg_id', belegId);
            formData.append(csrfToken, csrfHash);

            fetch(`${baseUrl}/abrechnungen/${abrechnungTyp}/addBeleg/${abrechnungId}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        // Beleg aus verfügbarer Liste entfernen
                        const belegItem = document.querySelector(`[data-beleg-id="${belegId}"]`);
                        const belegData = {
                            id: belegId,
                            belegnummer: belegItem.querySelector('strong').textContent,
                            betrag: belegItem.dataset.betrag,
                            html: belegItem.outerHTML
                        };

                        belegItem.remove();

                        // Beleg zu ausgewählten hinzufügen
                        addBelegToSelected(belegData);

                        // Summe aktualisieren
                        updateGesamtsumme(data.neue_gesamtsumme);

                        showMessage(data.message, 'success');
                    } else {
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    showMessage('Fehler beim Hinzufügen des Belegs', 'error');
                    console.error(error);
                });
        }

        // Beleg aus Abrechnung entfernen
        function removeBelegFromAbrechnung(belegId) {
            const formData = new FormData();
            formData.append('beleg_id', belegId);

            fetch(`${baseUrl}/abrechnungen/${abrechnungTyp}/removeBeleg/${abrechnungId}`, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Beleg aus ausgewählten entfernen
                        const belegItem = document.querySelector(`.zugeordneter-beleg[data-beleg-id="${belegId}"]`);
                        belegItem.remove();

                        // Summe aktualisieren
                        updateGesamtsumme(data.neue_gesamtsumme);
                        updateBelegAnzahl();

                        showMessage(data.message, 'success');

                        // Seite neu laden um verfügbare Belege zu aktualisieren
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showMessage(data.message, 'error');
                    }
                });
        }

        // Beleg zu ausgewählten hinzufügen (DOM)
        function addBelegToSelected(belegData) {
            const zugeordneteContainer = document.getElementById('zugeordnete-belege');
            const keineText = document.getElementById('keine-belege-text');

            // "Keine Belege" Text entfernen
            if (keineText) {
                keineText.remove();
            }

            // List-Group erstellen falls nicht vorhanden
            let listGroup = zugeordneteContainer.querySelector('.list-group');
            if (!listGroup) {
                listGroup = document.createElement('div');
                listGroup.className = 'list-group list-group-flush';
                zugeordneteContainer.appendChild(listGroup);
            }

            // Beleg-HTML anpassen und hinzufügen
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = belegData.html;
            const belegElement = tempDiv.firstElementChild;

            // Klasse ändern und Button austauschen
            belegElement.className = 'list-group-item zugeordneter-beleg';
            const button = belegElement.querySelector('.add-beleg-btn');
            button.className = 'btn btn-danger btn-sm remove-beleg-btn';
            button.innerHTML = '❌';
            button.title = 'Aus Abrechnung entfernen';

            // Hinzugefügt-Timestamp
            const zeitDiv = document.createElement('div');
            zeitDiv.className = 'text-muted small mt-1';
            zeitDiv.textContent = 'Hinzugefügt: ' + new Date().toLocaleDateString('de-DE') + ' ' + new Date().toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});
            belegElement.querySelector('.flex-grow-1').appendChild(zeitDiv);

            listGroup.appendChild(belegElement);
            updateBelegAnzahl();
        }

        // Gesamtsumme aktualisieren
        function updateGesamtsumme(neueSumme) {
            document.getElementById('gesamtsumme').textContent = neueSumme;
        }

        // Beleg-Anzahl aktualisieren
        function updateBelegAnzahl() {
            const anzahl = document.querySelectorAll('.zugeordneter-beleg').length;
            document.getElementById('anzahl-belege').textContent = anzahl + ' ausgewählt';
        }

        // Nachricht anzeigen
        function showMessage(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertHtml = `
        <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

            // Alert am Anfang der Seite einfügen
            const container = document.querySelector('.container-fluid');
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = alertHtml;
            container.insertBefore(tempDiv.firstElementChild, container.firstElementChild);
        }

        // Status auf "Ausstehend" setzen
        function markAsAusstehend() {
            if (confirm('Abrechnung als "Ausstehend" markieren? Sie kann dann nicht mehr bearbeitet werden.')) {
                const formData = new FormData();
                formData.append('status', 'ausstehend');

                fetch(`${baseUrl}/abrechnungen/${abrechnungTyp}/changeStatus/${abrechnungId}`, {
                    method: 'POST',
                    body: formData
                })
                    .then(() => location.reload());
            }
        }
    </script>
<?= $this->endSection() ?>