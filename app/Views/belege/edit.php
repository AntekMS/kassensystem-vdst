<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Beleg bearbeiten: <?= $beleg['belegnummer'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Beleg bearbeiten: <?= $beleg['belegnummer'] ?></h1>
            <div>
                <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>" class="btn btn-outline-vdst">
                    👁️ Ansicht
                </a>
                <a href="<?= base_url('/belege') ?>" class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>
            </div>
        </div>

        <form action="<?= base_url('/belege/update/' . $beleg['id']) ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <!-- Beleg-Metadaten -->
                <div class="col-md-8">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Beleg-Informationen</strong>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info">
                                <strong>Hinweis:</strong> Bei Änderung des Rechnungsdatums wird automatisch eine neue
                                Belegnummer generiert und die Datei entsprechend umbenannt.
                            </div>

                            <!-- Rechnungsdatum -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="rechnungsdatum" class="form-label">
                                        <strong>Rechnungsdatum</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="date"
                                           class="form-control <?= isset($errors['rechnungsdatum']) ? 'is-invalid' : '' ?>"
                                           id="rechnungsdatum"
                                           name="rechnungsdatum"
                                           value="<?= old('rechnungsdatum', $beleg['rechnungsdatum']) ?>"
                                           required>
                                    <?php if (isset($errors['rechnungsdatum'])): ?>
                                        <div class="invalid-feedback"><?= $errors['rechnungsdatum'] ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Aktuell: <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?>
                                    </small>
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
                                               value="<?= old('betrag', $beleg['betrag']) ?>"
                                               required>
                                        <span class="input-group-text">€</span>
                                        <?php if (isset($errors['betrag'])): ?>
                                            <div class="invalid-feedback"><?= $errors['betrag'] ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Lieferant und Kategorie -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="lieferant" class="form-label">
                                        <strong>Lieferant/Firma</strong>
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="lieferant"
                                           name="lieferant"
                                           value="<?= old('lieferant', $beleg['lieferant']) ?>"
                                           placeholder="Name der Firma oder Person">
                                </div>
                                <div class="col-md-6">
                                    <label for="kategorie" class="form-label">
                                        <strong>Kategorie</strong> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control <?= isset($errors['kategorie']) ? 'is-invalid' : '' ?>"
                                            id="kategorie"
                                            name="kategorie"
                                            required>
                                        <?php foreach($kategorien as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= old('kategorie', $beleg['kategorie']) === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($errors['kategorie'])): ?>
                                        <div class="invalid-feedback"><?= $errors['kategorie'] ?></div>
                                    <?php endif; ?>
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
                                          required><?= old('beschreibung', $beleg['beschreibung']) ?></textarea>
                                <?php if (isset($errors['beschreibung'])): ?>
                                    <div class="invalid-feedback"><?= $errors['beschreibung'] ?></div>
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
                                          rows="2"><?= old('notizen', $beleg['notizen']) ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Beleg-Info und Datei -->
                <div class="col-md-4">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Aktuelle Daten</strong>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tr>
                                    <td><strong>Belegnummer:</strong></td>
                                    <td><?= $beleg['belegnummer'] ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Eingabedatum:</strong></td>
                                    <td><?= date('d.m.Y', strtotime($beleg['eingabedatum'])) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Status:</strong></td>
                                    <td>
                                    <span class="badge bg-secondary">
                                        <?= ucfirst($beleg['status']) ?>
                                    </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td><strong>Dateityp:</strong></td>
                                    <td>
                                    <span class="badge bg-dark">
                                        <?= strtoupper($beleg['dateityp']) ?>
                                    </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <div class="card mt-3">
                        <div class="card-header bg-dark text-white">
                            <strong>Datei-Vorschau</strong>
                        </div>
                        <div class="card-body p-1">
                            <?php if ($beleg['dateityp'] === 'pdf'): ?>
                                <div class="text-center p-2">
                                    <p class="mb-2">📄 PDF-Dokument</p>
                                    <a href="<?= base_url('/belege/preview/' . $beleg['id']) ?>"
                                       target="_blank" class="btn btn-outline-dark btn-sm">
                                        PDF öffnen
                                    </a>
                                </div>
                            <?php else: ?>
                                <img src="<?= base_url('/belege/preview/' . $beleg['id']) ?>"
                                     alt="Beleg-Vorschau"
                                     class="img-fluid"
                                     style="max-height: 200px; width: 100%; object-fit: contain;">
                            <?php endif; ?>
                        </div>
                        <div class="card-footer text-center">
                            <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                               class="btn btn-outline-success btn-sm">
                                📥 Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>" class="btn btn-outline-secondary">
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
            // Warnung bei Rechnungsdatum-Änderung
            const originalDatum = '<?= $beleg['rechnungsdatum'] ?>';
            const datumInput = document.getElementById('rechnungsdatum');

            datumInput.addEventListener('change', function() {
                if (this.value !== originalDatum) {
                    if (!document.getElementById('datum-warnung')) {
                        const warnung = document.createElement('div');
                        warnung.id = 'datum-warnung';
                        warnung.className = 'alert alert-warning mt-2';
                        warnung.innerHTML = '<strong>Achtung:</strong> Bei Änderung des Rechnungsdatums wird eine neue Belegnummer generiert!';
                        this.parentNode.appendChild(warnung);
                    }
                } else {
                    const warnung = document.getElementById('datum-warnung');
                    if (warnung) {
                        warnung.remove();
                    }
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
    </script>
<?= $this->endSection() ?>