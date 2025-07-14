jQuery(document).ready(function($) {
    
    // Function to move ACF field group to WooCommerce panel
    function moveACFToWooPanel() {
        
        // Wait for both ACF and WooCommerce to be ready
        if (typeof acf === 'undefined' || !$('.woocommerce_options_panel').length) {
            setTimeout(moveACFToWooPanel, 100);
            return;
        }
        
        // Configuration - adjust these selectors for your specific setup
        const ACF_FIELD_GROUP_SELECTOR = '.acf-field-group[data-key="68321d2564253"]'; // Replace with your ACF field group selector
        const WOO_PANEL_SELECTOR = '#qa_product_data'; // Replace with your custom panel ID
        
        const $acfFieldGroup = $(ACF_FIELD_GROUP_SELECTOR);
        const $wooPanel = $(WOO_PANEL_SELECTOR);
        
        if (!$acfFieldGroup.length || !$wooPanel.length) {
            console.log('ACF field group or WooCommerce panel not found');
            return;
        }
        
        // Store original styles and data before moving
        const originalStyles = {
            acfStyles: $acfFieldGroup.attr('style') || '',
            select2Data: []
        };
        
        // Collect select2 instances data before moving
        $acfFieldGroup.find('select.select2-hidden-accessible').each(function() {
            const $select = $(this);
            const select2Data = $select.select2('data');
            const select2Options = $select.data('select2').options.options;
            
            originalStyles.select2Data.push({
                element: $select,
                data: select2Data,
                options: select2Options
            });
        });
        
        // Create a wrapper div to maintain ACF context
        const $acfWrapper = $('<div class="acf-moved-fields-wrapper"></div>');
        
        // Add custom CSS to preserve ACF styling in WooCommerce context
        if (!$('#acf-woo-styles').length) {
            $('<style id="acf-woo-styles">')
                .text(`
                    .woocommerce_options_panel .acf-moved-fields-wrapper {
                        margin: 0;
                        padding: 0;
                    }
                    
                    .woocommerce_options_panel .acf-field {
                        margin-bottom: 1em !important;
                        padding: 0 20px !important;
                    }
                    
                    .woocommerce_options_panel .acf-label {
                        font-weight: 600 !important;
                        margin-bottom: 3px !important;
                    }
                    
                    .woocommerce_options_panel .acf-input {
                        margin: 0 !important;
                    }
                    
                    .woocommerce_options_panel .acf-field .select2-container {
                        width: 100% !important;
                        max-width: none !important;
                    }
                    
                    .woocommerce_options_panel .acf-field .select2-selection {
                        min-height: 30px !important;
                        border: 1px solid #ddd !important;
                        border-radius: 3px !important;
                    }
                    
                    .woocommerce_options_panel .acf-field input[type="text"],
                    .woocommerce_options_panel .acf-field input[type="number"],
                    .woocommerce_options_panel .acf-field textarea,
                    .woocommerce_options_panel .acf-field select {
                        width: 100% !important;
                        padding: 6px 8px !important;
                        border: 1px solid #ddd !important;
                        border-radius: 3px !important;
                    }
                `)
                .appendTo('head');
        }
        
        // Move the field group
        $acfFieldGroup.appendTo($acfWrapper);
        $wooPanel.append($acfWrapper);
        
        // Reinitialize ACF after moving
        setTimeout(function() {
            
            // Reinitialize ACF fields
            acf.doAction('append', $acfWrapper);
            
            // Restore and reinitialize select2 instances
            originalStyles.select2Data.forEach(function(selectData) {
                const $select = selectData.element;
                
                // Destroy existing select2 if it exists
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                
                // Reinitialize select2 with original options
                $select.select2(selectData.options);
                
                // Restore selected data
                if (selectData.data && selectData.data.length > 0) {
                    const values = selectData.data.map(item => item.id);
                    $select.val(values).trigger('change');
                }
            });
            
            // Trigger ACF field initialization for any other field types
            $acfWrapper.find('.acf-field').each(function() {
                const $field = $(this);
                const fieldType = $field.data('type');
                
                // Trigger specific ACF field type initializations if needed
                switch(fieldType) {
                    case 'date_picker':
                        acf.doAction('date_picker/init', $field);
                        break;
                    case 'color_picker':
                        acf.doAction('color_picker/init', $field);
                        break;
                    case 'image':
                    case 'file':
                        acf.doAction('media/init', $field);
                        break;
                    case 'wysiwyg':
                        acf.doAction('wysiwyg/init', $field);
                        break;
                }
            });
            
            // Ensure select2 dropdowns work within WooCommerce panels
            $acfWrapper.find('.select2-container').on('select2:open', function() {
                const $dropdown = $('.select2-dropdown');
                $dropdown.css('z-index', 999999);
            });
            
            console.log('ACF field group successfully moved to WooCommerce panel with preserved styling');
            
        }, 50);
    }
    
    // Initialize the move process
    moveACFToWooPanel();
    
    // Alternative: If you need to move fields based on specific WooCommerce events
    $(document).on('woocommerce_variations_loaded', function() {
        // Re-run if variations are loaded and affect your fields
        moveACFToWooPanel();
    });
    
    // Handle WooCommerce panel switching to maintain ACF functionality
    $('.product_data_tabs li a').on('click', function() {
        const panelId = $(this).attr('href');
        
        setTimeout(function() {
            // Reinitialize ACF fields when switching to panel containing moved fields
            if ($(panelId).find('.acf-moved-fields-wrapper').length) {
                acf.doAction('show', $(panelId));
            }
        }, 10);
    });
    
});

// Additional helper function for complex field reinitialization
function reinitializeACFField($field) {
    const fieldType = $field.data('type');
    const fieldKey = $field.data('key');
    
    // Get the field object from ACF
    const field = acf.getField(fieldKey);
    
    if (field) {
        // Trigger field-specific initialization
        field.initialize();
        
        // For select fields, ensure select2 is properly initialized
        if (fieldType === 'select' && field.$input().length) {
            const $select = field.$input();
            
            if (!$select.hasClass('select2-hidden-accessible')) {
                // Initialize select2 if not already done
                const select2Options = {
                    width: '100%',
                    allowClear: $select.attr('data-allow_null') === '1',
                    placeholder: $select.attr('data-placeholder') || ''
                };
                
                $select.select2(select2Options);
            }
        }
    }
}
