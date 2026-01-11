<?php
/* Copyright (C) 2024-2025 Equipment Manager
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

class ChecklistTemplate extends CommonObject
{
    public $element = 'checklisttemplate';
    public $table_element = 'equipmentmanager_checklist_templates';
    public $picto = 'list';

    public $rowid;
    public $equipment_type_code;
    public $label;
    public $description;
    public $norm_reference;
    public $active;
    public $position;
    public $date_creation;
    public $tms;
    public $fk_user_creat;
    public $fk_user_modif;
    public $entity;

    // Cache for sections and items
    public $sections = array();

    public function __construct(DoliDB $db)
    {
        $this->db = $db;
    }

    /**
     * Load template by equipment type code
     *
     * @param string $equipment_type_code Equipment type code
     * @param int $entity Entity ID
     * @return int <0 if KO, >0 if OK
     */
    public function fetchByEquipmentType($equipment_type_code, $entity = 0)
    {
        global $conf;

        if (empty($entity)) {
            $entity = $conf->entity;
        }

        $sql = "SELECT rowid, equipment_type_code, label, description, norm_reference,";
        $sql .= " active, position, date_creation, tms, fk_user_creat, fk_user_modif, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE equipment_type_code = '".$this->db->escape($equipment_type_code)."'";
        $sql .= " AND entity = ".(int)$entity;
        $sql .= " AND active = 1";

        dol_syslog(get_class($this)."::fetchByEquipmentType", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->rowid = $obj->rowid;
                $this->id = $obj->rowid;
                $this->equipment_type_code = $obj->equipment_type_code;
                $this->label = $obj->label;
                $this->description = $obj->description;
                $this->norm_reference = $obj->norm_reference;
                $this->active = $obj->active;
                $this->position = $obj->position;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->tms = $this->db->jdate($obj->tms);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
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
     * Load template by ID
     *
     * @param int $id Template ID
     * @return int <0 if KO, >0 if OK
     */
    public function fetch($id)
    {
        $sql = "SELECT rowid, equipment_type_code, label, description, norm_reference,";
        $sql .= " active, position, date_creation, tms, fk_user_creat, fk_user_modif, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE rowid = ".(int)$id;

        dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $obj = $this->db->fetch_object($resql);

                $this->rowid = $obj->rowid;
                $this->id = $obj->rowid;
                $this->equipment_type_code = $obj->equipment_type_code;
                $this->label = $obj->label;
                $this->description = $obj->description;
                $this->norm_reference = $obj->norm_reference;
                $this->active = $obj->active;
                $this->position = $obj->position;
                $this->date_creation = $this->db->jdate($obj->date_creation);
                $this->tms = $this->db->jdate($obj->tms);
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->fk_user_modif = $obj->fk_user_modif;
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
     * Load all sections with items for this template
     *
     * @return int Number of sections loaded, <0 if error
     */
    public function fetchSectionsWithItems()
    {
        $this->sections = array();

        $sql = "SELECT rowid, fk_template, code, label, description, position, active";
        $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_sections";
        $sql .= " WHERE fk_template = ".(int)$this->id;
        $sql .= " AND active = 1";
        $sql .= " ORDER BY position ASC";

        dol_syslog(get_class($this)."::fetchSectionsWithItems sections", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            $num = $this->db->num_rows($resql);
            for ($i = 0; $i < $num; $i++) {
                $obj = $this->db->fetch_object($resql);

                $section = new stdClass();
                $section->id = $obj->rowid;
                $section->fk_template = $obj->fk_template;
                $section->code = $obj->code;
                $section->label = $obj->label;
                $section->description = $obj->description;
                $section->position = $obj->position;
                $section->items = array();

                // Load items for this section
                $sqlItems = "SELECT rowid, fk_section, code, label, description, answer_type, required, position, active";
                $sqlItems .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_items";
                $sqlItems .= " WHERE fk_section = ".(int)$section->id;
                $sqlItems .= " AND active = 1";
                $sqlItems .= " ORDER BY position ASC";

                $resqlItems = $this->db->query($sqlItems);
                if ($resqlItems) {
                    while ($objItem = $this->db->fetch_object($resqlItems)) {
                        $item = new stdClass();
                        $item->id = $objItem->rowid;
                        $item->fk_section = $objItem->fk_section;
                        $item->code = $objItem->code;
                        $item->label = $objItem->label;
                        $item->description = $objItem->description;
                        $item->answer_type = $objItem->answer_type;
                        $item->required = $objItem->required;
                        $item->position = $objItem->position;
                        $section->items[] = $item;
                    }
                    $this->db->free($resqlItems);
                }

                $this->sections[] = $section;
            }
            $this->db->free($resql);
            return count($this->sections);
        } else {
            $this->error = $this->db->lasterror();
            return -1;
        }
    }

    /**
     * Get all templates
     *
     * @param int $entity Entity ID
     * @param int $activeonly Only active templates
     * @return array Array of templates
     */
    public function fetchAll($entity = 0, $activeonly = 1)
    {
        global $conf;

        if (empty($entity)) {
            $entity = $conf->entity;
        }

        $templates = array();

        $sql = "SELECT rowid, equipment_type_code, label, description, norm_reference,";
        $sql .= " active, position, date_creation, tms, fk_user_creat, fk_user_modif, entity";
        $sql .= " FROM ".MAIN_DB_PREFIX.$this->table_element;
        $sql .= " WHERE entity = ".(int)$entity;
        if ($activeonly) {
            $sql .= " AND active = 1";
        }
        $sql .= " ORDER BY position ASC";

        dol_syslog(get_class($this)."::fetchAll", LOG_DEBUG);
        $resql = $this->db->query($sql);

        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $template = new stdClass();
                $template->id = $obj->rowid;
                $template->equipment_type_code = $obj->equipment_type_code;
                $template->label = $obj->label;
                $template->description = $obj->description;
                $template->norm_reference = $obj->norm_reference;
                $template->active = $obj->active;
                $template->position = $obj->position;
                $templates[] = $template;
            }
            $this->db->free($resql);
        }

        return $templates;
    }

    /**
     * Get translated label
     *
     * @param Translate $langs Language object
     * @return string Translated label
     */
    public function getLabel($langs)
    {
        $translated = $langs->trans($this->label);
        return ($translated != $this->label) ? $translated : $this->label;
    }
}
