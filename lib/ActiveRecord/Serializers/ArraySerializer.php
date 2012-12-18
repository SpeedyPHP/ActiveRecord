<?php 
namespace ActiveRecord\Serializers;


use ActiveRecord\Serializers\Serializer;

/**
 * Array serializer.
 *
 * @package ActiveRecord
 */
class ArraySerializer extends Serializer
{
	public static $include_root = false;

	public function to_s()
	{
		return self::$include_root ? array(strtolower(get_class($this->model)) => $this->to_a()) : $this->to_a();
	}
}
?>