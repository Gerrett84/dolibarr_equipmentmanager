<?php
/**
 * \file       core/triggers/interface_99_modEquipmentManager_PwaTokens.class.php
 * \ingroup    equipmentmanager
 * \brief      Revoke a user's PWA tokens when the password changes (stolen/lost devices
 *             must not stay logged in after a password reset).
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 * Trigger class for PWA token revocation
 */
class InterfacePwaTokens extends DolibarrTriggers
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
        $this->description = "Equipment Manager PWA token revocation on password change";
        $this->version = '1.0';
        $this->picto = 'equipmentmanager@equipmentmanager';
    }

    /**
     * Run trigger
     *
     * @param string       $action Event code
     * @param CommonObject $object Object (the user whose password changed)
     * @param User         $user   User doing the action
     * @param Translate    $langs  Translations
     * @param Conf         $conf   Configuration
     * @return int <0 if KO, 0 if nothing done, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if ($action !== 'USER_NEW_PASSWORD' || empty($object->id)) {
            return 0;
        }

        // The device that just changed its own password via the PWA stays logged in
        $keep = !empty($GLOBALS['eqm_keep_pwa_token_hash']) ? $GLOBALS['eqm_keep_pwa_token_hash'] : '';

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token WHERE fk_user = ".((int) $object->id);
        if ($keep !== '') {
            $sql .= " AND token <> '".$this->db->escape($keep)."'";
        }
        $this->db->query($sql);

        return 0;
    }
}
