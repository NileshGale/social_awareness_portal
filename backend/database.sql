-- ================================================================
--  AWAREX - COMPLETE DATABASE SCHEMA
--  Target: MySQL / MariaDB (XAMPP)
-- ================================================================

DROP DATABASE IF EXISTS social_awareness_portal;
CREATE DATABASE social_awareness_portal
    CHARACTER SET utf8mb4
    COLLATE       utf8mb4_unicode_ci;

USE social_awareness_portal;

SET FOREIGN_KEY_CHECKS = 0;
SET sql_mode            = '';
SET time_zone           = '+05:30';

-- ================================================================
--  TABLE 1: users
-- ================================================================
CREATE TABLE users (
    id            INT           NOT NULL AUTO_INCREMENT,
    full_name     VARCHAR(255)  NOT NULL DEFAULT 'User',
    email         VARCHAR(255)  NOT NULL,
    mobile        VARCHAR(15)   NOT NULL DEFAULT '',
    dob           DATE          NOT NULL DEFAULT '2000-01-01',
    age           INT           NOT NULL DEFAULT 18,
    gender        ENUM('Male','Female','Other') NOT NULL DEFAULT 'Other',
    is_admin      TINYINT(1)    NOT NULL DEFAULT 0,
    password      VARCHAR(255)  NOT NULL,
    profile_image VARCHAR(255)  NOT NULL DEFAULT 'default-profile.png',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY   (id),
    UNIQUE KEY    uq_email  (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 2: otp_records
-- ================================================================
CREATE TABLE otp_records (
    id         INT          NOT NULL AUTO_INCREMENT,
    email      VARCHAR(255) NOT NULL,
    otp        VARCHAR(6)   NOT NULL,
    purpose    ENUM('registration','password_reset','email_change') NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME     DEFAULT NULL,
    is_used    TINYINT(1)   NOT NULL DEFAULT 0,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 3: campaigns
-- ================================================================
CREATE TABLE campaigns (
    id             INT          NOT NULL AUTO_INCREMENT,
    user_id        INT          NOT NULL,
    title          VARCHAR(255) NOT NULL,
    description    TEXT         NOT NULL,
    media_path     VARCHAR(255) DEFAULT NULL,
    media_type     ENUM('image','video') DEFAULT NULL,
    likes_count    INT          NOT NULL DEFAULT 0,
    shares_count   INT          NOT NULL DEFAULT 0,
    comments_count INT          NOT NULL DEFAULT 0,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_camp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 4: campaign_likes
-- ================================================================
CREATE TABLE campaign_likes (
    id          INT      NOT NULL AUTO_INCREMENT,
    campaign_id INT      NOT NULL,
    user_id     INT      NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_campaign_like (campaign_id, user_id),
    CONSTRAINT fk_cl_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_cl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 5: campaign_comments
-- ================================================================
CREATE TABLE campaign_comments (
    id          INT      NOT NULL AUTO_INCREMENT,
    campaign_id INT      NOT NULL,
    user_id     INT      NOT NULL,
    comment     TEXT     NOT NULL,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_cc_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
    CONSTRAINT fk_cc_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 6: contact_submissions
-- ================================================================
CREATE TABLE contact_submissions (
    id         INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    subject    VARCHAR(255) NOT NULL,
    message    TEXT         NOT NULL,
    is_read    TINYINT(1)   NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 7: feedback
-- ================================================================
CREATE TABLE feedback (
    id         INT     NOT NULL AUTO_INCREMENT,
    user_id    INT     DEFAULT NULL,
    name       VARCHAR(255) NOT NULL,
    email      VARCHAR(255) NOT NULL,
    category   ENUM('women','child','cyber','social','mental','handsign') NOT NULL,
    message    TEXT    NOT NULL,
    status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    likes      INT     NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_fb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 8: feedback_replies
-- ================================================================
CREATE TABLE feedback_replies (
    id              INT          NOT NULL AUTO_INCREMENT,
    feedback_id     INT          NOT NULL,
    admin_name      VARCHAR(150) NOT NULL DEFAULT 'AwareX Team',
    reply           TEXT         NOT NULL,
    useful_count    INT          NOT NULL DEFAULT 0,
    not_useful_count INT         NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feedback_reply (feedback_id),
    CONSTRAINT fk_fr_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 9: feedback_likes
-- ================================================================
CREATE TABLE feedback_likes (
    id          INT          NOT NULL AUTO_INCREMENT,
    feedback_id INT          NOT NULL,
    user_id     INT          DEFAULT NULL,
    guest_token VARCHAR(100) DEFAULT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_feedback_user_like (feedback_id, user_id),
    UNIQUE KEY uq_feedback_guest_like (feedback_id, guest_token),
    CONSTRAINT fk_fl_feedback FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
    CONSTRAINT fk_fl_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 10: reply_votes
-- ================================================================
CREATE TABLE reply_votes (
    id          INT         NOT NULL AUTO_INCREMENT,
    reply_id    INT         NOT NULL,
    user_id     INT         DEFAULT NULL,
    guest_token VARCHAR(100) DEFAULT NULL,
    vote        ENUM('useful','not_useful') NOT NULL,
    created_at  DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reply_user_vote (reply_id, user_id),
    CONSTRAINT fk_rv_reply FOREIGN KEY (reply_id) REFERENCES feedback_replies(id) ON DELETE CASCADE,
    CONSTRAINT fk_rv_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ================================================================
--  TABLE 11: schedule_bookings
-- ================================================================
CREATE TABLE schedule_bookings (
    id              INT          NOT NULL AUTO_INCREMENT,
    user_id         INT          DEFAULT NULL,
    name            VARCHAR(255) NOT NULL,
    email           VARCHAR(255) NOT NULL,
    mobile          VARCHAR(15)  NOT NULL,
    problem_desc    TEXT         NOT NULL,
    preferred_date  DATE         NOT NULL,
    preferred_time  TIME         NOT NULL,
    meet_link       VARCHAR(500) DEFAULT NULL,
    status          ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_sb_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ================================================================
--  SEED DATA: Admin User
--  Password: '@Dhanu#05'
-- ================================================================
INSERT INTO users (id, full_name, email, mobile, dob, age, gender, is_admin, password)
VALUES (1, 'Dhanashree Game', 'dhanashreegame@gmail.com', '1111100000', '2005-10-19', 20, 'Female', 1, '$2y$10$4KKyRIpZ8cichGUrhciojOBhXV7gKULVIEHlcV.PaA6JPpPVunEXK');

-- ================================================================
--  SEED DATA: Regular Users
--  Passwords: 'Demo@1234'
-- ================================================================
INSERT INTO users (id, full_name, email, mobile, dob, age, gender, is_admin, password) VALUES
(2,  'Priya Sharma',   'priya.sharma@example.com',   '9876500001', '1995-03-14', 30, 'Female', 0, '$2y$10$TKh8H1.PfuA2iofE4/1hUO8K.QVGjx.VFg0oi.VqP6nzgajPvRiHG'),
(3,  'Rahul Mehta',    'rahul.mehta@example.com',    '9876500002', '1990-07-22', 35, 'Male',   0, '$2y$10$TKh8H1.PfuA2iofE4/1hUO8K.QVGjx.VFg0oi.VqP6nzgajPvRiHG'),
(4,  'Ananya Desai',   'ananya.desai@example.com',   '9876500003', '2002-11-05', 23, 'Female', 0, '$2y$10$TKh8H1.PfuA2iofE4/1hUO8K.QVGjx.VFg0oi.VqP6nzgajPvRiHG'),
(5,  'Suresh Patil',   'suresh.patil@example.com',   '9876500004', '1980-04-18', 45, 'Male',   0, '$2y$10$TKh8H1.PfuA2iofE4/1hUO8K.QVGjx.VFg0oi.VqP6nzgajPvRiHG'),
(6,  'Meera Joshi',    'meera.joshi@example.com',    '9876500005', '1998-09-30', 27, 'Female', 0, '$2y$10$TKh8H1.PfuA2iofE4/1hUO8K.QVGjx.VFg0oi.VqP6nzgajPvRiHG');

-- ================================================================
--  SEED DATA: Campaigns
-- ================================================================
INSERT INTO campaigns (id, user_id, title, description, likes_count) VALUES
(1, 2, 'Women Safety Awareness Walk — Nagpur 2026', 'We organised a 2 km awareness walk in Nagput to highlight women safety.', 47),
(2, 5, 'Child Protection Workshop', 'A workshop covering topics like good touch and bad touch.', 63);

-- ================================================================
--  SEED DATA: Feedback
-- ================================================================
INSERT INTO feedback (id, user_id, name, email, category, message, status, likes) VALUES
(1, 2, 'Priya Sharma', 'priya.sharma@example.com', 'women', 'The portal is truly inspiring.', 'approved', 3),
(2, 3, 'Rahul Mehta', 'rahul.mehta@example.com', 'cyber', 'Excellent cyber safety resources.', 'approved', 2);

-- ================================================================
--  SEED DATA: Admin Replies
-- ================================================================
INSERT INTO feedback_replies (id, feedback_id, admin_name, reply, useful_count) VALUES
(1, 1, 'AwareX Team', 'Thank you Priya! We are glad it helped.', 5),
(2, 2, 'AwareX Team', 'We are glad your father was protected Rahul!', 8);

-- Reset Auto-Increments
ALTER TABLE users AUTO_INCREMENT = 100;
ALTER TABLE campaigns AUTO_INCREMENT = 100;
ALTER TABLE feedback AUTO_INCREMENT = 100;
ALTER TABLE contact_submissions AUTO_INCREMENT = 100;
ALTER TABLE schedule_bookings AUTO_INCREMENT = 100;

-- ================================================================
--  VERIFICATION QUERIES
-- ================================================================
-- SELECT count(*) from users;
-- SELECT count(*) from feedback;