<?php 
namespace ActiveRecord\Cache;


use ActiveRecord\Exceptions\CacheException;
use ActiveRecord\Utility\File as FileUtility;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

class File implements CacheInterface {
	
	/**
	 * Clear a cache
	 * @param string $name
	 * @param string $setting
	 */
	public function clear($name = null) {
		if ($name) {
			$filepath = $this->fullPath($name);
			if (file_exists($filepath)) {
				@unlink($filepath);
			}
			
			return;
		} else {
			$this->flush();	
		}
	}
	
	/**
	 * Clear entire cache for path
	 * @param string $path
	 */
	public function flush() {
		$path = $this->path();
		foreach (glob($path . DS . "*") as $filename) {
			@unlink($filename);
		}
	} 
	
	/**
	 * Read from cache
	 * @param string $name
	 * @param string $setting (optional)
	 * @return mixed
	 */
	public function read($name, $setting = null) {
		$data	= @file_get_contents($this->fullPath($name));
		if (!$data) return false;
		
		return @unserialize(base64_decode($data));
	}
	
	/**
	 * Write to cache
	 * @param string $name
	 * @param mixed $data
	 * @param string $setting (optional)
	 * @return object $this
	 */
	public function write($name, $data) {
		$fullPath = $this->fullPath($name);
		$parts = pathinfo($fullPath);

		if (!file_exists($parts['dirname']))
			FileUtility::mkdir_p($parts['dirname']);

		file_put_contents($fullPath, base64_encode(serialize($data)));
		return $this;
	}
	
	/**
	 * Getter for path
	 * @param string $path
	 * @return string
	 */
	public function path() {	
		return TMP_PATH . DS . 'cache';
	}
	
	/**
	 * Get the full path
	 * @param string $name
	 * @param string $setting
	 * @return string
	 */
	protected function fullPath($name) {
		return $this->path() . DS . $name;
	}
	
}
?>