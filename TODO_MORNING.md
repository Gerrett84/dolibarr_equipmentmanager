# TODO: PDF Template Fix - Morgen weitermachen

## Problem Status
- ✅ Tabellen existieren (intervention_detail, intervention_material)
- ✅ Template in document_model registriert: `equipmentmanager`, type `fichinter`
- ❌ **FEHLER**: `FICHEINTER_ADDON_PDF = 'pdf_equipmentmanager'`
  - Sollte sein: `FICHEINTER_ADDON_PDF = 'equipmentmanager'`
  - Das PDF-Prefix gehört NUR in den Dateinamen, NICHT in die Config!

## Sofort-Fix (im Container):

```bash
cd /var/www/dolibarr/htdocs/custom/equipmentmanager
php fix_pdf_config.php
```

Die Datei `fix_pdf_config.php` wurde bereits erstellt und macht:
1. Löscht alte Einträge mit 'pdf_equipmentmanager'
2. Setzt FICHEINTER_ADDON_PDF = 'equipmentmanager'
3. Zeigt Status vorher/nachher

## Zu testen nach dem Fix:

1. **Setup prüfen**:
   - Setup → Module → Interventions → PDF-Vorlagen
   - "Equipment Manager" sollte GRÜN sein ✓

2. **Serviceauftrag testen**:
   - Serviceauftrag öffnen
   - PDF-Dropdown sollte "Equipment Manager" zeigen (nicht "Keine")
   - PDF generieren sollte funktionieren

3. **Diagnose erneut laufen lassen**:
   ```bash
   php check_db_state.php
   ```
   - Sollte zeigen: `FICHEINTER_ADDON_PDF = 'equipmentmanager'` (ohne pdf_)

## Wenn das funktioniert:

1. Fix in modEquipmentManager.class.php einbauen:
   - `init()` Methode: FICHEINTER_ADDON_PDF setzen
   - Sicherstellen, dass bei jedem Update die Config korrekt ist

2. Testen mit frischem Backup:
   - Backup wiederherstellen (v1.5.1)
   - Update auf v1.6.1 durchführen
   - Prüfen ob alles automatisch funktioniert

3. Wenn alles läuft:
   - Änderungen committen
   - Branch 1.6.1 → main mergen
   - Release erstellen

## Dateien im Repo:
- ✅ `check_db_state.php` - Diagnose-Tool (bereits committed)
- ✅ `fix_pdf_config.php` - Sofort-Fix für Config (neu erstellt)

## Nächste Schritte nach erfolgreichem Test:
1. Fix in permanente Init-Logik einbauen
2. Commit & Push
3. Merge in main
4. Release v1.6.1 taggen

---

**Letzter Stand:** 2024-12-22, 01:00 Uhr
**Branch:** 1.6.1
**Commit:** 61b24af
