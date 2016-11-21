<?php
/*
Plugin Name: WordPress Bimserver
Plugin URI:
Description: WordPress Bimserver connects WordPress with a Bimserver to enable a web frontend for Bimserver
Version: 0.1
Author: Bastiaan Grutters
Author URI: http://www.bastiaangrutters.nl
*/

/*
 * Usage: Place shortcodes in pages:
 * [showBimserverSettings]
 * [showIfcForm]
 * [showReports]
 */

namespace WordPressBimserver;

class WordPressBimserver {
   private $options;
   
   public function __construct() {
      spl_autoload_register( Array( '\WordPressBimserver\WordPressBimserver', 'autoload' ) );
      
      add_action( 'admin_menu', Array( '\WordPressBimserver\WordPressBimserver', 'optionsMenu' ) );
      
      $this->options = get_option( 'wordpress_bimserver_options', Array() );
      
      add_action( 'admin_enqueue_scripts', Array( '\WordPressBimserver\WordPressBimserver', 'adminEnqueueScripts' ) );
      add_action( 'wp_enqueue_scripts', Array( '\WordPressBimserver\WordPressBimserver', 'wpEnqueueScripts' ) );
      
      // Add post types etc at the WordPress init action
      add_action( 'init', Array( '\WordPressBimserver\WordPressBimserver', 'wordPressInit' ) );

      add_action( 'wp_ajax_wpbimserver_ajax', Array( '\WordPressBimserver\WordPressBimserver', 'ajaxCallback' ) );
      add_action( 'wp_ajax_nopriv_wpbimserver_ajax', Array( '\WordPressBimserver\WordPressBimserver', 'ajaxCallback' ) );

      // --- Shortcodes ---
      add_shortcode( 'showBimserverSettings', Array( '\WordPressBimserver\WordPressBimserver', 'showBimserverSettings' ) );
      add_shortcode( 'showIfcForm', Array( '\WordPressBimserver\WordPressBimserver', 'showIfcForm' ) );
      add_shortcode( 'showBimserverReports', Array( '\WordPressBimserver\WordPressBimserver', 'showReports' ) );

      // Registration action
      add_action( 'user_register', Array( '\WordPressBimserver\WordPressBimserver', 'userRegister' ), 10, 1 );
   }
   
   /**
    * @param $class
    */
   public static function autoload( $class ) {
      $class = ltrim( $class, '\\' );
      if( strpos( $class, __NAMESPACE__ ) !== 0 ) {
         return;
      }
      $class = str_replace( __NAMESPACE__, '', $class );
      $filename = plugin_dir_path( __FILE__ ) . 'includes/class-wordpress-bimserver' .
          strtolower( str_replace( '\\', '-', $class ) ) . '.php';
      require_once( $filename );
   }
   
   public static function optionsMenu() {
      add_options_page(
          __( 'WordPress and Bimserver Options', 'wordpress-bimserver' ),
          __( 'WordPress and Bimserver Options', 'wordpress-bimserver' ),
          'activate_plugins',
          basename( dirname( __FILE__ ) ) . '/wordpress-bimserver-options.php'
      );
   }
   
   public static function adminEnqueueScripts() {
      //wp_enqueue_script( 'jquery' );
      //wp_enqueue_style( 'wordpress-bimserver', plugins_url( 'wordpress-bimserver.css', __FILE__ ) );
   }
   
   public static function wpEnqueueScripts() {
      wp_enqueue_script( 'jquery' );
      //wp_enqueue_script( 'json-fallback', plugins_url( 'libraries/json.js', __FILE__ ), Array(), "1.0", true );
      wp_enqueue_script( 'wordpress-bimserver', plugins_url( 'wordpress-bimserver.js', __FILE__ ), Array( 'jquery' ), "1.0", true );
      wp_enqueue_style( 'wordpress-bimserver', plugins_url( 'wordpress-bimserver.css', __FILE__ ) );
   }
   
   public static function getOptions( $forceReload = false ) {
      global $wordPressBimserver;
      if( $forceReload ) {
         $wordPressBimserver->options = get_option( 'wordpress_bimserver_options', Array() );
      }
      return $wordPressBimserver->options;
   }
   
   public static function wordPressInit() {
      $postTypeArguments = Array(
          'labels' => Array(
              'name' => __( 'Report', 'wordpress-bimserver' ),
              'singular_name' => __( 'Report', 'wordpress-bimserver' ),
              'add_new' => __( 'Add New', 'wordpress-bimserver' ),
              'add_new_item' => __( 'Add New Report', 'wordpress-bimserver' ),
              'edit_item' => __( 'Edit Report', 'wordpress-bimserver' ),
              'new_item' => __( 'New Report', 'wordpress-bimserver' ),
              'all_items' => __( 'All Reports', 'wordpress-bimserver' ),
              'view_item' => __( 'View Report', 'wordpress-bimserver' ),
              'search_items' => __( 'Search Reports', 'wordpress-bimserver' ),
              'not_found' =>  __( 'No Reports found', 'wordpress-bimserver' ),
              'not_found_in_trash' => __( 'No Reports found in Trash', 'wordpress-bimserver' ),
              'parent_item_colon' => '',
              'menu_name' => 'BIM Quality Blocks Report' ),
          'public' => true,
          'publicly_queryable' => true,
          'show_ui' => true,
          'show_in_menu' => true,
          'query_var' => true,
          'rewrite' => true,
          'has_archive' => true,
          'hierarchical' => false,
          'menu_position' => null,
          'supports' => Array( 'title', 'editor', 'author' )
      );
      register_post_type( 'wp_bimserver_report', $postTypeArguments );
   }
   
   public function userRegister( $userId ) {
      $options = WordPressBimserver::getOptions();
      $userData = get_user_by( 'id', $userId );
      $username = $userData->get( 'user_email' );
      // Password generated
      if( function_exists( 'openssl_random_pseudo_bytes' ) ) {
         $password = bin2hex( openssl_random_pseudo_bytes( 8 ) );
      } else {
         // Unsafe fallback method in case openssl is not available for php
         $password = uniqid();
      }

      $name = '';
      if( isset( $_POST['first_name'] ) ) {
         $name .= $_POST['first_name'];
      }
      if( isset( $_POST['last_name'] ) ) {
         if( $name != '' ) {
            $name .= ' ';
         }
         $name .= $_POST['last_name'];
      }
      if( $name == '' ) {
         $name = $userData->get( 'user_login' );
      }

      $parameters = Array(
         'username' => $username,
         'password' => $password,
         'name' => $name,
         'type' => 'USER',
         'selfRegistration' => true,
         'resetUrl' => ''
      );

      // register
      $bimserver = new BimServerApi( $options['url'] );
      try {
         $response = $bimserver->apiCall( 'ServiceInterface', 'addUserWithPassword', $parameters );
         update_user_meta( $userId, '_bimserver_password', $password );
         update_user_meta( $userId, '_bimserver_uoid', $response['response']['result'] );
         $bimserverUser = new BimserverUser( get_current_user_id() );
         $poid = $bimserverUser->addProject( 'WordPressBimserver' );
         update_user_meta( $userId, '_bimserver_poid', $poid );
      } catch( \Exception $e ) {
         $message = __( 'Error message', 'wordpress-bimserver' ) . ': ' . $e->getMessage() . "\n" .
             __( 'User email', 'wordpress-bimserver' ) . ': ' . $userData->user_email . "\n" .
             __( 'Sent from', 'wordpress-bimserver' ) . ': ' . bloginfo( 'wpurl' );
         wp_mail( get_option( 'admin_email' ), __( 'User registration on Bimserver failed', 'wordpress-bimserver' ), $message );
      }
   }

   public static function showBimserverSettings() {
      if( is_user_logged_in() ) {
         $bimserverUser = new BimserverUser( get_current_user_id() );
         if( $bimserverUser->isBimserverUser() ) {
            $userSettings = $bimserverUser->getBimserverUserSettings();
            if( $userSettings === false ) {
               $userSettings = $bimserverUser->retrieveBimserverUserSettings();
            }
            if( $userSettings === false ) {
               _e( 'There was a problem retrieving user settings, contact a site administrator',  'wordpress-bimserver' );
            } else {
               if( count( $userSettings['parameters'] ) == 0 ) {
                  $options = WordPressBimserver::getOptions();
                  _e( 'There are no configurable settings for this service',  'wordpress-bimserver' );
                  print( '<br />' );
                  print( '<a href="' . get_permalink( $options['upload_page'] ) . '">' . __( 'Click here to continue', 'wordpress-bimserver' ) . '</a>' );
               } else {
                  if( isset( $_POST['submit'] ) ) {
                     // Store settings in the bimserverUserSettings object
                     foreach( $userSettings['parameters'] as $key => $parameter ) {
                        if( isset( $_POST[ 'user_setting_' . $key ] ) ) {
                           $userSettings['parameters'][ $key ]['value'] = filter_input( INPUT_POST, 'user_setting_' . $key, FILTER_SANITIZE_SPECIAL_CHARS );
                        }
                     }
                     $bimserverUser->setBimserverUserSettings( $userSettings );
                  }
                  // Generate a form based on the profile for the user and restore previous settings
                  $bimserverService = $bimserverUser->getServiceInformation();
                  if( $bimserverService === false ) {
                     _e( 'No correct bimserver service configured, contact a website administrator to have this resolved', 'wordpress-bimserver' );
                  } else {
                     ?>
                     <h3><?php print( $bimserverService['name'] ); ?></h3>
                     <?php _e( 'Description', 'wordpress-bimserver' ); ?>:
                     <p><?php print( $bimserverService['description'] ); ?></p>
                     <form method="post" action="">
                        <?php
                        foreach( $userSettings['parameters'] as $key => $parameter ) {
                           print( '<label for="user-setting-' . $key . '">' . $parameter['name'] . '</label>' );
                           if( $parameter['type']['type'] == 'STRING' ) {
                              print( '<input type="text" name="user_setting_' . $key . '" id="user-setting-' . $key .
                                  '" value="' . ( isset( $parameter['value'] ) ? $parameter['value'] : '' ) . '" />' );
                           } else {
                              // TODO: add support for other parameter types
                           }
                           print( '<br />' );
                        }
                        ?>
                        <input type="submit" name="submit" value="<?php _e( 'Update settings', 'wordpress-bimserver' ); ?>"/>
                     </form>
                     <?php
                  }
               }
            }
         } else {
            _e( 'This is not a valid Bimserver user',  'wordpress-bimserver' );
         }
      } else {
         _e( 'Log in to use this service', 'wordpress-bimserver' );
      }
   }

   public static function showIfcForm() {
      if( is_user_logged_in() ) {
         $topicId = get_user_meta( get_current_user_id(), '_bimserver_checkin_id', true );
         if( $topicId != '' ) {
            WordPressBimserver::showCheckinProgress( $topicId );
         } else {
            try {
               $bimserverUser = new BimserverUser( get_current_user_id() );
               $options = WordPressBimserver::getOptions();
               if( $bimserverUser->hasConfiguredService() ) {
                  $error = false;
                  if( isset( $_POST['submit'], $_FILES['ifc'], $_FILES['ifc']['tmp_name'], $_FILES['ifc']['name'] ) && $_FILES['ifc']['name'] != '' ) {
                     // upload the IFC to the bimserver and start the service
                     try {
                        $comment = isset( $_POST['comment'] ) ? filter_input( INPUT_POST, 'comment', FILTER_SANITIZE_SPECIAL_CHARS ) : '';
                        if( isset( $_POST['bimserver_project'] ) && $_POST['bimserver_project'] != '' ) {
                           $poid = filter_input( INPUT_POST, 'bimserver_project', FILTER_SANITIZE_NUMBER_INT );
                        } else {
                           $poid = $bimserverUser->addProject( $_FILES['ifc']['name'] );
                           if( $poid === false ) {
                              throw new \Exception( 'Could not create project for: ' . $_FILES['ifc']['name'] );
                           }
                        }
                        $data = file_get_contents( $_FILES['ifc']['tmp_name'] );
                        $size = $_FILES['ifc']['size'];
                        $filename = $_FILES['ifc']['name'];
                        $deserializer = isset( $_POST['bimserver_deserializer'] ) ? $_POST['bimserver_deserializer'] : - 1;
                        $parameters = Array(
                            'poid'            => $poid,
                            'comment'         => $comment,
                            'deserializerOid' => $deserializer,
                            'fileSize'        => $size,
                            'fileName'        => $filename,
                            'data'            => base64_encode( $data ),
                            'merge'           => false,
                            'sync'            => false
                        );
                        $result = $bimserverUser->apiCall( 'ServiceInterface', 'checkin', $parameters );
                        if( $result === false ) {
                           $error = __( 'Could not check in this file, make sure it is a valid ifc file', 'wordpress-bimserver' );
                        } else {
                           $checkinId = $result['response']['result'];
                           update_user_meta( get_current_user_id(), '_bimserver_checkin_id', $checkinId );
                           update_user_meta( get_current_user_id(), '_bimserver_checkin_poid', $poid );
                           WordPressBimserver::showCheckinProgress( $checkinId );
                        }
                     } catch ( \Exception $e ) {
                        $error = $e->getMessage();
                     }
                  }

                  if( !isset( $_POST['submit'], $_FILES['ifc'] ) || $error !== false ) {
                     $notice = false;
                     try {
                        $user = new BimserverUser( get_current_user_id() );
                        $result = $user->apiCall( 'Bimsie1ServiceInterface', 'getAllProjects', Array( 'onlyTopLevel' => true, 'onlyActive' => true ) );
                        if( $result === false ) {
                           $projects = Array();
                           $notice = __( 'Could not retrieve a list of projects', 'wordpress-bimserver' );
                        } else {
                           $projects = $result['response']['result'];
                        }
                        $result = $user->apiCall( 'PluginInterface', 'getAllDeserializers', Array( 'onlyEnabled' => true ) );
                        if( $result === false ) {
                           $deserializers = Array();
                           $notice = __( 'Could not retrieve a list of deserializers', 'wordpress-bimserver' );
                        } else {
                           $deserializers = $result['response']['result'];
                        }
                     } catch ( \Exception $e ) {
                        $notice = $e->getMessage();
                        $projects = Array();
                        $deserializers = Array();
                     }
                     if( $notice !== false ) {
                        print( '<div class="error-message">' . __( 'Notice', 'wordpress-bimserver' ) . ': ' . $notice . '</div>' );
                     }
                     if( $error !== false ) {
                        print( '<div class="error-message">' . __( 'There was a problem running this service', 'wordpress-bimserver' ) . ': ' . $error . '</div>' );
                     }
                     ?>
                     <form method="post" enctype="multipart/form-data" action="">
                        <label for="ifc-file"><?php _e( 'IFC', 'wordpress-bimserver' ); ?></label><br/>
                        <input type="file" name="ifc" id="ifc-file" accept=".ifc"/><br/>
                        <label for="bimserver-deserializer"><?php _e( 'Deserializer', 'wordpress-bimserver' ); ?></label><br/>
                        <select name="bimserver_deserializer" id="bimserver-deserializer">
                           <?php
                           foreach( $deserializers as $deserializer ) {
                              print( '<option value="' . $deserializer['oid'] . '"' . ( isset( $_POST['bimserver_deserializer'] ) && $_POST['bimserver_deserializer'] == $deserializer['oid'] ? ' selected' : '' ) . '>' . $deserializer['name'] . '</option>' );
                           }
                           ?>
                        </select><br/>
                        <label for="bimserver-project"><?php _e( 'Project', 'wordpress-bimserver' ); ?></label><br/>
                        <select name="bimserver_project" id="bimserver-project">
                           <option value=""><?php _e( 'New project', 'wordpress-bimserver' ); ?></option>
                           <?php
                           foreach( $projects as $project ) {
                              print( '<option value="' . $project['id'] . '"' . ( isset( $_POST['bimserver_project'] ) && $_POST['bimserver_project'] == $project['id'] ? ' selected' : '' ) . '>' . $project['name'] . '</option>' );
                           }
                           ?>
                        </select><br/>
                        <label for="checkin-comment"><?php _e( 'Comment', 'wordpress-bimserver' ); ?></label><br/>
                        <textarea name="comment" id="checkin-comment" placeholder="<?php _e( 'Comment', 'wordpress-bimserver' ); ?>">
                           <?php print( isset( $_POST['comment'] ) ? filter_input( INPUT_POST, 'comment', FILTER_SANITIZE_SPECIAL_CHARS ) : '' ); ?>
                        </textarea><br/>
                        <br/>
                        <input type="submit" name="submit" value="<?php _e( 'Upload', 'wordpress-bimserver' ); ?>"/>
                     </form>
                     <?php
                  }
               } else {
                  print( '<p>' . __( '', 'wordpress-bimserver' ) . '</p>' );
                  print( '<a href="' . get_permalink( $options['settings_page'] ) . '">' . __( 'Click here to choose your service preferences', 'wordpress-bimserver' ) . '</a>' );
               }
            } catch( \Exception $e ) {
               _e( 'There is a problem with your user account, please contact an administrator', 'wordpress-bimserver' );
            }
         }
      }
   }

   private static function showCheckinProgress( $topicId ) {
      $bimserverUser = new BimserverUser( get_current_user_id() );
      $progress = $bimserverUser->getProgress( $topicId );
      $options = WordPressBimserver::getOptions();
      print( '<div id="bimserver-progress-bar"><div class="filled"></div><div class="text"></div></div>' );
      print( '<script type="text/javascript">var wpBimserverSettings = ' . json_encode( Array(
              'ajaxUrl' => add_query_arg( Array( 'action' => 'wpbimserver_ajax' ), admin_url( 'admin-ajax.php' ) ),
              'initialProgress' => $progress,
              'completeContent' => '<a href="' . get_permalink( $options['reports_page'] ) . '">' . __( 'Checkin complete, click here to view the results', 'wordpress-bimserver' ) . '</a>'
          ) ) . ';</script>' );
   }

   public static function showReports() {
      if( is_user_logged_in() ) {
         $reports = get_user_meta( get_current_user_id(), '_bimserver_report' );
         if( count( $reports ) == 0 ) {
            print( '<p>' . __( 'You have no recorded uses of this service.', 'wordpress-bimserver' ) . '</p>' );
         } else {
            $reports = array_reverse( $reports );
            print( '<table class="wordpress-bimserver-table">' );
            print( '<tr><th>' . __( 'Download', 'wordpress-bimserver' ) . '</th><th>' . __( 'Status', 'wordpress-bimserver' ) . '</th><th>' . __( 'Date', 'wordpress-bimserver' ) . '</th></tr>' );
            foreach( $reports as $key => $report ) {
               $class = $report['status'] == __( 'new', 'wordpress-bimserver' ) ? 'bold ' : '';
               print( '<tr class="' . $class . ( $key % 2 == 0 ? 'even' : 'odd' ) . '">' );
               print( '<td><a href="' . add_query_arg( Array( 'action' => 'wpbimserver_ajax', 'type' => 'download', 'id' => $key ), admin_url( 'admin-ajax.php' ) ) . '">' . __( 'Download report', 'wordpress-bimserver' ) . '</a></td>' );
               print( '<td>' . $report['status'] . '</td>' );
               print( '<td>' . date( get_option( 'date_format' ), $report['timestamp'] ) . '</td>' );
               print( '</tr>' );
            }
            print( '</table>' );
         }
      }
   }

   public static function ajaxCallback() {
      if( isset( $_GET['type'] ) ) {
         if( $_GET['type'] == 'progress' ) {
            // Do the progress update if logged in
            if( is_user_logged_in() ) {
               $bimserverUser = new BimserverUser( get_current_user_id() );
               $topicId = get_user_meta( get_current_user_id(), '_bimserver_checkin_id', true );
               if( $topicId != '' ) {
                  $progress = $bimserverUser->getProgress( $topicId );
               } else {
                  $progress = 1;
               }
               if( $progress >= 1 ) {
                  delete_user_meta( get_current_user_id(), '_bimserver_checkin_id' );
                  $report = Array(
                     'topicId' => $topicId,
                     'timestamp' => time(),
                     'status' => __( 'new', 'wordpress-bimserver' ),
                     'poid' => get_user_meta( get_current_user_id(), '_bimserver_checkin_poid', true )
                  );
                  delete_user_meta( get_current_user_id(), '_bimserver_checkin_poid' );
                  add_user_meta( get_current_user_id(), '_bimserver_report', $report );
               }
               print( $progress );
            }
         } elseif( $_GET['type'] == 'download' && isset( $_GET['id'] ) && ctype_digit( $_GET['id'] ) ) {
            $key = intval( $_GET['id'] );
            $reports = get_user_meta( get_current_user_id(), '_bimserver_report' );
            $reports = array_reverse( $reports );
            if( isset( $reports[ $key ] ) ) {
               $newReport = $reports[ $key ];
               $newReport['status'] = __( 'downloaded', 'wordpress-bimserver' );
               update_user_meta( get_current_user_id(), '_bimserver_report', $newReport, $reports[ $key ] );
               $bimserverUser = new BimserverUser( get_current_user_id() );
               $bimserverUser->downloadData( $newReport['poid'] );
            } else {
               header( 'HTTP/1.0 404 Not Found' );
            }
         }
      }
      exit();
   }
}

$wordPressBimserver = new WordPressBimserver();
