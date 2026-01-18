# Dolibarr Equipment Manager

**Version 4.0.0** | Professionelle Anlagenverwaltung mit Wartungsplanung, PWA & Checklisten

[![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

> **Hinweis:** Die Installation und Nutzung dieses Moduls erfolgt auf eigene Verantwortung. Es wird empfohlen, vor der Installation ein Backup der Datenbank und des Dolibarr-Verzeichnisses zu erstellen.

-----

## Features

### NEU in v4.0: Wartungsplaner

- **Wartungskalender** - Monatsansicht aller fälligen Wartungen
- **Wartungskarte** - Geografische Übersicht mit OpenStreetMap
- **Auto-Erstellung** - Serviceaufträge automatisch generieren
- **Vertragsverknüpfung** - Wartungsverträge pro Anlage zuweisen
- **Planzeit** - Individuelle oder typ-basierte Wartungsdauer
- **Wartungsintervall** - Jährlich (Standard) oder Halbjährlich
- **Mehrfachbearbeitung** - Bulk-Edit für Monat, Planzeit, Vertrag, Intervall

### Checklisten-System (v3.0)

- **Wartungs-Checklisten** - Vordefinierte Checklisten pro Anlagentyp
- **Abschnitte & Prüfpunkte** - Strukturierte Prüflisten mit OK/Mangel/N/A Bewertung
- **Kommentare** - Anmerkungen pro Prüfpunkt
- **PDF-Export** - Checklisten-Ergebnisse im Servicebericht-PDF
- **PWA-Integration** - Checklisten mobil ausfüllen (offline-fähig)

### Progressive Web App (PWA)

- **Mobile Offline-App** - Serviceberichte direkt vor Ort erfassen
- **Checklisten mobil** - Wartungschecklisten auf dem Smartphone ausfüllen
- **Installierbar** - Als App auf Smartphone/Tablet installieren
- **Offline-fähig** - Arbeiten ohne Internetverbindung, automatische Synchronisation
- **Multiple Einträge** - Mehrere Arbeitseinträge pro Anlage
- **Dark Mode** - Hell/Dunkel/Auto-Modus

### Kernfunktionen

- **Wartungs-Dashboard** - Fällige Wartungen auf einen Blick, gruppiert nach Standort
- **Automatische Nummerierung** - Equipment-Nummern (A000001, A000002, ...) automatisch oder manuell
- **Objektadressen** - Separate Lieferadressen pro Anlage für optimale Tourenplanung
- **Serviceauftrag-Integration** - Zweistufig: Wartung/Service mit automatischer Status-Synchronisation
- **Wartungs-Historie** - Vollständige Dokumentation aller Arbeiten mit Link zu Serviceaufträgen
- **PDF-Export** - Professionelle Serviceberichte mit Equipment-Details, Checklisten und Signaturen

### Equipment-Typen

Drehtürantrieb | Schiebetürantrieb | Brandschutztür | Brandschutztor | Türschließer | Feststellanlage | RWS | RWA | Sonstige

-----

## Installation

```bash
# 1. Download
cd /var/www/dolibarr/htdocs/custom
git clone https://github.com/Gerrett84/dolibarr_equipmentmanager.git equipmentmanager

# 2. Berechtigungen
chown -R www-data:www-data equipmentmanager
chmod -R 755 equipmentmanager

# 3. In Dolibarr aktivieren
# Setup -> Modules -> Equipment Manager -> Activate
```

**Voraussetzungen:** Dolibarr 16.0+, PHP 7.4+, MySQL/MariaDB

-----

## Wartungsplaner (v4.0)

### Wartungskalender

- Monatsbasierte Übersicht aller fälligen Wartungen
- Gruppierung nach Objektadresse
- Status-Anzeige: Ausstehend / In Bearbeitung / Erledigt
- Navigation zwischen Monaten

### Wartungskarte

- Geografische Darstellung aller Wartungsstandorte
- Automatische Geocodierung via OpenStreetMap/Nominatim
- Farbcodierung nach Wartungsstatus
- Popup mit Equipment-Details

### Auto-Erstellung von Serviceaufträgen

1. **Wartungs-Übersicht** -> Auto-Erstellung Icon
2. Monat auswählen
3. Vorschau der zu erstellenden Aufträge
4. "Serviceaufträge erstellen" klicken
5. Aufträge werden gruppiert nach Objektadresse erstellt

**Voraussetzungen für Auto-Erstellung:**
- Anlage hat Wartungsmonat gesetzt
- Anlage hat Vertrag verknüpft
- Kein offener Wartungsauftrag vorhanden

### Wartungsintervall

- **Jährlich** (Standard) - Wartung einmal pro Jahr im Wartungsmonat
- **Halbjährlich** - Wartung zweimal pro Jahr (Wartungsmonat + 6 Monate)

Beispiel: Wartungsmonat Januar + Halbjährlich = Wartung in Januar UND Juli

-----

## PWA (Mobile App)

### Zugriff

- **In Dolibarr:** Klick auf Home-Icon in der Top-Bar -> "Service Report PWA"
- **Direkt:** `https://ihr-dolibarr.de/custom/equipmentmanager/pwa/`

### Installation als App

1. PWA im Browser öffnen
2. **iOS:** Teilen -> "Zum Home-Bildschirm"
3. **Android:** Menü -> "App installieren"

### Funktionen

- Serviceaufträge anzeigen und bearbeiten
- Equipment mit Arbeitseinträgen dokumentieren
- Wartungs-Checklisten ausfüllen
- Material erfassen
- Kundenunterschrift vor Ort
- Dokumente hochladen (Fotos)
- Offline arbeiten

-----

## Schnellstart

### Equipment anlegen

1. **Equipment Manager -> Neue Anlage**
2. Ausfüllen: Nummer (auto), Typ, Kunde, Objektadresse
3. Wartungsmonat, Intervall, Planzeit, Vertrag zuweisen
4. Erstellen

### Wartung planen

1. **Wartungs-Übersicht** zeigt fällige Wartungen (inkl. nächster Monat)
2. **Wartungskalender** für Monatsübersicht
3. **Wartungskarte** für geografische Planung
4. **Auto-Erstellung** für automatische Serviceaufträge

### Mehrfachbearbeitung

1. **Anlagen nach Objektadresse** öffnen
2. Anlagen per Checkbox auswählen
3. Wartungsmonat, Intervall, Planzeit oder Vertrag wählen
4. "Anwenden" klicken

-----

## Changelog

### v4.0.0 (2025-01-18)

- **Wartungskalender** - Neue Monatsansicht aller Wartungen
- **Wartungskarte** - OpenStreetMap-Integration mit Geocodierung
- **Auto-Erstellung** - Serviceaufträge automatisch generieren
- **Vertragsverknüpfung** - Wartungsverträge pro Anlage
- **Planzeit** - Individuelle Wartungsdauer pro Anlage
- **Wartungsintervall** - Jährlich oder Halbjährlich
- **Bulk-Edit** - Mehrfachbearbeitung für alle Wartungsfelder
- **Objektadresse** - Automatische Verknüpfung bei Serviceaufträgen
- **Menü-Reorganisation** - Bessere Strukturierung der Navigation

### v3.1.x (2025-01)

- **PWA Checklisten** - Vollständige Offline-Unterstützung
- **Multi-Select** - Mehrfachauswahl für Equipment-Verknüpfung
- **Dark Mode** - Backend-weite Korrekturen
- **PDF-Verbesserungen** - Checklisten im PDF-Export

### v3.0.0 (2025-01)

- **Checklisten-System** - Komplettes Wartungschecklisten-Management
- **Abschnitte & Items** - Strukturierte Prüflisten
- **Ergebnis-Tracking** - OK/Mangel/N/A mit Kommentaren
- **Anlagentyp-spezifisch** - Unterschiedliche Checklisten je Typ
- **PWA-Integration** - Mobile Checklisten-Erfassung

### v2.x (2024-2025)

- **v2.4** - Adressbasierte Anlagenfilterung
- **v2.3** - Mehrfachauswahl & Workflow-Verbesserungen
- **v2.0** - Progressive Web App (PWA)

### v1.x (2024)

- **v1.6** - PDF-Export, Techniker-Unterschrift
- **v1.5** - Wartungs-Dashboard, Serviceauftrag-Integration
- **v1.0** - Grundfunktionen, Equipment-Verwaltung

-----

## Update auf v4.0

### Backup vor dem Update

```bash
# Datenbank sichern
mysqldump -u root -p dolibarr \
  llx_equipmentmanager_equipment \
  llx_equipmentmanager_intervention_link \
  llx_equipmentmanager_equipment_types \
  llx_equipmentmanager_checklist_templates \
  llx_equipmentmanager_checklist_sections \
  llx_equipmentmanager_checklist_items \
  llx_equipmentmanager_checklist_results \
  llx_equipmentmanager_checklist_item_results \
  > equipmentmanager_backup_$(date +%Y%m%d).sql
```

### Update durchführen

```bash
cd /var/www/dolibarr/htdocs/custom/equipmentmanager
git pull

# SQL-Migration für v4.0 ausführen:
mysql -u dolibarr -p dolibarr < sql/llx_equipmentmanager_v4.0.sql
```

### In Dolibarr

1. Modul deaktivieren
2. Modul aktivieren (aktualisiert Menüs)
3. Browser-Cache leeren

-----

## Troubleshooting

**Equipment erscheint nicht im Dashboard/Kalender?**

- Status = Aktiv?
- Wartungsmonat gesetzt?
- Bei halbjährlich: beide Monate werden angezeigt

**Auto-Erstellung zeigt keine Anlagen?**

- Vertrag verknüpft? (Pflichtfeld für Auto-Erstellung)
- Wartungsmonat = aktueller Monat?
- Kein offener Wartungsauftrag vorhanden?

**Wartungskarte zeigt keine Standorte?**

- Objektadressen mit vollständiger Adresse (Straße, PLZ, Ort)?
- Geocodierung erfolgt automatisch beim Laden

**Checkliste wird nicht angezeigt?**

- Equipment als "Wartung" (nicht "Service") verknüpft?
- Checklisten-Template für Anlagentyp vorhanden?

**PWA funktioniert nicht?**

- HTTPS erforderlich (außer localhost)
- Browser-Cache leeren
- Service Worker neu registrieren

-----

## Contributing

Pull Requests sind willkommen!

```bash
git checkout -b feature/NeuesFeature
git commit -m 'Add: Tolles Feature'
git push origin feature/NeuesFeature
# -> Pull Request erstellen
```

-----

## Lizenz

GPL v3 oder höher

-----

## Autor

**Gerrett84** - [GitHub](https://github.com/Gerrett84)

-----

**Feedback?** -> [GitHub Issues](https://github.com/Gerrett84/dolibarr_equipmentmanager/issues)

-----

**Current Version:** 4.0.0
**Released:** January 2025
**Compatibility:** Dolibarr 16.0+
