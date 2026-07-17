# Dolibarr Equipment Manager

**Version 5.5.1** | Professionelle Anlagenverwaltung mit PWA, Checklisten & Wartungsplanung

[![Dolibarr](https://img.shields.io/badge/Dolibarr-16.0%2B-blue.svg)](https://www.dolibarr.org)
[![License](https://img.shields.io/badge/license-GPL--3.0-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://www.php.net)

> **Hinweis:** Die Installation und Nutzung dieses Moduls erfolgt auf eigene Verantwortung. Es wird empfohlen, vor der Installation ein Backup der Datenbank und des Dolibarr-Verzeichnisses zu erstellen.

-----

## Features

### NEU in v5.5.1: PWA Stabilitäts-Hotfix & PDF-Viewer Mehrseiten-Fix

- **Fix: PDF-Viewer zeigt nur erste Seite** – iOS Safari beschränkt `<iframe>` grundsätzlich auf Seite 1 eines mehrseitigen PDFs; PDF-Viewer injiziert jetzt `<object type="application/pdf">` direkt in den Overlay (kein iframe-Nesting); Servicebericht, Checkliste, Abnahmeprotokoll und Dokument-Vorschau zeigen jetzt alle Seiten scrollbar; sw.js v27 erzwingt Cache-Neuladen
- **Fix: PWA dauerhaft offline / Spinner eingefroren** – `offlineDB.init()` konnte lautlos hängen wenn IndexedDB blockiert war oder nicht antwortete; `checkAuth()` konnte bei null-DB einen TypeError werfen der die gesamte App-Initialisierung lautlos abbrach; `tryAutoLogin()` hatte keinen Timeout und konnte bei Netzwerkproblemen ewig hängen
- **db.js: IDB-Init mit 8s Timeout** – `Promise.race()` verhindert ewiges Hängen; nach 8s wird ein `IDB_TIMEOUT`-Fehler geworfen
- **db.js: `onblocked`-Handler** – IDB-Upgrade-Blockierung durch andere Tabs wirft `IDB_BLOCKED` statt stumm zu hängen
- **db.js: `onversionchange`-Handler** – bestehende Verbindung schließt sich automatisch wenn ein anderer Tab eine neuere DB-Version öffnet (verhindert gegenseitiges Blockieren)
- **db.js: Null-Guards** – alle CRUD-Methoden prüfen `this.db !== null` und geben sichere Standardwerte zurück statt TypeError zu werfen
- **app.js: try-catch um `checkAuth()`** – stille Einfrierung wird als Fehlermeldung mit Reload-Button angezeigt
- **app.js: AbortController in `tryAutoLogin()`** – 10s Timeout verhindert ewiges Warten bei Netzwerkproblemen
- **sw.js: v26** – frische Caches für neue Fixes

### NEU in v5.5.0: HTTPS-Fixes & PDF-Viewer

- **Fix: PWA permanent offline via HTTPS** – OpenResty/NPM auf ZimaOS cachte `sw.js` mit `max-age` und ignorierte `Cache-Control`-Header von Apache; Proxy-Caching für `service.kurina.net` deaktiviert; `sw.js` wird jetzt korrekt mit `no-cache, no-store, must-revalidate` ausgeliefert
- **Fix: Font Awesome Icons fehlen auf HTTPS** – NPM-Cache filterte CORS-Header (`Access-Control-Allow-Origin: *`) für Webfonts heraus; behoben zusammen mit dem Proxy-Caching-Fix
- **Fix: PDF-Viewer Skalierung auf iOS 26** – `<embed>` wird unter iOS 26 nicht mehr unterstützt; iOS PDF-Viewer rendert PDFs immer in natürlicher Breite (A4 = 595px) unabhängig von der iframe-Breite; PDF-Embed-Wrapper setzt inneren iframe auf 595px und skaliert per `transform: scale(deviceWidth/595)` auf Gerätebreite
- **Fix: TOTP-Feld fehlt auf HTTPS** – Login-Injektion via jQuery `.clone()` kopierte eingebetteten `<script nonce="...">` des Passwort-Toggles; auf HTTPS schlug die DOM-Injektion lautlos fehl; auf Vanilla-JS `createElement` umgeschrieben (TOTP-Modul v1.4.4)
- **Fix: mod_headers** – Apache-Modul `mod_headers` auf CT 100 und CT 104 aktiviert für `.htaccess`-Header-Direktiven
- **Fix: PDF-Dokumente „File not found"** – `pdf_proxy.php` suchte Dateien direkt unter `DOL_DATA_ROOT` ohne `ficheinter/`-Unterverzeichnis; `modulepart`-zu-Verzeichnis-Mapping ergänzt, `ficheinter/` als Standard-Subverzeichnis gesetzt
- **Fix: PDF-Viewer verlangt Dolibarr-Login** – `pdf_preview.php` und `acceptance_protocol.php` fehlte `NOLOGIN`; Dolibarr leitete auf Login-Seite um; `define('NOLOGIN', '1')` ergänzt + `$user->getrights()` nach Token-Authentifizierung damit Berechtigungsprüfung korrekt funktioniert

### NEU in v5.4.2: PWA Auto-Login & Session-Fix

- **Multi-Device-Token** – Login auf Desktop/neuem Browser löscht nicht mehr den Token anderer Geräte; jedes Gerät behält seinen eigenen gültigen Token
- **Rolling Token-Erneuerung** – Token-Gültigkeit wird bei jeder Nutzung automatisch um 90 Tage verlängert; läuft nur noch ab nach 90 Tagen kompletter Inaktivität
- **Auth-Expired-Banner** – Sitzungs-Ablauf wird jetzt sichtbar angezeigt (roter Banner mit Anmelde-Link); bisher wurde „Online" ohne Sync angezeigt ohne Hinweis
- **Foreground-Sync** – Beim Wechsel in den Vordergrund nach >5 Minuten Pause wird automatisch ein Sync ausgelöst (auch wenn App bereits „Online" war)

### NEU in v5.4.1: Wartungsübersicht-Fix

- **Wartungsübersicht PWA** – Anlagen die in diesem Jahr gewartet wurden erscheinen bei 12-Monaten-Filter korrekt als „Fällig 2027"; SW-Cache-Bypass über Query-Param (`?v=5.4.2`) verhindert Ausliefern alter JS-Versionen

### NEU in v5.4.0: PWA-Verbesserungen & E-Mail

- **Karte Dark Mode** – Automatischer Wechsel auf CartoDB Dark Matter Kacheln; Popup zeigt Objektname statt Kundenname
- **Auftragsliste** – Kompakteres Kartendesign: Objektname oben, Adresse + Kunde/Datum klein darunter; kein Abschneiden langer Namen mehr
- **Info-Header** – Auftraggeber als erster Abschnitt; Reihenfolge überarbeitet; „Interne Anmerkung" statt „Private"
- **Einstellungen** – Kopfleiste mit Zurück-Pfeil und Home-Button; kompakteres Layout; Backend-Button nur noch hier
- **Sync per Badge** – Klick auf Online/Offline-Badge löst Synchronisation aus; separater Reload-Button entfernt
- **E-Mail – Anhänge wählbar** – Servicebericht, Checkliste und Abnahmeprotokoll als einzeln abwählbare Checkboxen im E-Mail-Modal
- **E-Mail – HTML-Bearbeitung** – E-Mail-Body in `contenteditable`-Div; Formatierung bleibt beim Bearbeiten erhalten
- **Bugfixes** – Wartungsübersicht Januar/März-Fehler behoben; Abnahmeprotokoll-Anhang im E-Mail-Versand ergänzt

### NEU in v5.3.0: Preislisten

- **Zwei konfigurierbare Preislisten** – „Verrechnungssätze" (Stundensatz, Anfahrt etc.) und „Wartungspreise" als eigenständige Untermenüpunkte in der Seitenleiste
- **Positionen aus Produktkatalog** – Leistungen direkt aus dem Dolibarr-Produktkatalog auswählen; Bezeichnung, Beschreibung und Einheit werden automatisch übernommen (AJAX)
- **Dauerhaft gespeichert** – Positionen werden in der Datenbank gespeichert und sind jederzeit bearbeitbar; Reihenfolge per Pfeil-Buttons änderbar
- **PDF Vorschau** – Preisliste als PDF im Browser öffnen (Titelbalken mit Monat/Jahr, AGB-Text aus Dolibarr-Konstante `INVOICE_FREE_TEXT` im Footer)
- **PDF erstellen & speichern** – Aktuelle Preisliste archivieren; gespeicherte Versionen mit Datum und Ersteller jederzeit abrufbar (Vorschau + Download)
- **Kunden-Preisliste** – Individuelle PDF mit Kundenauswahl und Rabatt in Prozent; klassisches Brieflayout mit Kundenadresse links, Firmenadresse rechts; Sonderkonditionen farbig hervorgehoben
- **Kunden-PDFs archivierbar** – Auch kundenspezifische Preislisten können gespeichert werden; Archivtabelle zeigt Kunde, Rabatt, Ersteller und Datum
- **DB-Migration** – Neue Tabellen `llx_equipmentmanager_pricelist_item` und `llx_equipmentmanager_pricelist_archive` (SQL: `sql/llx_equipmentmanager_v5.3.0_pricelist.sql`, `sql/llx_equipmentmanager_v5.3.0_pricelist_archive.sql`)

### NEU in v5.2.4: Zeitraum-Eingabe für Arbeitseinträge

- **Von/Bis-Uhrzeit** – Arbeitseinträge können statt einer Dauer (Stunden/Minuten) alternativ als Zeitraum eingegeben werden (z.B. „Von 12:00 bis 14:00 Uhr")
- **Toggle Dauer/Zeitraum** – Umschalter zwischen beiden Eingabemodi in PWA und Backend
- **Automatische Berechnung** – Dauer wird automatisch aus Start- und Endzeit berechnet; Mitternachtsüberschreitung wird korrekt behandelt
- **Live-Vorschau** – In der PWA wird die berechnete Dauer sofort beim Eingeben angezeigt
- **PDF-Anzeige** – Zeitraum-Einträge erscheinen im Servicebericht-PDF als „12:00 – 14:00 Uhr (2 Std.)" statt nur „2 Std."
- **DB-Migration** – Neue Spalten `work_start_time` und `work_end_time` in `llx_equipmentmanager_intervention_detail` (SQL: `sql/llx_equipmentmanager_v5.2.4_work_time_range.sql`)

### NEU in v5.2.3: Servicebericht-PDF-Header

- **Ihr Zeichen** – Kundenreferenz (`ref_client`) wird im Servicebericht-PDF oben rechts angezeigt (nur wenn gesetzt)
- **Auftragsnummer** – Verknüpfte Auftragsnummer wird ebenfalls im PDF-Header oben rechts angezeigt (nur wenn verknüpft)

### NEU in v5.2.2: Zeitzone & Kalender-Bugfixes

- **Ganztägige Einträge im iPhone-Kalender** – ICS-Feed gibt jetzt korrektes RFC-5545-Format aus (`DTSTART;VALUE=DATE:YYYYMMDD`); iPhone zeigt „Ganztägig" statt „0:00 – 23:59"
- **Zeitzone-Fix** – Alle Kalendereinträge zeigten +2 Stunden Versatz; Serverzeit (Europe/Berlin) wird nun korrekt gesetzt (`MAIN_SERVER_TZ`)
- **Ganztägige Einträge in Serviceauftragsliste** – Zeigten fälschlicherweise „02:00 – 01:59 (2 Tage)"; Erkennung jetzt zeitzonenunabhängig über Rohstring-Auswertung
- **Kraftmessung in Serviceauftragsliste** – Schließ-/Öffnungskraft mit Grenzwert-Indikator (400 N, DIN EN 12453) auch in der Übersichtsliste sichtbar
- **Freigegebene Aufträge im Tab „Abrechnen"** – Aufträge mit Status „Freigegeben" (fk_statut=1) werden jetzt korrekt im Abrechnungs-Tab angezeigt
- **Überfällige Wartungen** – Vorschau auf maximal 3 Monate begrenzt; „Erledigt"-Prüfung ebenfalls auf 3 Monate erweitert

### NEU in v5.2: Terminplanung & iOS-Kalender

- **Termin bearbeiten** – Start- und Enddatum/-uhrzeit eines Serviceauftrags direkt in der Übersichtsliste und in der PWA bearbeitbar; funktioniert auch nach Dolibarr-Freigabe (Direkt-SQL-Update)
- **Ganztägig-Option** – Termin als ganztägig markieren; setzt automatisch 00:00–23:59
- **Termin-Spalte konfigurierbar** – Spalte „Termin" in der Serviceauftragsliste kann in den Admin-Einstellungen ein-/ausgeblendet werden
- **PWA Terminanzeige** – Termin wird im Info-Header des Auftrags angezeigt (Start & Ende, Ganztägig-Erkennung)
- **PWA Termin bearbeiten** – Neues Modal in der PWA zum Bearbeiten des Termins mit Datum/Uhrzeit und Ganztägig-Toggle
- **iOS-Kalender-Feed** – Neuer ICS/WebCal-Feed (`calendar.php`) zum Abonnieren offener Serviceaufträge im iOS-Kalender oder jeder anderen Kalender-App
- **Kalender-Authentifizierung** – Token-basierter Zugriff (`EQUIPMENTMANAGER_CAL_SECRET`); Token wird in der Admin-Einrichtungsseite generiert und angezeigt
- **Kalender-Inhalt** – Zusammenfassung: Auftragsnummer + Objektadresse-Name (Fallback: Adresse, dann Kundenname); Ort aus Objektadresse; Notiz aus Auftragsbeschreibung

### NEU in v5.1: Tortypen, Abnahmeprotokoll-Verbesserungen & Bugfixes

- **4 neue Tortypen** – Schwenkflügeltor, Schiebetor, Sektionaltor, Schwingtor (gate_swing/sliding/sectional/upandover) mit vollständigen Wartungschecklisten (DIN EN 12453)
- **Kraftmessung** – Schließ- und Öffnungskraftmessung als numerischer Eingabetyp in Checkliste und PDF
- **Abnahme mit Mängeln** – Neue dritte Abnahme-Variante: Abnahme erfolgt, aber mit dokumentierten Mängeln; Datum + Mängelliste im Protokoll
- **Abnahmeprotokoll Textwrapping** – Lange Bemerkungen/Mängeltexte brechen nun korrekt um (MultiCell, dynamische Boxhöhe)
- **Kraftmessung in Checklisten-PDF** – Numerische Prüfwerte werden jetzt korrekt im PDF dargestellt
- **Mängel-Fotos** – Fotos bleiben beim Bearbeiten eines Eintrags erhalten (saveItemResult schreibt Photo nur wenn explizit übergeben)
- **Serviceauftragsliste** – Standardmäßig Tab "Offen" beim Öffnen; "Alle" an letzter Position
- **Leistungsdatum-Fix** – Position auf Rechnungen mit mehreren verlinkten Objekten korrekt berechnet

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

Drehtürantrieb | Schiebetürantrieb | Brandschutztür | Türschließer | Feststellanlage | RWS | RWA | Schwenkflügeltor | Schiebetor | Sektionaltor | Schwingtor | Sonstige

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

### v5.5.0 (2026-07-12)

- **Fix: PWA permanent offline via HTTPS** – NPM/OpenResty cachte `sw.js` mit `max-age` und ignorierte `no-cache`-Header von Apache; Proxy-Caching deaktiviert
- **Fix: Icons fehlen auf HTTPS** – NPM-Cache filterte CORS-Header für Webfonts heraus; durch Proxy-Caching-Fix behoben
- **Fix: PDF-Viewer Skalierung iOS 26** – iOS PDF-Viewer rendert PDFs in natürlicher Breite; `pdf_embed.php` skaliert inneren iframe per `transform: scale()` auf Gerätebreite
- **Fix: TOTP-Feld auf HTTPS** – jQuery `.clone()` kopierte `<script nonce>` → DOM-Injektion fehlschlug; auf Vanilla-JS `createElement` umgeschrieben
- **Fix: mod_headers** – Apache `mod_headers` auf CT 100 & CT 104 aktiviert
- **Fix: PDF-Dokumente „File not found"** – `pdf_proxy.php` baute Dateipfad ohne `ficheinter/`-Unterverzeichnis; `modulepart`-Mapping + Fallback auf `ficheinter/` ergänzt; auch `modulepart` wird jetzt von `app.js` an den Proxy weitergegeben
- **Fix: PDF-Viewer verlangt Dolibarr-Login** – `define('NOLOGIN', '1')` in `pdf_preview.php` und `acceptance_protocol.php` ergänzt; `$user->getrights()` nach Token-Authentifizierung für korrekte `hasRight()`-Prüfung

### v5.4.2 (2026-06-29)

- **Fix: Multi-Device PWA-Token** – Neuer Login löscht nur noch abgelaufene Tokens, nicht alle; verhindert Invalidierung anderer Geräte
- **Fix: Rolling Token-Renewal** – `valid_until` wird bei jeder Nutzung um 90 Tage verlängert (statt festes Ablaufdatum)
- **Fix: Auth-Expired-Banner** – Sichtbarer roter Banner mit Anmelde-Link wenn Token-Erneuerung fehlschlägt (statt stilles „Online" ohne Sync)
- **Fix: Foreground-Sync** – Automatischer Sync beim App-Vordergrund-Wechsel wenn letzter Sync >5 Minuten her

### v5.4.1 (2026-06-23)

- **Fix: Wartungsübersicht** – Anlagen mit `maint_status = done` erscheinen bei 12-Monaten-Filter korrekt als „Fällig 2027" (nächster Jahres-Termin); wirklich überfällige Anlagen bleiben weiterhin dauerhaft als „Überfällig" sichtbar
- **Fix: SW-Cache** – Query-Param `?v=5.4.2` in `index.php` verhindert dass der Service Worker veraltetes `app.js` aus dem Cache ausliefert

### v5.4.0 (2026-06-23)

- **PWA: Karte – Dark Mode** – Kartenansicht wechselt automatisch auf CartoDB Dark Matter Kacheln im Dunkelmodus; Wechsel auch bei Theme-Änderung ohne Neustart
- **PWA: Karte – Popup** – Popup zeigt Objektname (statt Kundenname) + Adresse; Popup-Wrapper mit runden Ecken und Dark-Mode-Styling
- **PWA: Auftragsliste – kompakteres Design** – Objektname prominent oben (2-zeilig, kein harter Abschnitt), Adresse einzeilig, Kunde + Datum klein darunter; keine Kürzung durch ellipsis mehr
- **PWA: Info-Header** – Auftraggeber (Name + Adresse) als erster Abschnitt; Reihenfolge: Auftraggeber → Objektadresse → Termin → Beschreibung → Interne Anmerkung → Öffentliche Anmerkung; „Private" umbenannt in „Interne Anmerkung"
- **PWA: Einstellungen – Header** – Einstellungsseite erhält dieselbe Kopfleiste wie die Hauptapp (Zurück-Pfeil, Titel, Home-Button zum Dolibarr-Backend)
- **PWA: Home-Button** – Backend-Button nur noch in den Einstellungen; aus dem Haupt-Header entfernt
- **PWA: Titel** – „Serviceberichte" → „Serviceaufträge"
- **PWA: Sync per Badge** – Klick auf das Online/Offline-Badge löst Synchronisation aus; separater Reload-Button entfernt
- **PWA: Einstellungen – kompakter** – Statusbereich, Theme-Optionen und „Gespeicherte Daten" platzsparender dargestellt
- **PWA: E-Mail – Anhänge wählbar** – Jeder PDF-Anhang (Servicebericht, Checkliste, Abnahmeprotokoll) wird als Checkbox angezeigt; standardmäßig alle aktiv, einzeln abwählbar vor dem Versand
- **PWA: E-Mail – vorhandene Anhänge** – Modal zeigt nur Dateien, die tatsächlich existieren; Dateinamen statt generischem Hinweistext
- **PWA: E-Mail – Beta-Label entfernt** – „Beta"-Badge und Formatierungshinweis aus Einstellungen und Modal entfernt
- **Fix: E-Mail – Abnahmeprotokoll nicht angehängt** – `send-email`-Endpunkt hat Abnahmeprotokoll-PDF nie angehängt; nachgezogen
- **Fix: Wartungsübersicht Januar/März** – SQL-Bug: `MONTH >= maintenance_month - 1` für Januar ergab `>= 0` (immer wahr) → alle Anlagen als erledigt markiert; behoben mit exaktem Monatsvergleich inkl. Jahresübergang-Wrap
- **Fix: E-Mail-Formatierung** – E-Mail-Body wechselt von `<textarea>` (Plaintext) auf `contenteditable`-Div; Formatierung bleibt beim Bearbeiten vollständig erhalten; API liefert `body_html` (HTML) zusätzlich zu `body` (Plaintext)

### v5.3.1 (2026-06-11)

- **Sicherheit: PWA-Token-Authentifizierung** – Passwörter werden nicht mehr im Browser gespeichert; Login generiert einen sicheren Token, der für alle API-Anfragen und Auto-Login verwendet wird
- **Sicherheit: CSRF-Schutz** – AJAX-Endpunkt `ajax/update_schedule.php` prüft jetzt Session-Token (kein `NOCSRFCHECK` mehr)
- **Sicherheit: XSS-Schutz** – Formularwerte in HTML-Attributen korrekt mit `dol_escape_htmltag()` escaped
- **Sicherheit: CORS** – `Access-Control-Allow-Origin` wird aus `MAIN_URL_ROOT` gesetzt statt Wildcard; OPTIONS-Preflight früh beantwortet
- **Sicherheit: Keine DB-Fehlerdetails in API-Antworten** – Fehler werden nur noch in `dol_syslog` geschrieben, nicht an den Client gesendet
- **Sicherheit: Path-Traversal-Schutz** – `CONTEXT_DOCUMENT_ROOT`-Include-Pfade aus allen PHP-Dateien entfernt
- **Bugfix: PWA dauerhaft online** – CORS-Fix hatte einen Fatal Error verursacht (`getDolGlobalString` vor `main.inc.php`); korrekt behoben

### v5.2.4 (2026-06-03)

- **Zeitraum-Eingabe** – Arbeitseinträge können alternativ mit Von/Bis-Uhrzeit erfasst werden statt mit einer reinen Dauer (Stunden/Minuten)
- **Toggle Dauer/Zeitraum** – Umschalter in PWA und Backend; Dauer wird automatisch berechnet; Mitternachtsüberschreitung korrekt behandelt
- **PDF-Darstellung** – Zeitraum-Einträge werden als „HH:MM – HH:MM Uhr (X Std. Y min.)" im Servicebericht-PDF angezeigt; nur bei `link_type='service'` (nicht bei Wartung)
- **DB-Migration** – `work_start_time TIME NULL` und `work_end_time TIME NULL` in `llx_equipmentmanager_intervention_detail` (SQL: `sql/llx_equipmentmanager_v5.2.4_work_time_range.sql`)

### v5.2.3 (2026-05-29)

- **Servicebericht-PDF: Ihr Zeichen** – Kundenreferenz (`ref_client`) wird im Header oben rechts angezeigt (nur wenn gesetzt)
- **Servicebericht-PDF: Auftragsnummer** – Verknüpfte Auftragsnummer (`Auftragsnr.`) wird im Header oben rechts angezeigt (nur wenn verknüpft)

### v5.2.2 (2026-05-28)

- **Ganztägige Einträge im iPhone-Kalender** – ICS-Feed gibt jetzt korrektes RFC-5545-Format aus (`DTSTART;VALUE=DATE:YYYYMMDD`); iPhone zeigt „Ganztägig" statt „0:00 – 23:59"
- **Zeitzone-Fix** – Alle Kalendereinträge zeigten +2 Stunden Versatz; Serverzeit (Europe/Berlin) wird nun korrekt gesetzt (`MAIN_SERVER_TZ`)
- **Ganztägige Einträge in Serviceauftragsliste** – Zeigten fälschlicherweise „02:00 – 01:59 (2 Tage)"; Erkennung jetzt zeitzonenunabhängig über Rohstring-Auswertung
- **Kraftmessung: Grenzwert-Indikator** – Schließ-/Öffnungskraft (400 N, DIN EN 12453) wird in PWA, Backend und PDF grün/rot hervorgehoben; `threshold_max`-Spalte in Datenbank (SQL-Migration: `sql/llx_equipmentmanager_v5.3.0_checklist_threshold.sql`)
- **Freigegebene Aufträge im Tab „Abrechnen"** – Aufträge mit Status „Freigegeben" (fk_statut=1) werden korrekt im Abrechnungs-Tab angezeigt
- **Überfällige Wartungen** – Vorschau auf maximal 3 Monate begrenzt; „Erledigt"-Prüfung ebenfalls auf 3 Monate erweitert

### v5.2.1 (2026-05-05)

- **Hotfix: Termin-Uhrzeit wird nicht gespeichert** – `llx_fichinter.dateo`/`datee` waren `DATE`-Spalten; MySQL verwarf die Uhrzeit beim Speichern lautlos → immer 00:00 UTC gespeichert → 02:00 CEST angezeigt. Fix: Spalten auf `DATETIME` geändert, Anzeige auf `'tzserver'` umgestellt (SQL-Migration: `sql/llx_equipmentmanager_v5.2.1_fichinter_datetime.sql`)

### v5.2.0 (2026-05-05)

- **Termin bearbeiten** – Start-/Enddatum und Uhrzeit direkt in der Serviceauftragsliste und der PWA editierbar; Direkt-SQL-Update umgeht Dolibarr-Freigabesperre
- **Ganztägig** – Termin als ganztägig markieren (00:00–23:59); Anzeige angepasst in Liste und PWA
- **Termin-Spalte konfigurierbar** – Spalte in der Serviceauftragsliste via Admin-Einstellungen ein-/ausblendbar
- **PWA: Termin im Info-Header** – Start, Ende und Ganztägig-Status im aufklappbaren Auftragsheader
- **PWA: Termin bearbeiten** – Neues Bottom-Sheet-Modal mit Datum/Uhrzeit-Feldern, Ganztägig-Toggle und automatischer Anpassung des Enddatums
- **iOS-Kalender-Feed** – ICS/WebCal-Endpoint `calendar.php`; abonnierbar in iOS-Kalender, Google Calendar etc.
- **Kalender-Token** – Sicherer Token-Zugriff via `EQUIPMENTMANAGER_CAL_SECRET`; Generierung und Subscribe-Link in der Admin-Einrichtungsseite
- **Fix: HTTP 403 in PWA** – PWA-Token-Auth lädt keine Benutzerrechte; `hasRight()`-Prüfung im Schedule-API-Endpunkt entfernt

### v5.1.0 (2026-04-30)

- **4 neue Tortypen** – Schwenkflügeltor, Schiebetor, Sektionaltor, Schwingtor mit vollständigen Wartungschecklisten nach DIN EN 12453; Sections: Toranlage, Federn, Sicherheit, Antrieb/Funktion
- **Numerischer Antworttyp** – `number` answer_type für Messwerte (z.B. Schließ-/Öffnungskraft in N); Darstellung als Zahleneingabe in PWA und Wert in Checklisten-PDF
- **Abnahme mit Mängeln** – Neue dritte Abnahme-Variante neben "erfolgreich" und "nicht erfolgt": `acceptance_done=1, acceptance_defect_free=0`; zeigt Datum + Mängelliste im Protokoll
- **Abnahmeprotokoll Textwrapping** – Bemerkung/Mängeltext werden via MultiCell umgebrochen; Boxhöhe passt sich dynamisch dem Textinhalt an (beide Spalten synchron)
- **Mängel-Foto-Persistenz** – `saveItemResult()` überschreibt Photo nur wenn explizit übergeben (null-Sentinel); verhindert Verlust von Mängelfotos beim Bearbeiten
- **Serviceauftragsliste** – Standardmäßig Tab "Offen" aktiv beim Öffnen; "Alle" als letzter Tab
- **Wartungsübersicht** – Anlagen ohne `fk_contract` werden jetzt korrekt in der PWA-Wartungsübersicht angezeigt (Matching mit Backend-Kalender)
- **Leistungsdatum-Fix** – Y-Position auf Rechnungen wird korrekt berechnet wenn mehrere Objekte (Serviceauftrag + Kundenauftrag) verknüpft sind

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

### Techniker-Benutzer einrichten (Multi-User)

Damit ein Techniker nur seine eigenen Serviceaufträge in der PWA sieht und zugewiesene Aufträge korrekt angezeigt bekommt, sind diese Schritte erforderlich:

**1. Benutzer anlegen**
In Dolibarr: *Benutzer & Gruppen → Neuer Benutzer*

**2. Kontakt/Person anlegen (oder vorhandenen nutzen)**
In Dolibarr: *Drittes → Kontakte/Adressen → Neuer Kontakt*

**3. Benutzer mit Kontakt verknüpfen** *(Pflicht!)*
Im Benutzerprofil unter *„Weitere Informationen"* → *„Verlinkter Kontakt"* → Kontakt aus Schritt 2 auswählen und speichern.

**4. Techniker einem Serviceauftrag zuweisen**
Im Serviceauftrag: Tab *„Kontakte/Adressen"* → Kontakt hinzufügen → Typ **„Techniker (TECH)"** wählen.

Ab diesem Zeitpunkt sieht der Techniker diesen Serviceauftrag in der PWA — inklusive aller Details, Dateien, Fotos und Checklisten.

> **Hinweis:** Fehlt die Verknüpfung in Schritt 3 (`llx_user.contact_id`), sieht der Techniker nur Aufträge, bei denen er selbst der Ersteller ist. Zur Prüfung:
> ```sql
> SELECT login, contact_id FROM llx_user WHERE login = 'techniker_login';
> ```

---

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

**Current Version:** 5.5.0
**Released:** Juli 2026
**Compatibility:** Dolibarr 16.0+
