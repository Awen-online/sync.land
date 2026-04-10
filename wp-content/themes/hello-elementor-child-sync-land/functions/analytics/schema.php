<?php
/**
 * Analytics Database Schema
 *
 * Creates and manages the fml_analytics_events and fml_survey_responses tables.
 * Uses dbDelta() for safe table creation and version-based upgrades.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

define('FML_ANALYTICS_DB_VERSION', '1.0');

/**
 * Create or update analytics database tables
 */
function fml_analytics_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();

    $events_table = $wpdb->prefix . 'fml_analytics_events';
    $survey_table = $wpdb->prefix . 'fml_survey_responses';

    $sql = "CREATE TABLE {$events_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        event_type VARCHAR(50) NOT NULL,
        event_data LONGTEXT DEFAULT NULL,
        page_url VARCHAR(2048) DEFAULT NULL,
        referrer VARCHAR(2048) DEFAULT NULL,
        user_agent VARCHAR(512) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY event_type (event_type),
        KEY created_at (created_at),
        KEY user_id (user_id)
    ) {$charset_collate};

    CREATE TABLE {$survey_table} (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id VARCHAR(36) NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT NULL,
        nps_score TINYINT DEFAULT NULL,
        use_case VARCHAR(255) DEFAULT NULL,
        licensing_ease TINYINT DEFAULT NULL,
        feature_request TEXT DEFAULT NULL,
        how_found_us VARCHAR(100) DEFAULT NULL,
        trigger_type VARCHAR(50) DEFAULT NULL,
        page_url VARCHAR(2048) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY created_at (created_at),
        KEY nps_score (nps_score)
    ) {$charset_collate};";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    update_option('fml_analytics_db_version', FML_ANALYTICS_DB_VERSION);
}

/**
 * Check if tables need creation/update on theme switch or version mismatch
 */
function fml_analytics_check_db() {
    $installed_version = get_option('fml_analytics_db_version', '0');
    if (version_compare($installed_version, FML_ANALYTICS_DB_VERSION, '<')) {
        fml_analytics_create_tables();
    }
}
add_action('after_switch_theme', 'fml_analytics_create_tables');
add_action('admin_init', 'fml_analytics_check_db');
