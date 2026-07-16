<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Rechnungsversand: Getränkerechnung <?= esc($monats_name) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="page-title mb-1">Rechnungsversand</h1>
                        <p class="text-muted mb-0">Getränkerechnung <?= esc($monats_name) ?> — Einzelrechnungen per E-Mail verschicken</p>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a href="<?= base_url('/schulden/import/uebersicht?monat=' . esc($monat, 'url')) ?>" class="btn btn-outline-vdst">
                            <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Übersichts-PDF (Aushang)
                        </a>
                        <a href="<?= base_url('/schulden/emails') ?>" class="btn btn-outline-vdst">
                            <i class="bi bi-envelope-at" aria-hidden="true"></i> E-Mail-Adressen verwalten
                        </a>
                        <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                            <i class="bi bi-arrow-left" aria-hidden="true"></i> Zur Schuldenliste
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!$smtp_ok): ?>
            <div class="alert alert-warning">
                <strong><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> E-Mail-Versand nicht konfiguriert:</strong>
                Es fehlen die <code>email.*</code>-Einstellungen in der <code>.env</code>
                (mindestens <code>email.protocol&nbsp;=&nbsp;smtp</code>, <code>email.SMTPHost</code> und <code>email.fromEmail</code>).
                Adressen können trotzdem gespeichert werden.
            </div>
        <?php endif; ?>

        <?php if (!empty($versand_ergebnisse)): ?>
            <?php
                $erfolge = array_filter($versand_ergebnisse, static fn ($e) => $e['ok']);
                $fehlschlaege = array_filter($versand_ergebnisse, static fn ($e) => !$e['ok']);
            ?>
            <?php if ($erfolge !== []): ?>
                <div class="alert alert-success">
                    <strong><i class="bi bi-check-circle" aria-hidden="true"></i> Verschickt:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($erfolge as $ergebnis): ?>
                            <li><?= esc($ergebnis['person']) ?> (<?= esc($ergebnis['email']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            <?php if ($fehlschlaege !== []): ?>
                <div class="alert alert-danger">
                    <strong><i class="bi bi-x-circle" aria-hidden="true"></i> Nicht verschickt:</strong>
                    <ul class="mb-0 mt-1">
                        <?php foreach ($fehlschlaege as $ergebnis): ?>
                            <li><?= esc($ergebnis['person']) ?><?= $ergebnis['email'] !== '' ? ' (' . esc($ergebnis['email']) . ')' : '' ?> — <?= esc($ergebnis['hinweis']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="card card-vdst">
            <div class="card-header">
                <h5 class="mb-0"><i class="bi bi-send" aria-hidden="true"></i> Personen (<?= count($personen) ?>)</h5>
            </div>
            <form action="<?= base_url('/schulden/import/versand/senden') ?>" method="post" id="versandForm">
                <?= csrf_field() ?>
                <input type="hidden" name="monat" value="<?= esc($monat) ?>">
                <div class="card-body border-bottom">
                    <div class="row">
                        <div class="col-12 col-md-4">
                            <label for="fristInput" class="form-label">Rückmelde-Frist</label>
                            <input type="date" class="form-control" id="fristInput" name="frist"
                                   value="<?= esc(old('frist', $frist_default)) ?>">
                            <div class="form-text">Bis zu diesem Datum sollen Betroffene bei ungedecktem Konto Bescheid geben.</div>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-vdst table-stack mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center">Senden</th>
                                    <th>Person</th>
                                    <th class="text-end">Betrag</th>
                                    <th>E-Mail-Adresse</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personen as $i => $person): ?>
                                    <?php $vorausgewaehlt = $person['email'] !== '' && $person['versendet_am'] === null; ?>
                                    <tr>
                                        <td data-label="Senden" class="text-center">
                                            <input type="hidden" name="person[<?= (int) $i ?>]" value="<?= esc($person['person']) ?>">
                                            <input type="checkbox" class="form-check-input" name="senden[]"
                                                   value="<?= (int) $i ?>"
                                                   aria-label="Rechnung an <?= esc($person['person']) ?> senden"
                                                   <?= $vorausgewaehlt ? 'checked' : '' ?>>
                                        </td>
                                        <td data-label="Person"><strong><?= esc($person['person']) ?></strong></td>
                                        <td data-label="Betrag" class="text-end"><?= formatiere_betrag($person['betrag']) ?></td>
                                        <td data-label="E-Mail-Adresse">
                                            <input type="email" class="form-control form-control-sm"
                                                   name="email[<?= (int) $i ?>]"
                                                   value="<?= esc($person['email']) ?>"
                                                   placeholder="name@example.org">
                                        </td>
                                        <td data-label="Status">
                                            <?php if ($person['versendet_am'] !== null): ?>
                                                <span class="badge-status badge-status-gruen">verschickt am <?= esc(date('d.m.Y H:i', strtotime($person['versendet_am']))) ?></span>
                                            <?php elseif ($person['email'] === ''): ?>
                                                <span class="badge-status badge-status-amber">keine Adresse</span>
                                            <?php else: ?>
                                                <span class="badge-status badge-status-neutral">noch nicht verschickt</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-end gap-2 flex-wrap">
                        <?php if ($smtp_ok): ?>
                            <button type="submit" name="nur_speichern" value="1" class="btn btn-outline-vdst">
                                <i class="bi bi-save" aria-hidden="true"></i> Nur Adressen speichern
                            </button>
                            <button type="submit" class="btn btn-vdst" id="versandBtn">
                                <i class="bi bi-send" aria-hidden="true"></i> Rechnungen verschicken
                            </button>
                        <?php else: ?>
                            <button type="submit" name="nur_speichern" value="1" class="btn btn-vdst">
                                <i class="bi bi-save" aria-hidden="true"></i> Adressen speichern
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('versandForm');
            const btn = document.getElementById('versandBtn');

            if (form && btn) {
                form.addEventListener('submit', function(event) {
                    // Nur beim echten Versand den Spinner zeigen, nicht beim Speichern
                    if (event.submitter === btn) {
                        btn.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Wird verschickt...';
                        btn.disabled = true;
                    }
                });
            }
        });
    </script>
<?= $this->endSection() ?>
