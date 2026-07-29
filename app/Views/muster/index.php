<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Muster &amp; Vorlagen<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <h1 class="page-title">Muster &amp; Vorlagen</h1>

        <p class="text-muted mb-4">
            Hier siehst du mit Beispieldaten, wie jedes Dokument aussieht, das das System erzeugt.
            PDFs öffnen sich als Vorschau im neuen Tab, Excel-Dateien werden als Muster heruntergeladen.
            Alle Daten sind frei erfunden.
        </p>

        <?php foreach ($gruppen as $gruppe => $eintraege): ?>
            <h2 class="h5 mb-3"><?= esc($gruppe) ?></h2>
            <div class="row g-3 mb-4">
                <?php foreach ($eintraege as $m): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card card-vdst h-100">
                            <div class="card-header d-flex align-items-center gap-2">
                                <i class="bi <?= $m['art'] === 'pdf' ? 'bi-file-earmark-pdf' : 'bi-file-earmark-spreadsheet' ?>" aria-hidden="true"></i>
                                <?= esc($m['label']) ?>
                            </div>
                            <div class="card-body">
                                <p class="text-muted mb-0"><?= esc($m['beschreibung']) ?></p>
                            </div>
                            <div class="card-footer bg-transparent text-center">
                                <?php if ($m['art'] === 'pdf'): ?>
                                    <a href="<?= base_url('/muster/' . $m['slug']) ?>" target="_blank" rel="noopener"
                                       class="btn btn-outline-vdst btn-sm">
                                        <i class="bi bi-eye" aria-hidden="true"></i> Vorschau
                                    </a>
                                <?php else: ?>
                                    <a href="<?= base_url('/muster/' . $m['slug']) ?>"
                                       class="btn btn-outline-vdst btn-sm">
                                        <i class="bi bi-download" aria-hidden="true"></i> Muster laden
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>

        <div class="card card-vdst">
            <div class="card-body d-flex align-items-start gap-3">
                <i class="bi bi-file-earmark-zip fs-3 text-muted" aria-hidden="true"></i>
                <div>
                    <h2 class="h6 mb-1">Komplett-ZIP</h2>
                    <p class="text-muted mb-0">
                        Zusätzlich bietet jeder Abrechnungsbereich ein Komplett-ZIP an: die Abrechnung als Excel-Tabelle
                        plus alle zugehörigen Beleg-Dateien. Dafür gibt es kein Muster, da es echte hochgeladene Belege bündelt —
                        die enthaltene Excel-Tabelle entspricht dem Muster oben.
                    </p>
                </div>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
