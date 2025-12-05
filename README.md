# Dolibarr Equipment Manager

Ein Dolibarr-Modul zur Verwaltung von Equipment/Geräten mit Zuordnung zu Dritten (Third Parties).

## 🌟 Features

### Version 1.1
- ✅ Equipment-Verwaltung mit Listenansicht
- ✅ Detailansicht für jedes Equipment
- ✅ Verknüpfung mit Dritten (Third Parties)
- ✅ Seriennummer-Verwaltung
- ✅ Status-Tracking (Aktiv/Inaktiv)
- ✅ Notizen und Beschreibungen
- ✅ Equipment-Karte auf der Third Party Seite

### Version 1.2 (Aktuell)
- ✅ Alle Features von v1.1
- ✅ **NEU:** Objektadresse - Separate Adressverwaltung für Equipment
- ✅ Vollständige Adressfelder (Straße, PLZ, Stadt, Land, etc.)
- ✅ Unabhängige Standortverwaltung vom Third Party

## 📋 Voraussetzungen

- Dolibarr 15.0 oder höher
- PHP 7.4 oder höher
- MySQL/MariaDB Datenbank

## 🚀 Installation

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

## 📖 Verwendung

### Equipment erstellen
1. Navigiere zu `Equipment Manager → New Equipment`
2. Fülle die erforderlichen Felder aus:
   - **Name**: Bezeichnung des Equipments
   - **Third Party**: Zugehöriger Kunde/Lieferant
   - **Serial Number**: Eindeutige Seriennummer
   - **Description**: Detaillierte Beschreibung
   - **Status**: Aktiv/Inaktiv

### Equipment mit Adresse erstellen (v1.2)
3. Optional: Füge eine Objektadresse hinzu:
   - **Address**: Straße und Hausnummer
   - **ZIP**: Postleitzahl
   - **Town**: Stadt
   - **State**: Bundesland/Kanton
   - **Country**: Land

### Equipment anzeigen
- **Listen-Ansicht**: `Equipment Manager → List`
- **Equipment eines Dritten**: Auf der Third Party Karte unter dem Tab "Equipment"

### Equipment bearbeiten
- Klicke auf ein Equipment in der Liste
- Wähle "Modify" um Änderungen vorzunehmen

## 🗂️ Datenbankstruktur

### Tabelle: `llx_equipmentmanager_equipment`

| Feld | Typ | Beschreibung |
|------|-----|--------------|
| rowid | int(11) | Primärschlüssel |
| entity | int(11) | Multi-Company Entity |
| ref | varchar(128) | Equipment Referenz |
| label | varchar(255) | Equipment Name |
| fk_soc | int(11) | Third Party ID |
| serial_number | varchar(255) | Seriennummer |
| description | text | Beschreibung |
| note_public | text | Öffentliche Notizen |
| note_private | text | Private Notizen |
| status | int(11) | Status (0=Inaktiv, 1=Aktiv) |
| address | varchar(255) | Straße (v1.2) |
| zip | varchar(25) | PLZ (v1.2) |
| town | varchar(50) | Stadt (v1.2) |
| state_id | int(11) | Bundesland ID (v1.2) |
| country_id | int(11) | Land ID (v1.2) |

## 🔒 Berechtigungen

Das Modul unterstützt folgende Berechtigungen:
- **Read**: Equipment anzeigen
- **Write**: Equipment erstellen/bearbeiten
- **Delete**: Equipment löschen

## 🛠️ Entwicklung

### Dateistruktur
```
equipmentmanager/
├── core/
│   ├── modules/
│   │   └── modEquipmentManager.class.php
│   └── boxes/
├── class/
│   └── equipment.class.php
├── lib/
│   └── equipmentmanager.lib.php
├── sql/
│   ├── llx_equipmentmanager_equipment.sql
│   └── llx_equipmentmanager_equipment.key.sql
├── card.php
├── list.php
└── equipment_card.php
```

### Migrieren von v1.1 zu v1.2

**SQL Migration ausführen:**
```sql
ALTER TABLE llx_equipmentmanager_equipment 
ADD COLUMN address varchar(255) DEFAULT NULL,
ADD COLUMN zip varchar(25) DEFAULT NULL,
ADD COLUMN town varchar(50) DEFAULT NULL,
ADD COLUMN state_id int(11) DEFAULT NULL,
ADD COLUMN country_id int(11) DEFAULT NULL;
```

## 🐛 Bekannte Probleme

- Keine bekannten Probleme in der aktuellen Version

## 📝 Changelog

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

---

**Made with ❤️ for the Dolibarr Community**
