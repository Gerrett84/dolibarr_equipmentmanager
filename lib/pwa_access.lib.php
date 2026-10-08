<?php
/**
 * \file       lib/pwa_access.lib.php
 * \ingroup    equipmentmanager
 * \brief      Who may see/edit which service order (fichinter) in the PWA and its PDF endpoints.
 *
 * Full access (read + write): admin, the author of the order, or an internal contact
 * with the role "Beteiligter am Serviceauftrag" (INTERVENING).
 * Read-only access (history): orders sharing the Objektadresse with an order the user has full access to.
 */

/**
 * SQL condition: user is author of, or internal contact (role INTERVENING) of, the order.
 *
 * @param User   $user  User
 * @param string $alias Alias of the fichinter table in the query
 * @return string SQL condition in parentheses
 */
function eqmInterventionAccessSql($user, $alias = 'f')
{
    $uid = (int) $user->id;
    $sql = "(".$alias.".fk_user_author = ".$uid;
    $sql .= " OR EXISTS (";
    $sql .= "SELECT 1 FROM ".MAIN_DB_PREFIX."element_contact ec_acc";
    $sql .= " JOIN ".MAIN_DB_PREFIX."c_type_contact tc_acc ON tc_acc.rowid = ec_acc.fk_c_type_contact";
    $sql .= " WHERE ec_acc.element_id = ".$alias.".rowid AND ec_acc.fk_socpeople = ".$uid;
    $sql .= " AND tc_acc.element = 'fichinter' AND tc_acc.source = 'internal' AND tc_acc.code = 'INTERVENING'";
    $sql .= "))";
    return $sql;
}

/**
 * Full access (read + write) to an order.
 *
 * @param DoliDB $db            Database handler
 * @param User   $user          User
 * @param int    $interventionId Order id
 * @return bool
 */
function eqmUserCanAccessIntervention($db, $user, $interventionId)
{
    if (!empty($user->admin)) {
        return true;
    }
    $sql = "SELECT f.rowid FROM ".MAIN_DB_PREFIX."fichinter f";
    $sql .= " WHERE f.rowid = ".(int) $interventionId." AND ".eqmInterventionAccessSql($user, 'f');
    $res = $db->query($sql);
    return ($res && $db->num_rows($res) > 0);
}

/**
 * Read access: full access, or the order shares its Objektadresse with an order the user
 * has full access to (this is what the PWA history shows).
 *
 * @param DoliDB $db            Database handler
 * @param User   $user          User
 * @param int    $interventionId Order id
 * @return bool
 */
function eqmUserCanReadIntervention($db, $user, $interventionId)
{
    if (eqmUserCanAccessIntervention($db, $user, $interventionId)) {
        return true;
    }

    $sql = "SELECT 1";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l1";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e1 ON e1.rowid = l1.fk_equipment AND e1.fk_address > 0";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e2 ON e2.fk_address = e1.fk_address";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l2 ON l2.fk_equipment = e2.rowid AND l2.fk_intervention = ".(int) $interventionId;
    $sql .= " JOIN ".MAIN_DB_PREFIX."fichinter fm ON fm.rowid = l1.fk_intervention";
    $sql .= " WHERE ".eqmInterventionAccessSql($user, 'fm');
    $sql .= " LIMIT 1";
    $res = $db->query($sql);
    return ($res && $db->num_rows($res) > 0);
}

/**
 * May the user use the PWA-side PDF/document endpoints at all (permission, not per order)?
 * Technician accounts only need the module right "pwa use", not the Dolibarr backend right.
 *
 * @param User $user User (rights loaded)
 * @return bool
 */
function eqmUserHasPwaPermission($user)
{
    return !empty($user->admin) || $user->hasRight('ficheinter', 'lire') || $user->hasRight('equipmentmanager', 'pwa', 'use');
}

/**
 * May the user view (read) this order through the PWA-side PDF/document endpoints?
 * Admins and users with the regular Dolibarr right "ficheinter read" keep their previous
 * access; technician accounts (module right "pwa use" only) are limited to their own
 * orders and the history of the same Objektadresse.
 *
 * @param DoliDB $db            Database handler
 * @param User   $user          User (rights loaded)
 * @param int    $interventionId Order id
 * @return bool
 */
function eqmUserMayViewIntervention($db, $user, $interventionId)
{
    if (!empty($user->admin) || $user->hasRight('ficheinter', 'lire')) {
        return true;
    }
    return $user->hasRight('equipmentmanager', 'pwa', 'use') && eqmUserCanReadIntervention($db, $user, $interventionId);
}

