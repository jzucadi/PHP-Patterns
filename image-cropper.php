	/*
	* 
	This is an image cropping field for WordPress. It gives you a preview of the image, and if you click on it, the WordPress 
	Media-Upload box opens. Here you can edit the file by clicking on edit image and crop it to any size you want.

	##Installation

	- Go to your htdocs or /var/www/ folder
	- Move the image-crop.php file to wp-content/themes/YOUR-THEME/fields/ (where YOUR-THEME is the theme you are using)
	- Go to wp-content/themes/YOUR-THEME/functions.php file and put this right below the php tag:
	register_field('acf_Image_Crop', dirname(__FILE__).'fields/image_crop.php'); 
	- You now have a field type called Image Crop.
	* 
*
	*/    

<?php
class acf_Image_Crop extends acf_Field
{
	
	function __construct($parent)
	{
    	parent::__construct($parent);
    	
    	$this->name = 'image_crop';
		$this->title = __('Image Crop','acf');
		global $post;
		
		add_action('admin_head-media-upload-popup', array($this, 'popup_head'));
		add_filter('get_media_item_args', array($this, 'allow_img_insertion'));
		add_action('wp_ajax_acf_get_preview_image', array($this, 'acf_get_preview_image'));
   	}
   	
	
   	function acf_get_preview_image()
   	{
   		// vars
   		$id_string = isset($_GET['id']) ? $_GET['id'] : false;
   		$preview_size = isset($_GET['preview_size']) ? $_GET['preview_size'] : 'thumbnail';
		$return = array();
		
		
		// attachment ID is required
   		if($id_string)
   		{
   		
   			// convert id_string into an array
   			$id_array = explode(',' , $id_string);
   			if(!is_array($id_array))
   			{
   				$id_array = array( $id_string );
   			}
   			
   			
   			// find image preview url for each image
   			foreach($id_array as $id)
   			{
   				$file_src = wp_get_attachment_image_src($id, $preview_size);
				$return[] = array(
					'id' => $id,
					'url' => $file_src[0],
				);
   			}
   		}
		
		// return json
		echo json_encode( $return );
		die();
   	}
   	
	
	function allow_img_insertion($vars)
	{
	    $vars['send'] = true;
	    return($vars);
	}
	
	
	function admin_print_scripts()
	{
		wp_enqueue_script(array(
			'jquery',
			'jquery-ui-core',
			'jquery-ui-tabs',
			'thickbox',
			'media-upload',
		));
	}
	
	function admin_print_styles()
	{
  		wp_enqueue_style(array(
			'thickbox',		
		));
	}
	
	
	function create_field($field)
	{
		// vars
		$class = "";
		$file_src = "";
		$preview_size = isset($field['preview_size']) ? $field['preview_size'] : 'thumbnail';
		
		// get image url
		if($field['value'] != '' && is_numeric($field['value']))
		{
			$file_src = wp_get_attachment_image_src($field['value'], $preview_size);
			$file_src = $file_src[0];
			
			if($file_src) $class = "active";
		}
		// html
		echo '<div class="acf_image_uploader ' . $class . '" data-preview_size="' . $preview_size . '">';
			echo '<a href="#" class="remove_image"></a>';
			echo '<img class="savesend" src="' . $file_src . '" alt=""/>';
			echo '<p>click on image to resize</p>';
			echo '<input class="value" type="hidden" name="' . $field['name'] . '" value="' . $field['value'] . '" />';
			echo '<p>'.__('No image selected','acf').'. <input type="button" class="button" value="'.__('Add Image','acf').'" /></p>';
		echo '</div>';
		?>

		<script type="text/javascript">
		var id = <?php the_ID(); ?>;
		jQuery(document).ready(function() {
			jQuery(".savesend").live("click", function() {
				tb_show('', 'media-upload.php?post_id='+ id +'type=image&amp;tab=gallery&amp;TB_iframe=true');
			});
		});
		</script>

		<?php
	}
	
	function create_options($key, $field)
	{	
		// vars
		$field['save_format'] = isset($field['save_format']) ? $field['save_format'] : 'url';
		$field['preview_size'] = isset($field['preview_size']) ? $field['preview_size'] : 'thumbnail';
		
		?>
		<tr class="field_option field_option_<?php echo $this->name; ?>">
			<td class="label">
				<label><?php _e("Return Value",'acf'); ?></label>
			</td>
			<td>
				<?php 
				$this->parent->create_field(array(
					'type'	=>	'radio',
					'name'	=>	'fields['.$key.'][save_format]',
					'value'	=>	$field['save_format'],
					'layout'	=>	'horizontal',
					'choices' => array(
						'url'	=>	__("Image URL",'acf'),
						'id'	=>	__("Attachment ID",'acf')
					)
				));
				?>
			</td>
		</tr>
		<tr class="field_option field_option_<?php echo $this->name; ?>">
			<td class="label">
				<label><?php _e("Preview Size",'acf'); ?></label>
			</td>
			<td>
				<?php 
				$this->parent->create_field(array(
					'type'	=>	'radio',
					'name'	=>	'fields['.$key.'][preview_size]',
					'value'	=>	$field['preview_size'],
					'layout'	=>	'horizontal',
					'choices' => array(
						'thumbnail'	=>	__("Thumbnail",'acf'),
						'medium'	=>	__("Medium",'acf'),
						'large'		=>	__("Large",'acf'),
						'full'		=>	__("Full",'acf')
					)
				));
				?>
			</td>
		</tr>
		<?php
	}
  
	function popup_head()
	{	
		if(isset($_GET["acf_type"]) && $_GET['acf_type'] == 'image')
		{
			$tab = isset($_GET['tab']) ? $_GET['tab'] : "type"; // "type" is the upload tab
			$preview_size = isset($_GET['acf_preview_size']) ? $_GET['acf_preview_size'] : 'thumbnail';
			
?><style type="text/css">
	#media-upload-header #sidemenu li#tab-type_url,
	#media-upload-header #sidemenu li#tab-gallery, 
	#media-items .media-item table.slidetoggle,
	#media-items .media-item a.toggle {
		display: none !important;
	}
	
	#media-items .media-item {
		min-height: 68px;
	}
	
	#media-items .media-item .acf-checkbox {
		float: left;
		margin: 28px 10px 0;
	}
	
	#media-items .media-item .pinkynail {
		max-width: 64px;
		max-height: 64px;
		display: block !important;
	}
	
	#media-items .media-item .filename.new {
		min-height: 0;
		padding: 25px 10px 10px;
		line-height: 14px;
		
	}
	
	#media-items .media-item .title {
		line-height: 14px;
	}
	
	#media-items .media-item .button {
		float: right;
		margin: -2px 0 0 10px;
	}
	
	#media-upload .ml-submit {
		display: none !important;
	}
	#media-upload .acf-submit {
		margin: 1em 0;
		padding: 1em 0;
		position: relative;
		overflow: hidden;
		display: none; /* default is hidden */
	}
	
	#media-upload .acf-submit a {
		float: left;
		margin: 0 10px 0 0;
	}
</style>
<script type="text/javascript">
jQuery('.edit_image').live('click', function() {
	alert("test");
	self.parent.tb_remove();
});
tb_remove = function ()
{
	jQuery("#TB_imageOff").unbind("click");
	jquery("#TB_closeWindowButton").unbind("click");
	jQuery("#TB_window").fadeOut("fast", function(){jQuery('#TB_window, #TB_overlay, #TB_HideSelect').trigger("tb_unload").unbind().remove();});
	jQuery("TB_load").remove();
	if(typeof document.body.style.maxHeight == "undefined")
	{
		jQuery("body", "html").css({height: "auto", width: "auto"});
		jQuery("html").css("overflow", "");
	}
	jQuery(document).unbind('.thickbox');
	return false;
};
(function($){
	
	/*
	*  Vars
	*/
	
	// generate the preview size (150x150)
	var preview_size = "<?php echo get_option($preview_size . '_size_w'); ?>x<?php echo get_option($preview_size . '_size_h'); ?>";
	
	
	function get_preview_image(options, callback)
	{
		// defaults
		var defaults = {
			id : [],
			preview_size : "thumbnail",
		};
		
		
		// override deafault with options
		$.extend(defaults, options);
		
		
		// run ajax to get id urls
		$.ajax({
			url : ajaxurl
			data : options,
			dataType : "json",
			type : "POST",
			
			
		});
		
		
	}
	*/
		
	/*
	*  Select Image
	*
	*/
	
	$('#media-items .media-item .filename a.acf-select').live('click', function(){
		
		var id = $(this).attr('href');
		
		var data = {
			action: 'acf_get_preview_image',
			id: id,
			preview_size : "<?php echo $preview_size; ?>"
		};
	
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.getJSON(ajaxurl, data, function( json ) {
			
			// validate
			if(!json)
			{
				return false;
			}
			
			
			// get item
			item = json[0];
			
			
			// update acf_div
			self.parent.acf_div.find('input.value').val( item.id );
 			self.parent.acf_div.find('img').attr( 'src', item.url );
 			self.parent.acf_div.addClass('active');
 	
 	
 			// validation
 			self.parent.acf_div.closest('.field').removeClass('error');
 			
 			
 			// reset acf_div and return false
 			self.parent.acf_div = null;
 			self.parent.tb_remove();
 	
		});
		
		return false;
		
	});
	
	
	$('#acf-add-selected').live('click', function(){ 
		 
		// check total 
		var total = $('#media-items .media-item .acf-checkbox:checked').length;
		if(total == 0) 
		{ 
			alert("<?php _e("No images selected",'acf'); ?>"); 
			return false; 
		} 
		
		
		// generate id's
		var attachment_ids = [];
		$('#media-items .media-item .acf-checkbox:checked').each(function(){
			attachment_ids.push( $(this).val() );
		});
		
		
		// creae json data
		var data = {
			action: 'acf_get_preview_image',
			id: attachment_ids.join(','),
			preview_size : "<?php echo $preview_size; ?>"
		};
		
		
		// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
		$.getJSON(ajaxurl, data, function( json ) {
			
			// validate
			if(!json)
			{
				return false;
			}
			
			$.each(json, function(i ,item){
			
				// update acf_div
				self.parent.acf_div.find('input.value').val( item.id ); 
	 			self.parent.acf_div.find('img').attr('src', item.url ); 
	 			self.parent.acf_div.addClass('active'); 
	 	 
	 	 
	 			// validation 
	 			self.parent.acf_div.closest('.field').removeClass('error'); 
	
	 			 
	 			if((i+1) < total) 
	 			{ 
	 				// add row 
	 				self.parent.acf_div.closest('.repeater').find('.table_footer #r_add_row').trigger('click'); 
	 			 
	 				// set acf_div to new row image 
	 				self.parent.acf_div = self.parent.acf_div.closest('.repeater').find('> table > tbody > tr.row:last-child .acf_image_uploader'); 
	 			} 
	 			else 
	 			{ 
	 				// reset acf_div and return false 
					self.parent.acf_div = null; 
					self.parent.tb_remove(); 
	 			} 
				
    		});
			
		
		});
		
		return false;
		 
	}); 
	
	
	// set a interval function to add buttons to media items
	function acf_add_buttons()
	{
		// vars
		var is_sub_field = (self.parent.acf_div && self.parent.acf_div.closest('.repeater').length > 0) ? true : false;
		
		
		// add submit after media items (on for sub fields)
		if($('.acf-submit').length == 0 && is_sub_field)
		{
			$('#media-items').after('<div class="acf-submit"><a id="acf-add-selected" class="button"><?php _e("Add selected Images",'acf'); ?></a></div>');
		}
		
		
		// add buttons to media items
		$('#media-items .media-item:not(.acf-active)').each(function(){
			
			// show the add all button
			$('.acf-submit').show();
			
			// needs attachment ID
			if($(this).children('input[id*="type-of-"]').length == 0){ return false; }
			
			// only once!
			$(this).addClass('acf-active');
			
			// find id
			var id = $(this).children('input[id*="type-of-"]').attr('id').replace('type-of-', '');
			
			// if inside repeater, add checkbox
			if(is_sub_field)
			{
				$(this).prepend('<input type="checkbox" class="acf-checkbox" value="' + id + '" <?php if($tab == "type"){echo 'checked="checked"';} ?> />');
			}
			
			// change text of insert button, and add new button
			$(this).find('.filename.new').append('<a href="' + id + '" class="button acf-select"><?php _e("Select Image",'acf'); ?></a>');
			
		});
	}
	<?php
	
	// run the acf_add_buttons ever 500ms when on the image upload tab
	if($tab == 'type'): ?>
	var acf_t = setInterval(function(){
		acf_add_buttons();
	}, 500);
	<?php endif; ?>
	
	
	// add acf input filters to allow for tab navigation
	$(document).ready(function(){
		
		setTimeout(function(){
			acf_add_buttons();
		}, 1);
		
		
		$('form#filter, form#image-form').each(function(){
			
			$(this).append('<input type="hidden" name="acf_preview_size" value="<?php echo $preview_size; ?>" />');
			$(this).append('<input type="hidden" name="acf_type" value="image" />');
			
		});
	});
				
})(jQuery);
</script><?php
		}
	}

	
	function get_value_for_api($post_id, $field)
	{
		// vars
		$format = isset($field['save_format']) ? $field['save_format'] : 'url';
		
		$value = parent::get_value($post_id, $field);
		
		if($format == 'url')
		{
			$value = wp_get_attachment_url($value);
		}
		
		return $value;
	}
		
}
?>
