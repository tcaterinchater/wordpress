<?php
/**
 * Plugin Name: Automated Email Export
 * Description: Sends Vault Job CSV reports on schedule and supports manual triggers via URL.
 * Version: 1.2
 * Author: Your Name
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Wait for Action Scheduler to be ready
add_action( 'plugins_loaded', 'aee_init_plugin' );

function aee_init_plugin() {
	// Only proceed if Action Scheduler is available
	if ( ! function_exists( 'as_next_scheduled_action' ) ) {
		add_action( 'admin_notices', function() {
			echo '<div class="notice notice-error"><p>Automated Email Export: Action Scheduler plugin is required but not found.</p></div>';
		});
		return;
	}
	
	// Schedule the job after Action Scheduler is ready
	aee_setup_scheduled_events();
}

/**
 * Setup scheduled events using Action Scheduler
 */
function aee_setup_scheduled_events() {
	// Check if our daily event is already scheduled
	if ( ! as_next_scheduled_action( 'aee_daily_time_check' ) ) {
		// Schedule daily at 11:55 PM EST
		$timezone = new DateTimeZone( 'America/New_York' );
		$first_run = new DateTime( 'today 23:55', $timezone );
		
		// If we've passed today's time, start tomorrow
		$now = new DateTime( 'now', $timezone );
		if ( $first_run <= $now ) {
			$first_run->add( new DateInterval( 'P1D' ) );
		}
		
		as_schedule_recurring_action(
			$first_run->getTimestamp(),
			DAY_IN_SECONDS, // Every 24 hours
			'aee_daily_time_check',
			[],
			'aee-email-export'
		);
		
		error_log( 'AEE: Daily task scheduled for ' . $first_run->format( 'Y-m-d H:i:s T' ) );
	}
}

/**
 * Plugin activation
 */
register_activation_hook( __FILE__, function() {
	// Schedule setup on next page load to ensure Action Scheduler is ready
	add_option( 'aee_needs_setup', true );
});

// Handle delayed setup
add_action( 'init', function() {
	if ( get_option( 'aee_needs_setup' ) ) {
		delete_option( 'aee_needs_setup' );
		if ( function_exists( 'as_next_scheduled_action' ) ) {
			aee_setup_scheduled_events();
		}
	}
});

register_deactivation_hook( __FILE__, function() {
	// Clear Action Scheduler events
	if ( function_exists( 'as_unschedule_all_actions' ) ) {
		as_unschedule_all_actions( 'aee_daily_time_check', [], 'aee-email-export' );
		as_unschedule_all_actions( 'aee_single_scheduled_run', [], 'aee-email-export' );
	}
	// Also clear any old wp_cron events
	wp_clear_scheduled_hook( 'aee_hourly_time_check' );
});

/**
 * Daily Action Scheduler task - runs at 11:55 PM EST
 */
add_action( 'aee_daily_time_check', function() {
	try {
		$tz = new DateTimeZone( 'America/New_York' );
		$now = new DateTime( 'now', $tz );

		$day = (int) $now->format( 'd' );
		$last_day_of_month = (int) $now->format( 't' );
		$is_last_day = ( $day === $last_day_of_month );

		// Calculate mid-month Tuesday
		$mid_month_tuesday = null;
		$year = (int) $now->format('Y');
		$month = (int) $now->format('m');
		for ( $d = 15; $d <= 21; $d++ ) {
			$check_ts = strtotime( sprintf( '%04d-%02d-%02d', $year, $month, $d ) );
			if ( date( 'w', $check_ts ) == 2 ) {
				$mid_month_tuesday = $check_ts;
				break;
			}
		}
		
		$is_one_day_before_mid_tuesday = false;
		if ( $mid_month_tuesday ) {
			$one_day_before_ts = strtotime( '-1 day', $mid_month_tuesday );
			if ( $day === (int) date( 'd', $one_day_before_ts ) ) {
				$is_one_day_before_mid_tuesday = true;
			}
		}

		// Send email on last day of month OR one day before mid-month Tuesday
		if ( $is_last_day || $is_one_day_before_mid_tuesday ) {
			generate_and_send_vault_job_csv();
			error_log( 'AEE: Email sent - Last day: ' . ($is_last_day ? 'YES' : 'NO') . ', Mid-month Tuesday: ' . ($is_one_day_before_mid_tuesday ? 'YES' : 'NO') );
		} else {
			error_log( 'AEE: Daily check completed - no email sent today' );
		}
		
	} catch ( Exception $e ) {
		error_log( 'AEE Daily Check Error: ' . $e->getMessage() );
	}
});

// Keep the old hourly hook for backward compatibility
add_action( 'aee_hourly_time_check', function() {
	error_log( 'AEE: Old hourly hook called - migrating to daily Action Scheduler' );
	// Clear the old hook and set up new one
	wp_clear_scheduled_hook( 'aee_hourly_time_check' );
	if ( function_exists( 'as_next_scheduled_action' ) ) {
		aee_setup_scheduled_events();
	}
});

/**
 * Manual triggers (admin only) - Enhanced with better error handling
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Manual test trigger
	if ( isset( $_GET['run_email_test'] ) && $_GET['run_email_test'] == '1' ) {
		// Add nonce verification for security
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'aee_manual_test' ) ) {
			wp_die( 'Security check failed.' );
		}
		
		$result = generate_and_send_vault_job_csv( true );
		if ( $result ) {
			wp_die( 'Manual vault-job CSV email triggered successfully.' );
		} else {
			wp_die( 'Failed to send email. Check error logs.' );
		}
	}

	// Schedule for specific time
	if ( isset( $_GET['schedule_for'] ) ) {
		// Add nonce verification
		if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( $_GET['_wpnonce'], 'aee_schedule' ) ) {
			wp_die( 'Security check failed.' );
		}
		
		$when = sanitize_text_field( wp_unslash( $_GET['schedule_for'] ) );
		$ts   = strtotime( $when );
		if ( $ts && $ts > time() ) {
			// Use Action Scheduler instead of wp_schedule_single_event
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $ts, 'aee_single_scheduled_run', [], 'aee-email-export' );
				wp_die( "Single event scheduled for " . date( 'Y-m-d H:i:s', $ts ) . " (Server Time) via Action Scheduler" );
			} else {
				// Fallback to wp_cron
				wp_schedule_single_event( $ts, 'aee_single_scheduled_run' );
				wp_die( "Single event scheduled for " . date( 'Y-m-d H:i:s', $ts ) . " (Server Time) via WP Cron" );
			}
		} else {
			wp_die( 'Invalid or past datetime string provided.' );
		}
	}
});

add_action( 'aee_single_scheduled_run', function() {
	generate_and_send_vault_job_csv( true );
});

/**
 * Generate CSV and send email - Enhanced with better error handling
 */
function generate_and_send_vault_job_csv( $manual = false ) {
	global $wpdb;

	try {
		// Set timezone
		date_default_timezone_set( 'US/Eastern' );
		$days_15_ago_date = date( 'Y-m-d', strtotime( '-10 days' ) );

		// Get fiscal year
		if ( function_exists( 'get_active_and_next_fiscal_years' ) ) {
			$fiscal_years = get_active_and_next_fiscal_years();
			$current_year = (int) $fiscal_years['year'];
		} else {
			$current_year = (int) date( 'Y' );
		}
		
		$half_membership_current_year = $current_year - 1;
		$full_year_end_date = $current_year . '-06-30';
		$half_year_end_date = $half_membership_current_year . '-12-31';

		// Prepare the query with proper escaping
		$query = $wpdb->prepare("
		SELECT 
			wp_posts.ID, 
			order_key.meta_value AS order_key_value, 
			order_total.meta_value AS order_total_value, 
			user_data.meta_value AS customer_user_id,
			Parent_post.ID AS Parent_ID, 
			Parent_post.post_status AS Parent_post_status, 
			Parent_post.post_type AS Parent_post_type, 
			Parent_auto_renewed_order.meta_value AS auto_renewed_order_value, 
			Parent_postmeta_payment_method.meta_value AS parent_payment_method,
			Parent_postmeta_card.meta_value AS parent_payment_method_title
		FROM `wp_posts` 
		LEFT JOIN `wp_postmeta` AS `order_key` ON `wp_posts`.`ID`=`order_key`.`post_id` AND `order_key`.`meta_key` = '_order_key'  
		LEFT JOIN `wp_postmeta` AS `order_total` ON `wp_posts`.`ID`=`order_total`.`post_id` AND `order_total`.`meta_key` = '_order_total'  
		LEFT JOIN `wp_postmeta` AS `user_data` ON `wp_posts`.`ID`=`user_data`.`post_id` AND `user_data`.`meta_key` = '_customer_user'  
		LEFT JOIN `wp_posts` AS Parent_post ON Parent_post.ID = wp_posts.post_parent
		LEFT JOIN `wp_postmeta` AS `Parent_auto_renewed_order` ON `Parent_post`.`ID`=`Parent_auto_renewed_order`.`post_id` AND `Parent_auto_renewed_order`.`meta_key` = 'auto_renewed_order'  
		LEFT JOIN `wp_postmeta` AS Parent_postmeta_payment_method ON `Parent_post`.`ID`=`Parent_postmeta_payment_method`.`post_id` AND `Parent_postmeta_payment_method`.`meta_key` = '_payment_method'
		LEFT JOIN `wp_postmeta` AS Parent_postmeta_card ON `Parent_post`.`ID`=`Parent_postmeta_card`.`post_id` AND `Parent_postmeta_card`.`meta_key` = '_payment_method_title'
		INNER JOIN wp_woocommerce_order_items AS i ON i.order_id = Parent_post.ID
		INNER JOIN wp_woocommerce_order_itemmeta AS oi ON i.order_item_id = oi.order_item_id 
		LEFT JOIN wp_postmeta Product_expiry ON Product_expiry.post_id = oi.meta_value AND Product_expiry.meta_key = 'product_end_date'
		WHERE wp_posts.ID NOT IN (
			SELECT m1.post_id FROM wp_moneris_scheduled_payments m1 
			LEFT JOIN wp_moneris_scheduled_payments m2 ON (m1.post_id = m2.post_id AND m1.id < m2.id) 
			WHERE m2.id IS NULL AND CAST(m1.created AS DATE) > %s
		)
		AND wp_posts.post_status IN ('wc-scheduled-payment', 'wc-pending-deposit','wc-nsf','wc-failed')
		AND wp_posts.post_type = 'shop_order'
		AND CAST(wp_posts.post_date AS DATE) <= %s
		AND (Parent_auto_renewed_order.meta_key IS NULL OR Parent_auto_renewed_order.meta_value = '' OR Parent_auto_renewed_order.meta_value = 0)
		AND Parent_postmeta_payment_method.meta_value = 'moneris'
		AND user_data.meta_value IS NOT NULL
		AND Parent_post.post_status = 'wc-partial-payment'
		AND oi.meta_key = '_product_id'
		AND (Product_expiry.meta_value = %s OR Product_expiry.meta_value = %s)
		AND i.order_item_name NOT LIKE %s
		ORDER BY user_data.meta_value
		", $days_15_ago_date, date( 'Y-m-d' ), $full_year_end_date, $half_year_end_date, '%sig%');

		$results = $wpdb->get_results( $query, ARRAY_A );

		if ( empty( $results ) ) {
			$log_msg = $manual ? 'Manual trigger: No results for vault job CSV.' : 'Scheduled: No results for vault job CSV.';
			error_log( 'AEE: ' . $log_msg );
			return false;
		}

		// Generate CSV file
		$csv_filename = 'vault_jobs_' . date( 'Ymd_His' ) . '.csv';
		$theme_csv_dir = get_stylesheet_directory() . '/csv_files';
		$file_path = '';

		// Try to create directory in theme, fallback to temp
		if ( wp_mkdir_p( $theme_csv_dir ) && is_writable( $theme_csv_dir ) ) {
			$file_path = trailingslashit( $theme_csv_dir ) . $csv_filename;
		} else {
			$file_path = wp_tempnam( $csv_filename );
		}

		$fh = fopen( $file_path, 'w' );
		if ( ! $fh ) {
			error_log( 'AEE: Cannot write CSV to ' . $file_path );
			return false;
		}

		// Write CSV headers
		fputcsv( $fh, [
			'Child Order ID',
			'order_key_value',
			'order_total_value',
			'customer_user_id',
			'Parent_ID',
			'Parent_post_status',
			'Parent_post_type',
			'auto_renewed_order_value',
			'parent_payment_method',
			'parent_payment_method_title'
		]);

		// Write data rows
		foreach ( $results as $row ) {
			fputcsv( $fh, $row );
		}
		fclose( $fh );

		// Send email
		$to = 'harshal@108ideaspace.com';
		$subject = 'Vault Job List - ' . date( 'Y-m-d H:i' );
		$message = 'Attached is the vault job CSV export.' . "\n\n";
		$message .= 'Total records: ' . count( $results ) . "\n";
		$message .= 'Generated: ' . date( 'Y-m-d H:i:s T' ) . "\n";
		
		if ( $manual ) {
			$message .= 'Trigger: Manual' . "\n";
		} else {
			$message .= 'Trigger: Automated Schedule' . "\n";
		}

		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		$sent = wp_mail( $to, $subject, $message, $headers, [ $file_path ] );

		// Clean up temp files
		if ( ! strpos( $file_path, get_stylesheet_directory() ) && file_exists( $file_path ) ) {
			@unlink( $file_path );
		}

		if ( $sent ) {
			error_log( 'AEE: Email sent successfully with CSV (' . count( $results ) . ' records)' );
			return true;
		} else {
			error_log( 'AEE: Email failed to send' );
			return false;
		}
		
	} catch ( Exception $e ) {
		error_log( 'AEE: Exception in generate_and_send_vault_job_csv: ' . $e->getMessage() );
		return false;
	}
}

/**
 * Manual triggers (admin only) - Enhanced with better security
 */
add_action( 'admin_init', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// Manual test trigger
	if ( isset( $_GET['run_email_test'] ) && $_GET['run_email_test'] == '1' ) {
		// Generate nonce if not present (for backward compatibility)
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			$nonce_url = wp_nonce_url( admin_url( 'admin.php?run_email_test=1' ), 'aee_manual_test' );
			wp_redirect( $nonce_url );
			exit;
		}
		
		// Verify nonce
		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'aee_manual_test' ) ) {
			wp_die( 'Security check failed.' );
		}
		
		$result = generate_and_send_vault_job_csv( true );
		if ( $result ) {
			wp_die( 'Manual vault-job CSV email triggered successfully. Check your email.' );
		} else {
			wp_die( 'Failed to send email. Check error logs for details.' );
		}
	}

	// Schedule for specific time
	if ( isset( $_GET['schedule_for'] ) ) {
		// Generate nonce if not present (for backward compatibility)
		if ( ! isset( $_GET['_wpnonce'] ) ) {
			$schedule_time = sanitize_text_field( wp_unslash( $_GET['schedule_for'] ) );
			$nonce_url = wp_nonce_url( admin_url( 'admin.php?schedule_for=' . urlencode( $schedule_time ) ), 'aee_schedule' );
			wp_redirect( $nonce_url );
			exit;
		}
		
		// Verify nonce
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'aee_schedule' ) ) {
			wp_die( 'Security check failed. Please use the admin panel or generate a proper nonce URL.' );
		}
		
		$when = sanitize_text_field( wp_unslash( $_GET['schedule_for'] ) );
		$ts   = strtotime( $when );
		if ( $ts && $ts > time() ) {
			// Use Action Scheduler instead of wp_schedule_single_event
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action( $ts, 'aee_single_scheduled_run', [], 'aee-email-export' );
				wp_die( "Single event scheduled for " . date( 'Y-m-d H:i:s', $ts ) . " (Server Time) via Action Scheduler" );
			} else {
				// Fallback to wp_cron
				wp_schedule_single_event( $ts, 'aee_single_scheduled_run' );
				wp_die( "Single event scheduled for " . date( 'Y-m-d H:i:s', $ts ) . " (Server Time) via WP Cron" );
			}
		} else {
			wp_die( 'Invalid or past datetime string provided. Use format like "tomorrow 3pm" or "2025-09-01 15:30"' );
		}
	}
});

add_action( 'aee_single_scheduled_run', function() {
	generate_and_send_vault_job_csv( true );
});

/**
 * Add admin menu for easier management
 */
add_action( 'admin_menu', function() {
	add_management_page(
		'Email Export Tools',
		'Email Export',
		'manage_options',
		'aee-tools',
		'aee_admin_page'
	);
});

function aee_admin_page() {
	$manual_test_url = wp_nonce_url( admin_url( 'admin.php?run_email_test=1' ), 'aee_manual_test' );
	
	// Generate schedule URLs for easy access
	$schedule_now_url = wp_nonce_url( admin_url( 'admin.php?schedule_for=' . urlencode( 'now + 2 minutes' ) ), 'aee_schedule' );
	$schedule_tomorrow_url = wp_nonce_url( admin_url( 'admin.php?schedule_for=' . urlencode( 'tomorrow 9am' ) ), 'aee_schedule' );
	
	// Get next scheduled action from Action Scheduler
	$next_scheduled = function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( 'aee_daily_time_check' ) : null;
	$next_scheduled_date = $next_scheduled ? date( 'Y-m-d H:i:s T', $next_scheduled ) : 'Not scheduled';
	
	// Handle form submissions for better UX
	if ( $_POST && current_user_can( 'manage_options' ) ) {
		$action = sanitize_text_field( $_POST['action'] ?? '' );
		
		switch ( $action ) {
			case 'schedule_now':
				if ( wp_verify_nonce( $_POST['_wpnonce'], 'aee_schedule_now' ) ) {
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( time() + 120, 'aee_single_scheduled_run', [], 'aee-email-export' );
						echo '<div class="notice notice-success"><p>Email scheduled for 2 minutes from now via Action Scheduler!</p></div>';
					} else {
						wp_schedule_single_event( time() + 120, 'aee_single_scheduled_run' );
						echo '<div class="notice notice-success"><p>Email scheduled for 2 minutes from now via WP Cron!</p></div>';
					}
				}
				break;
				
			case 'schedule_tomorrow':
				if ( wp_verify_nonce( $_POST['_wpnonce'], 'aee_schedule_tomorrow' ) ) {
					$tomorrow_9am = strtotime( 'tomorrow 9am' );
					if ( function_exists( 'as_schedule_single_action' ) ) {
						as_schedule_single_action( $tomorrow_9am, 'aee_single_scheduled_run', [], 'aee-email-export' );
						echo '<div class="notice notice-success"><p>Email scheduled for tomorrow at 9 AM via Action Scheduler!</p></div>';
					} else {
						wp_schedule_single_event( $tomorrow_9am, 'aee_single_scheduled_run' );
						echo '<div class="notice notice-success"><p>Email scheduled for tomorrow at 9 AM via WP Cron!</p></div>';
					}
				}
				break;
				
			case 'reschedule':
				if ( wp_verify_nonce( $_POST['_wpnonce'], 'aee_reschedule' ) ) {
					if ( function_exists( 'as_unschedule_all_actions' ) ) {
						as_unschedule_all_actions( 'aee_daily_time_check', [], 'aee-email-export' );
					}
					wp_clear_scheduled_hook( 'aee_hourly_time_check' );
					aee_setup_scheduled_events();
					echo '<div class="notice notice-success"><p>Daily task rescheduled!</p></div>';
				}
				break;
		}
	}
	?>
	<div class="wrap">
		<h1>Automated Email Export</h1>
		
		<div class="card" style="max-width: 600px;">
			<h3>Manual Actions</h3>
			<p>Test the email export functionality:</p>
			<p>
				<a href="<?php echo esc_url( $manual_test_url ); ?>" class="button button-primary">
					Send Test Email Now
				</a>
			</p>
			
			<h4>Quick Schedule Options:</h4>
			<form method="post" style="display: inline;">
				<?php wp_nonce_field( 'aee_schedule_now' ); ?>
				<input type="hidden" name="action" value="schedule_now">
				<input type="submit" value="Schedule for 2 minutes from now" class="button">
			</form>
			
			<form method="post" style="display: inline; margin-left: 10px;">
				<?php wp_nonce_field( 'aee_schedule_tomorrow' ); ?>
				<input type="hidden" name="action" value="schedule_tomorrow">
				<input type="submit" value="Schedule for tomorrow 9 AM" class="button">
			</form>
			
			<h4>Custom Schedule:</h4>
			<form method="get">
				<input type="hidden" name="page" value="aee-tools">
				<input type="text" name="schedule_for" placeholder="tomorrow 3pm" style="width: 200px;">
				<?php wp_nonce_field( 'aee_schedule' ); ?>
				<input type="submit" value="Schedule" class="button">
			</form>
			<p><em>Examples: "tomorrow 3pm", "2025-09-01 15:30", "next Monday 2pm"</em></p>
		</div>
		
		<div class="card" style="max-width: 600px; margin-top: 20px;">
			<h3>Schedule Information</h3>
			<p><strong>Automatic Schedule:</strong> Every day at 11:55 PM EST</p>
			<p><strong>Send Conditions:</strong></p>
			<ul>
				<li>Last day of the month</li>
				<li>One day before mid-month Tuesday (15th-21st)</li>
			</ul>
			<p><strong>Next Scheduled:</strong> <?php echo esc_html( $next_scheduled_date ); ?></p>
			<p><strong>Action Scheduler Status:</strong> <?php echo function_exists( 'as_next_scheduled_action' ) ? '✅ Available' : '❌ Not Available'; ?></p>
			<?php if ( ! function_exists( 'as_next_scheduled_action' ) ): ?>
				<p style="color: red;"><strong>⚠️ Action Scheduler plugin is not installed or activated. Please install it for reliable scheduling.</strong></p>
			<?php endif; ?>
		</div>
		
		<div class="card" style="max-width: 600px; margin-top: 20px;">
			<h3>Actions</h3>
			<form method="post">
				<?php wp_nonce_field( 'aee_reschedule' ); ?>
				<input type="hidden" name="action" value="reschedule">
				<input type="submit" value="Reschedule Daily Task" class="button">
				<p><em>Use this if the automatic scheduling isn't working</em></p>
			</form>
		</div>
		
		<div class="card" style="max-width: 600px; margin-top: 20px;">
			<h3>URL Examples (for bookmarks)</h3>
			<p><strong>Manual Test:</strong><br><code><?php echo esc_url( $manual_test_url ); ?></code></p>
			<p><strong>Schedule Now:</strong><br><code><?php echo esc_url( $schedule_now_url ); ?></code></p>
			<p><strong>Schedule Tomorrow:</strong><br><code><?php echo esc_url( $schedule_tomorrow_url ); ?></code></p>
		</div>
	</div>
	<?php
}

/**
 * Add debug info for troubleshooting
 */
add_action( 'wp_ajax_aee_debug', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized' );
	}
	
	$debug_info = [
		'action_scheduler_available' => function_exists( 'as_next_scheduled_action' ),
		'next_action_scheduler' => function_exists( 'as_next_scheduled_action' ) ? as_next_scheduled_action( 'aee_daily_time_check' ) : null,
		'next_wp_cron' => wp_next_scheduled( 'aee_hourly_time_check' ),
		'current_time' => time(),
		'current_time_formatted' => date( 'Y-m-d H:i:s T' ),
		'timezone' => date_default_timezone_get(),
		'woocommerce_active' => class_exists( 'WooCommerce' ),
	];
	
	wp_send_json( $debug_info );
});
?>