<?php
/*
Plugin Name: 	Hrld Inline Links
Description: 	Makes URLs pasted on their own line inline links that can be styled as desired.
Author: 		Will Haynes
Author URI: 	http://badgerherald.com
License: 		Copyright (c) 2013 The Badger Herald


TODO:

 - This plugin should be extended to also include links to any other article on the web, using
   embed.ly's embedding service.

*/


/**
 * Enqueue's scripts and styles for plugin operation.
 * 
 * @since 0.1
 * @author Will Haynes
 */
function hrld_inline_link_embed_enqueue () {

	global $post;

	// Styling for the inline link.
	wp_enqueue_style( 'hrld_inline_link_style', plugins_url( 'css/css.css', __FILE__ ), false, '1.0.0' );

	// Count the clicks.
	wp_enqueue_script( 'hrld_inline_click_script', plugins_url( 'js/count-clicks.js', __FILE__ ), array( 'jquery' ));

	// also add the style to tiny mce.
	add_editor_style( plugins_url( 'css/css.css', __FILE__ ) );

	// When enqueueing the script, provide some js variables.
    wp_localize_script( 'hrld_inline_click_script', 'hrld_inline_click', array(
        'ajaxurl'       => admin_url( 'admin-ajax.php' ),
        'id'			=> $post->ID,
        'nonce'    	 	=> wp_create_nonce( 'hrld-count-click' ))
    );


} add_action( 'wp_enqueue_scripts', 'hrld_inline_link_embed_enqueue' );


/**
 * Ajax handler called when the user clicks an inline herald link
 *
 * The purpose of this ajax call is to gather some basic stats that we can then
 * present to editors.
 *
 * $_POST[] Variables:
 * 		hrld_inline_url - the url clicked.
 *		hrld_inline_id 	- the id of the post.
 * 
 * @since 0.1
 * @author Will Haynes
 */
function hrld_inline_click_submit_handler() {
	
	global $wpdb;

	$nonce = $_POST['nonce']; 	
	if ( ! wp_verify_nonce( $nonce, 'hrld-count-click' ) )
		die ( 'Nice Try' );

	$pid = $_POST["hrld_inline_id"];

	// Only count clicks from users who can't edit the post.
	if( ! current_user_can('edit_post',$pid) ) {
	
		$key = "_hrld-inline-click-" . $_POST["hrld_inline_url"];
	
		$curClicks = get_post_meta($pid,$key,true);

		if($curClicks == "" ) {
			add_post_meta($pid,$key,1,true);
		} else {
			update_post_meta($pid,$key,$curClicks+1);
		}
	}

	return;

}
add_action( 'wp_ajax_ajax-hrld_inline_click_script', 'hrld_inline_click_submit_handler' );
add_action( 'wp_ajax_nopriv_ajax-hrld_inline_click_script', 'hrld_inline_click_submit_handler' );

/**
 * Parses the passed in http://badgerherald.com url and returns output 'embed' code.
 *
 * To support development environment, we alter the URL to include the local
 * site url.
 *
 * @since 0.1
 * @author Will Haynes
 * 
 * @param $matches array — the part of the url that was matched by the regex.
 * @param $attr array - sizing info for the embed.
 * @param $url string - the url to embed.
 * @param $rawattr array - other attributes passed in.
 * 
 * @return embed code.
 */
function hrld_inline_link_embed( $matches, $attr, $url, $rawattr ) {

	global $post;

	// For now.

	if( home_url() == "http://localhost/bhrld") {
		$url = str_replace("badgerherald.com","localhost/bhrld",$url);
	} else if( home_url() == "http://localhost:8080/bhrld") {
		$url = str_replace("badgerherald.com","localhost:8080/bhrld",$url);
	}

	$ret = "";

	// Get the post ID from the supplied url.
	$postid = url_to_postid($url);

	// Query the post.
	$p = get_post($postid);

	$ret .= "<a target='_BLANK' class='hrld-inline-link' href='" . $url . "'>";

	$thumb 	= wp_get_attachment_image_src( get_post_thumbnail_id($postid), 'small-thumbnail' );

	if($thumb) {
		$thumb = $thumb['0'];
		$ret .= "<img class='hrld-inline-thumbnail' src='$thumb' />";
	}

	$key = "_hrld-inline-click-" . $url;
	$clicks = get_post_meta($post->ID,$key,true);

	$ret .=	"<span class='hrld-inline-link-title'>";

	if( is_user_logged_in() && current_user_can('edit_post') && $clicks != "") { 
		$ret .= "<span class='hrld-inline-click-count'>" . $clicks . " Click";
		if($clicks != 1) {
			$ret .= "s";
		}
		$ret .= "</span> | ";
	}

	$ret .= $p->post_title . "</span>";

	/* append the excerpt */
	$excerpt = $p->post_content;
	$excerpt = strip_shortcodes($excerpt);
	$re2='((?:http|https)(?::\\/{2}[\\w]+)(?:[\\/|\\.]?)(?:[^\\s"]*))';	
	
	$excerpt = preg_replace("/".$re2."/is", "", $excerpt);

	$excerpt = wp_trim_words($excerpt, 20, '<span class="excerpt-more"> ...</span>');

	$ret .= "<span class='hrld-inline-link-excerpt'>" . $excerpt . "</span>";


	$ret .= "</a>";

	return $ret;
}
/**
 * Registers a new callback when a badgerherald.com link is 
 * posted on its own line within the editor.
 */
wp_embed_register_handler( 'herald', '*(?:http|https)://badgerherald.com/*', 'hrld_inline_link_embed' );
