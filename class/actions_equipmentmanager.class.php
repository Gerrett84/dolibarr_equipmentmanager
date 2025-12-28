<?php
/* Copyright (C) 2024 Equipment Manager
 * Hook class for Equipment Manager module
 * Adds PWA link to Dolibarr top bar
 */

/**
 * Class ActionsEquipmentManager
 */
class ActionsEquipmentManager
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();

    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the printTopRightMenu function
     * Adds PWA icon to the top right menu
     *
     * @param array $parameters Parameters
     * @param CommonObject $object Object to modify
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return int <0 if error, 0 if nothing done, >0 if OK
     */
    public function printTopRightMenu($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        // Check if user has permission to access interventions
        if (!$user->hasRight('ficheinter', 'lire')) {
            return 0;
        }

        $langs->load("equipmentmanager@equipmentmanager");

        // Build the PWA link
        $pwaUrl = dol_buildpath('/equipmentmanager/pwa/', 1);

        // Add the icon to the top right menu
        $this->resprints = '
        <div class="login_block_elem login_block_elem_pwa" style="padding: 0 8px;">
            <a href="' . $pwaUrl . '" target="_blank" title="' . $langs->trans("ServiceReportPWA") . '" class="atoploginpwa">
                <span class="fa fa-mobile fa-fw" style="font-size: 1.4em;"></span>
            </a>
        </div>';

        return 0;
    }
}
