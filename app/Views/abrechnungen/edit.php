<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Bearbeiten: <?= esc($abrechnung['titel']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title"><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnung bearbeiten</h1>
            <div>
                <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>" class="btn btn-outline-info">
                    👁️ Vorschau
                </a>
                <a href="<?= base_url('/abrechnungen/' . $typ) ?>" class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Bearbeitung-Formular -->
            <div class="col-md-8">
                <form action="<?= base_url('/abrechnungen/' . $typ . '/update/' . $abrechnung['id']) ?>" method="post">
                    <?= csrf_field() ?>

                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong><?= $typ === 'ah' ? 'AH²' : 'HV' ?> Abrechnungs-Details</strong>
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
                                       value="<?= esc(old('titel', $abrechnung['titel']), 'attr') ?>"
                                       required>
                                <?php if (isset($errors['titel'])): ?>
                                    <div class="invalid-feedback"><?= esc($errors['titel']) ?></div>
                                <?php endif; ?>
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
                                              rows="6"
                                              maxlength="1000"
                                              placeholder="Begründung warum diese Ausgaben vom Heimverein erstattet werden sollen..."><?= esc(old('begruendung', $abrechnung['begruendung'] ?? '')) ?></textarea>
                                    <?php if (isset($errors['begruendung'])): ?>
                                        <div class="invalid-feedback"><?= esc($errors['begruendung']) ?></div>
                                    <?php endif; ?>
                                    <small class="text-muted">
                                        Diese Begründung erscheint in der Excel-Datei (max. 1000 Zeichen).
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
                                          placeholder="Interne Notizen zur Abrechnung..."><?= esc(old('notizen', $abrechnung['notizen'] ?? '')) ?></textarea>
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
                                <a href="<?= base_url('/abrechnungen/' . $typ . '/preview/' . $abrechnung['id']) ?>" class="btn btn-outline-secondary">
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
                                    <?= abrechnung_status_label($abrechnung['status']) ?>
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
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
