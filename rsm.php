<?php
/**
 * Plugin Name: Best Practices Reminder
 * Description: Best Practices Reminder, directly from the handbook!
 * Plugin URI:  https://mmilosevic.com/
 * Author:      Milos
 * Version:     1.0.1
 */

/**
 * Creating custom post type
 */
function bpr_add_custom_post_type() {

	$args = array(
		'labels'             => array( 'name' => 'Best Practices' ),
		'public'             => false,
		'publicly_queryable' => true,
		'show_ui'            => true,
		'show_in_menu'       => true,
		'query_var'          => true,
		'rewrite'            => array( 'slug' => 'bpr' ),
		'capability_type'    => 'post',
		'has_archive'        => true,
		'hierarchical'       => false,
		'menu_position'      => null,
		'supports'           => array( 'title', 'editor', 'author' ),
	);
	register_post_type( 'bpr', $args );
}

add_action( 'init', 'bpr_add_custom_post_type' );

/**
 * Bpr_wpcron - setting intervals
 *
 * @param mixed $schedules - retrun interval.
 */
function bpr_wpcron( $schedules ) {

	$one_minute = array(
		'interval' => 60,
		'display'  => 'One Minute',
	);

	$schedules['one_minute'] = $one_minute;

	$daily = array(
		'interval' => 86400,
		'display'  => 'Daily',
	);

	$schedules['daily'] = $daily;

	return $schedules;
}

add_filter( 'cron_schedules', 'bpr_wpcron' );

if ( ! wp_next_scheduled( 'bpr_time_set' ) ) {
	wp_schedule_event( time(), 'daily', 'bpr_time_set' );
}

/**
 * Bpr_post_get - get the post for the message.
 *
 * $post_id - retrun post_id.
 */
function bpr_post_get() {

	$args = array(
		'post_type'      => 'bpr',
		'meta_key'       => 'bpr_time',
		'posts_per_page' => 1,
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
	);

	$bpr_posts_query = new WP_Query( $args );

	$slack_message_post = $bpr_posts_query->posts[0];

	$post_id = $slack_message_post->ID;

	return ( $post_id );

}

/**
 * Bpr_update_post_timestamp
 *
 * @param Post_id $post_id forward post_id to update timestamp.
 */
function bpr_update_post_timestamp( $post_id ) {
	// Get current timestamp.
	$bpr_grab_time = time();

	// Update post meta with the timestamp based on the post id obtained above.
	update_post_meta(
		$post_id,
		'bpr_time',
		$bpr_grab_time
	);
}


/**
 * Bpr_add_metabox - that holds timestamp.
 */
function bpr_add_metabox() {

	$post_types = array( 'bpr', 'bpr' );

	foreach ( $post_types as $post_type ) {

		add_meta_box(
			'bpr_time', // Unique ID of meta box.
			'BPR Time',
			'bpr_time',
			$post_type
		);

	}

}
add_action( 'add_meta_boxes', 'bpr_add_metabox' );

/**
 * Bpr_time - updating post meta.
 *
 * @param Post $post mixed $post.
 */
function bpr_time( $post ) {

	if ( null === ( get_post_meta( $post->ID, 'bpr_time', true ) ) ) {

		$value = 1561711699; // deafult value.

		update_post_meta(
			$post->ID,
			'bpr_time',
			$value
		);

		$read_timestamp = gmdate( 'm/d/Y H:i:s', $value );
	} else {
		$value = get_post_meta( $post->ID, 'bpr_time', true );

		$bpr_read_timestamp = gmdate( 'm/d/Y H:i:s', $value );

	}
	?>
<label for="myplugin-meta-box">Last time shown: <?php echo esc_html( $bpr_read_timestamp ); ?></label>
	<?php
}

/**
 * Bpr_send_message_to_slack
 *
 * @param Bpr_message $bpr_message - forward message to send.
 */
function bpr_send_message_to_slack( $bpr_message ) {

	// Slack webhook logic.
	define( 'SLACK_WEBHOOK', 'add_URL' );
	// Make your message.
	$message = array( 'payload' => wp_json_encode( array( 'text' => $bpr_message ) ) );
	// Use curl to send your message.
	$c = curl_init( SLACK_WEBHOOK );
	curl_setopt( $c, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $c, CURLOPT_POST, true );
	curl_setopt( $c, CURLOPT_POSTFIELDS, $message );
	curl_exec( $c );
	curl_close( $c );
}

/**
 * Bpr_run_cron
 *
 * @return void
 */
function bpr_run_cron() {

	bpr_post_get();
	$post_id = bpr_post_get();
	bpr_update_post_timestamp( $post_id );
	$bpr_message = get_post_field( 'post_content', $post_id );
	bpr_send_message_to_slack( $bpr_message );

}

add_action( 'bpr_time_set', 'bpr_run_cron' );
