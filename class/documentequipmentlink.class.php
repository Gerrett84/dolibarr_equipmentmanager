<?php
/**
 * DocumentEquipmentLink - Links equipment to Propal or Commande
 * v4.4: Allows pre-linking equipment before creating Fichinter
 */

class DocumentEquipmentLink
{
    public $db;
    public $error = '';
    public $errors = array();

    public $id;
    public $fk_document;
    public $fk_equipment;
    public $link_type = 'service';
    public $date_creation;
    public $fk_user_creat;
    public $entity;

    // Joined fields
    public $equipment_number;
    public $equipment_label;
    public $equipment_type;
    public $location_note;

    // Document type: 'propal' or 'commande'
    private $document_type;
    private $table_name;
    private $fk_field;

    /**
     * Constructor
     * @param DoliDB $db Database handler
     * @param string $document_type 'propal' or 'commande'
     */
    public function __construct($db, $document_type = 'propal')
    {
        $this->db = $db;
        $this->setDocumentType($document_type);
    }

    /**
     * Set document type and table names
     */
    public function setDocumentType($type)
    {
        $this->document_type = $type;
        if ($type === 'commande') {
            $this->table_name = MAIN_DB_PREFIX . 'equipmentmanager_commande_equipment';
            $this->fk_field = 'fk_commande';
        } else {
            $this->table_name = MAIN_DB_PREFIX . 'equipmentmanager_propal_equipment';
            $this->fk_field = 'fk_propal';
        }
    }

    /**
     * Create link
     * @param User $user User object
     * @return int >0 if OK, <0 if KO
     */
    public function create($user)
    {
        global $conf;

        $this->entity = $conf->entity;
        $this->fk_user_creat = $user->id;

        $sql = "INSERT INTO " . $this->table_name . " (";
        $sql .= $this->fk_field . ", fk_equipment, link_type, date_creation, fk_user_creat, entity";
        $sql .= ") VALUES (";
        $sql .= (int) $this->fk_document . ",";
        $sql .= (int) $this->fk_equipment . ",";
        $sql .= "'" . $this->db->escape($this->link_type) . "',";
        $sql .= "'" . $this->db->idate(dol_now()) . "',";
        $sql .= (int) $this->fk_user_creat . ",";
        $sql .= (int) $this->entity;
        $sql .= ")";

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->id = $this->db->last_insert_id($this->table_name);
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
        $sql = "SELECT l.rowid, l." . $this->fk_field . " as fk_document, l.fk_equipment, l.link_type,";
        $sql .= " l.date_creation, l.fk_user_creat, l.entity,";
        $sql .= " e.equipment_number, e.label as equipment_label, e.equipment_type, e.location_note";
        $sql .= " FROM " . $this->table_name . " as l";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "equipmentmanager_equipment as e ON e.rowid = l.fk_equipment";
        $sql .= " WHERE l.rowid = " . (int) $id;

        $resql = $this->db->query($sql);
        if ($resql) {
            if ($obj = $this->db->fetch_object($resql)) {
                $this->id = $obj->rowid;
                $this->fk_document = $obj->fk_document;
                $this->fk_equipment = $obj->fk_equipment;
                $this->link_type = $obj->link_type;
                $this->date_creation = $obj->date_creation;
                $this->fk_user_creat = $obj->fk_user_creat;
                $this->entity = $obj->entity;
                $this->equipment_number = $obj->equipment_number;
                $this->equipment_label = $obj->equipment_label;
                $this->equipment_type = $obj->equipment_type;
                $this->location_note = $obj->location_note;
                return 1;
            }
            return 0;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Delete link
     */
    public function delete($user)
    {
        $sql = "DELETE FROM " . $this->table_name;
        $sql .= " WHERE rowid = " . (int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Delete by document and equipment
     */
    public function deleteByDocumentAndEquipment($fk_document, $fk_equipment)
    {
        $sql = "DELETE FROM " . $this->table_name;
        $sql .= " WHERE " . $this->fk_field . " = " . (int) $fk_document;
        $sql .= " AND fk_equipment = " . (int) $fk_equipment;

        $resql = $this->db->query($sql);
        if ($resql) {
            return 1;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Get all equipment linked to a document
     * @param int $fk_document Propal or Commande ID
     * @return array Array of DocumentEquipmentLink objects
     */
    public function fetchAllByDocument($fk_document)
    {
        $result = array();

        $sql = "SELECT l.rowid, l." . $this->fk_field . " as fk_document, l.fk_equipment, l.link_type,";
        $sql .= " l.date_creation, l.fk_user_creat, l.entity,";
        $sql .= " e.equipment_number, e.label as equipment_label, e.equipment_type, e.location_note,";
        $sql .= " s.nom as thirdparty_name";
        $sql .= " FROM " . $this->table_name . " as l";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "equipmentmanager_equipment as e ON e.rowid = l.fk_equipment";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON s.rowid = e.fk_soc";
        $sql .= " WHERE l." . $this->fk_field . " = " . (int) $fk_document;
        $sql .= " ORDER BY e.equipment_number ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $link = new DocumentEquipmentLink($this->db, $this->document_type);
                $link->id = $obj->rowid;
                $link->fk_document = $obj->fk_document;
                $link->fk_equipment = $obj->fk_equipment;
                $link->link_type = $obj->link_type;
                $link->date_creation = $obj->date_creation;
                $link->fk_user_creat = $obj->fk_user_creat;
                $link->entity = $obj->entity;
                $link->equipment_number = $obj->equipment_number;
                $link->equipment_label = $obj->equipment_label;
                $link->equipment_type = $obj->equipment_type;
                $link->location_note = $obj->location_note;
                $result[] = $link;
            }
            $this->db->free($resql);
        }
        return $result;
    }

    /**
     * Update link type
     */
    public function updateLinkType($link_type)
    {
        $sql = "UPDATE " . $this->table_name;
        $sql .= " SET link_type = '" . $this->db->escape($link_type) . "'";
        $sql .= " WHERE rowid = " . (int) $this->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            $this->link_type = $link_type;
            return 1;
        }
        $this->error = $this->db->lasterror();
        return -1;
    }

    /**
     * Copy equipment links from one document type to another
     * e.g., from Propal to Commande, or Commande to Fichinter
     *
     * @param int $source_id Source document ID
     * @param string $source_type Source document type ('propal' or 'commande')
     * @param int $target_id Target document ID
     * @param string $target_type Target document type ('commande' or 'fichinter')
     * @param User $user User object
     * @return int Number of links copied, or -1 on error
     */
    public static function copyLinks($db, $source_id, $source_type, $target_id, $target_type, $user)
    {
        // Fetch from source
        $sourceLink = new DocumentEquipmentLink($db, $source_type);
        $links = $sourceLink->fetchAllByDocument($source_id);

        if (empty($links)) {
            return 0;
        }

        $count = 0;

        if ($target_type === 'fichinter') {
            // Copy to Fichinter uses existing intervention_link table
            foreach ($links as $link) {
                $sql = "INSERT IGNORE INTO " . MAIN_DB_PREFIX . "equipmentmanager_intervention_link";
                $sql .= " (fk_intervention, fk_equipment, link_type, date_creation, fk_user_creat)";
                $sql .= " VALUES (";
                $sql .= (int) $target_id . ",";
                $sql .= (int) $link->fk_equipment . ",";
                $sql .= "'" . $db->escape($link->link_type) . "',";
                $sql .= "'" . $db->idate(dol_now()) . "',";
                $sql .= (int) $user->id;
                $sql .= ")";

                if ($db->query($sql)) {
                    $count++;
                }
            }
        } else {
            // Copy to Propal or Commande
            $targetLink = new DocumentEquipmentLink($db, $target_type);
            foreach ($links as $link) {
                $targetLink->id = 0;
                $targetLink->fk_document = $target_id;
                $targetLink->fk_equipment = $link->fk_equipment;
                $targetLink->link_type = $link->link_type;

                if ($targetLink->create($user) > 0) {
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Get formatted string for copy to clipboard
     */
    public function getFormattedString()
    {
        $str = "Anlage " . $this->equipment_number;
        if (!empty($this->equipment_label)) {
            $str .= " (" . $this->equipment_label . ")";
        }
        if (!empty($this->location_note)) {
            $str .= " - Standort: " . $this->location_note;
        }
        return $str;
    }

    /**
     * Get all formatted strings for a document
     */
    public static function getAllFormattedStrings($db, $document_type, $fk_document)
    {
        $link = new DocumentEquipmentLink($db, $document_type);
        $links = $link->fetchAllByDocument($fk_document);

        $strings = array();
        foreach ($links as $l) {
            $strings[] = $l->getFormattedString();
        }
        return $strings;
    }
}
