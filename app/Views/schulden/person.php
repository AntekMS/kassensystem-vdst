<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Schulden: <?= esc($person) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="page-title mb-0">
                Schulden: <?= esc($person) ?>
                <?php if ($summen['forderungen_getraenke'] >= GETRAENKESTOPP_LIMIT): ?>
                    <span class="badge bg-danger align-middle">🛑 Getränkestopp</span>
                <?php endif; ?>
            </h1>
            <div>
                <a href="<?= base_url('/schulden/create?person=' . urlencode($person)) ?>" class="btn btn-vdst">
                    <strong>+ Neuer Eintrag</strong>
                </a>
                <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>
            </div>
        </div>

        <!-- Personen-Summen -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card kontostand-card">
                    <div class="card-header">Offene Forderungen</div>
                    <div class="card-body text-center">
                        <h3 class="<?= $summen['forderungen'] > 0 ? 'text-danger' : 'text-success' ?>">
                            <?= number_format($summen['forderungen'], 2, ',', '.') ?> €
                        </h3>
                        <small class="text-muted">davon Getränke:
                            <?= number_format($summen['forderungen_getraenke'], 2, ',', '.') ?> €</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card kontostand-card">
                    <div class="card-header">Offene Verbindlichkeiten</div>
                    <div class="card-body text-center">
                        <h3><?= number_format($summen['verbindlichkeiten'], 2, ',', '.') ?> €</h3>
                        <small class="text-muted">schuldet der Verein</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Einträge -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Alle Einträge (<?= count($eintraege) ?>)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($eintraege)): ?>
                    <div class="text-center p-4">
                        <p class="text-muted">Keine Einträge für diese Person gefunden.</p>
                        <a href="<?= base_url('/schulden') ?>" class="btn btn-vdst">
                            Zurück zur Übersicht
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Datum</th>
                                <th>Typ</th>
                                <th>Kategorie</th>
                                <th>Grund</th>
                                <th class="text-end">Betrag</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($eintraege as $eintrag): ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d.m.Y', strtotime($eintrag['datum'])) ?></strong>
                                    </td>
                                    <td>
                                        <span class="badge <?= $eintrag['typ'] === 'forderung' ? 'bg-secondary' : 'bg-dark' ?>">
                                            <?= schuld_typ_label($eintrag['typ']) ?>
                                        </span>
                                    </td>
                                    <td><?= schuld_kategorie_label($eintrag['kategorie']) ?></td>
                                    <td><?= esc($eintrag['grund']) ?></td>
                                    <td class="text-end">
                                        <strong class="<?= $eintrag['betrag'] < 0 ? 'text-success' : '' ?>">
                                            <?= number_format($eintrag['betrag'], 2, ',', '.') ?> €
                                        </strong>
                                        <?php if ($eintrag['betrag'] < 0): ?>
                                            <br><small class="text-success">Rückzahlung</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('/schulden/edit/' . $eintrag['id']) ?>"
                                               class="btn btn-outline-dark" title="Bearbeiten" aria-label="Eintrag bearbeiten">
                                                <span aria-hidden="true">✏️</span>
                                            </a>
                                            <form method="post" class="d-inline"
                                                  action="<?= base_url('/schulden/delete/' . $eintrag['id']) ?>"
                                                  onsubmit="return confirmDelete('Eintrag wirklich löschen?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-outline-danger" title="Löschen" aria-label="Eintrag löschen">
                                                    <span aria-hidden="true">🗑️</span>
                                                </button>
                                            </form>
                                        </div>
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
<?= $this->endSection() ?>
