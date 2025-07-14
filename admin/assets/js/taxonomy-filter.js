(function($) {
    $(document).ready(function() {
        // Elements
        const $makeCheckboxes = $('#taxonomy-make input[type="checkbox"]');
        const $modelContainer = $('#taxonomy-model .categorychecklist');
        
        // Store all original models for reference
        let originalModelHTML = $modelContainer.html();
        
        // Hide all models initially
        $modelContainer.html('<li class="no-models">Please select a Make first</li>');
        
        // Function to update models
        function updateModels() {
            const selectedMakes = [];
            
            // Get all selected makes
            $makeCheckboxes.filter(':checked').each(function() {
                selectedMakes.push($(this).val());
            });
            
            // If no makes selected, hide all models
            if (selectedMakes.length === 0) {
                $modelContainer.html('<li class="no-models">Please select a Make first</li>');
                return;
            }
            
            // Add loading indicator
            $modelContainer.html('<li class="loading">Loading models...</li>');
            
            // Make AJAX request
            $.ajax({
                url: tractorTaxData.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_models_by_make',
                    nonce: tractorTaxData.nonce,
                    makes: selectedMakes
                },
                success: function(response) {
                    if (response.success && response.data) {
                        if (response.data.length === 0) {
                            // No models found
                            $modelContainer.html('<li class="no-models">No models available for selected makes</li>');
                            return;
                        }
                        
                        // Build new checkbox list
                        let modelHTML = '';
                        $.each(response.data, function(index, model) {
                            modelHTML += '<li id="model-' + model.id + '">';
                            modelHTML += '<label class="selectit">';
                            modelHTML += '<input value="' + model.id + '" type="checkbox" name="tax_input[model][]" id="in-model-' + model.id + '">';
                            modelHTML += ' ' + model.name;
                            modelHTML += '</label></li>';
                        });
                        
                        $modelContainer.html(modelHTML);
                    } else {
                        // Error in response
                        $modelContainer.html('<li class="error">Error loading models</li>');
                    }
                },
                error: function() {
                    $modelContainer.html('<li class="error">Error communicating with server</li>');
                }
            });
        }
        
        // Listen for changes to make checkboxes
        $makeCheckboxes.on('change', updateModels);
        
        // Store the original HTML for later reference
        $(window).on('load', function() {
            originalModelHTML = $modelContainer.html();
            
            // Check if any makes are already selected
            if ($makeCheckboxes.filter(':checked').length > 0) {
                updateModels();
            } else {
                // Hide models if no makes are selected
                $modelContainer.html('<li class="no-models">Please select a Make first</li>');
            }
        });
    });
})(jQuery);