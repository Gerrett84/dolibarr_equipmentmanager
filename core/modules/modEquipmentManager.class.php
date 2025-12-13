<?php
/* Copyright (C) 2024 Custom Module
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \defgroup equipmentmanager Module EquipmentManager
 * \brief Equipment and Service Report Management
 * \file core/modules/modEquipmentManager.class.php
 * \ingroup equipmentmanager
 * \brief Description and activation file for module EquipmentManager
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modEquipmentManager extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        // Modul Nummer (muss einzigartig sein, 500000-599999 für custom modules)
        $this->numero = 500100;
        
        $this->rights_class = 'equipmentmanager';
        
        // Familie des Moduls
        $this->family = "technic";
        
        $this->module_position = '90';
        
        // Modulname (ohne "mod" Präfix)
        $this->name = preg_replace('/^mod/i', '', get_class($this));
        
        // Beschreibung
        $this->description = "Equipment and Service Report Management";
        $this->descriptionlong = "Manage equipment (automatic doors, fire doors, hold-open systems) with service reports";
        
        // Versionsnummer
        $this->version = '1.4';
        
        // Konstanten Name
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        // Editor
        $this->editor_name = 'Custom';
        $this->editor_url = '';
        

        // Icon (kann auch leer bleiben)
        $this->picto = 'equipmentmanager@equipmentmanager';

        // Module parts
        $this->module_parts = array();

        // Benötigte Verzeichnisse
        $this->dirs = array();

        // Config page
        $this->config_page_url = array("setup.php@equipmentmanager");

        // Module ist nicht versteckt
        $this->hidden = false;
        
        // Abhängigkeiten
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        
        // Language files
        $this->langfiles = array("equipmentmanager@equipmentmanager");
        
        // PHP min version
        $this->phpmin = array(7, 0);
        
        // Dolibarr min version
        $this->need_dolibarr_version = array(16, 0);

        // Konstanten
        $this->const = array();

        // Boxen
        $this->boxes = array();

        // Cronjobs
        $this->cronjobs = array();

        // Berechtigungen
        $this->rights = array();
        $r = 0;

        // Equipment Berechtigungen
        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Read equipment';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'equipment';
        $this->rights[$r][5] = 'read';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Create/Update equipment';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'equipment';
        $this->rights[$r][5] = 'write';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Delete equipment';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'equipment';
        $this->rights[$r][5] = 'delete';

        // Service Report Berechtigungen
        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Read service reports';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'servicereport';
        $this->rights[$r][5] = 'read';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Create/Update service reports';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'servicereport';
        $this->rights[$r][5] = 'write';

        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Delete service reports';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'servicereport';
        $this->rights[$r][5] = 'delete';

        // Menü Einträge
        $this->menu = array();
        $r = 0;

        // Top Menu - GEÄNDERT zu equipment_list.php
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Equipment',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/equipment_list.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Left Menu - Equipment List - GEÄNDERT zu equipment_list.php
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'EquipmentList',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_equipment',
            'url' => '/equipmentmanager/equipment_list.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Left Menu - New Equipment - GEÄNDERT zu equipment_edit.php
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_equipment',
            'type' => 'left',
            'titre' => 'NewEquipment',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/equipment_edit.php?action=create',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Left Menu - Equipment by Address - NEU in v1.4
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_equipment',
            'type' => 'left',
            'titre' => 'EquipmentByAddress',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/equipment_by_address.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Tabs
        $this->tabs = array(
            // Equipment tab auf Intervention
            'intervention:+equipmentmanager_equipment:Equipment:equipmentmanager@equipmentmanager:$user->hasRight("equipmentmanager", "equipment", "read"):/equipmentmanager/intervention_equipment.php?id=__ID__',

            // Interventionen tab auf Equipment - GEÄNDERT zu equipment_view.php
            'equipment:+interventions:Interventions:equipmentmanager@equipmentmanager:$user->hasRight("ficheinter", "lire"):/equipmentmanager/equipment_interventions.php?id=__ID__'
        );

        // Dictionaries
        $this->dictionaries = array();
    }

    /**
     * Function called when module is enabled.
     * The init function add constants, boxes, permissions and menus 
     * (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        global $conf, $langs;

        $result = $this->_load_tables('/equipmentmanager/sql/');
        if ($result < 0) {
            return -1;
        }

        // Erstelle benötigte Verzeichnisse
        $this->_init(array(), $options);

        return 1;
    }

    /**
     * Function called when module is disabled.
     * Remove from database constants, boxes and permissions from Dolibarr database.
     * Data directories are not deleted
     *
     * @param string $options Options when disabling module ('', 'noboxes')
     * @return int 1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();
        return $this->_remove($sql, $options);
    }
}