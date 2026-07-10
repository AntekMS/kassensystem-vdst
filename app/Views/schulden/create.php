<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Neuer Schulden-Eintrag<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title">Neuer Schulden-Eintrag</h1>
            <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                ← Zurück zur Schuldenliste
            </a>
        </div>

        <form action="<?= base_url('/schulden/store') ?>" method="post">
            <?= csrf_field() ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <strong>Eintrag-Details</strong>
                        </div>
                        <div class="card-body">
                            <!-- Person und Datum -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="person" class="form-label">
                                        <strong>Person</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="person"
                                           name="person"
                                           list="personen-namen"
                                           value="<?= esc(old('person', $vorauswahl_person), 'attr') ?>"
                                           placeholder="Name der Person"
                                           required>
                                    <datalist id="personen-namen">
                                        <?php foreach ($personen_namen as $name): ?>
                                            <option value="<?= esc($name, 'attr') ?>">
                                        <?php endforeach; ?>
                                    </datalist>
                                </div>
                                <div class="col-md-6">
                                    <label for="datum" class="form-label">
                                        <strong>Datum</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="date"
                                           class="form-control"
                                           id="datum"
                                           name="datum"
                                           value="<?= old('datum', date('Y-m-d')) ?>"
                                           required>
                                </div>
                            </div>

                            <!-- Typ und Kategorie -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="typ" class="form-label">
                                        <strong>Typ</strong> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" id="typ" name="typ" required>
                                        <?php foreach (schuld_typ_optionen() as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= old('typ', 'forderung') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted">Forderung = Person schuldet dem Verein,
                                        Verbindlichkeit = Verein schuldet der Person.</small>
                                </div>
                                <div class="col-md-6">
                                    <label for="kategorie" class="form-label">
                                        <strong>Kategorie</strong> <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-control" id="kategorie" name="kategorie" required>
                                        <?php foreach (schuld_kategorie_optionen() as $value => $label): ?>
                                            <option value="<?= $value ?>" <?= old('kategorie', 'getraenke') === $value ? 'selected' : '' ?>>
                                                <?= $label ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Grund und Betrag -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="grund" class="form-label">
                                        <strong>Grund</strong> <span class="text-danger">*</span>
                                    </label>
                                    <input type="text"
                                           class="form-control"
                                           id="grund"
                                           name="grund"
                                           value="<?= esc(old('grund', ''), 'attr') ?>"
                                           placeholder="z.B. Getränkerechnung April 2025"
                                           required>
                                </div>
                                <div class="col-md-6">
                                    <label for="betrag" class="form-label">
                                        <strong>Betrag</strong> <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <input type="number"
                                               class="form-control"
                                               id="betrag"
                                               name="betrag"
                                               step="0.01"
                                               value="<?= old('betrag') ?>"
                                               required>
                                        <span class="input-group-text">€</span>
                                    </div>
                                    <small class="text-muted">Rückzahlungen als negativen Betrag eintragen,
                                        z.B. -10,00.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="row mt-4">
                <div class="col-md-8">
                    <div class="d-flex justify-content-between">
                        <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-secondary">
                            Abbrechen
                        </a>
                        <button type="submit" class="btn btn-vdst btn-lg">
                            <strong>Eintrag speichern</strong>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?= $this->endSection() ?>
