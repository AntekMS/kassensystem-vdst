# VDStE Kassensystem - Vollumfassendes Konzept

## 1. Systemübersicht

Das System funktioniert wie ein intelligenter digitaler Kassenwart, der automatisch:
- Buchungen erfasst und nummeriert (wie deine Excel-Tabelle)
- Belege nach Typ sortiert (AH², HV, normale Buchungen)
- Abrechnungen erstellt und zum Download bereitstellt
- Alles nachverfolgbar dokumentiert

## 2. Datenbank-Design

### Tabellen-Struktur:

```sql
-- Belege (KERN des Systems - wie dein physischer Ordner)
belege:
- id (Auto-Increment)
- belegnummer (VARCHAR) -- automatisch: YYYY-MM-DD-001 (nach Rechnungsdatum!)
- rechnungsdatum (DATE) -- Datum auf der Rechnung
- eingabedatum (DATE) -- Wann du es eingegeben hast
- beschreibung (TEXT)
- betrag (DECIMAL)
- lieferant (VARCHAR)
- dateiname_original (VARCHAR) -- ursprünglicher Dateiname
- dateiname_system (VARCHAR) -- umbenannt zu Belegnummer
- dateipfad (VARCHAR)
- dateityp (ENUM: 'pdf', 'jpg', 'png')
- kategorie (ENUM: 'normal', 'ah_berechtigt', 'hv_berechtigt')
- status (ENUM: 'erfasst', 'in_abrechnung', 'abgerechnet', 'bezahlt')
- created_at, updated_at

-- Buchungen (Verknüpfung zu Belegen + Kassenbuch-Einträge)
buchungen:
- id (Auto-Increment)
- beleg_id (Foreign Key, NULL möglich für beleglose Buchungen)
- buchungsdatum (DATE) -- Wann gebucht (kann != Rechnungsdatum)
- konto_typ (ENUM: 'aktivenkasse', 'getraenkekasse', 'barkasse')
- buchungsart (ENUM: 'ausgabe', 'einnahme')
- created_at, updated_at

-- AH² Abrechnungen (Sammelordner für Juni-Abrechnung etc.)
ah_abrechnungen:
- id
- abrechnungsmonat (VARCHAR) -- z.B. "2024-06"
- titel (VARCHAR) -- z.B. "AH² Abrechnung Juni 2024"
- erstellt_am (DATE)
- eingereicht_am (DATE, NULL)
- status (ENUM: 'entwurf', 'ausstehend', 'eingereicht', 'bezahlt')
- gesamtsumme (DECIMAL)
- notizen (TEXT)

-- HV Abrechnungen  
hv_abrechnungen:
- id
- abrechnungsmonat (VARCHAR)
- titel (VARCHAR) -- z.B. "HV Abrechnung Juni 2024 - Hausrenovierung"
- erstellt_am (DATE)
- eingereicht_am (DATE, NULL)
- status (ENUM: 'entwurf', 'ausstehend', 'eingereicht', 'bezahlt')
- gesamtsumme (DECIMAL)
- begruendung (TEXT)

-- Verknüpfung: Welche Belege sind in welcher Abrechnung?
abrechnung_belege:
- id
- beleg_id (Foreign Key)
- abrechnung_typ (ENUM: 'ah', 'hv')
- abrechnung_id (Foreign Key)
- hinzugefuegt_am (TIMESTAMP)
```

## 3. Controller-Struktur

### BelegeController (NEUER KERN)
**Analogie**: Wie ein intelligenter Aktenverwalter
- `index()` - Übersicht aller Belege (mit Suchfunktion)
- `create()` - Neuen Beleg mit Datei-Upload erfassen
- `store()` - Beleg speichern + Datei umbenennen + Belegnummer generieren
- `show()` - Beleg anzeigen + Datei-Vorschau
- `edit()` - Beleg bearbeiten
- `download()` - Original-Datei herunterladen

### BuchungenController
**Analogie**: Wie ein Kassenbuch-Schreiber
- `index()` - Kassenbuch-Übersicht (alle Buchungen)
- `create()` - Neue Buchung (mit optionaler Beleg-Verknüpfung)
- `store()` - Buchung speichern
- `exportExcel()` - Kassenbuch als Excel exportieren

### AbrechnungenController
**Analogie**: Wie ein Abrechnungs-Assistent mit Auswahlmöglichkeit
- `index()` - Übersicht aller Abrechnungen (AH² & HV)
- `create()` - Neue Abrechnung erstellen
- `selectBelege()` - **NEU**: Belege für Abrechnung auswählen
- `addBeleg()` - Einzelnen Beleg hinzufügen
- `removeBeleg()` - Beleg aus Abrechnung entfernen
- `preview()` - Abrechnung vor Download anzeigen
- `submit()` - Abrechnung als "ausstehend" markieren
- `downloadExcel()` - Abrechnung als Excel herunterladen

### DashboardController
**Analogie**: Wie ein Kontrollpult
- Übersicht über neue Belege
- Ausstehende Abrechnungen
- Kontostand-Übersicht
- Belege ohne Buchung

## 4. View-Struktur

### Layout
```
app/Views/
├── layouts/
│   └── main.php (Bootstrap + File-Preview JS)
├── belege/
│   ├── index.php (Tabelle aller Belege mit Vorschau)
│   ├── create.php (Upload-Formular)
│   ├── show.php (Beleg-Details + Datei-Anzeige)
│   └── edit.php
├── buchungen/
│   ├── index.php (Kassenbuch-Ansicht)
│   ├── create.php (mit Beleg-Auswahl-Dropdown)
│   └── edit.php
├── abrechnungen/
│   ├── index.php (Übersicht AH² & HV)
│   ├── create.php (Neue Abrechnung)
│   ├── select_belege.php (**NEU**: Checkbox-Liste verfügbarer Belege)
│   └── preview.php (Vorschau mit ausgewählten Belegen)
└── dashboard/
    └── index.php (Startseite mit Upload-Widget)
```

## 5. Funktionale Anforderungen

### Core-Features (MVP):

#### 5.1 Belege verwalten (**KERN-FEATURE**)
- **Upload**: PDF, JPG, PNG hochladen
- **Auto-Umbenennung**: `YYYY-MM-DD-001.pdf` (nach Rechnungsdatum!)
- **Belegnummer-Generator**: Basiert auf Rechnungsdatum, nicht Eingabedatum
- **Speicherort**: `/public/uploads/belege/YYYY/MM/`
- **Metadaten**: Lieferant, Betrag, Beschreibung aus Formular
- **Vorschau**: PDF-Viewer und Bild-Anzeige im Browser

#### 5.2 Buchungen verwalten
- **Beleg-Verknüpfung**: Optional einen Beleg auswählen
- **Kassenbuch-Export**: Alle Buchungen als Excel
- **Flexible Daten**: Buchungsdatum ≠ Rechnungsdatum möglich

#### 5.3 AH² Abrechnungen (**ÜBERARBEITET**)
- **Beleg-Auswahl**: Checkboxen für verfügbare Belege
- **Workflow**: Entwurf → Belege auswählen → Ausstehend → Eingereicht → Bezahlt
- **Excel-Export**: Mit Belegnummern und Dateipfaden
- **Flexibles Datum**: Juni-Abrechnung kann April-Belege enthalten

#### 5.4 HV Abrechnungen
- **Beleg-Auswahl**: Wie AH², aber mit Begründungstexten
- **Haus-spezifisch**: Filter nur HV-berechtigte Belege
- **Status-Tracking**: Wie AH²

#### 5.5 Intelligente Features
- **Dashboard-Alerts**: "5 neue Belege ohne Buchung"
- **Duplikats-Check**: Warnung bei ähnlichen Belegnummern
- **Datei-Sicherheit**: Validierung von Dateitypen

## 6. Technische Umsetzung

### 6.1 Belegnummer-Generator (**ÜBERARBEITET**)
```php
// BelegModel-Methode
public function generiereNaechsteBelegnummer($rechnungsdatum) {
    $tagesPrefix = date('Y-m-d', strtotime($rechnungsdatum));
    $anzahl = $this->where('DATE(rechnungsdatum)', $tagesPrefix)->countAllResults();
    return $tagesPrefix . '-' . str_pad($anzahl + 1, 3, '0', STR_PAD_LEFT);
}
```

### 6.2 Datei-Upload & Umbenennung
```php
// BelegeController
public function store() {
    $file = $this->request->getFile('beleg_datei');
    $rechnungsdatum = $this->request->getPost('rechnungsdatum');
    
    // Belegnummer generieren
    $belegnummer = $this->belegModel->generiereNaechsteBelegnummer($rechnungsdatum);
    
    // Datei umbenennen und speichern
    $jahr = date('Y', strtotime($rechnungsdatum));
    $monat = date('m', strtotime($rechnungsdatum));
    $pfad = "uploads/belege/{$jahr}/{$monat}/";
    
    $neuerDateiname = $belegnummer . '.' . $file->getExtension();
    $file->move($pfad, $neuerDateiname);
    
    // In DB speichern...
}
```

### 6.3 Beleg-Auswahl für Abrechnungen
```php
// AbrechnungenController::selectBelege()
public function selectBelege($abrechnungId) {
    // Verfügbare Belege laden (Status = 'erfasst' oder 'in_abrechnung')
    $verfuegbareBelege = $this->belegModel
        ->whereIn('status', ['erfasst', 'in_abrechnung'])
        ->where('kategorie', 'ah_berechtigt') // je nach Abrechnungstyp
        ->orderBy('rechnungsdatum', 'ASC')
        ->findAll();
    
    // Bereits ausgewählte Belege
    $ausgewaehlteBelege = $this->abrechnungBelegeModel
        ->where(['abrechnung_id' => $abrechnungId, 'abrechnung_typ' => 'ah'])
        ->findAll();
}
```

### 6.4 Excel-Export (PhpSpreadsheet)
- Installiere: `composer require phpoffice/phpspreadsheet`
- **Kassenbuch-Template**: Alle Buchungen chronologisch
- **Abrechnungs-Template**: Ausgewählte Belege mit Pfaden zu Dateien
- Automatische Summen und Belegnummer-Verlinkung

### 6.5 Datei-Sicherheit
```php
// Erlaubte Dateitypen
$erlaubteTypen = ['pdf', 'jpg', 'jpeg', 'png'];
$maxDateigroesse = 10 * 1024 * 1024; // 10MB

// Datei-Validierung
if (!in_array(strtolower($file->getExtension()), $erlaubteTypen)) {
    throw new \Exception('Nur PDF und Bilddateien erlaubt');
}
```

### 6.6 Einfache Navigation
```
Hauptmenü:
- Dashboard (Übersicht + Quick-Upload)
- Belege (Upload + Verwaltung) ← NEUE PRIORITÄT
- Buchungen (Kassenbuch)
- Abrechnungen (AH² & HV mit Beleg-Auswahl)
```

## 7. Implementierungsschritte

### Phase 1: Belege-Basis (2-3 Tage)
1. **Datenbank-Migration** für alle Tabellen
2. **BelegModel** mit Belegnummer-Generator
3. **Datei-Upload-Controller** mit Umbenennung
4. **Basis-Views** für Beleg-Upload und -Liste

### Phase 2: Buchungen-Integration (1-2 Tage)
1. **BuchungModel** mit Beleg-Verknüpfung
2. **Kassenbuch-Views** mit Beleg-Links
3. **Excel-Export** für Kassenbuch

### Phase 3: Abrechnungs-Workflow (3-4 Tage)
1. **Abrechnungs-Models** (AH² & HV)
2. **Beleg-Auswahl-Interface** (Checkboxen)
3. **Status-Management** (Entwurf → Ausstehend → Eingereicht)
4. **Excel-Templates** für Abrechnungen

### Phase 4: Dashboard & Verfeinerung (1-2 Tage)
1. **Dashboard** mit Upload-Widget und Alerts
2. **Datei-Vorschau** (PDF-Viewer, Bild-Anzeige)
3. **Such- und Filterfunktionen**
4. **Validierung** und Fehlerbehandlung

## 8. Datei-Struktur Vorschlag

```
app/
├── Controllers/
│   ├── BelegeController.php ← NEUER KERN
│   ├── BuchungenController.php
│   ├── AhAbrechnungenController.php
│   ├── HvAbrechnungenController.php
│   └── DashboardController.php
├── Models/
│   ├── BelegModel.php ← NEUER KERN
│   ├── BuchungModel.php
│   ├── AhAbrechnungModel.php
│   ├── HvAbrechnungModel.php
│   └── AbrechnungBelegModel.php ← NEU für Verknüpfungen
├── Views/
│   ├── belege/ ← NEUE PRIORITY
│   └── [rest wie oben beschrieben]
├── Database/Migrations/
│   └── 001_create_kassensystem_tables.php
├── Helpers/
│   ├── ExcelHelper.php
│   └── FileHelper.php ← NEU für Datei-Operationen
└── public/uploads/belege/
    ├── 2024/
    │   ├── 01/
    │   ├── 02/
    │   └── ...
    └── 2025/
```

## 9. Besonderheiten deines Systems (**ERWEITERT**)

- **Beleg-zentriert**: Belegnummer ist der Hauptschlüssel, nicht Buchungen
- **Flexible Zeiterfassung**: Rechnungsdatum ≠ Eingabedatum ≠ Buchungsdatum
- **Intelligente Abrechnungen**: Juni-Abrechnung kann April-Belege enthalten
- **Automatische Datei-Organisation**: `/2024/06/2024-06-15-001.pdf`
- **Status-Workflow**: Erfasst → In Abrechnung → Abgerechnet → Bezahlt
- **Beleg-Auswahl**: Checkboxen statt automatische Zuordnung
- **Doppelte Nachverfolgung**: Nach Belegnummer UND nach Abrechnungsmonat

## 10. Praktische Beispiele

### Szenario 1: Nachträglicher Beleg
1. **April**: Rechnung von Baumarkt (Datum: 2024-04-15)
2. **Juni**: Du bekommst endlich den Beleg
3. **System**: Speichert als `2024-04-15-001.pdf` (nach Rechnungsdatum!)
4. **Abrechnung**: Kann in Juni-AH²-Abrechnung eingefügt werden

### Szenario 2: Abrechnungs-Workflow
1. **Neue Abrechnung**: "AH² Juni 2024" erstellen
2. **Status**: "Entwurf"
3. **Belege auswählen**: Checkboxen für April-Beleg + Juni-Belege
4. **Excel generieren**: Download mit allen ausgewählten Belegen
5. **Status ändern**: "Ausstehend" → "Eingereicht" → "Bezahlt"

### Szenario 3: Dashboard-Übersicht
- "5 neue Belege ohne Buchung"
- "2 ausstehende AH²-Abrechnungen"
- "Quick-Upload: Drag & Drop für neuen Beleg"

## 10. Naming Conventions (Verbesserungsvorschläge)

- **Tabellen**: Singular, lowercase mit underscore (buchung, ah_abrechnung)
- **Controller**: PascalCase mit "Controller" (BuchungenController)
- **Models**: PascalCase mit "Model" (BuchungModel)
- **Variablen**: camelCase ($belegnummer, $abrechnungsmonat)
- **Konstanten**: UPPER_CASE (STATUS_OFFEN, KATEGORIE_AH)

Dieses System ist bewusst einfach gehalten und bildet genau deinen Workflow ab - vom Beleg bis zur fertigen Abrechnung, alles nachverfolgbar und automatisiert!