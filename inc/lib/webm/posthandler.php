<?php
// Glue code for handling a Tinyboard post.
// Portions of this file are derived from Tinyboard code.

function postHandler($post) {
	if (!$post->has_file) {
		return;
	}

	foreach ($post->files as &$file) {
		if ($file->extension !== 'webm' && $file->extension !== 'mp4') {
			continue;
		}

		if (Vi::$config['webm']['use_ffmpeg']) {
			require_once dirname(__FILE__) . '/ffmpeg.php';
			$webminfo = get_webm_info($file->file_path);

			if (!empty($webminfo['error'])) {
				return $webminfo['error']['msg'];
			}

			$file->width  = $webminfo['width'];
			$file->height = $webminfo['height'];

			if (Vi::$config['spoiler_images'] && isset($_POST['spoiler'])) {
				$file = webm_set_spoiler($file);
			} else {
				$file    = set_thumbnail_dimensions($post, $file);
				$tn_path = Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $file->file_id . '.jpg';
				
				if(file_exists($tn_path)) {
					continue;
				}

				if (0 == make_webm_thumbnail($file->file_path, $tn_path, $file->thumbwidth, $file->thumbheight, $webminfo['duration'])) {
					$file->thumb = $file->file_id . '.jpg';
					require_once 'inc/image.php';
					$image = new Image(getcwd() . DIRECTORY_SEPARATOR . $tn_path, 'jpg', @getimagesize($tn_path));
					$image->to($tn_path);
					$image->destroy();
				} else {
					$file->thumb = 'file';
				}
			}
		} else {
			require_once dirname(__FILE__) . '/videodata.php';
			$videoDetails = videoData($file->file_path);
			if (!isset($videoDetails['container']) || $videoDetails['container'] != 'webm') {
				return _("not a WebM file");
			}

			// Set thumbnail
			$thumbName = Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $file->file_id . '.webm';
			if (Vi::$config['spoiler_images'] && isset($_POST['spoiler'])) {
				// Use spoiler thumbnail
				$file = webm_set_spoiler($file);
			} elseif (isset($videoDetails['frame']) && $thumbFile = fopen($thumbName, 'wb')) {
				// Use single frame from video as pseudo-thumbnail
				fwrite($thumbFile, $videoDetails['frame']);
				fclose($thumbFile);
				$file->thumb = $file->file_id . '.webm';
			} else {
				// Fall back to file thumbnail
				$file->thumb = 'file';
			}
			unset($videoDetails['frame']);

			// Set width and height
			if (isset($videoDetails['width']) && isset($videoDetails['height'])) {
				$file->width  = $videoDetails['width'];
				$file->height = $videoDetails['height'];

				if ($file->thumb != 'file' && $file->thumb != 'spoiler') {
					$file = set_thumbnail_dimensions($post, $file);
				}
			}
		}
	}
}

function set_thumbnail_dimensions($post, $file) {
	$tn_dimensions = array();
	$tn_maxw       = $post->op ? Vi::$config['thumb_op_width'] : Vi::$config['thumb_width'];
	$tn_maxh       = $post->op ? Vi::$config['thumb_op_height'] : Vi::$config['thumb_height'];

	if ($file->width > $tn_maxw || $file->height > $tn_maxh) {
		$file->thumbwidth  = min($tn_maxw, intval(round($file->width * $tn_maxh / $file->height)));
		$file->thumbheight = min($tn_maxh, intval(round($file->height * $tn_maxw / $file->width)));
	} else {
		$file->thumbwidth  = $file->width;
		$file->thumbheight = $file->height;
	}

	return $file;
}

function webm_set_spoiler($file) {
	$file->thumb       = 'spoiler';
	$size              = @getimagesize(Vi::$config['spoiler_image']);
	$file->thumbwidth  = $size[0];
	$file->thumbheight = $size[1];

	return $file;
}
