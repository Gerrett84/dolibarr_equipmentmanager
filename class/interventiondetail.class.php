<?php
/* Copyright (C) 2024 Equipment Manager
 * Intervention Detail Class - v1.7
 * Equipment-spezifische Berichtstexte mit mehreren Einträgen
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class InterventionDetail extends CommonObject
{
    public $element = 'interventiondetail';
    public $table_element = 'equipmentmanager_intervention_detail';
    public $picto = 'generic';

    public $fk_intervention;
    public $fk_equipment;
    public $entry_number;
    public $report_text;
    public $work_done;
    public $issues_found;
    public $recommendations;
    public $notes;
    public $work_date;
    public $work_duration;
    public $date_creation;
    public $fk_user_creat;
    public $fk_user_modif;

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Create intervention detail
     */
    public function create(User $user, $notrigger = 0)
    {
        $error = 0;
        $now = dol_now();

        // Get next entry number if not set
        if (empty($this->entry_number)) {
            $this->entry_number = $this->getNextEntryNumber($this->fk_intervention, $this->fk_equipment);
        }

        $this->db->begin();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "fk_intervention,";
        $sql .= "fk_equipment,";
        $sql .= "entry_number,";
        $sql .= "report_text,";
        $sql .= "work_done,";
        $sql .= "issues_found,";
        $sql .= "recommendations,";
        $sql .= "notes,";
        $sql .= "work_date,";
        $sql .= "work_duration,";
        $sql .= "date_creation,";
        $sql .= "fk_user_creat";
        $sql .= ") VALUES (";
        $sql .= (int)$this->fk_intervention.",";
        $sql .= (int)$this->fk_equipment.",";
        $sql .= (int)$this->entry_number.",";
        $sql .= ($this->report_text ? "'".$this->db->escape($this->report_text)."'" : "NULL").",";
        $sql .= ($this->work_done ? "'".$this->db->escape($this->work_done)."'" : "NULL").",";
        $sql .= ($this->issues_found ? "'".$this->db->escape($this->issues_found)."'" : "NULL").",";
        $sql .= ($this->recommendations ? "'".$this->db->escape($this->recommendations)."'" : "NULL").",";
        $sql .= ($this->notes ? "'".$this->db->escape($this->notes)."'" : "NULL").",";
        $sql .= ($this->work_date ? "'".$this->db->idate($this->work_date)."'" : "NULL").",";
        $sql .= ($this->work_duration ? (int)$this->work_duration : "0").",";
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
     * Get next entry number for intervention + equipment
     */
    public function getNextEntryNumber($fk_intervention, $fk_equipment)
    {
        $sql = "SELECT MAX(entry_number) as max_entry FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return ($obj->max_entry ? $obj->max_entry + 1 : 1);
        }
        return 1;
    }

    /**
     * Load detail by intervention + equipment + entry_number
     * If entry_number is 0 or not provided, loads the first entry
     */
    public function fetchByInterventionEquipment($fk_intervention, $fk_equipment, $entry_number = 0)
    {
        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;
        if ($entry_number > 0) {
            $sql .= " AND entry_number = ".(int)$entry_number;
        }
        $sql .= " ORDER BY entry_number ASC";
        $sql .= " LIMIT 1";

        dol_syslog(get_class($this)."::fetchByInterventionEquipment", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);
                $this->setVarsFromFetchObj($obj);
                return 1;
            }
            return 0;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Load detail by rowid
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
                $this->setVarsFromFetchObj($obj);
                return 1;
            }
            return 0;
        } else {
            $this->errors[] = 'Error '.$this->db->lasterror();
            return -1;
        }
    }

    /**
     * Set object properties from database row
     */
    public function setVarsFromFetchObj($obj)
    {
        $this->id = $obj->rowid;
        $this->fk_intervention = $obj->fk_intervention;
        $this->fk_equipment = $obj->fk_equipment;
        $this->entry_number = $obj->entry_number;
        $this->report_text = $obj->report_text;
        $this->work_done = $obj->work_done;
        $this->issues_found = $obj->issues_found;
        $this->recommendations = $obj->recommendations;
        $this->notes = $obj->notes;
        $this->work_date = $this->db->jdate($obj->work_date);
        $this->work_duration = $obj->work_duration;
        $this->date_creation = $this->db->jdate($obj->date_creation);
        $this->fk_user_creat = $obj->fk_user_creat;
        $this->fk_user_modif = $obj->fk_user_modif;
    }

    /**
     * Fetch all entries for intervention + equipment
     * @return array Array of InterventionDetail objects
     */
    public function fetchAllByInterventionEquipment($fk_intervention, $fk_equipment)
    {
        $entries = array();

        $sql = "SELECT * FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;
        $sql .= " ORDER BY entry_number DESC"; // Newest first

        dol_syslog(get_class($this)."::fetchAllByInterventionEquipment", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $entry = new InterventionDetail($this->db);
                $entry->setVarsFromFetchObj($obj);
                $entries[] = $entry;
            }
            $this->db->free($resql);
        }

        return $entries;
    }

    /**
     * Get total duration of all entries for intervention + equipment
     */
    public function getTotalDuration($fk_intervention, $fk_equipment)
    {
        $sql = "SELECT SUM(work_duration) as total FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_intervention = ".(int)$fk_intervention;
        $sql .= " AND fk_equipment = ".(int)$fk_equipment;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return (int)$obj->total;
        }
        return 0;
    }

    /**
     * Update intervention detail
     */
    public function update(User $user, $notrigger = 0)
    {
        $error = 0;

        $this->db->begin();

        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " report_text = ".($this->report_text ? "'".$this->db->escape($this->report_text)."'" : "NULL").",";
        $sql .= " work_done = ".($this->work_done ? "'".$this->db->escape($this->work_done)."'" : "NULL").",";
        $sql .= " issues_found = ".($this->issues_found ? "'".$this->db->escape($this->issues_found)."'" : "NULL").",";
        $sql .= " recommendations = ".($this->recommendations ? "'".$this->db->escape($this->recommendations)."'" : "NULL").",";
        $sql .= " notes = ".($this->notes ? "'".$this->db->escape($this->notes)."'" : "NULL").",";
        $sql .= " work_date = ".($this->work_date ? "'".$this->db->idate($this->work_date)."'" : "NULL").",";
        $sql .= " work_duration = ".($this->work_duration ? (int)$this->work_duration : "0").",";
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
     * Create or update (upsert) - for backwards compatibility
     * Updates if id is set, creates new entry otherwise
     */
    public function createOrUpdate(User $user)
    {
        if (!empty($this->id)) {
            return $this->update($user);
        } else {
            return $this->create($user);
        }
    }

    /**
     * Delete intervention detail
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
}
