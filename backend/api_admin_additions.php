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

// ── NEW FUNCTIONS
// ──────────────────────────────────────────────────────────────

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
    $name           = sanitizeInput($_POST['name'] ?? '');
    $email          = sanitizeInput($_POST['email'] ?? '');
    $mobile         = sanitizeInput($_POST['mobile'] ?? '');
    $problem_desc   = sanitizeInput($_POST['problem_desc'] ?? '');
    $preferred_date = sanitizeInput($_POST['preferred_date'] ?? '');
    $preferred_time = sanitizeInput($_POST['preferred_time'] ?? '');
    $user_id        = isLoggedIn() ? getCurrentUserId() : null;

    if (!$name || !$email || !$mobile || !$problem_desc || !$preferred_date || !$preferred_time) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    // ── Validation: Date Range (Today to +1 Month) ──
    $today = date('Y-m-d');
    $maxDate = date('Y-m-d', strtotime('+1 month'));

    if ($preferred_date < $today) {
        echo json_encode(['success' => false, 'message' => 'Please select a future date (current or later)']);
        return;
    }
    if ($preferred_date > $maxDate) {
        echo json_encode(['success' => false, 'message' => 'Consultations can only be booked up to 1 month in advance']);
        return;
    }

    // ── Validation: Time Range (09:00 AM to 06:00 PM) ──
    $startTime = "09:00";
    $endTime   = "18:00";
    
    if ($preferred_time < $startTime || $preferred_time > $endTime) {
        echo json_encode(['success' => false, 'message' => 'Consultations are only available between 09:00 AM and 06:00 PM']);
        return;
    }

    // ── Verification: Future Time (If date is today) ──
    if ($preferred_date === $today) {
        $currentTime = date('H:i');
        if ($preferred_time <= $currentTime) {
            echo json_encode(['success' => false, 'message' => 'Please select a future time for today']);
            return;
        }
    }

    $stmt = $conn->prepare("INSERT INTO schedule_bookings (user_id, name, email, mobile, problem_desc, preferred_date, preferred_time) VALUES (?,?,?,?,?,?,?)");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error (prepare failed): ' . $conn->error]);
        return;
    }
    $stmt->bind_param("issssss", $user_id, $name, $email, $mobile, $problem_desc, $preferred_date, $preferred_time);
    
    if ($stmt->execute()) {
        // Appointment is saved to database, which will show on admin dashboard
        echo json_encode(['success' => true, 'message' => 'Your schedule has been booked successfully! We will contact you soon.', 'id' => $stmt->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to book schedule. Please try again.']);
    }
    $stmt->close();
}


// ── Conflict Check Helper ──
function isAppointmentConflicting($date, $time, $exclude_id = 0) {
    global $conn;
    $stmt = $conn->prepare("SELECT id FROM schedule_bookings WHERE preferred_date=? AND preferred_time=? AND status='confirmed' AND id!=?");
    $stmt->bind_param("ssi", $date, $time, $exclude_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $conflict = $res->num_rows > 0;
    $stmt->close();
    return $conflict;
}
// ── Get ALL appointments for admin ──
function handleAdminGetAppointments() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $query = "SELECT s.*, u.profile_image 
              FROM schedule_bookings s
              LEFT JOIN users u ON s.user_id = u.id
              ORDER BY 
                CASE 
                    WHEN CONCAT(s.preferred_date, ' ', s.preferred_time) >= NOW() THEN 0 
                    ELSE 1 
                END ASC,
                CASE 
                    WHEN CONCAT(s.preferred_date, ' ', s.preferred_time) >= NOW() 
                    THEN CONCAT(s.preferred_date, ' ', s.preferred_time) 
                END ASC,
                CONCAT(s.preferred_date, ' ', s.preferred_time) DESC";

    $result = $conn->query($query);
    $appointments = $result->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'appointments' => $appointments]);
}

// ── Update appointment date/time/link/status ──
function handleAdminUpdateAppointment() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id             = (int)($_POST['appointment_id'] ?? 0);
    $preferred_date = sanitizeInput($_POST['preferred_date'] ?? '');
    $preferred_time = sanitizeInput($_POST['preferred_time'] ?? '');

    if (!$id || !$preferred_date || !$preferred_time) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        return;
    }

    // Time validation: 9 AM to 6 PM
    $hour = (int)date('H', strtotime($preferred_time));
    if ($hour < 9 || $hour >= 18) {
        echo json_encode(['success' => false, 'message' => 'Error: Scheduling is only allowed between 09:00 AM and 06:00 PM.']);
        return;
    }

    // Fetch previous data to see what changed
    $stmt = $conn->prepare("SELECT * FROM schedule_bookings WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $old = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$old) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }

    // Update ONLY timing details, reset status to 'pending' to require user approval
    $stmt = $conn->prepare("UPDATE schedule_bookings SET preferred_date=?, preferred_time=?, status='pending' WHERE id=?");
    $stmt->bind_param("ssi", $preferred_date, $preferred_time, $id);
    
    if ($stmt->execute()) {
        $date_f = date('d-M-Y', strtotime($preferred_date));
        $time_f = date('h:i A', strtotime($preferred_time));

        // Create specialized notification for user
        if ($old['user_id']) {
            $msg = "Admin has proposed a new time for your consultation: $date_f at $time_f. Please approve or delete the request below.";
            
            $stmt_noti = $conn->prepare("INSERT INTO notifications (user_id, message, type, appointment_id) VALUES (?, ?, 'reschedule_request', ?)");
            $stmt_noti->bind_param("isi", $old['user_id'], $msg, $id);
            $stmt_noti->execute();
            $stmt_noti->close();
        }

        // Send Email Notification
        $subject = "Reschedule Requested for Your Appointment - AwareX";
        
        $email_body = "
        <html>
        <body style='font-family: sans-serif; color: #333; line-height: 1.6;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden;'>
                <div style='background: #1e3a8a; color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin:0;'>Reschedule Requested</h2>
                </div>
                <div style='padding: 30px;'>
                    <p>Hello <strong>" . htmlspecialchars($old['name']) . "</strong>,</p>
                    <p>Our admin has proposed a new time for your consultation request.</p>
                    
                    <div style='background: #f8faff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin: 20px 0;'>
                        <p style='margin: 0 0 10px 0;'><strong>Proposed Date:</strong> $date_f</p>
                        <p style='margin: 0 0 10px 0;'><strong>Proposed Time:</strong> $time_f</p>
                    </div>
                    
                    <p>Please log in to your dashboard to <strong>Approve</strong> or <strong>Delete</strong> this rescheduled slot.</p>
                    <p>Thank you for your cooperation.</p>
                </div>
                <div style='background: #f9fafb; padding: 20px; text-align: center; font-size: 0.85em; color: #777;'>
                    AwareX Administration Team
                </div>
            </div>
        </body>
        </html>";

        sendNotificationEmail($old['email'], $subject, $email_body);

        echo json_encode(['success' => true, 'message' => 'Appointment updated and user notified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update appointment']);
    }
    $stmt->close();
}

// ── GET notifications for user ──
function handleGetNotifications() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Login required']);
        return;
    }
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $notifications = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Check count of unread
    $stmt = $conn->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id=? AND is_read=0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $unread = $stmt->get_result()->fetch_assoc()['unread'];
    $stmt->close();

    echo json_encode(['success' => true, 'notifications' => $notifications, 'unread_count' => (int)$unread]);
}

// ── Mark notifications as read ──
function handleMarkNotificationsRead() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Login required']);
        return;
    }
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("UPDATE notifications SET is_read=1 WHERE user_id=? AND is_read=0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Notifications cleared']);
}

// ── Delete a single notification ──
function handleDeleteNotification() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Login required']);
        return;
    }
    $user_id = getCurrentUserId();
    $id = (int)($_POST['notification_id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        return;
    }
    $stmt = $conn->prepare("DELETE FROM notifications WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete notification']);
    }
    $stmt->close();
}

// ── Delete all notifications for user ──
function handleDeleteAllNotifications() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Login required']);
        return;
    }
    $user_id = getCurrentUserId();
    $stmt = $conn->prepare("DELETE FROM notifications WHERE user_id=?");
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'All notifications deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to clear notifications']);
    }
    $stmt->close();
}


// ── Delete appointment ──
function handleAdminDeleteAppointment() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id = (int)($_POST['appointment_id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM schedule_bookings WHERE id=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Appointment deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete appointment']);
    }
    $stmt->close();
}

// ── Confirm appointment and send meeting link via email ──
function handleAdminConfirmAppointment() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id = (int)($_POST['appointment_id'] ?? 0);

    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID is required']);
        return;
    }

    // Fetch appointment details
    $stmt = $conn->prepare("SELECT * FROM schedule_bookings WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }

    // CHECK FOR CONFLICT
    if (isAppointmentConflicting($booking['preferred_date'], $booking['preferred_time'], $id)) {
        echo json_encode(['success' => false, 'message' => 'Error: Another appointment is already confirmed for this Date/Time. Please reschedule this request first.']);
        return;
    }

    // Update status to 'confirmed' in DB
    $stmt = $conn->prepare("UPDATE schedule_bookings SET status='confirmed' WHERE id=?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Create in-app notification for user
        if ($booking['user_id']) {
            $date_f = date('d-M-Y', strtotime($booking['preferred_date']));
            $time_f = date('h:i A', strtotime($booking['preferred_time']));
            $msg = "Your appointment on $date_f at $time_f has been confirmed! Please be available at the scheduled time.";
            
            $stmt_noti = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt_noti->bind_param("is", $booking['user_id'], $msg);
            $stmt_noti->execute();
            $stmt_noti->close();
        }

        // Send confirmation email to user
        $subject = "Consultation Confirmed - AwareX";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden;'>
                <div style='background: #1e3a8a; color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin: 0;'>Appointment Confirmed</h2>
                </div>
                <div style='padding: 30px;'>
                    <p>Hello <strong>" . htmlspecialchars($booking['name']) . "</strong>,</p>
                    <p>Your consultation request has been confirmed. Please find the meeting details below:</p>
                    
                    <div style='background: #f8faff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; margin: 20px 0;'>
                        <p style='margin: 0 0 10px 0;'><strong>Date:</strong> " . date('d-M-Y', strtotime($booking['preferred_date'])) . "</p>
                        <p style='margin: 0 0 10px 0;'><strong>Time:</strong> " . date('h:i A', strtotime($booking['preferred_time'])) . "</p>
                    </div>
                                        
                    <p>Please ensure you are ready 5 minutes before the scheduled time.</p>
                    <p>Our team will contact you shortly regarding the meeting link.</p>
                </div>
                <div style='background: #f9fafb; padding: 20px; text-align: center; font-size: 0.85em; color: #777;'>
                    &copy; 2026 AwareX Team. All rights reserved.
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendNotificationEmail($booking['email'], $subject, $email_body);

        echo json_encode(['success' => true, 'message' => 'Appointment confirmed and user notified!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment']);
    }
    $stmt->close();
}

/**
 * Send meeting link to user (Post-Confirmation)
 */
function handleAdminSendMeetLink() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id   = (int)($_POST['appointment_id'] ?? 0);
    $link = sanitizeInput($_POST['meet_link'] ?? '');

    if (!$id || !$link) {
        echo json_encode(['success' => false, 'message' => 'ID and meeting link are required']);
        return;
    }

    // Fetch appointment details
    $stmt = $conn->prepare("SELECT * FROM schedule_bookings WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found']);
        return;
    }

    // Update meet_link in DB
    $stmt = $conn->prepare("UPDATE schedule_bookings SET meet_link=? WHERE id=?");
    $stmt->bind_param("si", $link, $id);
    
    if ($stmt->execute()) {
        // Create in-app notification for user
        if ($booking['user_id']) {
            $date_f = date('d-M-Y', strtotime($booking['preferred_date']));
            $msg = "The meeting link for your consultation on $date_f has been shared: <a href='$link' target='_blank' style='color:#1e3a8a; font-weight:bold; text-decoration:underline;'>$link</a>";
            
            $stmt_noti = $conn->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt_noti->bind_param("is", $booking['user_id'], $msg);
            $stmt_noti->execute();
            $stmt_noti->close();
        }

        // Send Email
        $subject = "Meeting Link for Your Consultation - AwareX";
        $email_body = "
        <html>
        <body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>
            <div style='max-width: 600px; margin: 0 auto; border: 1px solid #eee; border-radius: 12px; overflow: hidden;'>
                <div style='background: #1e3a8a; color: white; padding: 30px; text-align: center;'>
                    <h2 style='margin: 0;'>Meeting Link Details</h2>
                </div>
                <div style='padding: 30px;'>
                    <p>Hello <strong>" . htmlspecialchars($booking['name']) . "</strong>,</p>
                    <p>The meeting link for your upcoming consultation has been generated:</p>
                    
                    <div style='background: #f8faff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 20px; text-align: center; margin: 20px 0;'>
                        <p style='margin-bottom: 15px; font-weight: bold;'>Join via Zoom / Google Meet:</p>
                        <a href='$link' style='display: inline-block; background: #1e3a8a; color: white; padding: 12px 25px; text-decoration: none; border-radius: 8px; font-weight: bold;'>Start Meeting</a>
                        <p style='margin-top: 15px; font-size: 0.85em; color: #666;'>Or copy this URL: $link</p>
                    </div>
                                        
                    <hr style='border: 0; border-top: 1px solid #eee; margin: 25px 0;'>
                    <p><strong>Appointment Details:</strong></p>
                    <p>Date: " . date('d-M-Y', strtotime($booking['preferred_date'])) . "<br>Time: " . date('h:i A', strtotime($booking['preferred_time'])) . "</p>
                    <p>Please ensure you are ready 5 minutes before the scheduled time.</p>
                </div>
                <div style='background: #f9fafb; padding: 20px; text-align: center; font-size: 0.85em; color: #777;'>
                    &copy; 2026 AwareX Team.
                </div>
            </div>
        </body>
        </html>
        ";
        
        sendNotificationEmail($booking['email'], $subject, $email_body);

        echo json_encode(['success' => true, 'message' => 'Meeting link shared successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to share meeting link']);
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
/**
 * Handle user response to reschedule request
 */
function handleUserActionOnAppointment() {
    global $conn;
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'Login required']);
        return;
    }

    $user_id = getCurrentUserId();
    $id      = (int)($_POST['appointment_id'] ?? 0);
    $action  = sanitizeInput($_POST['user_action'] ?? '');
    $noti_id = (int)($_POST['notification_id'] ?? 0);

    if (!$id || !$action) {
        echo json_encode(['success' => false, 'message' => 'Missing ID or Action']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM schedule_bookings WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$booking) {
        echo json_encode(['success' => false, 'message' => 'Appointment not found or access denied']);
        return;
    }

    if ($action === 'approve') {
        if (isAppointmentConflicting($booking['preferred_date'], $booking['preferred_time'], $id)) {
            echo json_encode(['success' => false, 'message' => 'Error: This slot was just booked by someone else. Admin will pick another time.']);
            return;
        }

        $stmt = $conn->prepare("UPDATE schedule_bookings SET status='confirmed' WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            $msg = "Appointment for " . date('d-M-Y', strtotime($booking['preferred_date'])) . " has been confirmed by you.";
            if ($noti_id) {
                $stmt_upd = $conn->prepare("UPDATE notifications SET message=?, type='message' WHERE id=? AND user_id=?");
                $stmt_upd->bind_param("sii", $msg, $noti_id, $user_id);
                $stmt_upd->execute();
                $stmt_upd->close();
            }
            echo json_encode(['success' => true, 'message' => 'Appointment confirmed successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to confirm appointment']);
        }
        $stmt->close();
    } else if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM schedule_bookings WHERE id=?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($noti_id) {
                $stmt_noti = $conn->prepare("DELETE FROM notifications WHERE id=?");
                $stmt_noti->bind_param("i", $noti_id);
                $stmt_noti->execute();
                $stmt_noti->close();
            }
            echo json_encode(['success' => true, 'message' => 'Appointment deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete appointment']);
        }
        $stmt->close();
    }
}
/**
 * Get note history for an appointment
 */
function handleAdminGetNotes() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id = (int)($_POST['appointment_id'] ?? 0);
    if (!$id) {
        echo json_encode(['success' => false, 'message' => 'Appointment ID required']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM appointment_notes WHERE appointment_id=? ORDER BY created_at DESC");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success' => true, 'notes' => $notes]);
}

/**
 * Add a new note to the history
 */
function handleAdminAddNote() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $id   = (int)($_POST['appointment_id'] ?? 0);
    $note = sanitizeInput($_POST['admin_note'] ?? '');

    if (!$id || !$note) {
        echo json_encode(['success' => false, 'message' => 'ID and note content are required']);
        return;
    }

    $stmt = $conn->prepare("INSERT INTO appointment_notes (appointment_id, admin_note) VALUES (?, ?)");
    $stmt->bind_param("is", $id, $note);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add note']);
    }
    $stmt->close();
}

/**
 * Delete a specific note entry
 */
function handleAdminDeleteNote() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $note_id = (int)($_POST['note_id'] ?? 0);
    if (!$note_id) {
        echo json_encode(['success' => false, 'message' => 'Note ID required']);
        return;
    }

    $stmt = $conn->prepare("DELETE FROM appointment_notes WHERE id=?");
    $stmt->bind_param("i", $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete note']);
    }
    $stmt->close();
}
/**
 * Update an existing note
 */
function handleAdminUpdateNote() {
    global $conn;
    if (!isAdmin()) {
        echo json_encode(['success' => false, 'message' => 'Admin access required']);
        return;
    }

    $note_id = (int)($_POST['note_id'] ?? 0);
    $new_text = sanitizeInput($_POST['admin_note'] ?? '');

    if (!$note_id || !$new_text) {
        echo json_encode(['success' => false, 'message' => 'Note ID and content required']);
        return;
    }

    $stmt = $conn->prepare("UPDATE appointment_notes SET admin_note=? WHERE id=?");
    $stmt->bind_param("si", $new_text, $note_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Note updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update note']);
    }
    $stmt->close();
}
?>
