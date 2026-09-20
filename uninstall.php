<?php
/**
 * Uninstall file for Maldives Packages Booking.
 *
 * Triggered when the plugin is deleted via the WordPress Admin.
 *
 * NOTE: Destructive cleanup logic (deleting custom tables, options, or booking records)
 * is intentionally omitted at this stage until the data retention policy is finalized.
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Non-destructive placeholder: preserve user and booking records safely.
