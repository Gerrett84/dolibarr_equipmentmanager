# Status: PDF Template "Keine" Bug

## Das Problem
- ✅ Setup zeigt Template als GRÜN
- ✅ Template-Datei wird gefunden und geladen
- ✅ Klasse kann instantiiert werden
- ❌ **ABER:** In Serviceaufträgen (Interventions) zeigt Dropdown "Equipment Manager: Keine"

## Aktuelle Konfiguration (funktioniert teilweise)

### Dateien
- **Template:** `/custom/equipmentmanager/core/modules/fichinter/doc/equipmentmanager.modules.php`
- **Klasse:** `class equipmentmanager extends ModelePDFFicheinter`
- **$this->name:** `"equipmentmanager"`
- **$this->description:** `"Equipment Manager PDF Template"` (hardcoded, kein Translation-Key)

### Datenbank
- `FICHEINTER_ADDON_PDF = 'equipmentmanager'` (entity 1)
- `llx_document_model`: 2 Einträge
  - ID 96: nom='equipmentmanager', type='ficheinter', entity=1
  - ID 97: nom='equipmentmanager', type='fichinter', entity=1

### Modul
- ✅ EquipmentManager aktiviert: `MAIN_MODULE_EQUIPMENTMANAGER = 1`
- ✅ models-Flag gesetzt: `$this->module_parts = array('models' => 1)`
- ✅ Dolibarr erkennt: `EquipmentManager: models=1, enabled=YES`

## Was funktioniert
1. Setup → Module → Interventions → PDF Templates: **GRÜN** ✓
2. Template-Datei kann direkt geladen werden ✓
3. Klasse instantiiert ohne Fehler ✓
4. `write_file()` Methode existiert ✓

## Was NICHT funktioniert
1. `ModelePDFFicheinter::liste_modeles()` gibt zurück:
   ```
   [0] => 'Equipment Manager: Keine'
   ```
   Statt:
   ```
   ['equipmentmanager'] => 'Equipment Manager PDF Template'
   ```

2. `getListOfModels($db, 'fichinter')` findet das Template NICHT:
   - Scannt `/usr/share/dolibarr/htdocs/core/modules/fichinter/doc` ✓ (findet soleil)
   - Scannt `/usr/share/dolibarr/htdocs/custom` ❌ (NICHT rekursiv!)
   - Findet `/custom/equipmentmanager/core/modules/fichinter/doc/` NICHT!

3. Script `check_module_scanning.php` Section 4:
   - Sucht in `$basedir/../core/modules/fichinter/doc`
   - Wenn `$basedir = /custom/equipmentmanager/core/modules/`
   - Dann sucht es in `/custom/equipmentmanager/core/core/modules/fichinter/doc` ❌ FALSCH!
   - Sollte suchen in `/custom/equipmentmanager/core/modules/fichinter/doc` ✓

## Der kritische Bug
**`dolGetModulesDirs()` gibt zurück:**
```
/usr/share/dolibarr/htdocs/custom/equipmentmanager/core/modules/
```

**Dolibarr's Scan-Logik macht dann:**
```php
$docdir = $basedir . '/../core/modules/fichinter/doc';
```

**Ergebnis:**
```
/usr/share/dolibarr/htdocs/custom/equipmentmanager/core/modules/../core/modules/fichinter/doc
= /usr/share/dolibarr/htdocs/custom/equipmentmanager/core/core/modules/fichinter/doc ❌
```

**Sollte sein:**
```
/usr/share/dolibarr/htdocs/custom/equipmentmanager/core/modules/fichinter/doc ✓
```

## Mögliche Lösungen (noch zu testen)

### Option 1: Template manuell in liste_modeles() registrieren
In `modules_fichinter.php` einen Hook/Patch einfügen

### Option 2: Verzeichnisstruktur anpassen
Vielleicht fehlt ein Symlink oder Dolibarr erwartet eine andere Struktur

### Option 3: Template anders laden
Prüfen wie andere Custom-Module (z.B. UltimatePDF) ihre Templates registrieren

### Option 4: Dolibarr-Bug
Eventuell ist der Scan-Mechanismus für Custom-Module in dieser Dolibarr-Version kaputt

## Nächste Schritte
1. Prüfen wie Dolibarr Core-Code `getListOfModels()` für Custom-Module scannt
2. Andere Custom-Module als Beispiel nehmen (falls vorhanden)
3. Eventuell direkten Patch für `modules_fichinter.php` entwickeln
4. Als letztes Mittel: Template nach `/core/modules/fichinter/doc/` kopieren (nicht empfohlen)

## Test-Scripts vorhanden
- `test_fichinter_support.php` - Prüft PDF-Support
- `debug_template_loading.php` - Detailliertes Template-Loading
- `trace_getlistofmodels.php` - Traced getListOfModels() Schritt für Schritt
- `check_module_scanning.php` - Prüft Modul-Scanning

## Dateien Stand
Commit: `05897d4`
Branch: `1.6.1`
