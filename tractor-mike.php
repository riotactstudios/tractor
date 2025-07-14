<?php
/**
 * Plugin Name: Tractor Mike
 * Plugin URI: https://riotactstudios.com
 * Description: Tractor Mike Plugin.
 * Version: 1.3.3
 * Author: Matt Harris
 * Author URI: https://riotactstudios.com/
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get Required Files
 */

require_once(dirname(__FILE__) .'/admin/acf/qa-configure.php');
require_once(dirname(__FILE__) .'/admin/woocommerce/product-data.php');
require_once(dirname(__FILE__) .'/admin/woocommerce/product-taxonomies.php');
//require_once(dirname(__FILE__) .'/admin/woocommerce/product-templates.php');
require_once(dirname(__FILE__) .'/admin/woocommerce/woocommerce-enhancements.php');

/**
 * Enqueue Scripts
 */ 

add_action('admin_enqueue_scripts', 'qa_enqueue_admin_styles');

function qa_enqueue_admin_styles($hook) {
    global $typenow;

    // Only enqueue on product edit/new screens
    if (in_array($hook, ['post.php', 'post-new.php']) && 'product' === $typenow) {
        wp_enqueue_style('qa-admin-styles', plugin_dir_url(__FILE__) . 'admin/assets/css/admin.css', array(), '1.0.0', 'all' );
    }
}


class WC_Configurable_Products {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new WC_Configurable_Products();
        }
        return self::$instance;
    }
    
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }
    
    public function init() {
        // Check if WooCommerce and ACF are active
        if (!class_exists('WooCommerce') || !function_exists('get_field')) {
            add_action('admin_notices', array($this, 'missing_dependencies_notice'));
            return;
        }
        
        // Initialize hooks
        $this->init_hooks();
    }
    
    private function init_hooks() {
        // Add configuration selector to single product page
        add_action('woocommerce_single_product_summary', array($this, 'display_configurable_selector'), 25);
        
        // Cart integration hooks
        add_filter('woocommerce_add_to_cart_validation', array($this, 'validate_configurable_add_to_cart'), 10, 6);
        add_filter('woocommerce_add_cart_item_data', array($this, 'add_configurable_cart_item_data'), 10, 4);
        add_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10, 6);
        add_action('woocommerce_cart_item_removed', array($this, 'remove_grouped_items'), 10, 2);
        
        // Custom cart display hooks
        add_filter('woocommerce_cart_item_name', array($this, 'display_configurable_cart_item_name'), 10, 3);
        add_filter('woocommerce_cart_item_class', array($this, 'add_configurable_cart_item_class'), 10, 3);
        add_filter('woocommerce_cart_item_quantity', array($this, 'disable_child_quantity_input'), 10, 3);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add shortcode support
        add_shortcode('wc_configurable_selector', array($this, 'configurable_selector_shortcode'));
    }
    
    /**
     * Get sizes data from ACF repeater field nested inside quick_attach group
     */
    public function get_product_sizes($product_id) {
        $sizes_data = array();
        
        // Check if configuration is set to 'qa'
        $configuration = get_field('configuration', $product_id);
        
        if ($configuration && in_array('qa', (array)$configuration)) {
            // Access the quick_attach group
            $quick_attach = get_field('quick_attach', $product_id);
            
            if ($quick_attach && isset($quick_attach['sizes']) && is_array($quick_attach['sizes'])) {
                // Manually loop through the sizes repeater subfield
                foreach ($quick_attach['sizes'] as $size_row) {
                    $width = isset($size_row['width']) ? $size_row['width'] : '';
                    $buckets = array();
                    
                    if (isset($size_row['buckets']) && is_array($size_row['buckets'])) {
                        foreach ($size_row['buckets'] as $bucket_row) {
                            $bucket_product = isset($bucket_row['bucket']) ? $bucket_row['bucket'] : '';
                            $accessories = array();
                            
                            if (isset($bucket_row['accessories']) && is_array($bucket_row['accessories'])) {
                                foreach ($bucket_row['accessories'] as $accessory_row) {
                                    $accessory_product = isset($accessory_row['accessory']) ? $accessory_row['accessory'] : '';
                                    
                                    if ($accessory_product) {
                                        $accessories[] = array(
                                            'product_id' => $accessory_product,
                                            'product_data' => $this->get_bucket_product_data($accessory_product)
                                        );
                                    }
                                }
                            }
                            
                            if ($bucket_product) {
                                $buckets[] = array(
                                    'product_id' => $bucket_product,
                                    'product_data' => $this->get_bucket_product_data($bucket_product),
                                    'accessories' => $accessories
                                );
                            }
                        }
                    }
                    
                    if ($width) {
                        $sizes_data[] = array(
                            'width' => $width,
                            'buckets' => $buckets
                        );
                    }
                }
            }
        }
        
        return $sizes_data;
    }
    
    /**
     * Get bucket plate data from ACF field nested inside quick_attach group
     */
    public function get_bucket_plate($product_id) {
        // Check if configuration is set to 'qa'
        $configuration = get_field('configuration', $product_id);
        
        if ($configuration && in_array('qa', (array)$configuration)) {
            // Access the quick_attach group
            $quick_attach = get_field('quick_attach', $product_id);
            
            if ($quick_attach && isset($quick_attach['bucket_plate'])) {
                $bucket_plate = $quick_attach['bucket_plate'];
                
                if ($bucket_plate) {
                    return array(
                        'product_id' => $bucket_plate,
                        'product_data' => $this->get_bucket_product_data($bucket_plate)
                    );
                }
            }
        }
        
        return null;
    }
    
    /**
     * Get available widths from sizes
     */
    public function get_available_widths($product_id) {
        $sizes = $this->get_product_sizes($product_id);
        $widths = array();
        
        foreach ($sizes as $size) {
            if (!empty($size['width']) && !in_array($size['width'], $widths)) {
                $widths[] = $size['width'];
            }
        }
        
        return $widths;
    }
    
    /**
     * Get buckets for a specific width
     */
    public function get_buckets_by_width($product_id, $width) {
        $sizes = $this->get_product_sizes($product_id);
        
        foreach ($sizes as $size) {
            if (trim($size['width']) == trim($width) || $size['width'] == $width) {
                return $size['buckets'];
            }
        }
        
        return array();
    }
    
    /**
     * Get product data for a bucket or accessory
     */
    private function get_bucket_product_data($bucket_product_id) {
        if (!$bucket_product_id) {
            return array();
        }
        
        $product = wc_get_product($bucket_product_id);
        
        if (!$product) {
            return array();
        }
        
        return array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'formatted_price' => $product->get_price_html(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'in_stock' => $product->is_in_stock(),
        );
    }
    
    /**
     * Display admin notice for missing dependencies
     */
    public function missing_dependencies_notice() {
        $message = '<div class="notice notice-error"><p>';
        $message .= __('WooCommerce Configurable Products requires WooCommerce and Advanced Custom Fields to be installed and activated.', 'wc-configurable');
        $message .= '</p></div>';
        echo $message;
    }
    
    /**
     * Display configurable selector on single product page
     */
    public function display_configurable_selector() {
        global $product;
        
        if (!$product) return;
        
        $product_id = $product->get_id();
        $this->display_configurable_selector_for_product($product_id);
    }
    
    /**
     * Display bucket plates for Most Economical option
     */
    public function display_bucket_plates($product_id) {
        $bucket_plate = $this->get_bucket_plate($product_id);
        
        if (!$bucket_plate) {
            return;
        }
        
        $product_data = $bucket_plate['product_data'];
        
        echo '<div class="bucket-plate-section">';
        echo '<h4>Additional Bucket Plates:</h4>';
        echo '<div class="bucket-plate-option">';
        echo '<label class="bucket-plate-item">';
        echo '<input type="checkbox" name="bucket_plate" value="' . esc_attr($bucket_plate['product_id']) . '" data-plate-id="plate_' . esc_attr($bucket_plate['product_id']) . '">';
        echo '<div class="bucket-plate-details">';
        echo '<h5>' . esc_html($product_data['name']) . '</h5>';
        echo '<p class="price">' . $product_data['formatted_price'] . '</p>';
        
        if (!empty($product_data['sku'])) {
            echo '<p class="sku">SKU: ' . esc_html($product_data['sku']) . '</p>';
        }
        
        $stock_class = $product_data['in_stock'] ? 'in-stock' : 'out-of-stock';
        $stock_text = $product_data['in_stock'] ? 'In Stock' : 'Out of Stock';
        echo '<p class="stock-status ' . $stock_class . '">' . $stock_text . '</p>';
        
        echo '</div>';
        echo '<div class="quantity-selector" style="display: none;">';
        echo '<label>Qty:</label>';
        echo '<input type="number" name="bucket_plate_qty" value="1" min="1" max="999">';
        echo '</div>';
        echo '</label>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Display buckets for a specific size
     */
    public function display_buckets_for_size($product_id, $width) {
        $buckets = $this->get_buckets_by_width($product_id, $width);
        
        if (empty($buckets)) {
            echo '<p>No products available for this size.</p>';
            return;
        }
        
        echo '<h4>Select Product:</h4>';
        echo '<div class="bucket-options">';
        
        foreach ($buckets as $index => $bucket) {
            $product_data = $bucket['product_data'];
            $checked = $index === 0 ? 'checked' : '';
            
            echo '<label class="bucket-option">';
            echo '<input type="radio" name="selected_bucket" value="' . esc_attr($bucket['product_id']) . '" ' . $checked . ' data-bucket-id="bucket_' . esc_attr($width) . '_' . $index . '">';
            echo '<div class="bucket-details">';
            echo '<h5>' . esc_html($product_data['name']) . '</h5>';
            echo '<p class="price">' . $product_data['formatted_price'] . '</p>';
            
            if (!empty($product_data['sku'])) {
                echo '<p class="sku">SKU: ' . esc_html($product_data['sku']) . '</p>';
            }
            
            $stock_class = $product_data['in_stock'] ? 'in-stock' : 'out-of-stock';
            $stock_text = $product_data['in_stock'] ? 'In Stock' : 'Out of Stock';
            echo '<p class="stock-status ' . $stock_class . '">' . $stock_text . '</p>';
            
            echo '</div>';
            echo '</label>';
        }
        
        echo '</div>';
    }
    
    /**
     * Validate configurable product add to cart
     */
    public function validate_configurable_add_to_cart($passed, $product_id, $quantity, $variation_id = 0, $variations = array(), $cart_item_data = array()) {
        if (!isset($_POST['wc_configurable_nonce']) || !wp_verify_nonce($_POST['wc_configurable_nonce'], 'wc_configurable_nonce')) {
            wc_add_notice(__('Security check failed.', 'wc-configurable'), 'error');
            return false;
        }
        
        $sizes = $this->get_product_sizes($product_id);
        $bucket_plate = $this->get_bucket_plate($product_id);
        
        if (empty($sizes) && !$bucket_plate) {
            return $passed;
        }
        
        if (!isset($_POST['configuration_type'])) {
            if ($bucket_plate && empty($sizes)) {
                $_POST['configuration_type'] = 'economical';
            } elseif (!empty($sizes) && !$bucket_plate) {
                $_POST['configuration_type'] = 'installation';
            } else {
                wc_add_notice(__('Please select a configuration option.', 'wc-configurable'), 'error');
                return false;
            }
        }
        
        $configuration_type = sanitize_text_field($_POST['configuration_type']);
        
        if ($configuration_type === 'installation') {
            if (!isset($_POST['product_size']) || empty($_POST['product_size'])) {
                wc_add_notice(__('Please select a size.', 'wc-configurable'), 'error');
                return false;
            }
            
            $selected_size = sanitize_text_field($_POST['product_size']);
            $buckets_for_size = $this->get_buckets_by_width($product_id, $selected_size);
            
            if (!empty($buckets_for_size)) {
                if (!isset($_POST['selected_bucket']) || empty($_POST['selected_bucket'])) {
                    wc_add_notice(__('Please select a product for the chosen size.', 'wc-configurable'), 'error');
                    return false;
                }
                
                $selected_bucket = intval($_POST['selected_bucket']);
                $bucket_product = wc_get_product($selected_bucket);
                if (!$bucket_product || !$bucket_product->is_in_stock()) {
                    wc_add_notice(__('The selected product is out of stock.', 'wc-configurable'), 'error');
                    return false;
                }
            }
            
            if (!empty($_POST['accessories']) && is_array($_POST['accessories'])) {
                foreach ($_POST['accessories'] as $accessory_id) {
                    $accessory_product = wc_get_product(intval($accessory_id));
                    if (!$accessory_product || !$accessory_product->is_in_stock()) {
                        wc_add_notice(sprintf(__('Accessory "%s" is out of stock.', 'wc-configurable'), $accessory_product ? $accessory_product->get_name() : 'Unknown'), 'error');
                        return false;
                    }
                    if (!empty($_POST['accessory_qty'][$accessory_id]) && intval($_POST['accessory_qty'][$accessory_id]) < 1) {
                        wc_add_notice(__('Accessory quantity must be at least 1.', 'wc-configurable'), 'error');
                        return false;
                    }
                }
            }
        } elseif ($configuration_type === 'economical') {
            if (!empty($_POST['bucket_plate'])) {
                $plate_id = intval($_POST['bucket_plate']);
                $plate_product = wc_get_product($plate_id);
                if (!$plate_product || !$plate_product->is_in_stock()) {
                    wc_add_notice(__('The selected bucket plate is out of stock.', 'wc-configurable'), 'error');
                    return false;
                }
                if (!empty($_POST['bucket_plate_qty']) && intval($_POST['bucket_plate_qty']) < 1) {
                    wc_add_notice(__('Bucket plate quantity must be at least 1.', 'wc-configurable'), 'error');
                    return false;
                }
            }
        }
        
        return $passed;
    }
    
    /**
     * Add configurable data to cart item
     */
    public function add_configurable_cart_item_data($cart_item_data, $product_id, $variation_id, $quantity) {
        $sizes = $this->get_product_sizes($product_id);
        $bucket_plate = $this->get_bucket_plate($product_id);
        
        if (empty($sizes) && !$bucket_plate) {
            return $cart_item_data;
        }
        
        $group_id = uniqid('config_group_');
        $configuration_type = !empty($_POST['configuration_type']) ? sanitize_text_field($_POST['configuration_type']) : 
                             ($bucket_plate && empty($sizes) ? 'economical' : (!empty($sizes) && !$bucket_plate ? 'installation' : ''));

        if (empty($configuration_type)) {
            return $cart_item_data;
        }
        
        $configurable_data = array(
            'group_id' => $group_id,
            'is_main_product' => true,
            'configuration_type' => $configuration_type
        );
        
        if ($configuration_type === 'installation') {
            $configurable_data['selected_size'] = !empty($_POST['product_size']) ? sanitize_text_field($_POST['product_size']) : null;
            $configurable_data['selected_bucket'] = !empty($_POST['selected_bucket']) ? intval($_POST['selected_bucket']) : null;
            $configurable_data['accessories'] = $this->get_posted_accessories();
        } elseif ($configuration_type === 'economical') {
            $configurable_data['bucket_plate'] = !empty($_POST['bucket_plate']) ? intval($_POST['bucket_plate']) : null;
            $configurable_data['bucket_plate_qty'] = !empty($_POST['bucket_plate_qty']) ? intval($_POST['bucket_plate_qty']) : 1;
        }
        
        $cart_item_data['configurable_data'] = $configurable_data;
        
        return $cart_item_data;
    }
    
    /**
     * Add configurable items to cart
     */
    public function add_configurable_items_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
        // Guard against recursive calls - only process main products
        if (empty($cart_item_data['configurable_data']) || 
            !isset($cart_item_data['configurable_data']['configuration_type']) ||
            !isset($cart_item_data['configurable_data']['is_main_product']) ||
            !$cart_item_data['configurable_data']['is_main_product']) {
            return;
        }
        
        $config_data = $cart_item_data['configurable_data'];
        $group_id = $config_data['group_id'];
        
        // Temporarily remove this hook to prevent recursive calls
        remove_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10);
        
        if ($config_data['configuration_type'] === 'installation') {
            // Add the selected bucket to cart
            $bucket_id = isset($config_data['selected_bucket']) ? $config_data['selected_bucket'] : null;
            
            if ($bucket_id && $bucket_id != $product_id) {
                $bucket_added = WC()->cart->add_to_cart($bucket_id, $quantity, 0, array(), array(
                    'configurable_data' => array(
                        'group_id' => $group_id,
                        'is_bucket' => true,
                        'parent_cart_key' => $cart_item_key,
                        'size' => $config_data['selected_size']
                    )
                ));
                
                if (!$bucket_added) {
                    wc_add_notice(__('Failed to add the selected product to the cart.', 'wc-configurable'), 'error');
                    WC()->cart->remove_cart_item($cart_item_key);
                    add_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10, 6);
                    return;
                }
            }
            
            // Add accessories to cart
            if (!empty($config_data['accessories'])) {
                foreach ($config_data['accessories'] as $accessory) {
                    $accessory_added = WC()->cart->add_to_cart($accessory['product_id'], $accessory['quantity'], 0, array(), array(
                        'configurable_data' => array(
                            'group_id' => $group_id,
                            'is_accessory' => true,
                            'parent_cart_key' => $cart_item_key,
                            'size' => $config_data['selected_size']
                        )
                    ));
                    
                    if (!$accessory_added) {
                        wc_add_notice(sprintf(__('Failed to add accessory "%s" to the cart.', 'wc-configurable'), wc_get_product($accessory['product_id'])->get_name()), 'error');
                        WC()->cart->remove_cart_item($cart_item_key);
                        add_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10, 6);
                        return;
                    }
                }
            }
        } elseif ($config_data['configuration_type'] === 'economical') {
            // Add bucket plate to cart
            if (!empty($config_data['bucket_plate'])) {
                $plate_added = WC()->cart->add_to_cart($config_data['bucket_plate'], $config_data['bucket_plate_qty'], 0, array(), array(
                    'configurable_data' => array(
                        'group_id' => $group_id,
                        'is_bucket_plate' => true,
                        'parent_cart_key' => $cart_item_key
                    )
                ));
                
                if (!$plate_added) {
                    wc_add_notice(__('Failed to add bucket plate to the cart.', 'wc-configurable'), 'error');
                    WC()->cart->remove_cart_item($cart_item_key);
                    add_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10, 6);
                    return;
                }
            }
        }
        
        // Re-add the hook
        add_action('woocommerce_add_to_cart', array($this, 'add_configurable_items_to_cart'), 10, 6);
    }

    /**
     * Get posted accessories data
     */
    private function get_posted_accessories() {
        $accessories = array();
        
        if (!empty($_POST['accessories']) && is_array($_POST['accessories'])) {
            foreach ($_POST['accessories'] as $accessory_id) {
                $quantity = 1;
                if (!empty($_POST['accessory_qty'][$accessory_id])) {
                    $quantity = intval($_POST['accessory_qty'][$accessory_id]);
                }
                
                $accessories[] = array(
                    'product_id' => intval($accessory_id),
                    'quantity' => $quantity
                );
            }
        }
        
        return $accessories;
    }
    
    /**
     * Customize cart item name for hierarchical display
     */
    public function display_configurable_cart_item_name($item_name, $cart_item, $cart_item_key) {
        if (empty($cart_item['configurable_data']) || !isset($cart_item['configurable_data']['configuration_type'])) {
            return $item_name;
        }
        
        $config_data = $cart_item['configurable_data'];
        $prefix = '';
        
        if (!empty($config_data['is_main_product'])) {
            $config_label = $config_data['configuration_type'] === 'economical' ? 'Most Economical' : 'Easiest Installation';
            $size_label = !empty($config_data['selected_size']) ? ' (' . esc_html($config_data['selected_size']) . ')' : '';
            $prefix = '<strong>Configuration: ' . $config_label . $size_label . '</strong><br>';
        } elseif (!empty($config_data['is_bucket'])) {
            $prefix = '<span style="margin-left: 20px;">→ Product: </span>';
        } elseif (!empty($config_data['is_accessory'])) {
            $prefix = '<span style="margin-left: 20px;">→ Accessory: </span>';
        } elseif (!empty($config_data['is_bucket_plate'])) {
            $prefix = '<span style="margin-left: 20px;">→ Bucket Plate: </span>';
        }
        
        return $prefix . $item_name;
    }
    
    /**
     * Add CSS class to cart items for styling
     */
    public function add_configurable_cart_item_class($class, $cart_item, $cart_item_key) {
        if (empty($cart_item['configurable_data'])) {
            return $class;
        }
        
        $config_data = $cart_item['configurable_data'];
        if (!empty($config_data['is_main_product'])) {
            $class .= ' configurable-main-product';
        } elseif (!empty($config_data['is_bucket'])) {
            $class .= ' configurable-bucket';
        } elseif (!empty($config_data['is_accessory'])) {
            $class .= ' configurable-accessory';
        } elseif (!empty($config_data['is_bucket_plate'])) {
            $class .= ' configurable-bucket-plate';
        }
        
        return $class;
    }
    
    /**
     * Disable quantity input for child items
     */
    public function disable_child_quantity_input($quantity_html, $cart_item_key, $cart_item) {
        if (empty($cart_item['configurable_data'])) {
            return $quantity_html;
        }
        
        $config_data = $cart_item['configurable_data'];
        if (!empty($config_data['is_bucket']) || !empty($config_data['is_accessory']) || !empty($config_data['is_bucket_plate'])) {
            return $cart_item['quantity'];
        }
        
        return $quantity_html;
    }
    
    /**
     * Remove grouped items when any item is removed
     */
    public function remove_grouped_items($cart_item_key, $cart) {
        $removed_item = $cart->removed_cart_contents[$cart_item_key];
        
        if (!empty($removed_item['configurable_data']['group_id'])) {
            $group_id = $removed_item['configurable_data']['group_id'];
            
            foreach ($cart->cart_contents as $key => $item) {
                if (!empty($item['configurable_data']['group_id']) && 
                    $item['configurable_data']['group_id'] === $group_id &&
                    $key !== $cart_item_key) {
                    $cart->remove_cart_item($key);
                }
            }
        }
    }
    
    /**
     * AJAX handler to get buckets for selected size
     */
    public function ajax_get_buckets_for_size() {
        if (!wp_verify_nonce($_POST['nonce'], 'wc_configurable_nonce')) {
            wp_die('Security check failed');
        }
        
        if (!isset($_POST['product_id']) || !isset($_POST['width'])) {
            wp_die('Missing parameters');
        }
        
        $product_id = intval($_POST['product_id']);
        $width = sanitize_text_field($_POST['width']);
        
        error_log('AJAX Debug - Product ID: ' . $product_id . ', Width: ' . $width);
        
        $buckets = $this->get_buckets_by_width($product_id, $width);
        
        ob_start();
        $this->display_buckets_for_size($product_id, $width);
        $html = ob_get_clean();
        
        wp_send_json_success($html);
    }
    
    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_product()) {
            wp_enqueue_script(
                'wc-configurable-frontend',
                plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
                array('jquery'),
                '1.3.0',
                true
            );
            
            wp_localize_script('wc-configurable-frontend', 'wc_configurable_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_configurable_nonce')
            ));
            
            wp_enqueue_style(
                'wc-configurable-frontend',
                plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
                array(),
                '1.2.0'
            );
        }
    }
    
    /**
     * Shortcode to display configurable selector
     */
    public function configurable_selector_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID()
        ), $atts);
        
        $product_id = intval($atts['product_id']);
        
        if (!$product_id) {
            return '<p>No product ID specified.</p>';
        }
        
        ob_start();
        $this->display_configurable_selector_for_product($product_id);
        return ob_get_clean();
    }
    
    /**
     * Display configurable selector for specific product ID
     */
    public function display_configurable_selector_for_product($product_id) {
        $sizes = $this->get_product_sizes($product_id);
        $bucket_plate = $this->get_bucket_plate($product_id);
        $widths = $this->get_available_widths($product_id);
        
        if (empty($sizes) && !$bucket_plate) {
            return;
        }
        
        echo '<form class="cart" method="post" enctype="multipart/form-data">';
        echo '<input type="hidden" name="add-to-cart" value="' . esc_attr($product_id) . '">';
        echo '<input type="hidden" name="wc_configurable_nonce" value="' . wp_create_nonce('wc_configurable_nonce') . '">';
        
        echo '<div class="wc-configurable-wrapper">';
        
        // Determine configuration display
        $show_selector = (!empty($sizes) && $bucket_plate);
        $default_config = $bucket_plate && empty($sizes) ? 'economical' : 'installation';
        
        if ($show_selector) {
            echo '<h4>Select Configuration:</h4>';
            echo '<div class="configuration-options">';
            echo '<label class="configuration-option">';
            echo '<input type="radio" name="configuration_type" value="economical">';
            echo '<span class="config-label">Most Economical</span>';
            echo '</label>';
            echo '<label class="configuration-option">';
            echo '<input type="radio" name="configuration_type" value="installation">';
            echo '<span class="config-label">Easiest Installation</span>';
            echo '</label>';
            echo '</div>';
        } else {
            echo '<input type="hidden" name="configuration_type" value="' . esc_attr($default_config) . '">';
        }
        
        // Most Economical Section
        echo '<div class="configuration-section economical-section" style="display: ' . ($show_selector ? 'none' : ($default_config === 'economical' ? 'block' : 'none')) . ';">';
        if ($bucket_plate) {
            $this->display_bucket_plates($product_id);
        }
        echo '</div>';
        
        // Easiest Installation Section
        echo '<div class="configuration-section installation-section" style="display: ' . ($show_selector ? 'none' : ($default_config === 'installation' ? 'block' : 'none')) . ';">';
        
        if (!empty($widths)) {
            echo '<h4>Select Size:</h4>';
            echo '<div class="size-options">';
            
            foreach ($widths as $index => $width) {
                $checked = $index === 0 ? 'checked' : '';
                echo '<label class="size-option">';
                echo '<input type="radio" name="product_size" value="' . esc_attr($width) . '" ' . $checked . ' data-width="' . esc_attr($width) . '">';
                echo '<span class="size-label">' . esc_html($width) . '</span>';
                echo '</label>';
            }
            
            echo '</div>';
            
            echo '<div class="buckets-container">';
            
            $has_any_buckets = false;
            foreach ($sizes as $size) {
                $width = $size['width'];
                $buckets = $size['buckets'];
                
                if (!empty($buckets)) {
                    $has_any_buckets = true;
                    $display_style = ($width === $widths[0]) ? 'block' : 'none';
                    
                    echo '<div class="bucket-group" data-width="' . esc_attr($width) . '" style="display: ' . $display_style . ';">';
                    
                    foreach ($buckets as $bucket_index => $bucket) {
                        $product_data = $bucket['product_data'];
                        $accessories = $bucket['accessories'];
                        $checked = ($width === $widths[0] && $bucket_index === 0) ? 'checked' : '';
                        
                        // Sanitize width for use in HTML attributes (remove quotes and special chars)
                        $width_sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '', $width);
                        $bucket_id = 'bucket_' . $width_sanitized . '_' . $bucket_index;
                        
                        echo '<div class="bucket-item">';
                        echo '<label class="bucket-option">';
                        echo '<input type="radio" name="selected_bucket" value="' . esc_attr($bucket['product_id']) . '" ' . $checked . ' data-bucket-id="' . $bucket_id . '">';
                        echo '<div class="bucket-details">';
                        echo '<h5>' . esc_html($product_data['name']) . '</h5>';
                        echo '<p class="price">' . $product_data['formatted_price'] . '</p>';
                        
                        if (!empty($product_data['sku'])) {
                            echo '<p class="sku">SKU: ' . esc_html($product_data['sku']) . '</p>';
                        }
                        
                        $stock_class = $product_data['in_stock'] ? 'in-stock' : 'out-of-stock';
                        $stock_text = $product_data['in_stock'] ? 'In Stock' : 'Out of Stock';
                        echo '<p class="stock-status ' . $stock_class . '">' . $stock_text . '</p>';
                        
                        echo '</div>';
                        echo '</label>';
                        
                        if (!empty($accessories)) {
                            echo '<div class="accessories-section" data-bucket-id="' . $bucket_id . '" style="display: none;">';
                            echo '<h6>Available Accessories:</h6>';
                            echo '<div class="accessories-list">';
                            
                            foreach ($accessories as $acc_index => $accessory) {
                                $acc_data = $accessory['product_data'];
                                $acc_id = $bucket_id . '_acc_' . $acc_index;
                                
                                echo '<div class="accessory-item">';
                                echo '<label class="accessory-option">';
                                echo '<input type="checkbox" name="accessories[]" value="' . esc_attr($accessory['product_id']) . '" data-accessory-id="' . $acc_id . '">';
                                echo '<div class="accessory-details">';
                                echo '<h6>' . esc_html($acc_data['name']) . '</h6>';
                                echo '<p class="price">' . $acc_data['formatted_price'] . '</p>';
                                
                                if (!empty($acc_data['sku'])) {
                                    echo '<p class="sku">SKU: ' . esc_html($acc_data['sku']) . '</p>';
                                }
                                
                                $acc_stock_class = $acc_data['in_stock'] ? 'in-stock' : 'out-of-stock';
                                $acc_stock_text = $acc_data['in_stock'] ? 'In Stock' : 'Out of Stock';
                                echo '<p class="stock-status ' . $acc_stock_class . '">' . $acc_stock_text . '</p>';
                                
                                echo '</div>';
                                echo '<div class="quantity-selector" style="display: none;">';
                                echo '<label for="qty_' . $acc_id . '">Qty:</label>';
                                echo '<input type="number" id="qty_' . $acc_id . '" name="accessory_qty[' . esc_attr($accessory['product_id']) . ']" value="1" min="1" max="999">';
                                echo '</div>';
                                echo '</label>';
                                echo '</div>';
                            }
                            
                            echo '</div>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    }
                    
                    echo '</div>';
                }
            }
            
            if (!$has_any_buckets) {
                echo '<p class="no-products-message">No products available.</p>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<button type="submit" class="single_add_to_cart_button button alt">Add to Cart</button>';
        echo '</div>';
        echo '</form>';
    }
}

// Initialize the plugin
$GLOBALS['wc_configurable_products'] = WC_Configurable_Products::get_instance();

function wc_configurable_products() {
    return WC_Configurable_Products::get_instance();
}