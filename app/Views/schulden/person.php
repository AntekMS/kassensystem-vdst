<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Schulden: <?= esc($person) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
            <h1 class="page-title mb-0">
                Schulden: <?= esc($person) ?>
                <?php if ($summen['forderungen_getraenke'] >= GETRAENKESTOPP_LIMIT): ?>
                    <span class="badge-status badge-status-rot align-middle"><i class="bi bi-sign-stop-fill" aria-hidden="true"></i> Getränkestopp</span>
                <?php endif; ?>
            </h1>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($summen['forderungen_getraenke'] >= 0.01): ?>
                    <form method="post" class="d-inline"
                          action="<?= base_url('/schulden/getraenke-beglichen') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="person" value="<?= esc($person) ?>">
                        <button type="submit" class="btn btn-outline-success">
                            <i class="bi bi-check2-circle" aria-hidden="true"></i> Getränke beglichen
                        </button>
                    </form>
                <?php elseif ($getraenke_undo): ?>
                    <form method="post" class="d-inline"
                          action="<?= base_url('/schulden/getraenke-beglichen-undo') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="person" value="<?= esc($person) ?>">
                        <button type="submit" class="btn btn-outline-vdst">
                            <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> Beglichen rückgängig
                        </button>
                    </form>
                <?php endif; ?>
                <?php if ($summen['forderungen'] >= 0.01): ?>
                    <a href="<?= base_url('/schulden/person/rechnung?name=' . urlencode($person)) ?>"
                       class="btn btn-outline-vdst" target="_blank" rel="noopener">
                        <i class="bi bi-file-earmark-text" aria-hidden="true"></i> Gesamtrechnung ansehen
                    </a>
                <?php endif; ?>
                <a href="<?= base_url('/schulden/create?person=' . urlencode($person)) ?>" class="btn btn-vdst">
                    <i class="bi bi-plus-lg" aria-hidden="true"></i> Neuer Eintrag
                </a>
                <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück zur Übersicht
                </a>
            </div>
        </div>

        <!-- Personen-Summen -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card h-100">
                    <div class="card-header">Offene Forderungen</div>
                    <div class="card-body text-center">
                        <h3 class="<?= $summen['forderungen'] > 0 ? 'saldo-negativ' : 'saldo-positiv' ?>">
                            <?= number_format($summen['forderungen'], 2, ',', '.') ?> €
                        </h3>
                        <small class="text-muted">davon Getränke:
                            <?= number_format($summen['forderungen_getraenke'], 2, ',', '.') ?> €</small>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card kontostand-card h-100">
                    <div class="card-header">Offene Verbindlichkeiten</div>
                    <div class="card-body text-center">
                        <h3><?= number_format($summen['verbindlichkeiten'], 2, ',', '.') ?> €</h3>
                        <small class="text-muted">schuldet der Verein</small>
                    </div>
                </div>
            </div>
            <?php if (!$ist_institution): ?>
                <!-- E-Mail-Adresse aus dem Personen-Register (Issue #58) -->
                <div class="col-12 col-lg-6">
                    <div class="card h-100">
                        <div class="card-header">
                            <i class="bi bi-envelope" aria-hidden="true"></i> E-Mail-Adresse
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?= base_url('/schulden/person/email') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="person" value="<?= esc($person) ?>">
                                <div class="input-group">
                                    <input type="email" class="form-control" name="email"
                                           aria-label="E-Mail-Adresse"
                                           value="<?= esc(old('email', $register_person['email'] ?? ''), 'attr') ?>"
                                           placeholder="name@example.org">
                                    <button type="submit" class="btn btn-outline-vdst">
                                        <i class="bi bi-check-lg" aria-hidden="true"></i> Speichern
                                    </button>
                                </div>
                            </form>
                            <?php if ($register_person): ?>
                                <small class="text-muted d-block mt-2">
                                    <a href="<?= base_url('/schulden/personen?edit=' . (int) $register_person['id']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i> Im Personen-Register bearbeiten
                                    </a>
                                </small>
                            <?php else: ?>
                                <small class="text-muted d-block mt-2">
                                    Beim Speichern wird die Person im Register angelegt.
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Einträge -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Alle Einträge (<?= count($eintraege) ?>)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($eintraege)): ?>
                    <div class="empty-state">
                        <i class="bi bi-cash-coin" aria-hidden="true"></i>
                        <p>Keine Einträge für diese Person gefunden.</p>
                        <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                            Zurück zur Übersicht
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-stack mb-0">
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
                                    <td data-label="Datum">
                                        <strong><?= date('d.m.Y', strtotime($eintrag['datum'])) ?></strong>
                                    </td>
                                    <td data-label="Typ">
                                        <span class="badge-status <?= $eintrag['typ'] === 'forderung' ? 'badge-status-neutral' : 'badge-status-outline' ?>">
                                            <?= schuld_typ_label($eintrag['typ']) ?>
                                        </span>
                                    </td>
                                    <td data-label="Kategorie"><?= schuld_kategorie_label($eintrag['kategorie']) ?></td>
                                    <td data-label="Grund">
                                        <?= esc($eintrag['grund']) ?>
                                        <?php if (!empty($eintrag['beleg_id'])): ?>
                                            <br><small><a href="<?= base_url('/belege/show/' . $eintrag['beleg_id']) ?>"><i class="bi bi-receipt" aria-hidden="true"></i> Zum Beleg</a></small>
                                        <?php elseif (!empty($eintrag['buchung_id'])): ?>
                                            <br><small class="text-muted"><i class="bi bi-journal-text" aria-hidden="true"></i> Aus Buchung</small>
                                        <?php elseif (!empty($eintrag['abrechnung_id'])): ?>
                                            <br><small><a href="<?= base_url('/abrechnungen/' . $eintrag['abrechnung_typ'] . '/preview/' . $eintrag['abrechnung_id']) ?>"><i class="bi bi-clipboard-data" aria-hidden="true"></i> Zur Abrechnung</a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Betrag" class="text-end">
                                        <strong class="<?= $eintrag['betrag'] < 0 ? 'text-success' : '' ?>">
                                            <?= number_format($eintrag['betrag'], 2, ',', '.') ?> €
                                        </strong>
                                        <?php if ($eintrag['betrag'] < 0): ?>
                                            <br><small class="text-success">Rückzahlung</small>
                                        <?php endif; ?>
                                    </td>
                                    <?php $zeigeRechnung = $eintrag['typ'] === 'forderung' && $eintrag['betrag'] > 0; ?>
                                    <td data-label="Aktion" class="text-center <?= (\App\Models\SchuldModel::istAutomatisch($eintrag) && !$zeigeRechnung) ? '' : 'stack-actions' ?>">
                                        <?php if ($zeigeRechnung): ?>
                                            <a href="<?= base_url('/schulden/rechnung?id=' . $eintrag['id']) ?>"
                                               class="btn-icon" target="_blank" rel="noopener"
                                               title="Rechnung ansehen" aria-label="Rechnung für diesen Eintrag ansehen">
                                                <i class="bi bi-file-earmark-text" aria-hidden="true"></i>
                                                <span class="d-lg-none">Rechnung</span>
                                            </a>
                                        <?php endif; ?>
                                        <?php if (\App\Models\SchuldModel::istAutomatisch($eintrag)): ?>
                                            <span class="badge-status badge-status-outline" title="Wird über Beleg/Buchung/Abrechnung verwaltet">
                                                automatisch
                                            </span>
                                        <?php else: ?>
                                            <a href="<?= base_url('/schulden/edit/' . $eintrag['id']) ?>"
                                               class="btn-icon" title="Bearbeiten" aria-label="Eintrag bearbeiten">
                                                <i class="bi bi-pencil" aria-hidden="true"></i>
                                                <span class="d-lg-none">Bearbeiten</span>
                                            </a>
                                            <form method="post" class="d-inline stack-form"
                                                  action="<?= base_url('/schulden/delete/' . $eintrag['id']) ?>"
                                                  onsubmit="return confirmDelete('Eintrag wirklich löschen?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-icon btn-icon-danger" title="Löschen" aria-label="Eintrag löschen">
                                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                                    <span class="d-lg-none">Löschen</span>
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
    </div>
<?= $this->endSection() ?>
