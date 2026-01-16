<?php
/* Copyright (C) 2024-2025 Equipment Manager
 * v3.0.0 - Checklist System
 */

include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

class modEquipmentManager extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;
        $this->db = $db;

        $this->numero = 500100;
        $this->rights_class = 'equipmentmanager';
        $this->family = "technic";
        $this->module_position = '90';
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        $this->description = "Equipment and Service Report Management";
        $this->descriptionlong = "Manage equipment (automatic doors, fire doors, hold-open systems) with service reports, checklists, and PDF export";

        $this->version = '4.0.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        $this->editor_name = 'Gerrett84';
        $this->editor_url = 'https://github.com/Gerrett84';
        
        // Icon für Modul-Liste und Top Bar
        $this->picto = 'equipmentmanager@equipmentmanager';

        // Tell Dolibarr this module provides PDF templates for fichinter
        $this->module_parts = array(
            'models' => 1,  // This module provides document templates
            'hooks' => array(
                'toprightmenu',  // Hook for adding to top right menu
            ),
        );
        $this->dirs = array();
        $this->config_page_url = array("setup.php@equipmentmanager");
        $this->hidden = false;
        
        $this->depends = array();
        $this->requiredby = array();
        $this->conflictwith = array();
        
        $this->langfiles = array("equipmentmanager@equipmentmanager");
        $this->phpmin = array(7, 0);
        $this->need_dolibarr_version = array(16, 0);

        $this->const = array();
        $this->boxes = array();
        $this->cronjobs = array();

        // Berechtigungen
        $this->rights = array();
        $r = 0;

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

        // Top Menu - Wartungs-Dashboard mit Icon
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Equipment',
            'prefix' => '<span class="fa fa-wrench fa-fw paddingright pictofixedwidth"></span>',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/maintenance_dashboard.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Left Menu - Wartungs-Dashboard
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'MaintenanceDashboard',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_maintenance',
            'url' => '/equipmentmanager/maintenance_dashboard.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Left Menu - Equipment List
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

        // Left Menu - New Equipment
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

        // Left Menu - Equipment by Address
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

            // Service Report tab auf Intervention - v1.6
            'intervention:+equipmentmanager_service_report:ServiceReport:equipmentmanager@equipmentmanager:$user->hasRight("equipmentmanager", "equipment", "read"):/equipmentmanager/intervention_equipment_details.php?id=__ID__',
        );

        $this->dictionaries = array();
    }

    public function init($options = '')
    {
        global $conf, $langs, $db;

        $result = $this->_load_tables('/equipmentmanager/sql/');
        if ($result < 0) {
            return -1;
        }

        // Initialize equipment types if table is empty
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment_types WHERE entity = ".$conf->entity;
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj->nb == 0) {
                $this->loadDataFile('/equipmentmanager/sql/llx_equipmentmanager_equipment_types.data.sql');
            }
        }

        // Initialize checklist templates if table is empty
        $sql = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_templates WHERE entity = ".$conf->entity;
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj->nb == 0) {
                $this->loadDataFile('/equipmentmanager/sql/llx_equipmentmanager_checklist.data.sql');
            }
        }

        // Register PDF template for Fichinter
        // Clean up old entries (both old name and wrong type)
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom IN ('pdf_equipmentmanager', 'equipmentmanager') AND type IN ('fichinter', 'ficheinter') AND entity = ".$conf->entity;
        $db->query($sql);

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."document_model (nom, type, entity, libelle, description)";
        $sql .= " VALUES ('equipmentmanager', 'ficheinter', ".$conf->entity.", 'Equipment Manager', '')";
        $result = $db->query($sql);

        if (!$result) {
            dol_syslog("Error registering PDF template: ".$db->lasterror(), LOG_ERR);
        }

        $this->_init(array(), $options);

        return 1;
    }

    /**
     * Load a SQL data file
     *
     * @param string $relpath Relative path to SQL file
     * @return int Number of statements executed, <0 if error
     */
    private function loadDataFile($relpath)
    {
        global $db;

        $sqlfile = DOL_DOCUMENT_ROOT.'/custom'.$relpath;
        if (!file_exists($sqlfile)) {
            dol_syslog("Data file not found: ".$sqlfile, LOG_WARNING);
            return -1;
        }

        $content = file_get_contents($sqlfile);
        $statements = preg_split('/;\s*\n/', $content);
        $count = 0;

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            $statement = str_replace('llx_', MAIN_DB_PREFIX, $statement);
            if ($db->query($statement)) {
                $count++;
            } else {
                dol_syslog("SQL error in data file: ".$db->lasterror(), LOG_WARNING);
            }
        }

        dol_syslog("Loaded ".$count." statements from ".$relpath, LOG_INFO);
        return $count;
    }

    public function remove($options = '')
    {
        global $conf, $db;

        // Remove PDF template registration
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."document_model WHERE nom IN ('pdf_equipmentmanager', 'equipmentmanager') AND type IN ('fichinter', 'ficheinter') AND entity = ".$conf->entity;
        $db->query($sql);

        $sql = array();
        return $this->_remove($sql, $options);
    }
}