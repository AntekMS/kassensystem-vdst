<?= $this->extend('layouts/main') ?>

<?= $this->section('title') ?>Inventur<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="container-fluid">
        <!-- Page Title -->
        <h1 class="page-title">Inventur</h1>

        <!-- Aktionen -->
        <div class="row mb-4">
            <div class="col-md-12">
                <a href="<?= base_url('/schulden/export/inventur') ?>" class="btn btn-vdst">
                    <i class="bi bi-file-earmark-excel" aria-hidden="true"></i> Als Excel herunterladen
                </a>
                <a href="<?= base_url('/schulden/export/inventur-pdf') ?>" class="btn btn-outline-vdst">
                    <i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Als PDF herunterladen
                </a>
                <a href="<?= base_url('/schulden') ?>" class="btn btn-outline-vdst">
                    Zur Schuldenliste
                </a>
            </div>
        </div>

        <!-- Bestandsübersicht (gleicher Aufbau wie das Inventur-Excel) -->
        <div class="row">
            <div class="col-lg-6">
                <div class="card card-vdst">
                    <div class="card-header">
                        <strong>Kassenwart – Aktueller Bestand</strong>
                        <span class="float-end">Stand: <?= date('d.m.Y') ?></span>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0">
                            <tbody>
                            <!-- I. Kassenbestand -->
                            <tr class="table-light">
                                <th colspan="2">I. Kassenbestand</th>
                            </tr>
                            <?php foreach ($kontostaende as $konto => $daten): ?>
                                <tr>
                                    <td><?= esc(konto_label($konto)) ?></td>
                                    <td class="text-end <?= $daten['saldo'] < 0 ? 'saldo-negativ' : '' ?>">
                                        <?= formatiere_betrag($daten['saldo']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr>
                                <th>Summe I</th>
                                <th class="text-end <?= $summe_kassen < 0 ? 'saldo-negativ' : '' ?>">
                                    <?= formatiere_betrag($summe_kassen) ?>
                                </th>
                            </tr>

                            <!-- II. Forderungen / III. Verbindlichkeiten -->
                            <?php
                            $bloecke = [
                                'forderung' => ['titel' => 'II. Forderungen', 'summe_label' => 'Summe II'],
                                'verbindlichkeit' => ['titel' => 'III. Verbindlichkeiten', 'summe_label' => 'Summe III'],
                            ];
                            ?>
                            <?php foreach ($bloecke as $typ => $block): ?>
                                <tr class="table-light">
                                    <th colspan="2"><?= esc($block['titel']) ?></th>
                                </tr>
                                <?php foreach (schuld_kategorie_optionen() as $kategorie => $label): ?>
                                    <tr>
                                        <td><?= esc($label) ?></td>
                                        <td class="text-end"><?= formatiere_betrag($inventur[$typ][$kategorie] ?? 0) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr>
                                    <th><?= esc($block['summe_label']) ?></th>
                                    <th class="text-end"><?= formatiere_betrag($inventur[$typ]['summe'] ?? 0) ?></th>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Summe Gesamt -->
                            <tr class="zeile-summe-gesamt">
                                <th>Summe Gesamt (I + II − III)</th>
                                <th class="text-end"><?= formatiere_betrag($summe_gesamt) ?></th>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="text-muted mt-2">
                    <small>Summe Gesamt = Kassenbestand (I) + Forderungen (II) − Verbindlichkeiten (III).</small>
                </p>
            </div>
        </div>
    </div>
<?= $this->endSection() ?>
