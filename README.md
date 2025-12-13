# Dolibarr Equipment Manager

Ein Dolibarr-Modul zur Verwaltung von Equipment/Geräten mit Zuordnung zu Dritten (Third Parties) und Serviceaufträgen.

## 🌟 Features

### Version 1.1
- ✅ Equipment-Verwaltung mit Listenansicht
- ✅ Detailansicht für jedes Equipment
- ✅ Verknüpfung mit Dritten (Third Parties)
- ✅ Seriennummer-Verwaltung
- ✅ Status-Tracking (Aktiv/Inaktiv)
- ✅ Notizen und Beschreibungen
- ✅ Equipment-Karte auf der Third Party Seite

### Version 1.2
- ✅ Alle Features von v1.1
- ✅ Objektadresse - Separate Adressverwaltung für Equipment
- ✅ Vollständige Adressfelder (Straße, PLZ, Stadt, Land, etc.)
- ✅ Unabhängige Standortverwaltung vom Third Party

### Version 1.3
- ✅ Alle Features von v1.2
- ✅ Equipment-Nummerierung (automatisch: A000001, A000002, ... oder manuell)
- ✅ Equipment-Typ erweitert (Drehtür, Schiebetür, Brandschutztür, etc.)
- ✅ Hersteller-Feld
- ✅ Türflügel-Anzahl (1-flüglig, 2-flüglig)
- ✅ Verknüpfung mit Serviceaufträgen (Interventionen)
- ✅ Equipment-Historie auf Equipment Card
- ✅ Equipment-Tab auf Intervention Card

### Version 1.4 (Aktuell)
- ✅ Alle Features von v1.3
- ✅ **NEU:** Zweistufige Equipment-Verknüpfung (Wartung / Service)
- ✅ **NEU:** Gesplittete Equipment Card (View / Edit getrennt)
- ✅ **NEU:** Suche nach Objektadresse in der Anlagenliste
- ✅ **NEU:** Übersicht "Anlagen nach Objektadresse" (gruppierte Ansicht)
- ✅ Farbkodierung für Wartung (grün) und Service (orange)
- ✅ Verbesserte Code-Organisation und Performance

## 📋 Voraussetzungen

- Dolibarr 22.0 oder höher
- PHP 7.4 oder höher
- MySQL/MariaDB Datenbank

## 🚀 Installation

### Neu-Installation

1. **Download**
   ```bash
   cd /var/www/dolibarr/htdocs/custom
   git clone https://github.com/Gerrett84/dolibarr_equipmentmanager.git equipmentmanager
   ```

2. **Berechtigungen setzen**
   ```bash
   chown -R www-data:www-data equipmentmanager
   chmod -R 755 equipmentmanager
   ```

3. **Modul aktivieren**
   - In Dolibarr einloggen
   - Gehe zu: `Home → Setup → Modules/Applications`
   - Suche nach "Equipment Manager"
   - Klicke auf "Activate"

4. **Berechtigungen konfigurieren**
   - Gehe zu: `Home → Setup → Users & Groups`
   - Weise Benutzern die gewünschten Equipment-Berechtigungen zu

### Update von v1.3 auf v1.4

Siehe [Migrations-Guide](#migrations-guide-v13--v14) weiter unten.

## 📖 Verwendung

### Equipment erstellen
1. Navigiere zu `Equipment Manager → New Equipment`
2. Fülle die erforderlichen Felder aus:
   - **Equipment-Nummer-Modus**: Automatisch (A000001, A000002, ...) oder Manuell
   - **Bezeichnung**: Name/Beschreibung des Equipments
   - **Typ**: Art des Equipments (Drehtür, Schiebetür, Brandschutztür, etc.)
   - **Hersteller**: Hersteller des Equipments
   - **Türflügel**: 1-flüglig oder 2-flüglig
   - **Auftraggeber**: Zugehöriger Kunde/Lieferant
   - **Objektadresse**: Standort-Kontakt aus dem Auftraggeber
   - **Standort/Bemerkung**: Zusätzliche Standortinformationen
   - **Seriennummer**: Eindeutige Seriennummer
   - **Datum in Betrieb**: Installationsdatum
   - **Wartungsvertrag**: Aktiv/Inaktiv

### Equipment anzeigen
- **Listen-Ansicht**: `Equipment Manager → List`
  - Durchsuchbar nach: Nummer, Typ, Hersteller, Bezeichnung, Objektadresse
- **Anlagen nach Objektadresse**: `Equipment Manager → Equipment by Address`
  - Gruppierte Ansicht nach Standorten
  - Perfekt für Wartungsrunden und Übersichten
- **Equipment eines Dritten**: Auf der Third Party Karte unter dem Tab "Equipment"

### Equipment mit Serviceaufträgen verknüpfen
1. Öffne einen Serviceauftrag (Intervention)
2. Wechsle zum Tab "Equipment"
3. Wähle Equipment aus der Liste:
   - **Als Wartung verknüpfen** (grün) - für regelmäßige Wartungen nach DIN
   - **Als Service verknüpfen** (orange) - für Reparaturen, Störungen, Umbauten
4. Verknüpfte Equipments werden in separaten Sektionen angezeigt

### Equipment bearbeiten
- Klicke auf ein Equipment in der Liste
- Wähle "Modify" um Änderungen vorzunehmen

## 🗂️ Datenbankstruktur

### Tabelle: `llx_equipmentmanager_equipment`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| rowid | int(11) | Primärschlüssel |
| entity | int(11) | Multi-Company Entity |
| ref | varchar(128) | Equipment Referenz (EQU-0001, ...) |
| equipment_number | varchar(128) | Anlagen-Nummer (A000001, ...) |
| equipment_number_mode | varchar(10) | Modus (auto/manual) |
| label | varchar(255) | Bezeichnung |
| equipment_type | varchar(50) | Typ (door_swing, fire_door, ...) |
| manufacturer | varchar(255) | Hersteller |
| door_wings | varchar(20) | Türflügel (1/2) |
| fk_soc | int(11) | Third Party ID |
| fk_address | int(11) | Objektadresse (Contact ID) |
| location_note | text | Standort/Bemerkung |
| serial_number | varchar(255) | Seriennummer |
| installation_date | date | Installationsdatum |
| status | int(11) | Status (0=Inaktiv, 1=Aktiv) |
| note_public | text | Öffentliche Notizen |
| note_private | text | Private Notizen |
| date_creation | datetime | Erstelldatum |
| tms | timestamp | Letzte Änderung |
| fk_user_creat | int(11) | Ersteller |
| fk_user_modif | int(11) | Letzter Bearbeiter |

### Tabelle: `llx_equipmentmanager_intervention_link`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| rowid | int(11) | Primärschlüssel |
| fk_intervention | int(11) | Serviceauftrag ID |
| fk_equipment | int(11) | Equipment ID |
| link_type | varchar(20) | Typ (maintenance/service) |
| date_creation | datetime | Verknüpfungsdatum |
| fk_user_creat | int(11) | Ersteller |
| note | text | Notizen |

## 🔒 Berechtigungen

Das Modul unterstützt folgende Berechtigungen:
- **Read**: Equipment anzeigen
- **Write**: Equipment erstellen/bearbeiten
- **Delete**: Equipment löschen

## 🛠️ Entwicklung

### Dateistruktur v1.4
```
equipmentmanager/
├── equipment_list.php              # Liste mit Suche
├── equipment_view.php              # Anzeige (Read-Only)
├── equipment_edit.php              # Erstellen/Bearbeiten
├── equipment_by_address.php        # Gruppierte Übersicht nach Adresse
├── intervention_equipment.php      # Equipment-Tab auf Intervention
├── class/
│   └── equipment.class.php         # Equipment-Klasse
├── core/modules/
│   └── modEquipmentManager.class.php
├── lib/
│   └── equipmentmanager.lib.php
├── langs/
│   ├── de_DE/equipmentmanager.lang
│   └── en_US/equipmentmanager.lang
└── sql/
    ├── llx_equipmentmanager_equipment.sql
    ├── llx_equipmentmanager_equipment.key.sql
    ├── llx_equipmentmanager_intervention_link.sql
    ├── llx_equipmentmanager_v1.3.sql
    └── llx_equipmentmanager_v1.4.sql
```

### Migrations-Guide v1.3 → v1.4

#### 1. SQL Update ausführen
```sql
-- Neue Spalte link_type hinzufügen
ALTER TABLE llx_equipmentmanager_intervention_link 
ADD COLUMN link_type varchar(20) DEFAULT 'maintenance' AFTER fk_equipment;

-- Index auf link_type
ALTER TABLE llx_equipmentmanager_intervention_link 
ADD INDEX idx_link_type (link_type);

-- Bestehende Einträge aktualisieren
UPDATE llx_equipmentmanager_intervention_link 
SET link_type = 'maintenance' 
WHERE link_type IS NULL;
```

#### 2. Dateien anpassen
```bash
# Alte equipment_card.php sichern
mv equipment_card.php equipment_card.php.backup

# Neue Dateien erstellen
# - equipment_view.php (nur Anzeige)
# - equipment_edit.php (Bearbeitung/Erstellen)
# - equipment_by_address.php (Gruppierte Übersicht)
```

#### 3. Dateien aktualisieren
- `equipment_list.php` → v1.4 (Suchfeld Objektadresse)
- `intervention_equipment.php` → v1.4 (Wartung/Service-Trennung)
- `class/equipment.class.php` → v1.4 (getNomUrl → view.php)
- `core/modules/modEquipmentManager.class.php` → v1.4 (Menü-Links + neuer Eintrag)
- `langs/de_DE/equipmentmanager.lang` → v1.4 (neue Übersetzungen)

#### 4. Cache leeren
```bash
rm -rf /var/www/dolibarr/documents/install/temp/*
```

#### 5. Modul neu laden (optional)
Falls nötig: Deaktivieren → Aktivieren

## 🐛 Bekannte Probleme

- Keine bekannten Probleme in der aktuellen Version

## 📝 Changelog

### Version 1.4 (Dezember 2025)
**Added:**
- Zweistufige Equipment-Verknüpfung (Wartung/Service)
- Separate View- und Edit-Seiten für Equipment
- Suche nach Objektadresse in der Anlagenliste
- Gruppierte Übersicht "Anlagen nach Objektadresse"
- Farbkodierung für Verknüpfungstypen (grün=Wartung, orange=Service)

**Changed:**
- `equipment_card.php` aufgeteilt in `equipment_view.php` und `equipment_edit.php`
- `intervention_equipment.php` komplett überarbeitet
- Alle internen Links angepasst
- Menü-Einträge aktualisiert

**Database:**
- Neue Spalte `link_type` in `llx_equipmentmanager_intervention_link`

### Version 1.3
- Hinzugefügt: Equipment-Nummerierung (automatisch/manuell)
- Hinzugefügt: Equipment-Typen (Drehtür, Schiebetür, etc.)
- Hinzugefügt: Hersteller-Feld
- Hinzugefügt: Türflügel-Anzahl
- Hinzugefügt: Verknüpfung mit Serviceaufträgen
- Hinzugefügt: Equipment-Historie
- Hinzugefügt: Equipment-Tab auf Intervention

### Version 1.2
- Hinzugefügt: Objektadresse-Funktion
- Hinzugefügt: Vollständige Adressfelder für Equipment-Standorte
- Verbessert: Formular-Layout und Validierung

### Version 1.1
- Erste stabile Version
- Equipment-Verwaltung
- Third Party Integration
- Equipment-Karte

## 🤝 Beitragen

Beiträge sind willkommen! Bitte:
1. Forke das Repository
2. Erstelle einen Feature-Branch (`git checkout -b feature/AmazingFeature`)
3. Committe deine Änderungen (`git commit -m 'Add some AmazingFeature'`)
4. Push zum Branch (`git push origin feature/AmazingFeature`)
5. Öffne einen Pull Request

## 📄 Lizenz

Dieses Projekt steht unter der GPL v3 Lizenz - siehe die [LICENSE](LICENSE) Datei für Details.

## 👤 Autor

**Gerrett84**
- GitHub: [@Gerrett84](https://github.com/Gerrett84)

## 🙏 Danksagungen

- Dolibarr Community
- Alle Mitwirkenden

## 📞 Support

Bei Fragen oder Problemen:
- Öffne ein [Issue](https://github.com/Gerrett84/dolibarr_equipmentmanager/issues)
- Kontaktiere mich über GitHub

**Version:** 1.4  
**Release:** Dezember 2025  
**Kompatibilität:** Dolibarr 22.0+
