# Dolibarr Equipment Manager

**Version 4.1** | Professionelle Anlagenverwaltung mit PWA, Checklisten & Wartungsplanung

[![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

> **Hinweis:** Die Installation und Nutzung dieses Moduls erfolgt auf eigene Verantwortung. Es wird empfohlen, vor der Installation ein Backup der Datenbank und des Dolibarr-Verzeichnisses zu erstellen.

-----

## Features

### NEU in v3.0: Checklisten-System

- **Wartungs-Checklisten** - Vordefinierte Checklisten pro Anlagentyp
- **Abschnitte & Prüfpunkte** - Strukturierte Prüflisten mit OK/Mangel/N/A Bewertung
- **Kommentare** - Anmerkungen pro Prüfpunkt
- **PDF-Export** - Checklisten-Ergebnisse im Servicebericht-PDF
- **PWA-Integration** - Checklisten mobil ausfüllen (offline-fähig)
- **Wartung/Service Unterscheidung** - Checklisten nur bei Wartung, nicht bei Service

### NEU in v4.0: Gesamt-PDF & Checklisten-Editor

- **Gesamt-PDF** - Alle Serviceberichte in einem PDF exportieren
- **Checklisten-Editor** - Vorlagen direkt im Admin-Bereich bearbeiten
- **E-Mail-Anhänge** - Signierte PDFs & Checklisten automatisch an E-Mails anhängen
- **Verbesserte PDF-Ausgabe** - Besseres Layout für Checklisten und Signaturen

### NEU in v4.1: PWA-Statusfilter & Offline-Verbesserungen

- **Status-Tabs** - Auftragsübersicht mit Offen/Freigegeben/Erledigt Filtern
- **Zeitraum-Auswahl** - Erledigte Aufträge nach Zeitraum filtern (30 Tage, 3/6/12 Monate)
- **Besseres Offline-Caching** - Alle Anlagen für alle Aufträge offline verfügbar
- **Auto Re-Login** - Verbesserte Session-Wiederherstellung bei Ablauf
- **Foto-Komprimierung** - Automatische Bildkomprimierung vor Upload
- **Duplikat-Bereinigung** - Admin-Funktion zur Bereinigung doppelter Checklisten-Einträge

### v3.1: PWA-Verbesserungen & Dark Mode

- **Multi-Select Equipment** - Mehrere Anlagen gleichzeitig verknüpfen
- **Wartung/Service Badge** - Klare visuelle Unterscheidung
- **Dark Mode Fixes** - Vollständige Dark Mode Kompatibilität im Backend
- **Admin Cleanup** - Debug-Code entfernt, optimierte Setup-Seite

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

Drehtürantrieb | Schiebetürantrieb | Brandschutztür | Türschließer | Feststellanlage | RWS | RWA | Sonstige

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
2. Ausfüllen: Nummer (auto), Typ, Kunde, Objektadresse, Wartungsmonat
3. Erstellen

### Wartung planen

1. **Wartungs-Übersicht** zeigt fällige Wartungen (1 Monat Vorlauf)
2. **Serviceauftrag** erstellen -> Tab "Equipment" -> Als "Wartung" verknüpfen
3. Nach Erledigung: Equipment verschwindet automatisch

### Checkliste ausfüllen

1. Serviceauftrag -> Tab "Servicebericht"
2. Equipment auswählen (nur bei Wartung wird Checkliste angezeigt)
3. Prüfpunkte bewerten: OK / Mangel / N/A
4. Bei Mängeln: Kommentar hinzufügen
5. Speichern

### Servicebericht mit PWA

1. Serviceauftrag in PWA öffnen
2. Equipment auswählen
3. Checkliste ausfüllen (bei Wartung)
4. Arbeitseinträge hinzufügen
5. Material erfassen
6. Kundenunterschrift holen
7. Speichern & Freigeben

-----

## Changelog

### v4.1.0 (2025-01-24)

- **PWA Status-Filter** - Aufträge nach Offen/Freigegeben/Erledigt filtern
- **Zeitraum-Auswahl** - Erledigte Aufträge nach 30 Tage, 3/6/12 Monate oder alle filtern
- **Offline-Caching Fix** - Equipment-Store mit Composite Key für korrekte Offline-Speicherung
- **Status-Logik Fix** - Signierte Entwürfe werden korrekt als "Offen" angezeigt
- **Auto Re-Login** - Verbesserte Session-Wiederherstellung bei Ablauf
- **Foto-Upload** - Automatische Bildkomprimierung (max 1920px, JPEG 80%)
- **Upload-Fehlermeldungen** - Bessere Fehlerhinweise mit PHP-Limits
- **Duplikat-Bereinigung** - Admin-Funktion für doppelte Checklisten-Einträge

### v4.0.0 (2025-01-23)

- **Gesamt-PDF Export** - Alle Serviceberichte eines Auftrags in einem PDF
- **Checklisten-Editor** - Vorlagen direkt im Admin-Bereich bearbeiten
- **E-Mail-Anhänge** - Signierte PDFs und Checklisten automatisch an E-Mails anhängen
- **Verbesserte Checklisten-PDF** - Besseres Layout mit Kommentaren
- **formmail Hook** - Integration für automatische E-Mail-Anhänge

### v3.1.10 (2025-01-16)

- **Admin Cleanup** - Debug-Code aus PDF-Template-Bereich entfernt
- **Dark Mode Fix** - Signatur-Vorschau und Pad mit CSS-Variablen
- **Version Update** - Modulversion auf 3.1.10 aktualisiert

### v3.1.9 (2025-01-16)

- **Dark Mode** - Fixes für equipment_by_address.php
- **Badge-Styles** - Dolibarr Standard-Badge-Klassen verwendet

### v3.1.8 (2025-01-16)

- **Dark Mode Fixes** - Backend-weite Korrekturen für Dark Mode
- **Debug Cleanup** - Debug-Einträge aus API und PWA entfernt

### v3.1.7 (2025-01-15)

- **Wartung/Service Badge** - Anzeige im PWA nach rechts verschoben
- **Backend Badge** - Verknüpfungsart im Backend-Servicebericht angezeigt

### v3.1.0-3.1.6 (2025-01)

- **PWA Checklisten** - Vollständige Offline-Unterstützung
- **Multi-Select** - Mehrfachauswahl für Equipment-Verknüpfung
- **PDF-Verbesserungen** - Checklisten im PDF-Export
- **Workflow-Optimierungen** - Bessere Benutzerführung

### v3.0.0 (2025-01)

- **Checklisten-System** - Komplettes Wartungschecklisten-Management
- **Abschnitte & Items** - Strukturierte Prüflisten
- **Ergebnis-Tracking** - OK/Mangel/N/A mit Kommentaren
- **Anlagentyp-spezifisch** - Unterschiedliche Checklisten je Typ
- **PWA-Integration** - Mobile Checklisten-Erfassung

### v2.x (2024-2025)

- **v2.4** - Adressbasierte Anlagenfilterung
- **v2.3** - Mehrfachauswahl & Workflow-Verbesserungen
- **v2.2** - Wartungs-Dashboard Verbesserungen
- **v2.1** - Dark Mode & Auto-Login
- **v2.0** - Progressive Web App (PWA)

### v1.x (2024)

- **v1.6** - PDF-Export, Techniker-Unterschrift
- **v1.5** - Wartungs-Dashboard, Serviceauftrag-Integration
- **v1.0-1.4** - Grundfunktionen, Equipment-Verwaltung

-----

## Konfiguration

### Wartungsmonat-Logik

```
Equipment: Wartungsmonat Oktober (10)
Dashboard-Anzeige:
  - September (9): Vorlauf beginnt
  - Oktober (10): Hauptmonat
  - Nach Erledigung: Verschwindet

Jahreswechsel: Januar-Wartung zeigt ab Dezember
```

### Status-Bedeutung

- **Ausstehend** - Noch nicht begonnen
- **In Bearbeitung** - Serviceauftrag zugeordnet (Status 1-2)
- **Erledigt** - Serviceauftrag abgeschlossen (Status 3)

### Checklisten-Admin

Setup -> Equipment Manager -> Checklisten

- Vorlagen anzeigen und bearbeiten
- Abschnitte und Prüfpunkte verwalten
- Anlagentyp-Zuordnung

-----

## Update

### Backup vor dem Update

```bash
# Datenbank sichern
mysqldump -u root -p dolibarr \
  llx_equipmentmanager_equipment \
  llx_equipmentmanager_intervention_equipment \
  llx_equipmentmanager_equipment_socpeople \
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

# In Dolibarr:
# 1. Modul deaktivieren
# 2. Modul aktivieren (führt SQL-Updates aus)
# 3. Browser-Cache leeren
```

### Update auf v3.0

```bash
# SQL-Migration für Checklisten ausführen:
mysql -u dolibarr -p dolibarr < sql/llx_equipmentmanager_v3.0.sql
mysql -u dolibarr -p dolibarr < sql/llx_equipmentmanager_checklist.data.sql
```

-----

## Troubleshooting

**Equipment erscheint nicht im Dashboard?**

- Wartungsvertrag = Aktiv?
- Wartungsmonat gesetzt?
- Aktueller oder nächster Monat?

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

**Current Version:** 4.1.0
**Released:** January 2025
**Compatibility:** Dolibarr 16.0+
