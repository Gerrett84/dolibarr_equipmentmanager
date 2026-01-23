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
     * Overloading the getFormMail function
     * Automatically attach signed PDF and checklist PDF when sending email from intervention
     *
     * @param array $parameters Parameters
     * @param FormMail $object FormMail object
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return int <0 if error, 0 if nothing done, >0 if OK
     */
    public function getFormMail($parameters, &$object, &$action, $hookmanager)
    {
        global $conf;

        // Only process for fichinter (intervention) emails
        if (empty($object->param['id'])) {
            return 0;
        }

        // Check if we're in fichinter context
        $returnurl = isset($object->param['returnurl']) ? $object->param['returnurl'] : '';
        if (strpos($returnurl, 'fichinter') === false) {
            return 0;
        }

        $fichinterId = (int)$object->param['id'];
        $ref = '';

        // Get intervention ref
        $sql = "SELECT ref, signed_status FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".$fichinterId;
        $resql = $this->db->query($sql);
        if ($resql && $this->db->num_rows($resql) > 0) {
            $row = $this->db->fetch_object($resql);
            $ref = $row->ref;
            $signedStatus = $row->signed_status;
        } else {
            return 0;
        }

        // Only auto-attach if intervention is signed (signed_status = 3)
        if ($signedStatus != 3) {
            return 0;
        }

        $objectref = dol_sanitizeFileName($ref);
        $dir = $conf->ficheinter->dir_output.'/'.$objectref;

        if (!is_dir($dir)) {
            return 0;
        }

        $filesToAttach = array();

        // Add signed PDF if exists
        $signedPdf = $dir.'/'.$objectref.'_signed.pdf';
        if (file_exists($signedPdf)) {
            $filesToAttach[] = $signedPdf;
        }

        // Add combined checklists PDF if exists
        $checklistsPdf = $dir.'/Checklisten_'.$objectref.'.pdf';
        if (file_exists($checklistsPdf)) {
            $filesToAttach[] = $checklistsPdf;
        }

        // Merge with existing fileinit (keep intervention PDF if already there)
        if (!empty($object->param['fileinit']) && is_array($object->param['fileinit'])) {
            foreach ($object->param['fileinit'] as $existingFile) {
                if (!empty($existingFile) && !in_array($existingFile, $filesToAttach)) {
                    // Put the original intervention PDF first
                    array_unshift($filesToAttach, $existingFile);
                }
            }
        }

        // Update fileinit with our files
        if (!empty($filesToAttach)) {
            $object->param['fileinit'] = $filesToAttach;
        }

        return 0;
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

        // Only show for logged in users with intervention access
        if (empty($user->id)) {
            return 0;
        }

        // Check module is enabled
        if (empty($conf->ficheinter->enabled) && empty($conf->intervention->enabled)) {
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
