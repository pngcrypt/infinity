<?php
class Lock {
	public function __construct($key) {
		if (Vi::$config['lock']['enabled'] == 'fs') {
			$key = str_replace('/', '::', $key);
			$key = str_replace("\0", '', $key);

			$this->f = fopen("tmp/locks/$key", "w");
		}
	}

	// Get a shared lock
	public function get($nonblock = false) {
		if (Vi::$config['lock']['enabled'] == 'fs') {
			$wouldblock = false;
			flock($this->f, LOCK_SH | ($nonblock ? LOCK_NB : 0), $wouldblock);
			if ($nonblock && $wouldblock) {
				return false;
			}

		}
		return $this;
	}

	// Get an exclusive lock
	public function get_ex($nonblock = false) {
		if (Vi::$config['lock']['enabled'] == 'fs') {
			$wouldblock = false;
			flock($this->f, LOCK_EX | ($nonblock ? LOCK_NB : 0), $wouldblock);
			if ($nonblock && $wouldblock) {
				return false;
			}

		}
		return $this;
	}

	// Free a lock
	public function free() {
		if (Vi::$config['lock']['enabled'] == 'fs') {
			flock($this->f, LOCK_UN);
		}
		return $this;
	}
}
