<?php
// ══════════════════════════════════════════════════════════════
//  ADMIN API ADDITIONS — Paste these into your api.php
//  1. Add the new cases to the switch($action) block
//  2. Add the new functions below your existing functions
// ══════════════════════════════════════════════════════════════

// ── STEP 1: Add these cases inside your switch($action) block ──
//
//    case 'admin_get_feedbacks':           handleAdminGetFeedbacks(); break;
//    case 'admin_reply_feedback':          handleAdminReplyFeedback(); break;
//    case 'admin_delete_reply':            handleAdminDeleteReply(); break;
//    case 'admin_change_feedback_status':  handleAdminChangeFeedbackStatus(); break;
//    case 'admin_search_user':             handleAdminSearchUser(); break;
//    case 'admin_update_user':             handleAdminUpdateUser(); break;
//    case 'admin_delete_campaign':         handleAdminDeleteCampaign(); break;
//    case 'vote_reply':                    handleVoteReply(); break;
//    case 'submit_feedback':               handleSubmitFeedback(); break;
//    case 'get_my_feedbacks':              handleGetMyFeedbacks(); break;

// ── STEP 2: Also update handleLogin() and handleCheckSession()
//    to return is_admin field (patches shown at bottom of file)

// ══════════════════════════════════════════════════════════════
//  NEW FUNCTIONS
// ══════════════════════════════════════════════════════════════

function isAdmin() {
    // Use session flag set during login — avoids extra DB query
    return isLoggedIn() && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

// ── Get ALL feedbacks for admin with replies and vote counts ──
function handleAdminGetFeedbacks() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $user_id     = isLoggedIn() ? getCurrentUserId() : null;
    $guest_token = sanitizeInput($_POST['guest_token'] ?? '');

    $user_id_sql = $user_id ? "user_id = $user_id" : "1=0";
    $guest_token_sql = ($guest_token && $guest_token !== '') ? "guest_token = '$guest_token'" : "1=0";

    $query = "SELECT f.*,
              fr.id as reply_id, fr.reply as admin_reply, fr.admin_name,
              fr.created_at as reply_date, fr.useful_count, fr.not_useful_count,
              (SELECT COUNT(*) FROM feedback_likes 
               WHERE feedback_id = f.id 
               AND ($user_id_sql OR $guest_token_sql)
              ) > 0 as liked
              FROM feedback f
              LEFT JOIN feedback_replies fr ON fr.feedback_id = f.id
              ORDER BY f.created_at DESC";

    $result = $conn->query($query);
    $feedbacks = $result->fetch_all(MYSQLI_ASSOC);
    
    // Cast 'liked' to boolean for JSON
    foreach ($feedbacks as &$fb) {
        $fb['liked'] = (bool)$fb['liked'];
    }

    echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
}

/**
 * Public action: get approved feedbacks for community view
 */
function handleGetFeedbacks() {
    global $conn;
    
    $user_id     = isLoggedIn() ? getCurrentUserId() : null;
    $guest_token = sanitizeInput($_POST['guest_token'] ?? ($_GET['guest_token'] ?? ''));

    $user_id_sql = $user_id ? "user_id = $user_id" : "1=0";
    $guest_token_sql = ($guest_token && $guest_token !== '') ? "guest_token = '$guest_token'" : "1=0";

    $query = "SELECT f.*, 
              fr.id as reply_id, fr.reply as admin_reply, fr.admin_name,
              fr.created_at as reply_date, fr.useful_count, fr.not_useful_count,
              (SELECT COUNT(*) FROM feedback_likes 
               WHERE feedback_id = f.id 
               AND ($user_id_sql OR $guest_token_sql)
              ) > 0 as liked
              FROM feedback f
              LEFT JOIN feedback_replies fr ON fr.feedback_id = f.id
              WHERE f.status = 'approved'
              ORDER BY f.created_at DESC";

    $result = $conn->query($query);
    $feedbacks = $result->fetch_all(MYSQLI_ASSOC);

    foreach ($feedbacks as &$fb) {
        $fb['liked'] = (bool)$fb['liked'];
    }

    echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
}

// ── Submit or update admin reply ──
function handleAdminReplyFeedback() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $reply       = sanitizeInput($_POST['reply'] ?? '');

    if (!$feedback_id || !$reply) {
        echo json_encode(['success' => false, 'message' => 'Feedback ID and reply required']);
        return;
    }

    // Check if reply already exists
    $stmt = $conn->prepare("SELECT id FROM feedback_replies WHERE feedback_id=?");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Update existing reply
        $stmt = $conn->prepare("UPDATE feedback_replies SET reply=?, created_at=NOW() WHERE feedback_id=?");
        $stmt->bind_param("si", $reply, $feedback_id);
        $stmt->execute();
        $stmt->close();
    } else {
        // Insert new reply
        $stmt = $conn->prepare("INSERT INTO feedback_replies (feedback_id, admin_name, reply) VALUES (?, 'AwareX Team', ?)");
        $stmt->bind_param("is", $feedback_id, $reply);
        $stmt->execute();
        $stmt->close();
    }

    // Auto-approve the feedback when admin replies
    $stmt = $conn->prepare("UPDATE feedback SET status='approved' WHERE id=?");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Reply submitted successfully']);
}

// ── Delete admin reply ──
function handleAdminDeleteReply() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM feedback_replies WHERE feedback_id=?");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Reply deleted']);
}

// ── Delete entire feedback ──
function handleAdminDeleteFeedback() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    if (!$feedback_id) {
        echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id=?");
    $stmt->bind_param("i", $feedback_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Feedback deleted successfully']);
}

// ── Change feedback status ──
function handleAdminChangeFeedbackStatus() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    $status      = sanitizeInput($_POST['status'] ?? '');

    if (!in_array($status, ['pending','approved','rejected'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }

    $stmt = $conn->prepare("UPDATE feedback SET status=? WHERE id=?");
    $stmt->bind_param("si", $status, $feedback_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Status updated']);
}

// ── Search user by email or name ──
function handleAdminSearchUser() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $query = sanitizeInput($_POST['query'] ?? '');
    if (!$query) {
        echo json_encode(['success' => false, 'message' => 'Search query required']);
        return;
    }

    $like = '%' . $query . '%';
    $stmt = $conn->prepare("SELECT id, full_name, email, mobile, gender, dob, age, profile_image, created_at FROM users WHERE (email=? OR full_name LIKE ?) AND is_admin=0 LIMIT 1");
    $stmt->bind_param("ss", $query, $like);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }

    // Get user's campaigns
    $uid = $user['id'];
    $stmt = $conn->prepare("SELECT id, title, description, media_path, media_type, likes_count, created_at FROM campaigns WHERE user_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $uid);
    $stmt->execute();
    $campaigns = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'user' => $user, 'campaigns' => $campaigns]);
}

// ── Admin update user mobile + gender ──
function handleAdminUpdateUser() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $user_id = (int)($_POST['user_id'] ?? 0);
    $mobile  = sanitizeInput($_POST['mobile'] ?? '');
    $gender  = sanitizeInput($_POST['gender'] ?? '');

    if (!$user_id) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }

    // Verify the user exists and is not admin
    $stmt = $conn->prepare("SELECT id FROM users WHERE id=? AND is_admin=0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE users SET mobile=?, gender=? WHERE id=? AND is_admin=0");
    $stmt->bind_param("ssi", $mobile, $gender, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'User details updated successfully']);
}

// ── Admin delete any user's campaign ──
function handleAdminDeleteCampaign() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $campaign_id = (int)($_POST['campaign_id'] ?? 0);
    $user_id     = (int)($_POST['user_id'] ?? 0);

    if (!$campaign_id) {
        echo json_encode(['success' => false, 'message' => 'Campaign ID required']);
        return;
    }

    // Get media file path before deleting
    $stmt = $conn->prepare("SELECT media_path FROM campaigns WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $campaign_id, $user_id);
    $stmt->execute();
    $camp = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$camp) {
        echo json_encode(['success' => false, 'message' => 'Campaign not found']);
        return;
    }

    // Delete media file if exists
    if ($camp['media_path']) {
        $filePath = INCIDENT_UPLOAD_PATH . $camp['media_path'];
        if (file_exists($filePath)) @unlink($filePath);
    }

    // Delete from DB (cascade deletes likes/comments/shares)
    $stmt = $conn->prepare("DELETE FROM campaigns WHERE id=?");
    $stmt->bind_param("i", $campaign_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Campaign deleted successfully']);
}

// ── Vote on admin reply (useful / not_useful) ──
function handleVoteReply() {
    global $conn;
    $reply_id = (int)($_POST['reply_id'] ?? 0);
    $vote     = sanitizeInput($_POST['vote'] ?? '');

    if (!$reply_id || !in_array($vote, ['useful','not_useful'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid vote data']);
        return;
    }

    $user_id     = isLoggedIn() ? getCurrentUserId() : null;
    $guest_token = sanitizeInput($_POST['guest_token'] ?? '');

    if (!$user_id && !$guest_token) {
        echo json_encode(['success' => false, 'message' => 'User or guest token required']);
        return;
    }

    // Check if already voted
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id, vote FROM reply_votes WHERE reply_id=? AND user_id=?");
        $stmt->bind_param("ii", $reply_id, $user_id);
    } else {
        $stmt = $conn->prepare("SELECT id, vote FROM reply_votes WHERE reply_id=? AND guest_token=?");
        $stmt->bind_param("is", $reply_id, $guest_token);
    }
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        echo json_encode(['success' => false, 'message' => 'You have already voted on this reply']);
        return;
    }

    // Insert vote
    if ($user_id) {
        $stmt = $conn->prepare("INSERT INTO reply_votes (reply_id, user_id, vote) VALUES (?,?,?)");
        $stmt->bind_param("iis", $reply_id, $user_id, $vote);
    } else {
        $stmt = $conn->prepare("INSERT INTO reply_votes (reply_id, guest_token, vote) VALUES (?,?,?)");
        $stmt->bind_param("iss", $reply_id, $guest_token, $vote);
    }
    $stmt->execute();
    $stmt->close();

    // Update cached counts on feedback_replies
    if ($vote === 'useful') {
        $conn->query("UPDATE feedback_replies SET useful_count = useful_count + 1 WHERE id = $reply_id");
    } else {
        $conn->query("UPDATE feedback_replies SET not_useful_count = not_useful_count + 1 WHERE id = $reply_id");
    }

    // Return updated counts
    $stmt = $conn->prepare("SELECT useful_count, not_useful_count FROM feedback_replies WHERE id=?");
    $stmt->bind_param("i", $reply_id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Vote recorded', 'counts' => $counts]);
}

// ── Submit Feedback (for users) ──
function handleSubmitFeedback() {
    global $conn;
    $name     = sanitizeInput($_POST['name'] ?? '');
    $email    = sanitizeInput($_POST['email'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $message  = sanitizeInput($_POST['message'] ?? '');
    $user_id  = isLoggedIn() ? getCurrentUserId() : null;

    if (!$name || !$email || !$category || !$message) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    $valid_cats = ['women','child','cyber','social','mental','handsign'];
    if (!in_array($category, $valid_cats)) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO feedback (user_id, name, email, category, message, status) VALUES (?,?,?,?,?,'pending')");
    $stmt->bind_param("issss", $user_id, $name, $email, $category, $message);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Feedback submitted successfully!', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit feedback. Please try again.']);
    }
    $stmt->close();
}

// ── Get logged-in user's own feedbacks with admin replies ──
function handleGetMyFeedbacks() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Not logged in']);
        return;
    }

    $user_id = getCurrentUserId();
    $guest_token = sanitizeInput($_POST['guest_token'] ?? '');

    $user_id_sql = $user_id ? "user_id = $user_id" : "1=0";
    $guest_token_sql = ($guest_token && $guest_token !== '') ? "guest_token = '$guest_token'" : "1=0";

    $stmt = $conn->prepare("SELECT f.*, fr.id as reply_id, fr.reply as admin_reply,
                            fr.created_at as reply_date, fr.useful_count, fr.not_useful_count,
                            (SELECT COUNT(*) FROM feedback_likes 
                             WHERE feedback_id = f.id 
                             AND ($user_id_sql OR $guest_token_sql)
                            ) > 0 as liked
                            FROM feedback f
                            LEFT JOIN feedback_replies fr ON fr.feedback_id = f.id
                            WHERE f.user_id=?
                            ORDER BY f.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $feedbacks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    foreach ($feedbacks as &$fb) {
        $fb['liked'] = (bool)$fb['liked'];
    }

    echo json_encode(['success' => true, 'feedbacks' => $feedbacks]);
}

// ── Like / Unlike Feedback ──
function handleLikeFeedback() {
    global $conn;
    $feedback_id = (int)($_POST['feedback_id'] ?? 0);
    if (!$feedback_id) {
        echo json_encode(['success' => false, 'message' => 'Feedback ID required']);
        return;
    }

    $user_id     = isLoggedIn() ? getCurrentUserId() : null;
    $guest_token = sanitizeInput($_POST['guest_token'] ?? '');

    if (!$user_id && !$guest_token) {
        echo json_encode(['success' => false, 'message' => 'User or guest token required']);
        return;
    }

    // Check if already liked
    if ($user_id) {
        $stmt = $conn->prepare("SELECT id FROM feedback_likes WHERE feedback_id=? AND user_id=?");
        $stmt->bind_param("ii", $feedback_id, $user_id);
    } else {
        if (!$guest_token || $guest_token === '') {
            echo json_encode(['success' => false, 'message' => 'Unable to verify guest identity. Please refresh.']);
            return;
        }
        $stmt = $conn->prepare("SELECT id FROM feedback_likes WHERE feedback_id=? AND guest_token=?");
        $stmt->bind_param("is", $feedback_id, $guest_token);
    }
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing) {
        // Unlike: Remove record
        $stmt = $conn->prepare("DELETE FROM feedback_likes WHERE id=?");
        $stmt->bind_param("i", $existing['id']);
        $stmt->execute();
        $stmt->close();

        // Decrement count
        $conn->query("UPDATE feedback SET likes = GREATEST(likes - 1, 0) WHERE id = $feedback_id");
        $liked = false;
    } else {
        // Like: Insert record
        if ($user_id) {
            $stmt = $conn->prepare("INSERT INTO feedback_likes (feedback_id, user_id) VALUES (?,?)");
            $stmt->bind_param("ii", $feedback_id, $user_id);
        } else {
            $stmt = $conn->prepare("INSERT INTO feedback_likes (feedback_id, guest_token) VALUES (?,?)");
            $stmt->bind_param("is", $feedback_id, $guest_token);
        }
        $stmt->execute();
        $stmt->close();

        // Increment count
        $conn->query("UPDATE feedback SET likes = likes + 1 WHERE id = $feedback_id");
        $liked = true;
    }

    // Get updated count
    $res = $conn->query("SELECT likes FROM feedback WHERE id=$feedback_id")->fetch_assoc();
    echo json_encode(['success' => true, 'liked' => $liked, 'likes' => (int)$res['likes']]);
}

// ── Book a Schedule ──
function handleBookSchedule() {
    global $conn;
    $name          = sanitizeInput($_POST['name'] ?? '');
    $email         = sanitizeInput($_POST['email'] ?? '');
    $mobile        = sanitizeInput($_POST['mobile'] ?? '');
    $problem_desc  = sanitizeInput($_POST['problem_desc'] ?? '');
    $preferred_date = sanitizeInput($_POST['preferred_date'] ?? '');
    $user_id       = isLoggedIn() ? getCurrentUserId() : null;

    if (!$name || !$email || !$mobile || !$problem_desc || !$preferred_date) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    // Validate date is in the future
    $today = date('Y-m-d');
    if ($preferred_date <= $today) {
        echo json_encode(['success' => false, 'message' => 'Please select a future date']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO schedule_bookings (user_id, name, email, mobile, problem_desc, preferred_date) VALUES (?,?,?,?,?,?)");
    $stmt->bind_param("isssss", $user_id, $name, $email, $mobile, $problem_desc, $preferred_date);
    if ($stmt->execute()) {
        // Send email to admin
        $admin_email = "dhanashreegame@gmail.com";
        $subject = "New Schedule Booking Request on Zoom or Google Meet";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <h2 style='color: #1e3a8a;'>New Schedule Booking Request on Zoom or Google Meet</h2>
            <p>You have received a new schedule booking request from AwareX. Here are the details:</p>
            <table style='width: 100%; border-collapse: collapse; margin-top: 20px;'>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold; width: 30%;'>Full Name</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($name) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Email Address</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($email) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Mobile Number</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($mobile) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Preferred Date</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . htmlspecialchars($preferred_date) . "</td>
                </tr>
                <tr>
                    <td style='padding: 10px; border: 1px solid #ddd; font-weight: bold;'>Problem Description</td>
                    <td style='padding: 10px; border: 1px solid #ddd;'>" . nl2br(htmlspecialchars($problem_desc)) . "</td>
                </tr>
            </table>
            <p style='margin-top: 30px; font-size: 0.9em; color: #777;'>Automated message from AwareX.</p>
        </body>
        </html>
        ";
        
        // Use the generic send notification function with Reply-To header
        $reply_to = ['email' => $email, 'name' => $name];
        sendNotificationEmail($admin_email, $subject, $email_body, $reply_to);

        echo json_encode(['success' => true, 'message' => 'Your schedule has been booked successfully! We will contact you soon.', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book schedule. Please try again.']);
    }
    $stmt->close();
}


// ══════════════════════════════════════════════════════════════
//  PATCHES FOR EXISTING FUNCTIONS
//  Update handleLogin() and handleCheckSession() as shown below
// ══════════════════════════════════════════════════════════════

/*
──────────────────────────────────────────────────────────────
PATCH 1: handleLogin() — change the SELECT and response

REPLACE this line in handleLogin():
    $stmt = $conn->prepare("SELECT id, email, password, full_name FROM users WHERE email=?");

WITH:
    $stmt = $conn->prepare("SELECT id, email, password, full_name, is_admin FROM users WHERE email=?");

AND REPLACE the success response:
    echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => ['full_name' => $user['full_name'], 'email' => $user['email']]]);

WITH:
    $_SESSION['user_id']    = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['is_admin']   = $user['is_admin'];
    echo json_encode(['success' => true, 'message' => 'Login successful', 'user' => ['full_name' => $user['full_name'], 'email' => $user['email'], 'is_admin' => $user['is_admin']]]);

──────────────────────────────────────────────────────────────
PATCH 2: handleCheckSession() — add is_admin to user data

REPLACE:
    $stmt = $conn->prepare("SELECT full_name, email, profile_image FROM users WHERE id=?");

WITH:
    $stmt = $conn->prepare("SELECT full_name, email, profile_image, mobile, dob, gender, is_admin FROM users WHERE id=?");

──────────────────────────────────────────────────────────────
*/
?>
