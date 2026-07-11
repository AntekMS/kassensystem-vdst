<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Schuldenliste<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">Schuldenliste</h1>

        <!-- Summen-Übersicht -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card h-100">
                    <div class="card-header">Offene Forderungen</div>
                    <div class="card-body text-center">
                        <h3 class="<?= $summe_forderungen > 0 ? 'saldo-negativ' : 'saldo-positiv' ?>">
                            <?= number_format($summe_forderungen, 2, ',', '.') ?> €
                        </h3>
                        <small class="text-muted">schulden dem Verein</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card h-100">
                    <div class="card-header">Offene Verbindlichkeiten</div>
                    <div class="card-body text-center">
                        <h3 class="<?= $summe_verbindlichkeiten > 0 ? 'saldo-negativ' : 'saldo-positiv' ?>">
                            <?= number_format($summe_verbindlichkeiten, 2, ',', '.') ?> €
                        </h3>
                        <small class="text-muted">schuldet der Verein</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <a href="<?= base_url('/schulden/create') ?>" class="btn btn-vdst">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Neuer Eintrag
            </a>
            <a href="<?= base_url('/inventur') ?>" class="btn btn-outline-vdst">
                <i class="bi bi-calculator" aria-hidden="true"></i> Zur Inventur
            </a>
        </div>

        <!-- Personen-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Schulden pro Person (<?= count($personen) ?> Personen)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($personen)): ?>
                    <div class="empty-state">
                        <i class="bi bi-cash-coin" aria-hidden="true"></i>
                        <p>Noch keine Schulden erfasst.</p>
                        <a href="<?= base_url('/schulden/create') ?>" class="btn btn-vdst">
                            Ersten Eintrag erstellen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-stack mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Person</th>
                                <th class="text-end">Getränke</th>
                                <th class="text-end">Forderungen gesamt</th>
                                <th class="text-end">Verbindlichkeiten</th>
                                <th class="text-center d-none d-xl-table-cell">Einträge</th>
                                <th class="d-none d-xl-table-cell">Letzter Eintrag</th>
                                <th class="text-center">Status</th>
                                <th class="text-center">Aktion</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($personen as $p): ?>
                                <tr>
                                    <td data-label="Person">
                                        <a href="<?= base_url('/schulden/person?name=' . urlencode($p['person'])) ?>"
                                           class="text-decoration-none">
                                            <strong><?= esc($p['person']) ?></strong>
                                        </a>
                                    </td>
                                    <td data-label="Getränke" class="text-end <?= $p['forderungen_getraenke'] < 0 ? 'text-success' : '' ?>">
                                        <?= number_format($p['forderungen_getraenke'], 2, ',', '.') ?> €
                                    </td>
                                    <td data-label="Forderungen" class="text-end">
                                        <strong class="<?= $p['forderungen_gesamt'] > 0 ? 'text-danger' : 'text-success' ?>">
                                            <?= number_format($p['forderungen_gesamt'], 2, ',', '.') ?> €
                                        </strong>
                                    </td>
                                    <td data-label="Verbindlichkeiten" class="text-end <?= $p['verbindlichkeiten_gesamt'] == 0 ? 'stack-leer' : '' ?>">
                                        <?= number_format($p['verbindlichkeiten_gesamt'], 2, ',', '.') ?> €
                                    </td>
                                    <td data-label="Einträge" class="text-center d-none d-xl-table-cell"><?= $p['anzahl'] ?></td>
                                    <td data-label="Letzter Eintrag" class="d-none d-xl-table-cell"><?= date('d.m.Y', strtotime($p['letzter_eintrag'])) ?></td>
                                    <td data-label="Status" class="text-center <?= $p['forderungen_getraenke'] >= GETRAENKESTOPP_LIMIT ? '' : 'stack-leer' ?>">
                                        <?php if ($p['forderungen_getraenke'] >= GETRAENKESTOPP_LIMIT): ?>
                                            <span class="badge-status badge-status-rot"><i class="bi bi-sign-stop-fill" aria-hidden="true"></i> Getränkestopp</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center stack-actions <?= ($p['forderungen_getraenke'] >= 0.01 || $p['getraenke_undo']) ? '' : 'stack-leer' ?>">
                                        <?php if ($p['forderungen_getraenke'] >= 0.01): ?>
                                            <form method="post" class="d-inline stack-form"
                                                  action="<?= base_url('/schulden/getraenke-beglichen') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="person" value="<?= esc($p['person']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                    <i class="bi bi-check2-circle" aria-hidden="true"></i> Beglichen
                                                </button>
                                            </form>
                                        <?php elseif ($p['getraenke_undo']): ?>
                                            <form method="post" class="d-inline stack-form"
                                                  action="<?= base_url('/schulden/getraenke-beglichen-undo') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="person" value="<?= esc($p['person']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-vdst">
                                                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Rückgängig
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <p class="text-muted mt-2">
            <small>Ab <?= number_format(GETRAENKESTOPP_LIMIT, 2, ',', '.') ?> € Getränke-Schulden gilt ein Getränkeausgabestopp.
            Rückzahlungen als negativen Betrag erfassen.</small>
        </p>
    </div>
<?= $this->endSection() ?>
