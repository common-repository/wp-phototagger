var tag_array = {};

function phototaggertag_public() {
	var t = this;
	
	this.init = function() {
		for(var post_id in tag_array) {
			for(var image_id in tag_array[post_id]) {;
				for(var tag_id in tag_array[post_id][image_id]) {
					
					var tag_info = tag_array[post_id][image_id][tag_id];
					var image = jQuery('#tagged_image-' + post_id + '-' + image_id);
					var offset = jQuery(image).offset();

					var w_ratio = parseInt(tag_info['image_width'], 10) / parseInt(jQuery(image).width(), 10);
					var h_ratio = parseInt(tag_info['image_height'], 10) / parseInt(jQuery(image).height(), 10);
					
					var new_left = parseInt((tag_info['left'] / w_ratio) + offset.left,10);
					var new_top = parseInt((tag_info['top'] / h_ratio) + offset.top, 10);
					var new_width = parseInt((tag_info['width'] / w_ratio), 10);
					var new_height = parseInt((tag_info['height'] / h_ratio), 10);
					new_width -= 6;
					new_height -= 6;
					
					var z_index = parseInt(10000 - ((new_width * new_height) / 100), 10);
					
					var outer_style = 'z-index: ' + z_index + '; top: ' + new_top + 'px; left: ' + new_left + 'px; ';
					var inner_style = 'z-index: ' + z_index + '; width: ' + new_width + 'px; height: ' + new_height + 'px';
					var ie_table = jQuery('<table border="0" cellpadding="0" cellspacing="0" width="100%" height="100%" style="position: absolute; left: 3px; top: 3px; height: 3px; width: ' + new_width + 'px; height: ' + new_height +'px;"><tr><td>&nbsp;</td></tr></table>');
					
					var outer = jQuery('<div id="tag_frame-' + post_id + '-' + image_id + '-' + tag_id + '" class="tag_frame" style="cursor: pointer; ' + outer_style + '">');
					var inner = jQuery('<div style="' + inner_style + '">&nbsp;</div>');

					if(1||jQuery.browser.msie) {
						jQuery(ie_table).appendTo(jQuery(inner));
					}
					
					jQuery(inner).appendTo(jQuery(outer));
					jQuery(image).after(jQuery(outer));
					
					jQuery(outer).mouseover(function() {
						jQuery(this).css('opacity', 1);
					});

					jQuery(outer).mouseout(function() {
						jQuery(this).css('opacity', 0);

					});
				}
			}
		}
		jQuery('[id^=taglink-]').each(function() {
			var link = this;
			var ids = jQuery(this).attr('id').split('-');
			var p_id = ids[1];
			var i_id = ids[2];
			var t_id = ids[3];
			var id_string = 'tag_frame-' + p_id + '-' + i_id + '-' + t_id;

			jQuery(this).mouseover(function() {
				jQuery('#' + id_string).css('opacity', 1);
				jQuery(link).addClass('selected_tag');
			});
			jQuery(this).mouseout(function() {
				jQuery('#' + id_string).css('opacity', 0);
				jQuery(link).removeClass('selected_tag');
			});
			
			jQuery('#' + id_string).mouseover(function() {
				jQuery(link).addClass('selected_tag');
			});
			
			jQuery('#' + id_string).mouseout(function() {
				jQuery(link).removeClass('selected_tag');
			});
			
			jQuery('#' + id_string).click(function() {
				window.open(jQuery(link).attr('href'));
			});
		});
	}
}

jQuery(document).ready(function() {
	var t = new phototaggertag_public();
	t.init();
});