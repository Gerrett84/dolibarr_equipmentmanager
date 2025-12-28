<?php
/**
 * Equipment Manager PWA API
 * REST-like endpoints for offline sync
 */

// Disable CSRF check for API endpoints
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', '1');
if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', '1');
if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', '1');
if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', '1');
if (!defined('NOCSRFCHECK')) define('NOCSRFCHECK', '1');

// Prevent direct browser access without proper headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Token');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Dolibarr environment
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
dol_include_once('/equipmentmanager/class/equipment.class.php');
dol_include_once('/equipmentmanager/class/interventiondetail.class.php');
dol_include_once('/equipmentmanager/class/interventionmaterial.class.php');

// Check authentication
if (!$user->id) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

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

    // Debug: Check user entity
    $userEntity = (int)$user->entity;

    // Get interventions - removed strict entity filter for debugging
    $sql = "SELECT f.rowid, f.ref, f.datec, f.dateo, f.datee, f.duree, f.fk_statut as status,";
    $sql .= " f.description, f.note_public, f.note_private, f.entity as fichinter_entity,";
    $sql .= " f.signed_status,";
    $sql .= " s.rowid as socid, s.nom as customer_name, s.address, s.zip, s.town";
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
        'debug' => [
            'user_entity' => $userEntity,
            'sql' => $sql
        ],
        'interventions' => $interventions
    ]);
}

/**
 * GET /intervention/{id} - Get single intervention with equipment
 * GET /intervention/{id}/equipment - Get equipment list
 */
function handleIntervention($method, $parts, $input) {
    global $db, $user;

    // Accept both GET and POST for read operations (Dolibarr may convert GET to POST)
    if ($method !== 'GET' && $method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
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

    // If requesting equipment list
    if (isset($parts[2]) && $parts[2] === 'equipment') {
        $result = getInterventionEquipment($id, true); // with debug
        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'equipment' => $result['equipment'],
            'debug' => $result['debug']
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

        // Set signed_status = 1 (released for signature) but keep status as validated (1)
        // This marks the intervention as ready for signature without closing it
        $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 1 WHERE rowid = ".(int)$id;
        $resql = $db->query($sql);

        if ($resql) {
            // Generate PDF
            $pdfGenerated = generateInterventionPDF($fichinter, $user);

            // Get document path for debug
            $docPath = getFichinterDocDir() . '/' . $fichinter->ref;

            echo json_encode([
                'status' => 'ok',
                'message' => 'Intervention released for signature',
                'signed_status' => 1,
                'intervention_status' => (int)$fichinter->statut,
                'pdf_generated' => $pdfGenerated,
                'doc_path' => $docPath,
                'doc_exists' => is_dir($docPath),
                'dol_data_root' => defined('DOL_DATA_ROOT') ? DOL_DATA_ROOT : 'not defined'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to release intervention',
                'details' => $db->lasterror()
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

        // Set signed_status = 0 to allow editing again
        $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET signed_status = 0 WHERE rowid = ".(int)$id;
        $resql = $db->query($sql);

        if ($resql) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Intervention reopened for editing',
                'signed_status' => 0,
                'intervention_status' => (int)$fichinter->statut
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to unreleased intervention',
                'details' => $db->lasterror()
            ]);
        }
        return;
    }

    // Get documents/PDFs for intervention
    if (isset($parts[2]) && $parts[2] === 'documents') {
        $docPath = getFichinterDocDir() . '/' . $fichinter->ref;
        $documents = getInterventionDocuments($fichinter);
        echo json_encode([
            'status' => 'ok',
            'intervention_id' => $id,
            'intervention_ref' => $fichinter->ref,
            'doc_path' => $docPath,
            'doc_dir_exists' => is_dir($docPath),
            'dol_data_root' => defined('DOL_DATA_ROOT') ? DOL_DATA_ROOT : 'not defined',
            'documents' => $documents
        ]);
        return;
    }

    // Return full intervention with equipment
    $equipment = getInterventionEquipment($id);

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
            'signed_status' => (int)$fichinter->signed_status
        ],
        'equipment' => $equipment
    ]);
}

/**
 * Get equipment linked to intervention
 */
function getInterventionEquipment($intervention_id, $withDebug = false) {
    global $db;

    $sql = "SELECT e.rowid, e.equipment_number, e.label, e.equipment_type, e.serial_number,";
    $sql .= " e.location_note, e.manufacturer,";
    $sql .= " l.link_type,";
    $sql .= " d.rowid as detail_id, d.work_done, d.issues_found, d.recommendations,";
    $sql .= " d.notes, d.work_date, d.work_duration";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " LEFT JOIN ".MAIN_DB_PREFIX."equipmentmanager_intervention_detail d";
    $sql .= "   ON d.fk_intervention = l.fk_intervention AND d.fk_equipment = l.fk_equipment";
    $sql .= " WHERE l.fk_intervention = ".(int)$intervention_id;
    $sql .= " ORDER BY e.equipment_number";

    $debug = ['sql' => $sql];

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
                    'work_duration' => (int)$obj->work_duration
                ];
            }

            // Get materials for this equipment
            $eq['materials'] = getEquipmentMaterials($intervention_id, $obj->rowid);

            $equipment[] = $eq;
        }
        $debug['num_rows'] = $db->num_rows($resql);
    } else {
        $debug['error'] = $db->lasterror();
    }

    if ($withDebug) {
        return ['equipment' => $equipment, 'debug' => $debug];
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
 */
function getInterventionObjectAddresses($intervention_id) {
    global $db;

    $sql = "SELECT DISTINCT sp.rowid, sp.lastname, sp.firstname, sp.address, sp.zip, sp.town";
    $sql .= " FROM ".MAIN_DB_PREFIX."equipmentmanager_intervention_link l";
    $sql .= " JOIN ".MAIN_DB_PREFIX."equipmentmanager_equipment e ON e.rowid = l.fk_equipment";
    $sql .= " JOIN ".MAIN_DB_PREFIX."socpeople sp ON sp.rowid = e.fk_address";
    $sql .= " WHERE l.fk_intervention = ".(int)$intervention_id;
    $sql .= " AND e.fk_address > 0";

    $resql = $db->query($sql);
    $addresses = [];

    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $name = trim($obj->lastname . ' ' . $obj->firstname);
            $addresses[] = [
                'id' => (int)$obj->rowid,
                'name' => $name,
                'address' => $obj->address,
                'zip' => $obj->zip,
                'town' => $obj->town
            ];
        }
    }

    return $addresses;
}

/**
 * GET/POST /detail/{intervention_id}/{equipment_id}
 */
function handleDetail($method, $parts, $input) {
    global $db, $user;

    $intervention_id = (int)($parts[1] ?? 0);
    $equipment_id = (int)($parts[2] ?? 0);

    if (!$intervention_id || !$equipment_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Intervention ID and Equipment ID required']);
        return;
    }

    $detail = new InterventionDetail($db);

    if ($method === 'GET') {
        $result = $detail->fetchByInterventionEquipment($intervention_id, $equipment_id);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'detail' => [
                    'id' => (int)$detail->id,
                    'intervention_id' => (int)$detail->fk_intervention,
                    'equipment_id' => (int)$detail->fk_equipment,
                    'work_done' => $detail->work_done,
                    'issues_found' => $detail->issues_found,
                    'recommendations' => $detail->recommendations,
                    'notes' => $detail->notes,
                    'work_date' => $detail->work_date ? dol_print_date($detail->work_date, 'dayrfc') : null,
                    'work_duration' => (int)$detail->work_duration
                ]
            ]);
        } else {
            echo json_encode([
                'status' => 'ok',
                'detail' => null
            ]);
        }
    } elseif ($method === 'POST' || $method === 'PUT') {
        // Save detail
        $detail->fk_intervention = $intervention_id;
        $detail->fk_equipment = $equipment_id;
        $detail->work_done = $input['work_done'] ?? '';
        $detail->issues_found = $input['issues_found'] ?? '';
        $detail->recommendations = $input['recommendations'] ?? '';
        $detail->notes = $input['notes'] ?? '';
        $detail->work_date = !empty($input['work_date']) ? strtotime($input['work_date']) : null;
        $detail->work_duration = (int)($input['work_duration'] ?? 0);

        $result = $detail->createOrUpdate($user);

        if ($result > 0) {
            echo json_encode([
                'status' => 'ok',
                'message' => 'Detail saved',
                'id' => (int)$detail->id
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'error' => 'Failed to save detail',
                'details' => $detail->errors
            ]);
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
 * POST /signature/{intervention_id} - Upload signature
 */
function handleSignature($method, $parts, $input) {
    global $db, $user, $conf;

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

    // Load intervention
    $fichinter = new Fichinter($db);
    if ($fichinter->fetch($intervention_id) <= 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Intervention not found']);
        return;
    }

    // Decode signature (expects base64 PNG without data:image/png;base64, prefix)
    $signatureData = $input['signature'];
    if (strpos($signatureData, 'base64,') !== false) {
        $signatureData = explode('base64,', $signatureData)[1];
    }
    $signatureImage = base64_decode($signatureData);

    if (!$signatureImage) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature data']);
        return;
    }

    // Create signatures directory
    $upload_dir = getFichinterDocDir() . '/' . $fichinter->ref;
    $signatures_dir = $upload_dir . '/signatures';

    // Ensure directories exist
    if (!file_exists($upload_dir)) {
        dol_mkdir($upload_dir);
    }
    if (!file_exists($signatures_dir)) {
        dol_mkdir($signatures_dir);
    }

    // Save signature file
    $timestamp = dol_print_date(dol_now(), '%Y%m%d%H%M%S');
    $filename = $timestamp . '_signature.png';
    $filepath = $signatures_dir . '/' . $filename;

    if (file_put_contents($filepath, $signatureImage) === false) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to save signature file']);
        return;
    }

    // Update intervention signed status directly via SQL (more reliable)
    $signerName = $input['signer_name'] ?? $user->getFullName($langs);
    $signerIp = getUserRemoteIP();

    $sql = "UPDATE ".MAIN_DB_PREFIX."fichinter SET ";
    $sql .= "signed_status = 3,"; // STATUS_SIGNED_RECEIVER_ONLINE
    $sql .= "online_sign_ip = '".$db->escape($signerIp)."',";
    $sql .= "online_sign_name = '".$db->escape($signerName)."'";
    $sql .= " WHERE rowid = ".(int)$intervention_id;

    $resql = $db->query($sql);

    if ($resql) {
        // Also close the intervention (status = 3) after signature
        $fichinter->setClose($user);

        echo json_encode([
            'status' => 'ok',
            'message' => 'Signature saved',
            'file' => $filename,
            'signed_status' => 3,
            'intervention_closed' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'error' => 'Failed to update intervention status',
            'file_saved' => true,
            'db_error' => $db->lasterror()
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

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }

    $intervention_id = (int)($input['intervention_id'] ?? 0);
    $equipment_id = (int)($input['equipment_id'] ?? 0);
    $link_type = $input['link_type'] ?? 'service';

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
        return DOL_DATA_ROOT . '/fichinter';
    }

    // Method 3: Try global $dolibarr_main_data_root from conf.php
    if (!empty($dolibarr_main_data_root)) {
        return $dolibarr_main_data_root . '/fichinter';
    }

    // Method 4: Try to read from conf.php directly
    $confFile = DOL_DOCUMENT_ROOT . '/conf/conf.php';
    if (file_exists($confFile)) {
        include $confFile;
        if (!empty($dolibarr_main_data_root)) {
            return $dolibarr_main_data_root . '/fichinter';
        }
    }

    // Last fallback - common Dolibarr document paths
    $possiblePaths = [
        '/var/lib/dolibarr/documents/fichinter',
        '/home/dolibarr/documents/fichinter',
        dirname(DOL_DOCUMENT_ROOT) . '/documents/fichinter'
    ];

    foreach ($possiblePaths as $path) {
        if (is_dir($path) || is_dir(dirname($path))) {
            return $path;
        }
    }

    return '/var/lib/dolibarr/documents/fichinter';
}

/**
 * Generate PDF for intervention
 */
function generateInterventionPDF($fichinter, $user) {
    global $conf, $langs, $db;

    require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
    require_once DOL_DOCUMENT_ROOT.'/fichinter/class/fichinter.class.php';

    // Get PDF model
    $modele = !empty($conf->global->FICHINTER_ADDON_PDF) ? $conf->global->FICHINTER_ADDON_PDF : 'soleil';

    // Refetch to ensure we have latest data
    $fichinter->fetch($fichinter->id);
    $fichinter->fetch_thirdparty();
    $fichinter->fetch_lines();

    $outputlangs = $langs;
    if (!empty($conf->global->MAIN_MULTILANGS) && !empty($fichinter->thirdparty->default_lang)) {
        $outputlangs = new Translate("", $conf);
        $outputlangs->setDefaultLang($fichinter->thirdparty->default_lang);
    }
    $outputlangs->loadLangs(array("main", "interventions", "companies"));

    $docDir = getFichinterDocDir();
    error_log("PDF generation: using doc dir: " . $docDir);

    $result = $fichinter->generateDocument($modele, $outputlangs);

    if ($result <= 0) {
        error_log("PDF generation failed for intervention " . $fichinter->ref . ": " . $fichinter->error);
    } else {
        error_log("PDF generated successfully for intervention " . $fichinter->ref);
    }

    return $result > 0;
}

/**
 * Get list of documents/PDFs for intervention
 */
function getInterventionDocuments($fichinter) {
    global $conf;

    $documents = [];

    // Get the upload directory for this intervention
    $upload_dir = getFichinterDocDir() . '/' . $fichinter->ref;

    // Build base URL for document access (relative to Dolibarr root)
    $baseUrl = dol_buildpath('/document.php', 1);

    if (is_dir($upload_dir)) {
        // Scan directory for PDF files
        $files = scandir($upload_dir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            if (pathinfo($file, PATHINFO_EXTENSION) === 'pdf') {
                $filepath = $upload_dir . '/' . $file;
                $documents[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'date' => filemtime($filepath),
                    'url' => $baseUrl . '?modulepart=fichinter&file=' . urlencode($fichinter->ref . '/' . $file),
                    'type' => 'pdf'
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

    // Debug info
    if (empty($documents)) {
        error_log("No documents found in: " . $upload_dir);
    }

    return $documents;
}
