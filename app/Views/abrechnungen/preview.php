<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Vorschau: <?= $abrechnung['titel'] ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="page-title">Abrechnungs-Vorschau</h1>
                <h4 class="text-muted"><?= esc($abrechnung['titel']) ?></h4>
            </div>
            <div>
                <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                   class="btn btn-vdst">
                    📄 Belege verwalten
                </a>
                <a href="<?= base_url('/abrechnungen/' . $typ) ?>"
                   class="btn btn-outline-vdst">
                    ← Zurück zur Übersicht
                </a>

                <?php if (count($belege) > 0): ?>
                    <!-- Export-Optionen als große Buttons -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-success btn-lg dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            📊 Export herunterladen
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>">
                                    <span class="me-2">📊</span>
                                    <div>
                                        <strong>Excel-Datei</strong><br>
                                        <small class="text-muted">Tabelle mit allen Beleg-Daten</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>">
                                    <span class="me-2">📁</span>
                                    <div>
                                        <strong>ZIP-Archiv</strong><br>
                                        <small class="text-muted">Alle <?= count($belege) ?> Beleg-Dateien + Info</small>
                                    </div>
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Abrechnung-Details -->
        <div class="row mb-4">
            <div class="col-md-8">
                <div class="card card-vdst">
                    <div class="card-header">
                        <strong>Abrechnungs-Details</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Typ:</strong></td>
                                        <td>
                                        <span class="badge bg-<?= $typ === 'ah' ? 'info' : 'warning' ?>">
                                            <?= $typ === 'ah' ? 'AH² Abrechnung' : 'HV Abrechnung' ?>
                                        </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Monat:</strong></td>
                                        <td><?= $abrechnung['abrechnungsmonat'] ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Erstellt:</strong></td>
                                        <td><?= date('d.m.Y', strtotime($abrechnung['erstellt_am'])) ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Status:</strong></td>
                                        <td>
                                        <span class="badge bg-<?= $abrechnung['status'] === 'entwurf' ? 'secondary' : 'warning' ?>">
                                            <?= ucfirst($abrechnung['status']) ?>
                                        </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Anzahl Belege:</strong></td>
                                        <td><span class="badge bg-dark"><?= count($belege) ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Gesamtsumme:</strong></td>
                                        <td>
                                            <strong style="font-size: 1.2em; color: var(--vdst-rot);">
                                                <?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €
                                            </strong>
                                        </td>
                                    </tr>
                                    <?php if ($typ === 'hv' && !empty($abrechnung['begruendung'])): ?>
                                        <tr>
                                            <td><strong>Begründung:</strong></td>
                                            <td><small><?= nl2br(esc($abrechnung['begruendung'])) ?></small></td>
                                        </tr>
                                    <?php endif; ?>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <!-- Status-Änderung -->
                <?php if ($abrechnung['status'] !== 'bezahlt'): ?>
                    <div class="card">
                        <div class="card-header bg-warning text-dark">
                            <strong>Status ändern</strong>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?= base_url('/abrechnungen/' . $typ . '/changeStatus/' . $abrechnung['id']) ?>">
                                <div class="mb-3">
                                    <select name="status" class="form-control" required>
                                        <option value="entwurf" <?= $abrechnung['status'] === 'entwurf' ? 'selected' : '' ?>>
                                            Entwurf
                                        </option>
                                        <option value="ausstehend" <?= $abrechnung['status'] === 'ausstehend' ? 'selected' : '' ?>>
                                            Ausstehend
                                        </option>
                                        <option value="eingereicht" <?= $abrechnung['status'] === 'eingereicht' ? 'selected' : '' ?>>
                                            Eingereicht
                                        </option>
                                        <option value="bezahlt" <?= $abrechnung['status'] === 'bezahlt' ? 'selected' : '' ?>>
                                            Bezahlt
                                        </option>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-warning w-100">Status ändern</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Belege-Tabelle -->
        <div class="card">
            <div class="card-header table-vdst">
                <strong>Belege in dieser Abrechnung (<?= count($belege) ?>)</strong>
            </div>
            <div class="card-body p-0">
                <?php if (empty($belege)): ?>
                    <div class="text-center p-4">
                        <p class="text-muted">Keine Belege in dieser Abrechnung.</p>
                        <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                           class="btn btn-vdst">
                            Belege hinzufügen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Belegnummer</th>
                                <th>Datum</th>
                                <th>Beschreibung</th>
                                <th>Bezugsquelle</th>
                                <th class="text-end">Betrag</th>
                                <?php if ($typ === 'hv'): ?>
                                    <th>Begründung</th>
                                <?php endif; ?>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($belege as $beleg): ?>
                                <tr>
                                    <td>
                                        <strong><?= $beleg['belegnummer'] ?></strong>
                                        <br>
                                        <small class="text-muted"><?= strtoupper($beleg['dateityp']) ?></small>
                                    </td>
                                    <td>
                                        <?= date('d.m.Y', strtotime($beleg['rechnungsdatum'])) ?>
                                    </td>
                                    <td>
                                        <?= esc($beleg['beschreibung']) ?>
                                    </td>
                                    <td>
                                        <?= $beleg['lieferant'] ? esc($beleg['lieferant']) : '<span class="text-muted">-</span>' ?>
                                    </td>
                                    <td class="text-end">
                                        <strong><?= number_format($beleg['betrag'], 2, ',', '.') ?> €</strong>
                                    </td>
                                    <?php if ($typ === 'hv'): ?>
                                        <td>
                                            <small class="text-muted">
                                                <?php
                                                // Automatische Begründung basierend auf Beschreibung
                                                $beschreibung = strtolower($beleg['beschreibung']);
                                                if (strpos($beschreibung, 'farbe') !== false || strpos($beschreibung, 'streichen') !== false) {
                                                    echo 'Renovierung und Instandhaltung';
                                                } elseif (strpos($beschreibung, 'regal') !== false || strpos($beschreibung, 'möbel') !== false) {
                                                    echo 'Möblierung der Räume';
                                                } elseif (strpos($beschreibung, 'werkzeug') !== false || strpos($beschreibung, 'reparatur') !== false) {
                                                    echo 'Wartung und Reparatur';
                                                } elseif (strpos($beschreibung, 'küche') !== false || strpos($beschreibung, 'geschirr') !== false) {
                                                    echo 'Küchenausstattung';
                                                } else {
                                                    echo 'Vereinshaus-Ausgabe';
                                                }
                                                ?>
                                            </small>
                                        </td>
                                    <?php endif; ?>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                               target="_blank"
                                               class="btn btn-outline-dark" title="Beleg anzeigen">
                                                👁️
                                            </a>
                                            <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                                               class="btn btn-outline-success" title="Herunterladen">
                                                📥
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <th colspan="<?= $typ === 'hv' ? '4' : '4' ?>" class="text-end">Gesamtsumme:</th>
                                <th class="text-end">
                                    <strong style="font-size: 1.1em;">
                                        <?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €
                                    </strong>
                                </th>
                                <th colspan="<?= $typ === 'hv' ? '2' : '1' ?>"></th>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Export-Info -->
        <?php if (count($belege) > 0): ?>
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <strong>📊 Excel-Export</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>Strukturierte Tabelle</strong> mit allen Beleg-Daten:</p>
                            <ul class="small mb-3">
                                <li>Belegnummern und Rechnungsdaten</li>
                                <li>Bezugsquellen und Beschreibungen</li>
                                <li>Beträge und Gesamtsumme</li>
                                <?php if ($typ === 'hv'): ?>
                                    <li>Automatische HV-Begründungen</li>
                                <?php endif; ?>
                            </ul>
                            <a href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>"
                               class="btn btn-success w-100">
                                📊 Excel herunterladen
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-dark text-white">
                            <strong>📁 ZIP-Archiv</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>Komplettes Beleg-Archiv</strong> mit allen Originaldateien:</p>
                            <ul class="small mb-3">
                                <li><?= count($belege) ?> Original-Beleg-Dateien</li>
                                <li>Aussagekräftige Dateinamen</li>
                                <li>Übersichtliche Nummerierung</li>
                                <li>Detaillierte Info-Datei</li>
                                <li>Gesamtgröße: ca. <?= schaetzeArchivGroesse($belege) ?></li>
                            </ul>
                            <a href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>"
                               class="btn btn-dark w-100">
                                📁 ZIP-Archiv herunterladen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?php
// Hilfsfunktion für geschätzte Archivgröße
function schaetzeArchivGroesse($belege) {
    $gesamtgroesse = 0;
    foreach ($belege as $beleg) {
        if (isset($beleg['dateigroesse']) && $beleg['dateigroesse'] > 0) {
            $gesamtgroesse += $beleg['dateigroesse'];
        } else {
            // Schätzung basierend auf Dateityp
            $schaetzung = $beleg['dateityp'] === 'pdf' ? 200000 : 500000; // 200KB für PDF, 500KB für Bilder
            $gesamtgroesse += $schaetzung;
        }
    }

    if ($gesamtgroesse < 1024 * 1024) {
        return number_format($gesamtgroesse / 1024, 0) . ' KB';
    } else {
        return number_format($gesamtgroesse / (1024 * 1024), 1) . ' MB';
    }
}
?>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Beleg-Links in neuem Tab öffnen
            const belegLinks = document.querySelectorAll('a[href*="/belege/show/"]');
            belegLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    window.open(this.href, '_blank');
                });
            });

            // Export-Download Feedback initialisieren
            initializeExportFeedback();
        });

        function initializeExportFeedback() {
            // ZIP-Download mit Loading-Indikator
            const zipLinks = document.querySelectorAll('a[href*="/downloadZip/"]');

            zipLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    showLoadingToast('ZIP-Archiv wird erstellt...', 'Das kann einen Moment dauern.');

                    setTimeout(() => {
                        hideLoadingToast();
                        showSuccessToast('ZIP-Download gestartet!', 'Das Archiv wurde erstellt und der Download gestartet.');
                    }, 2000);
                });
            });

            // Excel-Download Feedback
            const excelLinks = document.querySelectorAll('a[href*="/exportExcel/"]');

            excelLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    showSuccessToast('Excel-Export gestartet!', 'Die Datei wird heruntergeladen.');
                });
            });
        }

        function showLoadingToast(title, message) {
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div id="loadingToast" class="toast show" role="alert">
                        <div class="toast-header bg-info text-white">
                            <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                            <strong class="me-auto">${title}</strong>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', toastHtml);
        }

        function hideLoadingToast() {
            const loadingToast = document.getElementById('loadingToast');
            if (loadingToast) {
                loadingToast.remove();
            }
        }

        function showSuccessToast(title, message) {
            const toastHtml = `
                <div class="toast-container position-fixed top-0 end-0 p-3">
                    <div class="toast show" role="alert" data-bs-autohide="true" data-bs-delay="3000">
                        <div class="toast-header bg-success text-white">
                            <strong class="me-auto">${title}</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">${message}</div>
                    </div>
                </div>
            `;

            document.body.insertAdjacentHTML('beforeend', toastHtml);
        }
    </script>
<?= $this->endSection() ?>