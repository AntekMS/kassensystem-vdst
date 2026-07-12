<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Getränkerechnung importieren<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-1">Getränkerechnung importieren</h1>
                        <p class="text-muted mb-0">Excel des Getränkewarts hochladen — Forderungen und AH-Belege werden automatisch angelegt</p>
                    </div>
                    <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück zur Schuldenliste
                    </a>
                </div>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-7">
                <div class="card card-vdst">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-arrow-up" aria-hidden="true"></i> Datei hochladen</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('/schulden/import/upload') ?>" method="post" enctype="multipart/form-data" id="importForm">
                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="monat" class="form-label fw-bold">
                                    Monat der Rechnung <span class="text-danger">*</span>
                                </label>
                                <input type="month"
                                       class="form-control <?= isset($errors['monat']) ? 'is-invalid' : '' ?>"
                                       id="monat"
                                       name="monat"
                                       value="<?= esc(old('monat', $vormonat), 'attr') ?>"
                                       required>
                                <?php if (isset($errors['monat'])): ?>
                                    <div class="invalid-feedback"><?= esc($errors['monat']) ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Wird für Grund, Datum und die Beleg-Beschreibung verwendet</small>
                            </div>

                            <div class="upload-area mb-3" onclick="document.getElementById('import_datei').click()">
                                <div class="text-center p-4 border-2 border-dashed border-secondary rounded" id="uploadZone">
                                    <div id="uploadDefault">
                                        <div class="mb-3">
                                            <i class="bi bi-file-earmark-spreadsheet display-4" aria-hidden="true"></i>
                                        </div>
                                        <h5 class="mb-2">Excel-Datei auswählen</h5>
                                        <p class="text-muted mb-2">Klicken Sie hier oder ziehen Sie die Getränkerechnung hinein</p>
                                        <small class="text-muted">XLSX • Max. <?= $max_upload_size ?> MB</small>
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
                                   class="form-control <?= isset($errors['import_datei']) ? 'is-invalid' : '' ?>"
                                   id="import_datei"
                                   name="import_datei"
                                   accept=".xlsx"
                                   style="display: none;"
                                   required>

                            <?php if (isset($errors['import_datei'])): ?>
                                <div class="invalid-feedback d-block"><?= esc($errors['import_datei']) ?></div>
                            <?php endif; ?>

                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-1 text-center">
                                        <i class="bi bi-info-circle fs-4" aria-hidden="true"></i>
                                    </div>
                                    <div class="col-11">
                                        <strong>Was passiert beim Import?</strong><br>
                                        <small>
                                            • Sheet „Bundesbrüder &amp; Gäste": pro Person eine Getränke-Forderung
                                            (Gesamt abzüglich „Ausstehend" — Altbestände führt bereits die Schuldenliste)<br>
                                            • Sheet „Coleur &amp; Bund": zwei Belege für die AH-Abrechnung
                                            (offene wird verwendet, sonst neu angelegt)<br>
                                            • Vor dem Anlegen wird eine Vorschau zur Kontrolle angezeigt
                                        </small>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">Abbrechen</a>
                                <button type="submit" class="btn btn-vdst" id="submitBtn">
                                    <i class="bi bi-search" aria-hidden="true"></i> Hochladen &amp; Vorschau
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
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

            fileInput.addEventListener('change', function() {
                handleFileSelection(this.files[0]);
            });

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

            function handleFileSelection(file) {
                if (file) {
                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    uploadDefault.style.display = 'none';
                    uploadPreview.style.display = 'block';
                    uploadZone.classList.remove('border-secondary');
                    uploadZone.classList.add('border-success');
                } else {
                    uploadDefault.style.display = 'block';
                    uploadPreview.style.display = 'none';
                }
            }

            function formatFileSize(bytes) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const sizes = ['Bytes', 'KB', 'MB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
            }

            form.addEventListener('submit', function() {
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Wird geprüft...';
                submitBtn.disabled = true;
            });
        });
    </script>
<?= $this->endSection() ?>
