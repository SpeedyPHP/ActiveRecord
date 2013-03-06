<?php 
namespace ActiveRecord\Cache;


use ActiveRecord\Exceptions\CacheException;
use ActiveRecord\Utility\File as FileUtility;

class File implements CacheInterface {

	const PathDefault	= 'default';
	
	public $path= array();
	
	
	
	public function __construct() {
		//$this->addPath('default', TMP_PATH . DS . 'cache');
	}
	
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
	 * @return mixed
	 */
	public function read($name) {
		$data	= @file_get_contents($this->fullPath($name));
		if (!$data) return null;

		return @unserialize(base64_decode($data));
	}
	
	/**
	 * Write to cache
	 * @param string $name
	 * @param mixed $data
	 * @return object $this
	 */
	public function write($name, $data, $expire = null) {
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
		$path = TMP_PATH . DS . 'cache';

		if (!file_exists($path)) {
			FileUtility::mkdir_p($path, 0755);
		}
		
		return $path;
	}
	
	/**
	 * Checks if a path exists
	 * @param string $path
	 * @return boolean
	 */
	public function hasPath($path) {
		return isset($this->path[$path]);
	}
	
	/**
	 * Add a path
	 * @param string $name
	 * @param string $path
	 * @return \Speedy\Cache
	 */
	public function addPath($name, $path) {
		$this->path[$name]	= $path;
		return $this;
	}
	
	/**
	 * Get the full path
	 * @param string $name
	 * @param string $setting
	 * @return string
	 */
	protected function fullPath($name) {
		return TMP_PATH . DS . 'cache' . DS . $name;
	}
	
}
?>