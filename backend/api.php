<?php
// ── Suppress all PHP warnings/notices so they don't break JSON ─
error_reporting(0);
ini_set('display_errors', 0);

require_once 'config.php';
require_once 'send_email.php';
require_once __DIR__ . '/api_admin_additions.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'register':                  handleRegister(); break;
    case 'verify_registration_otp':   handleVerifyRegistrationOTP(); break;
    case 'login':                     handleLogin(); break;
    case 'logout':                    handleLogout(); break;
    case 'check_session':             handleCheckSession(); break;
    case 'send_reset_otp':            handleSendResetOTP(); break;
    case 'verify_reset_otp':          handleVerifyResetOTP(); break;
    case 'reset_password':            handleResetPassword(); break;
    case 'get_profile':               handleGetProfile(); break;
    case 'update_profile':            handleUpdateProfile(); break;
    case 'upload_profile_image':      handleUploadProfileImage(); break;
    case 'request_email_change':      handleRequestEmailChange(); break;
    case 'verify_email_change':       handleVerifyEmailChange(); break;
    case 'upload_incident':           handleUploadIncident(); break;
    case 'get_campaigns':             handleGetCampaigns(); break;
    case 'get_my_incidents':          handleGetMyIncidents(); break;
    case 'delete_incident':           handleDeleteIncident(); break;
    case 'like_campaign':             handleLikeCampaign(); break;
    case 'add_comment':               handleAddComment(); break;
    case 'get_comments':              handleGetComments(); break;
    case 'share_campaign':            handleShareCampaign(); break;
    case 'submit_get_help':           handleSubmitGetHelp(); break;
    case 'submit_feedback':           handleSubmitFeedback(); break;
    case 'get_feedbacks':             handleGetFeedbacks(); break;
    case 'get_my_feedbacks':          handleGetMyFeedbacks(); break;
    case 'admin_get_feedbacks':       handleAdminGetFeedbacks(); break;
    case 'admin_reply_feedback':      handleAdminReplyFeedback(); break;
    case 'admin_delete_reply':        handleAdminDeleteReply(); break;
    case 'admin_delete_feedback':     handleAdminDeleteFeedback(); break;
    case 'admin_change_feedback_status': handleAdminChangeFeedbackStatus(); break;
    case 'admin_search_user':         handleAdminSearchUser(); break;
    case 'admin_update_user':         handleAdminUpdateUser(); break;
    case 'admin_delete_campaign':     handleAdminDeleteCampaign(); break;
    case 'vote_reply':                handleVoteReply(); break;
    case 'like_feedback':             handleLikeFeedback(); break;
    case 'book_schedule':             handleBookSchedule(); break;
    // ── ADMIN: Appointments ──
    case 'admin_get_appointments':        handleAdminGetAppointments(); break;
    case 'admin_update_appointment':     handleAdminUpdateAppointment(); break;
    case 'admin_delete_appointment':     handleAdminDeleteAppointment(); break;
    case 'admin_confirm_appointment':    handleAdminConfirmAppointment(); break;
    case 'get_notifications':            handleGetNotifications(); break;
    case 'mark_notifications_read':      handleMarkNotificationsRead(); break;
    case 'delete_notification':          handleDeleteNotification(); break;
    case 'delete_all_notifications':     handleDeleteAllNotifications(); break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}


// ── Registration ──────────────────────────────────────────────
function handleRegister() {
    global $conn;
    $email     = sanitizeInput($_POST['email'] ?? '');
    $mobile    = sanitizeInput($_POST['mobile'] ?? '');
    $dob       = sanitizeInput($_POST['dob'] ?? '');
    $gender    = sanitizeInput($_POST['gender'] ?? '');
    $password  = $_POST['password'] ?? '';
    $full_name = sanitizeInput($_POST['full_name'] ?? '');

    if (!$email || !$mobile || !$dob || !$gender || !$password || !$full_name) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        return;
    }
    $stmt->close();

    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;

    $_SESSION['reg_data'] = [
        'email'     => $email,
        'mobile'    => $mobile,
        'dob'       => $dob,
        'gender'    => $gender,
        'password'  => password_hash($password, PASSWORD_DEFAULT),
        'full_name' => $full_name,
        'age'       => $age
    ];

    $otp = generateOTP();
    $expires = null; // use MySQL time below

    $stmt = $conn->prepare("UPDATE otp_records SET is_used=1 WHERE email=? AND purpose='registration'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO otp_records (email, otp, purpose, expires_at) VALUES (?, ?, 'registration', ?)");
    $stmt->bind_param("sss", $email, $otp, $expires);
    $stmt->execute();
    $stmt->close();

    $emailResult = sendOTPEmail($email, $otp, 'Registration');

    if ($emailResult['sent']) {
        $response = ['success' => true, 'message' => 'OTP sent to your email address. Please check your inbox (and spam folder).'];
    } else {
        $response = [
            'success' => true,
            'message' => 'Email service not configured. Use the OTP shown on screen.',
            'otp'     => $otp,
            'email_sent' => false
        ];
    }
    echo json_encode($response);
}

function handleVerifyRegistrationOTP() {
    global $conn;
    $email = trim($_POST['email'] ?? '');
    $otp   = trim($_POST['otp'] ?? '');

    if (!$email || !$otp) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP required']);
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM otp_records WHERE email=? AND otp=? AND purpose='registration' AND is_used=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE) ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        return;
    }
    $otp_id = $result->fetch_assoc()['id'];
    $stmt->close();

    $stmt = $conn->prepare("UPDATE otp_records SET is_used=1 WHERE id=?");
    $stmt->bind_param("i", $otp_id);
    $stmt->execute();
    $stmt->close();

    $data = $_SESSION['reg_data'] ?? null;

    if (!$data || strtolower($data['email']) !== strtolower($email)) {
        $pw_raw = $_POST['password'] ?? '';
        if ($pw_raw) {
            $dob = trim($_POST['dob'] ?? '');
            $age = $dob ? (new DateTime())->diff(new DateTime($dob))->y : 18;
            $data = [
                'email'     => $email,
                'mobile'    => trim($_POST['mobile'] ?? ''),
                'dob'       => $dob,
                'gender'    => trim($_POST['gender'] ?? 'Other'),
                'password'  => password_hash($pw_raw, PASSWORD_DEFAULT),
                'full_name' => trim($_POST['full_name'] ?? 'User'),
                'age'       => $age,
            ];
        } else {
            echo json_encode(['success' => false, 'message' => 'Session expired. Please go back and register again.']);
            return;
        }
    }

    $stmt = $conn->prepare("INSERT INTO users (email, mobile, dob, gender, password, full_name, age) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $data['email'], $data['mobile'], $data['dob'], $data['gender'], $data['password'], $data['full_name'], $data['age']);
    if ($stmt->execute()) {
        $_SESSION['user_id']    = $stmt->insert_id;
        $_SESSION['user_email'] = $data['email'];
        unset($_SESSION['reg_data']);
        echo json_encode(['success' => true, 'message' => 'Registration successful! Welcome!']);
    } else {
        $err = $conn->error;
        if (strpos($err, 'Duplicate entry') !== false) {
            echo json_encode(['success' => false, 'message' => 'Email already registered. Please login.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
        }
    }
    $stmt->close();
}

// ── Login ─────────────────────────────────────────────────────
function handleLogin() {
    global $conn;
    $email    = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        echo json_encode(['success' => false, 'message' => 'Email and password required']);
        return;
    }

    $stmt = $conn->prepare("SELECT id, email, password, full_name, is_admin FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Invalid email or password (email not found)']);
        return;
    }
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid email or password (password hash mismatch)']);
        return;
    }

    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin']   = $user['is_admin'];
    echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => [
    'full_name' => $user['full_name'],
    'email'     => $user['email'],
    'is_admin'  => $user['is_admin']
    ]]);
    
}

// ── Logout ────────────────────────────────────────────────────
function handleLogout() {
    session_destroy();
    echo json_encode(['success' => true, 'message' => 'Logged out successfully']);
}

// ── Check Session ─────────────────────────────────────────────
function handleCheckSession() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'logged_in' => false]);
        return;
    }
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT id, full_name, email, profile_image, mobile, dob, gender, is_admin FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'logged_in' => true, 'user' => $user]);
}

// ── Password Reset ────────────────────────────────────────────
function handleSendResetOTP() {
    global $conn;
    $email = sanitizeInput($_POST['email'] ?? '');

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Email not found']);
        return;
    }
    $stmt->close();

    $otp = generateOTP();
    $expires = null; // use MySQL time below

    $stmt = $conn->prepare("UPDATE otp_records SET is_used=1 WHERE email=? AND purpose='password_reset'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("INSERT INTO otp_records (email, otp, purpose, expires_at) VALUES (?, ?, 'password_reset', ?)");
    $stmt->bind_param("sss", $email, $otp, $expires);
    $stmt->execute();
    $stmt->close();

    $emailResult = sendOTPEmail($email, $otp, 'Password Reset');
    $response = ['success' => true, 'message' => 'OTP sent to your email'];
    if (!$emailResult['sent']) {
        $response['message'] = 'Email service not configured. Check console or otp_debug.log for OTP.';
        $response['otp'] = $otp;
        $response['email_sent'] = false;
    }
    echo json_encode($response);
}

function handleVerifyResetOTP() {
    global $conn;
    $email = sanitizeInput($_POST['email'] ?? '');
    $otp   = sanitizeInput($_POST['otp'] ?? '');

    $stmt = $conn->prepare("SELECT id FROM otp_records WHERE email=? AND otp=? AND purpose='password_reset' AND is_used=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE) ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("ss", $email, $otp);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        return;
    }
    $stmt->close();
    $_SESSION['reset_email'] = $email;
    $_SESSION['reset_otp_verified'] = true;
    echo json_encode(['success' => true, 'message' => 'OTP verified']);
}

function handleResetPassword() {
    global $conn;
    $email    = $_SESSION['reset_email'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!$email || !$_SESSION['reset_otp_verified']) {
        echo json_encode(['success' => false, 'message' => 'Session expired']);
        return;
    }

    $hashed = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
    $stmt->bind_param("ss", $hashed, $email);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE otp_records SET is_used=1 WHERE email=? AND purpose='password_reset'");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->close();

    unset($_SESSION['reset_email'], $_SESSION['reset_otp_verified']);
    echo json_encode(['success' => true, 'message' => 'Password reset successful']);
}

// ── Profile ───────────────────────────────────────────────────
function handleGetProfile() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT id, full_name, email, mobile, dob, gender, age, profile_image, created_at FROM users WHERE id=?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'user' => $user]);
}

function handleUpdateProfile() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id   = getCurrentUserId();
    $full_name = sanitizeInput($_POST['full_name'] ?? '');
    $mobile    = sanitizeInput($_POST['mobile'] ?? '');
    $dob       = sanitizeInput($_POST['dob'] ?? '');
    $gender    = sanitizeInput($_POST['gender'] ?? '');

    if (!$full_name) {
        echo json_encode(['success' => false, 'message' => 'Full name is required']);
        return;
    }

    $stmt = $conn->prepare("UPDATE users SET full_name=?, mobile=?, dob=?, gender=? WHERE id=?");
    $stmt->bind_param("ssssi", $full_name, $mobile, $dob, $gender, $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
}

function handleUploadProfileImage() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id = getCurrentUserId();

    if (!isset($_FILES['profile_image'])) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded']);
        return;
    }

    $file = $_FILES['profile_image'];
    $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg','jpeg','png','gif','webp'];

    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
        return;
    }

    $filename = 'profile_' . $user_id . '_' . time() . '.' . $ext;
    $dest = PROFILE_UPLOAD_PATH . $filename;

    if (!is_dir(PROFILE_UPLOAD_PATH)) mkdir(PROFILE_UPLOAD_PATH, 0777, true);

    if (move_uploaded_file($file['tmp_name'], $dest)) {
        $stmt = $conn->prepare("UPDATE users SET profile_image=? WHERE id=?");
        $stmt->bind_param("si", $filename, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true, 'message' => 'Profile image updated', 'filename' => $filename]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed']);
    }
}

function handleRequestEmailChange() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id   = getCurrentUserId();
    $new_email = sanitizeInput($_POST['new_email'] ?? '');

    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
        return;
    }

    $stmt = $conn->prepare("SELECT id FROM users WHERE email=?");
    $stmt->bind_param("s", $new_email);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already in use']);
        return;
    }
    $stmt->close();

    $otp     = generateOTP();
    $expires = null; // use MySQL time below

    $stmt = $conn->prepare("INSERT INTO email_change_pending (user_id, new_email, otp, expires_at) VALUES (?,?,?,?)");
    $stmt->bind_param("isss", $user_id, $new_email, $otp, $expires);
    $stmt->execute();
    $stmt->close();

    $emailResult = sendOTPEmail($new_email, $otp, 'Email Change');
    $response = ['success' => true, 'message' => 'OTP sent to new email'];
    if (!$emailResult['sent']) {
        $response['otp'] = $otp;
        $response['email_sent'] = false;
    }
    echo json_encode($response);
}

function handleVerifyEmailChange() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id = getCurrentUserId();
    $otp     = sanitizeInput($_POST['otp'] ?? '');

    $stmt = $conn->prepare("SELECT id, new_email FROM email_change_pending WHERE user_id=? AND otp=? AND is_verified=0 AND created_at >= DATE_SUB(NOW(), INTERVAL 3 MINUTE) ORDER BY created_at DESC LIMIT 1");
    $stmt->bind_param("is", $user_id, $otp);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired OTP']);
        return;
    }
    $row = $result->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET email=? WHERE id=?");
    $stmt->bind_param("si", $row['new_email'], $user_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("UPDATE email_change_pending SET is_verified=1 WHERE id=?");
    $stmt->bind_param("i", $row['id']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Email changed successfully']);
}

// ── Campaigns / Incidents ─────────────────────────────────────
function handleUploadIncident() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Please login to upload']); return; }
    $user_id     = getCurrentUserId();
    $title       = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');

    if (!$title || !$description) {
        echo json_encode(['success' => false, 'message' => 'Title and description required']);
        return;
    }

    $media_path = null;
    $media_type = null;

    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media'];
        $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $image_ext = ['jpg','jpeg','png','gif','webp'];
        $video_ext = ['mp4','mov','avi','mkv','webm'];

        if (in_array($ext, $image_ext)) $media_type = 'image';
        elseif (in_array($ext, $video_ext)) $media_type = 'video';
        else {
            echo json_encode(['success' => false, 'message' => 'Invalid file type. Use JPG, PNG or MP4.']);
            return;
        }

        if (!is_dir(INCIDENT_UPLOAD_PATH)) mkdir(INCIDENT_UPLOAD_PATH, 0777, true);
        $filename  = 'incident_' . $user_id . '_' . time() . '.' . $ext;
        $dest      = INCIDENT_UPLOAD_PATH . $filename;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $media_path = $filename;
        }
    }

    $stmt = $conn->prepare("INSERT INTO campaigns (user_id, title, description, media_path, media_type) VALUES (?,?,?,?,?)");
    $stmt->bind_param("issss", $user_id, $title, $description, $media_path, $media_type);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Incident uploaded successfully! It will appear on the Campaigns page.', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Upload failed']);
    }
    $stmt->close();
}

function handleGetCampaigns() {
    global $conn;
    $user_id = getCurrentUserId() ?? 0;
    $page  = (int)($_GET['page'] ?? 1);
    $limit = 15;
    $offset = ($page - 1) * $limit;

    $query = "SELECT c.*, u.full_name, u.profile_image,
              (SELECT COUNT(*) FROM campaign_likes WHERE campaign_id = c.id AND user_id = ?) as user_liked
              FROM campaigns c
              JOIN users u ON c.user_id = u.id
              ORDER BY c.created_at DESC
              LIMIT ? OFFSET ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $limit, $offset);
    $stmt->execute();
    $campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'campaigns' => $campaigns]);
}

function handleGetMyIncidents() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id = getCurrentUserId();

    $stmt = $conn->prepare("SELECT * FROM campaigns WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $incidents = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'incidents' => $incidents]);
}

function handleDeleteIncident() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Not logged in']); return; }
    $user_id     = getCurrentUserId();
    $incident_id = (int)($_POST['incident_id'] ?? 0);

    // Verify ownership
    $stmt = $conn->prepare("SELECT id, media_path FROM campaigns WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $incident_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Incident not found or access denied']);
        return;
    }
    $inc = $result->fetch_assoc();
    $stmt->close();

    // Delete media file if exists
    if ($inc['media_path']) {
        $filePath = INCIDENT_UPLOAD_PATH . $inc['media_path'];
        if (file_exists($filePath)) @unlink($filePath);
    }

    // Delete from DB (cascade deletes likes/comments/shares)
    $stmt = $conn->prepare("DELETE FROM campaigns WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $incident_id, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Incident deleted successfully']);
}

function handleLikeCampaign() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Please login to like']); return; }
    $user_id     = getCurrentUserId();
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);

    $stmt = $conn->prepare("SELECT id FROM campaign_likes WHERE campaign_id=? AND user_id=?");
    $stmt->bind_param("ii", $campaign_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        $stmt->close();
        $stmt = $conn->prepare("DELETE FROM campaign_likes WHERE campaign_id=? AND user_id=?");
        $stmt->bind_param("ii", $campaign_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE campaigns SET likes_count = GREATEST(likes_count - 1, 0) WHERE id = $campaign_id");
        $count = $conn->query("SELECT likes_count FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['likes_count'];
        echo json_encode(['success' => true, 'liked' => false, 'count' => $count]);
    } else {
        $stmt->close();
        $stmt = $conn->prepare("INSERT INTO campaign_likes (campaign_id, user_id) VALUES (?,?)");
        $stmt->bind_param("ii", $campaign_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $conn->query("UPDATE campaigns SET likes_count = likes_count + 1 WHERE id = $campaign_id");
        $count = $conn->query("SELECT likes_count FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['likes_count'];
        echo json_encode(['success' => true, 'liked' => true, 'count' => $count]);
    }
}

function handleAddComment() {
    global $conn;
    if (!isLoggedIn()) { echo json_encode(['success' => false, 'message' => 'Please login to comment']); return; }
    $user_id     = getCurrentUserId();
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $comment     = sanitizeInput($_POST['comment'] ?? '');

    if (!$comment) { echo json_encode(['success' => false, 'message' => 'Comment cannot be empty']); return; }

    $stmt = $conn->prepare("INSERT INTO campaign_comments (campaign_id, user_id, comment) VALUES (?,?,?)");
    $stmt->bind_param("iis", $campaign_id, $user_id, $comment);
    $stmt->execute();
    $new_id = $stmt->insert_id;
    $stmt->close();
    $conn->query("UPDATE campaigns SET comments_count = comments_count + 1 WHERE id = $campaign_id");

    $stmt = $conn->prepare("SELECT cc.*, u.full_name, u.profile_image FROM campaign_comments cc JOIN users u ON cc.user_id = u.id WHERE cc.id = ?");
    $stmt->bind_param("i", $new_id);
    $stmt->execute();
    $new_comment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'comment' => $new_comment]);
}

function handleGetComments() {
    global $conn;
    $campaign_id = (int)($_GET['campaign_id'] ?? 0);
    $stmt = $conn->prepare("SELECT cc.*, u.full_name, u.profile_image FROM campaign_comments cc JOIN users u ON cc.user_id = u.id WHERE cc.campaign_id=? ORDER BY cc.created_at DESC");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    echo json_encode(['success' => true, 'comments' => $comments]);
}

function handleShareCampaign() {
    global $conn;
    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    if (isLoggedIn()) {
        $user_id = getCurrentUserId();
        $stmt = $conn->prepare("INSERT INTO campaign_shares (campaign_id, user_id) VALUES (?,?)");
        $stmt->bind_param("ii", $campaign_id, $user_id);
        $stmt->execute();
        $stmt->close();
    }
    $conn->query("UPDATE campaigns SET shares_count = shares_count + 1 WHERE id = $campaign_id");
    $count = $conn->query("SELECT shares_count FROM campaigns WHERE id=$campaign_id")->fetch_assoc()['shares_count'];
    echo json_encode(['success' => true, 'count' => $count]);
}

// ── Contact ───────────────────────────────────────────────────
function handleSubmitGetHelp() {
    global $conn;
    $name    = sanitizeInput($_POST['name'] ?? '');
    $email   = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    if (!$name || !$email || !$subject || !$message) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }

    // Save to DB
    $stmt = $conn->prepare("INSERT INTO contact_submissions (name, email, subject, message) VALUES (?,?,?,?)");
    $stmt->bind_param("ssss", $name, $email, $subject, $message);
    $saved = $stmt->execute();
    $stmt->close();

    // Send email notification to admin
    sendGetHelpEmail($name, $email, $subject, $message);

    if ($saved) {
        echo json_encode(['success' => true, 'message' => 'Your message has been sent successfully! We will respond within 24 hours.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
    }
}

function sendGetHelpEmail($name, $email, $subject, $message) {
    $adminEmail = SMTP_USERNAME; // Send to dhanashreegame@gmail.com

    $emailBody = "
    <html><head><style>
        body{font-family:Arial,sans-serif;background:#f5f5f5;margin:0;padding:20px;}
        .container{max-width:600px;margin:0 auto;background:white;border-radius:16px;overflow:hidden;}
        .header{background:linear-gradient(135deg,#1e3a8a,#3b82f6);color:white;padding:30px;text-align:center;}
        .content{padding:30px;}
        .field{margin-bottom:16px;}
        .label{font-size:12px;color:#9ca3af;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}
        .value{font-size:15px;color:#1f2937;padding:10px;background:#f8faff;border-radius:8px;border-left:4px solid #1e3a8a;}
        .footer{text-align:center;padding:20px;background:#f9fafb;color:#9ca3af;font-size:12px;}
    </style></head><body>
    <div class='container'>
        <div class='header'><h2>New Get Help Message</h2><p>AwareX</p></div>
        <div class='content'>
            <div class='field'><div class='label'>From</div><div class='value'>{$name} &lt;{$email}&gt;</div></div>
            <div class='field'><div class='label'>Subject</div><div class='value'>{$subject}</div></div>
            <div class='field'><div class='label'>Message</div><div class='value' style='white-space:pre-wrap;'>{$message}</div></div>
        </div>
        <div class='footer'>Sent from AwareX Get Help Form</div>
    </div></body></html>";

    // Check for PHPMailer
    $phpmailerPaths = [
        dirname(__DIR__) . '/vendor/autoload.php',
        dirname(__FILE__)  . '/../vendor/autoload.php',
        dirname(__DIR__) . '/PHPMailer/src/PHPMailer.php',          // SOCIAL_AWARENESS/PHPMailer/ ✓
        dirname(__DIR__) . '/frontend/PHPMailer/src/PHPMailer.php',
        dirname(__FILE__)  . '/PHPMailer/src/PHPMailer.php',
    ];

    $composerAutoload = null;
    $manualPHPMailer  = null;
    foreach ($phpmailerPaths as $path) {
        if (file_exists($path)) {
            if (substr($path, -12) === 'autoload.php') {
                $composerAutoload = $path;
            } else {
                $manualPHPMailer = dirname($path);
            }
            break;
        }
    }

    if ($composerAutoload || $manualPHPMailer) {
        if ($composerAutoload) require_once $composerAutoload;
        else {
            require_once $manualPHPMailer . '/Exception.php';
            require_once $manualPHPMailer . '/PHPMailer.php';
            require_once $manualPHPMailer . '/SMTP.php';
        }
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USERNAME;
            $mail->Password   = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($adminEmail);
            $mail->addReplyTo($email, $name);
            $mail->isHTML(true);
            $mail->Subject = "Get Help: {$subject} — from {$name}";
            $mail->Body    = $emailBody;
            $mail->send();
        } catch (\Exception $e) {
            error_log("Contact email error: " . $e->getMessage());
        }
    }
}
?>