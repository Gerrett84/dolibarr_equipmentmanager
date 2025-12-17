<?php
/* Copyright (C) 2024 Equipment Manager
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class Equipment extends CommonObject
{
    public $element = 'equipment';
    public $table_element = 'equipmentmanager_equipment';
    public $picto = 'generic';
    
    public $fk_element = 'fk_equipment';
    
    public $fields = array(
        'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'visible' => -1, 'position' => 1, 'notnull' => 1, 'index' => 1),
        'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'visible' => 1, 'position' => 10, 'notnull' => 1, 'index' => 1, 'searchall' => 1),
        'equipment_number' => array('type' => 'varchar(128)', 'label' => 'EquipmentNumber', 'enabled' => 1, 'visible' => 1, 'position' => 20, 'notnull' => 1, 'index' => 1, 'searchall' => 1),
        'equipment_number_mode' => array('type' => 'varchar(10)', 'label' => 'Mode', 'enabled' => 1, 'visible' => 0, 'position' => 21, 'default' => 'auto'),
        'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'visible' => 1, 'position' => 30, 'notnull' => 1, 'searchall' => 1),
        'fk_soc' => array('type' => 'integer:Societe:societe/class/societe.class.php', 'label' => 'ThirdParty', 'enabled' => 1, 'visible' => 1, 'position' => 40),
        'fk_address' => array('type' => 'integer', 'label' => 'ObjectAddress', 'enabled' => 1, 'visible' => 1, 'position' => 45),
        'location_note' => array('type' => 'text', 'label' => 'LocationNote', 'enabled' => 1, 'visible' => 1, 'position' => 50),
        'equipment_type' => array('type' => 'varchar(50)', 'label' => 'Type', 'enabled' => 1, 'visible' => 1, 'position' => 60, 'notnull' => 1, 'arrayofkeyval' => array(
            'door_swing' => 'DoorSwing',
            'door_sliding' => 'DoorSliding',
            'fire_door' => 'FireDoor',
            'door_closer' => 'DoorCloser',
            'hold_open' => 'HoldOpen',
            'rws' => 'RWS',
            'rwa' => 'RWA',
            'other' => 'Other'
        )),
        'serial_number' => array('type' => 'varchar(255)', 'label' => 'SerialNumber', 'enabled' => 1, 'visible' => 1, 'position' => 70),
        'installation_date' => array('type' => 'date', 'label' => 'InstallationDate', 'enabled' => 1, 'visible' => 1, 'position' => 80),
        'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'visible' => 1, 'position' => 90, 'default' => '1', 'arrayofkeyval' => array('0' => 'Inactive', '1' => 'Active')),
        'maintenance_month' => array('type' => 'integer', 'label' => 'MaintenanceMonth', 'enabled' => 1, 'visible' => 1, 'position' => 91),
        'last_maintenance_date' => array('type' => 'date', 'label' => 'LastMaintenanceDate', 'enabled' => 1, 'visible' => -2, 'position' => 92),
        'next_maintenance_date' => array('type' => 'date', 'label' => 'NextMaintenanceDate', 'enabled' => 1, 'visible' => -2, 'position' => 93),
        'note_public' => array('type' => 'html', 'label' => 'NotePublic', 'enabled' => 1, 'visible' => 0, 'position' => 100),
        'note_private' => array('type' => 'html', 'label' => 'NotePrivate', 'enabled' => 1, 'visible' => 0, 'position' => 110),
        'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'visible' => -2, 'position' => 500),
        'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'visible' => -2, 'position' => 501),
        'fk_user_creat' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'visible' => -2, 'position' => 510),
        'fk_user_modif' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserModif', 'enabled' => 1, 'visible' => -2, 'position' => 511),
        'import_key' => array('type' => 'varchar(14)', 'label' => 'ImportId', 'enabled' => 1, 'visible' => -2, 'position' => 1000),
        'entity' => array('type' => 'integer', 'label' => 'Entity', 'enabled' => 1, 'visible' => 0, 'default' => '1', 'position' => 1000),
    );

    public $rowid;
    public $ref;
    public $equipment_number;
    public $equipment_number_mode;
    public $label;
    public $equipment_type;
    public $manufacturer;
    public $door_wings;
    public $fk_soc;
    public $fk_address;
    public $location_note;
    public $serial_number;
    public $installation_date;
    public $status;
    public $maintenance_month;
    public $last_maintenance_date;
    public $next_maintenance_date;
    public $note_public;
    public $note_private;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $import_key;
    public $entity;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    public function create(User $user, $notrigger = 0)
    {
        global $conf;

        $error = 0;

        $this->db->begin();

        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        // Generate equipment number if auto mode
        if ($this->equipment_number_mode == 'auto' || empty($this->equipment_number)) {
            $this->equipment_number = $this->getNextEquipmentNumber();
            $this->equipment_number_mode = 'auto';
        }

        $now = dol_now();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "ref,";
        $sql .= "equipment_number,";
        $sql .= "equipment_number_mode,";
        $sql .= "label,";
        $sql .= "equipment_type,";
        $sql .= "manufacturer,";
        $sql .= "door_wings,";
        $sql .= "fk_soc,";
        $sql .= "fk_address,";
        $sql .= "location_note,";
        $sql .= "serial_number,";
        $sql .= "installation_date,";
        $sql .= "status,";
        $sql .= "maintenance_month,";
        $sql .= "note_public,";
        $sql .= "note_private,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat,";
        $sql .= "entity";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= "'".$this->db->escape($this->equipment_number)."',";
        $sql .= "'".$this->db->escape($this->equipment_number_mode)."',";
        $sql .= "'".$this->db->escape($this->label)."',";
        $sql .= "'".$this->db->escape($this->equipment_type)."',";
        $sql .= ($this->manufacturer ? "'".$this->db->escape($this->manufacturer)."'" : 'NULL').",";
        $sql .= ($this->door_wings ? "'".$this->db->escape($this->door_wings)."'" : 'NULL').",";
        $sql .= ($this->fk_soc > 0 ? $this->fk_soc : 'NULL').",";
        $sql .= ($this->fk_address > 0 ? $this->fk_address : 'NULL').",";
        $sql .= ($this->location_note ? "'".$this->db->escape($this->location_note)."'" : 'NULL').",";
        $sql .= ($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : 'NULL').",";
        $sql .= ($this->installation_date ? "'".$this->db->idate($this->installation_date)."'" : 'NULL').",";
        $sql .= (isset($this->status) ? $this->status : 1).",";
        $sql .= ($this->maintenance_month > 0 ? (int)$this->maintenance_month : 'NULL').",";
        $sql .= ($this->note_public ? "'".$this->db->escape($this->note_public)."'" : 'NULL').",";
        $sql .= ($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL').",";
        $sql .= "'".$this->db->idate($now)."',";
        $sql .= ($user->id > 0 ? $user->id : 'NULL').",";
        $sql .= $conf->entity;
        $sql .= ")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
        }

        if (!$error) {
            $this->db->commit();
            return $this->id;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    public function fetch($id, $ref = null)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        if (!empty($ref)) {
            $sql .= " WHERE ref = '".$this->db->escape($ref)."'";
        } else {
            $sql .= " WHERE rowid = ".(int) $id;
        }

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->ref = $obj->ref;
                $this->equipment_number = $obj->equipment_number;
                $this->equipment_number_mode = $obj->equipment_number_mode;
                $this->label = $obj->label;
                $this->equipment_type = $obj->equipment_type;
                $this->manufacturer = $obj->manufacturer;
                $this->door_wings = $obj->door_wings;
                $this->fk_soc = $obj->fk_soc;
                $this->fk_address = $obj->fk_address;
                $this->location_note = $obj->location_note;
                $this->serial_number = $obj->serial_number;
                $this->installation_date = $this->db->jdate($obj->installation_date);
                $this->status = $obj->status;
                $this->maintenance_month = $obj->maintenance_month;
                $this->last_maintenance_date = $this->db->jdate($obj->last_maintenance_date);
                $this->next_maintenance_date = $this->db->jdate($obj->next_maintenance_date);
                $this->note_public = $obj->note_public;
                $this->note_private = $obj->note_private;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->tms = $this->db->jdate($obj->tms);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
                $this->import_key = $obj->import_key;
                $this->entity = $obj->entity;

                return 1;
            }
            return 0;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    public function update(User $user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " equipment_number = '".$this->db->escape($this->equipment_number)."',";
        $sql .= " equipment_number_mode = '".$this->db->escape($this->equipment_number_mode)."',";
        $sql .= " label = '".$this->db->escape($this->label)."',";
        $sql .= " equipment_type = '".$this->db->escape($this->equipment_type)."',";
        $sql .= " manufacturer = ".($this->manufacturer ? "'".$this->db->escape($this->manufacturer)."'" : 'NULL').",";
        $sql .= " door_wings = ".($this->door_wings ? "'".$this->db->escape($this->door_wings)."'" : 'NULL').",";
        $sql .= " fk_soc = ".($this->fk_soc > 0 ? $this->fk_soc : 'NULL').",";
        $sql .= " fk_address = ".($this->fk_address > 0 ? $this->fk_address : 'NULL').",";
        $sql .= " location_note = ".($this->location_note ? "'".$this->db->escape($this->location_note)."'" : 'NULL').",";
        $sql .= " serial_number = ".($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : 'NULL').",";
        $sql .= " installation_date = ".($this->installation_date ? "'".$this->db->idate($this->installation_date)."'" : 'NULL').",";
        $sql .= " status = ".(isset($this->status) ? $this->status : 1).",";
        $sql .= " maintenance_month = ".($this->maintenance_month > 0 ? (int)$this->maintenance_month : 'NULL').",";
        $sql .= " note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : 'NULL').",";
        $sql .= " note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL').",";
        $sql .= " fk_user_modif = ".$user->id;
        $sql .= " WHERE rowid = ".(int) $this->id;

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if (!$resql) {
            $error++;
            $this->errors[] = "Error ".$this->db->lasterror();
        }

        if (!$error) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            return -1;
        }
    }

    public function delete(User $user, $notrigger = 0)
    {
        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int) $this->id;

        dol_syslog(get_class($this)."::delete", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if (!$resql) {
            $this->errors[] = "Error ".$this->db->lasterror();
            $this->db->rollback();
            return -1;
        }

        $this->db->commit();
        return 1;
    }

    public function getNextNumRef()
    {
        global $conf, $db;

        $sql = "SELECT MAX(CAST(SUBSTRING(ref FROM 5) AS SIGNED)) as maxref";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE ref LIKE 'EQU-%'";
        $sql .= " AND entity = ".$conf->entity;

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $max = intval($obj->maxref);
            $nextnum = $max + 1;
            return 'EQU-'.sprintf('%04d', $nextnum);
        }
        return 'EQU-0001';
    }

    public function getNextEquipmentNumber()
    {
        global $conf, $db;

        $sql = "SELECT MAX(CAST(SUBSTRING(equipment_number FROM 2) AS SIGNED)) as maxnum";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE equipment_number LIKE 'A%'";
        $sql .= " AND entity = ".$conf->entity;

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            $max = intval($obj->maxnum);
            $nextnum = $max + 1;
            return 'A'.sprintf('%06d', $nextnum);
        }
        return 'A000001';
    }

    /**
     * Return a link to the object card (with optionaly the picto)
     * GEÄNDERT: Verweist jetzt auf equipment_view.php
     */
    public function getNomUrl($withpicto = 0, $option = '', $notooltip = 0, $maxlen = 24)
    {
        global $conf, $langs;

        $result = '';
        $url = DOL_URL_ROOT.'/custom/equipmentmanager/equipment_view.php?id='.$this->id;

        if ($withpicto) {
            $result .= '<a href="'.$url.'">';
            $result .= img_object(($notooltip ? '' : $langs->trans("ShowEquipment")), 'generic', ($notooltip ? '' : 'class="classfortooltip"'));
            $result .= '</a>';
        }

        $result .= '<a href="'.$url.'">';
        $result .= $this->ref;
        $result .= '</a>';

        return $result;
    }
    
    /**
     * Get equipment list for a third party (static method for use in other modules)
     *
     * @param DoliDB $db Database handler
     * @param int $socid Third party ID
     * @return array Array of Equipment objects
     */
    public static function fetchAllBySoc($db, $socid)
    {
        $equipments = array();
        
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment";
        $sql .= " WHERE fk_soc = ".(int)$socid;
        $sql .= " AND status = 1";  // Nur aktive Equipments
        $sql .= " ORDER BY equipment_number ASC";
        
        dol_syslog("Equipment::fetchAllBySoc - socid=".$socid, LOG_DEBUG);
        
        $resql = $db->query($sql);
        if ($resql) {
            $num = $db->num_rows($resql);
            dol_syslog("Equipment::fetchAllBySoc - found ".$num." equipments", LOG_DEBUG);
            
            $i = 0;
            while ($i < $num) {
                $obj = $db->fetch_object($resql);
                $equipment = new Equipment($db);
                if ($equipment->fetch($obj->rowid) > 0) {
                    $equipments[] = $equipment;
                }
                $i++;
            }
            $db->free($resql);
        } else {
            dol_syslog("Equipment::fetchAllBySoc - SQL Error: ".$db->lasterror(), LOG_ERR);
        }
        
        return $equipments;
    }
}