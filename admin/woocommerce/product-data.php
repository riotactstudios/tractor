<?php 

/**
 * Add QA Configure to Product
 */ 

add_filter( 'woocommerce_product_data_tabs',  'add_qa_product_tab' );

function add_qa_product_tab( $tab ) {
  $tab['qa'] = array(
    'label'  => __( 'Configure', 'qa' ),
    'target' => 'qa_product_data',
    'class' => array( 'show_if_simple' ),
  );
  return $tab;
}

add_action( 'woocommerce_product_data_panels', 'display_qa_product_tab_content' );

function display_qa_product_tab_content() {
  global $product_object;

  echo '<div id="qa_product_data" class="panel woocommerce_options_panel">
    <div class="options_group qa-content"></div></div>';
}

add_action('admin_footer', 'qa_product_tab_content_js');

function qa_product_tab_content_js() {
  global $typenow, $pagenow;

  if (in_array($pagenow, ['post.php', 'post-new.php']) && 'product' === $typenow) : 
    $field_group_key = 'group_68321d2564253';
    ?>
    <script>
    jQuery(function($){
        const fieldGroup = '<?php echo $field_group_key; ?>', 
              fieldGroupID = '#acf-'+fieldGroup,
              fieldGroupHtml = $(fieldGroupID+' .acf-fields').prop('outerHTML'); // Get the full .acf-fields container
        $(fieldGroupID).remove();
        $('div.qa-content').append(fieldGroupHtml); // Insert the entire .acf-fields container
        $('div.qa-content').css({
            'padding': '15px 20px', // Add padding to the container, not individual fields
            'box-sizing': 'border-box'
        });
    });
    </script>
    <?php endif;
}
