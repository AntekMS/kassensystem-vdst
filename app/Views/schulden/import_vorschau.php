<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Import-Vorschau: Getränkerechnung <?= esc($monats_name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="page-title mb-1">Import-Vorschau</h1>
                        <p class="text-muted mb-0">Getränkerechnung <?= esc($monats_name) ?> — bitte prüfen, es wurde noch nichts angelegt</p>
                    </div>
                    <a href="<?= base_url('/schulden/import') ?>" class="btn btn-outline-vdst">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i> Andere Datei wählen
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($hinweise)): ?>
            <div class="alert alert-info">
                <strong><i class="bi bi-info-circle" aria-hidden="true"></i> Hinweis:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($hinweise as $hinweis): ?>
                        <li><?= esc($hinweis) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($warnungen)): ?>
            <div class="alert alert-warning">
                <strong><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> Bitte beachten:</strong>
                <ul class="mb-0 mt-1">
                    <?php foreach ($warnungen as $warnung): ?>
                        <li><?= esc($warnung) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="<?= base_url('/schulden/import/confirm') ?>" method="post" id="confirmForm">
            <?= csrf_field() ?>
            <div class="row">
                <!-- Linke Spalte: Zusammenfassung -->
                <div class="col-lg-4 mb-4">
                    <div class="card card-vdst h-100">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-clipboard-check" aria-hidden="true"></i> Zusammenfassung</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm mb-0">
                                <tr>
                                    <td>Monat / Zeitraum:</td>
                                    <td class="text-end fw-bold"><?= esc($monats_name) ?></td>
                                </tr>
                                <tr>
                                    <td>Getränke-Forderungen:</td>
                                    <td class="text-end fw-bold"><?= count($personen) ?> Personen</td>
                                </tr>
                                <tr>
                                    <td>Summe Forderungen:</td>
                                    <td class="text-end fw-bold"><?= formatiere_betrag($summe) ?></td>
                                </tr>
                                <tr>
                                    <td>Coleur-Beleg:</td>
                                    <td class="text-end fw-bold">
                                        <?= $coleur !== null && $coleur > 0 ? formatiere_betrag($coleur) : '—' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Bund-Beleg:</td>
                                    <td class="text-end fw-bold">
                                        <?= $bund !== null && $bund > 0 ? formatiere_betrag($bund) : '—' ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td>AH-Abrechnung:</td>
                                    <td class="text-end fw-bold">
                                        <?= $ziel_abrechnung !== null ? esc($ziel_abrechnung) : '— keine Zuordnung' ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-end gap-2">
                                <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">Abbrechen</a>
                                <button type="submit" class="btn btn-vdst" id="confirmBtn">
                                    <i class="bi bi-check-lg" aria-hidden="true"></i> Import bestätigen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rechte Spalte: erkannte Personen + Zuordnung -->
                <div class="col-lg-8 mb-4">
                    <div class="card card-vdst">
                        <div class="card-header">
                            <h5 class="mb-0"><i class="bi bi-people" aria-hidden="true"></i> Erkannte Personen (<?= count($personen) ?>)</h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover table-vdst table-stack mb-0">
                                    <thead>
                                        <tr>
                                            <th>Nachname (Excel)</th>
                                            <th class="text-end">Betrag</th>
                                            <th>Zuordnung</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($personen as $i => $person): ?>
                                            <tr>
                                                <td data-label="Nachname (Excel)">
                                                    <input type="hidden" name="nachname[<?= (int) $i ?>]" value="<?= esc($person['roh'], 'attr') ?>">
                                                    <strong><?= esc($person['roh']) ?></strong>
                                                    <?php if (!empty($person['positionen'])): ?>
                                                        <div class="small text-muted">
                                                            <?= esc(implode(', ', array_map(
                                                                static fn ($p) => rtrim(rtrim(number_format((float) $p['anzahl'], 2, ',', '.'), '0'), ',') . '× ' . $p['bezeichnung'],
                                                                $person['positionen']
                                                            ))) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td data-label="Betrag" class="text-end"><?= formatiere_betrag($person['betrag']) ?></td>
                                                <td data-label="Zuordnung">
                                                    <?php if ($person['status'] === 'eindeutig'): ?>
                                                        <span class="badge-status badge-status-gruen">zugeordnet</span>
                                                        <span class="ms-1"><?= esc($person['person']) ?></span>
                                                    <?php elseif ($person['status'] === 'mehrdeutig'): ?>
                                                        <span class="badge-status badge-status-amber d-block mb-1">mehrdeutig — bitte wählen</span>
                                                        <select name="wahl[<?= (int) $i ?>]" class="form-select form-select-sm">
                                                            <option value="">Als Gast übernehmen (Nachname behalten)</option>
                                                            <?php foreach ($person['kandidaten'] as $kandidat): ?>
                                                                <option value="<?= (int) $kandidat['id'] ?>"><?= esc(\App\Models\PersonModel::anzeigename($kandidat)) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php else: ?>
                                                        <span class="badge-status badge-status-amber d-block mb-1">unbekannt</span>
                                                        <select name="wahl[<?= (int) $i ?>]" class="form-select form-select-sm">
                                                            <option value="">Als Gast übernehmen (Nachname behalten)</option>
                                                            <?php foreach ($personen_register as $kandidat): ?>
                                                                <option value="<?= (int) $kandidat['id'] ?>"><?= esc(\App\Models\PersonModel::anzeigename($kandidat)) ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="fw-bold">
                                            <td>Summe</td>
                                            <td class="text-end"><?= formatiere_betrag($summe) ?></td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('confirmForm');
            const btn = document.getElementById('confirmBtn');

            form.addEventListener('submit', function() {
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Wird importiert...';
                btn.disabled = true;
            });
        });
    </script>
<?= $this->endSection() ?>
