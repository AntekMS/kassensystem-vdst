<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Kassenbuch importieren<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-1">📥 Kassenbuch importieren</h1>
                        <p class="text-muted mb-0">Stellen Sie ein komplettes Backup wieder her</p>
                    </div>
                    <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst">
                        ← Zurück zum Kassenbuch
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Hauptformular -->
            <div class="col-lg-8">
                <div class="card card-vdst">
                    <div class="card-header">
                        <strong>Backup-Datei hochladen</strong>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('/buchungen/import/analyse') ?>"
                              method="post"
                              enctype="multipart/form-data"
                              id="importForm">
                            <?= csrf_field() ?>

                            <!-- Upload-Bereich -->
                            <div class="upload-area mb-4"
                                 onclick="document.getElementById('import_datei').click()"
                                 style="cursor: pointer;">
                                <div class="text-center p-5 border-2 border-dashed border-secondary rounded"
                                     id="uploadZone">
                                    <div id="uploadDefault">
                                        <div class="mb-3">
                                            <i class="display-1">📦</i>
                                        </div>
                                        <h4 class="mb-2">Backup-Datei auswählen</h4>
                                        <p class="text-muted mb-2">Klicken Sie hier oder ziehen Sie eine ZIP-Datei hinein</p>
                                        <small class="text-muted">
                                            Nur .zip Dateien • Max. <?= $max_upload_size ?> MB
                                        </small>
                                    </div>
                                    <div id="uploadPreview" style="display: none;">
                                        <div class="mb-3">
                                            <i class="display-3">✓</i>
                                        </div>
                                        <h5 id="fileName" class="mb-2"></h5>
                                        <small class="text-muted" id="fileSize"></small>
                                    </div>
                                </div>
                            </div>

                            <input type="file"
                                   class="form-control <?= isset($errors['import_datei']) ? 'is-invalid' : '' ?>"
                                   id="import_datei"
                                   name="import_datei"
                                   accept=".zip"
                                   style="display: none;"
                                   required>

                            <?php if (isset($errors['import_datei'])): ?>
                                <div class="invalid-feedback d-block"><?= $errors['import_datei'] ?></div>
                            <?php endif; ?>

                            <!-- Hinweise -->
                            <div class="alert alert-info">
                                <h6 class="fw-bold mb-2">ℹ️ Wichtige Hinweise:</h6>
                                <ul class="mb-0 small">
                                    <li>Das System erstellt <strong>automatisch ein Sicherheits-Backup</strong> vor dem Import</li>
                                    <li>Sie können zwischen <strong>"Zusammenführen"</strong> und <strong>"Überschreiben"</strong> wählen</li>
                                    <li>Eine <strong>Vorschau</strong> zeigt mögliche Konflikte an</li>
                                    <li>Original-Belegnummern und Datumsstempel bleiben erhalten</li>
                                    <li>Kontostände werden nach Import neu berechnet</li>
                                </ul>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-flex justify-content-between">
                                <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-secondary">
                                    Abbrechen
                                </a>
                                <button type="submit" class="btn btn-vdst btn-lg" id="submitBtn">
                                    <strong>📊 Backup analysieren</strong>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Informationen -->
            <div class="col-lg-4">
                <!-- Was wird importiert -->
                <div class="card card-vdst mb-3">
                    <div class="card-header">
                        <strong>Was wird importiert?</strong>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0">
                            <li><strong>Buchungen</strong> - Alle Kassenbuch-Einträge</li>
                            <li><strong>Belege</strong> - Metadaten + Dateien</li>
                            <li><strong>Abrechnungen</strong> - AH² und HV</li>
                            <li><strong>Verknüpfungen</strong> - Belege↔Abrechnungen</li>
                            <li><strong>Einstellungen</strong> - System-Konfiguration</li>
                        </ul>
                    </div>
                </div>

                <!-- Import-Modi -->
                <div class="card card-vdst mb-3">
                    <div class="card-header">
                        <strong>Import-Modi</strong>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold">🔄 Zusammenführen</h6>
                        <p class="small text-muted mb-3">
                            Neue Einträge werden hinzugefügt, bestehende bleiben unverändert.
                            Empfohlen für ergänzende Daten.
                        </p>

                        <h6 class="fw-bold">♻️ Überschreiben</h6>
                        <p class="small text-muted mb-0">
                            Bestehende Einträge werden mit Import-Daten aktualisiert.
                            Empfohlen für vollständige Wiederherstellung.
                        </p>
                    </div>
                </div>

                <!-- Sicherheit -->
                <div class="card border-success">
                    <div class="card-header bg-success text-white">
                        <strong>🛡️ Sicherheit</strong>
                    </div>
                    <div class="card-body">
                        <p class="small mb-2">
                            <strong>Automatisches Backup:</strong><br>
                            Vor jedem Import wird automatisch ein Sicherungs-Backup erstellt.
                        </p>
                        <p class="small mb-0">
                            <strong>Validierung:</strong><br>
                            Alle Daten werden vor dem Import geprüft.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('styles') ?>
    <style>
        .upload-area {
            transition: all 0.3s ease;
        }

        .upload-area:hover {
            background-color: rgba(0,0,0,0.02);
        }

        .upload-area.dragover {
            background-color: rgba(220, 20, 60, 0.1);
            border-color: var(--vdst-rot) !important;
        }
    </style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('import_datei');
            const uploadZone = document.getElementById('uploadZone');
            const uploadDefault = document.getElementById('uploadDefault');
            const uploadPreview = document.getElementById('uploadPreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const form = document.getElementById('importForm');
            const submitBtn = document.getElementById('submitBtn');

            // Datei-Upload Handler
            fileInput.addEventListener('change', function() {
                handleFileSelection(this.files[0]);
            });

            // Drag & Drop
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                uploadZone.classList.add('dragover');
            });

            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');
            });

            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    handleFileSelection(files[0]);
                }
            });

            // Datei-Auswahl verarbeiten
            function handleFileSelection(file) {
                if (file) {
                    // Prüfe Dateityp
                    if (!file.name.toLowerCase().endsWith('.zip')) {
                        alert('Bitte wählen Sie eine .zip Datei aus.');
                        return;
                    }

                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    uploadDefault.style.display = 'none';
                    uploadPreview.style.display = 'block';

                    // Upload-Zone-Farbe ändern
                    const border = uploadZone.querySelector('.border-secondary');
                    if (border) {
                        border.classList.remove('border-secondary');
                        border.classList.add('border-success');
                    }
                } else {
                    uploadDefault.style.display = 'block';
                    uploadPreview.style.display = 'none';
                }
            }

            // Dateigröße formatieren
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            // Form-Submission mit Loading-State
            form.addEventListener('submit', function(e) {
                if (!fileInput.files || fileInput.files.length === 0) {
                    e.preventDefault();
                    alert('Bitte wählen Sie eine Backup-Datei aus.');
                    return;
                }

                submitBtn.innerHTML = '🔄 Wird analysiert...';
                submitBtn.disabled = true;
            });

            // Keyboard Shortcuts
            document.addEventListener('keydown', function(e) {
                // Escape = Zurück
                if (e.key === 'Escape') {
                    if (confirm('Import abbrechen und zurück zum Kassenbuch?')) {
                        window.location.href = '<?= base_url('/buchungen') ?>';
                    }
                }
            });

            console.log('📥 Import-Formular geladen');
        });
    </script>
<?= $this->endSection() ?>