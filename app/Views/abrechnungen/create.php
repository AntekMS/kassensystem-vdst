<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Neue <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Neue <?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung erstellen</h1>
            <a href="<?= base_url('/abrechnungen/' . $typ) ?>" class="btn btn-outline-vdst">
                ← Zurück zur Übersicht
            </a>
        </div>

        <div class="row">
            <!-- Formular -->
            <div class="col-md-8">
                <form action="<?= base_url('/abrechnungen/' . $typ . '/store') ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungs-Details</strong>
                        </div>
                        <div class="card-body">
                            <!-- Abrechnungsmonat -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="abrechnungsmonat" class="form-label">
                                        <strong>Abrechnungsmonat</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="month"
                                           class="form-control <?= isset($errors['abrechnungsmonat']) ? 'is-invalid' : '' ?>"
                                           id="abrechnungsmonat"
                                           name="abrechnungsmonat"
                                           value="<?= old('abrechnungsmonat', $aktueller_monat) ?>"
                                           required>
                                    <?php if (isset($errors['abrechnungsmonat'])): ?>
                                        <div class="invalid-feedback"><?= $errors['abrechnungsmonat'] ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Der Monat für den die Abrechnung erstellt wird.
                                    </small>
                                </div>
                            </div>

                            <!-- Titel -->
                            <div class="mb-3">
                                <label for="titel" class="form-label">
                                    <strong>Titel der Abrechnung</strong> <span class="text-danger">*</span>
                                </label>
                                <input type="text"
                                       class="form-control <?= isset($errors['titel']) ? 'is-invalid' : '' ?>"
                                       id="titel"
                                       name="titel"
                                       value="<?= old('titel') ?>"
                                       placeholder="<?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung [Monat] [Jahr]"
                                       required>
                                <?php if (isset($errors['titel'])): ?>
                                    <div class="invalid-feedback"><?= $errors['titel'] ?></div>
                                <?php endif; ?>
                                <small class="text-muted">
                                    Wird automatisch generiert, falls leer gelassen.
                                </small>
                            </div>

                            <?php if ($typ === 'hv'): ?>
                                <!-- HV-Begründung -->
                                <div class="mb-3">
                                    <label for="begruendung" class="form-label">
                                        <strong>Allgemeine Begründung für Heimverein</strong>
                                    </label>
                                    <textarea class="form-control <?= isset($errors['begruendung']) ? 'is-invalid' : '' ?>"
                                              id="begruendung"
                                              name="begruendung"
                                              rows="4"
                                              placeholder="Begründung warum diese Ausgaben vom Heimverein erstattet werden sollen..."><?= old('begruendung') ?></textarea>
                                    <?php if (isset($errors['begruendung'])): ?>
                                        <div class="invalid-feedback"><?= $errors['begruendung'] ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Diese Begründung erscheint in der Excel-Datei. Zusätzlich werden automatisch
                                        spezifische Begründungen pro Beleg generiert.
                                    </small>
                                </div>
                            <?php endif; ?>

                            <!-- Notizen -->
                            <div class="mb-3">
                                <label for="notizen" class="form-label">
                                    <strong>Interne Notizen</strong> <small class="text-muted">(optional)</small>
                                </label>
                                <textarea class="form-control"
                                          id="notizen"
                                          name="notizen"
                                          rows="3"
                                          placeholder="Interne Notizen zur Abrechnung (erscheinen nicht im Export)..."><?= old('notizen') ?></textarea>
                                <small class="text-muted">
                                    Diese Notizen sind nur intern sichtbar und erscheinen nicht in der Excel-Datei.
                                </small>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="d-flex justify-content-between">
                                <a href="<?= base_url('/abrechnungen/' . $typ) ?>" class="btn btn-outline-secondary">
                                    Abbrechen
                                </a>
                                <button type="submit" class="btn btn-vdst btn-lg">
                                    <strong>Abrechnung erstellen</strong>
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Info-Sidebar -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-<?= $typ === 'ah' ? 'info' : 'warning' ?> text-<?= $typ === 'ah' ? 'white' : 'dark' ?>">
                        <strong><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung - Info</strong>
                    </div>
                    <div class="card-body">
                        <?php if ($typ === 'ah'): ?>
                            <h6>AH² Abrechnungen:</h6>
                            <ul class="small">
                                <li>Für Veranstaltungskosten und ähnliche Ausgaben</li>
                                <li>Werden beim AH²-Bund eingereicht</li>
                                <li>Nur Belege mit Kategorie "AH² berechtigt" können ausgewählt werden</li>
                                <li>Budget-basierte Erstattung</li>
                            </ul>
                        <?php else: ?>
                            <h6>Heimverein Abrechnungen:</h6>
                            <ul class="small">
                                <li>Für Haus-bezogene Ausgaben (Renovierung, Möbel, etc.)</li>
                                <li>Werden beim Heimverein eingereicht</li>
                                <li>Nur Belege mit Kategorie "HV berechtigt" können ausgewählt werden</li>
                                <li>Benötigen detaillierte Begründungen</li>
                            </ul>
                        <?php endif; ?>

                        <hr>

                        <h6>Verfügbare Belege:</h6>
                        <div class="alert alert-info">
                            <strong><?= $verfuegbare_belege_count ?></strong> Belege verfügbar
                            <br>
                            <small>
                                Nur Belege mit Status "erfasst" und passender Kategorie werden angezeigt.
                            </small>
                        </div>

                        <h6>Nächste Schritte:</h6>
                        <ol class="small">
                            <li><strong>Abrechnung erstellen</strong></li>
                            <li><strong>Belege auswählen</strong> (Herzstück der Bearbeitung)</li>
                            <li><strong>Vorschau prüfen</strong></li>
                            <li><strong>Excel-Export herunterladen</strong></li>
                            <li>Status auf "Ausstehend" setzen und einreichen</li>
                        </ol>

                        <div class="alert alert-info mt-3">
                            <strong>💡 Tipp:</strong> Nach dem Erstellen kannst du über "📄 Belege" die gewünschten
                            Belege für diese Abrechnung auswählen.
                        </div>
                    </div>
                </div>

                <!-- Quick-Statistiken -->
                <div class="card mt-3">
                    <div class="card-header bg-dark text-white">
                        <strong>Aktueller Status</strong>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h4 class="text-secondary"><?= $stats['entwuerfe'] ?? 0 ?></h4>
                                <small class="text-muted">Entwürfe</small>
                            </div>
                            <div class="col-6">
                                <h4 class="text-warning"><?= $stats['ausstehend'] ?? 0 ?></h4>
                                <small class="text-muted">Ausstehend</small>
                            </div>
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
            // Auto-Titel generieren
            const monatInput = document.getElementById('abrechnungsmonat');
            const titelInput = document.getElementById('titel');

            monatInput.addEventListener('change', function() {
                if (!titelInput.value || titelInput.value === titelInput.placeholder) {
                    const datum = new Date(this.value + '-01');
                    const optionen = { year: 'numeric', month: 'long' };
                    const monatJahr = datum.toLocaleDateString('de-DE', optionen);

                    const neuerTitel = '<?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung ' +
                        monatJahr.charAt(0).toUpperCase() + monatJahr.slice(1);

                    titelInput.value = neuerTitel;
                }
            });

            // Initial trigger falls Monat bereits ausgewählt
            if (monatInput.value && !titelInput.value) {
                monatInput.dispatchEvent(new Event('change'));
            }
        });
    </script>
<?= $this->endSection() ?>