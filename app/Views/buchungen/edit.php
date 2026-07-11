<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Buchung bearbeiten<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Buchung bearbeiten</h1>
            <a href="<?= base_url('/buchungen') ?>" class="btn btn-outline-vdst">
                ← Zurück zum Kassenbuch
            </a>
        </div>

        <form action="<?= base_url('/buchungen/update/' . $buchung['id']) ?>" method="post">
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
                                           value="<?= old('buchungsdatum', $buchung['buchungsdatum']) ?>"
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
                                            <option value="<?= $value ?>" <?= old('konto_typ', $buchung['konto_typ']) === $value ? 'selected' : '' ?>>
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
                                               value="ausgabe" <?= old('buchungsart', $buchung['buchungsart']) === 'ausgabe' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-danger" for="ausgabe">
                                            📉 Ausgabe
                                        </label>

                                        <input type="radio" class="btn-check" name="buchungsart" id="einnahme"
                                               value="einnahme" <?= old('buchungsart', $buchung['buchungsart']) === 'einnahme' ? 'checked' : '' ?>>
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
                                               class="form-control <?= isset($errors['betrag']) ? 'is-invalid' : '' ?>"
                                               id="betrag"
                                               name="betrag"
                                               step="0.01"
                                               min="0.01"
                                               value="<?= old('betrag', $buchung['betrag']) ?>"
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
                                          required><?= esc(old('beschreibung', $buchung['beschreibung'])) ?></textarea>
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
                                          rows="2"><?= esc(old('notizen', $buchung['notizen'] ?? '')) ?></textarea>
                            </div>

                            <?php if (!empty($schuld_eintrag)): ?>
                                <div class="alert alert-info mb-3">
                                    Diese Buchung gleicht eine Schuld von
                                    <a href="<?= base_url('/schulden/person?name=' . urlencode($schuld_eintrag['person'])) ?>">
                                        <strong><?= esc($schuld_eintrag['person']) ?></strong>
                                    </a> aus.
                                    Betrag und Datum des Schulden-Eintrags werden beim Speichern automatisch angepasst;
                                    beim Löschen der Buchung wird er entfernt.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Beleg-Bereich -->
                <div class="col-md-4">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Verknüpfter Beleg</strong>
                        </div>
                        <div class="card-body">
                            <!-- Aktueller Beleg -->
                            <?php if (!empty($buchung['beleg_id'])): ?>
                                <div class="alert alert-info">
                                    <strong>Aktueller Beleg:</strong><br>
                                    <a href="<?= base_url('/belege/show/' . $buchung['beleg_id']) ?>" target="_blank">
                                        <?= esc($buchung['belegnummer'] ?? 'Beleg #' . $buchung['beleg_id']) ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-secondary">
                                    <strong>Kein Beleg verknüpft</strong>
                                </div>
                            <?php endif; ?>

                            <!-- Beleg ändern -->
                            <div class="mb-3">
                                <label for="beleg_id" class="form-label">Beleg ändern</label>
                                <select class="form-control" id="beleg_id" name="beleg_id">
                                    <option value="">Kein Beleg</option>
                                    <?php // Der aktuell verknüpfte Beleg fehlt in $verfuegbare_belege (nur Belege ohne
                                          // Buchung) — ohne diese Option würde Speichern die Verknüpfung lösen. ?>
                                    <?php if (!empty($buchung['beleg_id'])): ?>
                                        <option value="<?= $buchung['beleg_id'] ?>"
                                            <?= old('beleg_id', $buchung['beleg_id']) == $buchung['beleg_id'] ? 'selected' : '' ?>>
                                            Aktuellen Beleg beibehalten
                                        </option>
                                    <?php endif; ?>
                                    <?php foreach($verfuegbare_belege as $beleg): ?>
                                        <option value="<?= $beleg['id'] ?>"
                                            <?= old('beleg_id', $buchung['beleg_id']) == $beleg['id'] ? 'selected' : '' ?>>
                                            <?= esc($beleg['belegnummer']) ?> - <?= esc(substr($beleg['beschreibung'], 0, 30)) ?>
                                            (<?= number_format($beleg['betrag'], 2, ',', '.') ?> €)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">
                                    Nur verfügbare Belege werden angezeigt.
                                </small>
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
                            <strong>Änderungen speichern</strong>
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
        });
    </script>
<?= $this->endSection() ?>