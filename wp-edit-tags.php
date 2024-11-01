<?php
/**
 * @package Phototagger
 * @author Jon Tascher
 */


$attachment_id = (isset($_GET['attachment_id']) && is_numeric($_GET['attachment_id']) ? $_GET['attachment_id'] : false);

if (!current_user_can('edit_post', $attachment_id)) {
	wp_die(__('You are not allowed to edit this attachment.'));
}

if(!$attachment_id) {
	wp_die(__('Invalid attachment.'));
}

global $wpdb;
$image = wp_get_attachment_image_src($attachment_id, 'large');

?>
<h2>Edit Image Tags</h2>
<div class="wrap">
	<div id="image_div">
		<?php echo "<img src=\"{$image[0]}\" width=\"{$image[1]}]\" height=\"{$image[2]}\" id=\"main_image\" class=\"tagged_image-{$attachment_id}\" alt=\"tagged_image\">"; ?>
	</div>
	<br clear="all" />
	<strong>Tags in this photo:</strong>
	<div id="tags_div">&nbsp;</div>
	<br />
	<br />
	<input type="submit" class="button-primary" onclick="return false" id="add_tags" value="Add New Tag"/>
</div>



	

