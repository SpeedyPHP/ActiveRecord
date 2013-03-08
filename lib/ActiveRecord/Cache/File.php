<?php 
namespace ActiveRecord\Cache;


use ActiveRecord\Exceptions\CacheException;
use ActiveRecord\Utility\File as FileUtility;
use \SplFileObject;

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
	 * @return mixed
	 */
	public function read($name) {
		\Speedy\Logger::debug("Read: $name");
		$filePath = $this->fullPath($name);
		if (!file_exists($filePath))
			return null;

		$data = '';
		$file = new SplFileObject($filePath);
		while(!$file->eof()) {
			$data .= $file->current();
			$file->next();
		}
		unset($file);

		//$data	= @file_get_contents($this->fullPath($name));
		if (empty($data)) return null;

		//return @unserialize(base64_decode($data));*/
		return null;
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