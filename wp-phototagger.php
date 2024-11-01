<?php
/**
 * @package Phototagger
 * @author Jon Tascher
 * @version 0.8.6
 */
/*
Plugin Name: WP-Phototagger
Plugin URI: http://jontascher.com/phototagger
Description: Facebook-like Phototagging functionality
Author: Jon Tascher
Version: 0.8.6
Author URI: http://www.jontascher.com
*/

if(!class_exists('Phototagger')) {
	class Phototagger {
		/**
		 * Constructor
		 *
		 * @return void
		 * @author Jon Tascher
		 */
		function Phototagger() {
			
			$this->fix_json();
			
			//activation
			register_activation_hook(__FILE__, array(&$this, 'create_tables'));
			register_deactivation_hook(__FILE__, array(&$this, 'drop_tables'));
			
			//actions
			add_action('admin_print_scripts', array(&$this, 'add_admin_header_content'));
			add_action('wp_ajax_phototagger_newtag', array(&$this, 'phototagger_newtag'));
			add_action('wp_ajax_phototagger_get_tags_ajax', array(&$this, 'phototagger_get_tags_ajax'));
			add_action('wp_ajax_phototagger_search_tags', array(&$this, 'phototagger_search_tags'));
			add_action('wp_ajax_phototagger_delete_tag', array(&$this, 'phototagger_delete_tag'));
			add_action('delete_attachment', array(&$this, 'delete_image'));
			add_action('wp_print_scripts', array(&$this, 'add__header_content'));
			
			//filters
			add_filter('attachment_fields_to_edit', array(&$this, 'insert_tag_button'), 10, 2);
			add_filter('image_send_to_editor', array(&$this, 'flag_tagged_images'), 10, 2);
			add_filter('the_content', array(&$this, 'handle_post'));
		}
		
		
		function fix_json() {
			if(!function_exists('json_encode')) {
				require_once('json.php');
				function json_encode($data) {
					$json = new Services_JSON();
					return($json->encode($data));
				}
			}
			if(!function_exists('json_decode')) {
				require_once('json.php');
				function json_decode($data) {
					$json = new Services_JSON();
					return($json->decode($data));
				}
			}
		}
		
		/**
		 * delete_image
		 *
		 * Called whenever an item is deleted from the media library
		 *
		 * @param int $attachment_id 
		 * @return void
		 * @author Jon Tascher
		 */
		  function delete_image($attachment_id) {
			global $wpdb;
			$wpdb->query("DELETE FROM {$wpdb->prefix}phototagger_image_tags WHERE post_id = '{$attachment_id}'");
		}
		
		/**
		 * flag_tagged_images
		 *
		 * @param string $html 
		 * @param int $id 
		 * @return string
		 * @author Jon Tascher
		 */
		  function flag_tagged_images($html, $id) {
			if(isset($_POST['show_tags']) && $_POST['show_tags'] == 'yes') {
				$html = '<div id="tagged_image_div-' . $_POST['post_id'] . '-' . $id . '">' . $html . '</div>';
			}
			return $html;
		}
		
		/**
		 * add__header_content
		 * 
		 * 
		 *
		 * @return void
		 * @author Jon Tascher
		 */
		 function add__header_content() {
			echo '<link type="text/css" rel="stylesheet" href="' . $this->get_plugin_url() . '/css/wp-phototagger.css" />' . "\n";
			wp_enqueue_script('phototagger', $this->get_plugin_url() . '/js/wp-phototagger_public.js', array('jquery'));
		}
		
		  function phototagger_search_tags() {
			
			global $wpdb;
			
			$s = $_GET['q']; // is this escaped already?

			if (strpos($s, ',' ) !== false) {
				$s = explode(',', $s);
				$s = $s[count($s) - 1];
			}
			$s = trim($s);
			if ( strlen($s) < 2 )
				die; // require 2 chars for matching
			
			$s = $wpdb->escape($s);
			$results = $wpdb->get_col( "SELECT t.tag FROM {$wpdb->prefix}phototagger_tags AS t WHERE t.tag LIKE ('%". $s . "%')" );
			echo join($results, "\n");
			die();
		}
		
		 function handle_post($post) {	
			return preg_replace_callback('|(<div.*id="tagged_image_div-([\d]+)-([\d]+)".*)(<img.*/>)(.*)(</div>)|i', array(&$this, 'regex_callback'), $post);
		}
		
		 function regex_callback($matches) {
			//want to find any image tags inside of divs with an id starting with "tagged_image-"
			// @TODO: This won't work if another plugin is altering images by adding another image tag.. 
				
			$whole_block = $matches[0];
			$everything_up_to_img_tag = $matches[1];
			$post_id = $matches[2];
			$image_id = $matches[3];
			$img_tag = $matches[4];
			$other_closing = $matches[5];
			$div_close = $matches[6];
			
			//see if this image has any tags
			global $wpdb;
			
			$tags = $wpdb->get_results("
				SELECT tags.id AS tag_id, tags.tag, image_tags.*  
				FROM {$wpdb->prefix}phototagger_tags AS tags 
				INNER JOIN {$wpdb->prefix}phototagger_image_tags as image_tags 
				ON tags.id = image_tags.tag_id 
				WHERE image_tags.post_id = '{$image_id}'
			");
			
			if(count($tags)) {
				$script = '<script type="text/javascript">' . "\n";
				$html = '<br clear="all" /><div id="photo_tags-' . $post_id . '"><strong>Tags: </strong>' . "\n";
				foreach($tags as $tag) {
					
					$script .= "
						if(typeof tag_array[{$post_id}] == 'undefined') {
							tag_array[{$post_id}] = {};
						}
						if(typeof tag_array[{$post_id}][{$image_id}] == 'undefined') {
							tag_array[{$post_id}][{$image_id}] = {};
						}
						tag_array[{$post_id}][{$image_id}][{$tag->tag_id}] = {
							top: {$tag->tag_top},
							left: {$tag->tag_left},
							width: {$tag->tag_width},
							height: {$tag->tag_height},
							image_width: {$tag->image_width},
							image_height: {$tag->image_height},
							text: '{$tag->tag}',
							url: '{$tag->url}',
							image_id: {$tag->post_id},
							post_id: {$post_id}
						};\n";
					
					$html .= '<a target="_blank" href="' . $tag->url . '" id="taglink-' . $post_id . '-' . $image_id . '-' . $tag->tag_id . '">' . $tag->tag . '</a>, ' . "\n";
				}
				$html = substr($html, 0, -2);
				$html .= '</div>';
				$script .= '</script>' . "\n";

				$img_tag = str_replace('/>', 'id="tagged_image-' . $post_id . '-' . $image_id . '" />', $img_tag);

				return
					$everything_up_to_img_tag 
					.$img_tag 
					.$other_closing
					.$html 
					.$script 
					.$div_close;
			}
			// }	
			return $whole_block;
		}
		
		function get_plugin_url() {
			return plugins_url(plugin_basename(dirname(__FILE__)));
		}
		
		 function add_admin_header_content() {
			if(isset($_GET['page']) && $_GET['page'] == 'wp-phototagger/wp-edit-tags.php') {
				echo '<link type="text/css" rel="stylesheet" href="' . $this->get_plugin_url() . '/css/wp-phototagger.css" />' . "\n";
				wp_enqueue_script('phototagger', $this->get_plugin_url() . '/js/wp-phototagger_admin.js', array('jquery', 'suggest', 'jquery-ui-resizable', 'jquery-ui-draggable'));
			}
		}
		
		  function phototagger_delete_tag() {
			global $wpdb;
			
			$tag_id = preg_replace('/[^\d]/', '',$_POST['tag_id']);
			$wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}phototagger_image_tags WHERE id = %d", $tag_id));
			echo 1;
			die();
		}
		
		  function phototagger_newtag() {
			global $wpdb;
			
			//clean up the input b/c sometimes js sends us screwy stuff
			$tag = $wpdb->escape(trim($_POST['tag']));
			$url = $wpdb->escape(trim($_POST['url']));
			$post_id = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['post_id']));
			$top = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['top']));
			$left = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['left']));
			$width = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['width']));
			$height = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['height']));
			$image_width = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['image_width']));
			$image_height = $wpdb->escape(preg_replace('/[^\d]/', '', $_POST['image_height']));
			$editing = is_numeric($_POST['editing']) ? $wpdb->escape($_POST['editing']) : false;
			
			//see if the tag already exists, if not, create it
			if(!$tag_id = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}phototagger_tags WHERE tag = '{$tag}'")) {
				$wpdb->query("INSERT INTO {$wpdb->prefix}phototagger_tags (`tag`) VALUES ('{$tag}')");
				$tag_id = mysql_insert_id();
			}
			
			
			if($editing) {
				$cols = array('`id`, `post_id`', '`tag_id`', '`url`', '`tag_left`', '`tag_top`', '`tag_width`', '`tag_height`', '`image_width`', '`image_height`');
				$vals = array("'{$editing}'", "'{$post_id}'", "'{$tag_id}'", "'{$url}'", "'{$left}'", "'{$top}'", "'{$width}'", "'{$height}'", "'{$image_width}'", "'{$image_height}'");
				$wpdb->query("REPLACE INTO {$wpdb->prefix}phototagger_image_tags (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
			}
			else {
				//now save the tagged image
				$cols = array('`post_id`', '`tag_id`', '`url`', '`tag_left`', '`tag_top`', '`tag_width`', '`tag_height`', '`image_width`', '`image_height`');
				$vals = array("'{$post_id}'", "'{$tag_id}'", "'{$url}'", "'{$left}'", "'{$top}'", "'{$width}'", "'{$height}'", "'{$image_width}'", "'{$image_height}'");
				$wpdb->query("INSERT INTO {$wpdb->prefix}phototagger_image_tags (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ')');
			}
			
			echo '{}';
			die();
		}
		
		  function get_tag_list($id) {
			global $wpdb;

			$id = $wpdb->escape($id);

            $sql = "SELECT tags.tag, image_tags.tag_top AS `top`, image_tags.tag_left AS `left`, image_tags.id, image_tags.tag_width AS `width`, image_tags.tag_height AS `height`, image_tags.url
                    FROM {$wpdb->prefix}phototagger_image_tags image_tags
                    INNER JOIN {$wpdb->prefix}phototagger_tags tags
                    ON tags.id = image_tags.tag_id
                    WHERE image_tags.post_id = '{$id}'";
			return json_encode($wpdb->get_results($sql));
		}
		
		  function phototagger_get_tags_ajax() {
			$post_id = preg_replace('/[^\d]/', '', $_POST['post_id']);
			echo $this->get_tag_list($post_id);
			die();
		}
		
		
		 function insert_tag_button($form_fields, $post) {

			if(preg_match('/image/', $post->post_mime_type)) {
				
				if(isset($_GET['attachment_id'])) {
					$form_fields['phototagger']['label'] = '';
					$form_fields['phototagger']['html'] = '<button class="button-primary" onclick="window.location.href=\'tools.php?page=wp-phototagger%2fwp-edit-tags.php&attachment_id=' . $post->ID . '\';return false;">Edit Tags</button>';
					$form_fields['phototagger']['input'] = 'html';
				}
				elseif(!preg_match('/async-upload/i', $_SERVER['PHP_SELF'])) {
					$form_fields['phototagger']['label'] = 'Show Tags';
					$form_fields['phototagger']['html'] = '<input type="checkbox" name="show_tags" value="yes" id="show_tags">';
					$form_fields['phototagger']['input'] = 'html';
				}
			}
			return $form_fields;
		}
		
		
		/**
		 * create_tables
		 * 
		 * Creates necessary db tables
		 *
		 * @return void
		 * @author Jon Tascher
		 */
		 function create_tables() {
			global $wpdb;
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

			$table = $wpdb->prefix .'phototagger_tags';
			if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
				$sql = "CREATE TABLE {$table} (
						  id mediumint(9) NOT NULL AUTO_INCREMENT,
						  tag varchar(255) NOT NULL,
						  UNIQUE KEY id (id),
						  UNIQUE KEY `tag_idx` (`tag`)
						);";
				dbDelta($sql);	
			}

			$table = $wpdb->prefix .'phototagger_image_tags';
		    if($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
				$sql = "CREATE TABLE {$table} (
						  id mediumint(9) NOT NULL AUTO_INCREMENT,
						  post_id mediumint(9) NOT NULL,
						  tag_id varchar(255) NOT NULL,
						  url varchar(255),
						  tag_left int(12) unsigned NOT NULL,
						  tag_top int(12) unsigned NOT NULL,
						  tag_width int(12) unsigned NOT NULL,
						  tag_height int(12) unsigned NOT NULL,
						  image_width int(12) unsigned NOT NULL,
						  image_height int(12) unsigned NOT NULL,
						  UNIQUE KEY id (id)
						);";
				dbDelta($sql);	
			}
		}
		
		/**
		 * drop_tables
		 *
		 * Drop related db tables
		 *
		 * @return void
		 * @author Jon Tascher
		 */
		 function drop_tables() {
			global $wpdb;
			
			$table = $wpdb->prefix .'phototagger_tags';
			$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
			
			$table = $wpdb->prefix .'phototagger_image_tags';
			$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
			
		}
	}
}

if(class_exists('Phototagger')) {
	$phototagger = new Phototagger();
}