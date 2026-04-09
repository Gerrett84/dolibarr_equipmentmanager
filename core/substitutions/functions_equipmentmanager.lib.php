<?php
/**
 * Custom substitution variables for Equipment Manager
 *
 * Adds:
 *   __DELIVERY_ADDRESS_NAME__   → name of the OBJ contact on the document (or empty)
 *   __DELIVERY_ADDRESS_SUFFIX__ → " - NAME" if OBJ contact exists, otherwise ""
 *   __INVOICE_DATE__            → formatted invoice date for facture objects
 *   __FICHINTER_DATE__          → formatted release/completion date for fichinter objects (date_valid)
 *   __ORDER_DATE__              → formatted order date for commande objects
 */

function equipmentmanager_completesubstitutionarray(&$substitutionarray, $outputlangs, $object, $parameters)
{
    global $db;

    // --- OBJ contact (Objektadresse) ---
    $name = '';
    if (is_object($object) && !empty($object->id) && !empty($object->element)) {
        $element = $db->escape($object->element);
        $sql  = "SELECT sp.lastname, sp.firstname";
        $sql .= " FROM " . MAIN_DB_PREFIX . "element_contact ec";
        $sql .= " JOIN " . MAIN_DB_PREFIX . "c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
        $sql .= " JOIN " . MAIN_DB_PREFIX . "socpeople sp ON sp.rowid = ec.fk_socpeople";
        $sql .= " WHERE ec.element_id = " . (int)$object->id;
        $sql .= " AND tc.code = 'OBJ'";
        $sql .= " AND tc.element = '" . $element . "'";
        $sql .= " LIMIT 1";

        $res = $db->query($sql);
        if ($res && $obj = $db->fetch_object($res)) {
            $name = trim($obj->lastname . ($obj->firstname ? ' ' . $obj->firstname : ''));
        }
    }
    $substitutionarray['__DELIVERY_ADDRESS_NAME__']   = $name;
    $substitutionarray['__DELIVERY_ADDRESS_SUFFIX__'] = $name ? ' - ' . $name : '';

    // --- Invoice date for facture objects ---
    $invoiceDate = '';
    if (is_object($object) && isset($object->element) && $object->element === 'facture' && !empty($object->date)) {
        $invoiceDate = dol_print_date($object->date, 'day', false, $outputlangs);
    }
    $substitutionarray['__INVOICE_DATE__'] = $invoiceDate;

    // --- Intervention release/completion date for fichinter objects ---
    // Primary: date_valid (release date) → fallback: dateo (start) → datee (end) → datec (creation)
    $fichinterDate = '';
    if (is_object($object) && isset($object->element) && $object->element === 'fichinter') {
        $dateVal = !empty($object->date_valid) ? $object->date_valid
                 : (!empty($object->dateo) ? $object->dateo
                 : (!empty($object->datee) ? $object->datee
                 : (!empty($object->datec) ? $object->datec : null)));
        if ($dateVal) {
            $fichinterDate = dol_print_date($dateVal, 'day', false, $outputlangs);
        }
    }
    $substitutionarray['__FICHINTER_DATE__'] = $fichinterDate;

    // --- Order date for commande objects ---
    $orderDate = '';
    if (is_object($object) && isset($object->element) && $object->element === 'commande' && !empty($object->date)) {
        $orderDate = dol_print_date($object->date, 'day', false, $outputlangs);
    }
    $substitutionarray['__ORDER_DATE__'] = $orderDate;
}
