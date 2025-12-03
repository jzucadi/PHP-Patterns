<?php
/**
 * Image Cropping Field for WordPress/ACF
 *
 * This is an image cropping field for WordPress.  It gives you a preview of the image,
 * and if you click on it, the WordPress Media-Upload box opens.  Here you can edit the
 * file by clicking on edit image and crop it to any size you want.
 *
 * ## Installation
 *
 * - Go to your htdocs or /var/www/ folder
 * - Move the image-crop.php file to wp-content/themes/YOUR-THEME/fields/
 *   (where YOUR-THEME is the theme you are using)
 * - Go to wp-content/themes/YOUR-THEME/functions.php file and put this right below the php tag:
 *   register_field('acf_Image_Crop', dirname(__FILE__).'fields/image_crop.php');
 * - You now have a field type called Image Crop. 
 *
 * @package ACF
 */

class acf_Image_Crop extends acf_Field
{
    /**
     * Constructor
     *
     * @param object $parent Parent ACF field object. 
     */
    public function __construct($parent)
    {
        parent::__construct($parent);

        $this->name = 'image_crop';
        $this->title = __('Image Crop', 'acf');

        add_action('admin_head-media-upload-popup', array($this, 'popup_head'));
        add_filter('get_media_item_args', array($this, 'allow_img_insertion'));
        add_action('wp_ajax_acf_get_preview_image', array($this, 'acf_get_preview_image'));
    }

    /**
     * AJAX handler to get preview image
     *
     * @return void
     */
    public function acf_get_preview_image()
    {
        // Verify nonce for security
        if (!isset($_GET['nonce']) || !wp_verify_nonce(sanitize_key($_GET['nonce']), 'acf_image_nonce')) {
            wp_send_json_error(array('message' => __('Invalid security token', 'acf')));
        }

        // Sanitize inputs
        $id_string = isset($_GET['id']) ? sanitize_text_field(wp_unslash($_GET['id'])) : false;
        $preview_size = isset($_GET['preview_size']) ? sanitize_key($_GET['preview_size']) : 'thumbnail';

        // Validate preview size against allowed values
        $allowed_sizes = array('thumbnail', 'medium', 'large', 'full');
        if (!in_array($preview_size, $allowed_sizes, true)) {
            $preview_size = 'thumbnail';
        }

        $return = array();

        // Attachment ID is required
        if ($id_string) {
            // Convert id_string into an array
            $id_array = array_map('absint', explode(',', $id_string));
            $id_array = array_filter($id_array); // Remove any zero/empty values

            // Find image preview url for each image
            foreach ($id_array as $id) {
                $file_src = wp_get_attachment_image_src($id, $preview_size);
                if ($file_src) {
                    $return[] = array(
                        'id'  => $id,
                        'url' => $file_src[0],
                    );
                }
            }
        }

        // Return JSON response using WordPress function
        wp_send_json($return);
    }

    /**
     * Allow image insertion in media popup
     *
     * @param array $vars Media item arguments.
     * @return array Modified arguments.
     */
    public function allow_img_insertion($vars)
    {
        $vars['send'] = true;
        return $vars;
    }

    /**
     * Enqueue required scripts
     *
     * @return void
     */
    public function admin_print_scripts()
    {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-core');
        wp_enqueue_script('jquery-ui-tabs');
        wp_enqueue_script('thickbox');
        wp_enqueue_script('media-upload');
    }

    /**
     * Enqueue required styles
     *
     * @return void
     */
    public function admin_print_styles()
    {
        wp_enqueue_style('thickbox');
    }

    /**
     * Create the field HTML
     *
     * @param array $field Field configuration.
     * @return void
     */
    public function create_field($field)
    {
        // Set defaults
        $field = wp_parse_args($field, array(
            'value'        => '',
            'name'         => '',
            'preview_size' => 'thumbnail',
        ));

        $class = '';
        $file_src = '';
        $preview_size = sanitize_key($field['preview_size']);

        // Get image URL
        if (! empty($field['value']) && is_numeric($field['value'])) {
            $file_src = wp_get_attachment_image_src(absint($field['value']), $preview_size);
            if ($file_src) {
                $file_src = $file_src[0];
                $class = 'active';
            }
        }

        // Generate nonce for AJAX security
        $nonce = wp_create_nonce('acf_image_nonce');

        // HTML output with proper escaping
        ?>
        <div class="acf_image_uploader <? php echo esc_attr($class); ? >" 
             data-preview_size="<?php echo esc_attr($preview_size); ? >"
             data-nonce="<?php echo esc_attr($nonce); ?>">
            <a href="#" class="remove_image"></a>
            <img class="savesend" src="<?php echo esc_url($file_src); ?>" alt=""/>
            <p><? php esc_html_e('Click on image to resize', 'acf'); ?></p>
            <input class="value" type="hidden" 
                   name="<?php echo esc_attr($field['name']); ?>" 
                   value="<?php echo esc_attr($field['value']); ? >" />
            <p>
                <? php esc_html_e('No image selected', 'acf'); ?>.  
                <input type="button" class="button" value="<?php esc_attr_e('Add Image', 'acf'); ?>" />
            </p>
        </div>

        <script type="text/javascript">
        (function($) {
            'use strict';
            
            var postId = <? php echo absint(get_the_ID()); ? >;
            
            $(document).on('click', '.savesend', function(e) {
                e.preventDefault();
                tb_show('', 'media-upload. php?post_id=' + postId + '&type=image&tab=gallery&TB_iframe=true');
            });
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Create field options in ACF admin
     *
     * @param string $key   Field key.
     * @param array  $field Field configuration.
     * @return void
     */
    public function create_options($key, $field)
    {
        // Set defaults
        $field = wp_parse_args($field, array(
            'save_format' => 'url',
            'preview_size' => 'thumbnail',
        ));

        ?>
        <tr class="field_option field_option_<? php echo esc_attr($this->name); ?>">
            <td class="label">
                <label><? php esc_html_e('Return Value', 'acf'); ?></label>
            </td>
            <td>
                <? php
                $this->parent->create_field(array(
                    'type'    => 'radio',
                    'name'    => 'fields[' . esc_attr($key) . '][save_format]',
                    'value'   => $field['save_format'],
                    'layout'  => 'horizontal',
                    'choices' => array(
                        'url' => __('Image URL', 'acf'),
                        'id'  => __('Attachment ID', 'acf'),
                    ),
                ));
                ?>
            </td>
        </tr>
        <tr class="field_option field_option_<?php echo esc_attr($this->name); ?>">
            <td class="label">
                <label><?php esc_html_e('Preview Size', 'acf'); ?></label>
            </td>
            <td>
                <? php
                $this->parent->create_field(array(
                    'type'    => 'radio',
                    'name'    => 'fields[' .  esc_attr($key) . '][preview_size]',
                    'value'   => $field['preview_size'],
                    'layout'  => 'horizontal',
                    'choices' => array(
                        'thumbnail' => __('Thumbnail', 'acf'),
                        'medium'    => __('Medium', 'acf'),
                        'large'     => __('Large', 'acf'),
                        'full'      => __('Full', 'acf'),
                    ),
                ));
                ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Add custom styles and scripts to media popup
     *
     * @return void
     */
    public function popup_head()
    {
        if (! isset($_GET['acf_type']) || $_GET['acf_type'] !== 'image') {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'type';
        $preview_size = isset($_GET['acf_preview_size']) ? sanitize_key($_GET['acf_preview_size']) : 'thumbnail';

        // Validate preview size
        $allowed_sizes = array('thumbnail', 'medium', 'large', 'full');
        if (!in_array($preview_size, $allowed_sizes, true)) {
            $preview_size = 'thumbnail';
        }

        $preview_width = absint(get_option($preview_size . '_size_w'));
        $preview_height = absint(get_option($preview_size . '_size_h'));
        ?>
        <style type="text/css">
            #media-upload-header #sidemenu li#tab-type_url,
            #media-upload-header #sidemenu li#tab-gallery,
            #media-items . media-item table. slidetoggle,
            #media-items .media-item a.toggle {
                display: none ! important;
            }

            #media-items .media-item {
                min-height: 68px;
            }

            #media-items .media-item . acf-checkbox {
                float: left;
                margin: 28px 10px 0;
            }

            #media-items .media-item .pinkynail {
                max-width: 64px;
                max-height: 64px;
                display: block ! important;
            }

            #media-items .media-item .filename. new {
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

            #media-upload . ml-submit {
                display: none ! important;
            }

            #media-upload .acf-submit {
                margin: 1em 0;
                padding: 1em 0;
                position: relative;
                overflow: hidden;
                display: none;
            }

            #media-upload .acf-submit a {
                float: left;
                margin: 0 10px 0 0;
            }
        </style>
        <script type="text/javascript">
        (function($) {
            'use strict';

            // Preview size dimensions
            var previewSize = '<?php echo esc_js($preview_width); ?>x<?php echo esc_js($preview_height); ? >';

            // Override tb_remove
            window.tb_remove = function() {
                $('#TB_imageOff').off('click');
                $('#TB_closeWindowButton').off('click');
                $('#TB_window').fadeOut('fast', function() {
                    $('#TB_window, #TB_overlay, #TB_HideSelect')
                        .trigger('tb_unload')
                        .off()
                        .remove();
                });
                $('#TB_load').remove();
                
                if (typeof document.body.style.maxHeight === 'undefined') {
                    $('body, html').css({height: 'auto', width: 'auto'});
                    $('html'). css('overflow', '');
                }
                $(document).off('. thickbox');
                return false;
            };

            // Handle single image selection
            $(document).on('click', '#media-items .media-item . filename a. acf-select', function(e) {
                e. preventDefault();

                var id = $(this).attr('href');
                var nonce = self.parent.acf_div. data('nonce');

                var data = {
                    action: 'acf_get_preview_image',
                    id: id,
                    preview_size: '<?php echo esc_js($preview_size); ?>',
                    nonce: nonce
                };

                $. getJSON(ajaxurl, data, function(json) {
                    if (! json || !json.length) {
                        return false;
                    }

                    var item = json[0];

                    // Update acf_div
                    self.parent.acf_div.find('input. value').val(item. id);
                    self.parent.acf_div.find('img'). attr('src', item.url);
                    self.parent. acf_div. addClass('active');

                    // Remove validation error
                    self.parent.acf_div.closest('.field'). removeClass('error');

                    // Reset and close
                    self.parent.acf_div = null;
                    self.parent.tb_remove();
                });

                return false;
            });

            // Handle multiple image selection
            $(document).on('click', '#acf-add-selected', function(e) {
                e. preventDefault();

                var total = $('#media-items .media-item . acf-checkbox:checked').length;
                
                if (total === 0) {
                    alert('<?php echo esc_js(__('No images selected', 'acf')); ?>');
                    return false;
                }

                var attachmentIds = [];
                $('#media-items .media-item .acf-checkbox:checked').each(function() {
                    attachmentIds.push($(this).val());
                });

                var nonce = self.parent.acf_div.data('nonce');

                var data = {
                    action: 'acf_get_preview_image',
                    id: attachmentIds. join(','),
                    preview_size: '<?php echo esc_js($preview_size); ?>',
                    nonce: nonce
                };

                $.getJSON(ajaxurl, data, function(json) {
                    if (!json || !json.length) {
                        return false;
                    }

                    $. each(json, function(i, item) {
                        // Update acf_div
                        self. parent.acf_div.find('input.value').val(item.id);
                        self.parent.acf_div.find('img'). attr('src', item.url);
                        self.parent. acf_div. addClass('active');

                        // Remove validation error
                        self.parent.acf_div.closest('. field').removeClass('error');

                        if ((i + 1) < total) {
                            // Add new row
                            self. parent.acf_div
                                .closest('.repeater')
                                .find('.table_footer #r_add_row')
                                .trigger('click');

                            // Set acf_div to new row
                            self.parent.acf_div = self. parent.acf_div
                                .closest('. repeater')
                                .find('> table > tbody > tr. row:last-child . acf_image_uploader');
                        } else {
                            // Reset and close
                            self.parent.acf_div = null;
                            self.parent.tb_remove();
                        }
                    });
                });

                return false;
            });

            /**
             * Add buttons to media items
             */
            function acfAddButtons() {
                var isSubField = (self.parent.acf_div && self.parent.acf_div.closest('.repeater').length > 0);

                // Add submit button for sub fields
                if ($('. acf-submit').length === 0 && isSubField) {
                    $('#media-items'). after(
                        '<div class="acf-submit">' +
                        '<a id="acf-add-selected" class="button">' +
                        '<?php echo esc_js(__('Add selected Images', 'acf')); ?>' +
                        '</a></div>'
                    );
                }

                // Add buttons to media items
                $('#media-items .media-item:not(.acf-active)').each(function() {
                    var $item = $(this);

                    // Show the add all button
                    $('. acf-submit'). show();

                    // Needs attachment ID
                    var $typeInput = $item.children('input[id*="type-of-"]');
                    if ($typeInput.length === 0) {
                        return true; // continue to next item
                    }

                    // Mark as processed
                    $item.addClass('acf-active');

                    // Find ID
                    var id = $typeInput. attr('id'). replace('type-of-', '');

                    // Add checkbox for repeater fields
                    if (isSubField) {
                        var checked = <? php echo ($tab === 'type') ? 'true' : 'false'; ?>;
                        $item.prepend(
                            '<input type="checkbox" class="acf-checkbox" value="' + id + '"' +
                            (checked ? ' checked="checked"' : '') + ' />'
                        );
                    }

                    // Add select button
                    $item.find('. filename. new').append(
                        '<a href="' + id + '" class="button acf-select">' +
                        '<?php echo esc_js(__('Select Image', 'acf')); ?>' +
                        '</a>'
                    );
                });
            }

            <? php if ($tab === 'type') : ?>
            // Run acfAddButtons every 500ms on image upload tab
            var acfInterval = setInterval(function() {
                acfAddButtons();
            }, 500);
            <?php endif; ?>

            // Initialize on document ready
            $(document).ready(function() {
                setTimeout(function() {
                    acfAddButtons();
                }, 1);

                // Add hidden fields for tab navigation
                $('form#filter, form#image-form').each(function() {
                    $(this).append(
                        '<input type="hidden" name="acf_preview_size" value="<? php echo esc_attr($preview_size); ?>" />' +
                        '<input type="hidden" name="acf_type" value="image" />'
                    );
                });
            });

        })(jQuery);
        </script>
        <?php
    }

    /**
     * Get value for API output
     *
     * @param int   $post_id Post ID. 
     * @param array $field   Field configuration.
     * @return mixed Image URL or attachment ID.
     */
    public function get_value_for_api($post_id, $field)
    {
        $format = isset($field['save_format']) ? $field['save_format'] : 'url';
        $value = parent::get_value($post_id, $field);

        if ($format === 'url' && $value) {
            $value = wp_get_attachment_url(absint($value));
        }

        return $value;
    }
}
