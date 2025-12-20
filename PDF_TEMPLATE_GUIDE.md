# PDF-Export mit Equipment Details (v1.6.1)

## Übersicht

Ab Version 1.6.1 bietet das Equipment Manager Modul einen erweiterten PDF-Export für Serviceaufträge (Fichinter), der automatisch Equipment-spezifische Details einbindet.

## Was ist enthalten?

Das PDF enthält:
- **Standard Fichinter-Informationen**: Datum, Kunde, Techniker, Serviceauftragsnummer
- **Equipment-spezifische Details** (pro verknüpftem Equipment):
  - Equipment-Nummer und Bezeichnung
  - Typ und Standort
  - Arbeitsdatum und Arbeitszeit
  - Durchgeführte Arbeiten
  - Gefundene Mängel
  - Empfehlungen
  - Verbrauchtes Material mit Preisen
- **Zusammenfassung**:
  - Anzahl bearbeiteter Equipment
  - Gesamt-Arbeitszeit
  - Gesamt-Materialkosten
- **Unterschriften** für Techniker und Kunde

## Aktivierung

### 1. PDF-Template auswählen

In Dolibarr:
1. Gehe zu **Einstellungen → Module**
2. Suche **Equipment Manager**
3. Klicke auf **Einstellungen** (Zahnrad-Symbol)
4. Unter **PDF-Vorlagen** wähle: **"Equipment Manager"**
5. Speichern

### Alternative: Globale Einstellung

1. Gehe zu **Einstellungen → PDF**
2. Unter **Interventions/Fichinter**
3. Wähle Template: **"equipmentmanager"**

## Verwendung

### Workflow

1. **Serviceauftrag erstellen** (Fichinter)
2. **Equipment verknüpfen** über Tab "Equipment"
3. **Details eingeben** über Tab "Equipment Details":
   - Durchgeführte Arbeiten
   - Gefundene Mängel
   - Empfehlungen
   - Arbeitsdatum und -dauer
4. **Material hinzufügen** (falls verwendet)
5. **PDF generieren**:
   - Klicke auf "PDF generieren" in der Fichinter-Karte
   - Das PDF wird automatisch mit allen Equipment-Details erstellt

## PDF-Layout

```
┌─────────────────────────────────────────────┐
│ SERVICEAUFTRAG FI2512-0002                 │
│ Datum: 18.12.2024                          │
├─────────────────────────────────────────────┤
│ Kunde: [Name]                              │
│ Techniker: [Name]                          │
├─────────────────────────────────────────────┤
│                                             │
│ EQUIPMENT 1: A000013 - Haupteingang        │
│ ────────────────────────────────────────── │
│ Typ: Drehtürantrieb                        │
│ Standort: Gebäude A, EG                    │
│                                             │
│ Durchgeführte Arbeiten:                    │
│ - [Details...]                             │
│                                             │
│ Gefundene Mängel:                          │
│ - [Details...]                             │
│                                             │
│ Verbrauchtes Material:                     │
│ [Tabelle mit Artikel, Menge, Preis]       │
├─────────────────────────────────────────────┤
│                                             │
│ EQUIPMENT 2: A000014 - Seiteneingang       │
│ [...]                                      │
├─────────────────────────────────────────────┤
│                                             │
│ ZUSAMMENFASSUNG                            │
│ Anzahl Equipment: 2                        │
│ Gesamt-Arbeitszeit: 4h 15min              │
│ Material-Kosten: 46,00€                    │
├─────────────────────────────────────────────┤
│                                             │
│ Unterschrift Techniker:  Unterschrift Kunde:│
│ _________________        _________________  │
└─────────────────────────────────────────────┘
```

## Technische Details

### Dateien

- **PDF-Klasse**: `core/modules/fichinter/doc/pdf_equipmentmanager.modules.php`
- **Basis-Klasse**: `core/modules/fichinter/modules_fichinter.php`
- **Sprachdateien**:
  - `langs/de_DE/equipmentmanager.lang`
  - `langs/en_US/equipmentmanager.lang`

### Datenquellen

Das PDF nutzt folgende Datenquellen:
- `llx_fichinter` - Standard Serviceauftrag
- `llx_equipmentmanager_equipment` - Equipment-Stammdaten
- `llx_equipmentmanager_intervention_detail` - Equipment-spezifische Arbeiten
- `llx_equipmentmanager_intervention_material` - Verbrauchtes Material
- `llx_equipmentmanager_intervention_link` - Verknüpfung Equipment ↔ Intervention

## Anpassungen

### Corporate Design

Das PDF nutzt Dolibarrs Standard-PDF-Engine und unterstützt:
- Firmenlogo (konfiguriert unter Einstellungen → Firma)
- Firmendaten
- Fußzeilen-Text (konfiguriert unter Einstellungen → PDF)

### Mehrsprachigkeit

Das Template unterstützt automatisch die in Dolibarr konfigurierte Sprache:
- Deutsch (de_DE)
- Englisch (en_US)

Weitere Sprachen können durch Hinzufügen von `langs/[locale]/equipmentmanager.lang` ergänzt werden.

## Fehlerbehebung

### PDF wird nicht generiert

1. **Prüfe Berechtigungen**:
   ```bash
   chmod -R 755 custom/equipmentmanager/core/modules/fichinter
   ```

2. **Prüfe Template-Auswahl**:
   - Einstellungen → Module → Equipment Manager → PDF-Template

3. **Prüfe Verzeichnisse**:
   ```bash
   ls -la documents/ficheinter/
   ```

4. **Prüfe Logs**:
   - Aktiviere Dolibarr Debug-Modus
   - Schaue in `documents/dolibarr.log`

### Equipment-Details fehlen im PDF

1. **Prüfe Equipment-Verknüpfung**:
   - Tab "Equipment" im Fichinter
   - Mindestens ein Equipment muss verknüpft sein

2. **Prüfe Details-Eingabe**:
   - Tab "Equipment Details" im Fichinter
   - Arbeiten, Mängel, etc. müssen eingegeben sein

### Material fehlt im PDF

1. **Prüfe Material-Eingabe**:
   - Material muss über "Equipment Details" hinzugefügt werden
   - Pro Equipment separate Material-Listen

## Support

Bei Fragen oder Problemen:
- GitHub Issues: https://github.com/Gerrett84/dolibarr_equipmentmanager/issues
- Dokumentation: Siehe README.md im Projektverzeichnis

## Version History

- **v1.6.1** (2024-12-20):
  - Neues PDF-Template mit Equipment-Details
  - Material-Listen pro Equipment
  - Zusammenfassung mit Gesamt-Arbeitszeit und -Kosten
  - Unterschriftenbereich

## Lizenz

GNU GPL v3 - siehe LICENSE-Datei im Projektverzeichnis
