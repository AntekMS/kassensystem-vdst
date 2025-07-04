<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Neuen Beleg hinzufügen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Neuen Beleg hinzufügen</h1>
            <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst">
                ← Zurück zur Übersicht
            </a>
        </div>

        <form action="<?= base_url('/belege/store') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row">
                <!-- Datei-Upload -->
                <div class="col-md-6">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Datei hochladen</strong>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="beleg_datei" class="form-label">
                                    <strong>Beleg-Datei</strong> <span class="text-danger">*</span>
                                </label>
                                <input type="file"
                                       class="form-control <?= isset($errors['beleg_datei']) ? 'is-invalid' : '' ?>"
                                       id="beleg_datei"
                                       name="beleg_datei"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       required>
                                <?php if (isset($errors['beleg_datei'])): ?>
                                    <div class="invalid-feedback"><?= $errors['beleg_datei'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    PDF, JPG oder PNG • Max. <?= $max_upload_size ?> MB
                                </small>
                            </div>

                            <!-- Datei-Vorschau -->
                            <div id="file-preview" style="display: none;">
                                <div class="alert alert-info">
                                    <strong>Ausgewählte Datei:</strong><br>
                                    <span id="file-name"></span><br>
                                    <small id="file-size"></small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beleg-Metadaten -->
                <div class="col-md-6">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Beleg-Informationen</strong>
                        </div>
                        <div class="card-body">
                            <!-- Rechnungsdatum -->
                            <div class="mb-3">
                                <label for="rechnungsdatum" class="form-label">
                                    <strong>Rechnungsdatum</strong> <span class="text-danger">*</span>
                                </label>
                                <input type="date"
                                       class="form-control <?= isset($errors['rechnungsdatum']) ? 'is-invalid' : '' ?>"
                                       id="rechnungsdatum"
                                       name="rechnungsdatum"
                                       value="<?= old('rechnungsdatum', date('Y-m-d')) ?>"
                                       required>
                                <?php if (isset($errors['rechnungsdatum'])): ?>
                                    <div class="invalid-feedback"><?= $errors['rechnungsdatum'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">Datum auf der Rechnung (wichtig für Belegnummer)</small>
                            </div>

                            <!-- Betrag -->
                            <div class="mb-3">
                                <label for="betrag" class="form-label">
                                    <strong>Betrag</strong> <span class="text-danger">*</span>
                                </label>
                                <div class="input-group">
                                    <input type="number"
                                           class="form-control <?= isset($errors['betrag']) ? 'is-invalid' : '' ?>"
                                           id="betrag"
                                           name="betrag"
                                           step="0.01"
                                           min="0.01"
                                           value="<?= old('betrag') ?>"
                                           placeholder="0,00"
                                           required>
                                    <span class="input-group-text">€</span>
                                    <?php if (isset($errors['betrag'])): ?>
                                        <div class="invalid-feedback"><?= $errors['betrag'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Lieferant -->
                            <div class="mb-3">
                                <label for="lieferant" class="form-label">
                                    <strong>Lieferant/Firma</strong>
                                </label>
                                <input type="text"
                                       class="form-control"
                                       id="lieferant"
                                       name="lieferant"
                                       value="<?= old('lieferant') ?>"
                                       placeholder="Name der Firma oder Person">
                                <small class="text-muted">Optional</small>
                            </div>

                            <!-- Kategorie -->
                            <div class="mb-3">
                                <label for="kategorie" class="form-label">
                                    <strong>Kategorie</strong> <span class="text-danger">*</span>
                                </label>
                                <select class="form-control <?= isset($errors['kategorie']) ? 'is-invalid' : '' ?>"
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
                                    <div class="invalid-feedback"><?= $errors['kategorie'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    Bestimmt für welche Abrechnungen der Beleg verwendet werden kann
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Beschreibung -->
            <div class="row mt-3">
                <div class="col-12">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Beschreibung</strong>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="beschreibung" class="form-label">
                                    <strong>Was wurde gekauft/bezahlt?</strong> <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control <?= isset($errors['beschreibung']) ? 'is-invalid' : '' ?>"
                                          id="beschreibung"
                                          name="beschreibung"
                                          rows="3"
                                          placeholder="Beschreibung der Ausgabe..."
                                          required><?= old('beschreibung') ?></textarea>
                                <?php if (isset($errors['beschreibung'])): ?>
                                    <div class="invalid-feedback"><?= $errors['beschreibung'] ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="mb-3">
                                <label for="notizen" class="form-label">
                                    <strong>Notizen</strong> <small class="text-muted">(optional)</small>
                                </label>
                                <textarea class="form-control"
                                          id="notizen"
                                          name="notizen"
                                          rows="2"
                                          placeholder="Zusätzliche Informationen..."><?= old('notizen') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('/belege') ?>" class="btn btn-outline-secondary">
                            Abbrechen
                        </a>
                        <button type="submit" class="btn btn-vdst btn-lg">
                            <strong>Beleg speichern</strong>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Datei-Upload Vorschau
            const fileInput = document.getElementById('beleg_datei');
            const filePreview = document.getElementById('file-preview');
            const fileName = document.getElementById('file-name');
            const fileSize = document.getElementById('file-size');

            fileInput.addEventListener('change', function() {
                if (this.files[0]) {
                    const file = this.files[0];
                    fileName.textContent = file.name;
                    fileSize.textContent = formatFileSize(file.size);
                    filePreview.style.display = 'block';
                } else {
                    filePreview.style.display = 'none';
                }
            });

            // Betrag formatieren
            const betragInput = document.getElementById('betrag');
            betragInput.addEventListener('blur', function() {
                const value = parseFloat(this.value);
                if (!isNaN(value)) {
                    this.value = value.toFixed(2);
                }
            });
        });

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }
    </script>
<?= $this->endSection() ?>