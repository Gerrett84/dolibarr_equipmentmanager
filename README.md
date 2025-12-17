# Dolibarr Equipment Manager 🔧

[🇩🇪 Deutsch](#deutsch) | [🇬🇧 English](#english)

---

## 🇩🇪 Deutsch {#deutsch}

### Beschreibung

Ein leistungsstarkes Dolibarr-Modul zur Verwaltung von Anlagen (Türen, Brandschutz, RWA/RWS) mit intelligenter Wartungsplanung.

### ⭐ Hauptfeatures v1.5

- **Wartungs-Dashboard** - Übersicht fälliger Wartungen nach Standort
- **Automatische Fälligkeitsprüfung** - 1 Monat Vorlauf, Jahreswechsel-Support
- **Equipment-Nummerierung** - Automatisch (A000001, A000002, ...) oder manuell
- **Serviceauftrag-Integration** - Zweistufig (Wartung/Service)
- **Objektadresse-Verwaltung** - Separate Standorte pro Equipment
- **Status-Tracking** - Ausstehend → In Bearbeitung → Erledigt

### 📦 Installation

```bash
cd /var/www/dolibarr/htdocs/custom
git clone https://github.com/Gerrett84/dolibarr_equipmentmanager.git equipmentmanager
chown -R www-data:www-data equipmentmanager
chmod -R 755 equipmentmanager
```

**Dolibarr:** `Setup → Modules → Equipment Manager → Activate`

### 🚀 Schnellstart

1. **Equipment erstellen**
   - `Equipment Manager → New Equipment`
   - Wartungsvertrag: Aktiv
   - Wartungsmonat: z.B. Oktober

2. **Wartungs-Dashboard**
   - Zeigt fällige Wartungen (aktueller + nächster Monat)
   - Gruppiert nach Objektadresse
   - Status: Ausstehend / In Bearbeitung

3. **Serviceauftrag**
   - Erstellen und Equipment als "Wartung" verknüpfen
   - Bei Erledigung verschwindet Equipment automatisch

### 📋 Voraussetzungen

- Dolibarr 22.0+
- PHP 7.4+
- MySQL/MariaDB

### 📖 Dokumentation

- [Migrations-Guide](docs/MIGRATION.md)
- [User Guide](docs/USERGUIDE.md)
- [API Documentation](docs/API.md)

### 🤝 Mitwirken

Pull Requests willkommen! Bitte:
1. Repository forken
2. Feature-Branch erstellen
3. Änderungen committen
4. Pull Request öffnen

### 📄 Lizenz

GPL v3 - siehe [LICENSE](LICENSE)

### 👤 Autor

**Gerrett84** - [GitHub](https://github.com/Gerrett84)

---

## 🇬🇧 English {#english}

### Description

A powerful Dolibarr module for managing equipment (doors, fire protection, RWA/RWS) with intelligent maintenance planning.

### ⭐ Main Features v1.5

- **Maintenance Dashboard** - Overview of due maintenance by location
- **Automatic Due Date Check** - 1 month advance, year-end support
- **Equipment Numbering** - Automatic (A000001, A000002, ...) or manual
- **Service Order Integration** - Two-level (Maintenance/Service)
- **Object Address Management** - Separate locations per equipment
- **Status Tracking** - Pending → In Progress → Completed

### 📦 Installation

```bash
cd /var/www/dolibarr/htdocs/custom
git clone https://github.com/Gerrett84/dolibarr_equipmentmanager.git equipmentmanager
chown -R www-data:www-data equipmentmanager
chmod -R 755 equipmentmanager
```

**Dolibarr:** `Setup → Modules → Equipment Manager → Activate`

### 🚀 Quick Start

1. **Create Equipment**
   - `Equipment Manager → New Equipment`
   - Maintenance Contract: Active
   - Maintenance Month: e.g. October

2. **Maintenance Dashboard**
   - Shows due maintenance (current + next month)
   - Grouped by object address
   - Status: Pending / In Progress

3. **Service Order**
   - Create and link equipment as "Maintenance"
   - Automatically disappears when completed

### 📋 Requirements

- Dolibarr 22.0+
- PHP 7.4+
- MySQL/MariaDB

### 📖 Documentation

- [Migration Guide](docs/MIGRATION.md)
- [User Guide](docs/USERGUIDE.md)
- [API Documentation](docs/API.md)

### 🤝 Contributing

Pull requests welcome! Please:
1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Open a pull request

### 📄 License

GPL v3 - see [LICENSE](LICENSE)

### 👤 Author

**Gerrett84** - [GitHub](https://github.com/Gerrett84)

---

## 📊 Screenshots

### Maintenance Dashboard
![Dashboard](docs/images/dashboard.png)

### Equipment Card
![Equipment](docs/images/equipment.png)

---

## 🆚 Version Comparison

| Feature | v1.0 | v1.5 |
|---------|------|------|
| Equipment Management | ✅ | ✅ |
| Object Address | ❌ | ✅ |
| Maintenance Dashboard | ❌ | ✅ |
| Automatic Numbering | ❌ | ✅ |
| Service Integration | ❌ | ✅ |
| Status Tracking | Basic | Advanced |

---

## 🔄 Changelog

### v1.5 (2025-12)
- ✨ Maintenance dashboard with location grouping
- ✨ Annual maintenance planning per equipment
- ✨ Two-level service integration (Maintenance/Service)
- ✨ Manual completion option
- 🐛 Bug fixes and performance improvements

### v1.4 (2025-12)
- ✨ Split equipment card (view/edit)
- ✨ Search by object address
- ✨ Color-coded status badges

### v1.3 (2025-11)
- ✨ Automatic equipment numbering
- ✨ Equipment types extended
- ✨ Service order linking

[View Full Changelog](CHANGELOG.md)

---

## ⚙️ Configuration

### Database
```sql
-- Equipment with maintenance month
ALTER TABLE llx_equipmentmanager_equipment 
ADD COLUMN maintenance_month INT DEFAULT NULL;
```

### Maintenance Logic
- **Current Month** + **Next Month** = Displayed
- **Completed (Status 3)** = Hidden
- **1 Month Advance** = Accepted (Dec maintenance can be done in Nov)

---

## 🐛 Troubleshooting

**Equipment not visible in dashboard?**
- Check: Maintenance Contract = Active
- Check: Maintenance Month set (1-12)
- Check: Current or next month

**Service order link not shown?**
- Check: Equipment linked as "Maintenance"
- Check: Service order status 1-2 (not 0 or 3)

**Equipment doesn't disappear?**
- Check: Service order status = 3 (Completed)
- Check: Maintenance month matches or -1 month

---

**Current Version:** 1.5  
**Released:** December 2025  
**Compatibility:** Dolibarr 22.0+
