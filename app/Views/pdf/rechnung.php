<?php
/**
 * Geteiltes PDF-Template der Getränkerechnungen (Issue #35), gerendert von
 * App\Libraries\RechnungPdf für dompdf — kein Browser-View, daher bewusst
 * Standalone-HTML mit eigenem <style>-Block.
 *
 * $typ: 'einzel' | 'uebersicht' | 'coleur_bund' | 'inventur'
 */
$datumAnzeige = date('d.m.Y', strtotime($datum));
$kassenwart = trim((string) env('vdst.kassenwart_name', ''));
$kopfzusatz = $typ === 'inventur' ? 'Inventur' : 'Getränkeabrechnung';
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 90px 70px; }
    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 11px;
        color: #111111;
        margin: 0;
    }
    .briefkopf {
        border-bottom: 3px solid #dc143c;
        padding-bottom: 14px;
        margin-bottom: 28px;
    }
    .briefkopf td { vertical-align: middle; }
    .briefkopf .logo { width: 86px; }
    .briefkopf .logo img { height: 62px; }
    .vereinsname {
        font-size: 17px;
        font-weight: bold;
        letter-spacing: 0.5px;
    }
    .vereinszusatz {
        font-size: 10px;
        color: #555555;
        margin-top: 3px;
    }
    .meta {
        text-align: right;
        font-size: 10px;
        color: #555555;
        margin-bottom: 18px;
    }
    h1 {
        font-size: 15px;
        margin: 0 0 4px 0;
    }
    .untertitel {
        font-size: 11px;
        color: #555555;
        margin-bottom: 24px;
    }
    .betrag-kasten {
        border: 1px solid #dddddd;
        border-left: 4px solid #dc143c;
        padding: 14px 18px;
        margin: 18px 0;
    }
    .betrag-kasten .label { font-size: 10px; color: #555555; }
    .betrag-kasten .betrag { font-size: 20px; font-weight: bold; }
    table.posten {
        width: 100%;
        border-collapse: collapse;
        margin: 12px 0;
    }
    table.posten th {
        text-align: left;
        border-bottom: 2px solid #111111;
        padding: 6px 8px;
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    table.posten td {
        border-bottom: 1px solid #dddddd;
        padding: 6px 8px;
    }
    table.posten .betrag-spalte { text-align: right; white-space: nowrap; }
    table.posten tr.summe td {
        border-top: 2px solid #111111;
        border-bottom: none;
        font-weight: bold;
    }
    .hinweis {
        margin-top: 22px;
        font-size: 10px;
        color: #555555;
    }
    .fusszeile {
        position: fixed;
        bottom: -55px;
        left: 0;
        right: 0;
        border-top: 1px solid #dddddd;
        padding-top: 8px;
        font-size: 9px;
        color: #555555;
        text-align: center;
    }
</style>
</head>
<body>

<table class="briefkopf" width="100%" cellpadding="0" cellspacing="0">
    <tr>
        <?php if (!empty($logo_data_uri)): ?>
            <td class="logo"><img src="<?= $logo_data_uri ?>" alt=""></td>
        <?php endif; ?>
        <td>
            <div class="vereinsname">Verein deutscher Studenten zu Erlangen</div>
            <div class="vereinszusatz">Kassenwart · <?= esc($kopfzusatz) ?></div>
        </td>
    </tr>
</table>

<div class="meta"><?= esc($datumAnzeige) ?></div>

<?php if ($typ === 'einzel'): ?>

    <h1>Getränkerechnung <?= esc($monats_name) ?></h1>
    <div class="untertitel">für <?= esc($person) ?></div>

    <div class="betrag-kasten">
        <div class="label">Zu zahlender Betrag</div>
        <div class="betrag"><?= esc(formatiere_betrag((float) $betrag)) ?></div>
    </div>

    <p>
        Dieser Betrag ergibt sich aus der Getränkeliste des Monats
        <?= esc($monats_name) ?> und wurde in die Schuldenliste des Vereins übernommen.
    </p>
    <p class="hinweis">
        Bitte den Betrag zeitnah überweisen oder direkt beim Kassenwart begleichen.
    </p>

<?php elseif ($typ === 'uebersicht'): ?>

    <h1>Getränkerechnung <?= esc($monats_name) ?></h1>
    <div class="untertitel">Übersicht aller Beträge</div>

    <table class="posten">
        <thead>
            <tr>
                <th>Person</th>
                <th class="betrag-spalte">Betrag</th>
            </tr>
        </thead>
        <tbody>
            <?php $summe = 0.0; ?>
            <?php foreach ($personen as $eintrag): ?>
                <?php $summe += (float) $eintrag['betrag']; ?>
                <tr>
                    <td><?= esc($eintrag['person']) ?></td>
                    <td class="betrag-spalte"><?= esc(formatiere_betrag((float) $eintrag['betrag'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="summe">
                <td>Gesamt</td>
                <td class="betrag-spalte"><?= esc(formatiere_betrag($summe)) ?></td>
            </tr>
        </tbody>
    </table>

    <p class="hinweis">
        Bitte die Beträge zeitnah überweisen oder direkt beim Kassenwart begleichen.
    </p>

<?php elseif ($typ === 'inventur'): ?>

    <h1>Kassenwart – Aktueller Bestand</h1>
    <div class="untertitel">Inventur zum <?= esc($datumAnzeige) ?></div>

    <table class="posten">
        <thead>
            <tr>
                <th>Position</th>
                <th class="betrag-spalte">Betrag</th>
            </tr>
        </thead>
        <tbody>
            <tr><td colspan="2"><strong>I. Kassenbestand</strong></td></tr>
            <?php foreach ($kontostaende as $konto => $daten): ?>
                <tr>
                    <td><?= esc(konto_label($konto)) ?></td>
                    <td class="betrag-spalte"><?= esc(formatiere_betrag((float) $daten['saldo'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="summe">
                <td>Summe I</td>
                <td class="betrag-spalte"><?= esc(formatiere_betrag((float) $summe_kassen)) ?></td>
            </tr>

            <?php $bloecke = [
                'forderung' => ['roemisch' => 'II', 'titel' => 'Forderungen'],
                'verbindlichkeit' => ['roemisch' => 'III', 'titel' => 'Verbindlichkeiten'],
            ]; ?>
            <?php foreach ($bloecke as $key => $block): ?>
                <tr><td colspan="2"><strong><?= esc($block['roemisch'] . '. ' . $block['titel']) ?></strong></td></tr>
                <?php foreach (schuld_kategorie_optionen() as $kategorie => $label): ?>
                    <tr>
                        <td><?= esc($label) ?></td>
                        <td class="betrag-spalte"><?= esc(formatiere_betrag((float) ($inventur[$key][$kategorie] ?? 0))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr class="summe">
                    <td>Summe <?= esc($block['roemisch']) ?></td>
                    <td class="betrag-spalte"><?= esc(formatiere_betrag((float) ($inventur[$key]['summe'] ?? 0))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="betrag-kasten">
        <div class="label">Summe Gesamt (I + II − III)</div>
        <div class="betrag"><?= esc(formatiere_betrag((float) $summe_gesamt)) ?></div>
    </div>

<?php else: /* coleur_bund */ ?>

    <h1>Getränkerechnung <?= esc($monats_name) ?> – <?= esc($label) ?></h1>
    <div class="untertitel">Anteil <?= esc($label) ?> gemäß Getränkeliste</div>

    <table class="posten">
        <thead>
            <tr>
                <th>Position</th>
                <th class="betrag-spalte">Betrag</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Getränke <?= esc($monats_name) ?> – Anteil <?= esc($label) ?></td>
                <td class="betrag-spalte"><?= esc(formatiere_betrag((float) $betrag)) ?></td>
            </tr>
            <tr class="summe">
                <td>Gesamt</td>
                <td class="betrag-spalte"><?= esc(formatiere_betrag((float) $betrag)) ?></td>
            </tr>
        </tbody>
    </table>

    <p class="hinweis">
        Grundlage ist die monatliche Getränkeliste des Getränkewarts;
        der Beleg ist Teil der AH-Abrechnung.
    </p>

<?php endif; ?>

<div class="fusszeile">
    Verein deutscher Studenten zu Erlangen · Erstellt am <?= esc($datumAnzeige) ?><?= $kassenwart !== '' ? ' · Kassenwart: ' . esc($kassenwart) : '' ?>
</div>

</body>
</html>
