# VDSt Kassensystem - Vollständige Systemdokumentation

## Projektübersicht

**Name:** VDSt Kassensystem  
**Framework:** CodeIgniter 4  
**Version:** 1.0  
**Zweck:** Digitales Kassenbuch und Abrechnungssystem für den Verein deutscher Studenten

### Hauptfunktionen
- **Kassenbuch-Verwaltung** mit 3 Konten (Aktivenkasse, Getränkekasse, Barkasse)
- **Beleg-Upload** mit automatischer Belegnummer-Generierung
- **AH²/HV-Abrechnungen** mit Excel-Export
- **ZIP-Archive** mit allen Beleg-Dateien
- **Erweiterte Such- und Filterfunktionen**
- **Session-Management** mit automatischer Verlängerung

---

## Tech-Stack & Architektur

### Backend
- **PHP 8+** mit CodeIgniter 4
- **MySQL/MariaDB**
- **PhpOffice/PhpSpreadsheet** für Excel-Export
- **Automatische Datei-Organisation** nach Datum

### Frontend
- **Bootstrap 5** + Vanilla JavaScript
- **AJAX-basierte** Beleg-Zuordnung
- **VDSt-Design** (Schwarz/Weiß/Rot)
- **Responsive** für Desktop-Nutzung

### Sicherheit
- **Master-Passwort** System (.env konfiguriert)
- **Session-Timeout** mit Warnungen
- **CSRF-Protection**
- **Datei-Upload-Validierung**

---

## Installation & Setup

### Voraussetzungen
```bash
- PHP 8.0+
- MySQL/MariaDB 5.7+
- Composer
- Web-Server (Apache/Nginx)
- Optional: Docker
```

### 1. Installation
```bash
# Repository klonen
git clone [repository-url] vdst-kassensystem
cd vdst-kassensystem

# Dependencies installieren
composer install

# Umgebung konfigurieren
cp env .env
```

### 2. Konfiguration (.env)
```ini
# Datenbank
database.default.hostname = localhost
database.default.database = vdst_kassensystem
database.default.username = your_username
database.default.password = your_password

# VDSt Spezifisch
vdst.master_password = hier_ein_starkes_passwort
vdst.kassenwart_name = "Max Mustermann"
vdst.session_timeout = 28800

# Upload-Einstellungen
upload_max_filesize = 10M
post_max_size = 10M
```

### 3. Datenbank Setup
```bash
# Datenbank erstellen
mysql -u root -p -e "CREATE DATABASE vdst_kassensystem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Migrationen ausführen
php spark migrate

# Basis-Daten laden (optional für Test-System)
php spark migrate:refresh --seed
```

### 4. Verzeichnisse erstellen
```bash
# Upload-Ordner mit korrekten Rechten
mkdir -p public/uploads/belege
mkdir -p writable/temp/zip
chmod -R 755 public/uploads
chmod -R 755 writable
```

### 5. Web-Server Konfiguration

#### Apache (.htaccess bereits vorhanden)
```apache
DocumentRoot /pfad/zum/projekt/public
```

#### Nginx
```nginx
server {
    listen 80;
    server_name kassensystem.vdst.local;
    root /pfad/zum/projekt/public;
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### 6. Docker (Optional)
```yaml
# docker-compose.yml bereits im Projekt vorhanden
docker-compose up -d
```

---

## Systemarchitektur

### Datenbank-Design

#### Kern-Tabellen
```sql
belege              # Herzstück - alle Belege mit Dateien
├── buchungen      # Kassenbuch-Einträge (optional mit beleg_id)
├── ah_abrechnungen # AH² Monatsabrechnungen
├── hv_abrechnungen # HV Abrechnungen mit Begründungen
└── abrechnung_belege # Verknüpfungs-Tabelle (M:N)
```

#### Automatische Features
- **Gesamtsummen-Berechnung** in PHP bei jeder Beleg-Zuordnung
- **Belegnummer-Generator** basierend auf Rechnungsdatum
- **Datei-Organisation** nach `public/uploads/belege/YYYY/MM/`

### Controller-Struktur
```
DashboardController     # Übersicht + Schnellzugriff
BuchungenController     # Kassenbuch + Excel-Export
BelegeController        # Upload + Verwaltung + ZIP-Export
AhAbrechnungenController # AH² Abrechnungen + Export
HvAbrechnungenController # HV Abrechnungen + Begründungen
AuthController          # Master-Passwort System
```

---

## Nutzung

### 1. Anmeldung
- URL: `http://localhost/auth/login`
- Master-Passwort aus `.env` (`vdst.master_password`) — **unbedingt ein starkes Passwort setzen!**
- Session-Timeout: 8 Stunden (wird bei Aktivität verlängert)

### 2. Belege verwalten
```
/belege/create
├── Upload: PDF, JPG, PNG (max 10MB)
├── Automatische Belegnummer: YYYY-MM-DD-001
├── Kategorien: Normal, AH² berechtigt, HV berechtigt
└── Status-Tracking: erfasst → in_abrechnung → abgerechnet → bezahlt
```

### 3. Kassenbuch führen
```
/buchungen
├── 3 Konten: Aktivenkasse, Getränkekasse, Barkasse
├── Ein-/Ausgaben mit optionaler Beleg-Verknüpfung
├── Live-Kontostand-Berechnung
└── Excel-Export im Original-Format
```

### 4. Abrechnungen erstellen

#### AH² Abrechnungen (`/abrechnungen/ah`)
1. **Neue Abrechnung** für Monat erstellen
2. **Belege auswählen** (nur AH²-berechtigte)
3. **AJAX-Management** ohne Seitenreload
4. **Excel-Export** + ZIP mit allen Dateien

#### HV Abrechnungen (`/abrechnungen/hv`)
- Wie AH², aber mit **Freitext-Begründung** für den Heimverein
- Separate **Excel-Vorlage** für Heimverein

### 5. Export-Funktionen

#### Excel-Exporte
- **Kassenbuch**: Original-Format mit 3 Konten
- **Belege-Liste**: Filterfähige Übersicht
- **AH²-Abrechnung**: Excel-Vorlage für Einreichung
- **HV-Abrechnung**: Mit Freitext-Begründung

#### ZIP-Archive
- **Komplette Belege**: Excel + alle Original-Dateien
- **Aussagekräftige Dateinamen**: `01_2024-06-15-001_Beschreibung.pdf`
- **Info-Datei**: Übersicht und Zusammenfassung

---

## Besondere Features

### Automatische Belegnummer-Generierung
```php
// Beispiel: Rechnung vom 15.06.2024
"2024-06-15-001"  // Erste Rechnung des Tages
"2024-06-15-002"  // Zweite Rechnung des Tages
```

### Intelligente Beleg-Zuordnung
- **Flexible Datum-Behandlung**: Rechnungsdatum ≠ Eingabedatum
- **AJAX-Beleg-Auswahl** ohne Seitenreload
- **Live-Summenberechnung** bei Zuordnung
- **Status-Verfolgung** über gesamten Workflow

### Session-Management
- **8 Stunden Laufzeit**, wird bei jeder Server-Anfrage verlängert (Idle-Timeout)

### Erweiterte Suche
- **Volltext-Suche** in Beschreibungen, Notizen, Lieferanten
- **Datum-Filter** mit Von/Bis
- **Kategorie-Filter** für Beleg-Typen
- **Status-Filter** für Workflow-Stufen
- **Betrag-Filter** mit Min/Max

---

## Datei-Management

### Upload-System
```
Erlaubte Typen: PDF, JPG, JPEG, PNG
Maximale Größe: 10MB
Organisation: /uploads/belege/YYYY/MM/
Umbenennung: YYYY-MM-DD-001.ext
```

### Automatische Organisation
```
public/uploads/belege/
├── 2024/
│   ├── 01/ → Januar 2024
│   ├── 02/ → Februar 2024
│   └── ...
└── 2025/
    └── 01/ → Januar 2025
```

### Sicherheit
- **Dateityp-Validierung** auf Server-Seite
- **Größen-Limits** konfigurierbar
- **Eindeutige Dateinamen** verhindern Kollisionen
- **Virus-Scan** integration möglich

---

## Konfiguration

### Wichtige .env Einstellungen
```ini
# VDSt Spezifisch
vdst.master_password = "sicheres_passwort"
vdst.kassenwart_name = "Max Mustermann"
vdst.session_timeout = 28800  # 8 Stunden

# Upload-Limits
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300

# Für den Produktivbetrieb unbedingt setzen:
CI_ENVIRONMENT = production
```

---

## Wartung & Backup

### Regelmäßige Aufgaben
```bash
# Datenbank-Backup
mysqldump -u user -p vdst_kassensystem > backup_$(date +%Y%m%d).sql

# Upload-Ordner Backup
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz public/uploads/

# Log-Dateien prüfen
tail -f writable/logs/log-$(date +%Y-%m-%d).log
```

### Cleanup-Befehle
```bash
# Temporäre ZIP-Dateien löschen (älter als 1 Tag)
find writable/temp/zip/ -name "*.zip" -mtime +1 -delete

# Alte Log-Dateien archivieren (älter als 30 Tage)
find writable/logs/ -name "*.log" -mtime +30 -gzip
```

---

## Troubleshooting

### Häufige Probleme

#### Upload-Fehler
```bash
# Rechte prüfen
ls -la public/uploads/
chmod -R 755 public/uploads/

# PHP-Limits prüfen
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

#### Excel-Export Fehler
```bash
# PhpSpreadsheet Installation prüfen
composer show | grep phpoffice

# Memory-Limit erhöhen (php.ini)
memory_limit = 512M
```

#### Session-Probleme
```bash
# Session-Verzeichnis prüfen
ls -la writable/session/
chmod -R 755 writable/

# .env Session-Config prüfen
CI_ENCRYPTION_KEY = [32-Zeichen-Schlüssel]
```

#### Datenbank-Verbindung
```bash
# MySQL-Verbindung testen
mysql -h localhost -u username -p database_name

# CodeIgniter Debug aktivieren
CI_ENVIRONMENT = development
```

---

## Technische Details

### Performance-Optimierungen
- **Database Indexes** auf häufig genutzte Spalten
- **AJAX-Loading** für große Datenmengen
- **Lazy Loading** für Datei-Previews

### Sicherheits-Features
- **CSRF Protection** auf allen Forms
- **Input Validation** mit CodeIgniter Rules
- **File Type Validation** mit MIME-Type Check
- **SQL Injection Protection** durch Query Builder

### Browser-Kompatibilität
- **Chrome/Edge**: Vollständig unterstützt
- **Firefox**: Vollständig unterstützt
- **Safari**: Grundfunktionen unterstützt
- **Mobile**: Grundfunktionen (Desktop-optimiert)

---

## Development

### Lokale Entwicklung
```bash
# Development Server starten
php spark serve

# Database Reset (Vorsicht!)
php spark migrate:refresh

# Cache löschen
php spark cache:clear
```

### Code-Struktur
```
app/
├── Controllers/     # 5 Haupt-Controller
├── Models/         # 5 Models + Relationships
├── Views/          # Gemeinsame Views für AH²/HV
├── Helpers/        # ExcelHelper, ZipHelper
└── Filters/        # AuthFilter für Sicherheit
```

### Debugging
```php
// Log-Ausgabe in Controllern
log_message('error', 'Debug Info: ' . json_encode($data));

// SQL-Query Debug
echo $this->db->getLastQuery();

// CodeIgniter Toolbar aktivieren
$routes->set404Override('App\Controllers\Home::index');
```

---

## API-Dokumentation

### AJAX-Endpoints
```javascript
POST /abrechnungen/{typ}/addBeleg/{id}     // Beleg hinzufügen
POST /abrechnungen/{typ}/removeBeleg/{id}  // Beleg entfernen
GET  /belege/preview/{id}                  // Datei-Vorschau
```

### Response-Format
```json
{
  "success": true,
  "message": "Beleg wurde hinzugefügt",
  "neue_gesamtsumme": "1.234,56 €",
  "csrf_hash": "new_token"
}
```

---

## Support & Kontakt

### System-Info
- **Version**: 1.0.8
- **CodeIgniter**: 4.x
- **PHP Version**: 8.0+
- **Letzte Aktualisierung**: 2024

### Bei Problemen
1. **Logs prüfen**: `writable/logs/`
2. **Browser-Konsole** auf JavaScript-Fehler
3. **PHP Error Log** des Servers
4. **Datenbank-Logs** bei MySQL-Problemen

---

## Changelog

### Version 1.1.0 (Aufräum-Release)
- 🐛 Upload-Pfad-Bug behoben (Dateien landeten in `public/public/uploads/`; Migration verschiebt Bestandsdaten)
- 🐛 Deutsche Komma-Beträge (`10,50`) werden akzeptiert
- 🐛 „Ausstehend markieren“ und HV-Begründungs-Schnellspeichern funktionieren (CSRF/Titel fehlten)
- 🔒 XSS-Escaping in allen Views, Löschen nur noch per POST, Login gehärtet
- 🧹 Entfernt: Session-Timeout-Warnsystem, Keyboard-Shortcuts, HV-Auto-Begründungen,
  Dashboard-Quick-Upload, Buchungen-Listen-Excel, DB-Trigger/-Views, `system_einstellungen`
- ♻️ AH²/HV-Controller zusammengelegt, gemeinsame Upload-Logik, zentrale Label-Helper,
  geteiltes JS in `public/js/app.js`

### Version 1.0.8
- ✅ Vollständiges Kassenbuch-System
- ✅ Beleg-Upload mit automatischer Nummerierung
- ✅ AH²/HV-Abrechnungen mit Excel-Export
- ✅ ZIP-Archive mit allen Dateien
- ✅ Session-Management mit Timeout
- ✅ Erweiterte Such- und Filterfunktionen
- ✅ Master-Passwort Authentifizierung
- ✅ Docker-Integration