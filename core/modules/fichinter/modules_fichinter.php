<?php
/* Copyright (C) 2024 Equipment Manager
 * Fichinter PDF Modules Registration
 */

require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';

/**
 * Parent class for fichinter PDF models
 */
abstract class ModelePDFFicheinter extends CommonDocGenerator
{
    // Variables for backward compatibility with old Dolibarr versions
    public $error = '';
    public $name;
    public $description;

    // Dimension page pour format A4
    public $type = 'pdf';
    public $page_largeur;
    public $page_hauteur;
    public $format;
    public $marge_gauche;
    public $marge_droite;
    public $marge_haute;
    public $marge_basse;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->type = 'pdf';
    }

    /**
     * Write the PDF file
     *
     * @param Fichinter $object Object source
     * @param Translate $outputlangs Lang object for output language
     * @param string $srctemplatepath Full path of source filename for generator using a template file
     * @param int $hidedetails Hide details of lines
     * @param int $hidedesc Hide description
     * @param int $hideref Hide ref
     * @return int 1 if OK, <=0 if KO
     */
    abstract public function write_file($object, $outputlangs, $srctemplatepath = '', $hidedetails = 0, $hidedesc = 0, $hideref = 0);

    /**
     * Return list of active generation modules
     *
     * @param DoliDB $db Database handler
     * @param integer $maxfilenamelength Max length of value to show
     * @return array List of templates
     */
    public static function liste_modeles($db, $maxfilenamelength = 0)
    {
        $type = 'fichinter';
        $liste = array();

        include_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
        $liste = getListOfModels($db, $type, $maxfilenamelength);

        return $liste;
    }
}
