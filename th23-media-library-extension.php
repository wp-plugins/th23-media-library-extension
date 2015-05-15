<?php
/*
Plugin Name: th23 Media Library Extension
Plugin URI: http://th23.net/th23-media-library-extension
Description: Adds advanced filter options to the Media Library, attachment links to edit posts/ pages overview.
Version: 1.0.0
Author: Thorsten Hartmann (th23)
Author URI: http://th23.net
Text Domain: th23_media_library
License: GPLv2 only
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Copyright 2010-2015, Thorsten Hartmann (th23)
http://th23.net/

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License version 2, as published by the Free Software Foundation. You may NOT assume that you can use any other version of the GPL.
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU General Public License for more details.
This license and terms apply for the Basic part of this program as distributed, but NOT for the separately distributed Professional add-on!
*/

define('TH23_MEDIA_LIBRARY_BASEDIR', '/' . str_replace('/' . basename(__FILE__), '', plugin_basename(__FILE__)));


// init only on admin area
function th23_media_library_init() {
	// localize
	load_plugin_textdomain('th23_media_library', WP_PLUGIN_DIR . TH23_MEDIA_LIBRARY_BASEDIR . '/lang/', TH23_MEDIA_LIBRARY_BASEDIR . '/lang/');
	// add "Private" tag on plugin overview page
	add_filter('plugin_row_meta', 'th23_media_library_version', 10, 2);
	// add link to edit posts/ pages overview directing to filtered attachments
	add_filter('post_row_actions', 'th23_media_library_add_posts_pages_media_link', 10, 2);
	add_filter('page_row_actions', 'th23_media_library_add_posts_pages_media_link', 10, 2);
	// add filter to display only attachments of specified parent post/ page
	add_filter('posts_where', 'th23_media_library_filter_apply');
	add_action('restrict_manage_posts', 'th23_media_library_filter_show');
	// replace standard "attached to" column
	add_filter('manage_media_columns', 'th23_media_library_columns');
	add_filter('manage_upload_sortable_columns', 'th23_media_library_columns_sortable');
	add_filter('request', 'th23_media_library_columns_sortable_orderby');
	add_action('manage_media_custom_column', 'th23_media_library_columns_attached_to', 10, 2);
}
add_action('admin_init', 'th23_media_library_init');

// Add "Private" tag on plugin overview page
function th23_media_library_version($links, $file) {
	if(plugin_basename(__FILE__) == $file) {
		$links[0] = $links[0] . ' <strong><i style="color: #FF9900;">Private</i></strong>';
	}
	return $links;
}

// add link to edit posts/ pages overview directing to filtered attachments
function th23_media_library_add_posts_pages_media_link($actions, $post) {
	$actions[] = '<a href="upload.php?th23_post_parent=' . $post->ID . '">' . __('Show Media', 'th23_media_library') . '</a>';
	return $actions;
}

// add filter to display only attachments of specified parent post/ page (add where clause)
function th23_media_library_filter_apply($where) {
	global $pagenow;
	if($pagenow == 'upload.php') {
		if(isset($_REQUEST['th23_post_parent']) && (int) $_REQUEST['th23_post_parent'] >= 0) {
			global $wpdb;
			$where .= ' AND ' . $wpdb->posts . '.post_parent = ' . (int) $_REQUEST['th23_post_parent'];
		}
	}
	return $where;
}

// add filter to display only attachments of specified parent post/ page (add selection dropdown)
function th23_media_library_filter_show() {
	global $pagenow;
	if($pagenow != 'upload.php') {
		return;
	}

	// post_parent selection
	global $wpdb;
	
	$post_parents = $wpdb->get_results("SELECT a.ID, a.post_title, (SELECT COUNT(ID) FROM $wpdb->posts WHERE post_type = 'attachment' AND post_parent = a.ID) AS attachment_count 
		FROM $wpdb->posts a 
		WHERE (post_type = 'post' OR post_type = 'page') AND (post_status = 'publish' OR post_status = 'draft' OR post_status = 'pending') 
		ORDER BY a.post_title ASC");
	$post_parent_options = array(-1 => array(-1, __('Show all parents', 'th23_media_library')), 0 => array(0, __('(Unattached)', 'th23_media_library')));
	foreach ($post_parents as $post_parent) {
		if ($post_parent->attachment_count > 0) {
			$post_parent_options[$post_parent->ID] = array($post_parent->ID, $post_parent->post_title);
		}
	}
	unset($post_parents);

	$post_parent_id = (isset($_REQUEST['th23_post_parent']) && (int) $_REQUEST['th23_post_parent'] >= 0) ? (int) $_REQUEST['th23_post_parent'] : -1;

	if(!isset($post_parent_options[$post_parent_id])) {
		$post_parent_id = -1;
	}

	echo ' ' . th23_build_select('th23_post_parent', $post_parent_options, $post_parent_id, 'th23_post_parent');

}

// build select drop down
// param $options needs to be an array('value', 'title', 'css_class'), title and css can be left empty
function th23_build_select($name, $options, $selected = '', $id = '', $class = '', $multiple = false) {

	$id_html = ($id) ? ' id="' . $id . '"' : '';
	$class_html = ($class) ? ' class="' . $class . '"' : '';
	if ($multiple) {
		$name .= '[]';
		$multiple_html = ' multiple="multiple"';
	} else {
		$multiple_html = '';
	}

	$html_select = '<select name="' . $name . '"' . $id_html . $class_html . $multiple_html . '>';

	if (is_array($options)) {
		foreach ($options as $option) {
			
			if (!is_array($option) || !isset($option[0])) {
				continue;
			}
			$value = (string) $option[0];

			if ($multiple && is_array($selected)) {
				$selected_html = (in_array($value, $selected)) ? ' selected="selected"' : '';
			} else {
				$selected_html = ($value == $selected) ? ' selected="selected"' : '';
			}

			$title = (isset($option[1])) ? (string) $option[1] : $value;
			$style_html = (isset($option[2])) ? ' class="' . (string) $option[2] . '"' : '';			
			$html_select .= '<option value="' . $value . '"' . $selected_html . $style_html . '>' . $title . '</option>';

		}
	}

	return $html_select . '</select>';

}

// replace standard "attached to" column
function th23_media_library_columns($defaults) {
	$defaults_new = array();
	foreach($defaults as $default_key => $default_value) {
		if($default_key == 'parent') {
			$default_key = 'th23_media_library_attached_to';
			$default_value = __('Attached to', 'th23_media_library');
		}
		$defaults_new[$default_key] = $default_value;
	}
	return $defaults_new;
}

function th23_media_library_columns_sortable($columns) {
	$columns['th23_media_library_attached_to'] = 'th23_media_library_attached_to';
	return $columns;
}

function th23_media_library_columns_sortable_orderby($vars) {
	if(isset($vars['orderby'] ) && $vars['orderby'] == 'th23_media_library_attached_to') {
		$vars['orderby'] = 'parent';
	} 
	return $vars;
}

function th23_media_library_columns_attached_to($column_name, $id) {
	if ($column_name == 'th23_media_library_attached_to') {    
		$parent_id = (int) get_post_field('post_parent', (int) $id);
		if ( $parent_id > 0 ) {
			echo '<strong><a href="' . get_edit_post_link($parent_id) . '">' . _draft_or_post_title($parent_id) . '</a></strong>';
			echo '<p>' . get_the_time(__('Y/m/d'), $parent_id) . '</p>';
			echo '<div class="row-actions">';
			// add "re-attach" link
			echo '<a class="hide-if-no-js" onclick="findPosts.open(\'media[]\',\'' . $id . '\');return false;" href="#the-list">' . __('Re-Attach', 'th23_media_library') . '</a>';
			// add filter link "only media attached to this item"
			echo ' | <a href="upload.php?th23_post_parent=' . $parent_id . '">' . __('Show All Media', 'th23_media_library') . '</a>';
			echo '</div>';
		} else {
			echo __('(Unattached)', 'th23_media_library');
			echo '<p>&nbsp;</p>';
			echo '<div class="row-actions"><a class="hide-if-no-js" onclick="findPosts.open(\'media[]\',\'' . $id . '\');return false;" href="#the-list">' . __('Attach', 'th23_media_library') . '</a></div>';
		}	
	}
}

?>