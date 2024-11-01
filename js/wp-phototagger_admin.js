/**
 * phototagger_admin()
 *
 * main admin class
 *
 * @return phototagger_admin object
 */
function phototagger_admin() {
	
	var t = this; //cache a copy of 'this' object because jquery uses 'this' in weird ways..
	
	this.image = jQuery('#main_image'); //image being tagged
	this.add_button = jQuery('#add_tags'); //the add new tag button
	
	this.adding = false;  //are we currently adding a tag
	
	this.abs_x;  //abs x and y position of mouseclick
	this.abs_y; 
	this.image_x; //reletive x y position of click on the image
	this.image_y;
	
	this.tag_top;
	this.tag_left;
	this.tag_width;
	this.tag_length;
	
	
	/**
	 * init();
	 *
	 * set up the page
	 * get existing tags
	 * create boxes for existing tags
	 * bind events for viewing, editing, and adding tags
	 * 
	 * @return void
	 */
	this.init = function() {
		
		//get existing tags and create
		jQuery.post('admin-ajax.php', {
			action: 'phototagger_get_tags_ajax',
			post_id: jQuery('#main_image').attr('class').split('-')[1]
		},
		function(tags) {
			for(var i in tags) {
				//create the link
				if(link = t.create_tag_link(tags[i])) {
					link.appendTo('#tags_div');
					jQuery('#tags_div').append(', ');
					
					//create the tag box
					if(box = t.create_tag_box(tags[i])) {
						jQuery('#image_div').append(box);
					}
				}
			}
			
			var div = jQuery('<div id="edit_div">&nbsp;</div>');
			jQuery(div).append('<strong>Enter Tag:  </strong><br /><input type="text" id="tag"><br />');
			jQuery(div).append('<br /><strong>Enter Destination:  </strong><input type="text" style="width: 350px;" id="dest">');
			jQuery(div).append('<br /><br /><input type="submit" value="Save" name="save" id="save_tag" class="button-primary"/><input id="cancel_tag" type="submit" value="Cancel" name="cancel" class="button-primary"/>');
			jQuery(div).append('<input type="hidden" id="editing" value="">');
			jQuery(t.image).after(jQuery(div));
			
			jQuery('#tag').suggest( 'admin-ajax.php?action=phototagger_search_tags', { delay: 500, minchars: 2, multiple: false } );
			
			jQuery('#save_tag').click(function() {
				t.save_tag();
			});
			
			jQuery('#cancel_tag').click(function() {
				jQuery('#edit_div').hide(600, function(){
					jQuery('#outer_frame').remove();
					t.create_tag_boxes();
				});
				t.adding = false;
			});
			
			//add the event for the new tag button
			jQuery(t.add_button).click(function() {
				if(!t.adding) {					
					t.show_edit(false);
					t.swap_cursor();
				}
			});
			
		}, 'json');
	}
	
	/**
	 * swap_cursor()
	 *
	 * change the cursor to or from a crosshair
	 *
	 * @rturn void
	 */
	this.swap_cursor = function() {
		if(t.image.hasClass('crosshair')) {
			t.image.removeClass('crosshair');
		}
		else {
			t.image.addClass('crosshair');
		}
	}
	
	/**
	 * save_tag()
	 *
	 * save a new or edited tag
	 *
	 * @return void
	 */
	t.save_tag = function() {
		
		if(!jQuery('#tag').attr('value')) {
			alert('You must enter a tag');
		}
		else if(!jQuery('#dest').attr('value')) {
			alert('You must enter a url');
		}
		else {

			jQuery('#save_tag').unbind('click');
		
			jQuery.post('admin-ajax.php', t.get_tag_info(),
				function(response) {
					t.reset();
				}, 'json'
			);
		}
	}
	
	/**
	 * reset()
	 *
	 * reset the page
	 *
	 * @return void
	 */
	this.reset = function() {
		jQuery('#edit_div').hide(600, function() {
			jQuery('#edit_div').remove();
			jQuery('#outer_frame').remove();
			jQuery('#tags_div').empty();
			t.destroy_tags();
			t.init();
			t.adding = false;
		});
	}
	
	/**
	 * get_tag_info()
	 *
	 * build and return an array of info about the current tag
	 *
	 * @return object
	 */
	this.get_tag_info = function() {
		return {
			action: 'phototagger_newtag',
			tag: jQuery('#tag').attr('value'),
			url: jQuery('#dest').attr('value'),
			post_id: jQuery('#main_image').attr('class').split('-')[1],
			top: jQuery('#outer_frame').css('top'),
			left: jQuery('#outer_frame').css('left'),
			width: jQuery('#outer_frame').width(),
			height: jQuery('#outer_frame').height(),
			image_width: jQuery(t.image).attr('width'),
			image_height: jQuery(t.image).attr('height'),
			editing: jQuery('#editing').attr('value')
		};
	}
	
	/**
	 * create_tag_boxes()
	 *
	 * reconstruct the tag object sent by the first ajax request
	 * and pass it to create_tag_box()
	 *
	 * @return void
	 */
	this.create_tag_boxes = function() {
		jQuery('.tag_link').each(function() {

			//top-left-width-height-id
			var info = jQuery(this).attr('id').split('-');
			var tag = {
				height: info[3],
				id: info[4],
				left: info[1],
				top: info[0],
				tag: jQuery(this).text(),
				url: jQuery(this).attr('href'),
				width: info[2]
			};

			if(box = t.create_tag_box(tag)) {
				jQuery('#image_div').append(box);
			}
		});
	}
	
	/**
	 * create_tag_box()
	 *
	 * create a tag frame
	 *
	 * @param tag an object containing information about the tag
	 * @return a jquery object containing the divs in the frame
	 */
	this.create_tag_box = function(tag) {
		
		var z_index = parseInt(10000 - ((tag['width'] * tag['height']) / 100), 10);
		var outer_style = 'z-index: ' + z_index + '; top: ' + tag['top'] + 'px; left: ' + tag['left'] + 'px; ';
		var inner_style = 'z-index: ' + z_index + '; width: ' + (tag['width'] - 6) + 'px; height: ' + (tag['height']  - 6) + 'px';
		
		var outer = jQuery('<div id="tag_frame-' + tag['id'] + '" class="tag_frame" style="cursor: pointer; ' + outer_style + '">');
		var inner = jQuery('<div style="' + inner_style + '">&nbsp;</div>');
		jQuery(inner).appendTo(jQuery(outer));
		
		jQuery(outer).mouseover(function() {
			t.show_box(tag['id']);
		});
		
		jQuery(outer).mouseout(function() {
			t.hide_box(tag['id']);
		});
		
		return outer;
	}
	
	/**
	 * show_box()
	 *
	 * show a tag box
	 *
	 * @param id the id of the box to show
	 * @return void
	 */
	this.show_box = function(id) {
		if(!t.adding) {
			jQuery('#tag_frame-' + id).css('opacity', 1);
		}
	}
	
	/**
	 * hide_box()
	 *
	 * hide a tag box
	 *
	 * @param id the id of the box to hide
	 * @return void
	 */
	this.hide_box = function(id) {
		if(!t.adding) {
			jQuery('#tag_frame-' + id).css('opacity', 0);
		}
	}
	
	/**
	 * create_tag_link()
	 *
	 * create a tag and edit link for a tag
	 *
	 * @param tag an object containing information about the tag
	 * @return a jquery object containing the two links inside a span
	 */
	this.create_tag_link = function(tag) {
		if(tag['tag']) {
			
			var span = jQuery('<span>&nbsp;</span>');
			var id = tag['top'] + '-' + tag['left'] + '-' + tag['width'] + '-' + tag['height'] + '-' + tag['id'];
			var tag_link = jQuery('<a href="javascript: void(0);" id="' + id + '" class="tag_link">' + tag['tag'] + '</a>');
			var edit_link = jQuery(' <a href="javascript: void(0);" id="edit-' + tag['id'] + '">edit</a>');
			var delete_link = jQuery('<a href="javascript: void(0);" id="delete-' + tag['id'] + '">delete</a>');
			
			jQuery(tag_link).mouseover(function() {
				t.show_box(tag['id']);
				jQuery(this).addClass('selected_tag');
			});
			
			jQuery(tag_link).mouseout(function() {
				t.hide_box(tag['id']);
				jQuery(this).removeClass('selected_tag');
			});
			
			jQuery(edit_link).click(function() {
				t.show_edit(tag);
			});
			
			jQuery(delete_link).click(function() {
				if(confirm('Are you sure you want to delete this tag?')) {
					jQuery.post('admin-ajax.php', {
						action: 'phototagger_delete_tag',
						tag_id: jQuery(this).attr('id').split('-')[1]
					}, function() {
						t.reset();
					});
				}
			});
			
			jQuery(span).append(tag_link);
			jQuery(span).append('&nbsp;');
			jQuery(span).append('(');
			jQuery(span).append(edit_link);
			jQuery(span).append('&nbsp;/&nbsp;');
			jQuery(span).append(delete_link);
			jQuery(span).append(')');
			return span;
		}
		return false;
	}
	
	/**
	 * create_edit_tag()
	 *
	 * create draggable and resizable box for tag editing
	 *
	 * @param top the css top attribute
	 * @param left the css left attribute
	 * @param width the width
	 * @param height the height
	 * @return void
	 */
	this.create_edit_tag = function(top, left, width, height) {
		
		jQuery('#outer_frame').remove();

		var style = 'left: ' + left + 'px; top: ' + top + 'px; width: ' + width + 'px; height: ' + height + 'px;';
		var div = jQuery('<div id="outer_frame" style="' + style + '">&nbsp;</div>');
		jQuery(div).appendTo(jQuery('#image_div'));
		
		jQuery(div).resizable({
			containment: t.image,
			handles: 'all'
		});
		
		jQuery(div).draggable({
			containment: t.image
		});
	}
	
	/**
	 * get_click_position()
	 *
	 * set some class members
	 *
	 * @return void
	 */
	this.get_click_position = function(e) {
		t.abs_x = e.pageX;
		t.abs_y = e.pageY;
		t.image_x = t.abs_x - jQuery(t.image).offset().left;
		t.image_y = t.abs_y - jQuery(t.image).offset().top;
	}
	
	/**
	 * show_edit()
	 * 
	 * show the edit box
	 * 
	 * @param tag if present, box will be prefilled with tag info
	 * @return void
	 */
	this.show_edit = function(tag) {
		if(tag) {
			jQuery('#tag').val(tag['tag']);
			jQuery('#dest').val(tag['url']);
			jQuery('#editing').val(tag['id']);
			
			t.create_edit_tag(tag['top'], tag['left'], tag['width'], tag['height']);
			
			jQuery('#edit_div').show(600, function() {
				t.destroy_tags();
			});
			t.adding = true;
		}
		else {
			jQuery('#tag').val('');
			jQuery('#dest').val('');
			jQuery('#editing').val('false');
			
			t.destroy_tags();
			t.adding = true;
			
			jQuery(t.image).click(function(e) {
				t.get_click_position(e);
				t.create_edit_tag(t.image_y, t.image_x, 75, 75);
				jQuery('#edit_div').show(600);
				t.swap_cursor();
			});
		}
	}
	
	/**
	 * destroy_tags()
	 *
	 * remove all tag frames on the page
	 *
	 * @return void
	 */
	this.destroy_tags = function() {
		jQuery('.tag_frame').remove();
	}
}

jQuery(document).ready(function() {
	var t = new phototagger_admin();
	t.init();
});