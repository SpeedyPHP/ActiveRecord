<?php 
namespace ActiveRecord;

class Collection extends \ArrayObject {
	
	public function each($closure) {
		foreach ($this as &$value) {
			$closure($value);
		}
		return;
	}
	
	public function each_key($closure) {
		foreach ($this as $key => &$value) {
			$closure($key, $value);
		}
		return;
	}
	
	public function first() {
		return $this[0];
	}
	
	public function length() {
		return $this->count();
	}
	
	public function prepend($value){
		$tmp= $this->getArrayCopy();
		array_unshift($tmp, $value);
		$this->exchangeArray($tmp);
		return $this;
	}
	
}
?>
