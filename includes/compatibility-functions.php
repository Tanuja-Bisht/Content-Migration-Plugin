<?php
/**
 * Compatibility Functions
 * 
 * These functions provide compatibility and safety for serialized data operations.
 */

// Exit if accessed directly
if (!defined('WPINC')) {
    die;
}

/**
 * Safe string position check that handles multibyte strings
 *
 * @param string $haystack The string to search in
 * @param string $needle The string to search for
 * @param int $offset The position to start the search
 * @return int|false Position or false if not found
 */
function wcm_safe_strpos($haystack, $needle, $offset = 0) {
    if (!is_string($haystack) || !is_string($needle)) {
        return false;
    }
    
    if (function_exists('mb_strpos')) {
        return mb_strpos($haystack, $needle, $offset);
    } else {
        return strpos($haystack, $needle, $offset);
    }
}

/**
 * Safe string replacement that handles multibyte strings
 *
 * @param string|array $search The value being searched for (or array of values)
 * @param string|array $replace The replacement value (or array of values)
 * @param string|array $subject The string being searched and replaced
 * @param int &$count Optional count of replacements made
 * @return string|array String or array with replaced values
 */
function wcm_safe_str_replace($search, $replace, $subject, &$count = null) {
    if (!is_string($subject) && !is_array($subject)) {
        return $subject;
    }
    
    if (function_exists('mb_str_replace')) {
        return mb_str_replace($search, $replace, $subject, $count);
    } else {
        return str_replace($search, $replace, $subject, $count);
    }
}

/**
 * Check if a string is serialized
 *
 * @param mixed $data The data to check
 * @return bool Whether the data is serialized
 */
function wcm_safe_is_serialized($data) {
    // If it's not a string, it's not serialized
    if (!is_string($data)) {
        return false;
    }
    
    $data = trim($data);
    if ('N;' === $data) {
        return true;
    }
    
    if (strlen($data) < 4 || ':' !== $data[1]) {
        return false;
    }
    
    // Check for common serialized formats
    if (preg_match('/^[aOs]:/', $data) && preg_match('/[;}]$/', $data)) {
        return true;
    }
    
    // Try unserializing as a fallback (may be slow)
    $value = @unserialize($data);
    return ($value !== false || 'b:0;' === $data);
}

/**
 * Safe unserialize that handles corrupted data
 *
 * @param string $data The serialized data
 * @return mixed The unserialized data or the original string
 */
function wcm_safe_unserialize($data) {
    if (!is_string($data)) {
        return $data;
    }
    
    // Check if serialized
    if (!wcm_safe_is_serialized($data)) {
        return $data;
    }
    
    // Try to unserialize
    $unserialized = @unserialize($data);
    
    // Return the unserialized data or the original if failed
    return ($unserialized !== false || $data === 'b:0;') ? $unserialized : $data;
} 