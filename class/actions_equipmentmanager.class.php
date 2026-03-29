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

        // Add Abnahmeprotokoll PDF if exists
        $abnahmeprotokoll = $dir.'/Abnahmeprotokoll_'.$objectref.'.pdf';
        if (file_exists($abnahmeprotokoll)) {
            $filesToAttach[] = $abnahmeprotokoll;
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

        // Check if object is a Propal, Commande or Facture
        $className = is_object($object) ? get_class($object) : '';
        if (!in_array($className, array('Propal', 'Commande', 'Facture'))) {
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

        $stringaddress .= "\n".$outputlangs->transnoentities("ObjectAddress").":\n";

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
     * Hook beforePDFCreation — auto-populate Leistungsdatum extrafield on invoices.
     * Reads work_date MIN/MAX from linked fichinter's intervention_detail entries.
     */
    public function beforePDFCreation($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        if (get_class($object) !== 'Facture') {
            return 0;
        }

        // Find linked fichinter(s) — link can be in either direction
        $fichinterIds = array();
        $sql = "SELECT fk_source AS fid FROM ".MAIN_DB_PREFIX."element_element"
             . " WHERE sourcetype = 'fichinter' AND targettype = 'facture' AND fk_target = ".(int)$object->id;
        $sql .= " UNION ";
        $sql .= "SELECT fk_target AS fid FROM ".MAIN_DB_PREFIX."element_element"
             . " WHERE targettype = 'fichinter' AND sourcetype = 'facture' AND fk_source = ".(int)$object->id;

        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $fichinterIds[] = (int)$obj->fid;
            }
        }

        if (empty($fichinterIds)) {
            return 0;
        }

        // Get MIN/MAX work_date from our intervention details
        $ids = implode(',', $fichinterIds);
        $sqlDates = "SELECT MIN(work_date) AS min_date, MAX(work_date) AS max_date"
                  . " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail"
                  . " WHERE fk_intervention IN (".$ids.")"
                  . " AND work_date IS NOT NULL";

        $resDates = $this->db->query($sqlDates);
        if (!$resDates) {
            return 0;
        }
        $obj = $this->db->fetch_object($resDates);
        if (!$obj || empty($obj->min_date)) {
            return 0;
        }

        $langs->load('main');
        $minDate = dol_stringtotime($obj->min_date);
        $maxDate = dol_stringtotime($obj->max_date);

        if ($minDate === $maxDate) {
            $leistungsdatum = dol_print_date($minDate, 'day', 'tzserver', $langs);
        } else {
            $leistungsdatum = dol_print_date($minDate, 'day', 'tzserver', $langs)
                            . ' – '
                            . dol_print_date($maxDate, 'day', 'tzserver', $langs);
        }

        // Save to extrafield (fetch existing first so insertExtraFields merges correctly)
        $object->fetch_optionals();
        $object->array_options['options_leistungsdatum'] = $leistungsdatum;
        $object->insertExtraFields();

        return 0;
    }

    /**
     * Hook printUnderHeaderPDFline — draws Leistungsdatum in the right-side ref block area
     * Uses absolute SetXY positioning to draw in the top-right column (alongside Datum, Fälligkeit)
     *
     * @param array $parameters Parameters (contains 'object' = Facture, 'pdf' = TCPDF instance)
     * @param CommonObject $object PDF module instance (pdf_sponge etc.)
     * @param string $action Action triggered
     * @param HookManager $hookmanager Hook manager
     * @return int 0
     */
    public function printUnderHeaderPDFline($parameters, &$object, &$action, $hookmanager)
    {
        global $langs;

        // $object here is the PDF module instance; the Facture is in $parameters['object']
        $facture = isset($parameters['object']) ? $parameters['object'] : null;
        if (!is_object($facture) || get_class($facture) !== 'Facture') {
            return 0;
        }

        // Read the leistungsdatum stored by beforePDFCreation
        if (empty($facture->array_options)) {
            $facture->fetch_optionals();
        }
        $leistungsdatum = isset($facture->array_options['options_leistungsdatum']) ? $facture->array_options['options_leistungsdatum'] : '';

        if (empty($leistungsdatum)) {
            return 0;
        }

        $pdf         = $parameters['pdf'];
        $outputlangs = $parameters['outputlangs'];
        $langs->load('equipmentmanager@equipmentmanager');

        $default_font_size = pdf_getPDFFontSize($outputlangs);

        // Right-side ref block column (mirrors _pagehead logic)
        $w    = 110;
        $posx = $object->page_largeur - $object->marge_droite - $w;

        // Calculate $posy to match where _pagehead leaves off before pdf_writeLinkedObjects,
        // so Leistungsdatum appears directly above "Serviceauftrag Ref." line.
        // This mirrors the $posy increments in pdf_sponge::_pagehead().
        $posy = $object->marge_haute;
        $posy += 3; // after title MultiCell

        // Optional: ref_customer
        if (!empty($facture->ref_customer)) {
            $posy += 4;
        }
        // Optional: project title / project ref (skip for brevity — rare in invoice context)
        // Optional: replacement/correction invoice type (uncommon)

        $posy += 4; // DateInvoice
        if (getDolGlobalString('INVOICE_POINTOFTAX_DATE')) {
            $posy += 4;
        }
        if ($facture->type != 2) {
            $posy += 3; // DateDue
        }
        // Optional: customer code
        if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_CODE') && !empty($facture->thirdparty->code_client)) {
            $posy += 3;
        }
        // Optional: customer accounting code
        if (!getDolGlobalString('MAIN_PDF_HIDE_CUSTOMER_ACCOUNTING_CODE') && !empty($facture->thirdparty->code_compta_client)) {
            $posy += 3;
        }
        // Optional: sales rep
        if (getDolGlobalString('DOC_SHOW_FIRST_SALES_REP')) {
            $arrayidcontact = $facture->getIdContact('internal', 'SALESREPFOLL');
            if (count($arrayidcontact) > 0) {
                $posy += 4;
            }
        }
        $posy += 1; // final increment before pdf_writeLinkedObjects

        // Save current PDF cursor and draw in the ref block area
        $saved_x = $pdf->GetX();
        $saved_y = $pdf->GetY();

        $pdf->SetFont('', '', $default_font_size - 2);
        $pdf->SetTextColor(0, 0, 60);
        $pdf->SetXY($posx, $posy);
        $pdf->MultiCell($w, 3, $outputlangs->transnoentities('Leistungsdatum').' : '.$leistungsdatum, '', 'R');

        // Restore PDF cursor
        $pdf->SetXY($saved_x, $saved_y);

        return 0;
    }

}
