<?php
if (!defined('ABSPATH')) {
	exit;
}

global $wpdb;

use WordPressBimserver\WordPressBimserver;

if (isset($_POST['action'])) {
	$options = WordPressBimserver::getOptions();

	foreach ($_POST['wordpress_bimserver_options'] AS $key => $newOption) {
		$options[$key] = $newOption;
	}

	update_option('wordpress_bimserver_options', $options);
	delete_option('_wordpress_bimserver_service');
}

$options = WordPressBimserver::getOptions(true);
$pages                     = get_posts([
	'post_type'      => 'page',
	'posts_per_page' => - 1,
]);
$forms = GFAPI::get_forms();

?>
<div class="wrap">
    <div class="icon32" id="icon-options-general"></div>
    <h2><?php _e('WordPress and Bimserver Options', 'wordpress-bimserver'); ?></h2>
    <form method="post" enctype="multipart/form-data">
        <table class="form-table">
            <tr valign="top">
                <td><label for="wordpress-bimserver-url"><?php _e('Bimserver service url', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <input type="text" name="wordpress_bimserver_options[url]" id="wordpress-bimserver-url"
                           value="<?php print($options['url'] ?? ''); ?>"/>
                    <p class="description"><?php _e('The services URL of the Bimserver which we should connect to', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td><label for="wordpress-bimserver-token"><?php _e('Bimserver authorization token', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <input type="text" name="wordpress_bimserver_options[token]" id="wordpress-bimserver-token"
                           value="<?php print($options['token'] ?? ''); ?>"/>
                    <p class="description"><?php _e('The authorization token of Bimserver.', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td>
                    <label for="wordpress-bimserver-service-id"><?php _e('Bimserver service ID', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <input type="text" name="wordpress_bimserver_options[service_id]"
                           id="wordpress-bimserver-service-id"
                           value="<?php print($options['service_id'] ?? ''); ?>"/>
                    <p class="description"><?php _e('The service ID of the service of the service on the Bimserver which we need to trigger', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td>
                    <label for="wordpress-bimserver-download-input-type"><?php _e('Service input type', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <input type="text" name="wordpress_bimserver_options[input_type]"
                           id="wordpress-bimserver-download-input-type"
                           value="<?php print($options['input_type'] ?? 'IFC_STEP_2X3TC1'); ?>"/>
                    <p class="description"><?php _e('The input type for this BIMserver service.', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td>
                    <label for="wordpress-bimserver-download-output-type"><?php _e('Service output type', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <input type="text" name="wordpress_bimserver_options[output_type]"
                           id="wordpress-bimserver-download-output-type"
                           value="<?php print($options['output_type'] ?? 'VALIDATION_JSON_1_0'); ?>"/>
                    <p class="description"><?php _e('The output type for this BIMserver service.', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <tr valign="top">
                <td><label for="upload-form"><?php _e('Upload form', 'wordpress-bimserver'); ?></label></td>
                <td>
                    <select name="wordpress_bimserver_options[upload_form]" id="upload-form">
                    <?php
                    foreach ($forms as $form) {
	                    vprintf(
		                    '<option value="%s"%s>%s</option>',
		                    [
			                    $form['id'],
			                    $options['upload_form'] ?? null === $form['id'] ? ' selected' : '',
			                    $form['title'],
		                    ]
	                    );
                      }
                    ?>
                    </select>
                    <p class="description"><?php _e('The form used to upload IFC files for BIMserver processing.', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
           <tr valign="top">
                <td><label for="reports-page"><?php _e('Reports page', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <select name="wordpress_bimserver_options[reports_page]" id="reports-page">
							  <?php
							  foreach ($pages as $page) {
								  ?>
                           <option value="<?php print($page->ID); ?>"<?php print((isset($options['reports_page']) && $options['reports_page'] == $page->ID ? ' selected' : '')); ?>>
										<?php print($page->post_title); ?>
                           </option>
								  <?php
							  }
							  ?>
                    </select>
                    <p class="description"><?php _e('The page where the user can see his service usage history, it must contain the shortcode [showBimserverReports]', 'wordpress-bimserver'); ?></p>
                </td>
            </tr>
            <!--tr valign="top">
                <td><label for="upload-page"><?php _e('Upload page', 'wordpress-bimserver'); ?></label>
                </td>
                <td>
                    <select name="wordpress_bimserver_options[upload_page]" id="upload-page">
							  <?php
							  foreach ($pages as $page) {
								  ?>
                           <option value="<?php print($page->ID); ?>"<?php print((isset($options['upload_page']) && $options['upload_page'] == $page->ID ? ' selected' : '')); ?>>
										<?php print($page->post_title); ?>
                           </option>
								  <?php
							  }
							  ?>
                    </select>
                    <p class="description"><?php _e('The page where the user can use the service, it must contain the shortcode [showIfcForm]', 'wordpress-bimserver'); ?></p>
                </td>
            </tr-->
            <tr valign="top">
                <td colspan="2">
                    <p class="submit">
                        <input class="button-primary" type="submit" name="action" value="update"/>
                    </p>
                </td>
            </tr>
        </table>
    </form>
</div>
