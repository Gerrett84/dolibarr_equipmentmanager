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

    /**
     * Hook for pdf_build_address - replaces entire address to include Objektadresse
     * Returns 1 to completely replace the address (not just prepend)
     *
     * @param array $parameters Parameters
     * @param CommonObject $object Object (Propal, Commande, etc.)
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager
     * @return int 0 = nothing done, 1 = replace address completely
     */
    public function pdf_build_address($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf;

        // Only process for 'target' mode (customer/recipient address)
        if (empty($parameters['mode']) || $parameters['mode'] != 'target') {
            return 0;
        }

        // Check if object is a Propal or Commande
        $className = is_object($object) ? get_class($object) : '';
        if (!in_array($className, array('Propal', 'Commande'))) {
            return 0;
        }

        // Get linked OBJ contact (Objektadresse)
        $objContactIds = $object->getIdContact('external', 'OBJ');

        // If no Objektadresse linked, let normal address building happen
        if (empty($objContactIds) || !is_array($objContactIds) || count($objContactIds) == 0) {
            return 0;
        }

        // Load the OBJ contact
        require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
        $contactObj = new Contact($this->db);

        if ($contactObj->fetch($objContactIds[0]) <= 0) {
            return 0;
        }

        // Now we need to build the complete address ourselves
        // Get parameters from hook
        $outputlangs = $parameters['outputlangs'];
        $targetcompany = $parameters['targetcompany'];
        $targetcontact = $parameters['targetcontact'];
        $usecontact = $parameters['usecontact'];
        $sourcecompany = $parameters['sourcecompany'];

        $stringaddress = '';

        // Build target address (same logic as core pdf_build_address for mode='target')
        if ($usecontact && is_object($targetcontact)) {
            // Contact name
            $stringaddress .= $outputlangs->convToOutputCharset($targetcontact->getFullName($outputlangs, 1));

            // Contact or company address
            if (!empty($targetcontact->address)) {
                $stringaddress .= ($stringaddress ? "\n" : '').$outputlangs->convToOutputCharset(dol_format_address($targetcontact))."\n";
            } elseif (is_object($targetcompany)) {
                $companytouseforaddress = $targetcompany;
                if ($targetcontact->socid > 0 && $targetcontact->socid != $targetcompany->id) {
                    $targetcontact->fetch_thirdparty();
                    $companytouseforaddress = $targetcontact->thirdparty;
                }
                $stringaddress .= ($stringaddress ? "\n" : '').$outputlangs->convToOutputCharset(dol_format_address($companytouseforaddress))."\n";
            }

            // Country
            if (!empty($targetcontact->country_code) && $targetcontact->country_code != $sourcecompany->country_code) {
                $stringaddress .= ($stringaddress ? "\n" : '').$outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv("Country".$targetcontact->country_code));
            } elseif (empty($targetcontact->country_code) && !empty($targetcompany->country_code) && ($targetcompany->country_code != $sourcecompany->country_code)) {
                $stringaddress .= ($stringaddress ? "\n" : '').$outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv("Country".$targetcompany->country_code));
            }
        } else {
            // No contact, use company address
            if (is_object($targetcompany)) {
                $stringaddress .= $outputlangs->convToOutputCharset(dol_format_address($targetcompany))."\n";
                // Country
                if (!empty($targetcompany->country_code) && $targetcompany->country_code != $sourcecompany->country_code) {
                    $stringaddress .= ($stringaddress ? "\n" : '').$outputlangs->convToOutputCharset($outputlangs->transnoentitiesnoconv("Country".$targetcompany->country_code));
                }
            }
        }

        // Now append Objektadresse
        $langs->load("equipmentmanager@equipmentmanager");

        $stringaddress .= "\n\n".$outputlangs->transnoentities("ObjectAddress").":\n";

        // Name
        if ($contactObj->lastname || $contactObj->firstname) {
            $stringaddress .= trim($contactObj->firstname.' '.$contactObj->lastname)."\n";
        }

        // Address
        if ($contactObj->address) {
            $stringaddress .= $contactObj->address."\n";
        }

        // ZIP + City
        $cityLine = '';
        if ($contactObj->zip) {
            $cityLine .= $contactObj->zip;
        }
        if ($contactObj->town) {
            $cityLine .= ($cityLine ? ' ' : '').$contactObj->town;
        }
        if ($cityLine) {
            $stringaddress .= $cityLine;
        }

        // Set the complete address as hook output
        $this->resprints = $stringaddress;

        // Return 1 to completely replace the address (skip normal building)
        return 1;
    }

    /**
     * Hook for beforePDFCreation - adds equipment list to note_public for Propal/Commande PDFs
     *
     * @param array $parameters Parameters
     * @param CommonObject $object Object (Propal, Commande)
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager
     * @return int 0 = nothing done
     */
    public function beforePDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $langs, $conf;

        // Check if object is Propal or Commande
        $className = is_object($object) ? get_class($object) : '';
        if (!in_array($className, array('Propal', 'Commande'))) {
            return 0;
        }

        // Determine document type
        $docType = ($className == 'Propal') ? 'propal' : 'commande';

        // Load equipment links
        dol_include_once('/equipmentmanager/class/documentequipmentlink.class.php');
        dol_include_once('/equipmentmanager/class/equipment.class.php');

        $linkHelper = new DocumentEquipmentLink($this->db, $docType);
        $linked_equipment = $linkHelper->fetchAllByDocument($object->id);

        if (empty($linked_equipment)) {
            return 0;
        }

        // Load translations
        $langs->load("equipmentmanager@equipmentmanager");

        // Get equipment type labels
        $type_labels = Equipment::getEquipmentTypesTranslated($this->db, $langs);

        // Build equipment list text
        $equipmentText = "\n\n" . $langs->trans('Equipment') . ":\n";
        $equipmentText .= str_repeat('-', 40) . "\n";

        foreach ($linked_equipment as $link) {
            $line = '• ' . $link->equipment_number;
            if (!empty($link->equipment_label)) {
                $line .= ' (' . $link->equipment_label . ')';
            }

            // Add type
            $typeName = isset($type_labels[$link->equipment_type]) ? $type_labels[$link->equipment_type] : $link->equipment_type;
            $line .= ' - ' . $typeName;

            // Add location if available
            if (!empty($link->location_note)) {
                $line .= "\n  " . $langs->trans('LocationNote') . ': ' . $link->location_note;
            }

            $equipmentText .= $line . "\n";
        }

        // Store original note for restoration after PDF generation
        $object->equipmentmanager_original_note_public = $object->note_public;

        // Append equipment list to public note
        $object->note_public = ($object->note_public ? $object->note_public : '') . $equipmentText;

        return 0;
    }

    /**
     * Hook for afterPDFCreation - restores original note_public after PDF generation
     *
     * @param array $parameters Parameters
     * @param CommonObject $object Object (Propal, Commande)
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager
     * @return int 0 = nothing done
     */
    public function afterPDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        // Restore original note if we modified it
        if (isset($object->equipmentmanager_original_note_public)) {
            $object->note_public = $object->equipmentmanager_original_note_public;
            unset($object->equipmentmanager_original_note_public);
        }

        return 0;
    }
}
