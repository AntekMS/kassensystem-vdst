<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Personen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="page-title mb-1">Personen</h1>
                        <p class="text-muted mb-0">Register für Vor-/Nachname und E-Mail (Grundlage für Schulden &amp; Rechnungsversand)</p>
                    </div>
                    <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Zur Schuldenliste
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Linke Spalte: Person anlegen/bearbeiten -->
            <div class="col-lg-4 mb-4">
                <div class="card card-vdst">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-person-plus" aria-hidden="true"></i>
                            <?= $bearbeiten ? 'Person bearbeiten' : 'Person anlegen' ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('/schulden/personen/store') ?>" method="post">
                            <?= csrf_field() ?>
                            <?php if ($bearbeiten): ?>
                                <input type="hidden" name="id" value="<?= (int) $bearbeiten['id'] ?>">
                            <?php endif; ?>
                            <div class="mb-3">
                                <label for="vorname" class="form-label">Vorname</label>
                                <input type="text" class="form-control" id="vorname" name="vorname"
                                       value="<?= esc(old('vorname', $bearbeiten['vorname'] ?? ''), 'attr') ?>">
                            </div>
                            <div class="mb-3">
                                <label for="nachname" class="form-label">Nachname</label>
                                <input type="text" class="form-control" id="nachname" name="nachname"
                                       value="<?= esc(old('nachname', $bearbeiten['nachname'] ?? ''), 'attr') ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">E-Mail-Adresse</label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?= esc(old('email', $bearbeiten['email'] ?? ''), 'attr') ?>" placeholder="name@example.org">
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="aktiv" name="aktiv" value="1"
                                       <?= old('aktiv', $bearbeiten['aktiv'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="aktiv">Aktiv (in Vorschlagslisten anzeigen)</label>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <?php if ($bearbeiten): ?>
                                    <a href="<?= base_url('/schulden/personen') ?>" class="btn btn-outline-vdst">Abbrechen</a>
                                <?php endif; ?>
                                <button type="submit" class="btn btn-vdst">
                                    <i class="bi bi-save" aria-hidden="true"></i> Speichern
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Rechte Spalte: vorhandene Personen -->
            <div class="col-lg-8 mb-4">
                <div class="card card-vdst">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-people" aria-hidden="true"></i> Personen (<?= count($eintraege) ?>)</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if ($eintraege === []): ?>
                            <div class="empty-state">
                                <i class="bi bi-people" aria-hidden="true"></i>
                                <p>Noch keine Personen angelegt.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover table-vdst table-stack mb-0">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>E-Mail-Adresse</th>
                                            <th class="text-center">Status</th>
                                            <th class="text-center">Aktion</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($eintraege as $eintrag): ?>
                                            <tr>
                                                <td data-label="Name"><strong><?= esc(\App\Models\PersonModel::anzeigename($eintrag)) ?></strong></td>
                                                <td data-label="E-Mail-Adresse"><?= $eintrag['email'] ? esc($eintrag['email']) : '<span class="text-muted">—</span>' ?></td>
                                                <td data-label="Status" class="text-center">
                                                    <?php if ($eintrag['aktiv']): ?>
                                                        <span class="badge-status badge-status-gruen">Aktiv</span>
                                                    <?php else: ?>
                                                        <span class="badge-status badge-status-neutral">Inaktiv</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Aktion" class="text-center stack-actions">
                                                    <a href="<?= base_url('/schulden/personen?edit=' . (int) $eintrag['id']) ?>"
                                                       class="btn-icon" title="Bearbeiten" aria-label="Person bearbeiten">
                                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                                        <span class="d-lg-none">Bearbeiten</span>
                                                    </a>
                                                    <form method="post" class="d-inline stack-form"
                                                          action="<?= base_url('/schulden/personen/delete/' . (int) $eintrag['id']) ?>"
                                                          onsubmit="return confirmDelete('Person <?= esc(\App\Models\PersonModel::anzeigename($eintrag), 'js') ?> wirklich löschen?')">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="btn-icon btn-icon-danger" title="Löschen" aria-label="Person löschen">
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
