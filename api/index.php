<?php
/**
 * Equipment Manager PWA API
 * REST-like endpoints for offline sync
 */

// Disable CSRF check and login redirect for API endpoints
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');
// NOLOGIN prevents main.inc.php from rendering the login page when no session exists.
// Without this, PWA token authentication never runs because Dolibarr redirects first.
if (!defined('NOLOGIN')) define('NOLOGIN', '1');

// Prevent direct browser access without proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token, X-PWA-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Dolibarr environment (NOLOGIN prevents login redirect)
$res = 0;
if (!$res && file_exists("../../../main.inc.php")) {
    $res = include "../../../main.inc.php";
}
if (!$res && file_exists("../../../../main.inc.php")) {
    $res = include "../../../../main.inc.php";
}
if (!$res) {
    http_response_code(500);
    echo json_encode(['error' => 'Dolibarr environment not found']);
    exit;
}

require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/signature.lib.php';
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/interventiondetail.class.php');
dol_include_once('/equipmentmanager/class/interventionmaterial.class.php');
dol_include_once('/equipmentmanager/class/checklisttemplate.class.php');
dol_include_once('/equipmentmanager/class/checklistresult.class.php');
dol_include_once('/equipmentmanager/class/defectmaterial.class.php');

// Check authentication - support both session and PWA token.
// With NOLOGIN, main.inc.php does NOT load the user from session automatically,
// so we must do it ourselves here.
$authenticated = false;

// First: try to load user from Dolibarr session (browser-based access)
if (!$authenticated && !empty($_SESSION['dol_login'])) {
    require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
    $sessionUser = new User($db);
    $entity = isset($_SESSION['dol_entity']) ? (int)$_SESSION['dol_entity'] : 1;
    $result = $sessionUser->fetch(0, $_SESSION['dol_login'], '', 1, ($entity > 0 ? $entity : -1));
    if ($result > 0 && $sessionUser->id > 0) {
        $user = $sessionUser;
        $authenticated = true;
    }
}

// Second: try PWA token (standalone PWA without active browser session)
if (!$authenticated) {
    $pwaToken = $_SERVER['HTTP_X_PWA_TOKEN'] ?? '';
    if ($pwaToken) {
        $authenticated = validatePwaToken($pwaToken, $db, $user);
    }
}

if (!$authenticated) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

// Load language files for translations
$langs->loadLangs(array("main", "equipmentmanager@equipmentmanager"));

// Parse request
$method = $_SERVER['REQUEST_METHOD'];

// Support both PATH_INFO and query parameter routing
if (!empty($_GET['route'])) {
    // Query parameter routing: ?route=interventions
    $endpoint = $_GET['route'];
} elseif (!empty($_SERVER['PATH_INFO'])) {
    // PATH_INFO routing: /api/index.php/interventions
    $endpoint = trim($_SERVER['PATH_INFO'], '/');
} else {
    // URI-based routing: /api/interventions
    $uri = $_SERVER['REQUEST_URI'];
    $path = parse_url($uri, PHP_URL_PATH);

    // Remove base path to get endpoint
    $basePath = '/custom/equipmentmanager/api';
    $pos = strpos($path, $basePath);
    if ($pos !== false) {
        $endpoint = substr($path, $pos + strlen($basePath));
    } else {
        $endpoint = $path;
    }
    // Also remove index.php if present
    $endpoint = str_replace('/index.php', '', $endpoint);
    $endpoint = trim($endpoint, '/');
}

$parts = explode('/', $endpoint);

// Get JSON body for POST/PUT
$input = json_decode(file_get_contents('php://input'), true);

try {
    // Route requests
    switch ($parts[0] ?? 'index') {
        case 'index':
        case '':
            echo json_encode([
                'status' => 'ok',
                'version' => '1.0',
                'user' => $user->login,
                'endpoints' => [
                    'GET /interventions' => 'List user interventions',
                    'GET /intervention/{id}' => 'Get intervention details',
                    'GET /intervention/{id}/equipment' => 'Get equipment for intervention',
                    'GET /detail/{intervention_id}/{equipment_id}' => 'Get service report',
                    'POST /detail/{intervention_id}/{equipment_id}' => 'Save service report',
                    'POST /sync' => 'Batch sync offline changes',
                    'POST /signature/{intervention_id}' => 'Upload signature'
                ]
            ]);
            break;

        case 'ping':
            echo json_encode(['status' => 'ok']);
            break;

        case 'interventions':
            handleInterventions($method, $parts, $input);
            break;

        case 'intervention':
            handleIntervention($method, $parts, $input);
            break;

        case 'detail':
            handleDetail($method, $parts, $input);
            break;

        case 'sync':
            handleSync($method, $input);
            break;

        case 'signature':
            handleSignature($method, $parts, $input);
            break;

        case 'material':
            handleMaterial($method, $parts, $input);
            break;

        case 'products':
            handleProducts($method, $parts, $input);
            break;

        case 'available-equipment':
            handleAvailableEquipment($method, $parts, $input);
            break;

        case 'link-equipment':
            handleLinkEquipment($method, $parts, $input);
            break;

        case 'pwa-token':
            handlePwaToken($method, $input);
            break;

        case 'checklist':
            handleChecklist($method, $parts, $input);
            break;

        case 'defect-photo':
            handleDefectPhoto($method, $parts, $input);
            break;

        case 'entry-photo':
            handleEntryPhoto($method, $parts, $input);
            break;

        case 'defect-material':
            handleDefectMaterial($method, $parts, $input);
            break;

        case 'equipment':
            handleEquipment($method, $parts, $input);
            break;

        case 'maintenance-overview':
            handleMaintenanceOverview($method, $parts, $input);
            break;

        default:
            http_response_code(404);
            echo json_encode(['error' => 'Endpoint not found: ' . $endpoint]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * GET /interventions - List interventions for current user
 */
function handleInterventions($method, $parts, $input) {
    global $db, $user;

    // Accept both GET and POST for read operations (Dolibarr may convert GET to POST)
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Get interventions
    $sql = "SELECT f.rowid, f.ref, f.datec, f.dateo, f.datee, f.duree, f.fk_statut as status,";
    $sql .= " f.description, f.note_public, f.note_private, f.entity as fichinter_entity,";
    $sql .= " f.signed_status,";
    $sql .= " s.rowid as socid, s.nom as customer_name, s.address, s.zip, s.town,";
    $sql .= " (SELECT CASE WHEN EXISTS (SELECT 1 FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il WHERE il.fk_intervention = f.rowid AND il.link_type = 'maintenance') THEN 'maintenance' ELSE 'service' END) as primary_type,";
    $sql .= " (SELECT CASE";
    $sql .= "   WHEN MIN(e.next_maintenance_date) IS NULL THEN 'none'";
    $sql .= "   WHEN MIN(e.next_maintenance_date) < CURDATE() THEN 'overdue'";
    $sql .= "   WHEN MIN(e.next_maintenance_date) <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'soon'";
    $sql .= "   ELSE 'ok' END";
    $sql .= "  FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il2";
    $sql .= "  JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = il2.fk_equipment";
    $sql .= "  WHERE il2.fk_intervention = f.rowid AND il2.link_type = 'maintenance') as maintenance_status";
    $sql .= " FROM ".MAIN_DB_PREFIX."fichinter f";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = f.fk_soc";
    $sql .= " WHERE 1=1"; // Remove entity filter temporarily

    // Filter by status (draft=0, validated=1, closed=3)
    if (isset($_GET['status'])) {
        if ($_GET['status'] === 'all') {
            // Show all statuses
        } else {
            $sql .= " AND f.fk_statut = ".(int)$_GET['status'];
        }
    } else {
        // By default, show all non-closed interventions
        $sql .= " AND f.fk_statut IN (0, 1)";
    }

    // Filter by date
    if (isset($_GET['from'])) {
        $sql .= " AND f.dateo >= '".$db->escape($_GET['from'])."'";
    }

    $sql .= " ORDER BY f.dateo DESC";
    $sql .= " LIMIT 100";

    $resql = $db->query($sql);

    if (!$resql) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $db->lasterror()]);
        return;
    }

    $interventions = [];
    while ($obj = $db->fetch_object($resql)) {
        // Get object addresses for this intervention
        $objectAddresses = getInterventionObjectAddresses($obj->rowid);

        $interventions[] = [
            'id' => (int)$obj->rowid,
            'ref' => $obj->ref,
            'date_creation' => $obj->datec,
            'date_start' => $obj->dateo,
            'date_end' => $obj->datee,
            'duration' => (int)$obj->duree,
            'status' => (int)$obj->status,
            'signed_status' => (int)$obj->signed_status,
            'description' => $obj->description,
            'note_public' => $obj->note_public,
            'primary_type' => $obj->primary_type,
            'maintenance_status' => $obj->maintenance_status,
            'customer' => [
                'id' => (int)$obj->socid,
                'name' => $obj->customer_name,
                'address' => $obj->address,
                'zip' => $obj->zip,
                'town' => $obj->town
            ],
            'object_addresses' => $objectAddresses
        ];
    }

    echo json_encode([
        'status' => 'ok',
        'count' => count($interventions),
        'interventions' => $interventions
    ]);
}

/**
 * GET /intervention/{id} - Get single intervention with equipment
 * GET /intervention/{id}/equipment - Get equipment list
 * GET /intervention/{id}/documents - Get documents list
 * POST /intervention/{id}/documents - Upload document
 * DELETE /intervention/{id}/documents/{filename} - Delete document
 */
function handleIntervention($method, $parts, $input) {
    global $db, $user, $langs, $conf;

    // Accept GET, POST, and DELETE (DELETE for document removal)
    if (!in_array($method, ['GET', 'POST', 'DELETE'])) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed', 'method' => $method]);
        return;
    }

    $id = (int)($parts[1] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    // Get intervention
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }

    // GET /intervention/{id}/email-info — suggested recipient + subject for send-email modal
    if (isset($parts[2]) && $parts[2] === 'email-info' && $method === 'GET') {
        $fichinter->fetch_thirdparty();

        // Try CUSTOMER contact with email first
        $recipientEmail = '';
        $recipientName  = '';
        $sqlCust  = "SELECT sp.email, sp.lastname, sp.firstname";
        $sqlCust .= " FROM " . MAIN_DB_PREFIX . "element_contact ec";
        $sqlCust .= " JOIN " . MAIN_DB_PREFIX . "c_type_contact tc ON tc.rowid = ec.fk_c_type_contact";
        $sqlCust .= " JOIN " . MAIN_DB_PREFIX . "socpeople sp ON sp.rowid = ec.fk_socpeople";
        $sqlCust .= " WHERE ec.element_id = " . (int)$id;
        $sqlCust .= " AND tc.code = 'CUSTOMER' AND tc.element = 'fichinter'";
        $sqlCust .= " AND sp.email != '' LIMIT 1";
        $resCust = $db->query($sqlCust);
        if ($resCust && $objCust = $db->fetch_object($resCust)) {
            $recipientEmail = $objCust->email;
            $recipientName  = trim($objCust->lastname . ' ' . $objCust->firstname);
        }
        // Fallback to thirdparty email
        if (!$recipientEmail && is_object($fichinter->thirdparty)) {
            $recipientEmail = $fichinter->thirdparty->email ?: '';
            $recipientName  = $fichinter->thirdparty->name ?: '';
        }

        // Load template + apply substitutions for subject preview
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
        $formmail = new FormMail($db);
        $template = $formmail->getEMailTemplate($db, 'fichinter_send', $user, $langs, 0, 1, '', -1);
        $subject = '';
        $body    = '';
        if (is_object($template) && $template->id > 0) {
            $subst = getCommonSubstitutionArray($langs, 0, null, $fichinter);
            complete_substitutions_array($subst, $langs, $fichinter);
            $subject = make_substitutions($template->topic, $subst);
            $rawBody = make_substitutions($template->content, $subst);
            // Strip HTML tags for plain-text display in the textarea
            // First: replace literal \n strings (Dolibarr stores them in templates) with real newlines
            $plainBody = str_replace('\\n', "\n", $rawBody);
            // Convert block-level HTML to newlines, then strip remaining tags
            $plainBody = str_replace(['<br>', '<br/>', '<br />', '</div>', '</p>'], "\n", $plainBody);
            // Remove &nbsp; before decode so it becomes empty instead of \u00A0
            $plainBody = str_replace('&nbsp;', '', $plainBody);
            $plainBody = html_entity_decode(strip_tags($plainBody), ENT_QUOTES, 'UTF-8');
            // Trim each line (also strip non-breaking space \u00A0), collapse consecutive blank lines
            $lines = array_map(function($l) { return trim($l, " \t\n\r\0\x0B\xc2\xa0"); }, explode("\n", $plainBody));
            $collapsed = [];
            $prevBlank = false;
            foreach ($lines as $line) {
                $isBlank = ($line === '');
                if ($isBlank && $prevBlank) continue;
                $collapsed[] = $line;
                $prevBlank = $isBlank;
            }
            $body = trim(implode("\n", $collapsed));
        }

        echo json_encode([
            'status'         => 'ok',
            'email'          => $recipientEmail,
            'recipient_name' => $recipientName,
            'subject'        => $subject,
            'body'           => $body
        ]);
        return;
    }

    // POST /intervention/{id}/send-email — send service report email with PDF
    if (isset($parts[2]) && $parts[2] === 'send-email' && $method === 'POST') {
        $recipientEmail = trim($input['email'] ?? '');
        $customSubject  = trim($input['subject'] ?? '');
        $ccEmail        = trim($input['cc'] ?? '');
        $customBody     = trim($input['body'] ?? '');

        if (!$recipientEmail) {
            http_response_code(400);
            echo json_encode(['error' => 'Email address required']);
            return;
        }
        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid email address']);
            return;
        }
        if ($ccEmail && !filter_var($ccEmail, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid CC email address']);
            return;
        }

        $fichinter->fetch_thirdparty();

        // Build subject + body from template
        require_once DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php';
        $formmail = new FormMail($db);
        $template = $formmail->getEMailTemplate($db, 'fichinter_send', $user, $langs, 0, 1, '', -1);

        $subst = getCommonSubstitutionArray($langs, 0, null, $fichinter);
        complete_substitutions_array($subst, $langs, $fichinter);

        $subject = $customSubject ?: (is_object($template) && $template->id > 0
            ? make_substitutions($template->topic, $subst) : $fichinter->ref);
        if ($customBody) {
            // Client sent an edited plain-text body — convert newlines to <br>
            $message = nl2br(dol_htmlentitiesbr($customBody));
        } else {
            $message = is_object($template) && $template->id > 0
                ? make_substitutions($template->content, $subst) : '';
        }

        // Find PDF: prefer signed, fallback to main
        $docDir    = getFichinterDocDir($fichinter);
        $signedPdf = $docDir . '/' . dol_sanitizeFileName($fichinter->ref) . '/' . dol_sanitizeFileName($fichinter->ref) . '_signed.pdf';
        $mainPdf   = $docDir . '/' . dol_sanitizeFileName($fichinter->ref) . '/' . dol_sanitizeFileName($fichinter->ref) . '.pdf';

        $attachPaths = [];
        $attachMimes = [];
        $attachNames = [];
        if (file_exists($signedPdf)) {
            $attachPaths[] = $signedPdf;
            $attachMimes[] = 'application/pdf';
            $attachNames[] = dol_sanitizeFileName($fichinter->ref) . '_signed.pdf';
        } elseif (file_exists($mainPdf)) {
            $attachPaths[] = $mainPdf;
            $attachMimes[] = 'application/pdf';
            $attachNames[] = dol_sanitizeFileName($fichinter->ref) . '.pdf';
        }

        // From address
        $fromEmail = getDolGlobalString('MAIN_MAIL_EMAIL_FROM') ?: ($user->email ?: 'noreply@localhost');
        $fromName  = getDolGlobalString('MAIN_MAIL_EMAIL_FROM_NAME') ?: $user->getFullName($langs);
        $from      = $fromName ? '"' . $fromName . '" <' . $fromEmail . '>' : $fromEmail;

        require_once DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php';
        $mailfile = new CMailFile(
            $subject,
            $recipientEmail,
            $from,
            $message,
            $attachPaths,
            $attachMimes,
            $attachNames,
            $ccEmail, '', 0, 1
        );

        $result = $mailfile->sendfile();

        if ($result) {
            echo json_encode([
                'status'    => 'ok',
                'message'   => 'E-Mail gesendet',
                'recipient' => $recipientEmail,
                'attached'  => !empty($attachPaths)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'E-Mail konnte nicht gesendet werden', 'details' => $mailfile->error]);
        }
        return;
    }

    // If requesting equipment list
    if (isset($parts[2]) && $parts[2] === 'equipment') {
        $equipment = getInterventionEquipment($id);
        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'equipment' => $equipment
        ]);
        return;
    }

    // Release intervention for signature (NOT closing it)
    if (isset($parts[2]) && $parts[2] === 'release') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Use Dolibarr's setValid() method to properly validate the intervention
        // This generates the proper reference (FI...) and renames directories
        $fichinter->fetch_thirdparty();
        $result = $fichinter->setValid($user);

        if ($result > 0) {
            // Also set signed_status = 1 (released for signature)
            $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 1 WHERE rowid = ".(int)$id;
            $db->query($sql);

            // Refetch to get new reference
            $fichinter->fetch($id);

            // Generate PDF with new reference
            $pdfGenerated = generateInterventionPDF($fichinter, $user);

            // Regenerate all completed checklist PDFs with new intervention reference
            $checklistsRegenerated = regenerateChecklistPDFs($fichinter, $user, $langs);

            echo json_encode([
                'status' => 'ok',
                'message' => 'Intervention released for signature',
                'ref' => $fichinter->ref,
                'signed_status' => 1,
                'intervention_status' => (int)$fichinter->statut,
                'pdf_generated' => $pdfGenerated,
                'checklists_regenerated' => $checklistsRegenerated
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to validate intervention',
                'details' => $fichinter->error
            ]);
        }
        return;
    }

    // Unreleased intervention (allow editing again)
    if (isset($parts[2]) && $parts[2] === 'unreleased') {
        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
            return;
        }

        // Set signed_status = 0 AND fk_statut = 0 (draft) to allow editing again
        $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 0, fk_statut = 0 WHERE rowid = ".(int)$id;
        $resql = $db->query($sql);
        $affectedRows = $db->affected_rows($resql);

        // Verify the update by reading directly from database
        $sqlVerify = "SELECT signed_status FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$id;
        $resVerify = $db->query($sqlVerify);
        $actualSignedStatus = -1;
        if ($resVerify && $objVerify = $db->fetch_object($resVerify)) {
            $actualSignedStatus = (int)$objVerify->signed_status;
        }

        // Success if query worked AND (rows affected OR value is now correct)
        if ($resql && ($affectedRows > 0 || $actualSignedStatus == 0)) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Intervention reopened for editing',
                'signed_status' => $actualSignedStatus,
                'intervention_status' => (int)$fichinter->statut,
                'affected_rows' => $affectedRows
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to unreleased intervention',
                'details' => $db->lasterror(),
                'sql' => $sql,
                'affected_rows' => $affectedRows,
                'actual_signed_status' => $actualSignedStatus
            ]);
        }
        return;
    }

    // Get Online Sign URL for intervention
    if (isset($parts[2]) && $parts[2] === 'onlinesign-url') {
        // Use Dolibarr's official getOnlineSignatureUrl function from signature.lib.php
        // This ensures we generate the exact same URL and secure key as Dolibarr's UI
        $onlineSignUrl = getOnlineSignatureUrl(0, 'fichinter', $fichinter->ref, 1, $fichinter);

        // Get signed_status from database
        $sqlStatus = "SELECT signed_status FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$id;
        $resStatus = $db->query($sqlStatus);
        $signedStatus = 0;
        if ($resStatus && $objStatus = $db->fetch_object($resStatus)) {
            $signedStatus = (int)$objStatus->signed_status;
        }

        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'intervention_ref' => $fichinter->ref,
            'online_sign_url' => $onlineSignUrl,
            'signed_status' => $signedStatus
        ]);
        return;
    }

    // Document operations - check method-specific routes first

    // Delete a specific document (must be before GET check)
    if (isset($parts[2]) && $parts[2] === 'documents' && isset($parts[3]) && $method === 'DELETE') {
        $filename = urldecode($parts[3]);

        // Security: Only allow deleting files within the intervention's document directory
        $docDir = getFichinterDocDir() . '/' . dol_sanitizeFileName($fichinter->ref);

        // Check if it's a signature file (in signatures subdirectory)
        if (strpos($filename, 'signatures/') === 0) {
            // Allow signatures subdirectory but sanitize filename part
            $sigFilename = basename(substr($filename, 11)); // Remove 'signatures/' prefix
            $filepath = $docDir . '/signatures/' . $sigFilename;
        } else {
            $filepath = $docDir . '/' . basename($filename); // basename for security
        }

        // Check if file exists
        if (!file_exists($filepath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        // Delete the file
        if (@unlink($filepath)) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Document deleted',
                'filename' => $filename
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete file']);
        }
        return;
    }

    // Upload a document/photo
    if (isset($parts[2]) && $parts[2] === 'documents' && $method === 'POST') {
        $docDir = getFichinterDocDir() . '/' . dol_sanitizeFileName($fichinter->ref);

        // Ensure directory exists
        if (!is_dir($docDir)) {
            dol_mkdir($docDir);
        }

        // Check if POST data was truncated (happens when post_max_size is exceeded)
        if (empty($_FILES) && empty($_POST) && isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 0) {
            http_response_code(413);
            echo json_encode([
                'error' => 'Datei zu groß für Server (post_max_size: ' . ini_get('post_max_size') . ')',
                'php_limits' => [
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ]
            ]);
            return;
        }

        // Check for file upload
        if (!empty($_FILES['file'])) {
            $uploadedFile = $_FILES['file'];

            // Validate file
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
            $maxSize = 2 * 1024 * 1024 * 1024; // 2GB

            if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
                // Provide specific error messages for PHP upload errors
                $uploadErrors = [
                    UPLOAD_ERR_INI_SIZE => 'Datei zu groß (PHP upload_max_filesize: ' . ini_get('upload_max_filesize') . ')',
                    UPLOAD_ERR_FORM_SIZE => 'Datei zu groß (Formular-Limit)',
                    UPLOAD_ERR_PARTIAL => 'Datei nur teilweise hochgeladen',
                    UPLOAD_ERR_NO_FILE => 'Keine Datei empfangen',
                    UPLOAD_ERR_NO_TMP_DIR => 'Temporärer Ordner fehlt',
                    UPLOAD_ERR_CANT_WRITE => 'Fehler beim Schreiben auf Festplatte',
                    UPLOAD_ERR_EXTENSION => 'Upload durch PHP-Erweiterung gestoppt'
                ];
                $errorMsg = isset($uploadErrors[$uploadedFile['error']])
                    ? $uploadErrors[$uploadedFile['error']]
                    : 'Upload-Fehler Code: ' . $uploadedFile['error'];

                http_response_code(400);
                echo json_encode([
                    'error' => $errorMsg,
                    'php_limits' => [
                        'upload_max_filesize' => ini_get('upload_max_filesize'),
                        'post_max_size' => ini_get('post_max_size')
                    ]
                ]);
                return;
            }

            if ($uploadedFile['size'] > $maxSize) {
                http_response_code(400);
                echo json_encode(['error' => 'Datei zu groß (max 2GB, Datei: ' . round($uploadedFile['size']/1024/1024, 2) . 'MB)']);
                return;
            }

            // Get file extension and generate unique name
            $originalName = basename($uploadedFile['name']);
            $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

            if (!in_array($extension, $allowedExtensions)) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid file type. Allowed: ' . implode(', ', $allowedExtensions)]);
                return;
            }

            // Create unique filename with timestamp
            $timestamp = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
            $newFilename = $timestamp . '_' . dol_sanitizeFileName($originalName);
            $destPath = $docDir . '/' . $newFilename;

            if (move_uploaded_file($uploadedFile['tmp_name'], $destPath)) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'File uploaded',
                    'filename' => $newFilename,
                    'original_name' => $originalName
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save file']);
            }
            return;
        }

        // Check for base64 image data (from camera)
        if (!empty($input['image'])) {
            $imageData = $input['image'];
            $imageName = $input['name'] ?? 'photo';

            // Remove data URL prefix if present
            if (strpos($imageData, 'base64,') !== false) {
                $imageData = explode('base64,', $imageData)[1];
            }

            $decoded = base64_decode($imageData);
            if (!$decoded) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image data']);
                return;
            }

            // Detect image type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($decoded);
            $extension = 'jpg';
            if ($mimeType === 'image/png') $extension = 'png';
            elseif ($mimeType === 'image/gif') $extension = 'gif';

            $timestamp = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
            $newFilename = $timestamp . '_' . dol_sanitizeFileName($imageName) . '.' . $extension;
            $destPath = $docDir . '/' . $newFilename;

            if (file_put_contents($destPath, $decoded)) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Image uploaded',
                    'filename' => $newFilename
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save image']);
            }
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'No file provided']);
        return;
    }

    // Get documents/PDFs for intervention (GET request)
    if (isset($parts[2]) && $parts[2] === 'documents' && $method === 'GET') {
        $docPath = getFichinterDocDir() . '/' . $fichinter->ref;
        $documents = getInterventionDocuments($fichinter);
        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'intervention_ref' => $fichinter->ref,
            'doc_path' => $docPath,
            'doc_dir_exists' => is_dir($docPath),
            'documents' => $documents
        ]);
        return;
    }

    // Return full intervention with equipment
    $equipment = getInterventionEquipment($id);

    // Get signed_status directly from database (more reliable)
    $sqlStatus = "SELECT signed_status FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$id;
    $resStatus = $db->query($sqlStatus);
    $signedStatus = 0;
    if ($resStatus && $objStatus = $db->fetch_object($resStatus)) {
        $signedStatus = (int)$objStatus->signed_status;
    }

    echo json_encode([
        'status' => 'ok',
        'intervention' => [
            'id' => (int)$fichinter->id,
            'ref' => $fichinter->ref,
            'date_creation' => $fichinter->datec,
            'date_start' => $fichinter->dateo,
            'date_end' => $fichinter->datee,
            'duration' => (int)$fichinter->duree,
            'status' => (int)$fichinter->statut,
            'description' => $fichinter->description,
            'note_public' => $fichinter->note_public,
            'note_private' => $fichinter->note_private,
            'signed_status' => $signedStatus
        ],
        'equipment' => $equipment
    ]);
}

/**
 * Get equipment linked to intervention
 */
function getInterventionEquipment($intervention_id) {
    global $db;

    $sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.serial_number,";
    $sql .= " e.location_note, e.manufacturer, e.door_wings,";
    $sql .= " e.battery_install_month, e.battery_install_year, e.battery_replacement_cycle,";
    $sql .= " e.fire_protection,";
    $sql .= " e.smoke_detector_install_month, e.smoke_detector_install_year, e.smoke_detector_replacement_cycle,";
    $sql .= " l.link_type,";
    $sql .= " d.rowid as detail_id, d.work_done, d.issues_found, d.recommendations,";
    $sql .= " d.notes, d.work_date, d.work_duration,";
    $sql .= " d.commissioning_done, d.commissioning_date, d.commissioning_note,";
    $sql .= " d.acceptance_done, d.acceptance_date, d.acceptance_defect_free, d.acceptance_note,";
    $sql .= " d.instruction_done, d.testbook_handed";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d";
    $sql .= "   ON d.fk_intervention = l.fk_intervention AND d.fk_equipment = l.fk_equipment";
    $sql .= " WHERE l.fk_intervention = ".(int)$intervention_id;
    $sql .= " ORDER BY e.equipment_number";

    $resql = $db->query($sql);
    $equipment = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $eq = [
                'id' => (int)$obj->rowid,
                'ref' => $obj->equipment_number,
                'label' => $obj->label,
                'type' => $obj->equipment_type,
                'manufacturer' => $obj->manufacturer ?: '',
                'serial_number' => $obj->serial_number,
                'location' => $obj->location_note ?: '',
                'door_wings' => $obj->door_wings ?: '',
                'battery_install_month' => $obj->battery_install_month !== null ? (int)$obj->battery_install_month : null,
                'battery_install_year' => $obj->battery_install_year !== null ? (int)$obj->battery_install_year : null,
                'battery_replacement_cycle' => $obj->battery_replacement_cycle !== null ? (int)$obj->battery_replacement_cycle : null,
                'fire_protection' => $obj->fire_protection !== null ? (int)$obj->fire_protection : null,
                'smoke_detector_install_month' => $obj->smoke_detector_install_month !== null ? (int)$obj->smoke_detector_install_month : null,
                'smoke_detector_install_year' => $obj->smoke_detector_install_year !== null ? (int)$obj->smoke_detector_install_year : null,
                'smoke_detector_replacement_cycle' => $obj->smoke_detector_replacement_cycle !== null ? (int)$obj->smoke_detector_replacement_cycle : null,
                'link_type' => $obj->link_type,
                'detail' => null
            ];

            if ($obj->detail_id) {
                $eq['detail'] = [
                    'id' => (int)$obj->detail_id,
                    'work_done' => $obj->work_done,
                    'issues_found' => $obj->issues_found,
                    'recommendations' => $obj->recommendations,
                    'notes' => $obj->notes,
                    'work_date' => $obj->work_date,
                    'work_duration' => (int)$obj->work_duration,
                    'commissioning_done' => (int)$obj->commissioning_done,
                    'commissioning_date' => $obj->commissioning_date,
                    'commissioning_note' => $obj->commissioning_note,
                    'acceptance_done' => (int)$obj->acceptance_done,
                    'acceptance_date' => $obj->acceptance_date,
                    'acceptance_defect_free' => (int)$obj->acceptance_defect_free,
                    'acceptance_note' => $obj->acceptance_note,
                    'instruction_done' => (int)$obj->instruction_done,
                    'testbook_handed' => (int)$obj->testbook_handed
                ];
            }

            // Get materials for this equipment
            $eq['materials'] = getEquipmentMaterials($intervention_id, $obj->rowid);

            $equipment[] = $eq;
        }
    }

    return $equipment;
}

/**
 * Get materials for equipment in intervention
 */
function getEquipmentMaterials($intervention_id, $equipment_id) {
    global $db;

    $sql = "SELECT rowid, material_name, material_description, quantity, unit, unit_price, total_price, serial_number, notes";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
    $sql .= " WHERE fk_intervention = ".(int)$intervention_id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;

    $resql = $db->query($sql);
    $materials = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $materials[] = [
                'id' => (int)$obj->rowid,
                'name' => $obj->material_name,
                'description' => $obj->material_description,
                'quantity' => (float)$obj->quantity,
                'unit' => $obj->unit,
                'unit_price' => (float)$obj->unit_price,
                'total_price' => (float)$obj->total_price,
                'serial_number' => $obj->serial_number,
                'notes' => $obj->notes
            ];
        }
    }

    return $materials;
}

/**
 * Get unique object addresses for intervention
 * Primary: contact with OBJ role linked to intervention
 * Fallback: fk_address from linked equipment
 */
function getInterventionObjectAddresses($intervention_id) {
    global $db;

    $addresses = [];

    // Primary: contact with OBJ role linked to intervention
    $sql = "SELECT sp.rowid, sp.lastname, sp.firstname, sp.address, sp.zip, sp.town, sp.phone, sp.email, sp.note_public";
    $sql .= " FROM ".MAIN_DB_PREFIX."element_contact ec";
    $sql .= " JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = ec.fk_socpeople";
    $sql .= " WHERE ec.element_id = ".(int)$intervention_id;
    $sql .= " AND ec.fk_c_type_contact IN (";
    $sql .= "  SELECT rowid FROM ".MAIN_DB_PREFIX."c_type_contact";
    $sql .= "  WHERE element = 'fichinter' AND code = 'OBJ'";
    $sql .= " ) LIMIT 1";

    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $addresses[] = [
            'id'      => (int)$obj->rowid,
            'name'    => trim($obj->lastname . ' ' . $obj->firstname),
            'address' => $obj->address,
            'zip'     => $obj->zip,
            'town'    => $obj->town,
            'phone'   => $obj->phone,
            'email'   => $obj->email,
            'note'    => $obj->note_public,
        ];
        $db->free($resql);
        return $addresses;
    }

    // Fallback: fk_address from linked equipment
    $sql = "SELECT DISTINCT sp.rowid, sp.lastname, sp.firstname, sp.address, sp.zip, sp.town, sp.phone, sp.email, sp.note_public";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " WHERE l.fk_intervention = ".(int)$intervention_id;
    $sql .= " AND e.fk_address > 0";

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $addresses[] = [
                'id'      => (int)$obj->rowid,
                'name'    => trim($obj->lastname . ' ' . $obj->firstname),
                'address' => $obj->address,
                'zip'     => $obj->zip,
                'town'    => $obj->town,
                'phone'   => $obj->phone,
                'email'   => $obj->email,
                'note'    => $obj->note_public,
            ];
        }
        $db->free($resql);
    }

    return $addresses;
}

/**
 * GET/POST /detail/{intervention_id}/{equipment_id}
 */
function handleDetail($method, $parts, $input) {
    global $db, $user;

    $intervention_id = (int)($parts[1] ?? 0);
    $equipment_id = (int)($parts[2] ?? 0);  // 0 = general entries (no equipment)

    if (!$intervention_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    // equipment_id = 0 means "general entries" (without equipment link)
    $equipment_id_or_null = $equipment_id > 0 ? $equipment_id : null;

    $detail = new InterventionDetail($db);

    if ($method === 'GET') {
        // v1.7: Return all entries as array (equipment_id=0 means general entries)
        $entries = $detail->fetchAllByInterventionEquipment($intervention_id, $equipment_id_or_null);
        $totalDuration = $detail->getTotalDuration($intervention_id, $equipment_id_or_null);

        $entriesData = [];
        $recommendations = '';
        $notes = '';

        foreach ($entries as $entry) {
            // Load defect materials for this entry
            $defectMaterials = DefectMaterial::fetchAllForEntry($db, $entry->id);
            $materialsData = [];
            foreach ($defectMaterials as $mat) {
                $materialsData[] = [
                    'id' => $mat->id,
                    'fk_product' => $mat->fk_product ?: 0,
                    'freetext_label' => $mat->freetext_label ?: '',
                    'product_ref' => $mat->product_ref,
                    'product_label' => $mat->product_label,
                    'qty' => intval($mat->qty)
                ];
            }

            $entriesData[] = [
                'id' => (int)$entry->id,
                'entry_number' => (int)$entry->entry_number,
                'intervention_id' => (int)$entry->fk_intervention,
                'equipment_id' => (int)$entry->fk_equipment,
                'work_done' => $entry->work_done,
                'issues_found' => $entry->issues_found,
                'work_date' => $entry->work_date ? dol_print_date($entry->work_date, 'dayrfc') : null,
                'work_duration' => (int)$entry->work_duration,
                'photo' => $entry->photo,
                'materials' => $materialsData,
                'commissioning_done' => (int)$entry->commissioning_done,
                'commissioning_date' => $entry->commissioning_date ? dol_print_date($entry->commissioning_date, 'dayrfc') : null,
                'commissioning_note' => $entry->commissioning_note,
                'acceptance_done' => (int)$entry->acceptance_done,
                'acceptance_date' => $entry->acceptance_date ? dol_print_date($entry->acceptance_date, 'dayrfc') : null,
                'acceptance_defect_free' => (int)$entry->acceptance_defect_free,
                'acceptance_note' => $entry->acceptance_note,
                'instruction_done' => (int)$entry->instruction_done,
                'testbook_handed' => (int)$entry->testbook_handed
            ];
            // Get recommendations/notes from any entry that has them
            if (!empty($entry->recommendations)) $recommendations = $entry->recommendations;
            if (!empty($entry->notes)) $notes = $entry->notes;
        }

        echo json_encode([
            'status' => 'ok',
            'entries' => $entriesData,
            'recommendations' => $recommendations,
            'notes' => $notes,
            'total_duration' => $totalDuration,
            // Backwards compatibility: include first entry as 'detail'
            'detail' => count($entriesData) > 0 ? $entriesData[0] : null
        ]);
    } elseif ($method === 'POST' || $method === 'PUT') {
        // v1.7: Support entry_id for updating specific entries
        $entry_id = isset($input['entry_id']) ? (int)$input['entry_id'] : 0;
        $save_summary_only = !empty($input['save_summary_only']);

        // If saving summary only (recommendations/notes), update first entry
        if ($save_summary_only) {
            $entries = $detail->fetchAllByInterventionEquipment($intervention_id, $equipment_id_or_null);
            if (count($entries) > 0) {
                $detail = $entries[0];
                $detail->recommendations = $input['recommendations'] ?? '';
                $detail->notes = $input['notes'] ?? '';
                $result = $detail->update($user);
            } else {
                // No entries yet - create one with just recommendations/notes
                $detail->fk_intervention = $intervention_id;
                $detail->fk_equipment = $equipment_id_or_null;  // Can be NULL for general entries
                $detail->recommendations = $input['recommendations'] ?? '';
                $detail->notes = $input['notes'] ?? '';
                $detail->work_date = time();
                $result = $detail->create($user);
            }

            if ($result > 0) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Summary saved'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save summary']);
            }
            return;
        }

        if ($entry_id > 0) {
            // Update existing entry
            $detail->fetch($entry_id);
        }

        $detail->fk_intervention = $intervention_id;
        $detail->fk_equipment = $equipment_id_or_null;  // Can be NULL for general entries
        $detail->work_done = $input['work_done'] ?? '';
        $detail->issues_found = $input['issues_found'] ?? '';
        $detail->recommendations = $input['recommendations'] ?? '';
        $detail->notes = $input['notes'] ?? '';
        $detail->work_date = !empty($input['work_date']) ? strtotime($input['work_date']) : null;
        $detail->work_duration = (int)($input['work_duration'] ?? 0);

        // Commissioning and acceptance fields (v4.5)
        $detail->commissioning_done = (int)($input['commissioning_done'] ?? 0);
        $detail->commissioning_date = !empty($input['commissioning_date']) ? strtotime($input['commissioning_date']) : null;
        $detail->commissioning_note = $input['commissioning_note'] ?? '';
        $detail->acceptance_done = (int)($input['acceptance_done'] ?? 0);
        $detail->acceptance_date = !empty($input['acceptance_date']) ? strtotime($input['acceptance_date']) : null;
        $detail->acceptance_defect_free = (int)($input['acceptance_defect_free'] ?? 1);
        $detail->acceptance_note = $input['acceptance_note'] ?? '';
        $detail->instruction_done = (int)($input['instruction_done'] ?? 0);
        $detail->testbook_handed = (int)($input['testbook_handed'] ?? 0);

        // Get intervention ref for photo directory
        dol_include_once('/fichinter/class/fichinter.class.php');
        $fichinter = new Fichinter($db);
        $fichinter->fetch($intervention_id);
        $docDir = $conf->ficheinter->dir_output . '/' . dol_sanitizeFileName($fichinter->ref) . '';

        // Handle photo upload
        if (!empty($input['photo']) && strpos($input['photo'], 'data:image') === 0) {
            // Ensure directory exists
            if (!is_dir($docDir)) {
                dol_mkdir($docDir);
            }

            // Delete old photo if exists
            if (!empty($detail->photo)) {
                $oldPath = $docDir . '/' . $detail->photo;
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }

            // Save new photo
            $imageData = $input['photo'];
            if (strpos($imageData, 'base64,') !== false) {
                $imageData = explode('base64,', $imageData)[1];
            }
            $decoded = base64_decode($imageData);

            if ($decoded) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mimeType = $finfo->buffer($decoded);
                $extension = 'jpg';
                if ($mimeType === 'image/png') $extension = 'png';
                elseif ($mimeType === 'image/gif') $extension = 'gif';

                $timestamp = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
                $eqId = $equipment_id > 0 ? $equipment_id : 'general';
                $filename = "entry_{$eqId}_{$timestamp}.{$extension}";
                $filepath = $docDir . '/' . $filename;

                if (file_put_contents($filepath, $decoded)) {
                    $detail->photo = $filename;
                }
            }
        } elseif (!empty($input['delete_photo']) && !empty($detail->photo)) {
            // Delete photo
            $oldPath = $docDir . '/' . $detail->photo;
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
            $detail->photo = null;
        }

        $result = $detail->createOrUpdate($user);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Detail saved',
                'id' => (int)$detail->id,
                'entry_number' => (int)$detail->entry_number,
                'photo' => $detail->photo
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to save detail',
                'details' => $detail->errors
            ]);
        }
    } elseif ($method === 'DELETE') {
        // v1.7: Delete specific entry (from query param or input)
        $entry_id = isset($_GET['entry_id']) ? (int)$_GET['entry_id'] : (isset($input['entry_id']) ? (int)$input['entry_id'] : 0);

        if ($entry_id > 0 && $detail->fetch($entry_id) > 0) {
            $result = $detail->delete($user);
            if ($result > 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Entry deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete entry']);
            }
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Entry ID required']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * POST /sync - Batch sync offline changes
 */
function handleSync($method, $input) {
    global $db, $user;

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    if (!isset($input['changes']) || !is_array($input['changes'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No changes provided']);
        return;
    }

    $results = [];
    $errors = [];

    foreach ($input['changes'] as $change) {
        $type = $change['type'] ?? '';
        $data = $change['data'] ?? [];

        try {
            switch ($type) {
                case 'detail':
                    $detail = new InterventionDetail($db);
                    $detail->fk_intervention = (int)$data['intervention_id'];
                    $detail->fk_equipment = (int)$data['equipment_id'];
                    $detail->work_done = $data['work_done'] ?? '';
                    $detail->issues_found = $data['issues_found'] ?? '';
                    $detail->recommendations = $data['recommendations'] ?? '';
                    $detail->notes = $data['notes'] ?? '';
                    $detail->work_date = !empty($data['work_date']) ? strtotime($data['work_date']) : null;
                    $detail->work_duration = (int)($data['work_duration'] ?? 0);

                    $result = $detail->createOrUpdate($user);

                    $results[] = [
                        'type' => 'detail',
                        'intervention_id' => $detail->fk_intervention,
                        'equipment_id' => $detail->fk_equipment,
                        'success' => $result > 0,
                        'id' => $result > 0 ? $detail->id : null
                    ];
                    break;

                case 'material':
                    // Handle material sync
                    $material = new InterventionMaterial($db);
                    $material->fk_intervention = (int)$data['intervention_id'];
                    $material->fk_equipment = (int)$data['equipment_id'];
                    $material->material_name = $data['material_name'] ?? '';
                    $material->quantity = (float)($data['quantity'] ?? 0);
                    $material->unit = $data['unit'] ?? '';
                    $material->unit_price = (float)($data['unit_price'] ?? 0);
                    $material->notes = $data['notes'] ?? '';

                    if (!empty($data['id'])) {
                        $material->id = (int)$data['id'];
                        $result = $material->update($user);
                    } else {
                        $result = $material->create($user);
                    }

                    $results[] = [
                        'type' => 'material',
                        'success' => $result > 0,
                        'id' => $result > 0 ? $material->id : null
                    ];
                    break;

                case 'signature':
                    // Handle signature sync - calls the same logic as the signature endpoint
                    $intervention_id = (int)$data['intervention_id'];
                    $signatureData = $data['signature'] ?? '';
                    $signerName = $data['signer_name'] ?? '';

                    if (!$intervention_id || !$signatureData) {
                        $results[] = [
                            'type' => 'signature',
                            'intervention_id' => $intervention_id,
                            'success' => false,
                            'error' => 'Missing intervention_id or signature data'
                        ];
                        break;
                    }

                    // Process signature using helper function
                    $signatureResult = processSignature($intervention_id, $signatureData, $signerName);

                    $results[] = [
                        'type' => 'signature',
                        'intervention_id' => $intervention_id,
                        'success' => $signatureResult['success'],
                        'signed_pdf' => $signatureResult['signed_pdf'] ?? null,
                        'error' => $signatureResult['error'] ?? null
                    ];
                    break;

                default:
                    $errors[] = "Unknown change type: $type";
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }

    echo json_encode([
        'status' => count($errors) === 0 ? 'ok' : 'partial',
        'results' => $results,
        'errors' => $errors
    ]);
}

/**
 * POST /signature/{intervention_id} - Upload signature and create signed PDF
 */
function handleSignature($method, $parts, $input) {
    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $intervention_id = (int)($parts[1] ?? 0);
    if (!$intervention_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    if (empty($input['signature'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Signature data required']);
        return;
    }

    $signatureData = $input['signature'];
    $signerName = $input['signer_name'] ?? '';

    // Use shared signature processing function
    $result = processSignature($intervention_id, $signatureData, $signerName);

    if ($result['success']) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Signature saved and PDF signed',
            'signature_file' => $result['signature_file'] ?? null,
            'signed_pdf' => $result['signed_pdf'] ?? null,
            'signed_status' => 3,
            'intervention_closed' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => $result['error'] ?? 'Failed to process signature',
            'signed_pdf' => $result['signed_pdf'] ?? null
        ]);
    }
}

/**
 * POST /material - Create material
 * DELETE /material/{id} - Delete material
 */
function handleMaterial($method, $parts, $input) {
    global $db, $user;

    if ($method === 'POST') {
        // Create new material
        if (empty($input['intervention_id']) || empty($input['equipment_id']) || empty($input['material_name'])) {
            http_response_code(400);
            echo json_encode(['error' => 'intervention_id, equipment_id, and material_name required']);
            return;
        }

        $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
        $sql .= " (fk_intervention, fk_equipment, material_name, material_description,";
        $sql .= " quantity, unit, unit_price, total_price, serial_number, notes,";
        $sql .= " date_creation, fk_user_creat)";
        $sql .= " VALUES (";
        $sql .= (int)$input['intervention_id'].",";
        $sql .= (int)$input['equipment_id'].",";
        $sql .= "'".$db->escape($input['material_name'])."',";
        $sql .= ($input['material_description'] ? "'".$db->escape($input['material_description'])."'" : "NULL").",";
        $sql .= (float)($input['quantity'] ?? 1).",";
        $sql .= "'".$db->escape($input['unit'] ?? 'Stk')."',";
        $sql .= (float)($input['unit_price'] ?? 0).",";
        $sql .= (float)($input['total_price'] ?? 0).",";
        $sql .= ($input['serial_number'] ? "'".$db->escape($input['serial_number'])."'" : "NULL").",";
        $sql .= ($input['notes'] ? "'".$db->escape($input['notes'])."'" : "NULL").",";
        $sql .= "'".$db->idate(dol_now())."',";
        $sql .= (int)$user->id;
        $sql .= ")";

        $resql = $db->query($sql);

        if ($resql) {
            $id = $db->last_insert_id(MAIN_DB_PREFIX."equipmentmanager_intervention_material");
            echo json_encode([
                'status' => 'ok',
                'message' => 'Material created',
                'id' => (int)$id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create material: ' . $db->lasterror()]);
        }
    } elseif ($method === 'DELETE') {
        // Delete material
        $id = (int)($parts[1] ?? 0);
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Material ID required']);
            return;
        }

        $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material";
        $sql .= " WHERE rowid = ".(int)$id;

        $resql = $db->query($sql);

        if ($resql) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Material deleted'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete material']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * GET /products - Search products
 * GET /products?search=term - Search by name/ref
 */
function handleProducts($method, $parts, $input) {
    global $db, $user;

    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $search = $_GET['search'] ?? '';
    $limit = (int)($_GET['limit'] ?? 50);

    $sql = "SELECT p.rowid, p.ref, p.label, p.price, p.tva_tx";
    $sql .= " FROM ".MAIN_DB_PREFIX."product p";
    $sql .= " WHERE p.tosell = 1"; // Only products for sale

    if (!empty($search)) {
        $sql .= " AND (p.ref LIKE '%".$db->escape($search)."%'";
        $sql .= " OR p.label LIKE '%".$db->escape($search)."%')";
    }

    $sql .= " ORDER BY p.label ASC";
    $sql .= " LIMIT ".(int)$limit;

    $resql = $db->query($sql);
    $products = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $products[] = [
                'id' => (int)$obj->rowid,
                'ref' => $obj->ref,
                'label' => $obj->label,
                'price' => (float)$obj->price,
                'vat_rate' => (float)$obj->tva_tx
            ];
        }
    }

    echo json_encode([
        'status' => 'ok',
        'count' => count($products),
        'products' => $products
    ]);
}

/**
 * GET /available-equipment/{intervention_id} - Get equipment from object addresses not yet linked
 */
function handleAvailableEquipment($method, $parts, $input) {
    global $db, $user;

    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $intervention_id = (int)($parts[1] ?? 0);
    if (!$intervention_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID required']);
        return;
    }

    // Get the thirdparty (customer) of this intervention
    $sql_inter = "SELECT fk_soc FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$intervention_id;
    $res_inter = $db->query($sql_inter);
    if (!$res_inter || !$db->num_rows($res_inter)) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }
    $inter = $db->fetch_object($res_inter);
    $socid = (int)$inter->fk_soc;

    // Get all equipment for this customer that is NOT yet linked to this intervention
    $sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.location_note,";
    $sql .= " sp.lastname, sp.firstname, sp.address, sp.zip, sp.town";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " WHERE e.fk_soc = ".(int)$socid;
    $sql .= " AND e.rowid NOT IN (";
    $sql .= "   SELECT fk_equipment FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= "   WHERE fk_intervention = ".(int)$intervention_id;
    $sql .= " )";
    $sql .= " ORDER BY sp.lastname, sp.town, e.equipment_number";

    $resql = $db->query($sql);
    $equipment = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $addressName = trim($obj->lastname . ' ' . $obj->firstname);
            $equipment[] = [
                'id' => (int)$obj->rowid,
                'ref' => $obj->equipment_number,
                'label' => $obj->label,
                'type' => $obj->equipment_type,
                'location' => $obj->location_note,
                'address' => [
                    'name' => $addressName,
                    'street' => $obj->address,
                    'zip' => $obj->zip,
                    'town' => $obj->town
                ]
            ];
        }
    }

    echo json_encode([
        'status' => 'ok',
        'intervention_id' => $intervention_id,
        'count' => count($equipment),
        'equipment' => $equipment
    ]);
}

/**
 * POST /link-equipment - Link equipment to intervention
 */
function handleLinkEquipment($method, $parts, $input) {
    global $db, $user;

    if (!in_array($method, ['POST', 'DELETE'])) {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $intervention_id = (int)($input['intervention_id'] ?? 0);
    $equipment_id = (int)($input['equipment_id'] ?? 0);
    $link_type = $input['link_type'] ?? 'service';

    // DELETE: remove equipment from intervention (cascade detail + materials + checklists)
    if ($method === 'DELETE') {
        if (!$intervention_id || !$equipment_id) {
            http_response_code(400);
            echo json_encode(['error' => 'intervention_id and equipment_id required']);
            return;
        }

        $db->begin();

        // Delete checklist results for this equipment/intervention
        $sqlCl = "DELETE cr FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_result cr"
               . " JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il"
               . "   ON il.rowid = cr.fk_equipment_intervention"
               . " WHERE il.fk_intervention = ".(int)$intervention_id
               . " AND il.fk_equipment = ".(int)$equipment_id;
        $db->query($sqlCl);

        // Delete materials
        $sqlMat = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_material"
                . " WHERE fk_intervention = ".(int)$intervention_id
                . " AND fk_equipment = ".(int)$equipment_id;
        $db->query($sqlMat);

        // Delete detail
        $sqlDet = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail"
                . " WHERE fk_intervention = ".(int)$intervention_id
                . " AND fk_equipment = ".(int)$equipment_id;
        $db->query($sqlDet);

        // Delete the link itself
        $sqlLnk = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link"
                . " WHERE fk_intervention = ".(int)$intervention_id
                . " AND fk_equipment = ".(int)$equipment_id;
        $resql = $db->query($sqlLnk);

        if ($resql) {
            $db->commit();
            echo json_encode([
                'status' => 'ok',
                'message' => 'Equipment unlinked',
                'intervention_id' => $intervention_id,
                'equipment_id' => $equipment_id
            ]);
        } else {
            $db->rollback();
            http_response_code(500);
            echo json_encode(['error' => 'Failed to unlink equipment: ' . $db->lasterror()]);
        }
        return;
    }

    if (!$intervention_id || !$equipment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'intervention_id and equipment_id required']);
        return;
    }

    // Validate link_type
    if (!in_array($link_type, ['maintenance', 'service'])) {
        $link_type = 'service';
    }

    $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " (fk_intervention, fk_equipment, link_type, date_creation, fk_user_creat)";
    $sql .= " VALUES (";
    $sql .= (int)$intervention_id.",";
    $sql .= (int)$equipment_id.",";
    $sql .= "'".$db->escape($link_type)."',";
    $sql .= "'".$db->idate(dol_now())."',";
    $sql .= (int)$user->id;
    $sql .= ")";

    $resql = $db->query($sql);

    if ($resql) {
        echo json_encode([
            'status' => 'ok',
            'message' => 'Equipment linked',
            'intervention_id' => $intervention_id,
            'equipment_id' => $equipment_id,
            'link_type' => $link_type
        ]);
    } else {
        // Check if duplicate
        if ($db->lasterrno() == 1062) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Equipment already linked'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to link equipment: ' . $db->lasterror()]);
        }
    }
}

/**
 * Get the fichinter document output directory
 */
function getFichinterDocDir() {
    global $conf, $dolibarr_main_data_root;

    // Method 1: Try $conf->fichinter->dir_output
    if (!empty($conf->fichinter->dir_output)) {
        return $conf->fichinter->dir_output;
    }

    // Method 2: Build from DOL_DATA_ROOT constant
    if (defined('DOL_DATA_ROOT') && DOL_DATA_ROOT) {
        return DOL_DATA_ROOT . '/ficheinter';
    }

    // Method 3: Try global $dolibarr_main_data_root from conf.php
    if (!empty($dolibarr_main_data_root)) {
        return $dolibarr_main_data_root . '/ficheinter';
    }

    // Method 4: Try to read from conf.php directly
    $confFile = DOL_DOCUMENT_ROOT . '/conf/conf.php';
    if (file_exists($confFile)) {
        include $confFile;
        if (!empty($dolibarr_main_data_root)) {
            return $dolibarr_main_data_root . '/ficheinter';
        }
    }

    // Last fallback - common Dolibarr document paths
    $possiblePaths = [
        '/var/lib/dolibarr/documents/ficheinter',
        '/home/dolibarr/documents/ficheinter',
        dirname(DOL_DOCUMENT_ROOT) . '/documents/ficheinter'
    ];

    foreach ($possiblePaths as $path) {
        if (is_dir($path) || is_dir(dirname($path))) {
            return $path;
        }
    }

    return '/var/lib/dolibarr/documents/ficheinter';
}

/**
 * Generate PDF for intervention using EquipmentManager template
 */
function generateInterventionPDF($fichinter, $user) {
    global $conf, $langs, $db;

    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

    // Always use EquipmentManager PDF template for proper equipment details and technician signature
    $modele = 'equipmentmanager';

    // Refetch to ensure we have latest data
    $fichinter->fetch($fichinter->id);
    $fichinter->fetch_thirdparty();
    $fichinter->fetch_lines();

    $outputlangs = $langs;
    if (!empty($conf->global->MAIN_MULTILANGS) && !empty($fichinter->thirdparty->default_lang)) {
        $outputlangs = new Translate("", $conf);
        $outputlangs->setDefaultLang($fichinter->thirdparty->default_lang);
    }
    $outputlangs->loadLangs(array("main", "interventions", "companies", "equipmentmanager@equipmentmanager"));

    $docDir = getFichinterDocDir();
    $result = $fichinter->generateDocument($modele, $outputlangs);

    return $result > 0;
}

/**
 * Regenerate all completed checklist PDFs for an intervention
 * Called after intervention validation to update filenames with new reference
 * Also cleans up old checklist PDFs
 */
function regenerateChecklistPDFs($fichinter, $user, $langs) {
    global $db, $conf;

    dol_include_once('/equipmentmanager/class/pdf_checklist.class.php');
    dol_include_once('/equipmentmanager/class/checklistresult.class.php');
    dol_include_once('/equipmentmanager/class/checklisttemplate.class.php');
    dol_include_once('/equipmentmanager/class/equipment.class.php');

    $regenerated = 0;

    // Get document directory for this intervention
    $doc_dir = $conf->ficheinter->dir_output.'/'.dol_sanitizeFileName($fichinter->ref);

    // Delete old checklist PDFs (Checkliste_*.pdf and Checklist_*.pdf patterns)
    if (is_dir($doc_dir)) {
        $old_files = glob($doc_dir.'/Checkliste_*.pdf');
        $old_files = array_merge($old_files, glob($doc_dir.'/Checklist_*.pdf'));
        $old_files = array_merge($old_files, glob($doc_dir.'/Checklisten_*.pdf'));
        foreach ($old_files as $old_file) {
            @unlink($old_file);
        }
    }

    // Find all completed checklists for this intervention
    $sql = "SELECT cr.rowid as checklist_id, cr.fk_equipment, cr.fk_template";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_results cr";
    $sql .= " WHERE cr.fk_intervention = ".(int)$fichinter->id;
    $sql .= " AND cr.status = 1"; // Only completed checklists

    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            // Load checklist
            $checklist = new ChecklistResult($db);
            $checklist->fetch($obj->checklist_id);
            $checklist->fetchItemResults();

            // Load equipment
            $equipment = new Equipment($db);
            $equipment->fetch($obj->fk_equipment);

            // Load template
            $template = new ChecklistTemplate($db);
            $template->fetch($obj->fk_template);
            $template->fetchSectionsWithItems();

            // Regenerate PDF
            $pdf_gen = new pdf_checklist($db);
            $result = $pdf_gen->write_file($checklist, $equipment, $template, $fichinter, $user, $langs, false);

            if ($result) {
                $regenerated++;
            }
        }
        $db->free($resql);
    }

    return $regenerated;
}

/**
 * Get list of documents/PDFs/images for intervention
 */
function getInterventionDocuments($fichinter) {
    global $conf;

    $documents = [];

    // Get the upload directory for this intervention
    $upload_dir = getFichinterDocDir() . '/' . $fichinter->ref;

    // Build base URL for document access (relative to Dolibarr root)
    $baseUrl = dol_buildpath('/document.php', 1);

    // Allowed file extensions
    $pdfExtensions = ['pdf'];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif'];

    if (is_dir($upload_dir)) {
        // Scan directory for files
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $filepath = $upload_dir . '/' . $file;

            if (in_array($ext, $pdfExtensions)) {
                $documents[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'date' => filemtime($filepath),
                    'url' => $baseUrl . '?modulepart=fichinter&file=' . urlencode($fichinter->ref . '/' . $file),
                    'type' => 'pdf'
                ];
            } elseif (in_array($ext, $imageExtensions)) {
                $documents[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'date' => filemtime($filepath),
                    'url' => $baseUrl . '?modulepart=fichinter&file=' . urlencode($fichinter->ref . '/' . $file),
                    'type' => 'image'
                ];
            }
        }

        // Also check for signatures
        $sigDir = $upload_dir . '/signatures';
        if (is_dir($sigDir)) {
            $sigFiles = scandir($sigDir);
            foreach ($sigFiles as $file) {
                if ($file === '.' || $file === '..') continue;
                if (pathinfo($file, PATHINFO_EXTENSION) === 'png') {
                    $filepath = $sigDir . '/' . $file;
                    $documents[] = [
                        'name' => 'Unterschrift: ' . $file,
                        'size' => filesize($filepath),
                        'date' => filemtime($filepath),
                        'url' => $baseUrl . '?modulepart=fichinter&file=' . urlencode($fichinter->ref . '/signatures/' . $file),
                        'type' => 'signature'
                    ];
                }
            }
        }
    }

    // Sort by date descending
    usort($documents, function($a, $b) {
        return $b['date'] - $a['date'];
    });

    return $documents;
}

/**
 * Generate Acceptance Protocol PDF for an intervention
 * Creates Abnahmeprotokoll_REF.pdf in the intervention documents folder
 *
 * @param Fichinter $fichinter Intervention object (already fetched with thirdparty)
 * @param User $user Current user
 * @return string|false PDF filename on success, false on failure
 */
function generateAcceptanceProtocol($fichinter, $user) {
    global $db, $conf, $langs, $mysoc;

    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
    dol_include_once('/equipmentmanager/class/equipment.class.php');

    $outputlangs = $langs;
    $outputlangs->loadLangs(array("main", "interventions", "companies", "equipmentmanager@equipmentmanager"));

    // Check if there are service equipment
    $sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.serial_number,";
    $sql .= " e.location_note, e.manufacturer,";
    $sql .= " d.commissioning_done, d.commissioning_date, d.commissioning_note,";
    $sql .= " d.acceptance_done, d.acceptance_date, d.acceptance_defect_free, d.acceptance_note,";
    $sql .= " d.instruction_done, d.testbook_handed";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d";
    $sql .= "   ON d.fk_intervention = l.fk_intervention AND d.fk_equipment = l.fk_equipment";
    $sql .= " WHERE l.fk_intervention = ".(int)$fichinter->id;
    $sql .= " AND l.link_type = 'service'";
    $sql .= " AND (d.commissioning_done = 1 OR d.acceptance_done = 1)";
    $sql .= " ORDER BY e.equipment_number";

    $resql = $db->query($sql);
    $equipmentList = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $equipmentList[] = $obj;
        }
    }

    if (empty($equipmentList)) {
        return false;
    }

    // Get object address contact
    $objectAddress = null;
    $contacts = $fichinter->liste_contact(-1, 'external');
    if (is_array($contacts)) {
        foreach ($contacts as $contact) {
            if ($contact['code'] == 'OBJ') {
                $contactObj = new Contact($db);
                $contactObj->fetch($contact['id']);
                $objectAddress = $contactObj;
                break;
            }
        }
    }

    // Get equipment type labels
    $typeLabels = Equipment::getEquipmentTypesTranslated($db, $langs);

    // Create PDF
    $pdf = pdf_getInstance();
    if (class_exists('TCPDF')) {
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
    }
    $pdf->SetCreator("Dolibarr ".DOL_VERSION);
    $pdf->SetAuthor($mysoc->name);
    $pdf->SetTitle("Abnahmeprotokoll ".$fichinter->ref);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 25);
    $pdf->AddPage();

    $default_font_size = 9;
    $pageWidth = $pdf->getPageWidth();
    $contentWidth = $pageWidth - 30;
    $leftMargin = 15;

    // Logo
    $posy = 15;
    $logo = $conf->mycompany->dir_output.'/logos/'.$mysoc->logo;
    if ($mysoc->logo && file_exists($logo)) {
        $height = pdf_getHeightForLogo($logo);
        $pdf->Image($logo, $leftMargin, $posy, 0, $height);
        $posy += $height + 5;
    } else {
        $posy += 5;
    }

    // Company name
    $pdf->SetFont('', 'B', $default_font_size + 2);
    $pdf->SetXY($leftMargin, $posy);
    $pdf->Cell(0, 6, $outputlangs->convToOutputCharset($mysoc->name), 0, 1, 'L');
    $posy += 8;

    // Header (gray box like checklist)
    $pdf->SetFont('', 'B', $default_font_size + 4);
    $pdf->SetXY($leftMargin, $posy);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($contentWidth, 10, "Inbetriebnahme- und Abnahmeprotokoll", 1, 1, 'C', true);
    $posy += 12;

    // Intervention ref (left) and Date (right)
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->SetXY($leftMargin, $posy);
    $pdf->Cell(50, 5, "Serviceauftrag: ".$fichinter->ref, 0, 0, 'L');
    $pdf->Cell(0, 5, "Datum: ".dol_print_date(dol_now(), 'day'), 0, 1, 'R');
    $posy += 9;

    // Customer / Object Address columns with box
    $colWidth = ($contentWidth / 2) - 3;
    $boxStartY = $posy;

    // Draw background box for addresses
    $addressBoxHeight = 28;
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetFillColor(250, 250, 250);
    $pdf->Rect($leftMargin, $boxStartY, $contentWidth, $addressBoxHeight, 'DF');

    $posy += 3;

    // Headers inside box
    $pdf->SetFont('', 'B', $default_font_size);
    $pdf->SetXY($leftMargin + 3, $posy);
    $pdf->Cell($colWidth - 6, 5, "Auftraggeber:", 0, 0, 'L');
    $pdf->SetX($leftMargin + $colWidth + 3);
    $pdf->Cell($colWidth - 6, 5, "Objektadresse:", 0, 1, 'L');
    $posy += 6;

    $pdf->SetFont('', '', $default_font_size - 1);

    // Customer details (left)
    $pdf->SetXY($leftMargin + 3, $posy);
    if ($fichinter->thirdparty) {
        $pdf->Cell($colWidth - 6, 4, $fichinter->thirdparty->name, 0, 1, 'L');
        if ($fichinter->thirdparty->address) {
            $pdf->SetX($leftMargin + 3);
            $pdf->Cell($colWidth - 6, 4, $fichinter->thirdparty->address, 0, 1, 'L');
        }
        if ($fichinter->thirdparty->zip || $fichinter->thirdparty->town) {
            $pdf->SetX($leftMargin + 3);
            $pdf->Cell($colWidth - 6, 4, trim($fichinter->thirdparty->zip.' '.$fichinter->thirdparty->town), 0, 1, 'L');
        }
    }

    // Object address (right)
    $pdf->SetXY($leftMargin + $colWidth + 3, $posy);
    if ($objectAddress) {
        // Name
        $objName = trim($objectAddress->firstname.' '.$objectAddress->lastname);
        if (!empty($objName)) {
            $pdf->Cell($colWidth - 6, 4, $objName, 0, 1, 'L');
            $pdf->SetX($leftMargin + $colWidth + 3);
        }
        if ($objectAddress->address) {
            $pdf->Cell($colWidth - 6, 4, $objectAddress->address, 0, 1, 'L');
            $pdf->SetX($leftMargin + $colWidth + 3);
        }
        if ($objectAddress->zip || $objectAddress->town) {
            $pdf->Cell($colWidth - 6, 4, trim($objectAddress->zip.' '.$objectAddress->town), 0, 1, 'L');
        }
    } else {
        $pdf->Cell($colWidth - 6, 4, "-", 0, 1, 'L');
    }

    $pdf->SetY($boxStartY + $addressBoxHeight + 5);

    // Equipment list
    $equipmentCount = count($equipmentList);
    $equipmentIndex = 0;
    $rowHeight = 5;
    $headerHeight = 6;
    $fixedBoxHeight = $headerHeight + ($rowHeight * 4);
    $halfWidth = ($contentWidth / 2) - 2;

    foreach ($equipmentList as $eq) {
        $equipmentIndex++;

        if ($pdf->GetY() > 190) {
            $pdf->AddPage();
        }

        $typeLabel = isset($typeLabels[$eq->equipment_type]) ? $typeLabels[$eq->equipment_type] : $eq->equipment_type;

        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetLineWidth(0.3);
        $pdf->SetFillColor(230, 230, 230);
        $pdf->SetFont('', 'B', $default_font_size);
        $pdf->Cell(0, 6, $eq->equipment_number." - ".$eq->label, 1, 1, 'L', true);

        $pdf->SetFillColor(245, 245, 245);
        $pdf->SetFont('', '', $default_font_size - 1);
        $details = "Typ: ".$typeLabel;
        if (!empty($eq->serial_number)) $details .= "  |  S/N: ".$eq->serial_number;
        if (!empty($eq->manufacturer)) $details .= "  |  Hersteller: ".$eq->manufacturer;
        if (!empty($eq->location_note)) $details .= "  |  Standort: ".$eq->location_note;
        $pdf->Cell(0, 5, $details, 'LRB', 1, 'L', true);
        $pdf->SetLineWidth(0.2);
        $pdf->Ln(1);

        $boxStartY = $pdf->GetY();

        // LEFT: Inbetriebnahme
        $pdf->SetXY($leftMargin, $boxStartY);
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell($halfWidth, $headerHeight, "Inbetriebnahme", 1, 1, 'L');

        $pdf->SetX($leftMargin);
        $erfolgtIBN = $eq->commissioning_done ? "Ja" : "Nein";
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(18, $rowHeight, "Erfolgt:", 'L', 0, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->Cell($halfWidth - 18, $rowHeight, $erfolgtIBN, 'R', 1, 'L');

        $pdf->SetX($leftMargin);
        if ($eq->commissioning_done) {
            $dateStr = $eq->commissioning_date ? dol_print_date($db->jdate($eq->commissioning_date), 'day') : '-';
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->Cell(18, $rowHeight, "Datum:", 'L', 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->Cell($halfWidth - 18, $rowHeight, $dateStr, 'R', 1, 'L');
        } else {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->Cell(22, $rowHeight, "Bemerkung:", 'L', 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->Cell($halfWidth - 22, $rowHeight, "", 'R', 1, 'L');
        }

        $pdf->SetX($leftMargin);
        $pdf->SetFont('', '', $default_font_size - 1);
        if ($eq->commissioning_done) {
            $pdf->Cell($halfWidth, $rowHeight, "", 'LR', 1, 'L');
        } else {
            $note = $eq->commissioning_note ?: '-';
            $pdf->Cell($halfWidth, $rowHeight, $note, 'LR', 1, 'L');
        }

        $pdf->SetX($leftMargin);
        $pdf->Cell($halfWidth, $rowHeight, "", 'LRB', 1, 'L');

        // RIGHT: Abnahme
        $pdf->SetXY($leftMargin + $halfWidth + 4, $boxStartY);
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell($halfWidth, $headerHeight, "Abnahme", 1, 1, 'L');

        $pdf->SetX($leftMargin + $halfWidth + 4);
        $erfolgtAbn = $eq->acceptance_done ? "Ja" : "Nein";
        $pdf->SetFont('', 'B', $default_font_size - 1);
        $pdf->Cell(18, $rowHeight, "Erfolgt:", 'L', 0, 'L');
        $pdf->SetFont('', '', $default_font_size - 1);
        $pdf->Cell($halfWidth - 18, $rowHeight, $erfolgtAbn, 'R', 1, 'L');

        $pdf->SetX($leftMargin + $halfWidth + 4);
        if ($eq->acceptance_done) {
            $dateStr = $eq->acceptance_date ? dol_print_date($db->jdate($eq->acceptance_date), 'day') : '-';
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->Cell(18, $rowHeight, "Datum:", 'L', 0, 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
            $pdf->Cell($halfWidth - 18, $rowHeight, $dateStr, 'R', 1, 'L');
        } else {
            $pdf->SetFont('', 'B', $default_font_size - 1);
            $pdf->Cell($halfWidth, $rowHeight, "Wesentliche Mängel:", 'LR', 1, 'L');
            $pdf->SetFont('', '', $default_font_size - 1);
        }

        $pdf->SetX($leftMargin + $halfWidth + 4);
        if ($eq->acceptance_done) {
            if (!empty($eq->acceptance_note)) {
                $pdf->SetFont('', 'B', $default_font_size - 1);
                $pdf->Cell(22, $rowHeight, "Bemerkung:", 'L', 0, 'L');
                $pdf->SetFont('', '', $default_font_size - 1);
                $pdf->Cell($halfWidth - 22, $rowHeight, $eq->acceptance_note, 'R', 1, 'L');
            } else {
                $pdf->Cell($halfWidth, $rowHeight, "", 'LR', 1, 'L');
            }
        } else {
            $maengel = $eq->acceptance_note ?: '-';
            $pdf->Cell($halfWidth, $rowHeight, $maengel, 'LR', 1, 'L');
        }

        $pdf->SetX($leftMargin + $halfWidth + 4);
        $pdf->Cell($halfWidth, $rowHeight, "", 'LRB', 1, 'L');

        $pdf->SetY($boxStartY + $fixedBoxHeight + 1);

        $pdf->SetFont('', '', $default_font_size - 1);
        $checkYes = "[X]";
        $checkNo = "[  ]";
        $instruction = $eq->instruction_done ? $checkYes : $checkNo;
        $testbook = $eq->testbook_handed ? $checkYes : $checkNo;
        $pdf->Cell($halfWidth, 5, $instruction." Einweisung erfolgt", 1, 0, 'L');
        $pdf->Cell(4, 5, "", 0, 0);
        $pdf->Cell($halfWidth, 5, $testbook." Prüfbuch übergeben", 1, 1, 'L');

        if ($equipmentIndex < $equipmentCount) {
            $pdf->Ln(4);
            $pdf->SetDrawColor(180, 180, 180);
            $pdf->SetLineWidth(0.3);
            $pdf->Line($leftMargin, $pdf->GetY(), $leftMargin + $contentWidth, $pdf->GetY());
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.2);
            $pdf->Ln(4);
        } else {
            $pdf->Ln(2);
        }
    }

    // Signature section
    $pdf->SetAutoPageBreak(false);
    $signatureStartY = 240;

    if ($pdf->GetY() > $signatureStartY - 5) {
        $pdf->AddPage();
    }

    $pdf->SetY($signatureStartY);

    $signatureFile = null;
    $signatureDir = $conf->ficheinter->dir_output.'/'.$fichinter->ref.'/signatures';
    if (is_dir($signatureDir)) {
        $files = scandir($signatureDir);
        foreach ($files as $file) {
            if (preg_match('/_signature\.png$/', $file)) {
                $signatureFile = $signatureDir.'/'.$file;
                break;
            }
        }
    }

    $techSignatureFile = null;
    $techFile = DOL_DATA_ROOT.'/equipmentmanager/signatures/user_'.$user->id.'.png';
    if (file_exists($techFile)) {
        $techSignatureFile = $techFile;
    }

    $boxWidth = ($contentWidth / 2) - 5;
    $boxHeight = 25;
    $curY = $pdf->GetY();

    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->SetXY($leftMargin, $curY);
    $pdf->Cell($boxWidth, 5, "Techniker: ".$user->getFullName($langs), 0, 1);
    $pdf->SetLineWidth(0.3);
    $pdf->Rect($leftMargin, $curY + 5, $boxWidth, $boxHeight);

    if ($techSignatureFile) {
        $pdf->Image($techSignatureFile, $leftMargin + 2, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
    }

    $signerName = '';
    $sqlSigner = "SELECT online_sign_name FROM ".MAIN_DB_PREFIX."fichinter WHERE rowid = ".(int)$fichinter->id;
    $resSigner = $db->query($sqlSigner);
    if ($resSigner && $objSigner = $db->fetch_object($resSigner)) {
        $signerName = $objSigner->online_sign_name;
    }

    $pdf->SetFont('', 'B', $default_font_size - 1);
    $pdf->SetXY($leftMargin + $boxWidth + 10, $curY);
    $customerLabel = "Auftraggeber:";
    if (!empty($signerName)) {
        $customerLabel .= " ".$signerName;
    }
    $pdf->Cell($boxWidth, 5, $customerLabel, 0, 1);
    $pdf->Rect($leftMargin + $boxWidth + 10, $curY + 5, $boxWidth, $boxHeight);

    if ($signatureFile && file_exists($signatureFile)) {
        $pdf->Image($signatureFile, $leftMargin + $boxWidth + 12, $curY + 7, $boxWidth - 4, $boxHeight - 6, '', '', '', false, 300, '', false, false, 0, 'CM');
    }

    $ortText = '';
    if ($objectAddress && !empty($objectAddress->town)) {
        $ortText = $objectAddress->town;
    } elseif ($fichinter->thirdparty && !empty($fichinter->thirdparty->town)) {
        $ortText = $fichinter->thirdparty->town;
    }
    $pdf->SetY($curY + $boxHeight + 6);
    $pdf->SetFont('', '', $default_font_size - 1);
    $pdf->Cell(0, 5, $ortText.", ".dol_print_date(dol_now(), 'day'), 0, 1);

    // Save PDF
    $pdfFilename = "Abnahmeprotokoll_".$fichinter->ref.".pdf";
    $docDir = $conf->ficheinter->dir_output.'/'.$fichinter->ref;
    if (!is_dir($docDir)) {
        dol_mkdir($docDir);
    }
    $pdfPath = $docDir.'/'.$pdfFilename;

    $pdfContent = $pdf->Output($pdfFilename, 'S');
    file_put_contents($pdfPath, $pdfContent);

    if (file_exists($pdfPath)) {
        // Clean up old PROV-named acceptance protocol files (left over from before intervention was validated)
        $oldFiles = glob($docDir.'/Abnahmeprotokoll_*.pdf');
        if (is_array($oldFiles)) {
            foreach ($oldFiles as $oldFile) {
                if (basename($oldFile) !== $pdfFilename) {
                    @unlink($oldFile);
                }
            }
        }
        return $pdfFilename;
    }
    return false;
}

/**
 * Process signature for intervention (shared logic for sync and direct signature endpoint)
 * Uses EquipmentManager PDF template with technician signature and adds customer signature
 *
 * @param int $intervention_id Intervention ID
 * @param string $signatureData Base64 encoded signature image
 * @param string $signerName Name of signer
 * @return array Result with success, signed_pdf, error keys
 */
function processSignature($intervention_id, $signatureData, $signerName) {
    global $db, $user, $conf, $langs;

    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

    // Load intervention
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($intervention_id) <= 0) {
        return ['success' => false, 'error' => 'Intervention not found'];
    }

    // Decode signature (may include data:image/png;base64, prefix)
    if (strpos($signatureData, 'base64,') !== false) {
        $signatureData = explode('base64,', $signatureData)[1];
    }
    $signatureImage = base64_decode($signatureData);

    if (!$signatureImage) {
        return ['success' => false, 'error' => 'Invalid signature data'];
    }

    // Get document directory - use Dolibarr's standard path
    $upload_dir = !empty($conf->ficheinter->multidir_output[$fichinter->entity])
        ? $conf->ficheinter->multidir_output[$fichinter->entity]
        : $conf->ficheinter->dir_output;
    $upload_dir .= '/' . dol_sanitizeFileName($fichinter->ref) . '/';

    // Create signatures directory
    $signatures_dir = $upload_dir . 'signatures/';

    // Ensure directories exist
    if (!is_dir($upload_dir)) {
        dol_mkdir($upload_dir);
    }
    if (!is_dir($signatures_dir)) {
        dol_mkdir($signatures_dir);
    }

    // Save signature file
    $date = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
    $filename = $date . '_signature.png';
    $filepath = $signatures_dir . $filename;

    if (file_put_contents($filepath, $signatureImage) === false) {
        return ['success' => false, 'error' => 'Failed to save signature file'];
    }

    $signerIp = getUserRemoteIP();
    $signedPdfFile = null;

    // Always regenerate the PDF using EquipmentManager template (includes technician signature)
    $modele = 'equipmentmanager';
    $fichinter->fetch_thirdparty();
    $fichinter->fetch_lines();
    $outputlangs = $langs;
    $outputlangs->loadLangs(array("main", "interventions", "companies", "equipmentmanager@equipmentmanager"));
    $fichinter->generateDocument($modele, $outputlangs);

    // Now create signed version with customer signature
    $sourcefile = $upload_dir . dol_sanitizeFileName($fichinter->ref) . ".pdf";
    $newpdffilename = $upload_dir . dol_sanitizeFileName($fichinter->ref) . "_signed.pdf";

    if (dol_is_file($sourcefile)) {
        try {
            // Build the new PDF with customer signature
            $pdf = pdf_getInstance();
            if (class_exists('TCPDF')) {
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
            }
            $pdf->SetFont(pdf_getPDFFont($langs));

            if (getDolGlobalString('MAIN_DISABLE_PDF_COMPRESSION')) {
                $pdf->SetCompression(false);
            }

            $pagecount = $pdf->setSourceFile($sourcefile);

            $s = array();
            for ($i = 1; $i < ($pagecount + 1); $i++) {
                $tppl = $pdf->importPage($i);
                $s = $pdf->getTemplatesize($tppl);
                $pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
                $pdf->useTemplate($tppl);
            }

            // Add customer signature on last page - RIGHT signature box
            // EquipmentManager PDF has signature boxes at FIXED position:
            // Y = page_height - 67mm (230mm on A4)
            // Left box (technician): X=10, Right box (customer): X=page_width - 10 - 80
            // Box dimensions: 80mm wide, 25mm tall, with 5mm label above
            $default_font_size = pdf_getPDFFontSize($langs);
            $default_font = pdf_getPDFFont($langs);

            // Page dimensions - must match EquipmentManager template
            $marge_droite = 10;
            $boxWidth = 80;
            $boxHeight = 25;
            $rightX = $s['w'] - $marge_droite - $boxWidth;

            // Fixed Y position matching template: page_height - 67mm
            $signatureBoxY = $s['h'] - 67;
            // The box starts 5mm below the label
            $boxStartY = $signatureBoxY + 5;

            // Customer signature image - fit within right box with padding
            $padding = 2;
            $sigMaxWidth = $boxWidth - (2 * $padding);
            $sigMaxHeight = $boxHeight - (2 * $padding);

            // Get image dimensions to maintain aspect ratio
            $imageInfo = getimagesize($filepath);
            if ($imageInfo !== false) {
                $imgWidth = $imageInfo[0];
                $imgHeight = $imageInfo[1];
                $aspectRatio = $imgWidth / $imgHeight;

                // Calculate dimensions to fit within the box
                if ($sigMaxWidth / $sigMaxHeight > $aspectRatio) {
                    $finalHeight = $sigMaxHeight;
                    $finalWidth = $sigMaxHeight * $aspectRatio;
                } else {
                    $finalWidth = $sigMaxWidth;
                    $finalHeight = $sigMaxWidth / $aspectRatio;
                }

                // Center the image within the right box
                $sigX = $rightX + ($boxWidth - $finalWidth) / 2;
                $sigY = $boxStartY + ($boxHeight - $finalHeight) / 2;

                // Insert the customer signature image
                $pdf->Image($filepath, $sigX, $sigY, $finalWidth, $finalHeight, 'PNG');
            }

            // Add signature text below the customer box
            $pdf->SetXY($rightX, $boxStartY + $boxHeight + 2);
            $pdf->SetFont($default_font, '', $default_font_size - 2);
            $pdf->SetTextColor(80, 80, 80);
            $signatureText = dol_print_date(dol_now(), "day", false, $langs, true);
            if ($signerName) {
                $signatureText .= ' - ' . $signerName;
            }
            $pdf->MultiCell($boxWidth, 4, $signatureText, 0, 'C');

            // Save the signed PDF
            $pdf->Output($newpdffilename, "F");

            // Index the new file
            $fichinter->indexFile($newpdffilename, 1);

            $signedPdfFile = basename($newpdffilename);
        } catch (Exception $e) {
            // Continue without signed PDF - signature PNG is still saved
        }
    }

    // Update intervention signed status
    $tmpUser = new User($db);
    $tmpUser->id = $user->id;

    // Use Fichinter's setSignedStatus if available
    if (method_exists($fichinter, 'setSignedStatus')) {
        $result = $fichinter->setSignedStatus($tmpUser, Fichinter::$SIGNED_STATUSES['STATUS_SIGNED_RECEIVER_ONLINE'], 0, 'FICHINTER_MODIFY');
        // Also update online_sign_name and online_sign_ip (setSignedStatus doesn't set these)
        $sqlSignerInfo = "UPDATE ".MAIN_DB_PREFIX."fichinter SET ";
        $sqlSignerInfo .= "online_sign_ip = '".$db->escape($signerIp)."',";
        $sqlSignerInfo .= "online_sign_name = '".$db->escape($signerName)."'";
        $sqlSignerInfo .= " WHERE rowid = ".(int)$intervention_id;
        $db->query($sqlSignerInfo);
    } else {
        // Fallback to direct SQL update
        $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET ";
        $sql .= "signed_status = 3,"; // STATUS_SIGNED_RECEIVER_ONLINE
        $sql .= "online_sign_ip = '".$db->escape($signerIp)."',";
        $sql .= "online_sign_name = '".$db->escape($signerName)."'";
        $sql .= " WHERE rowid = ".(int)$intervention_id;
        $result = $db->query($sql) ? 1 : -1;
    }

    if ($result >= 0) {
        // NOTE: Do NOT close the intervention after signature
        // The intervention stays at "validated/released" status (fk_statut = 1)
        // Closing (fk_statut = 3) should happen separately

        // Generate combined checklists PDF if there are completed checklists
        $checklistsPdfFile = null;
        try {
            dol_include_once('/equipmentmanager/class/pdf_checklist.class.php');
            $pdfChecklist = new pdf_checklist($db);
            $checklistFile = $pdfChecklist->write_combined_file($fichinter, $user, $langs, false);
            if ($checklistFile && $checklistFile !== false) {
                $checklistsPdfFile = basename($checklistFile);
            }
        } catch (Exception $e) {
            // Continue without checklists PDF
        }

        // Generate acceptance protocol PDF if there are service equipment
        $acceptanceProtocolFile = null;
        try {
            $acceptanceFile = generateAcceptanceProtocol($fichinter, $user);
            if ($acceptanceFile && $acceptanceFile !== false) {
                $acceptanceProtocolFile = $acceptanceFile;
            }
        } catch (Exception $e) {
            // Continue without acceptance protocol PDF
        }

        return [
            'success' => true,
            'signed_pdf' => $signedPdfFile,
            'signature_file' => $filename,
            'checklists_pdf' => $checklistsPdfFile,
            'acceptance_protocol' => $acceptanceProtocolFile
        ];
    } else {
        return [
            'success' => false,
            'error' => 'Failed to update intervention status',
            'signed_pdf' => $signedPdfFile
        ];
    }
}

/**
 * POST /pwa-token - Generate or refresh PWA authentication token
 * GET /pwa-token - Check if token is valid and get token info
 */
function handlePwaToken($method, $input) {
    global $db, $user;

    if ($method === 'POST') {
        // Generate new token for current user
        // Only works if user is authenticated via session
        if (!$user->id) {
            http_response_code(401);
            echo json_encode(['error' => 'Must be authenticated to generate PWA token']);
            return;
        }

        // Generate secure token
        $token = bin2hex(random_bytes(32)); // 64 character hex token
        $validUntil = time() + (90 * 24 * 3600); // 90 days validity

        // Store token in database
        // First check if table exists, create if not
        createPwaTokenTableIfNeeded($db);

        // Delete existing tokens for this user (one token per user)
        $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token WHERE fk_user = ".(int)$user->id;
        $db->query($sql);

        // Insert new token
        $sql = "INSERT INTO ".MAIN_DB_PREFIX."equipmentmanager_pwa_token";
        $sql .= " (fk_user, token, valid_until, date_creation, last_use)";
        $sql .= " VALUES (";
        $sql .= (int)$user->id.",";
        $sql .= "'".$db->escape(hash('sha256', $token))."',"; // Store hashed
        $sql .= "'".$db->idate($validUntil)."',";
        $sql .= "'".$db->idate(dol_now())."',";
        $sql .= "'".$db->idate(dol_now())."'";
        $sql .= ")";

        $resql = $db->query($sql);

        if ($resql) {
            echo json_encode([
                'status' => 'ok',
                'token' => $token, // Return plain token (client stores this)
                'valid_until' => $validUntil,
                'user_id' => (int)$user->id,
                'user_login' => $user->login,
                'user_name' => $user->getFullName($GLOBALS['langs'])
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create token: ' . $db->lasterror()]);
        }
    } elseif ($method === 'GET') {
        // Check token validity (user must be authenticated somehow)
        echo json_encode([
            'status' => 'ok',
            'authenticated' => true,
            'user_id' => (int)$user->id,
            'user_login' => $user->login
        ]);
    } elseif ($method === 'DELETE') {
        // Revoke current user's PWA token
        if ($user->id) {
            $sql = "DELETE FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token WHERE fk_user = ".(int)$user->id;
            $db->query($sql);
        }
        echo json_encode(['status' => 'ok', 'message' => 'Token revoked']);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Validate PWA token and set up user context
 */
function validatePwaToken($token, $db, &$user) {
    if (empty($token)) {
        return false;
    }

    // Hash the provided token for comparison
    $hashedToken = hash('sha256', $token);

    // Look up token in database
    $sql = "SELECT fk_user, valid_until FROM ".MAIN_DB_PREFIX."equipmentmanager_pwa_token";
    $sql .= " WHERE token = '".$db->escape($hashedToken)."'";
    $sql .= " AND valid_until > '".$db->idate(dol_now())."'";

    $resql = $db->query($sql);

    if ($resql && $db->num_rows($resql) > 0) {
        $obj = $db->fetch_object($resql);
        $userId = (int)$obj->fk_user;

        // Load the user
        require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
        $user = new User($db);
        $user->fetch($userId);

        if ($user->id > 0) {
            // Update last use timestamp
            $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_pwa_token";
            $sqlUpdate .= " SET last_use = '".$db->idate(dol_now())."'";
            $sqlUpdate .= " WHERE token = '".$db->escape($hashedToken)."'";
            $db->query($sqlUpdate);

            return true;
        }
    }

    return false;
}

/**
 * Create PWA token table if it doesn't exist
 */
function createPwaTokenTableIfNeeded($db) {
    $sql = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."equipmentmanager_pwa_token (";
    $sql .= "rowid INT AUTO_INCREMENT PRIMARY KEY,";
    $sql .= "fk_user INT NOT NULL,";
    $sql .= "token VARCHAR(64) NOT NULL,";
    $sql .= "valid_until DATETIME NOT NULL,";
    $sql .= "date_creation DATETIME NOT NULL,";
    $sql .= "last_use DATETIME,";
    $sql .= "UNIQUE KEY uk_token (token),";
    $sql .= "KEY idx_user (fk_user)";
    $sql .= ") ENGINE=InnoDB";

    $db->query($sql);
}

/**
 * GET /checklist/{intervention_id}/{equipment_id} - Get checklist for equipment
 * POST /checklist/{intervention_id}/{equipment_id} - Create/update checklist
 * POST /checklist/{intervention_id}/{equipment_id}/complete - Complete checklist
 * DELETE /checklist/{checklist_id} - Delete checklist
 */
function handleChecklist($method, $parts, $input) {
    global $db, $user, $langs;

    $intervention_id = (int)($parts[1] ?? 0);
    $equipment_id = (int)($parts[2] ?? 0);

    // Handle DELETE by checklist_id
    if ($method === 'DELETE' && $intervention_id > 0 && $equipment_id == 0) {
        $checklist_id = $intervention_id; // In DELETE, first param is checklist_id
        $checklist = new ChecklistResult($db);
        if ($checklist->fetch($checklist_id) > 0) {
            $result = $checklist->delete($user);
            if ($result > 0) {
                echo json_encode(['status' => 'ok', 'message' => 'Checklist deleted']);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to delete checklist']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Checklist not found']);
        }
        return;
    }

    if (!$intervention_id || !$equipment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID and Equipment ID required']);
        return;
    }

    // Get equipment intervention link ID
    $sql = "SELECT rowid FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link";
    $sql .= " WHERE fk_intervention = ".(int)$intervention_id;
    $sql .= " AND fk_equipment = ".(int)$equipment_id;
    $resql = $db->query($sql);
    $eq_inter_id = 0;
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $eq_inter_id = $obj->rowid;
    }

    if ($method === 'GET') {
        // Get existing checklist
        $checklist = new ChecklistResult($db);
        $hasChecklist = ($checklist->fetchByEquipmentIntervention($equipment_id, $eq_inter_id) > 0);

        if (!$hasChecklist) {
            // Return available template info
            $equipment = new Equipment($db);
            $equipment->fetch($equipment_id);

            $template = new ChecklistTemplate($db);
            $templates = [];

            // Equipment type mapping - some types share the same checklist template
            $type_mapping = array(
                'hold_open' => 'fire_door_fsa',  // Feststellanlage = FSA Template
            );
            $template_type = $equipment->equipment_type;
            if (isset($type_mapping[$template_type])) {
                $template_type = $type_mapping[$template_type];
            }

            // Check for standard template (or mapped template)
            if ($template->fetchByEquipmentType($template_type) > 0) {
                $template->fetchSectionsWithItems();
                $templates[] = formatTemplateForApi($template, $langs);
            }

            // For fire_door, also check FSA variant
            if ($equipment->equipment_type == 'fire_door') {
                $templateFsa = new ChecklistTemplate($db);
                if ($templateFsa->fetchByEquipmentType('fire_door_fsa') > 0) {
                    $templateFsa->fetchSectionsWithItems();
                    $templates[] = formatTemplateForApi($templateFsa, $langs);
                }
            }

            echo json_encode([
                'status' => 'ok',
                'has_checklist' => false,
                'available_templates' => $templates,
                'equipment_type' => $equipment->equipment_type
            ]);
            return;
        }

        // Load template and results
        $template = new ChecklistTemplate($db);
        $template->fetch($checklist->fk_template);
        $template->fetchSectionsWithItems();
        $checklist->fetchItemResults();

        echo json_encode([
            'status' => 'ok',
            'has_checklist' => true,
            'checklist' => [
                'id' => (int)$checklist->id,
                'ref' => $checklist->ref,
                'status' => (int)$checklist->status,
                'passed' => $checklist->passed,
                'work_date' => $checklist->work_date ? dol_print_date($checklist->work_date, 'dayrfc') : null,
                'date_completion' => $checklist->date_completion ? dol_print_date($checklist->date_completion, 'dayrfc') : null
            ],
            'template' => formatTemplateForApi($template, $langs),
            'results' => $checklist->item_results
        ]);

    } elseif ($method === 'POST') {
        // Check for complete action
        if (isset($parts[3]) && $parts[3] === 'complete') {
            $checklist_id = (int)($input['checklist_id'] ?? 0);
            $checklist = new ChecklistResult($db);

            if ($checklist->fetch($checklist_id) <= 0) {
                http_response_code(404);
                echo json_encode(['error' => 'Checklist not found']);
                return;
            }

            // Save all items first
            if (!empty($input['items'])) {
                foreach ($input['items'] as $item_id => $item_data) {
                    $answer = $item_data['answer'] ?? '';
                    $answer_text = $item_data['answer_text'] ?? '';
                    $note = $item_data['note'] ?? '';
                    $photo = $item_data['photo'] ?? '';
                    $checklist->saveItemResult($item_id, $answer, $answer_text, $note, $photo);
                }
            }

            // Complete checklist
            $result = $checklist->complete($user);

            if ($result > 0) {
                // Auto-generate PDF and link to intervention
                $pdf_generated = false;
                $pdf_error = '';

                try {
                    dol_include_once('/equipmentmanager/class/pdf_checklist.class.php');
                    dol_include_once('/fichinter/class/fichinter.class.php');

                    // Fetch equipment
                    $equipment_obj = new Equipment($db);
                    $equipment_obj->fetch($checklist->fk_equipment);

                    // Fetch template with sections
                    $template = new ChecklistTemplate($db);
                    $template->fetch($checklist->fk_template);
                    $template->fetchSectionsWithItems();

                    // Fetch intervention
                    $intervention = new Fichinter($db);
                    $intervention->fetch($checklist->fk_intervention);

                    // Generate PDF (not preview mode - save to documents)
                    $pdf = new pdf_checklist($db);
                    $pdf_result = $pdf->write_file($checklist, $equipment_obj, $template, $intervention, $user, $langs, false);

                    if ($pdf_result && $pdf_result !== 'preview') {
                        $pdf_generated = true;
                    } else {
                        $pdf_error = 'PDF generation returned no file';
                    }
                } catch (Exception $e) {
                    $pdf_error = $e->getMessage();
                }

                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Checklist completed',
                    'passed' => $checklist->passed,
                    'pdf_generated' => $pdf_generated,
                    'pdf_error' => $pdf_error
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to complete checklist']);
            }
            return;
        }

        // Create new checklist or update existing
        $checklist = new ChecklistResult($db);
        $isNew = ($checklist->fetchByEquipmentIntervention($equipment_id, $eq_inter_id) <= 0);

        if ($isNew) {
            // Create new checklist
            $template_type = $input['template_type'] ?? '';

            if (empty($template_type)) {
                // Get default template for equipment type
                $equipment = new Equipment($db);
                $equipment->fetch($equipment_id);
                $template_type = $equipment->equipment_type;
            }

            // Equipment type mapping - some types share the same checklist template
            $type_mapping = array(
                'hold_open' => 'fire_door_fsa',  // Feststellanlage = FSA Template
            );
            if (isset($type_mapping[$template_type])) {
                $template_type = $type_mapping[$template_type];
            }

            $template = new ChecklistTemplate($db);
            if ($template->fetchByEquipmentType($template_type) <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Template not found for type: '.$template_type]);
                return;
            }

            $checklist->fk_template = $template->id;
            $checklist->fk_equipment = $equipment_id;
            $checklist->fk_intervention = $intervention_id;
            $checklist->fk_equipment_intervention = $eq_inter_id;
            $checklist->status = 0;
            $checklist->work_date = dol_now();

            $result = $checklist->create($user);

            if ($result <= 0) {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create checklist']);
                return;
            }
        }

        // Save item results
        if (!empty($input['items'])) {
            foreach ($input['items'] as $item_id => $item_data) {
                $answer = $item_data['answer'] ?? '';
                $answer_text = $item_data['answer_text'] ?? '';
                $note = $item_data['note'] ?? '';
                $photo = $item_data['photo'] ?? '';
                $checklist->saveItemResult($item_id, $answer, $answer_text, $note, $photo);
            }
        }

        echo json_encode([
            'status' => 'ok',
            'checklist_id' => (int)$checklist->id,
            'message' => $isNew ? 'Checklist created' : 'Checklist updated'
        ]);
    }
}

/**
 * Format template data for API response
 */
function formatTemplateForApi($template, $langs) {
    $sections = [];

    foreach ($template->sections as $section) {
        $items = [];
        foreach ($section->items as $item) {
            $items[] = [
                'id' => (int)$item->id,
                'code' => $item->code,
                'label' => html_entity_decode($langs->trans($item->label), ENT_QUOTES, 'UTF-8'),
                'label_key' => $item->label,
                'answer_type' => $item->answer_type,
                'required' => (int)$item->required
            ];
        }

        $sections[] = [
            'id' => (int)$section->id,
            'code' => $section->code,
            'label' => html_entity_decode($langs->trans($section->label), ENT_QUOTES, 'UTF-8'),
            'label_key' => $section->label,
            'items' => $items
        ];
    }

    return [
        'id' => (int)$template->id,
        'equipment_type_code' => $template->equipment_type_code,
        'label' => html_entity_decode($langs->trans($template->label), ENT_QUOTES, 'UTF-8'),
        'label_key' => $template->label,
        'norm_reference' => $template->norm_reference,
        'sections' => $sections
    ];
}

/**
 * GET /equipment/{id} - Get equipment details
 * PUT /equipment/{id} - Update equipment
 */
function handleEquipment($method, $parts, $input) {
    global $db, $user, $langs;

    $equipment_id = (int)($parts[1] ?? 0);

    if (!$equipment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Equipment ID required']);
        return;
    }

    $equipment = new Equipment($db);
    if ($equipment->fetch($equipment_id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Equipment not found']);
        return;
    }

    if ($method === 'GET') {
        // Return equipment details
        // Get equipment type labels
        $type_labels = Equipment::getEquipmentTypesTranslated($db, $langs);

        echo json_encode([
            'status' => 'ok',
            'equipment' => [
                'id' => (int)$equipment->id,
                'ref' => $equipment->equipment_number,
                'label' => $equipment->label,
                'type' => $equipment->equipment_type,
                'type_label' => $type_labels[$equipment->equipment_type] ?? $equipment->equipment_type,
                'manufacturer' => $equipment->manufacturer ?: '',
                'serial_number' => $equipment->serial_number,
                'location' => $equipment->location_note ?: '',
                'door_wings' => $equipment->door_wings ?: '',
                'fk_soc' => (int)$equipment->fk_soc,
                'fk_address' => (int)$equipment->fk_address,
                'battery_install_month' => $equipment->battery_install_month !== null ? (int)$equipment->battery_install_month : null,
                'battery_install_year' => $equipment->battery_install_year !== null ? (int)$equipment->battery_install_year : null,
                'battery_replacement_cycle' => $equipment->battery_replacement_cycle !== null ? (int)$equipment->battery_replacement_cycle : null,
                'fire_protection' => $equipment->fire_protection !== null ? (int)$equipment->fire_protection : null,
                'smoke_detector_install_month' => $equipment->smoke_detector_install_month !== null ? (int)$equipment->smoke_detector_install_month : null,
                'smoke_detector_install_year' => $equipment->smoke_detector_install_year !== null ? (int)$equipment->smoke_detector_install_year : null,
                'smoke_detector_replacement_cycle' => $equipment->smoke_detector_replacement_cycle !== null ? (int)$equipment->smoke_detector_replacement_cycle : null
            ]
        ]);

    } elseif ($method === 'PUT' || $method === 'POST') {
        // Update equipment - only specific fields allowed from PWA
        $allowed_fields = ['label', 'location_note', 'equipment_type', 'manufacturer', 'door_wings', 'serial_number',
            'battery_install_month', 'battery_install_year', 'battery_replacement_cycle',
            'fire_protection',
            'smoke_detector_install_month', 'smoke_detector_install_year', 'smoke_detector_replacement_cycle'];

        foreach ($allowed_fields as $field) {
            if (array_key_exists($field, $input)) {
                $val = $input[$field];
                if (in_array($field, ['label', 'location_note', 'equipment_type', 'manufacturer', 'door_wings', 'serial_number'])) {
                    $equipment->$field = $val;
                } else {
                    // Integer or null fields
                    $equipment->$field = ($val !== null && $val !== '') ? (int)$val : null;
                }
            }
        }

        $result = $equipment->update($user);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Equipment updated',
                'equipment_id' => (int)$equipment->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to update equipment', 'details' => $equipment->error]);
        }

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle defect photo upload for checklist items
 * POST /defect-photo/{checklist_id}/{item_id} - Upload photo
 * GET /defect-photo/{checklist_id}/{item_id} - Get photo info
 * GET /defect-photo/{checklist_id}/file/{filename} - Serve photo file directly
 * DELETE /defect-photo/{checklist_id}/{item_id} - Delete photo
 */
function handleDefectPhoto($method, $parts, $input) {
    global $db, $user, $conf;

    $checklist_id = (int)($parts[1] ?? 0);

    // Check for file serving mode: /defect-photo/{checklist_id}/file/{filename}
    if (($parts[2] ?? '') === 'file' && $method === 'GET') {
        $filename = $parts[3] ?? '';
        if (!$checklist_id || !$filename) {
            http_response_code(400);
            echo json_encode(['error' => 'Checklist ID and filename required']);
            return;
        }

        // Sanitize filename
        $filename = basename($filename);

        // Get checklist to find intervention
        $checklist = new ChecklistResult($db);
        if ($checklist->fetch($checklist_id) <= 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Checklist not found']);
            return;
        }

        // Get intervention ref for folder path
        dol_include_once('/fichinter/class/fichinter.class.php');
        $fichinter = new Fichinter($db);

        $sql = "SELECT fk_intervention FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link WHERE rowid = ".(int)$checklist->fk_equipment_intervention;
        $resql = $db->query($sql);
        $intervention_id = 0;
        if ($resql && $db->num_rows($resql)) {
            $obj = $db->fetch_object($resql);
            $intervention_id = $obj->fk_intervention;
        }

        if ($intervention_id > 0) {
            $fichinter->fetch($intervention_id);
        }

        $docDir = $conf->ficheinter->dir_output . '/' . dol_sanitizeFileName($fichinter->ref) . '/defect_photos';
        $filepath = $docDir . '/' . $filename;

        if (file_exists($filepath)) {
            // Serve file directly
            $mimeType = mime_content_type($filepath);
            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: public, max-age=3600');
            readfile($filepath);
            exit;
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
        }
        return;
    }

    $item_id = (int)($parts[2] ?? 0);

    if (!$checklist_id || !$item_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Checklist ID and Item ID required']);
        return;
    }

    // Get checklist to verify it exists and get intervention info
    $checklist = new ChecklistResult($db);
    if ($checklist->fetch($checklist_id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Checklist not found']);
        return;
    }

    // Get intervention ref for folder path
    dol_include_once('/fichinter/class/fichinter.class.php');
    $fichinter = new Fichinter($db);

    // Get intervention ID from equipment link
    $sql = "SELECT fk_intervention FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link WHERE rowid = ".(int)$checklist->fk_equipment_intervention;
    $resql = $db->query($sql);
    $intervention_id = 0;
    if ($resql && $db->num_rows($resql)) {
        $obj = $db->fetch_object($resql);
        $intervention_id = $obj->fk_intervention;
    }

    if ($intervention_id > 0) {
        $fichinter->fetch($intervention_id);
    }

    // Photo directory: ficheinter/{ref}/defect_photos/
    $docDir = $conf->ficheinter->dir_output . '/' . dol_sanitizeFileName($fichinter->ref) . '/defect_photos';

    if ($method === 'POST') {
        // Ensure directory exists
        if (!is_dir($docDir)) {
            dol_mkdir($docDir);
        }

        // Check for base64 image data
        if (!empty($input['image'])) {
            $imageData = $input['image'];

            // Remove data URL prefix if present
            if (strpos($imageData, 'base64,') !== false) {
                $imageData = explode('base64,', $imageData)[1];
            }

            $decoded = base64_decode($imageData);
            if (!$decoded) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid image data']);
                return;
            }

            // Detect image type
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->buffer($decoded);
            $extension = 'jpg';
            if ($mimeType === 'image/png') $extension = 'png';
            elseif ($mimeType === 'image/gif') $extension = 'gif';

            // Generate unique filename: defect_{checklist_id}_{item_id}_{timestamp}.jpg
            $timestamp = dol_print_date(dol_now(), "%Y%m%d%H%M%S");
            $filename = "defect_{$checklist_id}_{$item_id}_{$timestamp}.{$extension}";
            $filepath = $docDir . '/' . $filename;

            // Delete old photo if exists
            $pattern = $docDir . "/defect_{$checklist_id}_{$item_id}_*";
            foreach (glob($pattern) as $oldFile) {
                @unlink($oldFile);
            }

            // Save new photo
            if (file_put_contents($filepath, $decoded)) {
                // Update database with photo filename
                $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
                $sql .= " SET photo = '".$db->escape($filename)."'";
                $sql .= " WHERE fk_checklist_result = ".(int)$checklist_id;
                $sql .= " AND fk_checklist_item = ".(int)$item_id;
                $db->query($sql);

                echo json_encode([
                    'status' => 'ok',
                    'filename' => $filename,
                    'message' => 'Photo uploaded'
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to save photo']);
            }
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'No image data received']);

    } elseif ($method === 'GET') {
        // Get photo info
        $sql = "SELECT photo FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " WHERE fk_checklist_result = ".(int)$checklist_id;
        $sql .= " AND fk_checklist_item = ".(int)$item_id;
        $resql = $db->query($sql);

        if ($resql && $db->num_rows($resql)) {
            $obj = $db->fetch_object($resql);
            if (!empty($obj->photo)) {
                $filepath = $docDir . '/' . $obj->photo;
                if (file_exists($filepath)) {
                    echo json_encode([
                        'status' => 'ok',
                        'filename' => $obj->photo,
                        'url' => DOL_URL_ROOT . '/document.php?modulepart=ficheinter&file=' . urlencode(dol_sanitizeFileName($fichinter->ref) . '/defect_photos/' . $obj->photo)
                    ]);
                    return;
                }
            }
        }

        echo json_encode([
            'status' => 'ok',
            'filename' => null,
            'url' => null
        ]);

    } elseif ($method === 'DELETE') {
        // Delete photo
        $sql = "SELECT photo FROM ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " WHERE fk_checklist_result = ".(int)$checklist_id;
        $sql .= " AND fk_checklist_item = ".(int)$item_id;
        $resql = $db->query($sql);

        if ($resql && $db->num_rows($resql)) {
            $obj = $db->fetch_object($resql);
            if (!empty($obj->photo)) {
                $filepath = $docDir . '/' . $obj->photo;
                if (file_exists($filepath)) {
                    @unlink($filepath);
                }
            }
        }

        // Clear from database
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_checklist_item_results";
        $sql .= " SET photo = NULL";
        $sql .= " WHERE fk_checklist_result = ".(int)$checklist_id;
        $sql .= " AND fk_checklist_item = ".(int)$item_id;
        $db->query($sql);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Photo deleted'
        ]);

    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}

/**
 * Handle entry photo upload and serving
 * POST /entry-photo/{intervention_id}/{entry_id} - Upload photo for entry
 * GET /entry-photo/{intervention_id}/file/{filename} - Serve photo file directly
 */
function handleEntryPhoto($method, $parts, $input) {
    global $db, $conf, $user;

    $intervention_id = (int)($parts[1] ?? 0);

    // POST: Upload photo for entry - /entry-photo/{intervention_id}/{entry_id}
    if ($method === 'POST') {
        $entry_id = (int)($parts[2] ?? 0);

        if (!$intervention_id || !$entry_id) {
            http_response_code(400);
            echo json_encode(['error' => 'Intervention ID and Entry ID required']);
            return;
        }

        // Check for base64 image data
        if (empty($input['image'])) {
            http_response_code(400);
            echo json_encode(['error' => 'No image data provided']);
            return;
        }

        // Get intervention ref for folder path
        dol_include_once('/fichinter/class/fichinter.class.php');
        $fichinter = new Fichinter($db);
        if ($fichinter->fetch($intervention_id) <= 0) {
            http_response_code(404);
            echo json_encode(['error' => 'Intervention not found']);
            return;
        }

        // Create directory if needed
        $docDir = $conf->ficheinter->dir_output . '/' . dol_sanitizeFileName($fichinter->ref) . '';
        if (!is_dir($docDir)) {
            dol_mkdir($docDir);
        }

        // Decode base64 image
        $imageData = $input['image'];
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = preg_replace('#^data:image/\w+;base64,#i', '', $imageData);
        }
        $imageData = base64_decode($imageData);

        if ($imageData === false) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid base64 image data']);
            return;
        }

        // Load entry to check for old photo and get equipment
        dol_include_once('/custom/equipmentmanager/class/interventiondetail.class.php');
        dol_include_once('/custom/equipmentmanager/class/equipment.class.php');
        $entry = new InterventionDetail($db);
        $entryLoaded = ($entry->fetch($entry_id) > 0);

        // Delete old photo if exists
        if ($entryLoaded && !empty($entry->photo)) {
            $oldPath = $docDir . '/' . $entry->photo;
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }
        }

        // Get equipment number for filename
        $equipmentNumber = 'unknown';
        if ($entryLoaded && !empty($entry->fk_equipment)) {
            $equipment = new Equipment($db);
            if ($equipment->fetch($entry->fk_equipment) > 0) {
                $equipmentNumber = $equipment->equipment_number ?: $equipment->id;
            }
        }

        // Generate filename: {Intervention_ref}_{Equipment_number}_{date}.jpg
        $timestamp = dol_print_date(dol_now(), '%Y%m%d_%H%M%S');
        $filename = dol_sanitizeFileName($fichinter->ref) . '_' . dol_sanitizeFileName($equipmentNumber) . '_' . $timestamp . '.jpg';
        $filepath = $docDir . '/' . $filename;

        // Save new photo
        if (file_put_contents($filepath, $imageData) === false) {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save image']);
            return;
        }

        // Update entry with photo filename
        $sql = "UPDATE ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail";
        $sql .= " SET photo = '".$db->escape($filename)."'";
        $sql .= " WHERE rowid = ".$entry_id;
        $db->query($sql);

        // Add as linked document to the intervention
        require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
        addFileIntoDatabaseIndex($docDir, $filename, $filepath, 'uploaded', 0, $fichinter);

        echo json_encode([
            'success' => true,
            'photo' => $filename
        ]);
        return;
    }

    // GET: Only support file serving: /entry-photo/{intervention_id}/file/{filename}
    if (($parts[2] ?? '') !== 'file' || $method !== 'GET') {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid request']);
        return;
    }

    $filename = $parts[3] ?? '';
    if (!$intervention_id || !$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID and filename required']);
        return;
    }

    // Sanitize filename
    $filename = basename($filename);

    // Get intervention ref for folder path
    dol_include_once('/fichinter/class/fichinter.class.php');
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($intervention_id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }

    $docDir = $conf->ficheinter->dir_output . '/' . dol_sanitizeFileName($fichinter->ref) . '';
    $filepath = $docDir . '/' . $filename;

    if (file_exists($filepath)) {
        // Serve file directly
        $mimeType = mime_content_type($filepath);
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: public, max-age=3600');
        readfile($filepath);
        exit;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
    }
}

/**
 * Handle defect materials
 * GET /defect-material/{entry_id} - List materials for an entry
 * POST /defect-material/{entry_id} - Add material to entry (product OR freetext)
 * DELETE /defect-material/{material_id} - Delete material
 */
function handleDefectMaterial($method, $parts, $input) {
    global $db, $user;

    $id = (int)($parts[1] ?? 0);

    // GET - List materials for entry
    if ($method === 'GET' && $id > 0) {
        $materials = DefectMaterial::fetchAllForEntry($db, $id);
        $result = array();
        foreach ($materials as $mat) {
            $result[] = array(
                'id' => $mat->id,
                'fk_product' => $mat->fk_product ?: 0,
                'freetext_label' => $mat->freetext_label ?: '',
                'product_ref' => $mat->product_ref,
                'product_label' => $mat->product_label,
                'qty' => intval($mat->qty)
            );
        }
        echo json_encode($result);
        return;
    }

    // POST - Add material (product OR freetext)
    if ($method === 'POST' && $id > 0) {
        $fk_product = (int)($input['fk_product'] ?? 0);
        $freetext_label = trim($input['freetext_label'] ?? '');
        $qty = (int)($input['qty'] ?? 1);

        // v4.3: Either product ID or freetext required
        if ($fk_product <= 0 && empty($freetext_label)) {
            http_response_code(400);
            echo json_encode(['error' => 'Product ID or freetext label required']);
            return;
        }

        $mat = new DefectMaterial($db);
        $mat->fk_intervention_detail = $id;
        $mat->fk_product = $fk_product > 0 ? $fk_product : 0;
        $mat->freetext_label = $freetext_label;
        $mat->qty = $qty;
        $result = $mat->create($user);

        if ($result > 0) {
            // Reload to get product info
            $mat->fetch($result);
            echo json_encode([
                'id' => $mat->id,
                'fk_product' => $mat->fk_product ?: 0,
                'freetext_label' => $mat->freetext_label ?: '',
                'product_ref' => $mat->product_ref,
                'product_label' => $mat->product_label,
                'qty' => intval($mat->qty)
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to add material']);
        }
        return;
    }

    // DELETE - Remove material
    if ($method === 'DELETE' && $id > 0) {
        $mat = new DefectMaterial($db);
        if ($mat->fetch($id) > 0) {
            $mat->delete($user);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Material not found']);
        }
        return;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
}

/**
 * GET /maintenance-overview - Equipment grouped by object address with maintenance status
 */
function handleMaintenanceOverview($method, $parts, $input) {
    global $db, $langs;

    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    // Fetch active equipment with active contract, address + maintenance info
    // maint_status based on maintenance_month — same logic as backend maintenance dashboard
    $sql  = "SELECT e.rowid as equipment_id, e.equipment_number, e.label, e.equipment_type,";
    $sql .= " e.maintenance_month, e.last_maintenance_date, e.fk_soc, e.fk_address,";
    $sql .= " s.nom as customer_name, s.address as cust_addr, s.zip as cust_zip, s.town as cust_town,";
    $sql .= " sp.lastname, sp.firstname, sp.address as addr_street, sp.zip as addr_zip, sp.town as addr_town,";
    $sql .= " CASE";
    $sql .= "  WHEN e.maintenance_month IS NULL THEN 'none'";
    // Done check 1: last_maintenance_date in current year near maintenance_month
    // Done check 2: closed maintenance intervention (fk_statut=3) in current year near maintenance_month
    $sql .= "  WHEN (";
    $sql .= "   (e.last_maintenance_date IS NOT NULL";
    $sql .= "    AND YEAR(e.last_maintenance_date) = YEAR(CURDATE())";
    $sql .= "    AND (MONTH(e.last_maintenance_date) >= e.maintenance_month - 1";
    $sql .= "     OR (e.maintenance_month = 1 AND MONTH(e.last_maintenance_date) = 12)))";
    $sql .= "   OR EXISTS (";
    $sql .= "    SELECT 1 FROM ".MAIN_DB_PREFIX."fichinter f3";
    $sql .= "    JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il3 ON il3.fk_intervention = f3.rowid";
    $sql .= "    WHERE il3.fk_equipment = e.rowid AND il3.link_type = 'maintenance'";
    $sql .= "    AND f3.fk_statut = 3";
    $sql .= "    AND YEAR(f3.date_valid) = YEAR(CURDATE())";
    $sql .= "    AND (MONTH(f3.date_valid) = e.maintenance_month";
    $sql .= "     OR MONTH(f3.date_valid) = e.maintenance_month - 1";
    $sql .= "     OR (e.maintenance_month = 1 AND MONTH(f3.date_valid) = 12))";
    $sql .= "   )";
    $sql .= "  ) THEN 'done'";
    $sql .= "  WHEN e.maintenance_month < MONTH(CURDATE()) THEN 'overdue'";
    $sql .= "  WHEN e.maintenance_month = MONTH(CURDATE()) THEN 'due'";
    $sql .= "  WHEN e.maintenance_month = MONTH(DATE_ADD(CURDATE(), INTERVAL 1 MONTH)) THEN 'soon'";
    $sql .= "  ELSE 'future'";
    $sql .= " END as maint_status,";
    // Find open maintenance intervention for this equipment
    $sql .= " (SELECT f.rowid FROM ".MAIN_DB_PREFIX."fichinter f";
    $sql .= "  JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il ON il.fk_intervention = f.rowid";
    $sql .= "  WHERE il.fk_equipment = e.rowid AND il.link_type = 'maintenance' AND f.fk_statut IN (0,1)";
    $sql .= "  ORDER BY f.dateo DESC LIMIT 1) as open_intervention_id,";
    $sql .= " (SELECT f.ref FROM ".MAIN_DB_PREFIX."fichinter f";
    $sql .= "  JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_link il ON il.fk_intervention = f.rowid";
    $sql .= "  WHERE il.fk_equipment = e.rowid AND il.link_type = 'maintenance' AND f.fk_statut IN (0,1)";
    $sql .= "  ORDER BY f.dateo DESC LIMIT 1) as open_intervention_ref";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_equipment e";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."societe s ON s.rowid = e.fk_soc";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " INNER JOIN ".MAIN_DB_PREFIX."contrat c ON c.rowid = e.fk_contract AND c.statut = 1";
    $sql .= " WHERE e.status = 1 AND e.fk_contract IS NOT NULL";
    $sql .= " ORDER BY maint_status = 'none', maint_status = 'done',";
    $sql .= "  CASE maint_status WHEN 'overdue' THEN 1 WHEN 'due' THEN 2 WHEN 'soon' THEN 3 WHEN 'future' THEN 4 WHEN 'none' THEN 5 ELSE 6 END,";
    $sql .= "  e.maintenance_month ASC";

    $resql = $db->query($sql);
    if (!$resql) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error: ' . $db->lasterror()]);
        return;
    }

    // Group equipment by object address (fk_address) or fallback to customer (fk_soc)
    $groups = [];
    $groupOrder = []; // preserve insertion order with worst status first

    $statusRank = ['overdue' => 1, 'due' => 2, 'soon' => 3, 'future' => 4, 'none' => 5, 'done' => 6];

    while ($obj = $db->fetch_object($resql)) {
        // Build group key and label
        if ($obj->fk_address) {
            $groupKey = 'addr_' . (int)$obj->fk_address;
            $contactName = trim($obj->lastname . ' ' . $obj->firstname);
            $addrLine = trim(($obj->addr_zip ?: '') . ' ' . ($obj->addr_town ?: ''));
            $groupLabel = $contactName ?: $obj->customer_name;
            if ($addrLine) $groupLabel .= ' — ' . $addrLine;
            $groupAddress = trim(($obj->addr_street ?: '') . ', ' . ($obj->addr_zip ?: '') . ' ' . ($obj->addr_town ?: ''), ', ');
        } else {
            $groupKey = 'soc_' . (int)$obj->fk_soc;
            $groupLabel = $obj->customer_name;
            $addrLine = trim(($obj->cust_zip ?: '') . ' ' . ($obj->cust_town ?: ''));
            if ($addrLine) $groupLabel .= ' — ' . $addrLine;
            $groupAddress = trim(($obj->cust_addr ?: '') . ', ' . ($obj->cust_zip ?: '') . ' ' . ($obj->cust_town ?: ''), ', ');
        }

        if (!isset($groups[$groupKey])) {
            $groups[$groupKey] = [
                'key'       => $groupKey,
                'label'     => $groupLabel,
                'address'   => $groupAddress,
                'worst_status' => 'none',
                'equipment' => []
            ];
            $groupOrder[] = $groupKey;
        }

        // Update worst status for group
        $cur = $statusRank[$groups[$groupKey]['worst_status']] ?? 4;
        $new = $statusRank[$obj->maint_status] ?? 4;
        if ($new < $cur) {
            $groups[$groupKey]['worst_status'] = $obj->maint_status;
        }

        $groups[$groupKey]['equipment'][] = [
            'id'                    => (int)$obj->equipment_id,
            'ref'                   => $obj->equipment_number,
            'label'                 => $obj->label,
            'type'                  => $obj->equipment_type,
            'maint_status'          => $obj->maint_status,
            'maintenance_month'     => $obj->maintenance_month ? (int)$obj->maintenance_month : null,
            'open_intervention_id'  => $obj->open_intervention_id ? (int)$obj->open_intervention_id : null,
            'open_intervention_ref' => $obj->open_intervention_ref ?: null
        ];
    }

    // Sort groups by worst status
    usort($groupOrder, function($a, $b) use ($groups, $statusRank) {
        $ra = $statusRank[$groups[$a]['worst_status']] ?? 4;
        $rb = $statusRank[$groups[$b]['worst_status']] ?? 4;
        return $ra - $rb;
    });

    $result = [];
    foreach ($groupOrder as $key) {
        $result[] = $groups[$key];
    }

    echo json_encode(['status' => 'ok', 'groups' => $result]);
}
