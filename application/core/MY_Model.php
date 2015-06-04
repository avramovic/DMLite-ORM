<?php if ( !defined( 'BASEPATH' ) ) exit( 'No direct script access allowed' );

/**
 * DMLite ORM v.0.1
 *
 * A simple ORM model class for CodeIgniter 3 with one-to-many and many-to-many relations.
 *
 * made by Nemanja Avramović
 * http://avramovic.info
 * https://github.com/avramovic/DMLite-ORM
**/

define('DML_NO_RELATION', 0);
define('DML_ONE_TO_MANY', 1);
define('DML_MANY_TO_ONE', 2);
define('DML_MANY_TO_MANY', 3);

class MY_Model extends CI_Model implements Iterator {

	protected static $_ci = NULL;
	protected $_class = NULL;
	protected $_joined = array();
	protected $_data = NULL;
	protected $_db = NULL;
	protected $_include = array();
	protected $_select = array();
	protected $_db_columns = array();
	protected $_updated = array();
	protected $_result = NULL;
	protected $_related = array();
	public $has_one = array();
	public $has_many = array();
	public $table = NULL;
	public $all = array();
	public $stored = NULL;
	public $primary_key = 'id';

	/* constructor */
	public function __construct($ignore_cache = false)
	{
		parent::__construct();

		if (self::$_ci === NULL)
			self::$_ci = &get_instance();

		$this->_class = get_called_class();
		$this->_db = self::$_ci->db;
		//$this->_dbutil = self::$_ci->db;
		$this->_data = $this->stored = new stdClass;
		$this->_data->id = 0;
		if (get_called_class() !== __CLASS__)
			$this->_get_columns($ignore_cache);
	}

	/* return codeigniter instance */

	protected function _ci()
	{
		return self::$_ci;
	}

	/* return database instance */

	public function _db()
	{
		return $this->_db;
	}

	/* helper function to make singular form of the word */

	protected static function _singular($str)
	{
		$str = strtolower(trim($str));
		$end5 = substr($str, -5);
		$end4 = substr($str, -4);
		$end3 = substr($str, -3);
		$end2 = substr($str, -2);
		$end1 = substr($str, -1);

		if ($end5 == 'eives')
		{
			$str = substr($str, 0, -3).'f';
		}
		elseif ($end4 == 'eaux')
		{
			$str = substr($str, 0, -1);
		}
		elseif ($end4 == 'ives')
		{
			$str = substr($str, 0, -3).'fe';
		}
		elseif ($end3 == 'ves')
		{
			$str = substr($str, 0, -3).'f';
		}
		elseif ($end3 == 'ies')
		{
			$str = substr($str, 0, -3).'y';
		}
		elseif ($end3 == 'men')
		{
			$str = substr($str, 0, -2).'an';
		}
		elseif ($end3 == 'xes' && strlen($str) > 4 OR in_array($end3, array('ses', 'hes', 'oes')))
		{
			$str = substr($str, 0, -2);
		}
		elseif (in_array($end2, array('da', 'ia', 'la')))
		{
			$str = substr($str, 0, -1).'um';
		}
		elseif (in_array($end2, array('bi', 'ei', 'gi', 'li', 'mi', 'pi')))
		{
			$str = substr($str, 0, -1).'us';
		}
		else
		{
			if ($end1 == 's' && $end2 != 'us' && $end2 != 'ss')
			{
				$str = substr($str, 0, -1);
			}
		}

		return $str;
	}

	/* helper function to make plural form of the word */

	protected static function _plural($str, $force = FALSE)
	{
		$str = strtolower(trim($str));
		$end3 = substr($str, -3);
		$end2 = substr($str, -2);
		$end1 = substr($str, -1);

		if ($end3 == 'eau')
		{
			$str .= 'x';
		}
		elseif ($end3 == 'man')
		{
			$str = substr($str, 0, -2).'en';
		}
		elseif (in_array($end3, array('dum', 'ium', 'lum')))
		{
			$str = substr($str, 0, -2).'a';
		}
		elseif (strlen($str) > 4 && in_array($end3, array('bus', 'eus', 'gus', 'lus', 'mus', 'pus')))
		{
			$str = substr($str, 0, -2).'i';
		}
		elseif ($end3 == 'ife')
		{
			$str = substr($str, 0, -2).'ves';
		}
		elseif ($end1 == 'f')
		{
			$str = substr($str, 0, -1).'ves';
		}
		elseif ($end1 == 'y')
		{
			if(preg_match('#[aeiou]y#i', $end2))
			{
				// ays, oys, etc.
				$str = $str . 's';
			}
			else
			{
				$str = substr($str, 0, -1).'ies';
			}
		}
		elseif ($end1 == 'o')
		{
			if(preg_match('#[aeiou]o#i', $end2))
			{
				// oos, etc.
				$str = $str . 's';
			}
			else
			{
				$str .= 'es';
			}
		}
		elseif ($end1 == 'x' || in_array($end2, array('ss', 'ch', 'sh')) )
		{
			$str .= 'es';
		}
		elseif ($end1 == 's')
		{
			if ($force == TRUE)
			{
				$str .= 'es';
			}
		}
		else
		{
			$str .= 's';
		}

		return $str;
	}

	/* get model table (prefixed or not) */

	public function _table($prefixed=false)
	{
		if ($this->table === NULL)
			$this->table = strtolower(self::_plural($this->_class));

		if ($prefixed)
			return $this->_db->dbprefix.$this->table;
		else
			return $this->table;
	}

	/* function to get primary id key column name */

	public function _id()
	{
		return $this->primary_key;
	}

	/* function to get secondary id key column name */

	public static function _fkid($table)
	{
		return strtolower(self::_singular($table).'_id');
	}

	/* create join table name based on array of two tables */

	public function _get_join_table($tables)
	{
		sort($tables);
		return $this->_db->dbprefix.$tables[0].'_'.$tables[1];
	}

	/* create join table on this table based on the other table */

	public function _join_table($other_table)
	{
		return $this->_get_join_table(array($this->_table(), $other_table));
	}

	/* get relation type between this and other model */

	public function _get_relation_type($model)
	{
		$other_class = strtolower($model);
		$correct_class_name = ucfirst($other_class);
		$other_obj = new $correct_class_name;
		$this_class = strtolower($this->_class);

		if (in_array($this_class, $other_obj->has_one) && in_array($other_class, $this->has_many))
		{
			//one to many
			return DML_ONE_TO_MANY;
		}
		else if (in_array($this_class, $other_obj->has_many) && in_array($other_class, $this->has_one))
		{
			//many to one
			return DML_MANY_TO_ONE;
		}
		else if (in_array($this_class, $other_obj->has_many) && in_array($other_class, $this->has_many))
		{
			//many to many
			return DML_MANY_TO_MANY;
		}

		return DML_NO_RELATION;
	}

	/* join other table to the query */

	public function _join($other_class, $join_type=NULL, $second_class=NULL)
	{
		$other_class = strtolower($other_class);

		if (!in_array($other_class, $this->_joined))
		{
			$correct_class_name = ucfirst($other_class);
			$other_obj = new $correct_class_name;
			$other_table = $other_obj->_table(true);
			$other_table_nopref = $other_obj->_table();

			$this_class = strtolower($this->_class);
			$this_table = $this->_table(true);
			$this_table_nopref = $this->_table();
			$join_table = $this->_join_table($other_obj->_table());
			$relation_type = $this->_get_relation_type($other_class);

			if ($second_class != NULL)
			{
				$this_class = strtolower($second_class);
				$correct_class_name2 = ucfirst($this_class);
				$this_obj = new $correct_class_name2;
				$this_table = $this_obj->_table(true);
				$this_table_nopref = $this_obj->_table();
				$join_table = $this_obj->_join_table($other_obj->_table());
				$relation_type = $this_obj->_get_relation_type($other_class);
			}

			switch ($relation_type)
			{
				case DML_ONE_TO_MANY:
					$fkid = self::_fkid($this_table_nopref);
					$this->_db->join($other_table, "`{$other_table}`.`{$fkid}` = `{$this_table}`.`id`", $join_type);
				break;
				case DML_MANY_TO_ONE:
					$fkid = self::_fkid($other_table_nopref);
					$this->_db->join($other_table, "`{$other_table}`.`id` = `{$this_table}`.`{$fkid}`", $join_type);
				break;
				case DML_MANY_TO_MANY:
					$fkid1 = self::_fkid($this_table_nopref);
					$fkid2 = self::_fkid($other_table_nopref);
					$this->_db->join($join_table, "`{$this_table}`.`id` = `{$join_table}`.`{$fkid1}`", $join_type);
					$this->_db->join($other_table, "`{$other_table}`.`id` = `{$join_table}`.`{$fkid2}`", $join_type);
				break;
				default:
					trigger_error("Unable to relate ".ucfirst($this_class)." with ".ucfirst($other_class)."!");
			}

			$this->_joined[] = $other_class;
		}

		return $this;
	}

	/* magic function to set properties */

	public function __set($name, $value)
	{
		if (property_exists($this, $name))
			$this->{$name} = $value;
		else
		{
			if (!in_array($name, $this->_updated))
				$this->_updated[] = $name;

			$this->_data->{$name} = $value;
		}
	}

	/* magic function to get properties */

	public function __get($name)
	{
		$singular = self::_singular($name);
		if (in_array($singular, $this->has_many) || in_array($singular, $this->has_one))
		{
			if (!isset($this->_related[$singular]))
			{
				$class = ucfirst(strtolower($singular));
				$obj = new $class;
				$this->_related[$singular] = $obj->where_related($this->_class, 'id', (int)$this->stored->id);
			}

			return $this->_related[$singular];
		}
		else if (!property_exists($this, $name))
		{
			if (isset($this->_data->{$name}))
				return $this->_data->{$name};
			else
				return NULL;
		}
		else
			return $this->{$name};
	}

	/* magic function to call method */

	public function __call($method, $args=array())
	{
		$magic = array('get_by_', 'where_', 'where_related_', 'or_where_related_', 'where_in_related_'
					, 'or_where_in_related_', 'where_not_in_related_', 'or_where_not_in_related_'
					, 'include_related_');

		foreach ($magic as $func)
		{
			if (strpos($method, $func) === 0)
			{
				$arg1 = str_replace($func, '', $method);
				$func_to_call = substr($func, 0, -1);
				$args2 = array_merge(array($arg1), $args);

				return call_user_func_array(array($this, $func_to_call), $args2);
			}
		}


		if (method_exists($this, $method))
		{
			return call_user_func_array(array($this, $method), $args);
		}
		else if (method_exists($this->_db, $method))
		{

			call_user_func_array(array($this->_db, $method), $args);
			return $this;
		}
		else trigger_error("Unable to call {$method}() on ".$this->_class);
	}

	/* check if data is set */

	public function _isset($name)
	{
		return (is_object($this->_data) && isset($this->_data->{$name}));
	}

	/* get table columns (from cache?) */

	public function _get_columns($ignore_cache = false)
	{
		if (!empty($this->_db_columns))
			return $this->_db_columns;

		$path = APPPATH.'cache/dml';
		$cache = $path.'/'.$this->_table().'.cache';

		if (is_dir($path) && is_file($cache) && !$ignore_cache)
		{
			$this->_db_columns = unserialize(file_get_contents(realpath($cache)));
			return $this->_db_columns;
		}

		$this->_db_columns = $this->_db->list_fields($this->_table(true));

		if (is_dir($path) && is_really_writable($path) && !$ignore_cache)
		{
			file_put_contents($cache, serialize($this->_db_columns));
		}

		return $this->_db_columns;
	}

	/* include related models to the fetched result */

	public function include_related($model, $prefix=false)
	{
		if (!$prefix)
		{
			$arr = explode('/', $model);
			$prefix = strtolower(end($arr)).'_';
		}

		$this->_include[] = array('model'=>strtolower($model), 'prefix'=>$prefix);
		return $this;
	}

	/* get data from database */

	public function get()
	{
		$table = $this->_table(true);
		if (empty($this->_select))
		{
			$this->_db->select("$table.*");
		}
		else
		{
			foreach ($this->_select as $field)
			{
				if ((strpos($field, ' ') !== FALSE) || (strpos($field, '(') !== FALSE))
				{
					if (strpos($field, '.') !== FALSE)
						$this->_db->select("{$field}", false);
					else
						$this->_db->select("{$table}.{$field}", false);
				}
				else
					$this->_db->select("{$table}.`{$field}`");
			}
		}

		/* including related */

		if (!empty($this->_include))
		{
			foreach ($this->_include as $include)
			{
				$data = $this->_process_joins($include['model'], true);
				$other_obj = $data['object'];
				$other_table = $data['table'];
				$fields = $other_obj->_get_columns();

				foreach ($fields as $field)
				{
					$this->_db->select("$other_table.`$field` AS `{$include['prefix']}{$field}`", FALSE);
				}
			}
		}


		$this->_result = $this->_db->get($this->_table(true));

		$this->_data = $this->stored = new stdClass;
		$this->all = array();

		$i=0;
		foreach ($this->_result->result() as $row)
		{
			if ($i==0)
				$this->_data = $this->stored = $row;

			$obj = new $this->_class;
			$obj->_data = $obj->stored = $row;
			array_push($this->all, $obj);
			$i++;
		}

		return $this;
	}

	/* save data with relations */

	public function save($related=array())
	{

		$data = array();
		foreach ($this->_updated as $field)
		{
			if (in_array($field, $this->_db_columns))
				$data[$field] = $this->_data->{$field};
		}

		unset($data['id']);

		if (!empty($data))
		{
			if ($this->exists())
			{
				if (in_array('updated', $this->_db_columns) && !in_array('updated', $this->_updated))
					$data['updated'] = time();

				$this->where('id', $this->_data->id);
				$this->_db->update($this->_table(true), $data);
			}
			else
			{
				if (in_array('created', $this->_db_columns) && empty($this->_data->created))
					$data['created'] = time();

				$this->_db->insert($this->_table(true), $data);
				$this->stored->id = $this->_data->id = $this->_db->insert_id();
			}
		}


		$this->_updated = array();

		if (empty($related))
			return $this;

		//relations
		foreach ($related as $rel)
		{
			if (is_object($rel))
			{
				$relation = $this->_get_relation_type($rel->_class);

				switch ($relation)
				{
					case DML_ONE_TO_MANY:
						$dejta = array();
						$column = self::_fkid($this->_table());
						$dejta[$column] = $this->id;
						$this->where('id', $rel->_data->id);
						$this->_db->update($rel->_table(true), $dejta);
					break;
					case DML_MANY_TO_ONE:
						$dejta = array();
						$column = self::_fkid($rel->_table());
						$dejta[$column] = $rel->id;
						$this->where('id', $this->_data->id);
						$this->_db->update($this->_table(true), $dejta);

					break;
					case DML_MANY_TO_MANY:
						$dejta = array();
						$column1 = self::_fkid($this->_table());
						$column2 = self::_fkid($rel->_table());
						$dejta[$column1] = $this->id;
						$dejta[$column2] = $rel->id;
						$join_table = $this->_join_table($rel->_table());

						$check = $this->_db->where($column1, $this->id)
									->where($column2, $rel->id)
									->get($join_table);

						$total = $check->num_rows();
						if ($total < 1)
						{
							$this->_db->insert($join_table, $dejta);
						}

					break;
					default:
						trigger_error("Unable to relate ".$this->_class." with ".$rel->_class."!", E_USER_ERROR);
				}
			}
			else if (is_array($rel))
			{
				foreach ($rel as $obj)
				{
					$relation = $this->_get_relation_type($obj->_class);
					switch ($relation)
					{
						case DML_ONE_TO_MANY:
							$dejta = array();
							$column = self::_fkid($this->_table());
							$dejta[$column] = $this->id;
							$this->where('id', $obj->_data->id);
							$this->_db->update($obj->_table(true), $dejta);
						break;
						case DML_MANY_TO_ONE:
							$dejta = array();
							$column = self::_fkid($obj->_table());
							$dejta[$column] = $obj->id;
							$this->where('id', $this->_data->id);
							$this->_db->update($this->_table(true), $dejta);
						break;
						case DML_MANY_TO_MANY:
							$dejta = array();
							$column1 = self::_fkid($this->_table());
							$column2 = self::_fkid($obj->_table());
							$dejta[$column1] = $this->id;
							$dejta[$column2] = $obj->id;
							$join_table = $this->_join_table($obj->_table());

							$check = $this->_db->where($column1, $this->id)
										->where($column2, $obj->id)
										->get($join_table);

							$total = $check->num_rows();
							if ($total < 1)
							{
								$this->_db->insert($join_table, $dejta);
							}

						break;
						default:
							trigger_error("Unable to relate ".$this->_class." with ".$rel->_class."!", E_USER_ERROR);
					} //of switch
				} //of foreach
			} //of if
		} //of foreach

		return $this;
	}

	/* delete data or relations */

	public function delete($related = array())
	{
		if (!empty($related))
		{
			//relations
			foreach ($related as $rel)
			{
				if (is_object($rel))
				{
					$relation = $this->_get_relation_type($rel->_class);

					switch ($relation)
					{
						case DML_ONE_TO_MANY:
							$dejta = array();
							$column = self::_fkid($this->_table());
							$dejta[$column] = NULL;
							$this->where('id', $rel->_data->id);
							$this->_db->update($rel->_table(true), $dejta);
						break;
						case DML_MANY_TO_ONE:
							$dejta = array();
							$column = self::_fkid($rel->_table());
							$dejta[$column] = NULL;
							$this->where('id', $this->_data->id);
							$this->_db->update($this->_table(true), $dejta);
						break;
						case DML_MANY_TO_MANY:
							$column1 = self::_fkid($this->_table());
							$column2 = self::_fkid($rel->_table());
							$join_table = $this->_join_table($rel->_table());

							$this->_db->where($column1, $this->id)
										->where($column2, $rel->id)
										->delete($join_table);
						break;
						default:
							trigger_error("Unable to relate ".$this->_class." with ".$rel->_class."!", E_USER_ERROR);
					}
				}
				else if (is_array($rel))
				{
					foreach ($rel as $obj)
					{
						$relation = $this->_get_relation_type($obj->_class);
						switch ($relation)
						{
							case DML_ONE_TO_MANY:
								$dejta = array();
								$column = self::_fkid($this->_table());
								$dejta[$column] = NULL;
								$this->where('id', $obj->_data->id);
								$this->_db->update($obj->_table(true), $dejta);
							break;
							case DML_MANY_TO_ONE:
								$dejta = array();
								$column = self::_fkid($obj->_table());
								$dejta[$column] = NULL;
								$this->where('id', $this->_data->id);
								$this->_db->update($this->_table(true), $dejta);
							break;
							case DML_MANY_TO_MANY:
								$column1 = self::_fkid($this->_table());
								$column2 = self::_fkid($obj->_table());
								$join_table = $this->_join_table($obj->_table());

								$this->_db->where($column1, $this->id)
											->where($column2, $obj->id)
											->delete($join_table);

							break;
							default:
								trigger_error("Unable to relate ".$this->_class." with ".$rel->_class."!", E_USER_ERROR);
						} //of switch
					} //of foreach
				} //of if
			} //of foreach
		}
		else
		{
			if ($this->exists())
			{
				$this->_db->where('id', $this->stored->id)->delete($this->_table(true));
			}
			else
			{
				$this->_db->delete($this->_table(true));
			}
		}
	}

	/* delete whole set */

	public function delete_all()
	{
		if ($this->exists())
		{
			$ids = $this->get_column_values('id');
			return $this->_db->where_in('id', $ids)->delete($this->_table(true));
		}
		else
			return $this->_db->delete($this->_table(true));
	}

	/* get all values of a single column as a php array */

	public function get_column_values($col)
	{
		$ret = array();
		foreach ($this->all as $obj)
		{
			$ret[] = $obj->_data->{$col};
		}
		return $ret;
	}

	/* get by single column */

	public function get_by($col, $val)
	{
		return $this->where($col, $val)->get();
	}

	/* select fields */

	public function select($fields)
	{
		$fields = explode(',', $fields);
		foreach ($fields as $field)
			$this->_select[] = trim($field);

		return $this;
	}

	/* process column names in query */

	protected function _process_column($col)
	{
		if (strpos($col, '.') === FALSE)
		{
			$table = $this->_table(true);
			return "{$table}.`$col`";
		}
		else
		{
			$col = str_replace('`', '', $col);
			$arr = explode('.', $col);
			$table = $arr[0];
			$field = $arr[1];

			return "{$table}.`$field`";
		}
	}

	/* process where clausule in queries */

	protected function _process_where($col, $val)
	{
		$sign = '=';
		if (strpos($col, ' ') !== false)
		{
			$arr = explode(' ', $col);
			$col = $arr[0];
			$sign = $arr[1];
		}

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$col = $this->_process_column($col);

		return $this->_db->compile_binds("$col {$sign} ?", $val);

	}

	/* set where selection of the query */

	public function where($col, $val)
	{
		$where = $this->_process_where($col, $val);
		$this->_db->where($where, null, false);
		return $this;
	}

	public function or_where($col, $val)
	{
		$where = $this->_process_where($col, $val);
		$this->_db->or_where($where, null, false);
		return $this;
	}

	public function where_in($col, $val)
	{
		$col = $this->_process_column($col);
		$this->_db->where_in($col, $val);
		return $this;
	}

	public function or_where_in($col, $val)
	{
		$col = $this->_process_column($col);
		$this->_db->or_where_in($col, $val);
		return $this;
	}

	public function where_not_in($col, $val)
	{
		$col = $this->_process_column($col);
		$this->_db->where_not_in($col, $val);
		return $this;
	}

	public function or_where_not_in($col, $val)
	{
		$col = $this->_process_column($col);
		$this->_db->or_where_not_in($col, $val);
		return $this;
	}

	/* process joins with optional deep relations */

	protected function _process_joins($models, $extended=false)
	{
		$models = strtolower($this->_class).'/'.$models;
		$models = explode('/', $models);

		$second_table = null;
		$second_class = null;
		$other_obj = null;

		for ($i=0;$i<count($models)-1;$i++)
		{
			$first = $models[$i];
			$second = $models[$i+1];
			$first_class = strtolower($first);
			$second_class = strtolower($second);

			$corrected = ucfirst($second_class);
			$second_obj = new $corrected;
			$second_table = $second_obj->_table(true);

			$this->_join($second_class, null, $first_class);//*/
		}

		if ($extended)
			return array('model'=>$second_class, 'table'=>$second_table, 'object'=>$second_obj);
		else
			return $second_table;
	}

	/* query related models (with deep relationships) */

	public function where_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val===true) ? 1 : 0;
		}

		$where = $this->_process_where("{$other_table}.{$col}", $val);
		$this->_db->where($where, NULL, FALSE);

		return $this;
	}

	public function or_where_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$where = $this->_process_where("{$other_table}.{$col}", $val);
		$this->_db->or_where($where, NULL, FALSE);

		return $this;
	}

	public function where_in_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$col = $this->_process_column("{$other_table}.{$col}");
		$this->_db->where_in($col, $val);

		return $this;
	}

	public function or_where_in_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$col = $this->_process_column("{$other_table}.{$col}");
		$this->_db->or_where_in($col, $val);

		return $this;
	}

	public function where_not_in_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$col = $this->_process_column("{$other_table}.{$col}");
		$this->_db->where_not_in($col, $val);

		return $this;
	}

	public function or_where_not_in_related($other_class, $col, $val)
	{
		$other_table = $this->_process_joins($other_class);

		if (is_bool($val))
		{
			$val = ($val==true) ? 1 : 0;
		}

		$col = $this->_process_column("{$other_table}.{$col}");
		$this->_db->or_where_not_in($col, $val);

		return $this;
	}

	/* factory static method */

	public static function factory()
	{
		$args = func_get_args();
		$field = 'id';
		$val = NULL;
		if (count($args) == 1)
		{
			$val = $args[0];
		}
		else if (count($args) > 1)
		{
			$field = $args[0];
			$val = $args[1];
		}

		$class = get_called_class();
		$obj = new $class;

		if (count($args) > 0)
			$obj->where($field, $val)->get();

		return $obj;
	}

	/* check if object exists */

	public function exists()
	{
		return (is_object($this->_data) && (isset($this->_data->id)) ) ? ($this->_data->id > 0) : false;
	}

	/* count results */

	public function result_count()
	{
		/*if ($this->_result !== NULL)
			return $this->_result->num_rows();
		else//*/
			return count($this->all);
	}

	/* dump last query */

	public function check_last_query()
	{
		var_dump($this->last_query());
	}

	/* get last query */

	public function last_query()
	{
		return $this->_db->last_query();
	}

	public function insert_id()
	{
		return $this->_db->insert_id();
	}

	public function affected_rows()
	{
		return $this->_db->affected_rows();
	}

	public function count_all()
	{
		return $this->_db->count_all($this->_table(true));
	}

	public function platform()
	{
		return $this->_db->platform();
	}

	public function version()
	{
		return $this->_db->version();
	}

	public function count_all_results($keep = true)
	{
		return $this->_db->count_all_results($this->_table(true), $keep);
	}

	public function count($keep = true)
	{
		return $this->count_all_results($keep);
	}


	/* iterator */

	public function rewind()
	{
		return reset($this->all);
	}
	public function current()
	{
		return current($this->all);
	}
	public function key()
	{
		return key($this->all);
	}
	public function next()
	{
		return next($this->all);
	}
	public function valid()
	{
		$key = key($this->all);
		$var = ($key !== NULL && $key !== FALSE);
		return $var;
	}

    /* end of iterator */

}