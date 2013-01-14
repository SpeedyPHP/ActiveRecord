<?php 
/**
 * @package ActiveRecord
 */
namespace ActiveRecord;


use ActiveRecord\Exceptions\Exception as ActiveRecordException;
use ActiveRecord\Exceptions\MigrationException;

abstract class Migration {
	
	/**
	 * Default name for id
	 * @const string
	 */
	const ID	= 'id';

	/**
	 * Migration up flag
	 * @const int
	 */
	const UP	= 1;

	/**
	 * Migration down flag
	 * @const int
	 */
	const DOWN	= 2;
	
	/**
	 * Current AR Connection
	 * @var ActiveRecord\Connection
	 */
	private $connection;

	/**
	 * Current sql string
	 * @var string
	 */
	private $sql;
	
	/**
	 * Sql representation of columns
	 * @var string
	 */
	private $columns;

	/**
	 * Flag for determining where an id column is needed
	 * @var boolean
	 */
	private $id = false;

	/**
	 * Version string of current migration
	 * @var string
	 */
	private $version;

	/**
	 * SchemaMigration record for this migration
	 * @var ActiveRecord\SchemaMigration
	 */
	private $record;

	/**
	 * Flag to determine if migration has already been run
	 * @var boolean
	 */
	private $migrated = null;

	/**
	 * Direction of the migration
	 * @var int
	 */
	private $direction	= null;

	/**
	 * Log of statements executed
	 * @var array
	 */
	private $log	= array();

	/**
	 * Column build defaults
	 * @var array
	 */
	private $columnDefaults = [
		'null' => true
	];
	
	
	
	public function __construct($connection) 
	{
		if (!$connection)
			throw new ActiveRecordException('A valid database connection is required.');
		
		$reflection	= new \ReflectionClass($this);
		$path	= pathinfo($reflection->getFileName());
		$filename	= $path['filename'];
		$filenameArr= explode('_', $filename);
		$version	= $filenameArr[0];
		$this->connection	= $connection;
		$this->set_version($version);
		
		if ($this->migrated()) 
			$this->direction = self::DOWN;
		else 
			$this->direction = self::UP;
	}
	
	public function change() {}
	
	/**
	 * Getter for record
	 */
	public function record() {	
		return $this->record;
	}
	
	/**
	 * Record setter
	 * @param \ActiveRecord\Model $record
	 * @return \ActiveRecord\Migration
	 */
	private function set_record($record) {
		$this->record	= $record;
		return $this;
	}
	
	public function log() {
		return $this->log;
	}
	
	private function pushLog($msg) {
		$this->log[]	= $msg;
		return $this;
	}
	
	/**
	 * Test if current migration has been migrated already
	 * @throws MigrationException
	 * @return boolean
	 */
	public function migrated() {
		if ($this->migrated === null) {
			$this->set_record(SchemaMigration::find_by_version($this->version()));
		
			if ($this->record()) 
				$this->migrated	= true;
			else
				$this->migrated = false;
		}

		return $this->migrated;
	}
	
	/**
	 * Setter for version
	 * @param integer $version
	 */
	private function set_version($version) {
		$this->version	= $version;
		return $this;
	}
	
	/**
	 * Version getter
	 * @return integer
	 */
	public function version() {
		return $this->version;
	}
	
	public function query($sql) {
		return $this->connection->query($sql);
	}
	
	public function create_table($name, $closure)
	{
		if ($this->direction() === self::UP) {
			$this->query($this->build_create_table($name, $closure));
			$this->pushLog("Created table $name {$this->connection->get_execution_time()} ms");
			return;
		} elseif ($this->direction() === self::DOWN) {
			$this->query($this->build_drop_table($name));
			$this->pushLog("Dropped table $name {$this->connection->get_execution_time()} ms");
			return;
		}
		
		throw new MigrationException('Unknown error occured while creating table ' . $name);
	}
	
	public function drop_table($name, $closure = null) 
	{
		if ($this->direction() === self::UP) {
			$this->query($this->build_drop_table($name));
			$this->pushLog("Dropped table $name {$this->connection->get_execution_time()} ms");
		} elseif ($this->direction() === self::DOWN && isset($closure)) {
			$this->query($this->build_create_table($name, $closure));
			$this->pushLog("Recreated table $name {$this->connection->get_execution_time()} ms");
		}
		
		return;
	}
	
	public function add_column($table_name, $column, $type, $length = null, $null = true)
	{
		if ($this->direction() === self::UP) {
			$this->query($this->build_add_column($table_name, $column, $type, $length, $null));
			$this->pushLog("Added column $column to $table_name {$this->connection->get_execution_time()} ms");
			return;
		} elseif ($this->direction() === self::DOWN) {
			$this->query($this->build_drop_column($table_name, $column));
			$this->pushLog("Droppped column $column from $table_name {$this->connection->get_execution_time()}");
			return;
		}
		
		throw new MigrationException('Unable to determine direction in current migration');
	}
	
	public function set_direction($direction) {
		if ($direction != self::UP && $direction != self::DOWN) {
			throw new MigrationException('Unknown value for direction');
		}
		
		return $this;
	}
	
	public function migrate() {
		// try {
		$this->change();
		// } catch (\Exception $e) {
		// 	echo get_class($e) . "\n";
		// }
		
		return;
	}
	
	public function runUp($logMigration = true) {
		$this->set_direction(self::UP)->change();
		$this->up();
		
		if ($logMigration) {
			$record	= new SchemaMigration(array('version' => $this->version()));
			$record->save();
			$this->set_record($record);
		}
		
		return;
	}
	
	public function up() {}
	
	public function down() {}
	
	public function runDown()
	{
		$this->set_direction(self::DOWN)->migrate();
		$this->down();
		$this->record()->delete();
		
		return;
	}
	
	/**
	 * Getter for direction of migration
	 * @return 
	 */
	public function direction() 
	{
		if (!$this->direction) 
			throw new MigrationException('Unable to determine the direction');
		return $this->direction;
	}
	
	/**
	 * Build alter table drop column sql
	 * @return string sql
	 */
	private function build_drop_column($table_name, $column)
	{
		return "ALTER TABLE $table_name DROP COLUMN $column";
	}
	
	/**
	 * Build alter table add column sql
	 * @return string sql
	 */
	private function build_add_column($table_name, $column, $type, $length = null, $null = true) {
		$this->column($column, $type, $length, $null);
		
		return "ALTER TABLE $table_name ADD COLUMN {$this->columns}";
	}
	
	/**
	 * Build create table sql
	 * @return string sql 
	 */
	private function build_create_table($name, $closure) 
	{
		$this->sql	= "CREATE TABLE IF NOT EXISTS $name (";
		$closure();
		if (!$this->id) {
			$this->sql	.=	'id ' . $this->connection->column('primary_key') . ', ';
		}
		
		if (empty($this->columns))
			throw new MigrationException('No column definition in migration');
		
		$this->sql	.= $this->columns . ')';
		$sql = $this->sql;
		unset($this->sql);
		unset($this->columns);

		return $sql;
	}
	
	/**
	 * Build drop table sql
	 * @return string sql
	 */
	private function build_drop_table($name) 
	{
		$sql	= "DROP TABLE " . $name;
		return $sql;
	}
	
	/**
	 * Create column sql builder for create table
	 * @return void
	 */
	private function column() {
		if (empty($this->columns)) {
			$this->columns	= '';
		} else {
			$this->columns	.= ', ';
		}
	
		$args	= func_get_args();
		if (strtolower($args[0]) == self::ID) {
			$this->id	= true;
		}
		$this->columns .= call_user_func_array(array($this->connection, 'column'), $args);
	}
	
	/**
	 * Create string in create table
	 * @return void
	 */
	public function string($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'string', $options);
	}
	
	/**
	 * Create text in create table
	 * @return void
	 */
	public function text($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'text', $options);
	}
	
	/**
	 * Create integer in create table
	 * @return void
	 */
	public function integer($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'integer', $options);
	}
	
	/**
	 * Create float in create table
	 * @return void
	 */
	public function float($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'float', $options);
	}
	
	/**
	 * Create datetime in create table
	 * @return void
	 */
	public function datetime($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'datetime', $options);
	}
	
	/**
	 * Create timestamp in create table
	 * @return void
	 */
	public function timestamp($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'timestamp', $options);
	}
	
	/**
	 * Create time in create table
	 * @return void
	 */
	public function time($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'time', $options);
	}
	
	/**
	 * Create date in create table
	 * @return void
	 */
	public function date($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'date', $options);
	}
	
	/**
	 * Create binary in create table
	 * @return void
	 */
	public function binary($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'binary', $options);
	}
	
	/**
	 * Create boolean in create table
	 * @return void
	 */
	public function boolean($name, $options = []) {
		$options = array_merge($this->columnDefaults, [
				'default' => 0
			], $options);
		$this->column($name, 'boolean', $options);
	}
	
	/**
	 * Create double in create table
	 * @return void
	 */
	public function double($name, $options = []) {
		$options = array_merge($this->columnDefaults, $options);
		$this->column($name, 'double', $options);
	}
	
	/**
	 * Create timestamps for table
	 * @return void
	 */
	public function timestamps() {
		$this->timestamp('created_at');
		$this->timestamp('updated_at');
	}
	
}

?>
