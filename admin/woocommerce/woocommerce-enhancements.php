<?php

/**
 * Get Product Gallery
 */

function get_product_gallery($product) {
	$product_id = $product->get_id();
	$featured = $product->get_image_id();
	$images = $product->get_gallery_image_ids();
	$video = get_field('video', $product_id);
	$cover = get_field('cover_photo', $product_id);

 ?>
	
<div class="product-gallery">
	<!-- Featured Image -->
	<?php if(!empty($featured)) : ?>
		<div class="product-gallery-image featured">
			<img src="<?php echo wp_get_attachment_url($featured); ?>" alt="<?php the_title(); ?>" />
			<a class="gallery-full-screen" data-fancybox="product-gallery-<?php echo $product_id; ?>" href="<?php echo wp_get_attachment_url($featured); ?>">
				<i class="fa-sharp fa-regular fa-arrow-up-right-and-arrow-down-left-from-center"></i>
			</a>
		</div>
	<?php endif; ?>

	<!-- Video -->
	<?php if(get_field('video', $product_id)) : ?>
		<div class="product-gallery-image video">
			<img src="<?php echo $cover; ?>" alt="<?php the_title(); ?>-video" />
			<a class="gallery-video-play" data-fancybox="product-gallery-<?php echo $product_id; ?>" href="<?php echo $video; ?>">
				<i class="fa-sharp fa-regular fa-circle-play"></i>
			</a>
		</div>
	<?php endif; ?>

		<!-- Grid -->
		<?php if(!empty($images)) : ?>
			<?php $count = 0; ?>
			<?php $columns = count($images) % 2 === 0 ? 2 : 1; ?>
			<div class="product-gallery-image-grid" style="display: grid; grid-template-columns: repeat(<?php echo $columns; ?>, 1fr); gap: 10px;">
				<?php foreach($images as $image) : $count++; ?>
					<div class="product-gallery-image grid-item">
						<img src="<?php echo wp_get_attachment_url($image); ?>" alt="<?php the_title(); ?>" />
						<a class="gallery-full-screen" data-fancybox="product-gallery-<?php echo $product_id; ?>" href="<?php echo wp_get_attachment_url($image); ?>">
							<i class="fa-sharp fa-regular fa-arrow-up-right-and-arrow-down-left-from-center"></i>
						</a>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
	<?php 
}

/** 
 * Get Product Categories
 */

function get_product_categories($product) {
	
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	
	if ( $terms && ! is_wp_error( $terms ) ) : 
 		$cat_links = array();
    
    foreach ( $terms as $term ) {
    	$cat_links[] = '<a href="'.get_term_link($term->term_id).'">'.$term->name.'</a>';
    }
    $categories = join( ", ", $cat_links );
    
    return $categories;
	
	endif;

} 

/**
 * Quantity Buttons
 */

/*add_action( 'woocommerce_before_quantity_input_field', 'bbloomer_display_quantity_minus' );
 
function bbloomer_display_quantity_minus() {
	global $product;
  
  if(!is_product()) return;
  if($product->is_sold_individually()) return;
  echo '<button type="button" class="minus" >-</button>';
}
 
add_action( 'woocommerce_after_quantity_input_field', 'bbloomer_display_quantity_plus' );
 
function bbloomer_display_quantity_plus() {
	global $product;
  if(!is_product()) return;
  if($product->is_sold_individually()) return;
  echo '<button type="button" class="plus" >+</button>';
}*/