CREATE DATABASE IF NOT EXISTS ssc2027 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE ssc2027;

-- Users table
CREATE TABLE users (
    user_id INT(11) NOT NULL AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    location VARCHAR(100) DEFAULT NULL,
    profile_image VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    role ENUM('learner', 'teacher', 'both', 'admin') DEFAULT 'both',
    verification_status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    skills_summary TEXT DEFAULT NULL,
    avg_rating DECIMAL(3,2) DEFAULT 0.00,
    total_exchanges INT(11) DEFAULT 0,
    completed_exchanges INT(11) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (user_id),
    UNIQUE KEY username (username),
    UNIQUE KEY email (email),
    KEY location (location),
    KEY role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skill Categories table
CREATE TABLE skill_categories (
    category_id INT(11) NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(100) NOT NULL,
    parent_category_id INT(11) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    icon_class VARCHAR(50) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (category_id),
    UNIQUE KEY category_name (category_name),
    KEY parent_category_id (parent_category_id),
    CONSTRAINT skill_categories_ibfk_1 FOREIGN KEY (parent_category_id) REFERENCES skill_categories (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skills Catalog table
CREATE TABLE skills_catalog (
    skill_id INT(11) NOT NULL AUTO_INCREMENT,
    skill_name VARCHAR(100) NOT NULL,
    category VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    difficulty_level ENUM('beginner', 'intermediate', 'advanced') DEFAULT 'beginner',
    parent_skill_id INT(11) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (skill_id),
    UNIQUE KEY skill_name (skill_name),
    KEY category (category),
    KEY difficulty_level (difficulty_level),
    KEY parent_skill_id (parent_skill_id),
    CONSTRAINT skills_catalog_ibfk_1 FOREIGN KEY (parent_skill_id) REFERENCES skills_catalog (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Skills table
CREATE TABLE user_skills (
    user_skill_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) DEFAULT NULL,
    skill_id INT(11) DEFAULT NULL,
    proficiency_level ENUM('beginner', 'intermediate', 'advanced', 'expert') DEFAULT 'intermediate',
    experience_years DECIMAL(3,1) DEFAULT 0.0,
    can_teach TINYINT(1) DEFAULT 1,
    willing_to_teach TINYINT(1) DEFAULT 1,
    willing_to_learn TINYINT(1) DEFAULT 1,
    certification_url VARCHAR(255) DEFAULT NULL,
    portfolio_url VARCHAR(255) DEFAULT NULL,
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (user_skill_id),
    KEY user_id (user_id),
    KEY skill_id (skill_id),
    KEY can_teach (can_teach),
    KEY willing_to_teach (willing_to_teach),
    CONSTRAINT user_skills_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id),
    CONSTRAINT user_skills_ibfk_2 FOREIGN KEY (skill_id) REFERENCES skills_catalog (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Availability table
CREATE TABLE user_availability (
    availability_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) DEFAULT NULL,
    day_of_week TINYINT(4) DEFAULT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    timezone VARCHAR(50) DEFAULT NULL,
    available_for ENUM('teaching', 'learning', 'both') DEFAULT 'both',
    is_recurring TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (availability_id),
    KEY user_id (user_id),
    CONSTRAINT user_availability_ibfk_1 FOREIGN KEY (user_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange Proposals table
CREATE TABLE exchange_proposals (
    exchange_id INT(11) NOT NULL AUTO_INCREMENT,
    proposer_id INT(11) NOT NULL,
    skill_to_learn_id INT(11) NOT NULL,
    skill_to_teach_id INT(11) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    preferred_location VARCHAR(100) DEFAULT NULL,
    exchange_type ENUM('one_on_one', 'group', 'online', 'hybrid') DEFAULT 'one_on_one',
    duration_hours DECIMAL(4,1) DEFAULT NULL,
    sessions_count INT(11) DEFAULT 1,
    schedule_flexibility ENUM('fixed', 'flexible', 'negotiable') DEFAULT 'negotiable',
    status ENUM('pending', 'searching', 'matched', 'in_progress', 'completed', 'cancelled', 'rejected') DEFAULT 'pending',
    match_user_id INT(11) DEFAULT NULL,
    match_skill_id INT(11) DEFAULT NULL,
    started_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (exchange_id),
    KEY proposer_id (proposer_id),
    KEY skill_to_learn_id (skill_to_learn_id),
    KEY skill_to_teach_id (skill_to_teach_id),
    KEY status (status),
    KEY match_user_id (match_user_id),
    KEY match_skill_id (match_skill_id),
    CONSTRAINT exchange_proposals_ibfk_1 FOREIGN KEY (proposer_id) REFERENCES users (user_id),
    CONSTRAINT exchange_proposals_ibfk_2 FOREIGN KEY (skill_to_learn_id) REFERENCES skills_catalog (skill_id),
    CONSTRAINT exchange_proposals_ibfk_3 FOREIGN KEY (skill_to_teach_id) REFERENCES skills_catalog (skill_id),
    CONSTRAINT exchange_proposals_ibfk_4 FOREIGN KEY (match_user_id) REFERENCES users (user_id),
    CONSTRAINT exchange_proposals_ibfk_5 FOREIGN KEY (match_skill_id) REFERENCES skills_catalog (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange Matches table
CREATE TABLE exchange_matches (
    match_id INT(11) NOT NULL AUTO_INCREMENT,
    exchange_id INT(11) NOT NULL,
    acceptor_id INT(11) NOT NULL,
    proposer_skill_id INT(11) NOT NULL,
    acceptor_skill_id INT(11) NOT NULL,
    match_status ENUM('proposed', 'accepted', 'rejected', 'completed') DEFAULT 'proposed',
    terms_agreed TEXT DEFAULT NULL,
    proposer_confirmed TINYINT(1) DEFAULT 0,
    acceptor_confirmed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (match_id),
    KEY exchange_id (exchange_id),
    KEY acceptor_id (acceptor_id),
    KEY proposer_skill_id (proposer_skill_id),
    KEY acceptor_skill_id (acceptor_skill_id),
    CONSTRAINT exchange_matches_ibfk_1 FOREIGN KEY (exchange_id) REFERENCES exchange_proposals (exchange_id),
    CONSTRAINT exchange_matches_ibfk_2 FOREIGN KEY (acceptor_id) REFERENCES users (user_id),
    CONSTRAINT exchange_matches_ibfk_3 FOREIGN KEY (proposer_skill_id) REFERENCES skills_catalog (skill_id),
    CONSTRAINT exchange_matches_ibfk_4 FOREIGN KEY (acceptor_skill_id) REFERENCES skills_catalog (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange Agreements table
CREATE TABLE exchange_agreements (
    agreement_id INT(11) NOT NULL AUTO_INCREMENT,
    exchange_id INT(11) NOT NULL,
    proposer_signature TEXT DEFAULT NULL,
    acceptor_signature TEXT DEFAULT NULL,
    terms TEXT DEFAULT NULL,
    agreement_status ENUM('draft', 'proposed', 'signed', 'cancelled') DEFAULT 'draft',
    signed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (agreement_id),
    KEY exchange_id (exchange_id),
    CONSTRAINT exchange_agreements_ibfk_1 FOREIGN KEY (exchange_id) REFERENCES exchange_proposals (exchange_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange Sessions table
CREATE TABLE exchange_sessions (
    session_id INT(11) NOT NULL AUTO_INCREMENT,
    exchange_id INT(11) NOT NULL,
    session_number INT(11) NOT NULL,
    session_type ENUM('teaching', 'learning') NOT NULL,
    skill_id INT(11) NOT NULL,
    teacher_id INT(11) NOT NULL,
    learner_id INT(11) NOT NULL,
    scheduled_time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    duration_minutes INT(11) DEFAULT 60,
    meeting_url VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    session_status ENUM('scheduled', 'in_progress', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (session_id),
    KEY exchange_id (exchange_id),
    KEY skill_id (skill_id),
    KEY teacher_id (teacher_id),
    KEY learner_id (learner_id),
    KEY scheduled_time (scheduled_time),
    KEY session_status (session_status),
    CONSTRAINT exchange_sessions_ibfk_1 FOREIGN KEY (exchange_id) REFERENCES exchange_proposals (exchange_id),
    CONSTRAINT exchange_sessions_ibfk_2 FOREIGN KEY (skill_id) REFERENCES skills_catalog (skill_id),
    CONSTRAINT exchange_sessions_ibfk_3 FOREIGN KEY (teacher_id) REFERENCES users (user_id),
    CONSTRAINT exchange_sessions_ibfk_4 FOREIGN KEY (learner_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exchange Reviews table
CREATE TABLE exchange_reviews (
    review_id INT(11) NOT NULL AUTO_INCREMENT,
    exchange_id INT(11) NOT NULL,
    reviewer_id INT(11) NOT NULL,
    reviewee_id INT(11) NOT NULL,
    skill_id INT(11) NOT NULL,
    rating TINYINT(4) DEFAULT NULL,
    review_type ENUM('as_teacher', 'as_learner') NOT NULL,
    title VARCHAR(200) DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    teacher_rating TINYINT(4) DEFAULT NULL,
    communication_rating TINYINT(4) DEFAULT NULL,
    punctuality_rating TINYINT(4) DEFAULT NULL,
    would_recommend TINYINT(1) DEFAULT 1,
    is_approved TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (review_id),
    KEY exchange_id (exchange_id),
    KEY reviewer_id (reviewer_id),
    KEY reviewee_id (reviewee_id),
    KEY skill_id (skill_id),
    CONSTRAINT exchange_reviews_ibfk_1 FOREIGN KEY (exchange_id) REFERENCES exchange_proposals (exchange_id),
    CONSTRAINT exchange_reviews_ibfk_2 FOREIGN KEY (reviewer_id) REFERENCES users (user_id),
    CONSTRAINT exchange_reviews_ibfk_3 FOREIGN KEY (reviewee_id) REFERENCES users (user_id),
    CONSTRAINT exchange_reviews_ibfk_4 FOREIGN KEY (skill_id) REFERENCES skills_catalog (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Skill Requests table
CREATE TABLE skill_requests (
    request_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    skill_id INT(11) NOT NULL,
    urgency_level ENUM('low', 'medium', 'high') DEFAULT 'medium',
    preferred_learning_method ENUM('in_person', 'online', 'hybrid') DEFAULT 'hybrid',
    max_hours_per_week DECIMAL(4,1) DEFAULT NULL,
    status ENUM('active', 'in_progress', 'fulfilled', 'cancelled') DEFAULT 'active',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP() ON UPDATE CURRENT_TIMESTAMP(),
    PRIMARY KEY (request_id),
    KEY user_id (user_id),
    KEY skill_id (skill_id),
    KEY status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- User Badges table
CREATE TABLE user_badges (
    badge_id INT(11) NOT NULL AUTO_INCREMENT,
    user_id INT(11) NOT NULL,
    badge_type ENUM('skill_master', 'helpful_teacher', 'quick_learner', 'community_hero') NOT NULL,
    badge_tier ENUM('bronze', 'silver', 'gold', 'platinum') DEFAULT 'bronze',
    skill_id INT(11) DEFAULT NULL,
    awarded_for VARCHAR(200) DEFAULT NULL,
    awarded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (badge_id),
    KEY user_id (user_id),
    KEY skill_id (skill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Messages table
CREATE TABLE messages (
    message_id INT(11) NOT NULL AUTO_INCREMENT,
    exchange_id INT(11) DEFAULT NULL,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message_text TEXT NOT NULL,
    message_type ENUM('general', 'exchange_proposal', 'session_coordination') DEFAULT 'general',
    is_read TINYINT(1) DEFAULT 0,
    parent_message_id INT(11) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
    PRIMARY KEY (message_id),
    KEY exchange_id (exchange_id),
    KEY sender_id (sender_id),
    KEY receiver_id (receiver_id),
    KEY parent_message_id (parent_message_id),
    CONSTRAINT messages_ibfk_1 FOREIGN KEY (sender_id) REFERENCES users (user_id),
    CONSTRAINT messages_ibfk_2 FOREIGN KEY (receiver_id) REFERENCES users (user_id),
    CONSTRAINT messages_ibfk_3 FOREIGN KEY (exchange_id) REFERENCES exchange_proposals (exchange_id),
    CONSTRAINT messages_ibfk_4 FOREIGN KEY (parent_message_id) REFERENCES messages (message_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default Admin User
INSERT INTO users (username, email, password_hash, full_name, phone, role, location) VALUES
('admin', 'admin@liddy.com', '$2y$10$C3eb0KIREMMCgUM9cMncuea7DdPAvAGCtEZ.wZ49za9vMQf7ci1JS', 'System Administrator', '0244123456', 'admin', 'Accra');
