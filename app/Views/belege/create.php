<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Neuen Beleg hinzufügen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-1"><i class="bi bi-receipt" aria-hidden="true"></i> Neuen Beleg hinzufügen</h1>
                        <p class="text-muted mb-0">Laden Sie einen Beleg hoch und erfassen Sie die dazugehörigen Informationen</p>
                    </div>
                    <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück zur Übersicht
                    </a>
                </div>
            </div>
        </div>

        <form action="<?= base_url('/belege/store') ?>" method="post" enctype="multipart/form-data" id="belegForm">
            <?= csrf_field() ?>

            <div class="row">
                <!-- Linke Spalte: Datei-Upload -->
                <div class="col-lg-5">
                    <div class="card card-vdst h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-send" aria-hidden="true"></i> Datei hochladen</h5>
                        </div>
                        <div class="card-body">
                            <!-- Upload-Bereich -->
                            <div class="upload-area mb-3" onclick="document.getElementById('beleg_datei').click()" style="cursor: pointer;">
                                <div class="text-center p-4 border-2 border-dashed border-secondary rounded" id="uploadZone">
                                    <div id="uploadDefault">
                                        <div class="mb-3">
                                            <i class="bi bi-cloud-arrow-up display-4" aria-hidden="true"></i>
                                        </div>
                                        <h5 class="mb-2">Datei auswählen</h5>
                                        <p class="text-muted mb-2">Klicken Sie hier oder ziehen Sie eine Datei hinein</p>
                                        <small class="text-muted">PDF, JPG, PNG • Max. <?= $max_upload_size ?> MB</small>
                                    </div>
                                    <div id="uploadPreview" style="display: none;">
                                        <div class="mb-2">
                                            <i class="bi bi-check-circle display-5 text-success" aria-hidden="true"></i>
                                        </div>
                                        <div class="fw-bold" id="fileName"></div>
                                        <small class="text-muted" id="fileSize"></small>
                                    </div>
                                </div>
                            </div>

                            <input type="file"
                                   class="form-control <?= isset($errors['beleg_datei']) ? 'is-invalid' : '' ?>"
                                   id="beleg_datei"
                                   name="beleg_datei"
                                   accept=".pdf,.jpg,.jpeg,.png"
                                   style="display: none;"
                                   required>

                            <?php if (isset($errors['beleg_datei'])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors['beleg_datei']) ?></div>
                            <?php endif; ?>

                            <!-- Upload-Hinweise -->
                            <div class="mt-4">
                                <h6 class="fw-bold"><i class="bi bi-lightbulb" aria-hidden="true"></i> Hinweise zum Upload:</h6>
                                <ul class="small text-muted mb-0">
                                    <li>Erlaubte Dateitypen: PDF, JPG, PNG</li>
                                    <li>Maximale Dateigröße: <?= $max_upload_size ?> MB</li>
                                    <li>Die Datei wird automatisch umbenannt</li>
                                    <li>Originalname wird gespeichert</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rechte Spalte: Beleg-Daten -->
                <div class="col-lg-7">
                    <div class="card card-vdst h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-card-list" aria-hidden="true"></i> Beleg-Informationen</h5>
                        </div>
                        <div class="card-body">
                            <!-- Grunddaten -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rechnungsdatum" class="form-label fw-bold">
                                        Rechnungsdatum <span class="text-danger">*</span>
                                    </label>
                                    <input type="date"
                                           class="form-control <?= isset($errors['rechnungsdatum']) ? 'is-invalid' : '' ?>"
                                           id="rechnungsdatum"
                                           name="rechnungsdatum"
                                           value="<?= old('rechnungsdatum', date('Y-m-d')) ?>"
                                           required>
                                    <?php if (isset($errors['rechnungsdatum'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['rechnungsdatum']) ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Datum auf der Rechnung (bestimmt die Belegnummer)</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="betrag" class="form-label fw-bold">
                                        Betrag <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number"
                                               class="form-control js-betrag-format <?= isset($errors['betrag']) ? 'is-invalid' : '' ?>"
                                               id="betrag"
                                               name="betrag"
                                               step="0.01"
                                               min="0.01"
                                               value="<?= old('betrag') ?>"
                                               placeholder="0,00"
                                               required>
                                        <span class="input-group-text">€</span>
                                        <?php if (isset($errors['betrag'])): ?>
                                            <div class="invalid-feedback"><?= esc($errors['betrag']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Bezugsquelle und Kategorie -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="lieferant" class="form-label fw-bold">
                                        Bezugsquelle
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="lieferant"
                                           name="lieferant"
                                           value="<?= esc(old('lieferant') ?? '', 'attr') ?>"
                                           placeholder="z.B. Amazon, Rewe, Baumarkt...">
                                    <small class="text-muted">Name des Geschäfts oder Anbieters</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="kategorie" class="form-label fw-bold">
                                        Kategorie <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select <?= isset($errors['kategorie']) ? 'is-invalid' : '' ?>"
                                            id="kategorie"
                                            name="kategorie"
                                            required>
                                        <option value="">Kategorie auswählen...</option>
                                        <?php foreach($kategorien as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= old('kategorie') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['kategorie'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['kategorie']) ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">Bestimmt, für welche Abrechnungen der Beleg verfügbar ist</small>
                                </div>
                            </div>

                            <!-- Erstattung -->
                            <div class="mb-3">
                                <label for="erstattung_person" class="form-label fw-bold">
                                    Erstattung an <small class="text-muted">(optional)</small>
                                </label>
                                <input type="text"
                                       class="form-control <?= isset($errors['erstattung_person']) ? 'is-invalid' : '' ?>"
                                       id="erstattung_person"
                                       name="erstattung_person"
                                       list="personen-namen"
                                       value="<?= esc(old('erstattung_person') ?? '', 'attr') ?>"
                                       placeholder="Wer hat den Beleg ausgelegt und bekommt das Geld zurück?">
                                <datalist id="personen-namen">
                                    <?php foreach ($personen_namen as $name): ?>
                                        <option value="<?= esc($name, 'attr') ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <?php if (isset($errors['erstattung_person'])): ?>
                                    <div class="invalid-feedback"><?= esc($errors['erstattung_person']) ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Legt automatisch eine Verbindlichkeit in der Schuldenliste an</small>
                            </div>

                            <!-- Beschreibung -->
                            <div class="mb-3">
                                <label for="beschreibung" class="form-label fw-bold">
                                    Beschreibung <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control <?= isset($errors['beschreibung']) ? 'is-invalid' : '' ?>"
                                          id="beschreibung"
                                          name="beschreibung"
                                          rows="3"
                                          placeholder="Was wurde gekauft oder bezahlt? (z.B. Büromaterial, Verpflegung für Veranstaltung...)"
                                          required><?= esc(old('beschreibung') ?? '') ?></textarea>
                                <?php if (isset($errors['beschreibung'])): ?>
                                    <div class="invalid-feedback"><?= esc($errors['beschreibung']) ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Kurze, aber aussagekräftige Beschreibung der Ausgabe</small>
                            </div>

                            <!-- Notizen -->
                            <div class="mb-4">
                                <label for="notizen" class="form-label fw-bold">
                                    Zusätzliche Notizen <small class="text-muted">(optional)</small>
                                </label>
                                <textarea class="form-control"
                                          id="notizen"
                                          name="notizen"
                                          rows="2"
                                          placeholder="Weitere Informationen, Anmerkungen oder Besonderheiten..."><?= esc(old('notizen') ?? '') ?></textarea>
                            </div>

                            <!-- Info-Box -->
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-1 text-center">
                                        <i class="bi bi-info-circle fs-4" aria-hidden="true"></i>
                                    </div>
                                    <div class="col-11">
                                        <strong>Automatische Verarbeitung:</strong><br>
                                        <small>
                                            • Belegnummer wird automatisch generiert (Format: YYYY-MM-DD-001)<br>
                                            • Datei wird systematisch organisiert und umbenannt<br>
                                            • Status wird auf "Erfasst" gesetzt
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit-Bereich -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <span class="text-muted">
                                        <span class="text-danger">*</span> Pflichtfelder
                                    </span>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="<?= base_url('/belege') ?>" class="btn btn-outline-secondary">
                                        Abbrechen
                                    </a>
                                    <button type="submit" class="btn btn-vdst btn-lg" id="submitBtn">
                                        <strong><i class="bi bi-receipt" aria-hidden="true"></i> Beleg speichern</strong>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
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

        .form-label.fw-bold {
            color: var(--vdst-schwarz);
        }

        .card-header h5 {
            color: var(--vdst-weiss);
        }

        .input-group-text {
            background-color: var(--vdst-grau);
            border-color: #ddd;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--vdst-rot);
            box-shadow: 0 0 0 0.2rem rgba(220, 20, 60, 0.25);
        }
    </style>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fileInput = document.getElementById('beleg_datei');
            const uploadZone = document.getElementById('uploadZone');
            const uploadDefault = document.getElementById('uploadDefault');
            const uploadPreview = document.getElementById('uploadPreview');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            const form = document.getElementById('belegForm');
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
                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    uploadDefault.style.display = 'none';
                    uploadPreview.style.display = 'block';

                    // Upload-Zone-Farbe ändern
                    uploadZone.querySelector('.border-secondary').classList.remove('border-secondary');
                    uploadZone.querySelector('.border-dashed').classList.add('border-success');
                } else {
                    uploadDefault.style.display = 'block';
                    uploadPreview.style.display = 'none';
                }
            }

            // Dateigröße formatieren
            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            // Betrag-Formatierung erfolgt zentral über js-betrag-format (public/js/app.js)

            // Form-Submission mit Loading-State
            form.addEventListener('submit', function(e) {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Wird gespeichert...';
                submitBtn.disabled = true;

                // Falls Fehler auftreten, Button nach 5 Sekunden wieder aktivieren
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.innerHTML = '<strong><i class="bi bi-receipt" aria-hidden="true"></i> Beleg speichern</strong>';
                        submitBtn.disabled = false;
                    }
                }, 5000);
            });

        });
    </script>
<?= $this->endSection() ?>