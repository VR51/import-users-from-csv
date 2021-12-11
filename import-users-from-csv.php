<?php
/*
 Plugin Name: Import Users from CSV
 Plugin URI: http://wordpress.org/extend/plugins/import-users-from-csv/
 Description: Import Users data and metadata from a csv file.
 Version: 1.0.4.2
 Author: Andrew Lima & Contributors
 Author URI: https://andrewlima.co.za
 License: GPL2
 Text Domain: import-users-from-csv
 */

/*
 * Copyright 2011  Ulrich Sossou  (https://github.com/sorich87)
 * Copyright 2018  Andrew Lima  (https://github.com/andrewlimaza/import-users-from-csv)
 * Modified 2021  Lee Hodson (https://github.com/VR51/import-users-from-csv) v1.0.2, v1.0.3, v1.04.x
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

/**
 * @package Import_Users_from_CSV
 */

load_plugin_textdomain( 'import-users-from-csv', false, basename( dirname( __FILE__ ) ) . '/languages' );

if ( ! defined( 'IS_IU_CSV_DELIMITER' ) ){
	define ( 'IS_IU_CSV_DELIMITER', ',' );
}

/**
 * Main plugin class
 *
 * @since 0.1
 **/
class IS_IU_Import_Users {
	private static $log_dir_path = '';
	private static $log_dir_url  = '';
	
	/**
	 * Initialization
	 *
	 * @since 0.1
	 **/
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_admin_pages' ) );
		add_action( 'init', array( __CLASS__, 'process_csv' ) );
		add_action( 'init', array( __CLASS__, 'schedule_csv' ) );
		add_action( 'scheduled_wp_user_import', 'run_scheduled_user_import' );
		add_action('wp_ajax_iuaction_admin_ajax', ['IS_IU_Import_Users', 'iuaction_admin_ajax']);
		
		$upload_dir = wp_upload_dir();
		self::$log_dir_path = trailingslashit( $upload_dir['basedir'] );
		self::$log_dir_url  = trailingslashit( $upload_dir['baseurl'] );
		
		do_action('is_iu_after_init');
	}
	
	/**
	 * Add administration menus
	 *
	 * @since 0.1
	 **/
	public static function add_admin_pages() {
		// Check for current user privileges 
		if( !current_user_can( 'manage_options' ) ) { return false; }
		
		add_users_page( __( 'Import From CSV' , 'import-users-from-csv'), __( 'Import From CSV' , 'import-users-from-csv'), 'create_users', 'import-users-from-csv', array( __CLASS__, 'users_page' ) );
	}
	
	/**
	 * Delete Log File
	 *
	 * @since 1.0.3
	 * Lee A. Hodson - VR51
	 **/
	public static function iuaction_admin_ajax() {
		
		$response = array();
		
		if ( ! empty($_POST['delete'] ) ) {
			$error_log_file = self::$log_dir_path . 'is_iu_errors.log';
			unlink($error_log_file);
			if ( ! file_exists( $error_log_file ) ) {
				$response['response'] = "Error log deleted.";
			} else {
				$response['response'] = "Error log NOT deleted.";
			}
		}
		
		if ( ! empty($_POST['export'] ) ) {
			self::csv_user_export();
			$response['response'] = 'Users Exported';
		}
		
		header( "Content-Type: application/json" );
		echo json_encode($response);
		
		// Don't forget to always exit in the ajax function.
		exit();
		
	}
	
	/**
	 * Run Scheduled Import
	 *
	 * @since 1.0.2
	 * Lee A. Hodson - VR51
	 **/
	public static function run_scheduled_user_import() {
		
		$schedule = get_option('wp_user_import_set_import_schedule');
		// Set timestamp
		$schedule['last_run_sched'] = time();
		update_option('wp_user_import_set_import_schedule', $schedule, false);
		
		// Configure location of CSV file that is to be imported
		$import_file_location = $schedule['file'];
		
		// Optional: Configure name of directory within wp-uploads that the CSV file will import into
		$dirname = 'user-import/';
		
		// Download and store the CSV file that will be imported
		// This step is not necessary in all cases but some servers and programs.. meh!
		$upload_dir = wp_upload_dir();
		$dir = trailingslashit( $upload_dir['basedir'] ) . $direname;
		$upload_dir = wp_upload_dir();
		$dir = trailingslashit( $upload_dir['basedir'] ) . $dirname;
		
		$file = $dir . "import.csv";
		
		// Fetch and save the CSV file. The file is stored with name 'import.csv' in the directory wp-uploads/user-import/
		// require_once( ABSPATH . 'wp-admin/includes/file.php' ); // uncomment if this file is needed in your instance e.g this code is in a functionality plugin
		global $wp_filesystem;
		WP_Filesystem();
		
		// Create $dir if it needs to be created
		if( ! $wp_filesystem->is_dir( $dir ) ) {
			$wp_filesystem->mkdir( $dir ); 
		}
		
		// Delete previously imported file if one exists
		unlink( $filename );
		
		// Make this a user setting soon
		$site = get_site_url();
		
		// Fetch the import $csv file
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $import_file_location);
		curl_setopt($ch, CURLOPT_TRANSFERTEXT, true);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_REFERER, $site);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$csv = curl_exec($ch);
		curl_close($ch);
		
		// Import the new file and set file permissions to 0644
		$wp_filesystem->put_contents( $dir . "import.csv", $csv, 0644 ); 
		
		self::import_csv( $file, array(
			'password_nag' 			=> intval( $schedule['nag'] ), // Show password nag? true (1) or false (0)
			'new_user_notification' => intval( $schedule['notice'] ), // Send email notification to new users? true (1) or false (0)
			'update_login' 	=> intval( $schedule['update_login'] ), // Update user profile if username exists? true (1) or false (0)
			'update_email' 	=> intval( $schedule['update_email'] ) // Update user profile if email exists? true (1) or false (0)
		) );
		
	}
	
	/**
	 * Process content of CSV file
	 *
	 * @since 0.1
	 **/
	public static function process_csv() {
		if ( isset( $_POST['_wpnonce-is-iu-import-users-users-page_import'] ) ) {
			check_admin_referer( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' );
			
			if ( ! empty( $_FILES['users_csv']['tmp_name'] ) ) {
				/* Setup settings variables */
				$filename			= sanitize_text_field( $_FILES['users_csv']['tmp_name'] );
				$password_nag		= isset( $_POST['password_nag'] ) ? sanitize_text_field( $_POST['password_nag'] ) : false;
				$new_user_notification	= isset( $_POST['new_user_notification'] ) ? sanitize_text_field( $_POST['new_user_notification'] ) : false;
				$update_login		= isset( $_POST['update_login'] ) ? sanitize_text_field( $_POST['update_login'] ) : false;
				$update_email		= isset( $_POST['update_email'] ) ? sanitize_text_field( $_POST['update_email'] ) : false;
				$log_successes		= isset( $_POST['log_successes'] ) ? sanitize_text_field( $_POST['log_successes'] ) : false;
				
				/* Set re-usable WP options data */
				$import = get_option('wp_user_import_unscheduled');
				$import['last_run'] = time(); // Set timestamp
				update_option('wp_user_import_unscheduled', $import, false);
				
				// Results
				$results = self::import_csv( $filename, array(
					'password_nag' => intval( $password_nag ),
					'new_user_notification' => intval( $new_user_notification ),
					'update_login' => intval( $update_login ),
					'update_email' => intval( $update_email ),
					'log_successes' => intval( $log_successes )
				) );
				
				if ( ! $results['user_ids'] ){
					/* No users imported? */
					wp_redirect( add_query_arg( 'import', 'fail', wp_get_referer() ) );
				} else if ( $results['errors'] ){
					/* Some users imported? */
					wp_redirect( add_query_arg( 'import', 'errors', wp_get_referer() ) );
				} else {
					/* All users imported? :D */
					wp_redirect( add_query_arg( 'import', 'success', wp_get_referer() ) );
				}
				exit;
			}
			
			wp_redirect( add_query_arg( 'import', 'file', wp_get_referer() ) );
			exit;
		}
	}
	
	/**
	 * Schedule Import
	 *
	 * @since 1.0.2
	 * Lee A. Hodson - VR51
	 **/
	public static function schedule_csv() {
		
		if ( isset( $_POST['_wpnonce-is-iu-import-users-users-page_import_schedule'] ) ) {
			check_admin_referer( 'is-iu-import-users-users-page_import_schedule', '_wpnonce-is-iu-import-users-users-page_import_schedule' );
			
			// Enable this to help debugging. Disable and enable 'delete' once to wipe $caught after debugging
			// Stores POST data in WP Options
			// $caught[] = $_POST['submit'];
			// update_option('wp_user_import_set_import_schedule_post_log', $caught, false);
			// delete_option('wp_user_import_set_import_schedule_post_log');
			
			if ( $_POST['submit'] == 'Run Now' ) {
				self::run_scheduled_user_import();
			}
			
			if ( $_POST['submit'] == 'Unschedule' || $_POST['submit'] == 'Reschedule' ) {
				delete_option('wp_user_import_set_import_schedule');
				$timestamp = wp_next_scheduled( 'scheduled_wp_user_import' );
				wp_unschedule_event( $timestamp, 'scheduled_wp_user_import' );
			}
			
			if ( $_POST['submit'] == 'Schedule' || $_POST['submit'] == 'Reschedule' ) {
				/* Setup schedule variables */
				$schedule['set']			= TRUE;
				$schedule['timedate']		= isset( $_POST['schedule_time'] ) ? strtotime( $_POST['schedule_time'] ) : false;
				$schedule['period']			= isset( $_POST['schedule_period'] ) ? sanitize_text_field( $_POST['schedule_period'] ) : false;
				$schedule['file']			= isset( $_POST['schedule_file'] ) ? sanitize_text_field( $_POST['schedule_file'] ) : false;
				
				$schedule['notice']			= isset( $_POST['scheduled_new_user_notification'] ) ? sanitize_text_field( $_POST['scheduled_new_user_notification'] ) : false;
				$schedule['nag']			= isset( $_POST['scheduled_password_nag'] ) ? sanitize_text_field( $_POST['scheduled_password_nag'] ) : false;
				$schedule['update_login']	= isset( $_POST['scheduled_update_login'] ) ? sanitize_text_field( $_POST['scheduled_update_login'] ) : false;
				$schedule['update_email']	= isset( $_POST['scheduled_update_email'] ) ? sanitize_text_field( $_POST['scheduled_update_email'] ) : false;
				$schedule['log_successes']	= isset( $_POST['scheduled_log_successes'] ) ? sanitize_text_field( $_POST['scheduled_log_successes'] ) : false;
				
				update_option('wp_user_import_set_import_schedule', $schedule, false);
				
				if ( ! wp_next_scheduled( 'scheduled_wp_user_import' ) ) {
					wp_schedule_event( $schedule['timedate'], $schedule['period'], 'scheduled_wp_user_import' );
				}
			}
			
		}
	}
	
	/**
	 * Content of the settings page
	 *
	 * @since 0.1
	 **/
	public static function users_page() {
		if ( ! current_user_can( 'create_users' ) ){
			wp_die( __( 'You do not have sufficient permissions to access this page.' , 'import-users-from-csv') );
		}
		
		?>
		
		<div class="wrap">
		<h2><?php _e( 'Import users from a CSV file' , 'import-users-from-csv'); ?></h2>
		<p><?php _e( 'The default behaviour is to match the user ID of each user listed in the import file against a user ID in the WordPress database. If no ID column is listed in the import file all users listed in the file are treated as new users to be added to the site. Use the options below to force matches against either the login username or the user email address or both in order to compare existing users with users in the import file and update their date when matched. The data match priority order is user ID (if found in the import file) then (optionally) login name then (optionally) email address.' , 'import-users-from-csv'); ?></p>
		<?php
		$error_log_file = self::$log_dir_path . 'is_iu_errors.log';
		$error_log_url  = self::$log_dir_url . 'is_iu_errors.log';
		
		if ( ! file_exists( $error_log_file ) ) {
			if ( ! @fopen( $error_log_file, 'x' ) ){
				$message = sprintf( __( 'Notice: please make the directory %s writable so that you can see the error log.' , 'import-users-from-csv'), self::$log_dir_path );
				self::render_notice('updated', $message);
			}
		}
		
		$import = isset( $_GET['import'] ) ? sanitize_text_field( $_GET['import'] ) : false;
		
		if ( $import ) {
			$error_log_msg = '';
			if ( file_exists( $error_log_file ) ){
				$error_log_msg = sprintf( __( ", please <a href='%s' target='_blank'>check the error log</a>", 'import-users-from-csv'), esc_url( $error_log_url ) );
			}
			
			switch ( $import ) {
				case 'file':
					$message = __( 'Error during file upload.' , 'import-users-from-csv');
					self::render_notice('notice notice-error', $message);
					break;
				case 'data':
					$message = __( 'Cannot extract data from uploaded file or no file was uploaded.' , 'import-users-from-csv');
					self::render_notice('notice notice-error', $message);
					break;
				case 'fail':
					$message = sprintf( __( 'No user was successfully imported%s.' , 'import-users-from-csv'), $error_log_msg );
					self::render_notice('notice notice-error', $message);
					break;
				case 'errors':
					$message = sprintf( __( 'Some users were successfully imported but some were not%s.' , 'import-users-from-csv'), $error_log_msg );
					self::render_notice('notice notice-info', $message);
					break;
				case 'success':
					$message = __( 'Users import was successful.' , 'import-users-from-csv');
					self::render_notice('notice notice-info', $message);
					break;
				default:
					break;
			}
		}
		?>
		
		<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'is-iu-import-users-users-page_import', '_wpnonce-is-iu-import-users-users-page_import' ); ?>
		
		<?php do_action('is_iu_import_page_before_table'); ?>
		
		<table class="form-table widefat wp-list-table" style='padding: 5px;'>
		<?php do_action('is_iu_import_page_inside_table_top'); ?>
		<tr valign="top">
		<td scope="row">
		<strong>
		<label for="users_csv"><?php _e( 'CSV file' , 'import-users-from-csv'); ?></label>
		</strong>
		</td>
		<td>
		<input type="file" id="users_csv" name="users_csv" value="" class="all-options" /><br />
		<span class="description">
		<?php
		echo sprintf( __( 'You may want to see <a href="%s">the example of the CSV file</a>.' , 'import-users-from-csv'), esc_url( plugin_dir_url(__FILE__).'examples/import.csv' ) );
		?>
		</span>
		</td>
		</tr>
		<tr valign="top">
		<td scope="row">
		<strong>
		<?php _e( 'Notification' , 'import-users-from-csv'); ?>
		</strong>
		</td>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Notification' , 'import-users-from-csv'); ?></span></legend>
		
		<label for="new_user_notification">
		<input id="new_user_notification" name="new_user_notification" type="checkbox" value="1" />
		<?php _e('Send to new users', 'import-users-from-csv'); ?>
		</label>
		</fieldset>
		</td>
		</tr>
		<tr valign="top">
		<td scope="row">
		<strong>
		<?php _e( 'Password nag' , 'import-users-from-csv'); ?>
		</strong>
		</td>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Password nag' , 'import-users-from-csv'); ?></span></legend>
		
		<label for="password_nag">
		<input id="password_nag" name="password_nag" type="checkbox" value="1" />
		<?php _e('Show password nag on new users signon', 'import-users-from-csv') ?>
		</label>
		</fieldset>
		</td>
		</tr>
		<tr valign="top">
		<td scope="row"><strong><?php _e( 'Update if username exists' , 'import-users-from-csv'); ?></strong></td>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Update if username exists' , 'import-users-from-csv' ); ?></span></legend>
		
		<label for="update_login">
		<input id="update_login" name="update_login" type="checkbox" value="1" checked />
		<?php _e( 'Update user when a username exists', 'import-users-from-csv' ) ;?>
		</label>
		</fieldset>
		</td>
		</tr>
		<tr valign="top">
		<td scope="row"><strong><?php _e( 'Users update if email match found' , 'import-users-from-csv'); ?></strong></td>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Users update if email match found' , 'import-users-from-csv' ); ?></span></legend>
		
		<label for="update_email">
		<input id="update_email" name="update_email" type="checkbox" value="1" />
		<?php _e( 'Update user when an email address is matched', 'import-users-from-csv' ) ;?>
		</label>
		</fieldset>
		</td>
		</tr>
		<tr valign="top">
		<td scope="row"><strong><?php _e( 'Add successful user imports to the errorlog' , 'import-users-from-csv'); ?></strong></td>
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Add successful user imports to the errorlog' , 'import-users-from-csv' ); ?></span></legend>
		
		<label for="log_successes">
		<input id="log_successes" name="log_successes" type="checkbox" value="1" />
		<?php _e( 'Record successful user imports in the error log', 'import-users-from-csv' ) ;?>
		</label>
		</fieldset>
		</td>
		</tr>
		<tr valign="top">
		<td>
		<?php
		$import = get_option('wp_user_import_unscheduled');
		if ( $import['last_run'] ) {
			$lastrun = date( 'D M j G:i:s T Y', $import['last_run'] );
			_e( '<small style="color: #777777">Last import: ' . $lastrun . '</small>', 'import-users-from-csv' );
		}
		?>
		</td>
		</tr>
		
		<?php do_action('is_iu_import_page_inside_table_bottom'); ?>
		
		</table>
		
		<?php do_action('is_iu_import_page_after_table'); ?>
		
		<p class="submit">
		<input type="submit" class="button-primary" name="submit" value="<?php _e( 'Import' , 'import-users-from-csv'); ?>" />
		</p>
		
		</form>
		
		<?php
		$schedule = get_option('wp_user_import_set_import_schedule');
		if ( $schedule['set'] == true ) {
			_e( '<h2>Import Schedule</h2>', 'import-users-from-csv' );
		} else {
			_e( '<h2>Schedule Import</h2>', 'import-users-from-csv' );
		}
		?>
		
		<form method="post" action="" enctype="multipart/form-data">
		<?php wp_nonce_field( 'is-iu-import-users-users-page_import_schedule', '_wpnonce-is-iu-import-users-users-page_import_schedule' ); ?>
		
		<table class="form-table widefat wp-list-table" style='padding: 5px;'>
		
		<tr valign="top">
		<td scope="row">
		<strong>
		<label for="schedule">
		<legend class="screen-reader-text"><span><?php _e( 'Schedule import time, date & periodicity' , 'import-users-from-csv' ); ?></span></legend>
		</label>
		</strong>
		<?php
		if ( ! wp_next_scheduled( 'scheduled_wp_user_import' ) ) {
			if ( !empty($schedule['timedate']) ) {
				// Restore the scheduled event if it has been deleted by a Cron Manager e.g the plugin Cron Control
				$timestamp = date( 'Y-m-d\TH:i', $schedule['timedate'] );
				if ( ! wp_next_scheduled( 'scheduled_wp_user_import' ) ) {
					wp_schedule_event( $schedule['timedate'], $schedule['period'], 'scheduled_wp_user_import' );
				}
				_e( '<span>Scheduled cron event restored. Next run:</span><small><br /><br /></small>', 'import-users-from-csv' );
			} else {
				// Display current time & date if no cron event is scheduled to run
				$timestamp = date( 'Y-m-d\TH:i', time() );
			}
		} else {
			// Display current scheduled event
			$next = wp_next_scheduled( 'scheduled_wp_user_import' );
			$timestamp = date( 'Y-m-d\TH:i', $next );
			_e( '<span>Next run:</span><small><br /></small>', 'import-users-from-csv' );
			// Show last run
			if ( $schedule['last_run_sched'] ) {
				$lastrun = date( 'D M j G:i:s T Y', $schedule['last_run_sched'] );
				_e( '<small style="color: #777777">Last import: '. $lastrun .'</small><br /><br />', 'import-users-from-csv' );
			} else {
				echo '</br />';
			}
		}
		?>
		<input type="datetime-local" id="schedule_time" name="schedule_time" value="<?php echo "$timestamp" ?>" required>
		<select id="schedule_period" name="schedule_period" required>
		<option value="" selected hidden disabled>--Periodicity--</option>
		<option value="hourly" <?php if ( $schedule['period'] == 'hourly' ) { echo 'selected'; } ?> >Hourly</option>
		<option value="daily" <?php if ( $schedule['period'] == 'daily' ) { echo 'selected'; } ?> >Daily</option>
		<option value="twicedaily" <?php if ( $schedule['period'] == 'twicedaily' ) { echo 'selected'; } ?> >Twice Daily</option>
		</select>
		<input type="url" id="schedule_file" name="schedule_file" placeholder="https://example.com/file.csv" pattern="https://.*" size="60" value="<?php echo $schedule['file'] ?>" required>
		</td>
		</tr>
		
		<tr valign="top">
		<td>
		<fieldset>
		<legend class="screen-reader-text"><span><?php _e( 'Notification' , 'import-users-from-csv'); ?></span></legend>
		<label for="scheduled_new_user_notification">
		<input id="scheduled_new_user_notification" name="scheduled_new_user_notification" type="checkbox" value="1" <?php if ( $schedule['notice'] == '1' ) { echo 'checked'; } ?> />
		<?php _e('Send email notification to new users', 'import-users-from-csv'); ?>
		</label>
		
		<legend class="screen-reader-text"><span><?php _e( 'Password nag' , 'import-users-from-csv'); ?></span></legend>
		<label for="scheduled_password_nag">
		<input id="scheduled_password_nag" name="scheduled_password_nag" type="checkbox" value="1" <?php if ( $schedule['nag'] == '1' ) { echo 'checked'; } ?> />
		<?php _e('Show password nag to new users at sign-on', 'import-users-from-csv') ?>
		</label>
		
		<br />
		<legend class="screen-reader-text"><span><?php _e( 'Update if username exists' , 'import-users-from-csv' ); ?></span></legend>
		<label for="scheduled_update_login">
		<input id="scheduled_update_login" name="scheduled_update_login" type="checkbox" value="1" <?php if ( $schedule['update_login'] == '1' ) { echo 'checked'; } ?> />
		<?php _e( 'Update user data when a username exists', 'import-users-from-csv' ) ;?>
		</label>
		
		<legend class="screen-reader-text"><span><?php _e( 'Update if email match found' , 'import-users-from-csv' ); ?></span></legend>
		<label for="scheduled_update_email">
		<input id="scheduled_update_email" name="scheduled_update_email" type="checkbox" value="1" <?php if ( $schedule['update_email'] == '1' ) { echo 'checked'; } ?> />
		<?php _e( 'Update user data when an email address is matched', 'import-users-from-csv' ) ;?>
		</label>
		
		<br />
		<legend class="screen-reader-text"><span><?php _e( 'Add successful user imports to the errorlog' , 'import-users-from-csv' ); ?></span></legend>
		<label for="scheduled_log_successes">
		<input id="scheduled_log_successes" name="scheduled_log_successes" type="checkbox" value="1" <?php if ( $schedule['log_successes'] == '1' ) { echo 'checked'; } ?> />
		<?php _e( 'Record successful user imports in the error log', 'import-users-from-csv' ) ;?>
		</label>
		</fieldset>
		</td>
		</tr>
		
		</table>
		
		<p class="submit">
		<?php if ( $schedule['set'] == false ) { ?>
			<input type="submit" class="button-primary" name="submit" value="<?php _e( 'Schedule' , 'import-users-from-csv'); ?>" />
			<?php } else { ?>
				<input type="submit" class="button-primary" name="submit" value="<?php _e( 'Unschedule' , 'import-users-from-csv'); ?>" />
				<input type="submit" class="button-primary" name="submit" value="<?php _e( 'Reschedule', 'import-users-from-csv'); ?>" />
				<input type="submit" class="button-primary" name="submit" value="<?php _e( 'Run Now' , 'import-users-from-csv'); ?>" />
				<?php } ?>
				</p>
				
				</form>
				
				<script type="text/javascript">
				
				function log_delete() {
					
					var data = {
						'action': 'iuaction_admin_ajax',
						'delete': 'delete',
					};
					
					jQuery.post(ajaxurl, data, function(response) {
						var $obj = response;
						// alert($obj['response']);
						// alert(JSON.stringify(response));
						jQuery(".iuaction").css("display", "none");
						jQuery("#delete_response").html($obj['response']);
					});
					
				}
				// console.log("log_delete");
				
				function export_users() {
					
					var data = {
						'action': 'iuaction_admin_ajax',
						'export': 'export',
					};
					
					jQuery.post(ajaxurl, data, function(response) {
						var $obj = response;
						// alert($obj['response']);
						// alert(JSON.stringify(response));
						jQuery(".iuexportaction").css("display", "none");
						jQuery("#export_response").html($obj['response']);
					});
					
				}
				// console.log("export_users");
				</script>
				
				<?php
				// Information shown after the forms
				
				// Read / Delete log file
				if ( file_exists( $error_log_file ) ){
					_e( '<p><a href="' . $error_log_url . '" target="_blank">Read the error log</a></p>', 'import-users-from-csv');
					_e( '<p><a class="iuaction" href="" onclick="log_delete()">Delete error log</a><div id="delete_response"></div></p>', 'import-users-from-csv');
				}
				
				// Export button
				_e( '<p><a class="iuexportaction" href="" onclick="export_users()">Export users to CSV file</a><div id="export_response"></div></p>', 'import-users-from-csv');
				
				$serverTime = date("D M j G:i:s T Y");
				_e( '<p>Server Time: '. $serverTime .'</p>', 'import-users-from-csv');
				
				$debug = '';
				if ( $debug == '1' ) {
					echo "Import: ";
					print_r($import);
					echo "<br /><br />Schedule: ";
					print_r($schedule);
					// print_r($_POST);
					$caught = get_option('wp_user_import_set_import_schedule_post_log');
					echo "<br /><br />Caught: ";
					print_r($caught);
					
				}
				
	}
	
	/**
	 * Import a csv file
	 *
	 * @since 0.5
	 */
	public static function import_csv( $filename, $args ) {
		/* Stop timeouts */
		@set_time_limit(0);
		
		if ( ! class_exists( 'ReadCSV' ) ) {
			include( plugin_dir_path( __FILE__ ) . 'class-readcsv.php' );
		}
		
		$errors = $user_ids = array();
		
		$defaults = array(
			'password_nag' => false,
			'new_user_notification' => false,
			'update_login' => true,
			'update_email' => false,
			'log_successes' => false
		);
		
		extract( wp_parse_args( $args, $defaults ) );
		
		/*
		 * User data field map, used to match datasets
		 */
		$userdata_fields = array(
			'ID',
			'user_login',
			'user_pass',
			'user_email',
			'user_url',
			'user_nicename',
			'display_name',
			'user_registered',
			'first_name',
			'last_name',
			'nickname',
			'description',
			'rich_editing',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'show_admin_bar_admin',
			'role'
		);
		
		/* Filter for the user field map */
		apply_filters('is_iu_userdata_fields', $userdata_fields);
		
		/* Loop through the file lines */
		$file_handle = @fopen( $filename, 'r' );
		if($file_handle) {
			$csv_reader = new ReadCSV( $file_handle, IS_IU_CSV_DELIMITER, "\xEF\xBB\xBF" ); // Skip any UTF-8 byte order mark.
			
			$first = true;
			$rkey = 0;
			while ( ( $line = $csv_reader->get_row() ) !== NULL ) {
				if ( empty( $line ) ) {
					if ( $first ){
						/* If the first line is empty, abort */
						break;
					} else{
						/* If another line is empty, just skip it */
						continue;
					}
				}
				
				if ( $first ) {
					/* If we are on the first line, the columns are the headers */
					$headers = $line;
					$first = false;
					continue;
				}
				
				/* Separate user data from meta */
				$userdata = $usermeta = array();
				foreach ( $line as $ckey => $column ) {
					$column_name = $headers[$ckey];
					$column = trim( $column );
					
					if ( in_array( $column_name, $userdata_fields ) ) {
						$userdata[$column_name] = $column;
					} else {
						/**
						 * Data cleanup:
						 *
						 * Let's do a loose match on the column name
						 * This is to allow for small typos like 'UsEr PaSS' to be converted to 'user_pass'
						 *
						 * Todo: Add support for all uppercase as well
						 */
						$formatted_column_name = strtolower($column_name);
						$formatted_column_name = str_replace(' ', '_', $formatted_column_name);
						$formatted_column_name = str_replace('-', '_', $formatted_column_name);
						if( in_array( $formatted_column_name, $userdata_fields) ){
							/**
							 * We have a formatted match
							 */
							$userdata[$formatted_column_name] = $column;
						} else {
							/*
							 * We still have no match
							 * let's assume this is a meta value
							 */
							$usermeta[$column_name] = $column;
						}
					}
				}
				
				/*
				 * Hooks to allow other plugins to filter this data
				 */
				$userdata = apply_filters( 'is_iu_import_userdata', $userdata, $usermeta );
				$usermeta = apply_filters( 'is_iu_import_usermeta', $usermeta, $userdata );
				
				if ( empty( $userdata ) ){
					/* If no user data, bailout! */
					continue;
				}
				
				/* Hook to allow other plugins to execute additional code pre-import */
				do_action( 'is_iu_pre_user_import', $userdata, $usermeta );
				
				$user = $user_id = false;
				if ( isset( $userdata['ID'] ) ){
					$user = get_user_by( 'ID', $userdata['ID'] );
				}
				
				/**
				 * Find the user by some alternative fields
				 *
				 * Fields checked: user_login then, if set, user_email
				 */
				if ( ! $user && $update_login ) {
					if ( isset( $userdata['user_login'] ) ){
						$user = get_user_by( 'login', $userdata['user_login'] );
					}
				}
				
				if ( ! $user && $update_email ) {
					if ( isset( $userdata['user_email'] ) ){
						$user = get_user_by( 'email', $userdata['user_email'] );
					}
				}
				
				$update = false;
				if ( $user ) {
					$userdata['ID'] = $user->ID;
					$update = true;
				}
				
				if ( ! $update && empty( $userdata['user_pass'] ) ){
					/* No password set for this user, let's generate one automatically */
					$userdata['user_pass'] = wp_generate_password( 12, false );
				}
				
				if ( ! empty( $userdata['role'] ) ) {
					$userdata['role'] = strtolower( $userdata['role'] );
				}
				
				if ( $update ) {
					$user_id = wp_update_user( $userdata );
					// Log successes
					if ( isset($log_successes) ) {
						$login = $user->user_login;
						$what = "UPDATED $login";
					}
				} 
				if ( ! $update ) {
					$user_id = wp_insert_user( $userdata );
					// Log successes
					if ( isset($log_successes) ) {
						if ( isset( $userdata['ID'] ) ) {
							$newuser = get_user_by( 'id', $userdata['ID'] );
						} else {
							$newuser = get_user_by( 'email', $userdata['user_email'] );
						}
						$login = $newuser->user_login;
						$what = "CREATED $login";
					}
				}
				
				/* Is there an error o_O? */
				if ( is_wp_error( $user_id ) ) {
					$errors[$rkey] = $user_id;
					$mdata[$rkey] = $userdata['user_login'] . ", " . $userdata['user_email'];
				} else {
					/* If no error, let's update the user meta too! */
					if ( $usermeta ) {
						foreach ( $usermeta as $metakey => $metavalue ) {
							$metavalue = maybe_unserialize( $metavalue );
							update_user_meta( $user_id, $metakey, $metavalue );
						}
						$successes[$rkey] = "$what";
					}
					
					/* If we created a new user, maybe set password nag and send new user notification? */
					if ( ! $update ) {
						if ( $password_nag ){
							update_user_option( $user_id, 'default_password_nag', true, true );
						}
						
						if ( $new_user_notification ) {
							wp_new_user_notification( $user_id, null, 'user' );
						}
					}
					
					/* Hook to allow other plugins to run functionality post import */
					do_action( 'is_iu_post_user_import', $user_id, $userdata, $usermeta );
					
					$user_ids[] = $user_id;
				}
				
				$rkey++;
			}
			fclose( $file_handle );
		} else {
			$errors[] = new WP_Error('file_read', 'Unable to open CSV file.');
		}
		
		/* One more thing to do after all imports? */
		do_action( 'is_iu_post_users_import', $user_ids, $errors );
		
		/* Let's log the errors */
		self::log_errors( $errors, $mdata );
		self::log_successes( $successes );
		
		return array(
			'user_ids' => $user_ids,
			'errors'   => $errors
		);
	}
	
	/**
	 * Export User Data
	 *
	 * @since 1.04
	 * Lee A. Hodson - VR51
	 **/
	
	public static function csv_user_export() {
		// NOTE: Have disabled header rows for now
		// Will reintroduce later
		
		// Optional: Configure name of directory within wp-uploads that the CSV file will import into
		$dir = trailingslashit('user-import');
		$domain = $_SERVER['SERVER_NAME'];
		$filename = 'users-' . $domain . '-' . time() . '.csv';
		
		
		ob_start();
		
		
		$header_row = array(
			'Email',
			'Name'
		);
		$data_rows = array();
		
		
		global $wpdb;
		$sql = 'SELECT * FROM ' . $wpdb->users;
		$users = $wpdb->get_results( $sql, 'ARRAY_A' );

		/*
		foreach ( $users as $user ) {
			$row = array(
				$user['user_email'],
				$user['user_name']
			);
			$data_rows[] = $row;
		}
		*/
		
		// $fh = @fopen( 'php://output', 'w' );
		$fh = @fopen( self::$log_dir_path . $dir . $filename, 'a' );
		fprintf( $fh, chr(0xEF) . chr(0xBB) . chr(0xBF) );
		header( 'Cache-Control: must-revalidate, post-check=0, pre-check=0' );
		header( 'Content-Description: File Transfer' );
		header( 'Content-type: text/csv' );
		header( "Content-Disposition: attachment; filename={$filename}" );
		header( 'Expires: 0' );
		header( 'Pragma: public' );

		/*
		fputcsv( $fh, $header_row );
		foreach ( $data_rows as $data_row ) {
			fputcsv( $fh, $data_row );
		}
		*/
	
        for ($i = 0; $i < count($users); $i++)  {
            fputcsv( $fh, $users[$i] );
        }
		
		//fputcsv( $fh, $users );
		fclose( $fh );
		
		ob_end_flush();
		
		die();
		
	}
	
	/**
	 * Log errors to a file
	 *
	 * @since 0.2
	 **/
	private static function log_errors( $errors, $mdata ) {
		if ( empty( $errors ) ){
			return;
		}
		
		$log = @fopen( self::$log_dir_path . 'is_iu_errors.log', 'a' );
		@fwrite( $log, sprintf( __( 'BEGIN ERRORS %s' , 'import-users-from-csv'), date_i18n( 'Y-m-d H:i:s', time() ) ) . "\n" );
		
		foreach ( $errors as $key => $error ) {
			$line = $key + 2;
			$message = $error->get_error_message();
			$udata = $mdata[$key];
			$message = "$message ( $udata )";
			@fwrite( $log, sprintf( __( '[Line %1$s] %2$s' , 'import-users-from-csv'), $line, $message ) . "\n" );
		}
		
		@fclose( $log );
	}
	
	/**
	 * Log successes to a file
	 *
	 * @since 1.04
	 * Lee A. Hodson - VR51
	 **/
	private static function log_successes( $successes ) {
		if ( empty( $successes ) ){
			return;
		}
		
		$log = @fopen( self::$log_dir_path . 'is_iu_errors.log', 'a' );
		@fwrite( $log, sprintf( __( 'BEGIN SUCCESSES %s' , 'import-users-from-csv'), date_i18n( 'Y-m-d H:i:s', time() ) ) . "\n" );
		
		foreach ( $successes as $key => $success ) {
			$line = $key + 2;
			@fwrite( $log, sprintf( __( '[Line %1$s] %2$s' , 'import-users-from-csv'), $line, $success ) . "\n" );
		}
		
		@fclose( $log );
	}
	
	/**
	 * Echo out a notice withs specific class.
	 *
	 * @param $class - class to add to div
	 * @param $message - The content of the notice. This should be escaped before being passed in to ensure proper escaping is done.
	 *
	 *
	 * @since 1.0.1
	 */
	private static function render_notice($class, $message){
		$class = esc_attr($class);
		echo "<div class='$class'><p><strong>$message</strong></p></div>";
	}
}

function run_scheduled_user_import() {
	$import_users_vr51 = new IS_IU_Import_Users();
	$import_users_vr51->run_scheduled_user_import();
}

IS_IU_Import_Users::init();
