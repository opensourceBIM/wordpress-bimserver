<?php
global $wordPressBimserver, $wpdb;

use WordPressBimserver\WordPressBimserver;

$message = '';

if( isset( $_POST['action'] ) && $_POST[ 'action' ] == 'update' ) {
	$options = WordPressBimserver::getOptions();

	foreach( $_POST[ 'wordpress_bimserver_options' ] AS $key => $newOption ) {
		$options[$key] = $newOption;
	}
	 
	update_option( 'wordpress_bimserver_options', $options );
   delete_option( '_wordpress_bimserver_service' );
}

$wordPressBimserverOptions = WordPressBimserver::getOptions( true );
$postTypes = get_post_types( Array(), 'objects' );
$taxonomies = get_taxonomies();
$pages = get_posts( Array(
		'post_type' => 'page',
		'posts_per_page' => -1
) );
?>
<div class="wrap">
	<div class="icon32" id="icon-options-general"></div>
	<h2><?php _e( 'WordPress and Bimserver Options', 'wordpress-bimserver' ); ?></h2>
	<form method="post" enctype="multipart/form-data">
		<table class="form-table">
			<!--tr valign="top">
				<td><label for="wordpress-bimserver-post-type"><?php _e( 'Service usage post type', 'wordpress-bimserver' ); ?></label></td>
				<td>
<?php
if( is_array( $postTypes ) ) {
?>
                  <select name="wordpress_bimserver_options[post_type]" id="wordpress-bimserver-post-type">
<?php
   foreach( $postTypes AS $key => $postType ) {
?>
                     <option value="<?php print( $key ); ?>" <?php print(
                     ( ( isset( $wordPressBimserverOptions['post_type'] ) && $key == $wordPressBimserverOptions['post_type'] ) ? ' selected="selected"' : '' ) ); ?>>
						      <?php print( $postType->labels->name ); ?>
                     </option>
<?php
   }
?>
				   </select>
<?php
}
?>
					<p class="description"><?php _e( 'The post type in which BIM Quality Blocks are stored', 'wordpress-bimserver' ); ?></p>
				</td>
			</tr-->
         <tr valign="top">
            <td><label for="wordpress-bimserver-url"><?php _e( 'Bimserver url', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <input type="text" name="wordpress_bimserver_options[url]" id="wordpress-bimserver-url" value="<?php print( isset( $wordPressBimserverOptions['url'] ) ? $wordPressBimserverOptions['url'] : '' ); ?>" />
               <p class="description"><?php _e( 'The URL of the Bimserver which we should connect to', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="wordpress-bimserver-service-id"><?php _e( 'Bimserver service ID', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <input type="text" name="wordpress_bimserver_options[service_id]" id="wordpress-bimserver-service-id" value="<?php print( isset( $wordPressBimserverOptions['service_id'] ) ? $wordPressBimserverOptions['service_id'] : '' ); ?>" />
               <p class="description"><?php _e( 'The service ID of the service of the service on the Bimserver which we need to trigger', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="wordpress-bimserver-download-mime-type"><?php _e( 'Service download mime-type', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <input type="text" name="wordpress_bimserver_options[mime_type]" id="wordpress-bimserver-download-mime-type" value="<?php print( isset( $wordPressBimserverOptions['mime_type'] ) ? $wordPressBimserverOptions['mime_type'] : 'application/zip, application/octet-stream' ); ?>" />
               <p class="description"><?php _e( 'The services download mime-type of the download after using the service', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="wordpress-bimserver-project-scheme"><?php _e( 'Bimserver project scheme', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <input type="text" name="wordpress_bimserver_options[project_scheme]" id="wordpress-bimserver-project-scheme" value="<?php print( isset( $wordPressBimserverOptions['project_scheme'] ) ? $wordPressBimserverOptions['project_scheme'] : 'ifc2x3tc1' ); ?>" />
               <p class="description"><?php _e( 'The scheme used for projects on this Bimserver', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="reports-page"><?php _e( 'Reports page', 'wordpress-bimserver' ); ?></label>
            </td>
            <td>
               <select name="wordpress_bimserver_options[reports_page]" id="reports-page">
                  <?php
                  foreach( $pages as $page ) {
                     ?>
                     <option value="<?php print( $page->ID ); ?>"<?php print( ( isset( $wordPressBimserverOptions['reports_page'] ) && $wordPressBimserverOptions['reports_page'] == $page->ID ? ' selected' : '' ) ); ?>>
                        <?php print( $page->post_title ); ?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
               <p class="description"><?php _e( 'The page where the user can see his service usage history, it must contain the shortcode [showBimserverReports]', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="settings-page"><?php _e( 'Settings page', 'wordpress-bimserver' ); ?></label>
            </td>
            <td>
               <select name="wordpress_bimserver_options[settings_page]" id="settings-page">
                  <?php
                  foreach( $pages as $page ) {
                     ?>
                     <option value="<?php print( $page->ID ); ?>"<?php print( ( isset( $wordPressBimserverOptions['settings_page'] ) && $wordPressBimserverOptions['settings_page'] == $page->ID ? ' selected' : '' ) ); ?>>
                        <?php print( $page->post_title ); ?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
               <p class="description"><?php _e( 'The page where the user can set the services settings, it must contain the shortcode [showBimserverSettings]', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="upload-page"><?php _e( 'Upload page', 'wordpress-bimserver' ); ?></label>
            </td>
            <td>
               <select name="wordpress_bimserver_options[upload_page]" id="upload-page">
                  <?php
                  foreach( $pages as $page ) {
                     ?>
                     <option value="<?php print( $page->ID ); ?>"<?php print( ( isset( $wordPressBimserverOptions['upload_page'] ) && $wordPressBimserverOptions['upload_page'] == $page->ID ? ' selected' : '' ) ); ?>>
                        <?php print( $page->post_title ); ?>
                     </option>
                     <?php
                  }
                  ?>
               </select>
               <p class="description"><?php _e( 'The page where the user can use the service, it must contain the shortcode [showIfcForm]', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <!--tr valign="top">
            <td><label for="wordpress-bimserver-new-project"><?php _e( 'Each upload is a new project', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <select name="wordpress_bimserver_options[new_project]" id="wordpress-bimserver-new-project">
                  <option value="yes"<?php print( ( isset( $wordPressBimserverOptions['new_project'] ) && $wordPressBimserverOptions['new_project'] == 'yes' ) ? ' selected' : '' ); ?>><?php _e( 'Yes', 'wordpress-bimserver' ); ?></option>
                  <option value="no"<?php print( ( isset( $wordPressBimserverOptions['new_project'] ) && $wordPressBimserverOptions['new_project'] == 'no' ) ? ' selected' : '' ); ?>><?php _e( 'No', 'wordpress-bimserver' ); ?></option>
               </select>
               <p class="description"><?php _e( 'If set to yes, for each upload a new project is created, if set to no it is all added to the same project', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr>
         <tr valign="top">
            <td><label for="wordpress-bimserver-new-revision"><?php _e( 'Each upload is a new revision', 'wordpress-bimserver' ); ?></label></td>
            <td>
               <select name="wordpress_bimserver_options[new_revision]" id="wordpress-bimserver-new-revision">
                  <option value="yes"<?php print( ( isset( $wordPressBimserverOptions['new_revision'] ) && $wordPressBimserverOptions['new_revision'] == 'yes' ) ? ' selected' : '' ); ?>><?php _e( 'Yes', 'wordpress-bimserver' ); ?></option>
                  <option value="no"<?php print( ( isset( $wordPressBimserverOptions['new_revision'] ) && $wordPressBimserverOptions['new_revision'] == 'no' ) ? ' selected' : '' ); ?>><?php _e( 'No', 'wordpress-bimserver' ); ?></option>
               </select>
               <p class="description"><?php _e( 'If set to yes, for each upload a new revision is return, if set to no the result data will be added as extended data', 'wordpress-bimserver' ); ?></p>
            </td>
         </tr-->
			<tr valign="top">
				<td colspan="2">
					<p class="submit">
						<input class="button-primary" type="submit" name="action" value="update" />
					</p>
				</td>
			</tr>
		</table>
	</form>
</div>
