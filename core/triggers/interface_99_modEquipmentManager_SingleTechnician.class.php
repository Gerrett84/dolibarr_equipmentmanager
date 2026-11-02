<?php
/**
 * \file       core/triggers/interface_99_modEquipmentManager_SingleTechnician.class.php
 * \ingroup    equipmentmanager
 * \brief      Allow only one "Beteiligter am Serviceauftrag" (INTERVENING) per intervention.
 *             The PWA cannot yet handle several technicians on one order (planned for v6.2).
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class rejecting a second technician on an intervention
 */
class InterfaceSingleTechnician extends DolibarrTriggers
{
    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "equipmentmanager";
        $this->description = "Equipment Manager: only one technician (INTERVENING) per intervention";
        $this->version = '1.0';
        $this->picto = 'equipmentmanager@equipmentmanager';
    }

    /**
     * Run trigger
     *
     * add_contact() has already inserted the row inside a transaction; returning <0
     * makes it roll back and the contact page shows $object->error.
     *
     * @param string       $action Event code
     * @param CommonObject $object Intervention
     * @param User         $user   User doing the action
     * @param Translate    $langs  Translations
     * @param Conf         $conf   Configuration
     * @return int <0 if KO, 0 if nothing done, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if ($action !== 'FICHINTER_ADD_CONTACT' || empty($object->id)) {
            return 0;
        }

        // Only react when the contact just added has the INTERVENING role
        $sql = "SELECT tc.code FROM ".MAIN_DB_PREFIX."element_contact ec";
        $sql .= " JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
        $sql .= " WHERE ec.element_id = ".((int) $object->id)." AND tc.element = 'fichinter'";
        $sql .= " ORDER BY ec.rowid DESC LIMIT 1";
        $res = $this->db->query($sql);
        $last = $res ? $this->db->fetch_object($res) : null;
        if (!$last || $last->code !== 'INTERVENING') {
            return 0;
        }

        $sql = "SELECT COUNT(*) AS nb FROM ".MAIN_DB_PREFIX."element_contact ec";
        $sql .= " JOIN ".MAIN_DB_PREFIX."c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
        $sql .= " WHERE ec.element_id = ".((int) $object->id);
        $sql .= " AND tc.element = 'fichinter' AND tc.source = 'internal' AND tc.code = 'INTERVENING'";
        $res = $this->db->query($sql);
        $obj = $res ? $this->db->fetch_object($res) : null;

        if ($obj && (int) $obj->nb > 1) {
            $langs->load('equipmentmanager@equipmentmanager');
            $object->error = $langs->trans('ErrorOnlyOneTechnicianPerIntervention');
            $object->errors[] = $object->error;
            return -1;
        }

        return 0;
    }
}
