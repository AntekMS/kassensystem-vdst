<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Pager der Listen (Issue #105) — Bootstrap-Markup, deutsche Labels, links
 * die Bereichsanzeige („26–50 von 112"). Bei nur einer Seite bleibt er leer.
 * Die Links behalten die Filter-GET-Parameter der aktuellen Seite.
 *
 * @var PagerRenderer $pager
 */
if ($pager->getPageCount() <= 1) {
    return;
}

$pager->setSurroundCount(2);
$links = $pager->links();
$ersteImFenster = (int) ($links[0]['title'] ?? 1);
$letzteImFenster = (int) (end($links)['title'] ?? 1);
?>
<div class="pager-leiste">
<small class="text-muted"><?= esc(seiten_bereich_text($pager->getCurrentPageNumber(), (int) $pager->getPerPage(), (int) $pager->getTotal())) ?></small>
<nav aria-label="Seitennavigation">
    <ul class="pagination pagination-sm pagination-vdst mb-0">
        <?php if ($pager->hasPreviousPage()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getPreviousPage()) ?>" title="Vorherige Seite" aria-label="Vorherige Seite">
                    <i class="bi bi-chevron-left" aria-hidden="true"></i>
                </a>
            </li>
        <?php endif; ?>

        <?php if ($pager->hasPrevious()): ?>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getFirst()) ?>">1</a></li>
            <?php if ($ersteImFenster > 2): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($links as $link): ?>
            <li class="page-item<?= $link['active'] ? ' active' : '' ?>">
                <a class="page-link" href="<?= esc($link['uri']) ?>"<?= $link['active'] ? ' aria-current="page"' : '' ?>><?= $link['title'] ?></a>
            </li>
        <?php endforeach; ?>

        <?php if ($pager->hasNext()): ?>
            <?php if ($letzteImFenster < $pager->getPageCount() - 1): ?>
                <li class="page-item disabled"><span class="page-link">…</span></li>
            <?php endif; ?>
            <li class="page-item"><a class="page-link" href="<?= esc($pager->getLast()) ?>"><?= $pager->getPageCount() ?></a></li>
        <?php endif; ?>

        <?php if ($pager->hasNextPage()): ?>
            <li class="page-item">
                <a class="page-link" href="<?= esc($pager->getNextPage()) ?>" title="Nächste Seite" aria-label="Nächste Seite">
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
</div>
