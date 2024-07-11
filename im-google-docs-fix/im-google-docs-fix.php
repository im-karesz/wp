<?php
/*
Plugin Name: IM Google Docs Copy-Paste fix
Plugin URI: https://github.com/im-karesz/wp
Description: Egy bővítmény, amely eltávolítja a Google Docs másolás-beillesztés során beszúrt felesleges span tageket.
Version: 1.0.0
Author: im-karesz
Author URI: https://github.com/im-karesz
License: GPL2
GitHub Plugin URI: https://github.com/im-karesz/wp
GitHub Branch: main
*/

// Your plugin code here
function remove_span_tags_from_content($content) {
    return preg_replace('/<span style="font-weight: 400;">(.*?)<\/span>/is', '$1', $content);
}

function clean_acf_meta_fields($post_id) {
    if (function_exists('get_field_objects')) {
        $fields = get_field_objects($post_id);
        if ($fields) {
            foreach ($fields as $field) {
                $value = get_field($field['name'], $post_id);
                if (is_string($value)) {
                    // Only clean if the <span style="font-weight: 400;"> tag is present
                    if (strpos($value, '<span style="font-weight: 400;">') !== false) {
                        $clean_value = remove_span_tags_from_content($value);
                        if ($clean_value !== $value) {
                            update_field($field['key'], $clean_value, $post_id);
                        }
                    }
                } elseif (is_array($value)) {
                    $clean_value = array_map_recursive(function($item) {
                        if (is_string($item) && strpos($item, '<span style="font-weight: 400;">') !== false) {
                            return remove_span_tags_from_content($item);
                        }
                        return $item;
                    }, $value);
                    if ($clean_value !== $value) {
                        update_field($field['key'], $clean_value, $post_id);
                    }
                }
            }
        }
    }
}

function array_map_recursive($callback, $array) {
    $func = function ($item) use (&$func, &$callback) {
        return is_array($item) ? array_map($func, $item) : $callback($item);
    };
    return array_map($func, $array);
}

// Hook for saving ACF fields
add_action('acf/save_post', 'clean_acf_meta_fields', 20);

function remove_span_tags_on_save($post_id) {
    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    // Check user permissions
    if (isset($_POST['post_type']) && ($_POST['post_type'] == 'post' || $_POST['post_type'] == 'page')) {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }

    // Remove span tags from the main content
    $post_content = get_post_field('post_content', $post_id);
    if (strpos($post_content, '<span style="font-weight: 400;">') !== false) {
        $clean_content = remove_span_tags_from_content($post_content);
        if ($clean_content !== $post_content) {
            remove_action('save_post', 'remove_span_tags_on_save'); // Prevent infinite loop
            wp_update_post(array(
                'ID' => $post_id,
                'post_content' => $clean_content,
            ));
            add_action('save_post', 'remove_span_tags_on_save'); // Re-hook
        }
    }
}

// Hook for saving the post
add_action('save_post', 'remove_span_tags_on_save');
/* End of IM Google Docs Copy-Paste fix */
