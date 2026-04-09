# Dolibarr Equipment Manager

**Version 5.0** | Professionelle Anlagenverwaltung mit PWA, Checklisten & Wartungsplanung

[![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

> **Hinweis:** Die Installation und Nutzung dieses Moduls erfolgt auf eigene Verantwortung. Es wird empfohlen, vor der Installation ein Backup der Datenbank und des Dolibarr-Verzeichnisses zu erstellen.

-----

## Features

### NEU in v5.0: E-Mail-Versand, Checklisten-PDF & Anlagetyp-Felder

- **E-Mail nach Unterschrift** – Nach der Kundenunterschrift öffnet sich automatisch ein E-Mail-Modal; Servicebericht und kombinierte Checklisten-PDF werden automatisch angehängt
- **E-Mail CC/BCC** – CC-Feld im Modal, BCC automatisch mit Techniker-E-Mail vorausgefüllt
- **Body-Vorschau** – E-Mail-Text aus Dolibarr-Vorlage mit Substitutionsvariablen vorschaubar und editierbar (Beta)
- **Neue Substitutionsvariablen** – `__FICHINTER_DATE__` (Freigabedatum), `__ORDER_DATE__` (Auftragsdatum) für E-Mail-Vorlagen
- **Typ-spezifische Anlagefelder** – Eigene Felder pro Anlagentyp: Akku-Info für Brandschutztüren/RWA/RWS, Rauchmelder-Anzahl, Brandschutz Ja/Nein für Drehtüranlagen
- **Anlagenfelder in PWA** – Akku und Rauchmelder-Felder direkt in der PWA sichtbar und editierbar
- **Anlage aus Serviceauftrag entfernen** – Anlagen können in der PWA aus dem Auftrag entfernt werden; Entfernung wird gesperrt solange Einträge existieren
- **Bulk-Anlagenerstellung** – Neue Seite zur Massenanlage von Geräten für einen Kunden inkl. Wartungsvertrag-Pflichtfeld
- **Checklisten-PDF überarbeitet** – 2-Spalten-Layout, Objektadresse im Header, kompakte Equipment-Box (Anlage/Standort, Hersteller/Bezeichnung, optionale Akku/Rauchmelder-Zeilen)
- **Serviceauftragsliste** – Neue deduplizierte Liste unter eigenem Menüpunkt; konfigurierbare Spalten, Objektadresse-Spalte, Status-Sortierung
- **Statistik** – Neuer Statistik-Link im Serviceaufträge-Menü
- **Dark Mode** – Anlagentyp-Badges korrekt im Dark Mode dargestellt

### NEU in v4.8: PWA PDF-Viewer & Offline-Stabilität

- **In-App PDF-Viewer** - PDFs (Servicebericht, Checkliste, Abnahmeprotokoll, Dokumente) öffnen direkt in der PWA mit Zurück-Button — kein Verlassen der App mehr nötig
- **Fit-to-Width** - PDFs werden automatisch auf Displaybreite skaliert (besonders iOS)
- **Dark Mode im PDF-Viewer** - Viewer-Header und Hintergrund folgen dem App-Theme
- **Offline-Recovery** - Zuverlässige Erkennung von Service Worker Fake-200 Antworten; automatischer Session-Refresh bei abgelaufener PHP-Session (401)
- **Manueller Sync** - Sync-Button versucht erst Verbindungsaufbau bevor Sync gestartet wird
- **Cache leeren** - Neuer Button in Einstellungen löscht alle Offline-Daten (Login-Daten bleiben erhalten), auch für iOS nutzbar
- **Login-Redirect** - Nach Cache-Leerung führt der Login zur PWA-Einstellungsseite statt ins Backend
- **Abnahmeprotokoll-Filter** - Protokoll zeigt nur die ausgewählte Anlage (nicht alle); Filter auf `commissioning_done = 1 OR acceptance_done = 1`
- **PROV-Datei-Bereinigung** - Alte `PROV*.pdf` Abnahmeprotokolle werden nach Auftragsfreigabe automatisch gelöscht
- **PDF: Öffentliche Anmerkung entfernt** - `note_public` wird nicht mehr im Servicebericht-PDF gerendert (verhinderte Layout-Fehler)
- **Objektadresse:** - Label mit Doppelpunkt in allen PDF-Dokumenten (Servicebericht, Angebot, Rechnung, Auftrag)

### NEU in v4.5: Inbetriebnahme- & Abnahmeprotokoll

- **Abnahmeprotokoll PDF** - Professionelles Protokoll für Inbetriebnahme und Abnahme
- **Zwei-Spalten-Layout** - IBN und Abnahme nebeneinander pro Anlage
- **Automatische Generierung** - PDF wird beim Unterschreiben automatisch erstellt
- **Email-Anhang** - Protokoll wird automatisch an Emails angehängt
- **Checklisten-PDF** - Wird ebenfalls automatisch beim Abschluss generiert
- **Einheitliches Design** - Logo, Header und Adressen-Box wie Checklisten
- **Unterschriften** - Techniker- und Kundenunterschrift im Protokoll
- **Signer-Name** - Name des Unterschreibenden wird gespeichert und angezeigt

### NEU in v4.4: Anlagen in Angeboten & Aufträgen

- **Equipment-Tab in Angeboten** - Anlagen direkt im Angebot verknüpfen
- **Equipment-Tab in Aufträgen** - Anlagen im Auftrag verwalten
- **Automatische Übernahme** - Anlagen werden von Angebot → Auftrag → Serviceauftrag übernommen
- **Copy-Button** - Anlageninfos mit einem Klick in die Zwischenablage kopieren
- **Wartung/Service-Unterscheidung** - Separate Kennzeichnung der Arbeitsart

### NEU in v4.3: Freitext-Material

- **Freitext für Mängel-Material** - Material ohne Produktauswahl erfassen
- **Toggle Produkt/Freitext** - Umschalten zwischen Produktsuche und Freitext
- **Offline-Support** - Freitext-Material wird lokal gespeichert und später synchronisiert
- **Backend & PWA** - In beiden Oberflächen verfügbar

### NEU in v4.2: Mängel-Fotos & Dokumentation

- **Mängel-Fotos** - Fotos für Checklisten-Punkte und Service-Einträge
- **Interner Mängelbericht** - Neues PDF mit Objektadresse, Anlagennummer und Fotos
- **Mängel-Materialien** - Material für Mängelbeseitigung erfassen
- **Allgemeine Arbeiten** - Service-Einträge ohne Anlagenbezug
- **Clickable Adressen** - Adressen in PWA öffnen Maps-App
- **Foto-Zuschnitt** - Bilder in PWA zuschneiden

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

### NEU in v4.1.1: Objektadresse in Angeboten

- **Objektadresse im PDF** - Angebote zeigen die Objektadresse unterhalb der Kundenadresse
- **Kontakttyp OBJ** - Nutzt den bestehenden "Objektadresse" Kontakttyp
- **Automatische Integration** - Funktioniert mit allen Standard-PDF-Templates (Cyan, Azur, etc.)
- **Keine Template-Änderung nötig** - Hook-basierte Lösung für maximale Kompatibilität

### v4.1: PWA-Statusfilter & Offline-Verbesserungen

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

### v5.0.0 (2026-04-09)

- **E-Mail nach Unterschrift** – Nach Kundenunterschrift öffnet sich automatisch das E-Mail-Modal; Servicebericht-PDF und kombinierte Checklisten-PDF (`Checklisten_{ref}.pdf`) werden automatisch angehängt
- **E-Mail CC/BCC** – CC-Feld frei editierbar; BCC wird automatisch mit der E-Mail-Adresse des eingeloggten Technikers vorausgefüllt
- **E-Mail Body-Vorschau** – Text aus Dolibarr-Vorlage `fichinter_send` inkl. Substitutionsvariablen vorschaubar und direkt im Modal editierbar (Beta)
- **Neue Substitutionsvariablen** – `__FICHINTER_DATE__` (Freigabedatum via `date_valid`), `__ORDER_DATE__` (Auftragsdatum) für Dolibarr E-Mail-Vorlagen
- **Typ-spezifische Anlagefelder** – Akku-Typ/Kapazität für Brandschutztüren, RWA, RWS; Rauchmelder-Anzahl für RWA/RWS; Brandschutz Ja/Nein für Drehtüranlagen
- **Anlagefelder in PWA** – Akku- und Rauchmelder-Felder in der Anlagendetailansicht der PWA anzeigbar und editierbar
- **Anlage entfernen in PWA** – Anlagen können aus Serviceaufträgen entfernt werden; Entfernung gesperrt wenn noch Arbeitseinträge vorhanden
- **Bulk-Anlagenerstellung** – Neue Seite `/equipment_bulk_create.php` zur Massenanlage; Wartungsvertrag als Pflichtfeld; einzelne Firmenauswahl mit AJAX-Adressfeld
- **Checklisten-PDF** – Komplettes Redesign: 2-Spalten-Layout, Objektadresse im Seitenkopf (unter Serviceauftrag/Datum), keine Firmenname-Zeile; Equipment-Box kompakt (Anlage | Standort / Hersteller | Bezeichnung / Akku & Rauchmelder optional)
- **Serviceauftragsliste** – Neue deduplizierte Liste (`service_order_list.php`) als eigener Menüpunkt; konfigurierbare Spalten; Objektadresse-Spalte; Sortierung nach Status-Priorität dann Datum; Entwurf und Offen zusammengefasst
- **Menü-Struktur** – Serviceaufträge an erster Position; Untermenü mit Liste, Statistik und neuem Serviceauftrags-Menüpunkt
- **Dark Mode Fix** – Anlagentyp-Badges mit dunklem Hintergrund und weißem Text auch im Dark Mode korrekt dargestellt

### v4.8.1 (2026-03-30)

- **Leistungszeitraum auf Rechnungen** - Wird automatisch aus den Arbeitsterminen des verknüpften Serviceberichts berechnet (MIN/MAX `work_date`) und direkt im rechten Info-Block der Rechnung unterhalb von „Serviceauftrag Ref." angezeigt — ohne Extrafeld, komplett Hook-basiert

### v4.8.0 (2026-03-29)

- **PWA: In-App PDF-Viewer** – PDFs öffnen in einem Vollbild-Overlay mit Zurück-Button; kein Tab-Wechsel mehr nötig
- **PWA: PDF fit-to-width** – `pdf_embed.php` Wrapper sorgt dafür, dass PDFs auf Displaybreite skaliert werden (iOS Safari)
- **PWA: Dark Mode im PDF-Viewer** – Header und Hintergrund des PDF-Viewers folgen dem App-Theme
- **PWA: Offline-Recovery** – Service Worker Fake-200 Erkennung; Session-Refresh bei 401 ohne Nutzerinteraktion
- **PWA: Sync-Button** – Versucht bei Offline-Zustand zuerst Verbindung herzustellen
- **PWA: Cache leeren** – Neuer Button in Einstellungen (funktioniert auch auf iOS)
- **PWA: Login-Redirect** – Nach Cache-Leerung → PWA-Einstellungen statt Dolibarr-Backend
- **Abnahmeprotokoll** – Nur Anlagen mit `commissioning_done=1` oder `acceptance_done=1` erscheinen im Protokoll
- **Abnahmeprotokoll** – Einzelanlage-Filter per `equipment_id` Parameter (Klick auf eine Anlage)
- **Abnahmeprotokoll** – PROV-benannte PDFs werden nach Auftragsfreigabe automatisch gelöscht
- **PDF Servicebericht** – `note_public` (öffentliche Anmerkung) entfernt; verhinderte Layout-Fehler
- **PDF alle Dokumente** – `Objektadresse:` Label mit Doppelpunkt in Servicebericht, Angebot, Rechnung und Auftrag

### v4.7.1 (2026-03-18)

- **Fix: Wartungsübersicht Monatsüberschriften** – Anlagen werden nach Wartungsmonat gruppiert mit Monatsüberschriften angezeigt; nur Monate mit Anlagen werden gezeigt, aufsteigend sortiert (überfällige zuerst)
- **Fix: Wartungsstatus-Logik** – `maintenance_month` ist nun der einzige Bezugspunkt (nicht mehr `next_maintenance_date`); abgeschlossene Serviceaufträge (`fk_statut=3`) des laufenden Jahres werden als "Erledigt" gewertet
- **Fix: Verlinkung aus Karte & Wartungsübersicht** – "Fehler beim Laden des Equipments" beim Öffnen eines Auftrags aus der Karten- oder Wartungsansicht behoben

### v4.7.0 (2026-03-17)

- **NEU: Wartungsübersicht in PWA** – Neue "Wartung 📅" Ansicht zeigt alle Anlagen mit aktivem Vertrag, gruppiert nach Objektadresse; Farbkodierung (rot/orange/grün) nach Fälligkeit; Zeitraum-Filter (3/6/9/12 Monate zusätzlich zu Überfällig+Bald); verlinkter Auftrag direkt aufrufbar
- **NEU: Kartenansicht in PWA** – "Karte 🗺️" Ansicht zeigt offene Aufträge via Leaflet/OpenStreetMap; farbige Marker nach Typ (Service=blau, Wartung=Wartungsfarbe); Geocaching in IndexedDB
- **NEU: Info-Header in Aufträgen** – Klappbarer Header mit Objektname, Adresse, Telefon (klickbar), E-Mail und Anmerkung aus der Objektadresse; ersetzt den Info-Button in der Navigationsleiste
- **NEU: Typ-Badge auf Auftragskarten** – "Service" oder "Wartung" Badge mit passender Farbe unterhalb des Titels
- **NEU: Dynamische Objektadresse** – Beim Anlegen einer neuen Anlage werden Objektadressen direkt nach Kundenauswahl geladen, ohne erst zu speichern
- **Fix: Bottom-Navigation** – Karte- und Wartungs-Buttons werden innerhalb eines Auftrags ausgeblendet; mehr Platz für Auftrag-spezifische Aktionen
- **Fix: equipmentTypeLabels** – Typenbezeichnungen (z.B. "Feststellanlage") stehen jetzt beim App-Start zur Verfügung, nicht erst nach Öffnen eines Auftrags

### v4.6.1 (2026-03-08)

- **Fix: PWA Offline-Recovery** – Zuverlässige Verbindungsprüfung via echtem API-Ping statt unzuverlässigem Browser-`online`-Event; automatischer Retry alle 30 Sekunden
- **Fix: PWA Statusanzeige** – Statusänderungen (Freigeben) werden sofort beim Zurücknavigieren sichtbar, kein Neustart der PWA mehr nötig
- **Fix: Umlaute im PDF** – Anlagentypen (z.B. Drehtürantrieb) und Produktbezeichnungen wurden ohne `convToOutputCharset()` in die PDF geschrieben
- **Fix: Fehlende DB-Spalten** – `maintenance_month`, `last_maintenance_date`, `next_maintenance_date` fehlten in den SQL-Migrationsdateien; SQL-Migration v4.6.1 ergänzt
- **Fix: Admin-Seiten** – `../../../main.inc.php` Fallback für alternative Dolibarr-Installationsstrukturen ergänzt; dreifaches Duplikat in setup.php bereinigt

### v4.6.0 (2026-03-01)

- **Fix: Allgemeine Arbeiten (PHP 8)** - `equipment_id` wird jetzt korrekt zu int gecastet; in PHP 8 ist `"" >= 0` false (String-Vergleich), wodurch Speichern lautlos fehlschlug
- **Fix: Objektadresse – Kontaktrolle OBJ** - Kontakt mit Rolle „Objektadresse" (Code `OBJ`) ist jetzt primäre Quelle für Objektadresse in PDF und PWA; Anlagen-`fk_address` dient als Fallback
- **Fix: Objektadresse auch ohne Anlage** - Interventionen ohne verknüpfte Anlage (Service/Wartung) zeigen Objektadresse korrekt an
- **Fix: `elementtype` → `element`** - Falscher Spaltenname in PDF-Fallback-Abfragen korrigiert; alte Fallbacks schlugen immer lautlos fehl
- **Fix: Allgemeine Arbeiten Offline-Sync** - PWA holt General-Einträge korrekt vor (detail/X/0)
- **Fix: API-Authentifizierung** - NOLOGIN-Flag verhindert HTML-Login-Seite vor PWA-Token-Check

### v4.5.0 (2025-02-22)

- **Abnahmeprotokoll PDF** - Neues Protokoll für Inbetriebnahme und Abnahme
- **Zwei-Spalten-Layout** - IBN-Daten und Abnahme-Daten nebeneinander pro Anlage
- **Automatische PDF-Generierung** - Checklisten- und Abnahmeprotokoll-PDF beim Unterschreiben
- **Email-Anhänge** - Alle PDFs (Signiert, Checklisten, Abnahmeprotokoll) automatisch anhängen
- **Einheitliches PDF-Design** - Logo, Firmenname, grauer Titel-Header, Adressen-Box
- **Signer-Name Fix** - Name des Unterschreibenden wird korrekt gespeichert
- **Serviceauftrag im PDF** - Referenz links, Datum rechts (wie Checkliste)
- **Seriennummer in PDF** - Equipment-Seriennummer wird angezeigt

### v4.4.0 (2025-02-12)

- **Equipment in Angeboten** - Neuer Tab "Anlagen" in Angeboten (Propal)
- **Equipment in Aufträgen** - Neuer Tab "Anlagen" in Aufträgen (Commande)
- **Automatische Übernahme** - Equipment wird von Angebot → Auftrag → Serviceauftrag übernommen
- **Copy-Button** - Anlageninfo in Zwischenablage kopieren für schnelles Einfügen
- **Bulk-Verknüpfung** - Mehrere Anlagen gleichzeitig verknüpfen
- **Wartung/Service-Toggle** - Arbeitsart direkt umschalten

### v4.3.0 (2025-02-11)

- **Freitext-Material** - Material für Mängelbeseitigung als Freitext eingeben
- **Toggle Produkt/Freitext** - Umschalten zwischen Produktsuche und Freitext-Eingabe
- **Offline-Support** - Freitext-Material wird offline gespeichert und später synchronisiert
- **Backend-Integration** - Toggle-Buttons im Dolibarr Backend
- **CSS-Variablen** - Bessere Theme-Unterstützung in PWA

### v4.2.0 (2025-02-11)

- **Mängel-Fotos** - Fotos für Checklisten-Punkte und Service-Einträge
- **Interner Mängelbericht PDF** - Neues PDF mit Objektadresse, Anlagennummer, Kontakt und Fotos
- **Mängel-Materialien** - Material für Mängelbeseitigung erfassen (Produkt oder Freitext)
- **Allgemeine Arbeiten** - Service-Einträge ohne Anlagenbezug (Montage, Anfahrt, etc.)
- **Clickable Adressen** - Adressen in PWA öffnen Maps-App
- **Foto-Upload** - Backend und PWA mit Kamera/Galerie Auswahl
- **Foto-Zuschnitt** - Bilder in PWA zuschneiden vor Upload
- **PDF-Layout** - Beschreibung als separate Box, Foto im Mängel-Bereich

### v4.1.1 (2025-01-30)

- **Objektadresse in Angeboten** - Objektadresse wird im PDF unter der Kundenadresse angezeigt
- **Hook-basierte Integration** - Funktioniert mit allen Standard-PDF-Templates (Cyan, Azur, etc.)
- **OBJ Kontakttyp für Propal** - Kontakttyp "Objektadresse" jetzt auch für Angebote verfügbar
- **Custom PDF Templates** - Optionale spezielle Templates (cyan_objektadresse, azur_objektadresse)

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

**Current Version:** 5.0.0
**Released:** April 2026
**Compatibility:** Dolibarr 16.0+
