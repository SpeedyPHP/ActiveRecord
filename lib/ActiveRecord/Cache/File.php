<?php 
namespace ActiveRecord\Cache;


use ActiveRecord\Exceptions\CacheException;
use ActiveRecord\Utility\File as FileUtility;
use \SplFileObject;
use \SplFileInfo;

defined('DS') or define('DS', DIRECTORY_SEPARATOR);

class File implements CacheInterface {

	/**
	 * Holder for files objects
	 * @var array $_files
	 */
	private $_files = [];

	private $_isWindows = false;


	public function __construct() {
		if (DIRECTORY_SEPARATOR === '\\') 
			$this->_isWindows = true;
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
		$file = $this->file($name);
		if (!isset($file))
			return null;

		$file->rewind();
		$time = time();
		$cachedtime = intval($file->current());
		// TODO: implement functions that auto expire files

		$file->next();
		$data = '';
		while ($file->valid()) {
			$data .= $file->current();
			$file->next();
		}

		//$data	= @file_get_contents($this->fullPath($name));
		if (empty($data)) return null;

		$ret = $this->unserializeData($data);
		return $ret;
		//return null;
	}
	
	/**
	 * Write to cache
	 * @param string $name
	 * @param mixed $data
	 * @return boolean
	 */
	public function write($name, $data, $duration = 0) {
		$lineBreak	= "\n";
		if ($this->isWindows())
			$lineBreak = "\r\n";

		$expires = time() + $duration;
		$content = $expires . $lineBreak . $this->serializeData($data) . $lineBreak;

		$file = $this->file($name);
		$file->rewind();

		$success = $file->ftruncate(0) && $file->fwrite($content) && $file->fflush();

		return $success;
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

	/**
	 * Get the file object
	 * @param string $name
	 * @return SplFileObject
	 */
	public function file($name) {
		if (isset($this->_files[$name])) 
			return $this->_files[$name];

		$filePath 	= $this->fullPath($name);
		$pathInfo	= pathinfo($filePath);

		if (!file_exists($pathInfo['dirname'])) {
			FileUtility::mkdir_p($pathInfo['dirname']);
			return null;
		}	

		if (!file_exists($filePath))
			touch($filePath);

		$file	= new SplFileObject($filePath, 'r+');
		$this->_files[$name] = $file;

		return $this->_files[$name];
	}

	/**
	 * Getter for _isWindows property
	 * @return boolean $this->_isWindows;
	 */
	public function isWindows() {
		return $this->_isWindows;
	}

	/**
	 * Serialize function
	 * @return string
	 */
	public function serializeData($data) {
		if ($this->isWindows()) {
			$data = base64_encode(str_replace('\\', '\\\\\\\\', serialize($data)));
		} else {
			$data = base64_encode(serialize($data));
		}

		return $data;
	}

	/**
	 * Unserialize data
	 * @param string $data
	 * @return mixed
	 */
	public function unserializeData($data) {
		$data = base64_decode($data);

		if ($this->isWindows()) {
			$data = str_replace('\\\\\\\\', '\\', $data);
		}

		return unserialize($data);
	}
	
}
?>