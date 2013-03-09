<?php
namespace ActiveRecord;


use Closure;
use ActiveRecord\Inflector;

/**
 * Cache::get('the-cache-key', function() {
 *	 # this gets executed when cache is stale
 *	 return "your cacheable datas";
 * });
 */
class Cache
{
	static $adapter = null;
	static $options = array();

	/**
	 * Initializes the cache.
	 *
	 * With the $options array it's possible to define:
	 * - expiration of the key, (time in seconds)
	 * - a namespace for the key
	 *
	 * this last one is useful in the case two applications use
	 * a shared key/store (for instance a shared Memcached db)
	 *
	 * Ex:
	 * $cfg_ar = ActiveRecord\Config::instance();
	 * $cfg_ar->set_cache('memcache://localhost:11211',array('namespace' => 'my_cool_app',
	 *																											 'expire'		 => 120
	 *																											 ));
	 *
	 * In the example above all the keys expire after 120 seconds, and the
	 * all get a postfix 'my_cool_app'.
	 *
	 * (Note: expiring needs to be implemented in your cache store.)
	 *
	 * @param string $url URL to your cache server
	 * @param array $options Specify additional options
	 */
	public static function initialize()
	{
 		$args	= func_get_args();
 		if (empty($args)) {
 			static::$adapter = null;
 			return;
 		}
 		
		$class	= array_shift($args);
		if (class_exists($class)) {
			static::$adapter = new $class($args);
		} elseif ($url) {
			$url = parse_url($url);
			static::$adapter = new ActiveRecord\Cache\Memcache($url, $args);
		} 

		
		if (isset($args[0]) && is_array($args[0])) $options = array_shift($args);
		else $options = [];
		
		static::$options = array_merge(array('expire' => 30, 'namespace' => ''),$options);
	}

	public static function flush()
	{
		if (static::$adapter)
			static::$adapter->flush();
	}

	public static function get($key, $closure)
	{
		$key = self::get_namespace() . $key;
		
		if (!self::$adapter)
			return $closure();

		\Speedy\Logger::debug($key);
		$value = self::$adapter->read($key);
		\Speedy\Logger::debug($value);
		\Speedy\Logger::debug(isset($value));
		if (!isset($value)) {
			$clean_name = str_replace('`', '', $key);
			self::$adapter->write($clean_name,($value = $closure()),self::$options['expire']);
		}
		\Speedy\Logger::debug($value);

		return $value;
	}

	private static function get_namespace()
	{
		return (isset(static::$options['namespace']) && strlen(static::$options['namespace']) > 0) ? (static::$options['namespace'] . "::") : "";
	}
}
?>
