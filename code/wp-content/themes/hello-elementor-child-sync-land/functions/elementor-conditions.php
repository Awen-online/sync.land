<?php
/**
 * Custom Elementor Theme Builder Conditions for Genre and Mood taxonomies
 */

if (!defined('ABSPATH')) {
    exit;
}

// Make sure Elementor Pro is active and the base class exists
if (!class_exists('\ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base')) {
    return;
}

use ElementorPro\Modules\ThemeBuilder\Conditions\Condition_Base;

/**
 * Genre Archive Condition
 */
class FML_Genre_Archive_Condition extends Condition_Base {

    public static function get_type() {
        return 'archive';
    }

    public static function get_priority() {
        return 40;
    }

    public function get_name() {
        return 'genre_archive';
    }

    public function get_label() {
        return 'Genre Archive';
    }

    public function get_all_label() {
        return 'All Genre Archives';
    }

    public function check($args) {
        if (isset($args['id'])) {
            return is_tax('genre', $args['id']);
        }
        return is_tax('genre');
    }

    public function register_sub_conditions() {
        // Don't register individual terms to avoid complexity
    }
}

/**
 * Mood Archive Condition
 */
class FML_Mood_Archive_Condition extends Condition_Base {

    public static function get_type() {
        return 'archive';
    }

    public static function get_priority() {
        return 40;
    }

    public function get_name() {
        return 'mood_archive';
    }

    public function get_label() {
        return 'Mood Archive';
    }

    public function get_all_label() {
        return 'All Mood Archives';
    }

    public function check($args) {
        if (isset($args['id'])) {
            return is_tax('mood', $args['id']);
        }
        return is_tax('mood');
    }

    public function register_sub_conditions() {
        // Don't register individual terms to avoid complexity
    }
}
