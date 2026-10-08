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

        $this->version = '6.1.1';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        
        $this->editor_name = 'Gerrett84';
        $this->editor_url = 'https://github.com/Gerrett84';
        
        // Icon für Modul-Liste und Top Bar
        $this->picto = 'equipmentmanager@equipmentmanager';

        // Tell Dolibarr this module provides PDF templates for fichinter
        $this->module_parts = array(
            'models' => 1,  // This module provides document templates
            'substitutions' => 1, // Custom substitution variables (OBJ address, invoice date)
            'triggers' => 1,      // Revoke PWA tokens when a user's password changes
            'hooks' => array(
                'toprightmenu',      // Hook for adding to top right menu
                'formmail',          // Hook for auto-attaching PDFs to emails
                'pdfgeneration',     // Hook for adding Objektadresse to Propal/Commande PDF
                'ordersuppliercard', // Hook context for order PDF
                'ordercard',         // Hook context for order PDF
                'index',             // Hook for home dashboard maintenance tile
                'main',              // Hook for sitewide addHtmlHeader (top menu icon color)
                'thirdpartycard',    // Hook for making the Objektadresse extrafield always visible on Societe card
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
        // Use a plain FontAwesome icon for the top menu entry instead of the module's
        // own img/equipmentmanager.png. Without this, Dolibarr's theme (theme/eldy/global.inc.php)
        // auto-detects that PNG and renders it as a desaturated background image behind the
        // manually printed <span class="fa fa-wrench">, making the top-bar icon look like two
        // overlapping icons. Setting this to a fa-* value skips that background-image entirely
        // (see the MAIN_MODULE_<NAME>_ICON check in global.inc.php) — only affects the top menu,
        // not the module's picto used elsewhere (module list, object icons, etc.).
        $this->const[] = array('MAIN_MODULE_EQUIPMENTMANAGER_ICON', 'chaine', 'fa-wrench', '', 0, 'current');
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

        // Technician accounts: use the field-service PWA (own/assigned service orders) without
        // backend access to service orders and equipment
        $r++;
        $this->rights[$r][0] = $this->numero + $r;
        $this->rights[$r][1] = 'Use the field-service PWA (technician)';
        $this->rights[$r][3] = 0;
        $this->rights[$r][4] = 'pwa';
        $this->rights[$r][5] = 'use';

        // Menü Einträge
        $this->menu = array();
        $r = 0;

        // Top Menu - Equipment Manager mit Icon
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => '',
            'type' => 'top',
            'titre' => 'Equipment',
            'prefix' => '<span class="fa fa-wrench fa-fw paddingright pictofixedwidth" style="color:#e67e22"></span>',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/service_order_list.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 1: Serviceaufträge (Parent) — v5
        // ============================================
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'ServiceOrders',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_service',
            'url' => '/equipmentmanager/service_order_list.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Neuer Serviceauftrag
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_service',
            'type' => 'left',
            'titre' => 'NewServiceOrder',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/fichinter/card.php?action=create',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Liste
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_service',
            'type' => 'left',
            'titre' => 'ServiceOrderList',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/service_order_list.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Statistik
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_service',
            'type' => 'left',
            'titre' => 'Statistics',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/fichinter/stats/index.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 2: Wartungs-Übersicht (Parent)
        // ============================================
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
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Wartungskalender
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_maintenance',
            'type' => 'left',
            'titre' => 'MaintenanceCalendar',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/maintenance_calendar.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Wartungskarte
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_maintenance',
            'type' => 'left',
            'titre' => 'MaintenanceMap',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/maintenance_map.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Serviceaufträge auto. erstellen
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_maintenance',
            'type' => 'left',
            'titre' => 'AutoCreateServiceOrders',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/maintenance_auto_create.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 3: Objektadressen (Parent) — v6.0
        // Links to Dolibarr's native Societe pages, filtered/pre-set via
        // the 'equipmentmanager_object_address' extrafield (list.php supports
        // search_options_<name>, card.php?action=create supports options_<name>
        // as a GETPOST-based default value override - both native mechanisms,
        // no custom pages needed here). Placed above Anlagenliste since an
        // Objektadresse is normally picked/created before adding equipment there.
        // ============================================
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'ObjectAddresses',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_objectaddress',
            'url' => '/societe/list.php?search_options_equipmentmanager_object_address=1&search_options_equipmentmanager_object_address_boolean=1',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Neue Objektadresse
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_objectaddress',
            'type' => 'left',
            'titre' => 'NewObjectAddress',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/societe/card.php?action=create&options_equipmentmanager_object_address=1',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 4: Anlagenliste (Parent)
        // ============================================
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
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Mehrere Anlagen anlegen
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_equipment',
            'type' => 'left',
            'titre' => 'BulkCreateEquipment',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/equipment_bulk_create.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Neue Anlage
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
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Anlagen nach Objektadresse
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
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 5: Preisliste (Parent)
        // ============================================
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'PriceList',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_pricelist',
            'url' => '/equipmentmanager/pricelist.php?tab=rate',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Verrechnungssätze
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_pricelist',
            'type' => 'left',
            'titre' => 'PriceListRate',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/pricelist.php?tab=rate',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Wartungspreise
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_pricelist',
            'type' => 'left',
            'titre' => 'PriceListMaintenance',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/pricelist.php?tab=maintenance',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin || $user->hasRight(\'ficheinter\', \'lire\') || $user->hasRight(\'equipmentmanager\', \'equipment\', \'read\')',
            'target' => '',
            'user' => 2,
        );

        // ============================================
        // Überschrift 6: Einstellungen (Parent) - per-user + list settings, not admin-only
        // ============================================
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager',
            'type' => 'left',
            'titre' => 'EMSettings',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => 'equipmentmanager_settings',
            'url' => '/equipmentmanager/profile.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Mein Profil (Techniker-Name + Unterschrift)
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_settings',
            'type' => 'left',
            'titre' => 'MyProfile',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/profile.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Kalender-Abo
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_settings',
            'type' => 'left',
            'titre' => 'CalendarFeed',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/calendar_settings.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '1',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Spalten der Serviceauftragsliste (globale Einstellung, nur Admin)
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_settings',
            'type' => 'left',
            'titre' => 'MenuServiceOrderColumns',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/service_order_list_settings.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2,
        );

        // Unterpunkt: Moduleinrichtung (nur Admin)
        $r++;
        $this->menu[$r] = array(
            'fk_menu' => 'fk_mainmenu=equipmentmanager,fk_leftmenu=equipmentmanager_settings',
            'type' => 'left',
            'titre' => 'ModuleSetup',
            'mainmenu' => 'equipmentmanager',
            'leftmenu' => '',
            'url' => '/equipmentmanager/admin/setup.php',
            'langs' => 'equipmentmanager@equipmentmanager',
            'position' => 1000 + $r,
            'enabled' => '1',
            'perms' => '$user->admin',
            'target' => '',
            'user' => 2,
        );

        // Tabs
        $this->tabs = array(
            // Equipment tab auf Intervention
            'intervention:+equipmentmanager_equipment:Equipment:equipmentmanager@equipmentmanager:$user->hasRight("equipmentmanager", "equipment", "read"):/equipmentmanager/intervention_equipment.php?id=__ID__',

            // Service Report tab auf Intervention - v1.6
            'intervention:+equipmentmanager_service_report:ServiceReport:equipmentmanager@equipmentmanager:$user->hasRight("equipmentmanager", "equipment", "read"):/equipmentmanager/intervention_equipment_details.php?id=__ID__',

            // Equipment tab auf Propal (Angebot) - v4.4
            'propal:+equipmentmanager:Equipment:equipmentmanager@equipmentmanager:$user->hasRight("propal", "lire"):/equipmentmanager/propal_equipment.php?id=__ID__',

            // Equipment tab auf Commande (Auftrag) - v4.4
            'order:+equipmentmanager:Equipment:equipmentmanager@equipmentmanager:$user->hasRight("commande", "lire"):/equipmentmanager/commande_equipment.php?id=__ID__',
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