# VDSt Kassensystem - Vollständige Systemdokumentation

## Projektübersicht

**Name:** VDSt Kassensystem  
**Framework:** CodeIgniter 4  
**Zweck:** Digitales Kassenbuch und Abrechnungssystem für den Verein deutscher Studenten  
**Hauptfunktionen:** Kassenbuch-Verwaltung, Beleg-Upload, AH²/HV-Abrechnungen, Excel-Export

---

## Systemarchitektur

### Tech-Stack
- **Backend:** PHP 8+ mit CodeIgniter 4
- **Frontend:** Bootstrap 5 + Vanilla JavaScript
- **Datenbank:** MySQL/MariaDB
- **Datei-Verwaltung:** Lokaler File-Upload mit automatischer Organisation
- **Excel-Export:** PhpOffice/PhpSpreadsheet
- **Design:** VDSt-Farben (Schwarz/Weiß/Rot) - funktional, klassisch

### Ordner-Struktur
```
app/
├── Controllers/
│   ├── DashboardController.php
│   ├── BuchungenController.php (Kassenbuch)
│   ├── BelegeController.php
│   ├── AhAbrechnungenController.php
│   └── HvAbrechnungenController.php
├── Models/
│   ├── BelegModel.php (Kern-Model)
│   ├── BuchungModel.php
│   ├── AhAbrechnungModel.php
│   ├── HvAbrechnungModel.php
│   └── AbrechnungBelegModel.php (Verknüpfungen)
├── Views/
│   ├── layouts/main.php
│   ├── dashboard/index.php
│   ├── buchungen/ (Kassenbuch-Views)
│   ├── belege/
│   └── abrechnungen/ (gemeinsame Views für AH² + HV)
├── Helpers/
│   └── ExcelHelper.php (Excel-Export-Funktionen)
└── Config/Routes.php

public/uploads/belege/YYYY/MM/ (Datei-Organisation)
```

---

## Datenbank-Design

### Kern-Tabellen

#### 1. `belege` (Herzstück)
```sql
- id (PK)
- belegnummer (UNIQUE, Format: YYYY-MM-DD-001)
- rechnungsdatum (wichtig für Belegnummer!)
- eingabedatum 
- beschreibung
- betrag
- lieferant
- dateiname_original, dateiname_system, dateipfad
- dateityp (pdf, jpg, jpeg, png)
- kategorie (normal, ah_berechtigt, hv_berechtigt)
- status (erfasst, in_abrechnung, abgerechnet, bezahlt)
- notizen
```

#### 2. `buchungen` (Kassenbuch)
```sql
- id (PK)
- beleg_id (FK zu belege, NULL möglich)
- buchungsdatum
- beschreibung, betrag
- konto_typ (aktivenkasse, getraenkekasse, barkasse)
- buchungsart (einnahme, ausgabe)
- notizen
```

#### 3. `ah_abrechnungen` / `hv_abrechnungen`
```sql
- id (PK)
- abrechnungsmonat (YYYY-MM)
- titel
- status (entwurf, ausstehend, eingereicht, bezahlt)
- gesamtsumme (automatisch berechnet)
- begruendung (nur HV)
- notizen
```

#### 4. `abrechnung_belege` (Verknüpfung)
```sql
- beleg_id (FK)
- abrechnung_typ (ah, hv)
- abrechnung_id (FK)
- hinzugefuegt_am
```

### Besondere Features
- **Automatische Triggers** berechnen Gesamtsummen
- **Belegnummer-Generator** basierend auf Rechnungsdatum
- **Flexible Datum-Behandlung** (Rechnungsdatum ≠ Eingabedatum ≠ Buchungsdatum)

---

## Core-Funktionalitäten

### 1. Kassenbuch (Kern des Systems)

**Controller:** `BuchungenController`  
**Views:** `buchungen/index.php`, `buchungen/create.php`, `buchungen/edit.php`

**Funktionen:**
- **Hauptansicht**: Tabellarische Darstellung aller Buchungen
- **Kontostand-Berechnung**: Automatische Salden für alle 3 Konten
- **Filter**: Datum, Konto-Typ, Buchungsart, Freitext-Suche
- **Buchung erstellen**: Mit/ohne Beleg-Verknüpfung
- **Excel-Export**: Kassenbuch im Original-Format

**Workflow:**
1. Buchung eingeben (Datum, Betrag, Konto, Ein/Ausgabe)
2. Optional: Beleg verknüpfen (Upload oder Auswahl)
3. Automatische Kontostand-Aktualisierung
4. Excel-Export für Steuerberater

### 2. Beleg-Verwaltung

**Controller:** `BelegeController`  
**Views:** `belege/index.php`, `belege/create.php`, `belege/show.php`, `belege/edit.php`

**Funktionen:**
- **Upload**: PDF, JPG, PNG mit automatischer Belegnummer-Generierung
- **Datei-Organisation**: Automatisch nach `/uploads/belege/YYYY/MM/`
- **Umbenennung**: `YYYY-MM-DD-001.ext` basierend auf Rechnungsdatum
- **Kategorisierung**: Normal, AH²-berechtigt, HV-berechtigt
- **Vorschau**: PDF-Viewer und Bild-Anzeige im Browser
- **Status-Tracking**: erfasst → in_abrechnung → abgerechnet → bezahlt

**Besonderheiten:**
- **Rechnungsdatum bestimmt Belegnummer** (nicht Eingabedatum!)
- Bei Datum-Änderung: Neue Belegnummer + Datei-Verschiebung
- Bearbeitung nur möglich wenn Status = "erfasst"

### 3. AH²-Abrechnungen

**Controller:** `AhAbrechnungenController`  
**Views:** Gemeinsame Views in `abrechnungen/` (mit `$typ = 'ah'`)

**Funktionen:**
- **Monatliche Abrechnungen** erstellen
- **Beleg-Auswahl**: Nur AH²-berechtigte Belege
- **AJAX-Management**: Belege hinzufügen/entfernen ohne Reload
- **Status-Workflow**: entwurf → ausstehend → eingereicht → bezahlt
- **Excel-Export**: Format wie Original AH²-Abrechnung

**Workflow:**
1. Neue Abrechnung für Monat erstellen
2. Belege auswählen (auch aus anderen Monaten möglich!)
3. Vorschau prüfen
4. Status auf "ausstehend" → Excel downloaden → einreichen

### 4. HV-Abrechnungen (Heimverein)

**Controller:** `HvAbrechnungenController`  
**Views:** Gemeinsame Views in `abrechnungen/` (mit `$typ = 'hv'`)

**Funktionen:**
- Wie AH²-Abrechnungen, aber mit **Begründungen**
- **Automatische Begründungs-Generierung** basierend auf Schlüsselwörtern
- **Manuelle Begründung** pro Abrechnung
- **Excel-Export** mit Begründungs-Spalte

**Auto-Begründungen:**
- "Farbe, Streichen" → "Renovierung und Instandhaltung"
- "Regal, Möbel" → "Möblierung der Räume"
- "Werkzeug" → "Wartung und Reparatur"
- Standard → "Notwendige Ausgabe für das Vereinshaus"

### 5. Dashboard

**Controller:** `DashboardController`  
**View:** `dashboard/index.php`

**Funktionen:**
- **Kontostand-Übersicht**: Alle 3 Kassen + Gesamtsaldo
- **Schnellzugriff**: Buttons für häufige Aktionen
- **Statistiken**: Belege, Buchungen, Abrechnungen (nur Zahlen)
- **Aktivitäten**: Letzte 5 Buchungen und Belege
- **System-Alerts**: Warnungen für wichtige Aufgaben

---

## Gemeinsame Views (Effizienz-Feature)

### Konzept
Statt 8 separater Views für AH² und HV gibt es nur 4 gemeinsame Views, die beide Typen über die `$typ` Variable handhaben.

### Views:
- `abrechnungen/index.php` - Übersicht (beide Typen)
- `abrechnungen/create.php` - Erstellen (beide Typen)
- `abrechnungen/select_belege.php` - **Herzstück**: Beleg-Auswahl mit AJAX
- `abrechnungen/preview.php` - Vorschau vor Export

### Typ-Unterscheidung:
```php
// In Controller:
$data['typ'] = 'ah'; // oder 'hv'

// In View:
<?php if ($typ === 'ah'): ?>
    AH²-spezifischer Inhalt
<?php else: ?>
    HV-spezifischer Inhalt
<?php endif; ?>
```

---

## Excel-Export System

### Technologie
**PhpOffice/PhpSpreadsheet** für echte `.xlsx` Dateien (nicht CSV)

### Export-Typen

#### 1. Kassenbuch-Export
**Methode:** `ExcelHelper::erstelleKassenbuch()`  
**Format:** Exakt wie Original-Kassenbuch
```
B1: Datum | C1: Beschreibung | D1: Beleg Nr. | E1: Aktivenkasse | G1: Getränkekasse | I1: Barkasse | K1: Gesamt
E2: Ein   | F2: Aus         |               | G2: Ein          | H2: Aus          | I2: Ein      | J2: Aus
E3: Kontostand-Zeile mit aktuellen Salden
B4+: Buchungen mit Beträgen in richtigen Spalten
```

#### 2. AH²-Abrechnung
**Methode:** `ExcelHelper::erstelleAhAbrechnung()`  
**Format:** Wie Original AH-Sheet
```
A2: Name | B2: Datum | C2: Grund | D2: Betrag | ... | L2: Gesamt: | M2: Gesamtsumme
G3+: Belege mit Name, Datum, Beschreibung, Betrag
```

#### 3. HV-Abrechnung
**Methode:** `ExcelHelper::erstelleHvAbrechnung()`  
**Format:** Mit Begründungen
```
A1: HV Abrechnung Titel
A4: Allgemeine Begründung
A7: Belegnummer | B7: Datum | C7: Beschreibung | D7: Lieferant | E7: Betrag | F7: Begründung
```

### Formatierung
- **Euro-Format**: `#,##0.00 "€";[Red]-#,##0.00 "€"`
- **Datum-Format**: `DD.MM.YYYY`
- **Spaltenbreiten**: Angepasst an Inhalt
- **Fette Header**: Wie im Original

---

## AJAX-System (Beleg-Verwaltung)

### Kern-Feature: select_belege.php
**Zweiteilige Ansicht:**
- **Links**: Verfügbare Belege (filter nach Kategorie)
- **Rechts**: Ausgewählte Belege für Abrechnung

### AJAX-Endpunkte:
```php
POST /abrechnungen/{typ}/addBeleg/{id}     // Beleg hinzufügen
POST /abrechnungen/{typ}/removeBeleg/{id}  // Beleg entfernen
```

### JavaScript-Funktionen:
- `addBelegToAbrechnung()` - AJAX-Request + DOM-Update
- `removeBelegFromAbrechnung()` - AJAX-Request + DOM-Update
- `updateGesamtsumme()` - Live-Summenberechnung
- `updateBelegAnzahl()` - Badge-Updates

### Response-Format:
```json
{
  "success": true,
  "message": "Beleg wurde hinzugefügt",
  "neue_gesamtsumme": "1.234,56 €"
}
```

---

## Datei-Management System

### Upload-Workflow
1. **Datei-Upload** (PDF/JPG/PNG)
2. **Belegnummer-Generierung** basierend auf Rechnungsdatum
3. **Datei-Umbenennung** zu `YYYY-MM-DD-001.ext`
4. **Ordner-Erstellung** `/uploads/belege/YYYY/MM/`
5. **Datei-Verschiebung** in Zielordner
6. **DB-Eintrag** mit allen Metadaten

### Datei-Organisation
```
public/uploads/belege/
├── 2024/
│   ├── 01/
│   │   ├── 2024-01-15-001.pdf
│   │   └── 2024-01-15-002.jpg
│   └── 02/
└── 2025/
    └── 01/
```

### Sicherheit
- **Dateityp-Validierung**: Nur PDF, JPG, PNG
- **Größen-Limit**: 10MB per Datei
- **Eindeutige Namen**: Keine Kollisionen möglich

---

## Business Logic

### Belegnummer-System
**Format:** `YYYY-MM-DD-001`  
**Basis:** Rechnungsdatum (nicht Eingabedatum!)  
**Generator:** `BelegModel::generiereNaechsteBelegnummer()`

```php
// Beispiel:
Rechnung vom 15.01.2025 → 2025-01-15-001
Zweite Rechnung vom selben Tag → 2025-01-15-002
```

### Status-Workflow

#### Belege:
1. **erfasst** - Neu eingegeben, bearbeitbar
2. **in_abrechnung** - In AH²/HV-Abrechnung zugeordnet
3. **abgerechnet** - Abrechnung eingereicht
4. **bezahlt** - Geld erhalten

#### Abrechnungen:
1. **entwurf** - Bearbeitbar, löschbar
2. **ausstehend** - Fertig, wartet auf Einreichung
3. **eingereicht** - An AH²/HV geschickt
4. **bezahlt** - Geld erhalten

### Flexible Datum-Behandlung
**Problem:** Belege kommen oft verspätet an  
**Lösung:** Trennung von Rechnungsdatum und Eingabedatum

**Beispiel:**
- April-Rechnung kommt im Juni an
- Belegnummer: `2024-04-15-001` (nach Rechnungsdatum)
- Kann trotzdem in Juni-Abrechnung

---

## VDSt-Design-System

### Farbschema
```css
--vdst-schwarz: #000000;
--vdst-weiss: #ffffff;
--vdst-rot: #dc143c;
--vdst-grau: #f8f9fa;
--vdst-dunkelgrau: #343a40;
```

### Design-Prinzipien
- **Klassisch & Funktional** - keine modernen Spielereien
- **VDSt-Tradition** - Schwarz/Weiß/Rot wie Kaiserreich 1881
- **Desktop-First** - keine Mobile-Optimierung nötig
- **Klarheit vor Schönheit** - Lesbarkeit wichtiger als Design

### UI-Komponenten
- **Navigation**: Schwarze Leiste mit weißer Schrift
- **Buttons**: `.btn-vdst` (schwarz), `.btn-outline-vdst`
- **Tables**: `.table-vdst` für Header
- **Cards**: `.card-vdst` mit schwarzem Header
- **Badges**: Status-abhängige Farben

---

## Entwickler-Hinweise

### Model-Struktur
Jedes Model hat Standard-CRUD plus Business-Logic:
- **Validation Rules** in Model definiert
- **Custom Methods** für Business-Logic
- **Relationships** über Foreign Keys
- **Helper Methods** für Formatierung

### Controller-Pattern
```php
// Standard-Struktur:
public function index()     // Übersicht
public function create()    // Formular
public function store()     // Speichern + Validation
public function show($id)   // Details
public function edit($id)   // Bearbeiten
public function update($id) // Änderungen speichern
public function delete($id) // Löschen
```

### View-Pattern
```php
// Layout-Vererbung:
<?= $this->extend('layouts/main') ?>
<?= $this->section('title') ?>Titel<?= $this->endSection() ?>
<?= $this->section('content') ?>Inhalt<?= $this->endSection() ?>
<?= $this->section('scripts') ?>JS<?= $this->endSection() ?>
```

### Error Handling
- **Model-Validation** mit deutschen Fehlermeldungen
- **Controller Try-Catch** für kritische Operationen
- **Flash-Messages** für User-Feedback
- **Log-Ausgabe** für Debugging

---

## Installation & Setup

### Anforderungen
- PHP 8.0+
- MySQL/MariaDB
- Composer
- CodeIgniter 4
- PhpOffice/PhpSpreadsheet

### Installation
```bash
# 1. Composer Dependencies
composer require phpoffice/phpspreadsheet

# 2. Upload-Ordner erstellen
mkdir public/uploads/belege
chmod 755 public/uploads -R

# 3. Datenbank importieren
mysql -u user -p database < kassensystem.sql
```

### Datei-Struktur erstellen
```
app/Controllers/ - Alle 5 Controller
app/Models/ - Alle 5 Models  
app/Views/ - Alle Views wie dokumentiert
app/Helpers/ExcelHelper.php
app/Config/Routes.php
```

---

## Testing & Debugging

### Test-Workflow
1. **Dashboard** aufrufen - Grundfunktion
2. **Beleg hochladen** - Datei-System testen
3. **Buchung erstellen** - Kassenbuch testen
4. **Abrechnung erstellen** - AJAX testen
5. **Excel-Export** - PhpSpreadsheet testen

### Häufige Probleme
- **Composer fehlt**: PhpSpreadsheet nicht installiert
- **Upload-Rechte**: 755 Permissions auf uploads/
- **SQL-Syntax**: ORDER BY ohne Tabellen-Präfix
- **Views nicht gefunden**: Pfade in Controller prüfen

### Debug-Tipps
```php
// In Controller für AJAX-Debug:
log_message('error', 'DEBUG: ' . json_encode($data));

// SQL-Debug in Model:
echo $this->db->getLastQuery();
```

---

## Wartung & Erweiterung

### Regelmäßige Aufgaben
- **Upload-Ordner aufräumen** (alte Dateien archivieren)
- **Datenbank-Backups** vor größeren Änderungen
- **Log-Dateien** überwachen

### Erweiterungsmöglichkeiten
- **Benutzer-System** (aktuell Single-User)
- **API-Endpoints** für externe Integration
- **Automatische Backups**
- **PDF-Generation** statt nur Excel
- **E-Mail-Benachrichtigungen**

### Code-Qualität
- **PSR-12** Coding Standards befolgen
- **Kommentierung** in deutsch (passt zum Projekt)
- **Model-Separation** für Business Logic
- **View-Components** für Wiederverwendung

---

## Fazit

Das VDSt Kassensystem ist ein **funktionales, klassisches Web-System** das die traditionellen Arbeitsabläufe des Kassenwarts digitalisiert ohne die bewährten Strukturen zu verändern. Es kombiniert moderne Web-Technologien mit dem klassischen VDSt-Design und fokussiert auf **Effizienz und Zuverlässigkeit** statt auf moderne UI-Trends.

**Kernstärken:**
- Exakte Nachbildung der Excel-Kassenbuch-Struktur
- Intelligente Beleg-Verwaltung mit flexibler Zuordnung
- Automatisierte Arbeitsprozesse bei Beibehaltung der Kontrolle
- Klassisches, zeitloses Design im VDSt-Stil
- Einfache Wartung und Erweiterbarkeit

**Zielgruppe:** Kassenwarte die Effizienz wollen aber ihre bewährten Arbeitsweisen beibehalten möchten.