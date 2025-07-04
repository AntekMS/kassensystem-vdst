<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Bearbeiten: <?= $abrechnung['titel'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">HV Abrechnung bearbeiten</h1>
            <div>
                <a href="<?= base_url('/abrechnungen/hv/preview/' . $abrechnung['id']) ?>" class="btn btn-outline-info">
                    👁️ Vorschau
                </a>
                <a href="<?= base_url('/abrechnungen/hv') ?>" class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Bearbeitung-Formular -->
            <div class="col-md-8">
                <form action="<?= base_url('/abrechnungen/hv/update/' . $abrechnung['id']) ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>HV Abrechnungs-Details</strong>
                        </div>
                        <div class="card-body">
                            <!-- Titel -->
                            <div class="mb-3">
                                <label for="titel" class="form-label">
                                    <strong>Titel der Abrechnung</strong> <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control <?= isset($errors['titel']) ? 'is-invalid' : '' ?>"
                                       id="titel"
                                       name="titel"
                                       value="<?= old('titel', $abrechnung['titel']) ?>"
                                       required>
                                <?php if (isset($errors['titel'])): ?>
                                    <div class="invalid-feedback"><?= $errors['titel'] ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- HV-Begründung -->
                            <div class="mb-3">
                                <label for="begruendung" class="form-label">
                                    <strong>Allgemeine Begründung für Heimverein</strong>
                                </label>
                                <textarea class="form-control <?= isset($errors['begruendung']) ? 'is-invalid' : '' ?>"
                                          id="begruendung"
                                          name="begruendung"
                                          rows="6"
                                          placeholder="Begründung warum diese Ausgaben vom Heimverein erstattet werden sollen..."><?= old('begruendung', $abrechnung['begruendung']) ?></textarea>
                                <?php if (isset($errors['begruendung'])): ?>
                                    <div class="invalid-feedback"><?= $errors['begruendung'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    Diese Begründung erscheint in der Excel-Datei. Zusätzlich werden automatisch
                                    spezifische Begründungen pro Beleg generiert.
                                </small>
                            </div>

                            <!-- Notizen -->
                            <div class="mb-3">
                                <label for="notizen" class="form-label">
                                    <strong>Interne Notizen</strong> <small class="text-muted">(optional)</small>
                                </label>
                                <textarea class="form-control"
                                          id="notizen"
                                          name="notizen"
                                          rows="3"
                                          placeholder="Interne Notizen zur Abrechnung..."><?= old('notizen', $abrechnung['notizen']) ?></textarea>
                                <small class="text-muted">
                                    Diese Notizen sind nur intern sichtbar.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="<?= base_url('/abrechnungen/hv/preview/' . $abrechnung['id']) ?>" class="btn btn-outline-secondary">
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

            <!-- Info-Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-warning text-dark">
                        <strong>Abrechnungs-Info</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Monat:</strong></td>
                                <td><?= $abrechnung['abrechnungsmonat'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td>
                                <span class="badge bg-<?= $abrechnung['status'] === 'entwurf' ? 'secondary' : 'warning' ?>">
                                    <?= ucfirst($abrechnung['status']) ?>
                                </span>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Gesamtsumme:</strong></td>
                                <td><strong><?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €</strong></td>
                            </tr>
                            <tr>
                                <td><strong>Erstellt:</strong></td>
                                <td><?= date('d.m.Y', strtotime($abrechnung['erstellt_am'])) ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <strong>HV-Begründungs-Hilfen</strong>
                    </div>
                    <div class="card-body">
                        <h6>Automatische Begründungen:</h6>
                        <ul class="small">
                            <li><strong>Farbe, Streichen:</strong> "Renovierung und Instandhaltung"</li>
                            <li><strong>Regal, Möbel:</strong> "Möblierung der Räume"</li>
                            <li><strong>Werkzeug, Reparatur:</strong> "Wartung und Reparatur"</li>
                            <li><strong>Küche, Geschirr:</strong> "Küchenausstattung"</li>
                            <li><strong>Standard:</strong> "Vereinshaus-Ausgabe"</li>
                        </ul>

                        <hr>

                        <h6>Begründungs-Beispiele:</h6>
                        <div class="small">
                            <p><strong>Renovierung:</strong><br>
                                "Notwendige Renovierungsarbeiten zur Werterhaltung und Verschönerung der Vereinsräume."</p>

                            <p><strong>Möblierung:</strong><br>
                                "Ausstattung der Gemeinschaftsräume für Vereinsaktivitäten und Veranstaltungen."</p>

                            <p><strong>Reparaturen:</strong><br>
                                "Instandsetzung und Wartung der Hausausstattung zur Gewährleistung der Funktionalität."</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Zeichen-Zähler für Begründung
            const begruendungTextarea = document.getElementById('begruendung');
            const maxLength = 1000;

            // Zeichen-Zähler hinzufügen
            const counter = document.createElement('small');
            counter.className = 'text-muted float-end';
            counter.id = 'char-counter';
            begruendungTextarea.parentNode.appendChild(counter);

            function updateCounter() {
                const remaining = maxLength - begruendungTextarea.value.length;
                counter.textContent = `${begruendungTextarea.value.length}/${maxLength} Zeichen`;

                if (remaining < 100) {
                    counter.className = 'text-warning float-end';
                } else if (remaining < 0) {
                    counter.className = 'text-danger float-end';
                } else {
                    counter.className = 'text-muted float-end';
                }
            }

            begruendungTextarea.addEventListener('input', updateCounter);
            updateCounter(); // Initial call
        });
    </script>
<?= $this->endSection() ?>