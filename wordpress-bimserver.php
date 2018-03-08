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
 * [showReports]
 */

namespace WordPressBimserver;

class WordPressBimserver {
	protected $options;


	public function __construct() {
		spl_autoload_register([WordPressBimserver::class, 'autoload']);

		add_action('admin_menu', [WordPressBimserver::class, 'optionsMenu']);

		$this->options = get_option('wordpress_bimserver_options', []);

		// Add post types etc at the WordPress init action
		add_action('init', [WordPressBimserver::class, 'wordPressInit']);

		// --- Shortcodes ---
		add_shortcode('showBimserverReports', [WordPressBimserver::class, 'showReports']);

		add_action('wp_ajax_wpbimserver_ajax', [WordPressBimserver::class, 'ajaxCallback']);
		add_action('wp_ajax_nopriv_wpbimserver_ajax', [WordPressBimserver::class, 'ajaxCallback']);

		// Hook into GF form submit
		if (isset($this->options['upload_form'])) {
			add_action(vsprintf('gform_after_submission_%d', [$this->options['upload_form']]), [WordPressBimserver::class, 'afterSubmit'], 10, 2 );
		}
	}

	/**
	 * @param $class
	 */
	public static function autoload(string $class) {
		$class = ltrim($class, '\\');
		if (strpos($class, __NAMESPACE__) !== 0) {
			return;
		}
		$class    = str_replace(__NAMESPACE__, '', $class);
		$filename = plugin_dir_path(__FILE__) . 'includes/class-wordpress-bimserver' .
		            strtolower(str_replace('\\', '-', $class)) . '.php';
		require_once($filename);
	}

	public static function optionsMenu() {
		add_options_page(
			__('WordPress and Bimserver Options', 'wordpress-bimserver'),
			__('WordPress and Bimserver Options', 'wordpress-bimserver'),
			'activate_plugins',
			basename(dirname(__FILE__)) . '/wordpress-bimserver-options.php'
		);
	}

	public static function wpEnqueueScripts() {
		wp_enqueue_script('jquery');
		wp_enqueue_script('wordpress-bimserver', plugins_url('wordpress-bimserver.js', __FILE__), ['jquery'], "1.0", true);
		wp_enqueue_style('wordpress-bimserver', plugins_url('wordpress-bimserver.css', __FILE__));
	}

	public static function getOptions(bool $forceReload = false): array {
		global $wordPressBimserver;
		if ($forceReload) {
			$wordPressBimserver->options = get_option('wordpress_bimserver_options', []);
		}

		return $wordPressBimserver->options;
	}

	public static function wordPressInit() {
		$postTypeArguments = [
			'labels'             => [
				'name'               => __('Report', 'wordpress-bimserver'),
				'singular_name'      => __('Report', 'wordpress-bimserver'),
				'add_new'            => __('Add New', 'wordpress-bimserver'),
				'add_new_item'       => __('Add New Report', 'wordpress-bimserver'),
				'edit_item'          => __('Edit Report', 'wordpress-bimserver'),
				'new_item'           => __('New Report', 'wordpress-bimserver'),
				'all_items'          => __('All Reports', 'wordpress-bimserver'),
				'view_item'          => __('View Report', 'wordpress-bimserver'),
				'search_items'       => __('Search Reports', 'wordpress-bimserver'),
				'not_found'          => __('No Reports found', 'wordpress-bimserver'),
				'not_found_in_trash' => __('No Reports found in Trash', 'wordpress-bimserver'),
				'parent_item_colon'  => '',
				'menu_name'          => 'BIM Quality Blocks Report',
			],
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => true,
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => null,
			'supports'           => ['title', 'editor', 'author'],
		];
		register_post_type('wp_bimserver_report', $postTypeArguments);
	}

	public static function afterSubmit(array $entry, array $form) {
		$gravityFormsHelpers = new GravityForms($entry, $form);
		$service = new Service($gravityFormsHelpers->getUploadFileContent());
		try {
			$response = $service->submit();
		} catch (\Exception $e) {
			$response = json_encode(['Exception' => $e->getMessage()]);
		}
		$gravityFormsHelpers->processServiceResponse($response);
	}

	public static function showReports() {
		if (!is_user_logged_in()) {
			return;
		}

		$reports = get_user_meta(get_current_user_id(), '_bimserver_report');
		if (count($reports) === 0) {
			print('<p>' . __('You have no recorded uses of this service.', 'wordpress-bimserver') . '</p>');

			return;
		}
		$reports = array_reverse($reports);
		print('<table class="wordpress-bimserver-table">');
		print('<tr><th>' . __('Download', 'wordpress-bimserver') . '</th><th>' . __('Status', 'wordpress-bimserver') . '</th><th>' . __('Date', 'wordpress-bimserver') . '</th></tr>');
		foreach ($reports as $key => $report) {
			$class = $report['status'] == __('new', 'wordpress-bimserver') ? 'bold ' : '';
			print('<tr class="' . $class . ($key % 2 == 0 ? 'even' : 'odd') . '">');
			print('<td><a href="' . add_query_arg([
					'action' => 'wpbimserver_ajax',
					'type'   => 'download',
					'id'     => $key,
				], admin_url('admin-ajax.php')) . '">' . __('Download report', 'wordpress-bimserver') . '</a></td>');
			print('<td>' . $report['status'] . '</td>');
			print('<td>' . date(get_option('date_format'), $report['timestamp']) . '</td>');
			print('</tr>');
		}
		print('</table>');
	}
}

$wordPressBimserver = new WordPressBimserver();
