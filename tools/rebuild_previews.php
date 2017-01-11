#!/usr/bin/php
<?php

require dirname(__FILE__) . '/inc/cli.php';
require_once 'inc/image.php';

// parse command line
$opts          = getopt('b:p:', Array('board:', 'post:', 'force'));
$options       = [];

$options['board'] = isset($opts['board']) ? $opts['board'] : (isset($opts['b']) ? $opts['b'] : false);
$options['post'] = isset($opts['post']) ? $opts['post'] : (isset($opts['p']) ? $opts['p'] : false);
$options['force'] = isset($opts['force']);

$boards = [];
if($options['board']) {
	if($options['board'] == 'all') {
		$boards = listBoards();
	}
	else {
		$boards = explode(',', $options['board']);
	}
}

if(!count($boards)) {
	die("Usage:\n{$argv[0]} --board b --post thread_id or post_id\n");
}

foreach ($boards as $board) {
	echo "/{$board}/...", PHP_EOL;
	
	if(!openBoard($board)) {
		echo 'error opening board', PHP_EOL;
		continue;
	}

	Vi::$config['mask_db_error'] = false;
	
	$post = $options['post'] ? " AND (`thread`= '" . $options['post'] . "' OR `id`='" .$options['post']. "')" : NULL;
	$query = query(sprintf("SELECT * FROM ``posts_%s`` WHERE `files` IS NOT NULL%s", Vi::$board['uri'], $post)) or die(db_error());
	
	while ($post = $query->fetch(PDO::FETCH_OBJ)) {
		$post->files = json_decode($post->files);
		$post->has_file = $post->num_files > 0;
		$post->op = !$post->thread;
		$webm = false;
		$update = false;
		foreach($post->files as &$file) {
			switch($file->extension) {
				case 'webm':
				case 'mp4':
					if($file->file == 'deleted' || in_array($file->thumb, array('spoiler', 'deleted', 'file'))) {
						break;
					}

					$file->thumb = $file->file_id . '.' . Vi::$config['thumb_ext'];
					$file->thumb_path = Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $file->thumb;
					$file->file_path = Vi::$board['dir'] . Vi::$config['dir']['img'] . $file->file_id . '.' . $file->extension;

					if(file_exists($file->thumb_path)) {
						if(!$options['force']) {
							break;
						}
						file_unlink(Vi::$config['dir']['img_root'] . $file->thumb_path);
					}
					$update = true;
					$webm = true;
					echo $post->id, ' ', $file->file_path, ' ', $file->thumb_path, PHP_EOL;
					break;

				case 'png':
				case 'gif':
				case 'jpg':
				case 'jpeg':
					if($file->file == 'deleted' || in_array($file->thumb, array('spoiler', 'deleted', 'file'))) {
						break;
					}

					$ext = (Vi::$config['thumb_ext'] ? Vi::$config['thumb_ext'] : $file['extension']);

					if($file->extension == 'png') {
						$ext = $file->extension;
					}
					else if($file->extension == 'gif' && Vi::$config['gif_preview_animate']) {
						$ext = $file->extension;
					}

					$file->thumb = $file->file_id . '.' . $ext;
					$file->thumb_path = Vi::$board['dir'] . Vi::$config['dir']['thumb'] . $file->thumb;
					$file->file_path = Vi::$board['dir'] . Vi::$config['dir']['img'] . $file->file_id . '.' . $file->extension;

					if(file_exists($file->thumb_path) && !$options['force']) {
						break;
					}

					$size = @getimagesize($file->file_path);

					if(!$size) {
						echo 'Wrong getimagesize: ' . $post->thread, ' ' . $post->id, ' ', $file->file_path, ' ', $file->thumb_path, PHP_EOL;
						break;
					}
					
					$image = new Image($file->file_path, $file->extension, $size);
					$thumb = $image->resize(
						$ext,
						$post->op ? Vi::$config['thumb_op_width'] : Vi::$config['thumb_width'],
						$post->op ? Vi::$config['thumb_op_height'] : Vi::$config['thumb_height']
					);

					$thumb->to($file->thumb_path);

					$file->thumbwidth = $thumb->width;
					$file->thumbheight = $thumb->height;
					$file->width = $size[0];
					$file->height = $size[0];

					$thumb->_destroy();

					echo $post->id, ' ', $file->file_path, ' ', $file->thumb_path, PHP_EOL;

					$update = true;
					break;
			}
		}

		if($update) {
			if($webm) {
				postHandler($post);
			}
			$q = prepare("UPDATE  `posts_" . Vi::$board['uri'] . "` SET `files`=:files WHERE `id`=:id");
			$q->bindValue(':files', json_encode($post->files));
			$q->bindValue(':id', $post->id);
			$q->execute() or error(db_error($q));
			if($post->op) {
				buildThread($post->id);
			}
		}
	}

	buildIndex();

	echo "end", PHP_EOL;
}
