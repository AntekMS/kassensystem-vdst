<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Import-Vorschau<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="page-title">📊 Import-Vorschau</h1>
                <p class="text-muted">Prüfen Sie die zu importierenden Daten vor der Wiederherstellung</p>
            </div>
        </div>

        <div class="row">
            <!-- Hauptbereich -->
            <div class="col-lg-8">
                <!-- Backup-Informationen -->
                <div class="card card-vdst mb-4">
                    <div class="card-header">
                        <strong>📦 Backup-Informationen</strong>
                    </div>
                    <div class="card-body">
                        <table class="table table-sm">
                            <tr>
                                <td style="width: 200px;"><strong>Erstellt am:</strong></td>
                                <td><?= date('d.m.Y H:i:s', strtotime($vorschau['backup_info']['backup_erstellt_am'])) ?></td>
                            </tr>
                            <tr>
                                <td><strong>System-Version:</strong></td>
                                <td><?= $vorschau['backup_info']['kassensystem_version'] ?></td>
                            </tr>
                            <tr>
                                <td><strong>Export-Umfang:</strong></td>
                                <td>
                                <span class="badge bg-success">
                                    <?= ucfirst($vorschau['backup_info']['export_umfang']) ?>
                                </span>
                                </td>
                            </tr>
                        </table>

                        <?php if (!empty($vorschau['backup_info']['fehlende_dateien'])): ?>
                            <div class="alert-warning mb-0">
                                <strong>⚠️ Hinweis:</strong>
                                <?= count($vorschau['backup_info']['fehlende_dateien']) ?> Beleg-Dateien
                                waren beim Export nicht verfügbar. Die Metadaten werden trotzdem importiert.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Import-Statistik -->
                <div class="card card-vdst mb-4">
                    <div class="card-header">
                        <strong>📊 Zu importierende Daten</strong>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-primary mb-1">
                                        <?= $vorschau['import_statistik']['buchungen_gesamt'] ?>
                                    </h3>
                                    <small class="text-muted">Buchungen</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-success mb-1">
                                        <?= $vorschau['import_statistik']['belege_gesamt'] ?>
                                    </h3>
                                    <small class="text-muted">Belege</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-info mb-1">
                                        <?= $vorschau['import_statistik']['ah_abrechnungen_gesamt'] ?>
                                    </h3>
                                    <small class="text-muted">AH² Abrechnungen</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3 class="text-warning mb-1">
                                        <?= $vorschau['import_statistik']['hv_abrechnungen_gesamt'] ?>
                                    </h3>
                                    <small class="text-muted">HV Abrechnungen</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Konflikt-Analyse -->
                <div class="card card-vdst mb-4">
                    <div class="card-header">
                        <strong>🔍 Konflikt-Analyse</strong>
                    </div>
                    <div class="card-body">
                        <!-- Buchungen -->
                        <div class="mb-3">
                            <h6 class="fw-bold">Buchungen</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert-success mb-0">
                                        <strong><?= $vorschau['konflikte']['buchungen']['neue'] ?></strong> neue Buchungen
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert-warning mb-0">
                                        <strong><?= $vorschau['konflikte']['buchungen']['duplikate'] ?></strong> mögliche Duplikate
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($vorschau['konflikte']['buchungen']['beispiele'])): ?>
                                <details class="mt-2">
                                    <summary class="text-muted" style="cursor: pointer;">
                                        Beispiele für Duplikate anzeigen
                                    </summary>
                                    <table class="table table-sm mt-2">
                                        <thead>
                                        <tr>
                                            <th>Datum</th>
                                            <th>Beschreibung</th>
                                            <th>Betrag</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($vorschau['konflikte']['buchungen']['beispiele'] as $bsp): ?>
                                            <tr>
                                                <td><?= date('d.m.Y', strtotime($bsp['datum'])) ?></td>
                                                <td><?= esc($bsp['beschreibung']) ?></td>
                                                <td><?= number_format($bsp['betrag'], 2, ',', '.') ?> €</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </details>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <!-- Belege -->
                        <div class="mb-3">
                            <h6 class="fw-bold">Belege</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert-success mb-0">
                                        <strong><?= $vorschau['konflikte']['belege']['neue'] ?></strong> neue Belege
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert-warning mb-0">
                                        <strong><?= $vorschau['konflikte']['belege']['duplikate_belegnummer'] ?></strong> existierende Belegnummern
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($vorschau['konflikte']['belege']['beispiele'])): ?>
                                <details class="mt-2">
                                    <summary class="text-muted" style="cursor: pointer;">
                                        Beispiele für existierende Belegnummern
                                    </summary>
                                    <table class="table table-sm mt-2">
                                        <thead>
                                        <tr>
                                            <th>Belegnummer</th>
                                            <th>Beschreibung</th>
                                            <th>Betrag</th>
                                        </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($vorschau['konflikte']['belege']['beispiele'] as $bsp): ?>
                                            <tr>
                                                <td><?= esc($bsp['belegnummer']) ?></td>
                                                <td><?= esc($bsp['beschreibung']) ?></td>
                                                <td><?= number_format($bsp['betrag'], 2, ',', '.') ?> €</td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </details>
                            <?php endif; ?>
                        </div>

                        <hr>

                        <!-- Abrechnungen -->
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="fw-bold">AH² Abrechnungen</h6>
                                <div class="alert-success mb-0">
                                    <strong><?= $vorschau['konflikte']['ah_abrechnungen']['neue'] ?></strong> neue
                                </div>
                                <?php if ($vorschau['konflikte']['ah_abrechnungen']['duplikate_monat'] > 0): ?>
                                    <div class="alert-warning mt-2 mb-0">
                                        <strong><?= $vorschau['konflikte']['ah_abrechnungen']['duplikate_monat'] ?></strong> existierende Monate
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <h6 class="fw-bold">HV Abrechnungen</h6>
                                <div class="alert-success mb-0">
                                    <strong><?= $vorschau['konflikte']['hv_abrechnungen']['neue'] ?></strong> neue
                                </div>
                                <?php if ($vorschau['konflikte']['hv_abrechnungen']['duplikate_monat'] > 0): ?>
                                    <div class="alert-warning mt-2 mb-0">
                                        <strong><?= $vorschau['konflikte']['hv_abrechnungen']['duplikate_monat'] ?></strong> existierende Monate
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import-Optionen -->
                <div class="card card-vdst mb-4">
                    <div class="card-header">
                        <strong>⚙️ Import-Optionen</strong>
                    </div>
                    <div class="card-body">
                        <form action="<?= base_url('/buchungen/import/durchfuehren') ?>"
                              method="post"
                              id="importForm">
                            <?= csrf_field() ?>

                            <!-- Import-Modus -->
                            <div class="mb-4">
                                <label class="form-label fw-bold">Import-Modus</label>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="import_modus"
                                                   id="modus_merge"
                                                   value="merge"
                                                <?= $vorschau['empfehlung']['modus'] === 'merge' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="modus_merge">
                                                <strong>🔄 Zusammenführen</strong><br>
                                                <small class="text-muted">
                                                    Neue Einträge hinzufügen, bestehende behalten
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input"
                                                   type="radio"
                                                   name="import_modus"
                                                   id="modus_overwrite"
                                                   value="overwrite"
                                                <?= $vorschau['empfehlung']['modus'] === 'overwrite' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="modus_overwrite">
                                                <strong>♻️ Überschreiben</strong><br>
                                                <small class="text-muted">
                                                    Bestehende Einträge aktualisieren
                                                </small>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Beleg-Dateien kopieren -->
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input"
                                           type="checkbox"
                                           id="beleg_dateien_kopieren"
                                           name="beleg_dateien_kopieren"
                                           value="1"
                                           checked>
                                    <label class="form-check-label" for="beleg_dateien_kopieren">
                                        <strong>Beleg-Dateien kopieren</strong><br>
                                        <small class="text-muted">
                                            Original-Dateien in das System importieren
                                        </small>
                                    </label>
                                </div>
                            </div>

                            <!-- Warnung -->
                            <div class="alert-warning">
                                <h6 class="fw-bold mb-2">⚠️ Wichtig vor dem Import:</h6>
                                <ul class="mb-0 small">
                                    <li>Ein <strong>Sicherungs-Backup</strong> wird automatisch erstellt</li>
                                    <li>Der Import kann bei großen Datenmengen mehrere Minuten dauern</li>
                                    <li>Kontostände werden nach Import neu berechnet</li>
                                </ul>
                            </div>

                            <!-- Buttons -->
                            <div class="d-flex justify-content-between">
                                <a href="<?= base_url('/buchungen/import/abbrechen') ?>"
                                   class="btn btn-outline-danger">
                                    ❌ Import abbrechen
                                </a>
                                <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                    <strong>✅ Import durchführen</strong>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Empfehlung -->
                <div class="card border-<?= $vorschau['empfehlung']['risiko'] === 'niedrig' ? 'success' : 'warning' ?> mb-3">
                    <div class="card-header bg-<?= $vorschau['empfehlung']['risiko'] === 'niedrig' ? 'success' : 'warning' ?> text-white">
                        <strong>💡 System-Empfehlung</strong>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <strong>Empfohlener Modus:</strong><br>
                            <?= $vorschau['empfehlung']['modus'] === 'merge' ? '🔄 Zusammenführen' : '♻️ Überschreiben' ?>
                        </p>
                        <p class="text-muted small mb-2">
                            <?= $vorschau['empfehlung']['nachricht'] ?>
                        </p>
                        <span class="badge bg-<?= $vorschau['empfehlung']['risiko'] === 'niedrig' ? 'success' : 'warning' ?>">
                        Risiko: <?= ucfirst($vorschau['empfehlung']['risiko']) ?>
                    </span>
                    </div>
                </div>

                <!-- Kontostand-Vorschau -->
                <?php if (!empty($vorschau['backup_info']['kontostaende_beim_export'])): ?>
                    <div class="card card-vdst mb-3">
                        <div class="card-header">
                            <strong>💰 Kontostände im Backup</strong>
                        </div>
                        <div class="card-body">
                            <?php foreach ($vorschau['backup_info']['kontostaende_beim_export'] as $konto => $daten): ?>
                                <div class="mb-2">
                                    <strong><?= ucfirst(str_replace('_', ' ', $konto)) ?>:</strong><br>
                                    <span class="<?= $daten['saldo'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($daten['saldo'], 2, ',', '.') ?> €
                                </span>
                                </div>
                            <?php endforeach; ?>
                            <hr>
                            <small class="text-muted">
                                Nach Import werden die Kontostände neu berechnet
                            </small>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Hinweise -->
                <div class="card border-info">
                    <div class="card-header bg-info text-white">
                        <strong>ℹ️ Weitere Hinweise</strong>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li>Original-Belegnummern bleiben erhalten</li>
                            <li>Datumsstempel werden übernommen</li>
                            <li>Abrechnungs-Verknüpfungen werden wiederhergestellt</li>
                            <li>Bei Fehlern wird der Import abgebrochen</li>
                            <li>Sie können danach das Sicherungs-Backup wiederherstellen</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('importForm');
            const submitBtn = document.getElementById('submitBtn');

            // Form-Submission mit Bestätigung
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const modus = document.querySelector('input[name="import_modus"]:checked').value;
                const modusText = modus === 'merge' ? 'Zusammenführen' : 'Überschreiben';

                if (confirm(
                    `Import im Modus "${modusText}" durchführen?\n\n` +
                    `Ein Sicherungs-Backup wird automatisch erstellt.\n` +
                    `Dieser Vorgang kann nicht rückgängig gemacht werden (außer durch Wiederherstellen des Backups).`
                )) {
                    submitBtn.innerHTML = '⏳ Import läuft...';
                    submitBtn.disabled = true;

                    // Zusätzliche Info anzeigen
                    const infoDiv = document.createElement('div');
                    infoDiv.className = 'alert-info mt-3';
                    infoDiv.innerHTML = '<strong>⏳ Import läuft...</strong><br>Bitte warten Sie, bis der Vorgang abgeschlossen ist. Dies kann einige Minuten dauern.';
                    form.appendChild(infoDiv);

                    form.submit();
                }
            });

            // Warnung bei Seitenverlassen während Import
            let importStarted = false;
            form.addEventListener('submit', function() {
                importStarted = true;
            });

            window.addEventListener('beforeunload', function(e) {
                if (importStarted) {
                    e.preventDefault();
                    e.returnValue = '';
                    return 'Import läuft noch. Wirklich abbrechen?';
                }
            });

            console.log('📊 Import-Vorschau geladen');
        });
    </script>
<?= $this->endSection() ?>