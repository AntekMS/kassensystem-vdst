<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Beleg <?= $beleg['belegnummer'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Beleg: <?= $beleg['belegnummer'] ?></h1>
            <div>
                <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>
                <?php if ($kann_bearbeitet_werden): ?>
                    <a href="<?= base_url('/belege/edit/' . $beleg['id']) ?>" class="btn btn-outline-vdst">
                        ✏️ Bearbeiten
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>" class="btn btn-vdst">
                    📥 Download
                </a>
                <?php if (empty($abrechnungen) && $beleg['status'] === 'erfasst'): ?>
                    <a href="<?= base_url('/belege/delete/' . $beleg['id']) ?>"
                       class="btn btn-danger"
                       onclick="return confirmDelete('Beleg <?= $beleg['belegnummer'] ?> wirklich löschen? Die Datei wird ebenfalls unwiderruflich gelöscht!')"
                       title="Beleg und Datei löschen">
                        🗑️ Löschen
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="row">
            <!-- Beleg-Informationen -->
            <div class="col-md-4">
                <div class="card card-vdst">
                    <div class="card-header">
                        <strong>Beleg-Details</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Belegnummer:</strong></td>
                                <td><?= $beleg['belegnummer'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Rechnungsdatum:</strong></td>
                                <td><?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Eingabedatum:</strong></td>
                                <td><?= date('d.m.Y', strtotime($beleg['eingabedatum'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>Betrag:</strong></td>
                                <td>
                                    <strong style="font-size: 1.2em;">
                                        <?= number_format($beleg['betrag'], 2, ',', '.') ?> €
                                    </strong>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Lieferant:</strong></td>
                                <td><?= $beleg['lieferant'] ?: '<span class="text-muted">Nicht angegeben</span>' ?></td>
                            </tr>
                            <tr>
                                <td><strong>Kategorie:</strong></td>
                                <td>
                                <span class="badge bg-<?= $beleg['kategorie'] === 'normal' ? 'secondary' : 'primary' ?>">
                                    <?php
                                    $kategorien = [
                                        'normal' => 'Normal',
                                        'ah_berechtigt' => 'AH² berechtigt',
                                        'hv_berechtigt' => 'HV berechtigt'
                                    ];
                                    echo $kategorien[$beleg['kategorie']] ?? $beleg['kategorie'];
                                    ?>
                                </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                <span class="badge bg-<?= $beleg['status'] === 'erfasst' ? 'secondary' : 'info' ?>">
                                    <?php
                                    $status = [
                                        'erfasst' => 'Erfasst',
                                        'in_abrechnung' => 'In Abrechnung',
                                        'abgerechnet' => 'Abgerechnet',
                                        'bezahlt' => 'Bezahlt'
                                    ];
                                    echo $status[$beleg['status']] ?? $beleg['status'];
                                    ?>
                                </span>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Beschreibung -->
                <div class="card card-vdst mt-3">
                    <div class="card-header">
                        <strong>Beschreibung</strong>
                    </div>
                    <div class="card-body">
                        <p><?= nl2br(esc($beleg['beschreibung'])) ?></p>
                        <?php if (!empty($beleg['notizen'])): ?>
                            <hr>
                            <strong>Notizen:</strong><br>
                            <small class="text-muted"><?= nl2br(esc($beleg['notizen'])) ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Datei-Informationen -->
                <div class="card card-vdst mt-3">
                    <div class="card-header">
                        <strong>Datei-Informationen</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Original-Name:</strong></td>
                                <td><small><?= esc($beleg['dateiname_original']) ?></small></td>
                            </tr>
                            <tr>
                                <td><strong>Dateityp:</strong></td>
                                <td>
                                <span class="badge bg-secondary">
                                    <?= strtoupper($beleg['dateityp']) ?>
                                </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Dateigröße:</strong></td>
                                <td><?= number_format($beleg['dateigroesse'] / 1024, 1) ?> KB</td>
                            </tr>
                            <tr>
                                <td><strong>Datei-Status:</strong></td>
                                <td>
                                    <?php if ($datei_existiert): ?>
                                        <span class="text-success">✓ Verfügbar</span>
                                    <?php else: ?>
                                        <span class="text-danger">✗ Datei nicht gefunden</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <!-- Abrechnungen -->
                <?php if (!empty($abrechnungen)): ?>
                    <div class="card card-vdst mt-3">
                        <div class="card-header">
                            <strong>Verwendung in Abrechnungen</strong>
                        </div>
                        <div class="card-body">
                            <?php foreach($abrechnungen as $abrechnung): ?>
                                <div class="mb-2">
                            <span class="badge bg-<?= $abrechnung['typ'] === 'ah' ? 'info' : 'warning' ?>">
                                <?= strtoupper($abrechnung['typ']) ?>
                            </span>
                                    <strong><?= esc($abrechnung['titel']) ?></strong><br>
                                    <small class="text-muted">
                                        Status: <?= $abrechnung['status'] ?> |
                                        Hinzugefügt: <?= date('d.m.Y H:i', strtotime($abrechnung['hinzugefuegt_am'])) ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Datei-Vorschau -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <strong>Datei-Vorschau: <?= esc($beleg['dateiname_original']) ?></strong>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!$datei_existiert): ?>
                            <div class="text-center p-5">
                                <h4 class="text-danger">❌ Datei nicht gefunden</h4>
                                <p class="text-muted">
                                    Die Datei <code><?= esc($beleg['dateipfad']) ?></code> konnte nicht gefunden werden.
                                </p>
                            </div>
                        <?php elseif ($beleg['dateityp'] === 'pdf'): ?>
                            <!-- PDF-Vorschau -->
                            <div id="pdf-container" style="height: 700px; background: #f8f9fa;">
                                <div class="text-center p-4">
                                    <p class="mb-3">
                                        <strong>PDF-Dokument</strong><br>
                                        <small class="text-muted">Klicken Sie auf "PDF laden" um das Dokument anzuzeigen</small>
                                    </p>
                                    <button class="btn btn-vdst" onclick="loadPDF()">
                                        📄 PDF laden
                                    </button>
                                </div>
                                <iframe id="pdf-frame"
                                        src=""
                                        style="width: 100%; height: 100%; border: none; display: none;">
                                </iframe>
                            </div>
                        <?php else: ?>
                            <!-- Bild-Vorschau -->
                            <div class="text-center" style="background: #f8f9fa;">
                                <img src="<?= base_url('/belege/preview/' . $beleg['id']) ?>"
                                     alt="<?= esc($beleg['beschreibung']) ?>"
                                     class="img-fluid"
                                     style="max-height: 700px; cursor: zoom-in;"
                                     onclick="openImageModal(this.src)">
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($datei_existiert): ?>
                        <div class="card-footer text-center">
                            <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                               class="btn btn-outline-vdst">
                                📥 Original herunterladen
                            </a>
                            <?php if ($beleg['dateityp'] !== 'pdf'): ?>
                                <button class="btn btn-outline-secondary" onclick="openImageModal('<?= base_url('/belege/preview/' . $beleg['id']) ?>')">
                                    🔍 Vollbild anzeigen
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Vollbild-Ansicht -->
    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vollbild-Ansicht</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center p-0">
                    <img id="modalImage" src="" class="img-fluid" style="max-width: 100%; height: auto;">
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        // PDF laden
        function loadPDF() {
            const frame = document.getElementById('pdf-frame');
            const container = document.getElementById('pdf-container');

            frame.src = '<?= base_url('/belege/preview/' . $beleg['id']) ?>';
            frame.style.display = 'block';

            // Loading-Text verstecken
            container.querySelector('.text-center').style.display = 'none';

            // Fallback falls PDF nicht geladen werden kann
            frame.onload = function() {
                console.log('PDF erfolgreich geladen');
            };

            frame.onerror = function() {
                container.innerHTML = `
            <div class="text-center p-4">
                <h5 class="text-warning">⚠️ PDF-Vorschau nicht verfügbar</h5>
                <p class="text-muted">
                    Das PDF kann in diesem Browser nicht angezeigt werden.
                </p>
                <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                   class="btn btn-vdst">
                    📥 PDF herunterladen
                </a>
            </div>
        `;
            };
        }

        // Vollbild-Modal für Bilder
        function openImageModal(imageSrc) {
            const modal = new bootstrap.Modal(document.getElementById('imageModal'));
            document.getElementById('modalImage').src = imageSrc;
            modal.show();
        }

        // Tastatur-Navigation
        document.addEventListener('keydown', function(e) {
            // ESC zum Schließen der Modals
            if (e.key === 'Escape') {
                const modals = document.querySelectorAll('.modal.show');
                modals.forEach(modal => {
                    bootstrap.Modal.getInstance(modal)?.hide();
                });
            }
        });
    </script>
<?= $this->endSection() ?>