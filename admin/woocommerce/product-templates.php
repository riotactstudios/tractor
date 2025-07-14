<?php 

/**
 * QA Product Template
 */

add_filter( 'template_include', 'qa_single_product_template_include', 50, 1 );

function qa_single_product_template_include( $template ) {
  if(is_singular('product') && (has_term(array('qa-conversion-kits', 'qa-replacement-kits'), 'product_cat'))) {
  	$template = get_stylesheet_directory() . '/woocommerce/single-product-qa.php';
  } 
  return $template;
}

function my_custom_shop_template() {
    get_template_part( 'shop', 'shop' );
}
add_filter( 'woocommerce_shop_page_template', 'my_custom_shop_template' );