<?php
/**
 * \file       lib/pwa_access.lib.php
 * \ingroup    equipmentmanager
 * \brief      Who may see/edit which service order (fichinter) in the PWA and its PDF endpoints.
 *
 * Full access (read + write): admin, or an internal contact with the role
 * "Beteiligter am Serviceauftrag" (INTERVENING). Being the author of an order does NOT
 * grant PWA access - whoever creates orders in the backend only sees in the PWA those
 * he is assigned to. The PWA order list is limited to assigned orders for everybody,
 * admins included.
 * Read-only access (history): orders sharing the Objektadresse with an order the user has full access to.
 */

/**
 * SQL condition: user is internal contact with the role INTERVENING ("Beteiligter am
 * Serviceauftrag") of the order.
 *
 * @param User   $user  User
 * @param string $alias Alias of the fichinter table in the query
 * @return string SQL condition in parentheses
 */
function eqmInterventionAccessSql($user, $alias = 'f')
{
    $uid = (int) $user->id;
    $sql = "(EXISTS (";
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

/**
 * Resolve a PWA token to its (active) user. Single implementation for the API and the
 * PDF/document endpoints.
 *
 * A token is rejected (and removed) when it is expired, older than the absolute maximum
 * age (EQUIPMENTMANAGER_PWA_TOKEN_MAX_DAYS, default 365, regardless of rolling renewal),
 * or its user is no longer active (disabled, or outside the validity date range).
 *
 * @param DoliDB $db    Database handler
 * @param string $token Plain token as sent by the client
 * @param bool   $touch Renew the rolling 90-day validity and update last use
 * @return User|null User with rights loaded, or null
 */
function eqmResolvePwaTokenUser($db, $token, $touch = false)
{
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }

    $hash = hash('sha256', $token);
    $sql = "SELECT fk_user, date_creation FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token";
    $sql .= " WHERE token = '".$db->escape($hash)."' AND valid_until > '".$db->idate(dol_now())."'";
    $res = $db->query($sql);
    if (!$res || $db->num_rows($res) == 0) {
        return null;
    }
    $row = $db->fetch_object($res);

    $maxDays = max(1, getDolGlobalInt('EQUIPMENTMANAGER_PWA_TOKEN_MAX_DAYS', 365));
    $created = $db->jdate($row->date_creation);
    $reject = ($created && $created < dol_now() - $maxDays * 86400);

    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $tokenUser = new User($db);
    if (!$reject) {
        $reject = ($tokenUser->fetch((int) $row->fk_user) <= 0 || (int) $tokenUser->statut !== 1 || $tokenUser->isNotIntoValidityDateRange());
    }
    if ($reject) {
        $db->query("DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token WHERE token = '".$db->escape($hash)."'");
        return null;
    }

    if ($touch) {
        $db->query("UPDATE ".MAIN_DB_PREFIX."equipmentmanager_pwa_token SET last_use = '".$db->idate(dol_now())."', valid_until = '".$db->idate(dol_now() + 90 * 86400)."' WHERE token = '".$db->escape($hash)."'");
    }

    $tokenUser->getrights();
    return $tokenUser;
}

/**
 * Secret used to sign short-lived view tickets.
 *
 * @return string
 */
function eqmViewTicketSecret()
{
    global $conf;
    $base = !empty($conf->file->instance_unique_id) ? $conf->file->instance_unique_id : DOL_DOCUMENT_ROOT;
    return hash('sha256', 'eqm-view-ticket|'.$base);
}

/**
 * Create a short-lived ticket that lets the browser open a PDF/document URL
 * (iframe, new tab) without putting the long-lived PWA token into the URL and
 * from there into access logs and the browser history.
 *
 * @param int $userId User id
 * @param int $ttl    Lifetime in seconds
 * @return string URL-safe ticket
 */
function eqmCreateViewTicket($userId, $ttl = 300)
{
    $payload = ((int) $userId).'.'.(time() + (int) $ttl);
    return $payload.'.'.hash_hmac('sha256', $payload, eqmViewTicketSecret());
}

/**
 * Resolve a view ticket to an active user.
 *
 * @param DoliDB $db     Database handler
 * @param string $ticket Ticket from eqmCreateViewTicket()
 * @return User|null
 */
function eqmResolveViewTicket($db, $ticket)
{
    if (!is_string($ticket) || !preg_match('/^(\d+)\.(\d+)\.([a-f0-9]{64})$/', $ticket, $m)) {
        return null;
    }
    $payload = $m[1].'.'.$m[2];
    if (!hash_equals(hash_hmac('sha256', $payload, eqmViewTicketSecret()), $m[3]) || (int) $m[2] < time()) {
        return null;
    }

    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $ticketUser = new User($db);
    if ($ticketUser->fetch((int) $m[1]) <= 0 || (int) $ticketUser->statut !== 1 || $ticketUser->isNotIntoValidityDateRange()) {
        return null;
    }
    $ticketUser->getrights();
    return $ticketUser;
}

/**
 * Authenticate a PDF/document request: the X-PWA-Token header, or a short-lived
 * view ticket in the "t" parameter. The long-lived token is deliberately not
 * accepted in the URL.
 *
 * @param DoliDB $db Database handler
 * @return User|null
 */
function eqmResolveViewRequestUser($db)
{
    $header = isset($_SERVER['HTTP_X_PWA_TOKEN']) ? (string) $_SERVER['HTTP_X_PWA_TOKEN'] : '';
    if ($header !== '') {
        return eqmResolvePwaTokenUser($db, $header, false);
    }
    $ticket = isset($_GET['t']) ? (string) $_GET['t'] : '';
    return $ticket !== '' ? eqmResolveViewTicket($db, $ticket) : null;
}

