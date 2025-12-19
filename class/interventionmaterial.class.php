<?php
/* Copyright (C) 2024 Equipment Manager
 * Intervention Material Class - v1.6
 * Material-/Ersatzteil-Verbuchung pro Equipment
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class InterventionMaterial extends CommonObject
{
    public $element = 'interventionmaterial';
    public $table_element = 'equipmentmanager_intervention_material';
    public $picto = 'product';

    public $fk_intervention;
    public $fk_equipment;
    public $fk_product;
    public $material_name;
    public $material_description;
    public $quantity;
    public $unit;
    public $unit_price;
    public $total_price;
    public $serial_number;
    public $notes;
    public $date_creation;
    public $fk_user_creat;
    public $fk_user_modif;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Create material entry
     */
    public function create(User $user, $notrigger = 0)
    {
        $error = 0;
        $now = dol_now();

        $this->db->begin();

        // Calculate total price
        if (!isset($this->total_price) || $this->total_price == 0) {
            $this->total_price = $this->quantity * $this->unit_price;
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_intervention,";
        $sql .= "fk_equipment,";
        $sql .= "fk_product,";
        $sql .= "material_name,";
        $sql .= "material_description,";
        $sql .= "quantity,";
        $sql .= "unit,";
        $sql .= "unit_price,";
        $sql .= "total_price,";
        $sql .= "serial_number,";
        $sql .= "notes,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= (int)$this->fk_intervention.",";
        $sql .= (int)$this->fk_equipment.",";
        $sql .= ($this->fk_product > 0 ? (int)$this->fk_product : "NULL").",";
        $sql .= "'".$this->db->escape($this->material_name)."',";
        $sql .= ($this->material_description ? "'".$this->db->escape($this->material_description)."'" : "NULL").",";
        $sql .= (float)$this->quantity.",";
        $sql .= "'".$this->db->escape($this->unit)."',";
        $sql .= (float)$this->unit_price.",";
        $sql .= (float)$this->total_price.",";
        $sql .= ($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : "NULL").",";
        $sql .= ($this->notes ? "'".$this->db->escape($this->notes)."'" : "NULL").",";
        $sql .= "'".$this->db->idate($now)."',";
        $sql .= (int)$user->id;
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

    /**
     * Load material entry
     */
    public function fetch($id)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$id;

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->id = $obj->rowid;
                $this->fk_intervention = $obj->fk_intervention;
                $this->fk_equipment = $obj->fk_equipment;
                $this->fk_product = $obj->fk_product;
                $this->material_name = $obj->material_name;
                $this->material_description = $obj->material_description;
                $this->quantity = $obj->quantity;
                $this->unit = $obj->unit;
                $this->unit_price = $obj->unit_price;
                $this->total_price = $obj->total_price;
                $this->serial_number = $obj->serial_number;
                $this->notes = $obj->notes;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;

                return 1;
            }
            return 0;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update material entry
     */
    public function update(User $user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        // Recalculate total price
        $this->total_price = $this->quantity * $this->unit_price;

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " fk_product = ".($this->fk_product > 0 ? (int)$this->fk_product : "NULL").",";
        $sql .= " material_name = '".$this->db->escape($this->material_name)."',";
        $sql .= " material_description = ".($this->material_description ? "'".$this->db->escape($this->material_description)."'" : "NULL").",";
        $sql .= " quantity = ".(float)$this->quantity.",";
        $sql .= " unit = '".$this->db->escape($this->unit)."',";
        $sql .= " unit_price = ".(float)$this->unit_price.",";
        $sql .= " total_price = ".(float)$this->total_price.",";
        $sql .= " serial_number = ".($this->serial_number ? "'".$this->db->escape($this->serial_number)."'" : "NULL").",";
        $sql .= " notes = ".($this->notes ? "'".$this->db->escape($this->notes)."'" : "NULL").",";
        $sql .= " fk_user_modif = ".(int)$user->id;
        $sql .= " WHERE rowid = ".(int)$this->id;

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

    /**
     * Delete material entry
     */
    public function delete(User $user, $notrigger = 0)
    {
        $this->db->begin();

        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$this->id;

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

    /**
     * Get all materials for equipment on intervention
     */
    public static function fetchAllForEquipment($db, $fk_intervention, $fk_equipment)
    {
        $materials = array();

        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;
        $sql .= " ORDER BY date_creation ASC";

        dol_syslog("InterventionMaterial::fetchAllForEquipment", LOG_DEBUG);

        $resql = $db->query($sql);
        if ($resql) {
            $num = $db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $db->fetch_object($resql);
                $material = new InterventionMaterial($db);
                if ($material->fetch($obj->rowid) > 0) {
                    $materials[] = $material;
                }
                $i++;
            }
            $db->free($resql);
        }

        return $materials;
    }

    /**
     * Get total price for equipment
     */
    public static function getTotalForEquipment($db, $fk_intervention, $fk_equipment)
    {
        $sql = "SELECT SUM(total_price) as total";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            return $obj->total ? (float)$obj->total : 0;
        }

        return 0;
    }

    /**
     * Get total price for entire intervention
     */
    public static function getTotalForIntervention($db, $fk_intervention)
    {
        $sql = "SELECT SUM(total_price) as total";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;

        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            return $obj->total ? (float)$obj->total : 0;
        }

        return 0;
    }
}