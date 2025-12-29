# Dolibarr Equipment Manager 🔧

**Version 2.0.0** | Professionelle Anlagenverwaltung mit PWA & Wartungsplanung

[![Dolibarr](https://img.shields.io/badge/Dolibarr-22.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

-----

## 🎯 Features

### NEU in v2.0: Progressive Web App (PWA)
- **Mobile Offline-App** - Serviceberichte direkt vor Ort erfassen
- **Installierbar** - Als App auf Smartphone/Tablet installieren
- **Offline-fähig** - Arbeiten ohne Internetverbindung, automatische Synchronisation
- **Multiple Einträge** - Mehrere Arbeitseinträge pro Anlage für mehrtägige Einsätze
- **Schneller Zugriff** - PWA-Link in Dolibarr Top-Bar

### Kernfunktionen
- **Wartungs-Dashboard** - Fällige Wartungen auf einen Blick, gruppiert nach Standort
- **Automatische Nummerierung** - Equipment-Nummern (A000001, A000002, …) automatisch oder manuell
- **Objektadressen** - Separate Lieferadressen pro Anlage für optimale Tourenplanung
- **Serviceauftrag-Integration** - Zweistufig: Wartung/Service mit automatischer Status-Synchronisation
- **Wartungs-Historie** - Vollständige Dokumentation aller Arbeiten mit Link zu Serviceaufträgen
- **Status-Tracking** - Ausstehend → In Bearbeitung → Erledigt
- **Manuelle Erledigung** - Für Sonderfälle außerhalb des Workflows
- **PDF-Export** - Professionelle Serviceberichte mit Equipment-Details, Material und Signaturen

### Equipment-Typen

Drehtürantrieb • Schiebetürantrieb • Brandschutztür • Türschließer • Feststellanlage • RWS • RWA

-----

## 📦 Installation

```bash
# 1. Download
cd /var/www/dolibarr/htdocs/custom
git clone https://github.com/Gerrett84/dolibarr_equipmentmanager.git equipmentmanager

# 2. Berechtigungen
chown -R www-data:www-data equipmentmanager
chmod -R 755 equipmentmanager

# 3. In Dolibarr aktivieren
# Setup → Modules → Equipment Manager → Activate
```

**Voraussetzungen:** Dolibarr 22.0+, PHP 7.4+, MySQL/MariaDB

-----

## 📱 PWA (Mobile App)

### Zugriff
- **In Dolibarr:** Klick auf 🏠-Icon in der Top-Bar → "Service Report PWA"
- **Direkt:** `https://ihr-dolibarr.de/custom/equipmentmanager/pwa/`

### Installation als App
1. PWA im Browser öffnen
2. **iOS:** Teilen → "Zum Home-Bildschirm"
3. **Android:** Menü → "App installieren"

### Funktionen
- Serviceaufträge anzeigen und bearbeiten
- Equipment mit Arbeitseinträgen dokumentieren
- Material erfassen
- Kundenunterschrift vor Ort
- Dokumente hochladen (Fotos)
- Offline arbeiten

-----

## 🚀 Schnellstart

### Equipment anlegen

1. **Equipment Manager → Neue Anlage**
1. Ausfüllen: Nummer (auto), Typ, Kunde, Objektadresse, Wartungsmonat
1. Erstellen

### Wartung planen

1. **Wartungs-Übersicht** zeigt fällige Wartungen (1 Monat Vorlauf)
1. **Serviceauftrag** erstellen → Tab "Equipment" → Als "Wartung" verknüpfen
1. Nach Erledigung: Equipment verschwindet automatisch

### Servicebericht mit PWA

1. Serviceauftrag in PWA öffnen
2. Equipment auswählen
3. Arbeitseinträge hinzufügen (Datum, Zeit, Arbeiten, Mängel)
4. Material erfassen
5. Kundenunterschrift holen
6. Speichern & Freigeben

-----

## 📝 Changelog

### v2.0.0 (2024-12-29)

- ✨ **Progressive Web App (PWA)** - Mobile Offline-App für Serviceberichte
- ✨ **Multiple Arbeitseinträge** - Mehrere Einträge pro Anlage für mehrtägige Einsätze
- ✨ **Kundenunterschrift in PWA** - Digitale Unterschrift vor Ort
- ✨ **Dokument-Upload** - Fotos direkt in der PWA hochladen
- ✨ **Online-Signatur** - Integration mit Dolibarr Online-Signatur
- ✨ **PWA-Link in Top-Bar** - Schneller Zugriff auf die Mobile App
- ✨ **REST-API** - Vollständige API für alle Equipment-Operationen
- 🎨 **PDF-Layout** - Dynamische Seitenumbrüche, letzte Anlage + Unterschrift auf einer Seite
- 🎨 **PDF-Formatierung** - Verbesserte Abstände und Linienführung
- 🐛 **Fix:** Mehrere Einträge pro Anlage möglich (entry_number)
- 🐛 **Fix:** PDF-Signatur korrekt positioniert

### v1.6.3 (2024-12-24)

- ✨ **Techniker-Unterschrift** - Unterschrift im Setup zeichnen und in allen PDFs automatisch einfügen
- ✨ **Signatur-Verwaltung** - Canvas-basierter Unterschriften-Pad mit Speichern/Löschen
- ✨ **Auto-Insert in PDF** - Gespeicherte Unterschrift wird automatisch ins PDF eingefügt
- 🎨 **PDF-Formatierung** - Verbesserte Zeitdarstellung mit Punkt nach "min."
- 🎨 **PDF-Layout** - Dauer rechtsbündig für bessere Konsistenz mit Gesamtdauer
- 📁 **Signatur-Speicherung** - Als transparentes PNG in `/equipmentmanager/signatures/`

### v1.6.2 (2024-12-23)

- ✨ **Produkt-Auswahl für Material** - Integration mit Dolibarr Produktkatalog
- ✨ **Auto-Fill** - Automatische Übernahme von Produktname und -preis
- ✨ **Auto-Freigabe** - Serviceauftrag kann direkt nach Bericht-Speicherung freigegeben werden
- 🏷️ **Tab umbenannt** - "Anlagen Details" → "Servicebericht" (passender zum Zweck)
- 🐛 **Fix:** Bearbeitung gespeicherter Serviceberichte funktioniert jetzt
- 🐛 **Fix:** Material-Dropdown zeigt nur Produkte (keine Leistungen/Services)
- 🐛 **Fix:** PDF-Seitenumbruch - Equipment-Berichte bleiben komplett auf einer Seite
- 🐛 **Fix:** Preis-Formatierung mit korrekter Dezimalanzahl

### v1.6.1 (2024-12-21)

- ✨ **PDF-Export für Serviceaufträge** - Professionelles Template mit Equipment-Details
- ✨ **Equipment-spezifische PDFs** - Arbeiten, Mängel, Empfehlungen pro Anlage
- ✨ **Material-Listen** - Verbrauchtes Material mit Preisen im PDF
- ✨ **Signaturen** - Unterschriftenfelder für Techniker und Kunde
- ✨ **Zusammenfassung** - Gesamt-Arbeitszeit und Materialkosten

### v1.5.1 (2024-12-19)

- ✨ **Letzte Wartung** auf Equipment-Karte mit Link zu Serviceauftrag
- ✨ **Icon in Top Bar** für schnellen Zugriff
- 🐛 **Fix:** Serviceauftrags-Link verwendet jetzt `ref` statt `id`
- 🐛 **Fix:** Status "In Bearbeitung" bereits ab Validierung (Status 1)
- 🎨 **Dark Mode:** Tabellenfarben mit rgba-Transparenz

### v1.5 (2024-12-18)

- ✨ Wartungs-Dashboard mit Standort-Gruppierung
- ✨ Jährliche Wartungsplanung pro Equipment (Wartungsmonat)
- ✨ Zweistufige Serviceauftrag-Integration (Wartung/Service)
- ✨ Manuelle Erledigung für Sonderfälle
- 🐛 Bug fixes und Performance-Verbesserungen

### Frühere Versionen

- **v1.4** (2024-12) - Getrennte Equipment-Ansicht, Suche nach Objektadresse
- **v1.3** (2024-11) - Automatische Equipment-Nummerierung
- **v1.0** (2024-10) - Erste Version

-----

## 🔧 Konfiguration

### Wartungsmonat-Logik

```
Equipment: Wartungsmonat Oktober (10)
Dashboard-Anzeige:
  ├─ September (9): Vorlauf beginnt
  ├─ Oktober (10): Hauptmonat
  └─ Nach Erledigung: Verschwindet

Jahreswechsel: Januar-Wartung zeigt ab Dezember
```

### Status-Bedeutung

- 🔴 **Ausstehend** - Noch nicht begonnen
- 🟢 **In Bearbeitung** - Serviceauftrag zugeordnet (Status 1-2)
- ✅ **Erledigt** - Serviceauftrag abgeschlossen (Status 3)

-----

## 🔄 Update

```bash
cd /var/www/dolibarr/htdocs/custom/equipmentmanager
git pull

# In Dolibarr:
# 1. Modul deaktivieren
# 2. Modul aktivieren (führt SQL-Updates aus)
# 3. Browser-Cache leeren
```

### Update auf v2.0

```bash
# SQL-Migration für Multiple Entries ausführen:
mysql -u dolibarr -p dolibarr < sql/llx_equipmentmanager_v1.7.sql
```

-----

## 🐛 Troubleshooting

**Equipment erscheint nicht im Dashboard?**

- Wartungsvertrag = Aktiv? ✓
- Wartungsmonat gesetzt? ✓
- Aktueller oder nächster Monat? ✓

**Serviceauftrag-Link fehlt?**

- Equipment als "Wartung" verknüpft? ✓
- Serviceauftrag Status 1-2? ✓

**PWA funktioniert nicht?**

- HTTPS erforderlich (außer localhost)
- Browser-Cache leeren
- Service Worker neu registrieren

**Mehrere Einträge nicht möglich?**

- SQL-Migration ausgeführt? (sql/llx_equipmentmanager_v1.7.sql)

-----

## 🤝 Contributing

Pull Requests sind willkommen!

```bash
git checkout -b feature/NeuesFeature
git commit -m 'Add: Tolles Feature'
git push origin feature/NeuesFeature
# → Pull Request erstellen
```

-----

## 📄 Lizenz

GPL v3 oder höher

-----

## 👤 Autor

**Gerrett84** - [GitHub](https://github.com/Gerrett84)

-----


**Feedback?** → [GitHub Issues](https://github.com/Gerrett84/dolibarr_equipmentmanager/issues)

-----

**Current Version:** 2.0.0
**Released:** December 2024
**Compatibility:** Dolibarr 22.0+
