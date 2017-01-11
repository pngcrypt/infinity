<?php
/*
 * ffmpeg.php
 * A barebones ffmpeg based webm implementation for vichan.
 */

function get_webm_info($filename) {
	$filename    = escapeshellarg($filename);
	$ffprobe     = Vi::$config['webm']['ffprobe_path'];
	$ffprobe_out = array();
	$webminfo    = array();

	exec("$ffprobe -v quiet -print_format json -show_format -show_streams $filename", $ffprobe_out);
	$ffprobe_out       = json_decode(implode("\n", $ffprobe_out), 1);
	$webminfo['error'] = is_valid_webm($ffprobe_out);

	if (empty($webminfo['error'])) {
		$webminfo['width']    = $ffprobe_out['_width'];
		$webminfo['height']   = $ffprobe_out['_height'];
		$webminfo['duration'] = $ffprobe_out['format']['duration'];
	}

	return $webminfo;
}

function is_valid_webm(&$ffprobe_out) {
	if (empty($ffprobe_out)) {
		return array('code' => 1, 'msg' => Vi::$config['error']['genwebmerror']);
	}

	if ((count($ffprobe_out['streams']) > 1) && (!Vi::$config['webm']['allow_audio'])) {
		return array('code' => 3, 'msg' => Vi::$config['error']['webmhasaudio']);
	}

	$streams = array();
	foreach($ffprobe_out['streams'] as $stream) {
		if($stream['codec_type'] == 'video') {
			$streams['video'] = $stream;
		}
		else if($stream['codec_type'] == 'audio') {
			$streams['audio'] = $stream;
		}
	}

	$extension = pathinfo($ffprobe_out['format']['filename'], PATHINFO_EXTENSION);

	if ($extension === 'webm') {
		if ($ffprobe_out['format']['format_name'] != 'matroska,webm') {
			return array('code' => 2, 'msg' => Vi::$config['error']['invalidwebm']);
		}
	}
	elseif ($extension === 'mp4') {
		if (!isset($streams['video']) || $streams['video']['codec_name'] != 'h264') {
			return array('code' => 2, 'msg' => Vi::$config['error']['invalidwebm']);
		}
	}
	else {
		return array('code' => 1, 'msg' => Vi::$config['error']['genwebmerror']);
	}

	if (!isset($streams['video']) || empty($streams['video']['width']) || empty($streams['video']['height'])) {
		return array('code' => 2, 'msg' => Vi::$config['error']['invalidwebm']);
	}

	if ($ffprobe_out['format']['duration'] > Vi::$config['webm']['max_length']) {
		return array('code' => 4, 'msg' => sprintf(Vi::$config['error']['webmtoolong'], Vi::$config['webm']['max_length']));
	}

	$ffprobe_out['_width'] = $streams['video']['width'];
	$ffprobe_out['_height'] = $streams['video']['height'];
}

function make_webm_thumbnail($filename, $thumbnail, $width, $height, $duration) {
	$filename    = escapeshellarg($filename);
	$thumbnailfc = escapeshellarg($thumbnail); // Should be safe by default but you can never be too safe.
	$ffmpeg      = Vi::$config['webm']['ffmpeg_path'];

	$ret               = 0;
	$ffmpeg_out        = array();
	$preview_frame_sec = Vi::$config['webm']['preview_frame_sec'] == 'middle' ? floor($duration / 2) : Vi::$config['webm']['preview_frame_sec'];
	exec("$ffmpeg -strict -2 -ss " . $preview_frame_sec . " -i $filename -v quiet -an -vframes 1 -f mjpeg -vf scale=$width:$height $thumbnailfc 2>&1", $ffmpeg_out, $ret);
	// Work around for https://trac.ffmpeg.org/ticket/4362
	if (filesize($thumbnail) === 0) {
		// try again with first frame
		exec("$ffmpeg -y -strict -2 -t 0 -i $filename -v quiet -an -vframes 1 -f mjpeg -vf scale=$width:$height $thumbnailfc 2>&1", $ffmpeg_out, $ret);
		clearstatcache();
		// failed if no thumbnail size even if ret code 0, ffmpeg is buggy
		if (filesize($thumbnail) === 0) {
			$ret = 1;
		}
	}
	return $ret;
}
