<?php
/**
 * \file       core/triggers/interface_99_modEquipmentManager_ReleaseState.class.php
 * \ingroup    equipmentmanager
 * \brief      Keep the PWA release flag (fichinter.signed_status) in line with what is done
 *             in the backend: "Ändern" (back to draft) makes the order editable again,
 *             validating it releases it for signature. A signature (3) is never touched.
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class syncing signed_status with backend status changes
 */
class InterfaceReleaseState extends DolibarrTriggers
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
        $this->description = "Equipment Manager: sync PWA release state with backend validate/unvalidate";
        $this->version = '1.0';
        $this->picto = 'equipmentmanager@equipmentmanager';
    }

    /**
     * Run trigger
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
        if (empty($object->id)) {
            return 0;
        }
        if ($action === 'FICHINTER_UNVALIDATE') {
            $this->db->query("UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 0"
                ." WHERE rowid = ".((int) $object->id)." AND COALESCE(signed_status, 0) < 3");
        } elseif ($action === 'FICHINTER_VALIDATE') {
            $this->db->query("UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 1"
                ." WHERE rowid = ".((int) $object->id)." AND COALESCE(signed_status, 0) < 1");
        }
        return 0;
    }
}
