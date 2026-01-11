<?php
/* Copyright (C) 2024-2025 Equipment Manager
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class ChecklistResult extends CommonObject
{
    public $element = 'checklistresult';
    public $table_element = 'equipmentmanager_checklist_results';
    public $picto = 'list';

    // Status constants
    const STATUS_DRAFT = 0;
    const STATUS_COMPLETED = 1;
    const STATUS_PASSED = 2;
    const STATUS_FAILED = 3;

    public $rowid;
    public $ref;
    public $fk_template;
    public $fk_equipment;
    public $fk_intervention;
    public $fk_equipment_intervention;
    public $status;
    public $passed;
    public $work_date;
    public $note_public;
    public $note_private;
    public $date_creation;
    public $date_completion;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $fk_user_completion;
    public $entity;

    // Loaded objects
    public $template;
    public $item_results = array();

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Create checklist result
     *
     * @param User $user User object
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function create(User $user, $notrigger = 0)
    {
        global $conf;

        $error = 0;
        $this->db->begin();

        if (empty($this->ref)) {
            $this->ref = $this->getNextNumRef();
        }

        $now = dol_now();

        $sql = "INSERT INTO ".MAIN_DB_PREFIX.$this->table_element." (";
        $sql .= "ref, fk_template, fk_equipment, fk_intervention, fk_equipment_intervention,";
        $sql .= "status, passed, work_date, note_public, note_private,";
        $sql .= "date_creation, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= "'".$this->db->escape($this->ref)."',";
        $sql .= (int)$this->fk_template.",";
        $sql .= (int)$this->fk_equipment.",";
        $sql .= ($this->fk_intervention > 0 ? (int)$this->fk_intervention : 'NULL').",";
        $sql .= ($this->fk_equipment_intervention > 0 ? (int)$this->fk_equipment_intervention : 'NULL').",";
        $sql .= (int)$this->status.",";
        $sql .= "NULL,";
        $sql .= ($this->work_date ? "'".$this->db->idate($this->work_date)."'" : 'NULL').",";
        $sql .= ($this->note_public ? "'".$this->db->escape($this->note_public)."'" : 'NULL').",";
        $sql .= ($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL').",";
        $sql .= "'".$this->db->idate($now)."',";
        $sql .= ($user->id > 0 ? $user->id : 'NULL').",";
        $sql .= $conf->entity;
        $sql .= ")";

        dol_syslog(get_class($this)."::create", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->id = $this->db->last_insert_id(MAIN_DB_PREFIX.$this->table_element);
            $this->rowid = $this->id;

            $this->db->commit();
            return $this->id;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Load checklist result
     *
     * @param int $id ID
     * @param string $ref Reference
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id, $ref = '')
    {
        $sql = "SELECT rowid, ref, fk_template, fk_equipment, fk_intervention, fk_equipment_intervention,";
        $sql .= " status, passed, work_date, note_public, note_private,";
        $sql .= " date_creation, date_completion, tms, fk_user_creat, fk_user_modif, fk_user_completion, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        if ($id > 0) {
            $sql .= " WHERE rowid = ".(int)$id;
        } elseif ($ref) {
            $sql .= " WHERE ref = '".$this->db->escape($ref)."'";
        }

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->rowid = $obj->rowid;
                $this->id = $obj->rowid;
                $this->ref = $obj->ref;
                $this->fk_template = $obj->fk_template;
                $this->fk_equipment = $obj->fk_equipment;
                $this->fk_intervention = $obj->fk_intervention;
                $this->fk_equipment_intervention = $obj->fk_equipment_intervention;
                $this->status = $obj->status;
                $this->passed = $obj->passed;
                $this->work_date = $this->db->jdate($obj->work_date);
                $this->note_public = $obj->note_public;
                $this->note_private = $obj->note_private;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->date_completion = $this->db->jdate($obj->date_completion);
                $this->tms = $this->db->jdate($obj->tms);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
                $this->fk_user_completion = $obj->fk_user_completion;
                $this->entity = $obj->entity;

                $this->db->free($resql);
                return 1;
            }
            $this->db->free($resql);
            return 0;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Update checklist result
     *
     * @param User $user User object
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function update(User $user, $notrigger = 0)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX.$this->table_element." SET";
        $sql .= " status = ".(int)$this->status.",";
        $sql .= " passed = ".($this->passed !== null ? (int)$this->passed : 'NULL').",";
        $sql .= " work_date = ".($this->work_date ? "'".$this->db->idate($this->work_date)."'" : 'NULL').",";
        $sql .= " note_public = ".($this->note_public ? "'".$this->db->escape($this->note_public)."'" : 'NULL').",";
        $sql .= " note_private = ".($this->note_private ? "'".$this->db->escape($this->note_private)."'" : 'NULL').",";
        $sql .= " date_completion = ".($this->date_completion ? "'".$this->db->idate($this->date_completion)."'" : 'NULL').",";
        $sql .= " fk_user_modif = ".($user->id > 0 ? $user->id : 'NULL').",";
        $sql .= " fk_user_completion = ".($this->fk_user_completion > 0 ? (int)$this->fk_user_completion : 'NULL');
        $sql .= " WHERE rowid = ".(int)$this->id;

        dol_syslog(get_class($this)."::update", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Delete checklist result and all item results
     *
     * @param User $user User object
     * @param int $notrigger Disable triggers
     * @return int <0 if KO, >0 if OK
     */
    public function delete(User $user, $notrigger = 0)
    {
        $this->db->begin();

        // Delete item results first
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " WHERE fk_checklist_result = ".(int)$this->id;

        dol_syslog(get_class($this)."::delete item_results", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if (!$resql) {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }

        // Delete checklist result
        $sql = "DELETE FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$this->id;

        dol_syslog(get_class($this)."::delete", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * Load item results
     *
     * @return int Number of results loaded, <0 if error
     */
    public function fetchItemResults()
    {
        $this->item_results = array();

        $sql = "SELECT rowid, fk_checklist_result, fk_checklist_item, answer, answer_text, note, date_creation, tms";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " WHERE fk_checklist_result = ".(int)$this->id;

        dol_syslog(get_class($this)."::fetchItemResults", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $this->item_results[$obj->fk_checklist_item] = array(
                    'id' => $obj->rowid,
                    'fk_checklist_item' => $obj->fk_checklist_item,
                    'answer' => $obj->answer,
                    'answer_text' => $obj->answer_text,
                    'note' => $obj->note
                );
            }
            $this->db->free($resql);
            return count($this->item_results);
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Save item result
     *
     * @param int $item_id Checklist item ID
     * @param string $answer Answer value (ok, mangel, nv, ja, nein)
     * @param string $answer_text Text answer for info fields
     * @param string $note Additional note
     * @return int <0 if KO, >0 if OK
     */
    public function saveItemResult($item_id, $answer, $answer_text = '', $note = '')
    {
        $now = dol_now();

        // Check if result already exists
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " WHERE fk_checklist_result = ".(int)$this->id;
        $sql .= " AND fk_checklist_item = ".(int)$item_id;

        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql)) {
            $obj = $this->db->fetch_object($resql);
            // Update existing
            $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results SET";
            $sql .= " answer = ".($answer ? "'".$this->db->escape($answer)."'" : 'NULL').",";
            $sql .= " answer_text = ".($answer_text ? "'".$this->db->escape($answer_text)."'" : 'NULL').",";
            $sql .= " note = ".($note ? "'".$this->db->escape($note)."'" : 'NULL');
            $sql .= " WHERE rowid = ".(int)$obj->rowid;
        } else {
            // Insert new
            $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
            $sql .= " (fk_checklist_result, fk_checklist_item, answer, answer_text, note, date_creation)";
            $sql .= " VALUES (";
            $sql .= (int)$this->id.",";
            $sql .= (int)$item_id.",";
            $sql .= ($answer ? "'".$this->db->escape($answer)."'" : 'NULL').",";
            $sql .= ($answer_text ? "'".$this->db->escape($answer_text)."'" : 'NULL').",";
            $sql .= ($note ? "'".$this->db->escape($note)."'" : 'NULL').",";
            $sql .= "'".$this->db->idate($now)."'";
            $sql .= ")";
        }

        dol_syslog(get_class($this)."::saveItemResult", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Complete checklist and evaluate result
     *
     * @param User $user User object
     * @return int <0 if KO, >0 if OK
     */
    public function complete(User $user)
    {
        // Load item results
        $this->fetchItemResults();

        // Check if all required items have answers
        // and determine if passed or failed
        $passed = true;
        foreach ($this->item_results as $item_id => $result) {
            // If any answer is 'mangel' or 'nein' for the final question, mark as failed
            if ($result['answer'] == 'mangel' || $result['answer'] == 'nein') {
                $passed = false;
            }
        }

        $this->status = self::STATUS_COMPLETED;
        $this->passed = $passed ? 1 : 0;
        $this->date_completion = dol_now();
        $this->fk_user_completion = $user->id;

        return $this->update($user);
    }

    /**
     * Get next reference number
     *
     * @return string Next reference
     */
    public function getNextNumRef()
    {
        global $conf;

        $sql = "SELECT MAX(CAST(SUBSTRING(ref, 5) AS UNSIGNED)) as maxref";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE ref LIKE 'CKL-%'";
        $sql .= " AND entity = ".$conf->entity;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            $num = $obj->maxref ? $obj->maxref + 1 : 1;
            return 'CKL-'.str_pad($num, 6, '0', STR_PAD_LEFT);
        }
        return 'CKL-000001';
    }

    /**
     * Fetch checklist for equipment and intervention
     *
     * @param int $fk_equipment Equipment ID
     * @param int $fk_equipment_intervention Equipment-Intervention link ID
     * @return int <0 if KO, 0 if not found, >0 if OK
     */
    public function fetchByEquipmentIntervention($fk_equipment, $fk_equipment_intervention)
    {
        $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE fk_equipment = ".(int)$fk_equipment;
        $sql .= " AND fk_equipment_intervention = ".(int)$fk_equipment_intervention;

        dol_syslog(get_class($this)."::fetchByEquipmentIntervention", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);
                return $this->fetch($obj->rowid);
            }
            return 0;
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Get status label
     *
     * @param int $mode Mode (0=long, 1=short)
     * @return string Status label
     */
    public function getLibStatut($mode = 0)
    {
        return $this->LibStatut($this->status, $this->passed, $mode);
    }

    /**
     * Get status label static
     *
     * @param int $status Status
     * @param int $passed Passed flag
     * @param int $mode Mode (0=long, 1=short)
     * @return string Status label
     */
    public function LibStatut($status, $passed, $mode = 0)
    {
        global $langs;

        $langs->load('equipmentmanager@equipmentmanager');

        $statusLabel = '';
        $statusClass = '';

        switch ($status) {
            case self::STATUS_DRAFT:
                $statusLabel = $langs->trans('ChecklistDraft');
                $statusClass = 'status0';
                break;
            case self::STATUS_COMPLETED:
                if ($passed) {
                    $statusLabel = $langs->trans('ChecklistPassed');
                    $statusClass = 'status4';
                } else {
                    $statusLabel = $langs->trans('ChecklistFailed');
                    $statusClass = 'status8';
                }
                break;
        }

        if ($mode == 0) {
            return '<span class="badge badge-status '.$statusClass.'">'.$statusLabel.'</span>';
        }
        return $statusLabel;
    }
}
