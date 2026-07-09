<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Neue Buchung<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Neue Buchung erstellen</h1>
            <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst">
                ← Zurück zum Kassenbuch
            </a>
        </div>

        <form action="<?= base_url('/buchungen/store') ?>" method="post" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="row">
                <!-- Hauptformular -->
                <div class="col-md-8">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Buchungs-Details</strong>
                        </div>
                        <div class="card-body">
                            <!-- Buchungsdatum und Konto -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="buchungsdatum" class="form-label">
                                        <strong>Buchungsdatum</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="date"
                                           class="form-control <?= isset($errors['buchungsdatum']) ? 'is-invalid' : '' ?>"
                                           id="buchungsdatum"
                                           name="buchungsdatum"
                                           value="<?= old('buchungsdatum', date('Y-m-d')) ?>"
                                           required>
                                    <?php if (isset($errors['buchungsdatum'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['buchungsdatum']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="konto_typ" class="form-label">
                                        <strong>Konto</strong> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control <?= isset($errors['konto_typ']) ? 'is-invalid' : '' ?>"
                                            id="konto_typ"
                                            name="konto_typ"
                                            required>
                                        <option value="">Konto auswählen...</option>
                                        <?php foreach($konten as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= old('konto_typ') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['konto_typ'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['konto_typ']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Buchungsart und Betrag -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="buchungsart" class="form-label">
                                        <strong>Art der Buchung</strong> <span class="text-danger">*</span>
                                    </label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="buchungsart" id="ausgabe"
                                               value="ausgabe" <?= old('buchungsart', 'ausgabe') === 'ausgabe' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger" for="ausgabe">
                                            📉 Ausgabe
                                        </label>

                                        <input type="radio" class="btn-check" name="buchungsart" id="einnahme"
                                               value="einnahme" <?= old('buchungsart') === 'einnahme' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-success" for="einnahme">
                                            📈 Einnahme
                                        </label>
                                    </div>
                                    <?php if (isset($errors['buchungsart'])): ?>
                                        <div class="text-danger small mt-1"><?= esc($errors['buchungsart']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <label for="betrag" class="form-label">
                                        <strong>Betrag</strong> <span class="text-danger">*</span>
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

                            <!-- Beschreibung -->
                            <div class="mb-3">
                                <label for="beschreibung" class="form-label">
                                    <strong>Beschreibung</strong> <span class="text-danger">*</span>
                                </label>
                                <textarea class="form-control <?= isset($errors['beschreibung']) ? 'is-invalid' : '' ?>"
                                          id="beschreibung"
                                          name="beschreibung"
                                          rows="3"
                                          placeholder="Was wurde gekauft/verkauft/bezahlt..."
                                          required><?= esc(old('beschreibung') ?? '') ?></textarea>
                                <?php if (isset($errors['beschreibung'])): ?>
                                    <div class="invalid-feedback"><?= esc($errors['beschreibung']) ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Notizen -->
                            <div class="mb-3">
                                <label for="notizen" class="form-label">
                                    <strong>Notizen</strong> <small class="text-muted">(optional)</small>
                                </label>
                                <textarea class="form-control"
                                          id="notizen"
                                          name="notizen"
                                          rows="2"
                                          placeholder="Zusätzliche Informationen..."><?= esc(old('notizen') ?? '') ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beleg-Bereich -->
                <div class="col-md-4">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Beleg zuordnen</strong>
                        </div>
                        <div class="card-body">
                            <!-- Beleg-Optionen -->
                            <div class="mb-3">
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="beleg_option" id="kein_beleg"
                                           value="kein_beleg" checked>
                                    <label class="btn btn-outline-secondary" for="kein_beleg">
                                        Kein Beleg
                                    </label>

                                    <input type="radio" class="btn-check" name="beleg_option" id="beleg_waehlen"
                                           value="beleg_waehlen">
                                    <label class="btn btn-outline-primary" for="beleg_waehlen">
                                        Beleg wählen
                                    </label>

                                    <input type="radio" class="btn-check" name="beleg_option" id="beleg_upload"
                                           value="beleg_upload">
                                    <label class="btn btn-outline-success" for="beleg_upload">
                                        Upload
                                    </label>
                                </div>
                            </div>

                            <!-- Beleg auswählen -->
                            <div id="beleg_auswahl_bereich" style="display: none;">
                                <label for="beleg_id" class="form-label">Vorhandenen Beleg auswählen</label>
                                <select class="form-control" id="beleg_id" name="beleg_id">
                                    <option value="">Beleg auswählen...</option>
                                    <?php foreach($verfuegbare_belege as $beleg): ?>
                                        <option value="<?= $beleg['id'] ?>"
                                                data-betrag="<?= $beleg['betrag'] ?>"
                                                data-beschreibung="<?= esc($beleg['beschreibung']) ?>">
                                            <?= esc($beleg['belegnummer']) ?> - <?= esc($beleg['beschreibung']) ?>
                                            (<?= number_format($beleg['betrag'], 2, ',', '.') ?> €)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    Nur Belege ohne Buchung werden angezeigt.
                                </small>
                            </div>

                            <!-- Beleg Upload -->
                            <div id="beleg_upload_bereich" style="display: none;">
                                <label for="beleg_datei" class="form-label">Neue Beleg-Datei</label>
                                <input type="file"
                                       class="form-control"
                                       id="beleg_datei"
                                       name="beleg_datei"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">
                                    PDF, JPG oder PNG • Max. 10 MB
                                </small>

                                <!-- Beleg-Metadaten bei Upload -->
                                <div id="beleg_metadaten" style="display: none;">
                                    <hr>
                                    <div class="mb-2">
                                        <label for="beleg_rechnungsdatum" class="form-label">Rechnungsdatum</label>
                                        <input type="date"
                                               class="form-control"
                                               id="beleg_rechnungsdatum"
                                               name="beleg_rechnungsdatum">
                                    </div>
                                    <div class="mb-2">
                                        <label for="beleg_lieferant" class="form-label">Lieferant</label>
                                        <input type="text"
                                               class="form-control"
                                               id="beleg_lieferant"
                                               name="beleg_lieferant"
                                               placeholder="Firma/Person">
                                    </div>
                                    <div class="mb-2">
                                        <label for="beleg_kategorie" class="form-label">Kategorie</label>
                                        <select class="form-control" id="beleg_kategorie" name="beleg_kategorie">
                                            <option value="normal">Normal</option>
                                            <option value="ah_berechtigt">AH² berechtigt</option>
                                            <option value="hv_berechtigt">HV berechtigt</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <!-- Beleg-Vorschau -->
                            <div id="beleg_info" class="mt-3" style="display: none;">
                                <div class="alert alert-info">
                                    <strong>Ausgewählter Beleg:</strong><br>
                                    <span id="beleg_info_text"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-secondary">
                            Abbrechen
                        </a>
                        <button type="submit" class="btn btn-vdst btn-lg">
                            <strong>Buchung erstellen</strong>
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
            // Beleg-Option Handler
            const belegOptions = document.querySelectorAll('input[name="beleg_option"]');
            const belegAuswahlBereich = document.getElementById('beleg_auswahl_bereich');
            const belegUploadBereich = document.getElementById('beleg_upload_bereich');
            const belegMetadaten = document.getElementById('beleg_metadaten');
            const belegInfo = document.getElementById('beleg_info');

            belegOptions.forEach(option => {
                option.addEventListener('change', function() {
                    // Alle Bereiche verstecken
                    belegAuswahlBereich.style.display = 'none';
                    belegUploadBereich.style.display = 'none';
                    belegMetadaten.style.display = 'none';
                    belegInfo.style.display = 'none';

                    // Required-Attribute zurücksetzen
                    document.getElementById('beleg_datei').required = false;
                    document.getElementById('beleg_rechnungsdatum').required = false;

                    if (this.value === 'beleg_waehlen') {
                        belegAuswahlBereich.style.display = 'block';
                    } else if (this.value === 'beleg_upload') {
                        belegUploadBereich.style.display = 'block';
                    }
                });
            });

            // Beleg-Auswahl Handler
            const belegSelect = document.getElementById('beleg_id');
            belegSelect.addEventListener('change', function() {
                if (this.value) {
                    const option = this.options[this.selectedIndex];
                    const betrag = option.dataset.betrag;
                    const beschreibung = option.dataset.beschreibung;

                    // Automatisch Formular ausfüllen
                    document.getElementById('betrag').value = betrag;
                    document.getElementById('beschreibung').value = beschreibung;

                    // Info anzeigen
                    document.getElementById('beleg_info_text').innerHTML =
                        option.text + '<br><small>Betrag und Beschreibung wurden übernommen.</small>';
                    belegInfo.style.display = 'block';
                } else {
                    belegInfo.style.display = 'none';
                }
            });

            // Datei-Upload Handler
            const belegDatei = document.getElementById('beleg_datei');
            belegDatei.addEventListener('change', function() {
                if (this.files[0]) {
                    belegMetadaten.style.display = 'block';
                    // Required setzen für Upload
                    document.getElementById('beleg_rechnungsdatum').required = true;

                    // Rechnungsdatum auf heute setzen falls leer
                    const rechnungsdatum = document.getElementById('beleg_rechnungsdatum');
                    if (!rechnungsdatum.value) {
                        rechnungsdatum.value = new Date().toISOString().split('T')[0];
                    }
                } else {
                    belegMetadaten.style.display = 'none';
                    document.getElementById('beleg_rechnungsdatum').required = false;
                }
            });

            // Buchungsart-Änderung: Betrag-Farbe anpassen
            const buchungsartInputs = document.querySelectorAll('input[name="buchungsart"]');
            const betragInput = document.getElementById('betrag');

            buchungsartInputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value === 'einnahme') {
                        betragInput.classList.remove('border-danger');
                        betragInput.classList.add('border-success');
                    } else {
                        betragInput.classList.remove('border-success');
                        betragInput.classList.add('border-danger');
                    }
                });
            });

            // Initiale Buchungsart setzen
            const checkedBuchungsart = document.querySelector('input[name="buchungsart"]:checked');
            if (checkedBuchungsart) {
                checkedBuchungsart.dispatchEvent(new Event('change'));
            }

            // Betrag-Formatierung erfolgt zentral über js-betrag-format (public/js/app.js)
        });
    </script>
<?= $this->endSection() ?>