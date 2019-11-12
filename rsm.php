<?php
/*
Plugin Name: Best Practices Reminder
Description: Best Practices Reminder, directly from the handbook!
Plugin URI:  https://mmilosevic.com/
Author:      Milos
Version:     1.0.1
 */

// setting schedules

function bpr_add_custom_post_type() {

	$args = array(
		'labels' => array('name' => 'Best Practices'),
		'public' => false,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'query_var' => true,
		'rewrite' => array('slug' => 'bpr'),
		'capability_type' => 'post',
		'has_archive' => true,
		'hierarchical' => false,
		'menu_position' => null,
		'supports' => array('title', 'editor', 'author'),
	);

	register_post_type('bpr', $args);

}

add_action('init', 'bpr_add_custom_post_type');

function bpr_wpcron($schedules) {

	// one minute
	$one_minute = array(
		'interval' => 60,
		'display' => 'One Minute',
	);

	$schedules['one_minute'] = $one_minute;

	//daily
	$daily = array(
		'interval' => 86400,
		'display' => 'Daily',
	);

	$schedules['daily'] = $daily;

	// return data
	return $schedules;

}

add_filter('cron_schedules', 'bpr_wpcron');

if (!wp_next_scheduled('bpr_time_set')) {
	wp_schedule_event(time(), 'daily', 'bpr_time_set');
}

function bpr_post_get() {

	//arguments for the query, sorting of the values
	$args = array(
		'post_type' => 'bpr',
		'meta_key' => 'bpr_time',
		'posts_per_page' => 1,
		'orderby' => 'meta_value_num',
		'order' => 'ASC',
	);

	// The Query
	$bpr_posts_query = new WP_Query($args);

	//get the object array
	$slack_message_post = $bpr_posts_query->posts[0];

	// get the post id
	$post_id = $slack_message_post->ID;

	return ($post_id);

}

function bpr_update_post_timestamp($post_id) {
	//take current timestamp
	$bpr_grab_time = time();

	//Update post meta with the timestamp based on the post id obtained above
	update_post_meta(
		$post_id, // Post ID
		'bpr_time', // Meta key
		$bpr_grab_time // Meta value
	);
}

// register meta box for bpr_time
function bpr_add_metabox() {

	$post_types = array('bpr', 'bpr');

	foreach ($post_types as $post_type) {

		add_meta_box(
			'bpr_time', // Unique ID of meta box
			'BPR Time', // Title of meta box
			'bpr_time', // Callback function
			$post_type // Post type
		);

	}

}

add_action('add_meta_boxes', 'bpr_add_metabox');

//set deafult value for new post, display time under wp-admin
function bpr_time($post) {

	if (null == (get_post_meta($post->ID, 'bpr_time', true))) {

		$value = 1561711699; //deafult value

		update_post_meta(
			$post->ID, // Post ID
			'bpr_time', // Meta key
			$value // Meta value
		);

		$read_timestamp = date('m/d/Y H:i:s', $value);
	} else {
		$value = get_post_meta($post->ID, 'bpr_time', true);

		$bpr_read_timestamp = date('m/d/Y H:i:s', $value);

	}

	?>

	<label for="myplugin-meta-box">Last time shown: <?php echo $bpr_read_timestamp; ?></label>

	<?php

}

function bpr_send_message_to_slack($bpr_message) {

	//slack webhook logic

	define('SLACK_WEBHOOK', 'https://hooks.slack.com/services/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');
	// Make your message
	$message = array('payload' => json_encode(array('text' => $bpr_message)));
	// Use curl to send your message
	$c = curl_init(SLACK_WEBHOOK);
	curl_setopt($c, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($c, CURLOPT_POST, true);
	curl_setopt($c, CURLOPT_POSTFIELDS, $message);
	curl_exec($c);
	curl_close($c);
}

function bpr_run_cron() {

	bpr_post_get();
	$post_id = bpr_post_get();
	bpr_update_post_timestamp($post_id);
	$bpr_message = get_post_field('post_content', $post_id);
	bpr_send_message_to_slack($bpr_message);

}

add_action('bpr_time_set', 'bpr_run_cron');
