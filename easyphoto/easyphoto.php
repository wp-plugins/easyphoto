<?php
/*
Plugin Name: Easyphoto
Plugin URI: http://www.alterclickr.com/projects/easyphoto/
Description: This plugin allow to better manage images in your blog. Once you'll upload an image, you'll get 3 versions: feed (thumbnail to be included in the post or in the feed= build-in feature from WP), post ( to be included in the post, resize to be lighter, just to fit into your post), standalone (it's a resized backup). If you choose the 'replace_original' parameter, the original file will be replaced by the standalone one, so that your hosting will no longer host too heavy files and it will prevent people from stealing your full resolution original image. I recommand to use also the thumbfeed plugin available at <a href="http://www.alterclickr.com/projects/thumbfeed/">http://www.alterclickr.com/projects/thumbfeed/</a>
Author: Marc Thouvenin contact@marcthouvenin.com
Version: 0.5.1
Author URI: http://www.marcthouvenin.com/
*/ 

/*
Log:
- 0.5.1 Added thumbnail size management
- 0.5 works now well with thumbfeed
- 0.4 refresh the thumbs list after upload


To do:
- manage the too high or too wide images 
- manage magnifying very small images while making thumbnail
*/


//include ('easyphoto_include.php');

// Thumbpress tab selected by default
define ('ezp_default_tab' , true) ;

// Remove Regular Wordpress Upload Tabs
define ('ezp_remove_regular_tabs' , true ) ;

// Parameters for the thumbnail that will be used in feeds
define ('ezp_feed_prefix' , "feed." );
define ('ezp_feed_suffix', "");
define ('ezp_feed_max_size', 256);
define ('ezp_feed_root_path', "");

// Parameters for the thumbnail that will be used in the posts
define ('ezp_post_prefix', "post.");
define ('ezp_post_suffix', "");
define ('ezp_post_max_size', 400);  
define ('ezp_post_root_path', "");

// Parameters for the thumbnail that will be used in the standalone version
define ('ezp_standalone_prefix', "main.");
define ('ezp_standalone_suffix',"");
define ('ezp_standalone_max_size', 1024);
define ('ezp_standalone_root_path', "");

// Parameters to display the standalone version
define ('ezp_standalone_max_width', 1000);
define ('ezp_standalone_max_height', 400);

// Max ratio used for resizing : 
define ('ezp_max_resizing_ratio', 1.5);

// store directory can be different for each user (option not active yet)
define ('ezp_upload_dir_diff_for_users', false);

// upload directory
$ezp_uploadarray = wp_upload_dir();
define ('ezp_upload_dir' , $ezp_uploadarray ['path']."/" );  // You should not modify this value
if ( ezp_upload_dir_diff_for_users ) {
	define ('ezp_upload_dir', "$current_user/");
}

// Overwrite existing files (if a file with same name already exists)
define ('ezp_ovewrite' , false);

// Remove the original full resolution file and replace by the standalone version
define ('ezp_replace_original', true);

define ('ezp_dontmagnify', true);

// thumbnail_creation_size_limit : Above this number of pixels of the original image, no thumbnail will be created
// You can set it to 10 M Pixels or to negative value to disable this buildin wordpress feature
define ('ezp_thumbnail_creation_size_limit', -1 );

add_filter ('wp_thumbnail_creation_size_limit', 'ezp_thumbnail_creation_size_limit',10, 3);

// Attachment page max dims
add_filter ('attachment_max_dims', 'ezp_attachment_max_dims');

// Manage the thumbnail size
add_filter ('wp_thumbnail_max_side_length', 'ezp_thumbnail_max_side_length');
function ezp_thumbnail_max_side_length () {
	return ezp_feed_max_size;
}


function ezp_thumbnail_creation_size_limit($max, $attachment_id, $file){
	return ezp_thumbnail_creation_size_limit;	
}

// Set the max dimension of the attachment in the attachment.php script in your theme
function ezp_attachment_max_dims ($max_dims_array) {
	return array( ezp_standalone_max_width, ezp_standalone_max_height );
	
}

wp_deregister_script( 'upload' );
wp_register_script( 'upload', '/wp-content/plugins/easyphoto/upload-js.php', array('prototype'), '20070218' );

function ezp_thumbnail_filename ($filename) {

	$ezp_overwrite = ezp_overwrite;
	$ezp_upload_dir = ezp_upload_dir;
	$file = ezp_feed_prefix .preg_replace( '!(\.[^.]+)?$!', ezp_feed_suffix. '$1', $filename, 1 );

	if ( ! $ezp_overwrite) {
		$num=0;	
		while ( file_exists( $ezp_upload_dir.$file ) )  {
		$num ++;
		$file = ezp_feed_prefix .preg_replace( '!(\.[^.]+)?$!', "-$num" .ezp_feed_suffix . '$1', $filename, 1 );
		}	
	}
	return $file;
	
}

add_filter ('thumbnail_filename', 'ezp_thumbnail_filename', 10, 3);

function ezp_thumbnail_filename_custom ($filename, $prefix = "", $suffix = ".thumbnail") {

	$file = $prefix.preg_replace( '!(\.[^.]+)?$!', $suffix .'$1', $filename, 1 );
	if ( ! ezp_overwrite ) {
		$num=0;	
		while ( file_exists( ezp_upload_dir. $file ) )  {
		$num ++;
		$file = $prefix.preg_replace( '!(\.[^.]+)?$!', "-$num" .$suffix. '$1', $filename, 1 );
		}	

	}
	return $file;
	
}

/* 	Actually it's almost a copy of the original wp_create_thumbnail function. 
	The only changes are :
	- management of the prefix and the suffix
	- management of the dontmagnify effect which allow to present magnifying an image when it is smaller than the standard size
	*/
function ezp_create_thumbnail( $file, $max_side, $effect = '', $prefix = "", $suffix = ".post"  ) {
		// 1 = GIF, 2 = JPEG, 3 = PNG
	$max_width = $max_side;
	$max_height = ezp_max_resizing_ratio * $max_side;
	
	if ( file_exists( $file ) ) {
		$type = getimagesize( $file );

		// if the associated function doesn't exist - then it's not
		// handle. duh. i hope.
		
		if (!function_exists( 'imagegif' ) && $type[2] == 1 ) {
			$error = __( 'Filetype not supported. Thumbnail not created.' ); 
		}
		elseif (!function_exists( 'imagejpeg' ) && $type[2] == 2 ) {
			$error = __( 'Filetype not supported. Thumbnail not created.' ); 
		}
		elseif (!function_exists( 'imagepng' ) && $type[2] == 3 ) {
			$error = __( 'Filetype not supported. Thumbnail not created.' );
		} else {

			// create the initial copy from the original file
			if ( $type[2] == 1 ) {
				$image = imagecreatefromgif( $file );
			}
			elseif ( $type[2] == 2 ) {
				$image = imagecreatefromjpeg( $file );
			}
			elseif ( $type[2] == 3 ) {
				$image = imagecreatefrompng( $file );
			}

			if ( function_exists( 'imageantialias' ))
				imageantialias( $image, TRUE );

			$image_attr = getimagesize( $file );

			// figure out if the image has to be resized (width & height < max and dontmagnify option set)
			if ( $image_attr[0] < $max_width AND $image_attr[1] < $max_height AND ezp_dontmagnify ) {  
				// New image must not be bigger than original 
					$image_new_width = $image_attr[0];
					$image_new_height = $image_attr[1];
			} else { 
				// figure out the longest side
				if ( $image_attr[0] > $image_attr[1] ) { //width is > height
					$image_width = $image_attr[0];
					$image_height = $image_attr[1];
					$image_new_width = $max_side;

					$image_ratio = $image_width / $image_new_width;
					$image_new_height = $image_height / $image_ratio;
					
				} else { //height > width
					$image_width = $image_attr[0];
					$image_height = $image_attr[1];
			
					$image_new_height = $max_side;

					$image_ratio = $image_height / $image_new_height;
					$image_new_width = $image_width / $image_ratio;
				}
			} 
			$thumbnail = imagecreatetruecolor( $image_new_width, $image_new_height);
			@ imagecopyresampled( $thumbnail, $image, 0, 0, 0, 0, $image_new_width, $image_new_height, $image_attr[0], $image_attr[1] );

			// If no filters change the filename, we'll do a default transformation.
			if ( basename( $file ) == $thumb = ezp_thumbnail_filename_custom ( basename( $file ), $prefix, $suffix ) )
				$thumb = preg_replace( '!(\.[^.]+)?$!', __( '.thumbnail' ).'$1', basename( $file ), 1 );

			$thumbpath = str_replace( basename( $file ), $thumb, $file );

			// move the thumbnail to it's final destination
			if ( $type[2] == 1 ) {
				if (!imagegif( $thumbnail, $thumbpath ) ) {
					$error = __( "Thumbnail path invalid" );
				}
			}
			elseif ( $type[2] == 2 ) {
				if (!imagejpeg( $thumbnail, $thumbpath ) ) {
					$error = __( "Thumbnail path invalid" ); 
				}
			}
			elseif ( $type[2] == 3 ) {
				if (!imagepng( $thumbnail, $thumbpath ) ) {
					$error = __( "Thumbnail path invalid" );
				}
			}

		} 
	} else { 
		$error = __( 'File not found' );
	}

	if (!empty ( $error ) ) {
		return $error;
	} else {
		return $thumbpath;
	}
}

function ezp_generate_attachment_metadata_postsize( $metadata ) {
	$file =	$metadata['file'] ;
	
	$thumb = ezp_create_thumbnail( $file, ezp_post_max_size, '', ezp_post_prefix, ezp_post_suffix );
	$metadata['postsize']=basename($thumb);
	return $metadata;
}

function ezp_generate_attachment_metadata_standalone( $metadata ) {

	$file =	$metadata['file'] ;
	
	$standalone = ezp_create_thumbnail( $file, ezp_standalone_max_size,'', ezp_standalone_prefix, ezp_standalone_suffix );
	if ( ezp_replace_original )
		{   
			unlink ($file);	
			rename ($standalone, $file );
			$metadata['standalone']=basename($file);
		} else {
			$metadata['standalone']=basename($standalone);
		}
	
	
	return $metadata;
}

add_filter ('wp_generate_attachment_metadata', 'ezp_generate_attachment_metadata_postsize');
add_filter ('wp_generate_attachment_metadata', 'ezp_generate_attachment_metadata_standalone');

add_action('load-upload.php', 'ezp_add_upload_tabs');

function ezp_add_upload_tabs() {
    add_filter('wp_upload_tabs','ezp_specific_upload_tabs');
    add_action('upload_files_ezpupload', 'ezp_upload_tab_ezpupload_action');
    add_action('upload_files_ezpbrowse', 'ezp_upload_tab_ezpbrowse_action');
}

function ezp_specific_upload_tabs ($array) {
 /*
     0 => tab display name, 
    1 => required cap, 
    2 => function that produces tab content, 
    3 => total number objects OR array(total, objects per page), 
    4 => add_query_args
*/
    $args = array();
    $ezptabs = array(
        'ezpupload' => array('Easyphoto Upload', 'upload_files', 'ezp_upload_tab_ezpupload', 0, $args),
        'ezpbrowse' => array('Easyphoto Manage', 'upload_files', 'ezp_upload_tab_ezpbrowse', 0, $args)
        );
	
	return array_merge( $array, $ezptabs);
    
}

function ezp_unique_filename( $path, $file ) {
		$number = '';
		
		$wp_filetype = wp_check_filetype( $file, $mimes );
		extract( $wp_filetype );

		$objectname = $_POST['post_title'];
		if ($objectname != "" and strlen ($objectname) > 8 ) {
			$file = $objectname ;
			$filename = str_replace( array('#'," "), '_', $file );
			$filename = str_replace( array( '(',')','\\', "'" ), '', $filename );
		} else {
			$filename = str_replace( array('#'," "), '_', $file );
			$filename = $objectname.str_replace( array( '(',')','\\', "'" ), '', $filename );
		}
		
		if ( empty( $ext) )
			$ext = '';
		else
			$ext = ".$ext";
		while ( file_exists( $path . "/$filename" ) ) {
			if ( '' == "$number$ext" )
				$filename = $filename . ++$number . $ext;
			else
				$filename = str_replace( "$number$ext", ++$number . $ext, $filename );
		}
		$filename = str_replace( $ext, '', $filename );
		$filename = sanitize_title_with_dashes( $filename ) . $ext;
		return $filename;
}

// Function that is called just after the upload tabs when the 'ezpupload' tab is selected
function ezp_upload_tab_ezpupload() {
		$id = get_the_ID();
		global $post_id, $tab, $style;
		$enctype = $id ? '' : ' enctype="multipart/form-data"';
	?>
		<form<?php echo $enctype; ?> id="upload-file" method="post" action="<?php echo get_option('siteurl') . "/wp-admin/upload.php?style=$style&amp;tab=ezpupload&amp;post_id=$post_id"; ?>">
	<?php
		if ( $id ) :
			$attachment = get_post_to_edit( $id );
			$attachment_data = wp_get_attachment_metadata( $id );
	?>
			<div id="file-title">
				<h2><?php if ( !isset($attachment_data['width']) && 'inline' != $style )
						echo "<a href='" . wp_get_attachment_url() . "' title='" . __('Direct link to file') . "'>";
					the_title();
					if ( !isset($attachment_data['width']) && 'inline' != $style )
						echo '</a>';
				?></h2>
				<span><?php
					echo '[&nbsp;';
					echo '<a href="' . get_permalink() . '">' . __('view') . '</a>';
					echo '&nbsp;|&nbsp;';
						echo '<a href="' . attribute_escape(add_query_arg('action', 'view')) . '">' . __('links') . '</a>';
					echo '&nbsp;|&nbsp;';
					echo '<a href="' . attribute_escape(remove_query_arg(array('action','ID'))) . '" title="' . __('Browse your files') . '">' . __('cancel') . '</a>';
					echo '&nbsp;]'; ?></span>
			</div>

		<div id="upload-file-view" class="alignleft">
	<?php		if ( isset($attachment_data['width']) && 'inline' != $style )
				echo "<a href='" . wp_get_attachment_url() . "' title='" . __('Direct link to file') . "'>";
			echo wp_upload_display( array(171, 128) );
			if ( isset($attachment_data['width']) && 'inline' != $style )
				echo '</a>'; ?>
		</div>
	<?php	endif; ?>
			<table><col /><col class="widefat" />
	<?php	if ( $id ): ?>
				<tr>
					<th scope="row"><label for="url"><?php _e('URL'); ?></label></th>
					<td><input type="text" id="url" class="readonly" value="<?php echo wp_get_attachment_url(); ?>" readonly="readonly" /></td>
				</tr>
	<?php	else : ?>
				<tr>
					<th scope="row"><label for="upload"><?php _e('File', 'easyphoto'); ?></label></th>
					<td><input type="file" id="upload" name="image" /></td>
				</tr>
	<?php	endif; ?>
				<tr>
					<th scope="row"><label for="post_title"><?php _e('Title', 'easyphoto'); ?></label></th>
					<td><input type="text" id="post_title" name="post_title" value="<?php echo $attachment->post_title; ?>" /><br><?php _e('Title will be used to rename the file', 'easyphoto'); ?></td>
				</tr>
				<tr>
					<th scope="row"><label for="post_content"><?php _e('Description'); ?></label></th>
					<td><textarea name="post_content" id="post_content"><?php echo $attachment->post_content; ?></textarea></td>
				</tr>
				<tr id="buttons" class="submit">
					<td colspan='2'>
	<?php	if ( $id ) : ?>
						<input type="submit" name="delete" id="delete" class="delete alignleft" value="<?php _e('Delete File'); ?>" />
	<?php	endif; ?>
						<input type="hidden" name="from_tab" value="<?php echo $tab; ?>" />
						<input type="hidden" name="action" value="<?php echo $id ? 'save' : 'upload'; ?>" />
	<?php	if ( $post_id ) : ?>
						<input type="hidden" name="post_id" value="<?php echo $post_id; ?>" />
	<?php	endif; if ( $id ) : ?>
						<input type="hidden" name="ID" value="<?php echo $id; ?>" />
	<?php	endif; ?>
						<?php wp_nonce_field( 'inlineuploading' ); ?>
						<div class="submit">
							<input type="submit" value="<?php $id ? _e('Save') : _e('Upload'); ?> &raquo;" />
						</div>
					</td>
				</tr>
			</table>
		</form>
	<?php
	} 


function prepare_clean_filename ($filename) {
	$clean_filename = $filename;
	$clean_filename = str_replace( array( '"',' ','\\', "'", "/" ), '-', $clean_filename  );
	$clean_filename = str_replace( array( '"',' ','\\', "'", "/" ), '-', $clean_filename  );
	$clean_filename = str_replace( array( 'é','è','ê' ), 'e', $clean_filename  );
	$clean_filename = str_replace( 'à', 'a', $clean_filename  );
	
	return clean_filename;
}

function ezp_upload_tab_ezpupload_action() {
	global $action;

	if ( isset($_POST['delete']) )
		$action = 'delete';

	switch ( $action ) :
	case 'upload' :
		global $from_tab, $post_id, $style;
		if ( !$from_tab )
			$from_tab = 'upload';

		check_admin_referer( 'inlineuploading' );

		global $post_title, $post_content;

		if ( !current_user_can( 'upload_files' ) )
			wp_die( __('You are not allowed to upload files.')
				. " <a href='" . get_option('siteurl') . "/wp-admin/upload.php?style=$style&amp;tab=browse-all&amp;post_id=$post_id'>"
				. __('Browse Files') . '</a>'
			);

		$overrides = array('action'=>'upload', 'unique_filename_callback'=>'ezp_unique_filename', 'objectname'=>'Mon objet');
		$file = wp_handle_upload($_FILES['image'], $overrides);

		if ( isset($file['error']) )
			wp_die($file['error'] . "<br /><a href='" . get_option('siteurl')
			. "/wp-admin/upload.php?style=$style&amp;tab=$from_tab&amp;post_id=$post_id'>" . __('Back to Image Uploading') . '</a>'
		);

		$url = $file['url'];
		$type = $file['type'];
		$file = $file['file'];
		$filename = basename($file);

		// Construct the attachment array
		$attachment = array(
			'post_title' => $post_title ? $post_title : $filename,
			'post_content' => $post_content,
			'post_type' => 'attachment',
			'post_parent' => $post_id,
			'post_mime_type' => $type,
			'guid' => $url
		);
	
		// Save the data
		$id = wp_insert_attachment($attachment, $file, $post_id);
		wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
		
		wp_redirect( get_option('siteurl') . "/wp-admin/upload.php?style=$style&tab=ezpbrowse&action=view&ID=$id&post_id=$post_id");

		die;
		break;

	case 'save' :
		global $from_tab, $post_id, $style;
		if ( !$from_tab )
			$from_tab = 'upload';
		check_admin_referer( 'inlineuploading' );

		wp_update_post($_POST);
		wp_redirect( get_option('siteurl') . "/wp-admin/upload.php?style=$style&tab=$from_tab&post_id=$post_id");
		die;
		break;

	case 'delete' :
		global $ID, $post_id, $from_tab, $style;
		if ( !$from_tab )
			$from_tab = 'upload';

		check_admin_referer( 'inlineuploading' );

		if ( !current_user_can('edit_post', (int) $ID) )
			wp_die( __('You are not allowed to delete this attachment.')
				. " <a href='" . get_option('siteurl') . "/wp-admin/upload.php?style=$style&amp;tab=$from_tab&amp;post_id=$post_id'>"
				. __('Go back') . '</a>'
			);

		wp_delete_attachment($ID);

		wp_redirect( get_option('siteurl') . "/wp-admin/upload.php?style=$style&tab=$from_tab&post_id=$post_id" );
		die;
		break;

	endswitch;

}

function ezp_upload_tab_ezpbrowse() {
	global $wpdb, $action, $paged;
	$old_vars = compact( 'paged' );
	
	switch ( $action ) :
	case 'edit' :
	case 'view' :
		global $ID;
		$attachments = query_posts("attachment_id=$ID");
		if ( have_posts() ) : while ( have_posts() ) : the_post();
			'edit' == $action ? ezp_upload_form() : ezp_upload_view();
		endwhile; endif;
		break;
	default :
		global $tab, $post_id, $style;
		add_action( 'pre_get_posts', 'wp_upload_grab_attachments' );
		if ( 'browse' == $tab && $post_id )
			add_filter( 'posts_where', 'wp_upload_posts_where' );
		$attachments = query_posts("what_to_show=posts&posts_per_page=10&paged=$paged");
		$count_query = "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = 'attachment'";
		if ( $post_id )
			$count_query .= " AND post_parent = '$post_id'";
        	$total =  $wpdb->get_var($count_query);

		echo "<ul id='upload-files'>\n";
		if ( have_posts() ) : while ( have_posts() ) : the_post();
			$href = wp_specialchars( add_query_arg( array(
				'action' => 'inline' == $style ? 'view' : 'edit',
				'ID' => get_the_ID())
			 ), 1 );

			echo "\t<li id='file-";
			the_ID();
			echo "' class='alignleft'>\n";
			echo ezp_upload_display( array(128,128), $href );
			echo "\t</li>\n";
		endwhile;
		else :
			echo "\t<li>" . __('There are no attachments to show.', 'easyphoto') . "</li>\n";
		endif;
		echo "</ul>\n\n";

		echo "<form action='' id='browse-form'><input type='hidden' id='nonce-value' value='" . wp_create_nonce( 'inlineuploading' )  . "' /></form>\n";
		break;
	endswitch;
	global $post_id, $temp_id;
	// If thumbfeed is installed and active, update the thumbs list just after upload
	if (function_exists('tf_update_form_script')){		
		echo tf_update_form_script($post_id, $temp_id);
	}
	extract($old_vars);
}

function ezp_upload_tab_ezpbrowse_action() {
	global $style;
	if ( 'inline' == $style )
		wp_enqueue_script('upload');
}

function ezp_upload_files_ezpupload() {
  echo 'upload_files_ezpupload';
}

function ezp_upload_files_ezpbrowse(){
 	echo 'upload_files_ezpdone';
die;
}

function ezp_upload_display( $dims = false, $href = '' ) {
	global $post;
	$id = get_the_ID();
	$attachment_data = wp_get_attachment_metadata( $id );
	$is_image = (int) wp_attachment_is_image();
	if ( !isset($attachment_data['width']) && $is_image ) {
		if ( $image_data = getimagesize( get_attached_file( $id ) ) ) {
			$attachment_data['width'] = $image_data[0];
			$attachment_data['height'] = $image_data[1];
			wp_update_attachment_metadata( $id, $attachment_data );
		}
	}
	if ( isset($attachment_data['width']) )
		list($width,$height) = wp_shrink_dimensions($attachment_data['width'], $attachment_data['height'], 171, 128);
		
	ob_start();
		the_title();
		$post_title = attribute_escape(ob_get_contents());
	ob_end_clean();
	$post_content = apply_filters( 'content_edit_pre', $post->post_content );
	
	$class = 'text';
	$innerHTML = get_attachment_innerHTML( $id, false, $dims );
	if ( $image_src = get_attachment_icon_src() ) {
		$image_rel = wp_make_link_relative($image_src);
		$innerHTML = '&nbsp;' . str_replace($image_src, $image_rel, $innerHTML);
		$class = 'image';
	}

	$src_base = wp_get_attachment_url();
	$src = wp_make_link_relative( $src_base );
	$src_base = str_replace($src, '', $src_base);
	
/*	$metadata = wp_get_attachment_metadata( $id, true );
	$post_src_base = $metadata['postsize'];
	$post_src = wp_make_link_relative( $post_src_base );
	$post_src_base = str_replace($src, '', $post_src_base);
*/	
	$r = '';

	if ( $href )
		$r .= "<a id='file-link-$id' href='$href' title='$post_title' class='file-link $class'>\n";
	if ( $href || $image_src )
		$r .= "\t\t\t$innerHTML";
	if ( $href )
		$r .= "</a>\n";
	$r .= "\n\t\t<div class='upload-file-data'>\n\t\t\t<p>\n";
	$r .= "\t\t\t\t<input type='hidden' name='attachment-url-$id' id='attachment-url-$id' value='$src' />\n";
	$r .= "\t\t\t\t<input type='hidden' name='attachment-url-base-$id' id='attachment-url-base-$id' value='$src_base' />\n";

	if ( !$thumb_base = wp_get_attachment_thumb_url($id) )
		$thumb_base = wp_mime_type_icon();
	if ( $thumb_base ) {
		$thumb_rel = wp_make_link_relative( $thumb_base );
		$thumb_base = str_replace( $thumb_rel, '', $thumb_base );
		$r .= "\t\t\t\t<input type='hidden' name='attachment-thumb-url-$id' id='attachment-thumb-url-$id' value='$thumb_rel' />\n";
		$r .= "\t\t\t\t<input type='hidden' name='attachment-thumb-url-base-$id' id='attachment-thumb-url-base-$id' value='$thumb_base' />\n";
	}

	if ( !$postsize_base = ezp_get_attachment_url($id, "postsize") )
		$postsize_base = wp_mime_type_icon();
//	if ( $postsize_base ) {
		$postsize_rel = wp_make_link_relative( $postsize_base );
		$postsize_base = str_replace( $postsize_rel, '', $postsize_base );
		$r .= "\t\t\t\t<input type='hidden' name='attachment-postsize-url-$id' id='attachment-postsize-url-$id' value='$postsize_rel' />\n";
		$r .= "\t\t\t\t<input type='hidden' name='attachment-postsize-url-base-$id' id='attachment-postsize-url-base-$id' value='$postsize_base' />\n";
//	}

	$r .= "\t\t\t\t<input type='hidden' name='attachment-is-image-$id' id='attachment-is-image-$id' value='$is_image' />\n";

	if ( isset($width) ) {
		$r .= "\t\t\t\t<input type='hidden' name='attachment-width-$id' id='attachment-width-$id' value='$width' />\n";
		$r .= "\t\t\t\t<input type='hidden' name='attachment-height-$id' id='attachment-height-$id' value='$height' />\n";
	}
	$r .= "\t\t\t\t<input type='hidden' name='attachment-page-url-$id' id='attachment-page-url-$id' value='" . get_attachment_link( $id ) . "' />\n";
	$r .= "\t\t\t\t<input type='hidden' name='attachment-title-$id' id='attachment-title-$id' value='$post_title' />\n";
	$r .= "\t\t\t\t<input type='hidden' name='attachment-description-$id' id='attachment-description-$id' value='$post_content' />\n";
	$r .= "\t\t\t</p>\n\t\t</div>\n";
	return $r;
}

function ezp_upload_view() {
	global $style, $post_id, $style;
	$id = get_the_ID();
	$attachment_data = wp_get_attachment_metadata( $id );
?>
	<div id="upload-file">
		<div id="file-title">
			<h2><?php if ( !isset($attachment_data['width']) && 'inline' != $style )
					echo "<a href='" . wp_get_attachment_url() . "' title='" . __('Direct link to file', 'easyphoto') . "'>";
				the_title();
				if ( !isset($attachment_data['width']) && 'inline' != $style )
					echo '</a>';
			?></h2>
			<span><?php
				echo '[&nbsp;';
				echo '<a href="' . get_permalink() . '">' . __('view') . '</a>';
				echo '&nbsp;|&nbsp;';
					echo '<a href="' . attribute_escape(add_query_arg('action', 'edit')) . '" title="' . __('Edit this file') . '">' . __('edit') . '</a>';
				echo '&nbsp;|&nbsp;';
				echo '<a href="' . attribute_escape(remove_query_arg(array('action', 'ID'))) . '" title="' . __('Browse your files') . '">' . __('cancel') . '</a>';
				echo '&nbsp;]'; ?></span>
		</div>

		<div id="upload-file-view" class="alignleft">
<?php		if ( isset($attachment_data['width']) && 'inline' != $style )
			echo "<a href='" . wp_get_attachment_url() . "' title='" . __('Direct link to file') . "'>";
		echo ezp_upload_display( array(171, 128) );
		if ( isset($attachment_data['width']) && 'inline' != $style )
			echo '</a>'; ?>
		</div>
		<?php the_attachment_links( $id ); ?>
	</div>
<?php	echo "<form action='' id='browse-form'><input type='hidden' id='nonce-value' value='" . wp_create_nonce( 'inlineuploading' )  . "' /></form>\n";
}

function ezp_get_attachment_url( $post_id = 0, $size = 'postsize' ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;
	if ( !$url = wp_get_attachment_url( $post->ID ) )
		return false;

	if ( !$thumb = ezp_get_attachment_file( $post->ID , $size) )
		return false;

	$url = str_replace(basename($url), basename($thumb), $url);

	return $url;
}



function ezp_get_attachment_file( $post_id = 0, $size = 'postsize' ) {
	$post_id = (int) $post_id;
	if ( !$post =& get_post( $post_id ) )
		return false;
	if ( !$imagedata = wp_get_attachment_metadata( $post->ID ) )
		return false;

	$file = get_attached_file( $post->ID );
	
	if ( !empty($imagedata[$size]) && ($attachment_file = str_replace(basename($file), $imagedata[$size], $file)) && file_exists($attachment_file) )
		return  $attachment_file;
	return false;
}

function ezp_uploading_iframe_src($uploading_iframe_src) {
	global $post_ID, $temp_ID;
	$uploading_iframe_ID = (0 == $post_ID ? $temp_ID : $post_ID);
	$uploading_iframe_src = wp_nonce_url("upload.php?style=inline&amp;tab=ezpupload&amp;post_id=$uploading_iframe_ID", 'inlineuploading');
	return $uploading_iframe_src;

}

if (ezp_default_tab) {
	add_filter( 'uploading_iframe_src', 'ezp_uploading_iframe_src') ;
} 




?>