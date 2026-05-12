<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

require_once 'db_connection.php';
$user_id = intval($_SESSION['user_id']);
$hasUnreadColumn = false;
$colRes = mysqli_query($conn, "SHOW COLUMNS FROM messages LIKE 'is_read'");
if ($colRes && mysqli_num_rows($colRes) > 0) {
    $hasUnreadColumn = true;
} else {
    $alter_query = "ALTER TABLE messages ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0";
    if (mysqli_query($conn, $alter_query)) {
        $hasUnreadColumn = true;
    }
}
$data = json_decode(file_get_contents('php://input'), true);
$req = is_array($data) ? $data : $_POST;
$req = array_merge($_GET, $req);

$action = $req['action'] ?? '';

if ($action === 'send') {
    $receiver_id = intval($req['receiver_id'] ?? 0);
    $message = trim($req['message'] ?? '');
    
    if ($receiver_id > 0 && !empty($message)) {
        if ($hasUnreadColumn) {
            $q = "INSERT INTO messages (Sender_id, Receiver_id, body, is_read) VALUES (?, ?, ?, 0)";
        } else {
            $q = "INSERT INTO messages (Sender_id, Receiver_id, body) VALUES (?, ?, ?)";
        }
        $st = mysqli_prepare($conn, $q);
        if ($hasUnreadColumn) {
            mysqli_stmt_bind_param($st, "iis", $user_id, $receiver_id, $message);
        } else {
            mysqli_stmt_bind_param($st, "iis", $user_id, $receiver_id, $message);
        }
        if (mysqli_stmt_execute($st)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'DB error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
    }
}
elseif ($action === 'fetch') {
    $contact_id = intval($req['contact_id'] ?? 0);
    if ($contact_id > 0) {
        if ($hasUnreadColumn) {
            $update_read = "UPDATE messages SET is_read = 1 WHERE Sender_id = ? AND Receiver_id = ? AND is_read = 0";
            $upd = mysqli_prepare($conn, $update_read);
            mysqli_stmt_bind_param($upd, "ii", $contact_id, $user_id);
            mysqli_stmt_execute($upd);
        }

        $q = "SELECT * FROM messages WHERE 
              (Sender_id = ? AND Receiver_id = ?) OR 
              (Sender_id = ? AND Receiver_id = ?) 
              ORDER BY created_at ASC";
        $st = mysqli_prepare($conn, $q);
        mysqli_stmt_bind_param($st, "iiii", $user_id, $contact_id, $contact_id, $user_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $msgs = [];
        while($r = mysqli_fetch_assoc($res)) {
            $msgs[] = [
                'id' => $r['Message_id'],
                'sender_id' => $r['Sender_id'],
                'receiver_id' => $r['Receiver_id'],
                'message' => htmlspecialchars($r['body']),
                'time' => date('h:i a', strtotime($r['created_at']))
            ];
        }
        echo json_encode(['success' => true, 'messages' => $msgs]);
    } else {
        echo json_encode(['success' => false]);
    }
}
elseif ($action === 'get_contacts') {
    if ($hasUnreadColumn) {
        $q = "
        SELECT u.user_id AS User_id, u.full_name AS User_name, u.profile_pic, 
               (SELECT body FROM messages WHERE (Sender_id=u.user_id AND Receiver_id=?) OR (Sender_id=? AND Receiver_id=u.user_id) ORDER BY created_at DESC LIMIT 1) as last_msg,
               (SELECT created_at FROM messages WHERE (Sender_id=u.user_id AND Receiver_id=?) OR (Sender_id=? AND Receiver_id=u.user_id) ORDER BY created_at DESC LIMIT 1) as last_time,
               (SELECT COUNT(*) FROM messages WHERE Sender_id=u.user_id AND Receiver_id=? AND is_read = 0) as unread_count
        FROM users u 
        WHERE u.user_id IN (
            SELECT Sender_id FROM messages WHERE Receiver_id = ?
            UNION 
            SELECT Receiver_id FROM messages WHERE Sender_id = ?
        )
        ORDER BY last_time DESC
        ";
    } else {
        $q = "
        SELECT u.user_id AS User_id, u.full_name AS User_name, u.profile_pic, 
               (SELECT body FROM messages WHERE (Sender_id=u.user_id AND Receiver_id=?) OR (Sender_id=? AND Receiver_id=u.user_id) ORDER BY created_at DESC LIMIT 1) as last_msg,
               (SELECT created_at FROM messages WHERE (Sender_id=u.user_id AND Receiver_id=?) OR (Sender_id=? AND Receiver_id=u.user_id) ORDER BY created_at DESC LIMIT 1) as last_time,
               0 as unread_count
        FROM users u 
        WHERE u.user_id IN (
            SELECT Sender_id FROM messages WHERE Receiver_id = ?
            UNION 
            SELECT Receiver_id FROM messages WHERE Sender_id = ?
        )
        ORDER BY last_time DESC
        ";
    }
    
    $st = mysqli_prepare($conn, $q);
    if ($hasUnreadColumn) {
        mysqli_stmt_bind_param($st, "iiiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    } else {
        mysqli_stmt_bind_param($st, "iiiiii", $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
    }
    mysqli_stmt_execute($st);
    $res = mysqli_stmt_get_result($st);
    $contacts = [];
    while($r = mysqli_fetch_assoc($res)) {
        if (mb_strlen($r['last_msg']) > 30) {
            $r['last_msg'] = mb_substr($r['last_msg'], 0, 30) . '...';
        }
        $r['last_msg'] = htmlspecialchars($r['last_msg'] ?? '');
        $r['unread_count'] = intval($r['unread_count'] ?? 0);
        $contacts[] = $r;
    }
    
    // Check if new_chat_id is passed and missing from contacts
    $new_chat_id = intval($req['new_chat_id'] ?? 0);
    $found = false;
    foreach($contacts as $c) {
        if ($c['User_id'] == $new_chat_id) $found = true;
    }

    if ($new_chat_id > 0 && !$found) {
        // Fetch user data to inject into contacts
        $nq = "SELECT user_id AS User_id, full_name AS User_name, profile_pic FROM users WHERE user_id = ?";
        $nst = mysqli_prepare($conn, $nq);
        mysqli_stmt_bind_param($nst, "i", $new_chat_id);
        mysqli_stmt_execute($nst);
        $nres = mysqli_stmt_get_result($nst);
        if ($nr = mysqli_fetch_assoc($nres)) {
            $nr['last_msg'] = 'Start chatting!';
            array_unshift($contacts, $nr); // put at top
        }
    }
    
    echo json_encode(['success' => true, 'contacts' => $contacts]);
}
else {
    echo json_encode(['success' => false, 'message' => 'Unknown action']);
}
?>
