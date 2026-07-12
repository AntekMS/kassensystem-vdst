<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>E-Mail-Adressen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="page-title mb-1">E-Mail-Adressen</h1>
                        <p class="text-muted mb-0">Zuordnung Person → E-Mail für den Rechnungsversand</p>
                    </div>
                    <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zur Schuldenliste
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Linke Spalte: Adresse anlegen/ändern -->
            <div class="col-lg-4 mb-4">
                <div class="card card-vdst">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-envelope-plus" aria-hidden="true"></i> Adresse speichern</h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('/schulden/emails/store') ?>" method="post">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="name" class="form-label">Person</label>
                                <input type="text" class="form-control" id="name" name="name"
                                       value="<?= esc(old('name', '')) ?>" list="personenListe" required>
                                <datalist id="personenListe">
                                    <?php foreach ($personen_namen as $name): ?>
                                        <option value="<?= esc($name) ?>">
                                    <?php endforeach; ?>
                                </datalist>
                                <div class="form-text">Genau so schreiben wie in der Schuldenliste.</div>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-Mail-Adresse</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= esc(old('email', '')) ?>" placeholder="name@example.org" required>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-vdst">
                                    <i class="bi bi-save" aria-hidden="true"></i> Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Rechte Spalte: vorhandene Zuordnungen -->
            <div class="col-lg-8 mb-4">
                <div class="card card-vdst">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-envelope-at" aria-hidden="true"></i> Gespeicherte Adressen (<?= count($eintraege) ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($eintraege === []): ?>
                            <div class="empty-state">
                                <i class="bi bi-envelope" aria-hidden="true"></i>
                                <p>Noch keine E-Mail-Adressen gespeichert.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-vdst table-stack mb-0">
                                    <thead>
                                        <tr>
                                            <th>Person</th>
                                            <th>E-Mail-Adresse</th>
                                            <th class="text-center">Aktion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eintraege as $eintrag): ?>
                                            <tr>
                                                <td data-label="Person"><strong><?= esc($eintrag['name']) ?></strong></td>
                                                <td data-label="E-Mail-Adresse"><?= esc($eintrag['email']) ?></td>
                                                <td data-label="Aktion" class="text-center stack-actions">
                                                    <form method="post" class="d-inline stack-form"
                                                          action="<?= base_url('/schulden/emails/delete/' . $eintrag['id']) ?>"
                                                          onsubmit="return confirmDelete('E-Mail-Adresse von <?= esc($eintrag['name'], 'js') ?> wirklich löschen?')">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn-icon btn-icon-danger" title="Löschen" aria-label="E-Mail-Adresse löschen">
                                                            <i class="bi bi-trash" aria-hidden="true"></i>
                                                            <span class="d-lg-none">Löschen</span>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
