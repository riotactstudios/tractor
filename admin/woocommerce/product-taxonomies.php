<?php

/**
 * Product Option Categories
 */

function get_most_specific_product_category($product_id) {
	$categories = get_the_terms($product_id, 'product_cat');

  if (empty($categories)) {
  	return null; // No categories found
  }

  // Sort categories by depth (child categories have higher depth)
  usort($categories, function ($a, $b) {
  	return $b->depth - $a->depth;
  });

  return $categories[0]; // Return the first category (most specific)
}

/**
 * Custom Taxonomies
 */

function register_tractor_part_taxonomies() {
    // Register Make taxonomy
    register_taxonomy(
        'make',
        'product',
        array(
            'label' => 'Make',
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'make'),
        )
    );
    
    // Register Model taxonomy
    register_taxonomy(
        'model',
        'product',
        array(
            'label' => 'Model',
            'hierarchical' => true,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'model'),
        )
    );
}
add_action('init', 'register_tractor_part_taxonomies'); 

function tractor_admin_scripts() {
    global $pagenow, $post_type;
    
    // Only load on product edit/add pages
    if (($pagenow == 'post.php' || $pagenow == 'post-new.php') && $post_type == 'product') {
        wp_enqueue_script(
            'tractor-taxonomy-filter',
            plugins_url('assets/js/taxonomy-filter.js', dirname(__FILE__)),
            array('jquery'),
            '1.0',
            true
        );
        
        // Pass important data to JavaScript
        wp_localize_script(
            'tractor-taxonomy-filter',
            'tractorTaxData',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tractor_tax_nonce')
            )
        );
    }
}
add_action('admin_enqueue_scripts', 'tractor_admin_scripts');

// AJAX handler
function get_models_by_make() {
    // Verify nonce
    check_ajax_referer('tractor_tax_nonce', 'nonce');
    
    // Get selected makes
    $makes = isset($_POST['makes']) ? array_map('intval', $_POST['makes']) : [];
    
    if (empty($makes)) {
        wp_send_json_error('No makes selected');
        return;
    }
    
    // Get all models
    $models = get_terms(array(
        'taxonomy' => 'model',
        'hide_empty' => false,
    ));
    
    // Filter models based on their make relationship
    $filtered_models = array();
    
    foreach ($models as $model) {
        $model_makes = get_term_meta($model->term_id, 'related_makes', true);
        
        // If no related makes are set, skip this model
        if (empty($model_makes)) {
            continue;
        }
        
        // Ensure we have an array
        $model_makes = maybe_unserialize($model_makes);
        if (!is_array($model_makes)) {
            $model_makes = array($model_makes);
        }
        
        // Check if any of the selected makes match this model
        $match = false;
        foreach ($makes as $make_id) {
            if (in_array($make_id, $model_makes)) {
                $match = true;
                break;
            }
        }
        
        if ($match) {
            $filtered_models[] = array(
                'id' => $model->term_id,
                'name' => $model->name
            );
        }
    }
    
    wp_send_json_success($filtered_models);
}
add_action('wp_ajax_get_models_by_make', 'get_models_by_make');

// Add a custom field to the model taxonomy add/edit form
function add_model_make_field($term) {
    // Get all makes
    $makes = get_terms(array(
        'taxonomy' => 'make',
        'hide_empty' => false,
    ));
    
    // Get current makes for this model
    $term_id = is_object($term) ? $term->term_id : 0;
    $related_makes = get_term_meta($term_id, 'related_makes', true);
    $related_makes = $related_makes ? maybe_unserialize($related_makes) : array();
    ?>
    <div class="form-field term-related-makes-wrap">
        <label>Related Makes</label>
        <div style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
            <?php foreach ($makes as $make) : ?>
                <label style="display: block; margin-bottom: 5px;">
                    <input type="checkbox" name="related_makes[]" value="<?php echo $make->term_id; ?>" 
                        <?php echo in_array($make->term_id, $related_makes) ? 'checked' : ''; ?>>
                    <?php echo $make->name; ?>
                </label>
            <?php endforeach; ?>
        </div>
        <p class="description">Select which makes this model belongs to.</p>
    </div>
    <?php
}
add_action('model_add_form_fields', 'add_model_make_field');
add_action('model_edit_form_fields', 'add_model_make_field');

// Save the custom field
function save_model_make_field($term_id) {
    if (isset($_POST['related_makes'])) {
        $related_makes = array_map('intval', $_POST['related_makes']);
        update_term_meta($term_id, 'related_makes', $related_makes);
    } else {
        update_term_meta($term_id, 'related_makes', array());
    }
}
add_action('created_model', 'save_model_make_field');
add_action('edited_model', 'save_model_make_field');

// Add custom column to the Model taxonomy admin page
function add_model_custom_column($columns) {
    $new_columns = array();
    
    // Insert the Related Makes column before the posts column
    foreach ($columns as $key => $value) {
        if ($key == 'posts') {
            $new_columns['related_makes'] = 'Related Makes';
        }
        $new_columns[$key] = $value;
    }
    
    return $new_columns;
}
add_filter('manage_edit-model_columns', 'add_model_custom_column');

// Populate the Related Makes column with data
function manage_model_custom_column($content, $column_name, $term_id) {
    if ($column_name == 'related_makes') {
        // Get the related makes for this model
        $related_makes = get_term_meta($term_id, 'related_makes', true);
        
        if (empty($related_makes)) {
            return '—'; // Display a dash if no related makes
        }
        
        // Ensure we have an array
        $related_makes = maybe_unserialize($related_makes);
        if (!is_array($related_makes)) {
            $related_makes = array($related_makes);
        }
        
        // Get the make terms
        $make_terms = array();
        foreach ($related_makes as $make_id) {
            $make = get_term($make_id, 'make');
            if (!is_wp_error($make) && $make) {
                $make_terms[] = '<a href="' . get_edit_term_link($make->term_id, 'make') . '">' . $make->name . '</a>';
            }
        }
        
        // Join the terms with commas
        return implode(', ', $make_terms);
    }
    
    return $content;
}
add_filter('manage_model_custom_column', 'manage_model_custom_column', 10, 3);

// Make the column sortable (optional)
function make_model_custom_column_sortable($columns) {
    $columns['related_makes'] = 'related_makes';
    return $columns;
}
add_filter('manage_edit-model_sortable_columns', 'make_model_custom_column_sortable');