<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Vorschau: <?= esc($abrechnung['titel']) ?><?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <h1 class="page-title">Abrechnungs-Vorschau</h1>
                <h4 class="text-muted"><?= esc($abrechnung['titel']) ?></h4>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                   class="btn btn-vdst">
                    <i class="bi bi-receipt" aria-hidden="true"></i> Belege verwalten
                </a>
                <a href="<?= base_url('/abrechnungen/' . $typ) ?>"
                   class="btn btn-outline-vdst">
                    <i class="bi bi-arrow-left" aria-hidden="true"></i> Zurück zur Übersicht
                </a>

                <?php if (count($belege) > 0): ?>
                    <!-- Export-Optionen als große Buttons -->
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-vdst dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-download" aria-hidden="true"></i> Export herunterladen
                        </button>
                        <ul class="dropdown-menu">
                            <li>
                                <a class="dropdown-item d-flex align-items-center"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>">
                                    <i class="bi bi-file-earmark-excel me-2" aria-hidden="true"></i>
                                    <div>
                                        <strong>Excel-Datei</strong><br>
                                        <small class="text-muted">Tabelle mit allen Beleg-Daten</small>
                                    </div>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/exportPdf/' . $abrechnung['id']) ?>">
                                    <i class="bi bi-file-earmark-pdf me-2" aria-hidden="true"></i>
                                    <div>
                                        <strong>PDF-Rechnung</strong><br>
                                        <small class="text-muted">VDSt-gebrandete Abrechnung als PDF</small>
                                    </div>
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item d-flex align-items-center"
                                   href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>">
                                    <i class="bi bi-file-earmark-zip me-2" aria-hidden="true"></i>
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
                                        <span class="badge-status badge-status-outline">
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
                                        <span class="<?= abrechnung_status_badge_class($abrechnung['status']) ?>">
                                            <?= abrechnung_status_label($abrechnung['status']) ?>
                                        </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-sm">
                                    <tr>
                                        <td><strong>Anzahl Belege:</strong></td>
                                        <td><span class="badge-status badge-status-neutral"><?= count($belege) ?></span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Gesamtsumme:</strong></td>
                                        <td>
                                            <strong class="betrag-gross">
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
                <!-- Status-Änderung - KORRIGIERT -->
                <?php if ($abrechnung['status'] !== 'bezahlt'): ?>
                    <div class="card">
                        <div class="card-header">
                            <strong>Status ändern</strong>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?= base_url('/abrechnungen/' . $typ . '/changeStatus/' . $abrechnung['id']) ?>">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <select name="status" class="form-select" required>
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
                                    <small class="text-muted mt-1 d-block">
                                        Aktuell: <strong><?= abrechnung_status_label($abrechnung['status']) ?></strong>
                                    </small>
                                </div>
                                <button type="submit" class="btn btn-outline-vdst w-100" onclick="return confirmStatusChange()">
                                    Status ändern
                                </button>
                            </form>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-header text-success">
                            <strong><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Abrechnung bezahlt</strong>
                        </div>
                        <div class="card-body">
                            <p class="text-muted mb-0">
                                Diese Abrechnung wurde als bezahlt markiert und kann nicht mehr bearbeitet werden.
                            </p>
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
                    <div class="empty-state">
                        <i class="bi bi-receipt" aria-hidden="true"></i>
                        <p>Keine Belege in dieser Abrechnung.</p>
                        <a href="<?= base_url('/abrechnungen/' . $typ . '/belege/' . $abrechnung['id']) ?>"
                           class="btn btn-vdst">
                            Belege hinzufügen
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-vdst">
                            <tr>
                                <th>Belegnummer</th>
                                <th>Datum</th>
                                <th>Beschreibung</th>
                                <th>Bezugsquelle</th>
                                <th class="text-end">Betrag</th>
                                <th class="text-center">Aktionen</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach($belege as $beleg): ?>
                                <tr>
                                    <td>
                                        <strong><?= esc($beleg['belegnummer']) ?></strong>
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
                                    <td class="text-center">
                                        <a href="<?= base_url('/belege/show/' . $beleg['id']) ?>"
                                           target="_blank"
                                           class="btn-icon" title="Beleg anzeigen" aria-label="Beleg anzeigen">
                                            <i class="bi bi-eye" aria-hidden="true"></i>
                                        </a>
                                        <a href="<?= base_url('/belege/download/' . $beleg['id']) ?>"
                                           class="btn-icon" title="Herunterladen" aria-label="Beleg herunterladen">
                                            <i class="bi bi-download" aria-hidden="true"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Gesamtsumme:</th>
                                <th class="text-end">
                                    <strong class="betrag-gross">
                                        <?= number_format($abrechnung['gesamtsumme'], 2, ',', '.') ?> €
                                    </strong>
                                </th>
                                <th></th>
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
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel-Export</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>Strukturierte Tabelle</strong> mit allen Beleg-Daten:</p>
                            <ul class="small mb-3">
                                <li>Belegnummern und Rechnungsdaten</li>
                                <li>Bezugsquellen und Beschreibungen</li>
                                <li>Beträge und Gesamtsumme</li>
                            </ul>
                            <a href="<?= base_url('/abrechnungen/' . $typ . '/exportExcel/' . $abrechnung['id']) ?>"
                               class="btn btn-outline-vdst w-100">
                                <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Excel herunterladen
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF-Rechnung</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>VDSt-gebrandete Abrechnung</strong> als PDF-Rechnung:</p>
                            <ul class="small mb-3">
                                <li>Vereinslogo und Briefkopf</li>
                                <li>Belegliste mit Datum und Betrag</li>
                                <li>Gesamtsumme<?= $typ === 'hv' ? ' und Begründung' : '' ?></li>
                            </ul>
                            <a href="<?= base_url('/abrechnungen/' . $typ . '/exportPdf/' . $abrechnung['id']) ?>"
                               class="btn btn-outline-vdst w-100">
                                <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> PDF herunterladen
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <strong><i class="bi bi-file-earmark-zip" aria-hidden="true"></i> ZIP-Archiv</strong>
                        </div>
                        <div class="card-body">
                            <p><strong>Komplettes Beleg-Archiv</strong> mit allen Originaldateien:</p>
                            <ul class="small mb-3">
                                <li><?= count($belege) ?> Original-Beleg-Dateien</li>
                                <li>Aussagekräftige Dateinamen</li>
                                <li>Übersichtliche Nummerierung</li>
                                <li>Detaillierte Info-Datei</li>
                                <li>Gesamtgröße: ca. <?= schaetze_archiv_groesse($belege) ?></li>
                            </ul>
                            <a href="<?= base_url('/abrechnungen/' . $typ . '/downloadZip/' . $abrechnung['id']) ?>"
                               class="btn btn-outline-vdst w-100">
                                <i class="bi bi-file-earmark-zip" aria-hidden="true"></i> ZIP-Archiv herunterladen
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
    <script>
        // Status-Änderungs-Bestätigung
        function confirmStatusChange() {
            const select = document.querySelector('select[name="status"]');
            const neuerStatus = select.options[select.selectedIndex].text.trim();
            const alterStatus = '<?= abrechnung_status_label($abrechnung['status']) ?>';

            if (neuerStatus.includes(alterStatus)) {
                alert('Der Status wurde nicht geändert.');
                return false;
            }

            return confirm(`Status von "${alterStatus}" zu "${neuerStatus}" ändern?`);
        }
    </script>
<?= $this->endSection() ?>