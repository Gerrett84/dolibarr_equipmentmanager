<?php
/**
 * DefectMaterial class - Materials needed to fix defects
 */

class DefectMaterial
{
    public $db;
    public $table_element = 'equipmentmanager_defect_material';

    public $id;
    public $rowid;
    public $fk_intervention_detail;
    public $fk_product;
    public $qty;
    public $description;
    public $date_creation;
    public $fk_user_creat;
    public $entity;

    // Loaded product data
    public $product_ref;
    public $product_label;
    public $product_price;

    /**
     * Constructor
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Create defect material
     */
    public function create($user)
    {
        global $conf;

        $this->entity = $conf->entity;
        $this->date_creation = dol_now();
        $this->fk_user_creat = $user->id;

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_intervention_detail, fk_product, qty, description,";
        $sql .= " date_creation, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= (int)$this->fk_intervention_detail.",";
        $sql .= (int)$this->fk_product.",";
        $sql .= (float)($this->qty ?: 1).",";
        $sql .= ($this->description ? "'".$this->db->escape($this->description)."'" : "NULL").",";
        $sql .= "'".$this->db->idate($this->date_creation)."',";
        $sql .= (int)$this->fk_user_creat.",";
        $sql .= (int)$this->entity;
        $sql .= ")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Fetch by ID
     */
    public function fetch($id)
    {
        $sql = "SELECT m.*, p.ref as product_ref, p.label as product_label, p.price as product_price";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element." m";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
        $sql .= " WHERE m.rowid = ".(int)$id;

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);
                $this->setVarsFromFetchObj($obj);
                return 1;
            }
            return 0;
        }
        return -1;
    }

    /**
     * Set vars from fetch object
     */
    private function setVarsFromFetchObj($obj)
    {
        $this->id = $obj->rowid;
        $this->rowid = $obj->rowid;
        $this->fk_intervention_detail = $obj->fk_intervention_detail;
        $this->fk_product = $obj->fk_product;
        $this->qty = $obj->qty;
        $this->description = $obj->description;
        $this->date_creation = $this->db->jdate($obj->date_creation);
        $this->fk_user_creat = $obj->fk_user_creat;
        $this->entity = $obj->entity;
        $this->product_ref = $obj->product_ref ?? '';
        $this->product_label = $obj->product_label ?? '';
        $this->product_price = $obj->product_price ?? 0;
    }

    /**
     * Delete
     */
    public function delete($user)
    {
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        }
        return -1;
    }

    /**
     * Fetch all materials for an intervention detail (entry)
     */
    public static function fetchAllForEntry($db, $fk_intervention_detail)
    {
        $materials = array();

        $sql = "SELECT m.*, p.ref as product_ref, p.label as product_label, p.price as product_price";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_defect_material m";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
        $sql .= " WHERE m.fk_intervention_detail = ".(int)$fk_intervention_detail;
        $sql .= " ORDER BY m.rowid ASC";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $mat = new DefectMaterial($db);
                $mat->setVarsFromFetchObj($obj);
                $materials[] = $mat;
            }
            $db->free($resql);
        }

        return $materials;
    }

    /**
     * Fetch all materials for an intervention (all entries)
     */
    public static function fetchAllForIntervention($db, $fk_intervention)
    {
        $materials = array();

        $sql = "SELECT m.*, p.ref as product_ref, p.label as product_label, p.price as product_price,";
        $sql .= " d.fk_equipment, d.issues_found";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_defect_material m";
        $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = m.fk_product";
        $sql .= " INNER JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d ON d.rowid = m.fk_intervention_detail";
        $sql .= " WHERE d.fk_intervention = ".(int)$fk_intervention;
        $sql .= " ORDER BY d.fk_equipment, m.rowid ASC";

        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $mat = new DefectMaterial($db);
                $mat->setVarsFromFetchObj($obj);
                $mat->fk_equipment = $obj->fk_equipment;
                $mat->issues_found = $obj->issues_found;
                $materials[] = $mat;
            }
            $db->free($resql);
        }

        return $materials;
    }
}
