<?php
// Simple file-backed API for office map to restore basic functionality.
// Supports actions: load (GET), save (POST JSON with action:'save' and data:[]), reset (POST), upload_image (multipart POST).
header('Content-Type: application/json');
// allow session-based preloads from other pages (e.g. accountables.php)
if (session_status() === PHP_SESSION_NONE) session_start();
$storage = __DIR__ . '/data/office_map_points.json';
$action = isset($_GET['action']) ? $_GET['action'] : null;
$method = $_SERVER['REQUEST_METHOD'];

// helper to load/save file storage
function load_storage($path)
{
    if (!file_exists($path)) return [];
    $c = file_get_contents($path);
    $j = json_decode($c, true);
    return is_array($j) ? $j : [];
}
function save_storage($path, $data)
{
    @mkdir(dirname($path), 0755, true);
    return file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT));
}

// GET: load desks
if ($method === 'GET' && $action === 'load') {
    $desks = load_storage($storage);
    echo json_encode(['success' => true, 'desks' => $desks]);
    exit;
}

// GET: return preload assign data (set by accountables.php)
if ($method === 'GET' && $action === 'get_assign_preload') {
    $out = null;
    if (isset($_SESSION['omap_preassign']) && is_array($_SESSION['omap_preassign'])) {
        $out = $_SESSION['omap_preassign'];
        // clear after read
        unset($_SESSION['omap_preassign']);
    }
    echo json_encode(['success' => true, 'preload' => $out]);
    exit;
}

// GET: provide existing image info (first match)
if ($method === 'GET' && $action === 'image') {
    $candidates = [
        __DIR__ . '/officemap.PNG',
        __DIR__ . '/officemap.png',
        __DIR__ . '/assets/img/officemap.PNG',
        __DIR__ . '/assets/img/officemap.png',
        __DIR__ . '/assets/img/Capture.PNG',
        __DIR__ . '/assets/img/office_floor.png',
        __DIR__ . '/../assets/img/officemap.PNG',
        __DIR__ . '/../assets/img/officemap.png',
        __DIR__ . '/../assets/img/Capture.PNG',
        __DIR__ . '/../assets/img/office_floor.png'
    ];

    // helper to compute a web path from filesystem path
    $get_web_path = function ($p) {
        $p = str_replace('\\', '/', $p);
        $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/')) : '';
        if ($docRoot && strpos($p, $docRoot) === 0) {
            $web = substr($p, strlen($docRoot));
            if ($web === '') $web = '/';
            return '/' . ltrim(str_replace('\\', '/', $web), '/');
        }
        // try to find project folder name (common fallback)
        $proj = '/inventory_cao_v2';
        $pos = strpos($p, $proj);
        if ($pos !== false) {
            return substr($p, $pos);
        }
        // final fallback: serve from OfficeMap by basename
        return '/inventory_cao_v2/OfficeMap/' . basename($p);
    };

    foreach ($candidates as $p) {
        if (file_exists($p)) {
            $webPath = $get_web_path($p);
            $imgData = @file_get_contents($p);
            $img = @imagecreatefromstring($imgData);
            $w = $h = 0;
            if ($img) {
                $w = imagesx($img);
                $h = imagesy($img);
                // imagedestroy($img);
            }
            echo json_encode(['success' => true, 'path' => $webPath, 'width' => $w, 'height' => $h]);
            exit;
        }
    }
    // check DB table office_map_images for a record
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf; // provides $conn
        if (isset($conn) && $conn instanceof mysqli) {
            $res = $conn->query("SELECT filename, width, height FROM office_map_images ORDER BY uploaded_at DESC LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) {
                // prefer OfficeMap/assets then root assets
                $paths = [__DIR__ . '/assets/img/' . $row['filename'], __DIR__ . '/../assets/img/' . $row['filename']];
                foreach ($paths as $p) {
                    if (file_exists($p)) {
                        $rel = str_replace($_SERVER['DOCUMENT_ROOT'], '', $p);
                        echo json_encode(['success' => true, 'path' => $rel, 'width' => intval($row['width']), 'height' => intval($row['height'])]);
                        exit;
                    }
                }
            }
        }
    }
    // No actual file found — return a sensible default path to the officemap filename so front-end uses it by default.
    $defaultRel = '/inventory_cao_v2/OfficeMap/assets/img/officemap.PNG';
    echo json_encode(['success' => true, 'path' => $defaultRel, 'note' => 'default path returned (file may be missing)']);
    exit;
}

// Simple search endpoints to support frontend autocomplete
if ($method === 'GET' && $action === 'search_inventory') {
    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if ($q && file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $like = $conn->real_escape_string('%' . $q . '%');
            $res = $conn->query("SELECT id, item_name, particulars, quantity, are_mr_ics_num FROM inventory_items WHERE item_name LIKE '" . $like . "' OR particulars LIKE '" . $like . "' LIMIT 50");
            if ($res) {
                while ($row = $res->fetch_assoc()) $out[] = $row;
            }
        }
    }
    echo json_encode(['success' => true, 'items' => $out]);
    exit;
}

if ($method === 'GET' && $action === 'search_accountables') {
    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if ($q && file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $like = $conn->real_escape_string('%' . $q . '%');
            // try common table names: accountable_items, accountable, accountable_items_list
            $tables = ['accountable_items', 'accountable', 'accountable_items_list'];
            foreach ($tables as $t) {
                $res = @$conn->query("SELECT * FROM `" . $t . "` WHERE person_name LIKE '" . $like . "' OR property_number LIKE '" . $like . "' LIMIT 50");
                if ($res) {
                    while ($row = $res->fetch_assoc()) $out[] = $row;
                    if (!empty($out)) break;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'accountables' => $out]);
    exit;
}

if ($method === 'GET' && $action === 'search_employees') {
    $q = isset($_GET['q']) ? $_GET['q'] : '';
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if ($q && file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $like = $conn->real_escape_string('%' . $q . '%');
            // try common employee table names (include cao_employee)
            $tables = ['cao_employee', 'employees', 'end_users', 'users', 'end_user'];
            foreach ($tables as $t) {
                $res = @$conn->query("SELECT * FROM `" . $t . "` WHERE FIRSTNAME LIKE '" . $like . "' OR LASTNAME LIKE '" . $like . "' OR person_name LIKE '" . $like . "' LIMIT 50");
                if ($res) {
                    while ($row = $res->fetch_assoc()) $out[] = $row;
                    if (!empty($out)) break;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'employees' => $out]);
    exit;
}

// Return a short list of employees (no search) for initial population
if ($method === 'GET' && $action === 'list_employees') {
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $res = $conn->query("SELECT LASTNAME, FIRSTNAME, end_user_id_number AS end_user_id_number, ID FROM cao_employee ORDER BY LASTNAME LIMIT 200");
            if ($res) {
                while ($row = $res->fetch_assoc()) $out[] = $row;
            }
        }
    }
    echo json_encode(['success' => true, 'employees' => $out]);
    exit;
}

// Return a short list of inventory items
if ($method === 'GET' && $action === 'list_inventory') {
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $res = $conn->query("SELECT id, item_name, particulars FROM inventory_items ORDER BY item_name LIMIT 200");
            if ($res) while ($row = $res->fetch_assoc()) $out[] = $row;
        }
    }
    echo json_encode(['success' => true, 'items' => $out]);
    exit;
}

// Return a short list of accountables
if ($method === 'GET' && $action === 'list_accountables') {
    $out = [];
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $res = @$conn->query("SELECT person_name, property_number, id FROM accountable_items LIMIT 200");
            if ($res) while ($row = $res->fetch_assoc()) $out[] = $row;
        }
    }
    echo json_encode(['success' => true, 'accountables' => $out]);
    exit;
}

// Get single employee by end_user_id_number or ID
if ($method === 'GET' && $action === 'get_employee') {
    $id = isset($_GET['id']) ? $_GET['id'] : '';
    $out = null;
    $conf = __DIR__ . '/../config.php';
    if ($id && file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $safe = $conn->real_escape_string($id);
            $res = $conn->query("SELECT * FROM cao_employee WHERE end_user_id_number = '" . $safe . "' OR ID = '" . $safe . "' LIMIT 1");
            if ($res && $row = $res->fetch_assoc()) $out = $row;
        }
    }
    echo json_encode(['success' => true, 'employee' => $out]);
    exit;
}

// GET: fetch assigned accountables and borrowed items for a person/employee
if ($method === 'GET' && $action === 'get_person_items') {
    $employee_id = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : null;
    $person_name = isset($_GET['person_name']) ? trim($_GET['person_name']) : null;
    // normalize person name: strip trailing parenthetical ids and create alternate 'FIRST LAST' form
    $person_name_alt = '';
    if ($person_name) {
        // remove trailing parentheses like " (4)" or " (EndUser)"
        $person_name = preg_replace('/\s*\([^)]*\)\s*$/', '', $person_name);
        if (strpos($person_name, ',') !== false) {
            $parts = array_map('trim', explode(',', $person_name, 2));
            if (count($parts) === 2) {
                $person_name_alt = $parts[1] . ' ' . $parts[0];
            }
        }
    }
    $out = ['assigned' => [], 'borrowed' => []];
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            // assigned: accountable_items matching employee_id or exact person_name
            if ($employee_id) {
                $stmt = $conn->prepare("SELECT ai.id, ai.inventory_item_id, ai.person_name, ai.assigned_quantity, ai.remarks, ai.date_assigned, ii.item_name, COALESCE(ai.property_number,'') AS property_number, COALESCE(ai.serial_number,'') AS serial_number FROM accountable_items ai INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id WHERE ai.is_deleted = 0 AND ai.employee_id = ? ORDER BY ai.date_assigned DESC");
                $stmt->bind_param('i', $employee_id);
                $stmt->execute();
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $out['assigned'][] = $r;
                $stmt->close();
            }
            if (empty($out['assigned']) && $person_name) {
                // tokenise name into parts (handles LAST, FIRST and names with middle initials)
                $tokens = preg_split('/[,\s]+/', $person_name);
                $tokens = array_values(array_filter(array_map('trim', $tokens)));
                $tokens_alt = [];
                if ($person_name_alt) $tokens_alt = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $person_name_alt))));

                $conds = [];
                $paramsA = [];
                $typesA = '';
                if (count($tokens) > 0) {
                    $parts = [];
                    foreach ($tokens as $t) { $parts[] = "ai.person_name LIKE ?"; $paramsA[] = '%' . $t . '%'; $typesA .= 's'; }
                    $conds[] = '(' . implode(' AND ', $parts) . ')';
                }
                if (count($tokens_alt) > 0) {
                    $parts = [];
                    foreach ($tokens_alt as $t) { $parts[] = "ai.person_name LIKE ?"; $paramsA[] = '%' . $t . '%'; $typesA .= 's'; }
                    $conds[] = '(' . implode(' AND ', $parts) . ')';
                }
                if (count($conds) > 0) {
                    $where = implode(' OR ', $conds);
                    $sql = "SELECT ai.id, ai.inventory_item_id, ai.person_name, ai.assigned_quantity, ai.remarks, ai.date_assigned, ii.item_name, COALESCE(ai.property_number,'') AS property_number, COALESCE(ai.serial_number,'') AS serial_number FROM accountable_items ai INNER JOIN inventory_items ii ON ii.id = ai.inventory_item_id WHERE ai.is_deleted = 0 AND ($where) ORDER BY ai.date_assigned DESC";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        if ($typesA !== '') {
                            $refs = [];
                            $refs[] = &$typesA;
                            foreach ($paramsA as $k => $v) $refs[] = &$paramsA[$k];
                            call_user_func_array(array($stmt, 'bind_param'), $refs);
                        }
                        $stmt->execute();
                        $res = $stmt->get_result();
                        while ($r = $res->fetch_assoc()) $out['assigned'][] = $r;
                        $stmt->close();
                    }
                }
            }

            // borrowed: borrowed_items where to_person matches or borrower_employee_id matches
            // borrowed: try borrower_employee_id match first; otherwise match tokens in to_person/from_person (both name orders)
            $tokens = $person_name ? array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $person_name)))) : [];
            $tokens_alt = $person_name_alt ? array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $person_name_alt)))) : [];

            $borrowParams = [];
            $borrowTypes = '';
            $borrowConds = [];

            if ($employee_id) {
                $borrowConds[] = 'borrower_employee_id = ?';
                $borrowParams[] = $employee_id; $borrowTypes .= 'i';
            }

            // helper to build (to_person LIKE ? AND to_person LIKE ? ... ) blocks
            $buildLikeBlock = function($field, $tokens, &$params, &$types) {
                if (!is_array($tokens) || count($tokens) === 0) return '';
                $parts = [];
                foreach ($tokens as $t) { $parts[] = "$field LIKE ?"; $params[] = '%' . $t . '%'; $types .= 's'; }
                return '(' . implode(' AND ', $parts) . ')';
            };

            $tpBlock = $buildLikeBlock('to_person', $tokens, $borrowParams, $borrowTypes);
            $fpBlock = $buildLikeBlock('from_person', $tokens, $borrowParams, $borrowTypes);
            if ($tpBlock) $borrowConds[] = $tpBlock;
            if ($fpBlock) $borrowConds[] = $fpBlock;

            // alt tokens
            $tpBlock2 = $buildLikeBlock('to_person', $tokens_alt, $borrowParams, $borrowTypes);
            $fpBlock2 = $buildLikeBlock('from_person', $tokens_alt, $borrowParams, $borrowTypes);
            if ($tpBlock2) $borrowConds[] = $tpBlock2;
            if ($fpBlock2) $borrowConds[] = $fpBlock2;

            if (count($borrowConds) > 0) {
                $where = implode(' OR ', $borrowConds);
                $sql = "SELECT b.borrow_id, b.inventory_item_id, ii.item_name, b.from_person, b.to_person, b.borrower_employee_id, b.quantity, b.reference_no, b.remarks, b.borrow_date, b.return_date, b.is_returned FROM borrowed_items b LEFT JOIN inventory_items ii ON ii.id = b.inventory_item_id WHERE ($where) ORDER BY b.borrow_date DESC";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($borrowTypes !== '') {
                        $refs = [];
                        $refs[] = &$borrowTypes;
                        foreach ($borrowParams as $k => $v) $refs[] = &$borrowParams[$k];
                        call_user_func_array(array($stmt, 'bind_param'), $refs);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) $out['borrowed'][] = $r;
                    $stmt->close();
                }
            }

            // related transactions: recent transactions for items involving this person
            $out['transactions'] = [];
            // build params for prepared statement
            $itemIds = [];
            foreach ($out['assigned'] as $a) $itemIds[] = intval($a['inventory_item_id']);
            foreach ($out['borrowed'] as $b) $itemIds[] = intval($b['inventory_item_id']);
            $itemIds = array_values(array_unique(array_filter($itemIds)));

            // prepare a where clause: inventory_item_id IN (...) OR reference_no IN (borrow refs) OR person name match in borrowed_items
            $conds = [];
            $params = [];
            $types = '';
            if (count($itemIds) > 0) {
                $place = implode(',', array_fill(0, count($itemIds), '?'));
                $conds[] = "it.inventory_item_id IN ($place)";
                foreach ($itemIds as $id) { $params[] = $id; $types .= 'i'; }
            }
            // also include borrowed refs
            $borrowRefs = [];
            foreach ($out['borrowed'] as $b) if (!empty($b['reference_no'])) $borrowRefs[] = $b['reference_no'];
            $borrowRefs = array_values(array_unique($borrowRefs));
            if (count($borrowRefs) > 0) {
                $place2 = implode(',', array_fill(0, count($borrowRefs), '?'));
                $conds[] = "it.reference_no IN ($place2)";
                foreach ($borrowRefs as $rf) { $params[] = $rf; $types .= 's'; }
            }

            // include transactions where borrowed_items.to_person matches person_name
            if ($person_name) {
                $likepn1 = '%' . $person_name . '%';
                $likepn2 = $person_name_alt ? '%' . $person_name_alt . '%' : $likepn1;
                $conds[] = "EXISTS (SELECT 1 FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND (b.to_person LIKE ? OR b.from_person LIKE ? OR b.to_person LIKE ? OR b.from_person LIKE ?))";
                $params[] = $likepn1; $types .= 's';
                $params[] = $likepn1; $types .= 's';
                $params[] = $likepn2; $types .= 's';
                $params[] = $likepn2; $types .= 's';
            }

            if (count($conds) > 0) {
                $where = implode(' OR ', $conds);
                $sql = "SELECT it.*, ii.item_name,
                    (SELECT ai.person_name FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS current_holder_name,
                    (SELECT ai.assigned_quantity FROM accountable_items ai WHERE ai.inventory_item_id = it.inventory_item_id AND ai.is_deleted = 0 ORDER BY ai.date_assigned DESC, ai.id DESC LIMIT 1) AS current_assigned_qty,
                    (SELECT CONCAT(b.to_person, ' (Borrowed - ', b.quantity, ')') FROM borrowed_items b WHERE b.inventory_item_id = it.inventory_item_id AND b.is_returned = 0 ORDER BY b.borrow_date DESC LIMIT 1) AS borrowed_status
                    FROM inventory_transactions it INNER JOIN inventory_items ii ON ii.id = it.inventory_item_id WHERE ($where) ORDER BY it.transaction_date DESC LIMIT 200";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    if ($types !== '') {
                        // bind params dynamically
                        $refs = [];
                        $refs[] = &$types;
                        foreach ($params as $k => $v) $refs[] = &$params[$k];
                        call_user_func_array(array($stmt, 'bind_param'), $refs);
                    }
                    $stmt->execute();
                    $res = $stmt->get_result();
                    while ($r = $res->fetch_assoc()) $out['transactions'][] = $r;
                    $stmt->close();
                    // remove duplicate transactions by reference_no (keep first occurrence from ordered results)
                    $seenRefs = [];
                    $uniqueTx = [];
                    foreach ($out['transactions'] as $tx) {
                        $ref = isset($tx['reference_no']) ? trim($tx['reference_no']) : '';
                        if ($ref !== '') {
                            if (isset($seenRefs[$ref])) continue;
                            $seenRefs[$ref] = true;
                        }
                        $uniqueTx[] = $tx;
                    }
                    $out['transactions'] = $uniqueTx;
                }
            }
        }
    }
    echo json_encode(['success' => true, 'data' => $out]);
    exit;
}

// Update employee record (simple writable fields)
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update_employee') {
    // only ADMIN may update employee via this API
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!$json || !isset($json['employee'])) { echo json_encode(['success'=>false,'msg'=>'invalid']); exit; }
    $emp = $json['employee'];
    $conf = __DIR__ . '/../config.php';
    if (!file_exists($conf)) { echo json_encode(['success'=>false,'msg'=>'no db']); exit; }
    include_once $conf;
    if (!isset($conn) || !($conn instanceof mysqli)) { echo json_encode(['success'=>false,'msg'=>'db missing']); exit; }

    // accept either ID or end_user_id_number as identifier
    $id = isset($emp['ID']) ? $emp['ID'] : (isset($emp['id']) ? $emp['id'] : null);
    $endno = isset($emp['end_user_id_number']) ? $emp['end_user_id_number'] : (isset($emp['end_user']) ? $emp['end_user'] : null);
    $first = isset($emp['FIRSTNAME']) ? $emp['FIRSTNAME'] : (isset($emp['firstname']) ? $emp['firstname'] : '');
    $last = isset($emp['LASTNAME']) ? $emp['LASTNAME'] : (isset($emp['lastname']) ? $emp['lastname'] : '');

    try {
        if ($id) {
            $stmt = $conn->prepare("UPDATE cao_employee SET FIRSTNAME = ?, LASTNAME = ?, end_user_id_number = ? WHERE ID = ?");
            $stmt->bind_param('sssi', $first, $last, $endno, $id);
        } else if ($endno) {
            $stmt = $conn->prepare("UPDATE cao_employee SET FIRSTNAME = ?, LASTNAME = ? WHERE end_user_id_number = ?");
            $stmt->bind_param('sss', $first, $last, $endno);
        } else {
            echo json_encode(['success'=>false,'msg'=>'no identifier']); exit;
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        echo json_encode(['success'=>true,'affected'=>$affected]);
        exit;
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'msg'=>$e->getMessage()]);
        exit;
    }
}

// Areas: file-backed storage for map areas (rectangles)
if ($method === 'GET' && $action === 'areas') {
    $areasFile = __DIR__ . '/data/office_map_areas.json';
    if (!file_exists($areasFile)) echo json_encode(['success' => true, 'areas' => []]);
    else echo json_encode(['success' => true, 'areas' => json_decode(file_get_contents($areasFile), true) ?: []]);
    exit;
}

if ($method === 'POST' && $action === 'save_area') {
    // only ADMIN may create or edit areas
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!$json || !isset($json['area'])) { echo json_encode(['success' => false, 'msg' => 'invalid']); exit; }
    $area = $json['area'];
    $areasFile = __DIR__ . '/data/office_map_areas.json';
    @mkdir(dirname($areasFile), 0755, true);
    $areas = [];
    if (file_exists($areasFile)) $areas = json_decode(file_get_contents($areasFile), true) ?: [];
    // upsert by id
    if (!isset($area['id']) || !$area['id']) $area['id'] = 'A' . (time() . rand(100,999));
    $found = false;
    for ($i=0;$i<count($areas);$i++){
        if (isset($areas[$i]['id']) && $areas[$i]['id'] === $area['id']) { $areas[$i] = $area; $found = true; break; }
    }
    if (!$found) $areas[] = $area;
    file_put_contents($areasFile, json_encode($areas, JSON_PRETTY_PRINT));
    // try DB sync
    $dbOk = false;
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) {
        include_once $conf;
        if (isset($conn) && $conn instanceof mysqli) {
            $conn->query("CREATE TABLE IF NOT EXISTS office_map_areas (id VARCHAR(64) PRIMARY KEY, name VARCHAR(255), x1 INT, y1 INT, x2 INT, y2 INT, meta LONGTEXT, last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $stmt = $conn->prepare("INSERT INTO office_map_areas (id,name,x1,y1,x2,y2,meta) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), x1=VALUES(x1), y1=VALUES(y1), x2=VALUES(x2), y2=VALUES(y2), meta=VALUES(meta)");
            $meta = json_encode(isset($area['meta']) ? $area['meta'] : new stdClass());
            $x1 = intval($area['x1']); $y1 = intval($area['y1']); $x2 = intval($area['x2']); $y2 = intval($area['y2']);
            $stmt->bind_param('ssiiiss', $area['id'], $area['name'], $x1, $y1, $x2, $y2, $meta);
            $stmt->execute();
            $stmt->close();
            $dbOk = true;
        }
    }
    echo json_encode(['success'=>true,'id'=>$area['id'],'db_sync'=>$dbOk]);
    exit;
}

if ($method === 'POST' && $action === 'delete_area') {
    // only ADMIN may delete areas
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!$json || !isset($json['id'])) { echo json_encode(['success'=>false,'msg'=>'invalid']); exit; }
    $id = $json['id'];
    $areasFile = __DIR__ . '/data/office_map_areas.json';
    $areas = [];
    if (file_exists($areasFile)) $areas = json_decode(file_get_contents($areasFile), true) ?: [];
    $areas = array_values(array_filter($areas, function($a) use ($id){ return !isset($a['id']) || $a['id'] !== $id; }));
    file_put_contents($areasFile, json_encode($areas, JSON_PRETTY_PRINT));
    // try DB delete
    $conf = __DIR__ . '/../config.php';
    if (file_exists($conf)) { include_once $conf; if (isset($conn) && $conn instanceof mysqli) { $stmt = $conn->prepare('DELETE FROM office_map_areas WHERE id=?'); $stmt->bind_param('s', $id); $stmt->execute(); $stmt->close(); }}
    echo json_encode(['success'=>true]);
    exit;
}

// Record inventory transaction (POST JSON)
if ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'record_transaction') {
    // only ADMIN may record transactions from this API
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);
    if (!$json) { echo json_encode(['success' => false, 'msg' => 'invalid json']); exit; }

    $inventory_item_id = isset($json['inventory_item_id']) ? intval($json['inventory_item_id']) : 0;
    $qty = isset($json['quantity']) ? intval($json['quantity']) : 0;
    $type = isset($json['transaction_type']) ? $json['transaction_type'] : 'OUT';
    $reference = isset($json['reference_no']) ? $json['reference_no'] : ('WEB-' . strtoupper($type) . '-' . date('YmdHis'));
    $remarks = isset($json['remarks']) ? $json['remarks'] : '';

    if (!$inventory_item_id || $qty <= 0) { echo json_encode(['success' => false, 'msg' => 'invalid payload']); exit; }

    $conf = __DIR__ . '/../config.php';
    if (!file_exists($conf)) { echo json_encode(['success' => false, 'msg' => 'no db']); exit; }
    include_once $conf;
    if (!isset($conn) || !($conn instanceof mysqli)) { echo json_encode(['success' => false, 'msg' => 'db missing']); exit; }

    try {
        $conn->begin_transaction();
        // adjust inventory quantity
        if ($type === 'OUT') {
            // lock and check
            $stmt = $conn->prepare("SELECT quantity FROM inventory_items WHERE id = ? FOR UPDATE");
            $stmt->bind_param('i', $inventory_item_id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) throw new Exception('item not found');
            if (intval($row['quantity']) < $qty) throw new Exception('insufficient quantity');
            $newq = intval($row['quantity']) - $qty;
            $stmt = $conn->prepare("UPDATE inventory_items SET quantity = ? WHERE id = ?");
            $stmt->bind_param('ii', $newq, $inventory_item_id);
            $stmt->execute();
            $stmt->close();
        } else {
            // IN: increase stock
            $stmt = $conn->prepare("UPDATE inventory_items SET quantity = quantity + ? WHERE id = ?");
            $stmt->bind_param('ii', $qty, $inventory_item_id);
            $stmt->execute();
            $stmt->close();
        }

        // ensure `remarks` column exists on inventory_transactions (some schemas may not have it)
        $colRes = $conn->query("SHOW COLUMNS FROM inventory_transactions LIKE 'remarks'");
        if (!$colRes || $colRes->num_rows === 0) {
            // try to add the column (text, nullable)
            @$conn->query("ALTER TABLE inventory_transactions ADD COLUMN remarks TEXT NULL");
        }

        // insert transaction
        $stmt = $conn->prepare("INSERT INTO inventory_transactions (inventory_item_id, transaction_type, quantity, reference_no, remarks, transaction_date) VALUES (?, ?, ?, ?, ?, NOW())");
        // types: i (inventory_item_id), s (transaction_type), i (quantity), s (reference_no), s (remarks)
        $stmt->bind_param('isiss', $inventory_item_id, $type, $qty, $reference, $remarks);
        $stmt->execute();
        $stmt->close();

        // If caller provided borrow/accountable info and this was an OUT, try to create borrowed_items record
        $accountable_id = isset($json['accountable_id']) && $json['accountable_id'] ? intval($json['accountable_id']) : null;
        $to_person = isset($json['to_person']) ? trim($json['to_person']) : null;
        $borrower_employee_id = isset($json['borrower_employee_id']) && $json['borrower_employee_id'] ? intval($json['borrower_employee_id']) : null;

        if (strtoupper($type) === 'OUT' && ($accountable_id || $to_person || $borrower_employee_id)) {
            // try best-effort to insert into borrowed_items table
            // check table exists
            $tb = $conn->query("SHOW TABLES LIKE 'borrowed_items'");
            if ($tb && $tb->num_rows > 0) {
                try {
                    // if accountable_id provided, fetch accountable row for from_person and inventory_item_id
                    $from_person = null;
                    $inv_from_accountable = null;
                    if ($accountable_id) {
                        $s = $conn->prepare("SELECT inventory_item_id, person_name, assigned_quantity, are_mr_ics_num, property_number, serial_number, po_number, account_code, old_account_code FROM accountable_items WHERE id = ? FOR UPDATE");
                        if ($s) {
                            $s->bind_param('i', $accountable_id);
                            $s->execute();
                            $accRow = $s->get_result()->fetch_assoc();
                            $s->close();
                            if ($accRow) {
                                $from_person = $accRow['person_name'];
                                $inv_from_accountable = intval($accRow['inventory_item_id']);
                                // decrease assigned_quantity by $qty (best-effort)
                                $stmtUp = $conn->prepare("UPDATE accountable_items SET assigned_quantity = GREATEST(0, assigned_quantity - ?) WHERE id = ?");
                                if ($stmtUp) {
                                    $stmtUp->bind_param('ii', $qty, $accountable_id);
                                    $stmtUp->execute();
                                    $stmtUp->close();
                                }
                            }
                        }
                    }

                    // prefer using provided inventory_item_id
                    $inv_for_borrow = $inventory_item_id;
                    if (!$inv_for_borrow && $inv_from_accountable) $inv_for_borrow = $inv_from_accountable;

                    // insert borrowed_items with minimal columns (keep nulls for optional)
                    $insCols = ['inventory_item_id','to_person','borrower_employee_id','quantity','reference_no','remarks','borrow_date','is_returned'];
                    $placeholders = '?,?,?,?,?,?,NOW(),0';

                    // If caller is not ADMIN, include status and requested_by
                    $isAdmin = isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN';
                    $currentUser = $_SESSION['id'] ?? null;
                    if (!$isAdmin) {
                        $insCols[] = 'status';
                        $insCols[] = 'requested_by';
                        $placeholders .= ", 'PENDING', ?"; // status literal and requested_by placeholder
                    }

                    $stmtB = $conn->prepare("INSERT INTO borrowed_items (" . implode(',', $insCols) . ") VALUES ($placeholders)");
                    if ($stmtB) {
                        // bind types: i (inventory_item_id), s (to_person), i (borrower_employee_id), i (quantity), s (reference_no), s (remarks)
                        $tp = '';
                        $bindArr = [];
                        $tp .= 'i'; $bindArr[] = $inv_for_borrow;
                        $tp .= 's'; $bindArr[] = $to_person ?: '';
                        $tp .= 'i'; $bindArr[] = $borrower_employee_id ?: 0;
                        $tp .= 'i'; $bindArr[] = $qty;
                        $tp .= 's'; $bindArr[] = $reference;
                        $tp .= 's'; $bindArr[] = $remarks;
                        if (!$isAdmin) {
                            $tp .= 'i'; $bindArr[] = $currentUser;
                        }
                        // call bind_param dynamically
                        $refs = [];
                        $refs[] = &$tp;
                        foreach ($bindArr as $k => $v) $refs[] = &$bindArr[$k];
                        call_user_func_array([$stmtB, 'bind_param'], $refs);
                        $stmtB->execute();
                        $newBorrowId = $conn->insert_id;
                        $stmtB->close();

                        // if non-admin, notify all admins using the unified NotificationHandler
                        if (!$isAdmin) {
                            try {
                                $adminIds = [];
                                $q = $conn->query("SELECT id FROM `user` WHERE role = 'ADMIN'");
                                while ($q && $a = $q->fetch_assoc()) {
                                    $adminIds[] = intval($a['id']);
                                }
                                if ($adminIds) {
                                    // use the handler once per admin for clarity
                                    $notifHandler = new NotificationHandler($conn, $currentUser, 'STAFF');
                                    $notifHandler->sendToMultiple(
                                        NotificationTypes::BORROW_REQUEST_SUBMITTED,
                                        $adminIds,
                                        $currentUser,
                                        $newBorrowId,
                                        "Borrow request {$reference} submitted",
                                        ['reference' => $reference, 'to_person' => $to_person]
                                    );
                                }
                            } catch (Exception $e) {
                                // ignore errors but do not abort the transaction
                            }
                        }
                    }
                } catch (Exception $e) {
                    // don't fail the whole transaction if borrow insert fails; just continue
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'reference_no' => $reference]);
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'msg' => $e->getMessage()]);
        exit;
    }
}

// multipart upload (image/csv)
if ($method === 'POST' && !empty($_FILES) && isset($_GET['action']) && $_GET['action'] === 'upload_image') {
    if (!isset($_FILES['image'])) {
        echo json_encode(['success' => false, 'msg' => 'no file']);
        exit;
    }
    $fn = $_FILES['image']['name'];
    $tmp = $_FILES['image']['tmp_name'];
    $destDir = __DIR__ . '/assets/img/';
    @mkdir($destDir, 0755, true);
    $dest = $destDir . 'officemap_' . time() . '_' . basename($fn);
    if (move_uploaded_file($tmp, $dest)) {
        // Also copy to canonical path in OfficeMap root as officemap.PNG (overwrite)
        $canon = __DIR__ . '/officemap.PNG';
        // try to convert extension to PNG name even if original different
        @copy($dest, $canon);
        // try to write a DB record if table exists
        $conf = __DIR__ . '/../config.php';
        $w = 0;
        $h = 0;
        $size = @getimagesize($dest);
        if ($size) {
            $w = intval($size[0]);
            $h = intval($size[1]);
        }
        if (file_exists($conf)) {
            include_once $conf;
            if (isset($conn) && $conn instanceof mysqli) {
                // insert record into office_map_images if table exists
                $stmt = $conn->prepare("INSERT INTO office_map_images (filename, width, height) VALUES (?, ?, ?)");
                if ($stmt) {
                    $fname = basename($dest);
                    $stmt->bind_param('sii', $fname, $w, $h);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
        // return canonical path to client (web-safe)
        $canonWeb = '/' . ltrim(basename(__DIR__) . '/' . basename($canon), '/');
        // attempt to compute better path using document root
        if (isset($_SERVER['DOCUMENT_ROOT'])) {
            $docRoot = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'], '/'));
            $p = str_replace('\\', '/', $canon);
            if ($docRoot && strpos($p, $docRoot) === 0) {
                $canonWeb = substr($p, strlen($docRoot));
                $canonWeb = '/' . ltrim($canonWeb, '/');
            }
        }
        echo json_encode(['success' => true, 'filename' => basename($dest), 'canonical' => $canonWeb]);
        exit;
    }
    echo json_encode(['success' => false, 'msg' => 'move failed']);
    exit;
}

// POST JSON handling
$raw = file_get_contents('php://input');
if ($method === 'POST') {
    $json = json_decode($raw, true);
    if (!$json) {
        echo json_encode(['success' => false, 'msg' => 'invalid json']);
        exit;
    }
    $act = isset($json['action']) ? $json['action'] : ($action ?: null);

    // Save (upsert) single desk
    if ($act === 'save' || isset($json['desk'])) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
        $desk = isset($json['desk']) ? $json['desk'] : $json['data'];
        if (!$desk || !isset($desk['id'])) {
            echo json_encode(['success' => false, 'msg' => 'invalid desk']);
            exit;
        }
        $desks = load_storage($storage);
        // replace or add
        $found = false;
        for ($i = 0; $i < count($desks); $i++) {
            if (isset($desks[$i]['id']) && $desks[$i]['id'] == $desk['id']) {
                $desks[$i] = $desk;
                $found = true;
                break;
            }
        }
        if (!$found) $desks[] = $desk;
        save_storage($storage, $desks);

        // optional DB sync: try to include project config and upsert into office_map_desks
        $dbOk = false;
        $dbMsg = '';
        $conf = __DIR__ . '/../config.php';
        if (file_exists($conf)) {
            try {
                include_once $conf; // provides $conn
                if (isset($conn) && $conn instanceof mysqli) {
                    // ensure table exists
                    $conn->query("CREATE TABLE IF NOT EXISTS office_map_desks (
                        id VARCHAR(100) PRIMARY KEY,
                        name VARCHAR(255),
                        x INT,
                        y INT,
                        employee_id VARCHAR(64),
                        items LONGTEXT,
                        last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                    $stmt = $conn->prepare("INSERT INTO office_map_desks (id,name,x,y,employee_id,items) VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE name=VALUES(name), x=VALUES(x), y=VALUES(y), employee_id=VALUES(employee_id), items=VALUES(items)");
                    $itemsJson = json_encode(isset($desk['items']) ? $desk['items'] : []);
                    $x = isset($desk['x']) ? intval($desk['x']) : (isset($desk['lng']) ? intval($desk['lng']) : 0);
                    $y = isset($desk['y']) ? intval($desk['y']) : (isset($desk['lat']) ? intval($desk['lat']) : 0);
                    $employee_id = isset($desk['employee_id']) ? (string)$desk['employee_id'] : '';
                    $stmt->bind_param('ssiiss', $desk['id'], $desk['name'], $x, $y, $employee_id, $itemsJson);
                    $stmt->execute();
                    $stmt->close();
                    $dbOk = true;
                }
            } catch (Exception $e) {
                $dbMsg = $e->getMessage();
            }
        }

        echo json_encode(['success' => true, 'db_sync' => $dbOk, 'db_msg' => $dbMsg]);
        exit;
    }

    // Delete desk
    if ($act === 'delete' && isset($json['id'])) {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
        $id = $json['id'];
        $desks = load_storage($storage);
        $desks = array_values(array_filter($desks, function ($d) use ($id) {
            return !isset($d['id']) || $d['id'] !== $id;
        }));
        save_storage($storage, $desks);
        // delete from DB if available
        $conf = __DIR__ . '/../config.php';
        if (file_exists($conf)) {
            include_once $conf;
            if (isset($conn) && $conn instanceof mysqli) {
                $stmt = $conn->prepare('DELETE FROM office_map_desks WHERE id=?');
                $stmt->bind_param('s', $id);
                $stmt->execute();
                $stmt->close();
            }
        }
        echo json_encode(['success' => true]);
        exit;
    }

    // Reset to sample
    if ($act === 'reset') {
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') { http_response_code(403); echo json_encode(['success'=>false,'msg'=>'forbidden']); exit; }
        $sample = [['id' => 'D1', 'name' => 'Desk 1', 'x' => 200, 'y' => 200, 'items' => []], ['id' => 'D2', 'name' => 'Desk 2', 'x' => 400, 'y' => 200, 'items' => []]];
        save_storage($storage, $sample);
        echo json_encode(['success' => true]);
        exit;
    }
}

// fallback
http_response_code(400);
echo json_encode(['success' => false, 'msg' => 'unknown action']);
