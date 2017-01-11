This directory contains files being the integration of containerchan with vichan-devel imageboards.

Containerchan allows posting of webm files, like they were the regular images.

An original board using this code can be found at:
http://containerchan.org/tb/demo/

The original repo containing the containerchan (possibly with no Tinyboard integration) can be found here:
https://github.com/ccd0/containerchan


Be aware that this is beta software.  Please report any bugs you find.

Installation
------------

Add these lines to inc/instance-config.php:

    Vi::$config['allowed_ext_files'][] = 'webm';
    Vi::$config['additional_javascript'][] = 'js/webm-settings.js';
    Vi::$config['additional_javascript'][] = 'js/expand-video.js';

If you have an [FFmpeg](https://www.ffmpeg.org/) binary on your server and you wish to generate real thumbnails (the webm thumbnails created with the original implementation reportedly cause users' browsers to crash), add the following to inc/instance-config.php as well:

    Vi::$config['webm']['use_ffmpeg'] = true;

    // If your ffmpeg binary isn't in your path you need to set these options
    // as well.

    Vi::$config['webm']['ffmpeg_path'] = '/path/to/ffmeg';
    Vi::$config['webm']['ffprobe_path'] = '/path/to/ffprobe';

License
-------

See [LICENSE.md](https://github.com/ccd0/containerchan/blob/master/LICENSE.md).
