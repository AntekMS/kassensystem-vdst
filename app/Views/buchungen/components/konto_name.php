<?php
// Einfaches Component für einheitliche Konto-Namen
$kontoNamen = [
    'aktivenkasse' => 'Aktivenkasse',
    'getraenkekasse' => 'Getränkekasse',
    'barkasse' => 'Barkasse'
];

// Variable prüfen und Standard setzen falls nicht vorhanden
$konto = $konto ?? 'unbekannt';

echo $kontoNamen[$konto] ?? ucfirst($konto);
?>