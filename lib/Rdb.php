<?php
/*
 * Created by Martin Wernståhl on 2009-08-07.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * The main class to interact with when doing database queries.
 */
final class Rdb
{
	const VERSION = '0.6.2';

	/**
	 * The path to this file, so the autoloader loads the file properly.
	 *
	 * @var string
	 */
	protected static $lib_base = '';

	/**
	 * Associative of active database connections.
	 *
	 * @var array
	 */
	protected static $conn_list = array();

	/**
	 * Associative array of connection configurations.
	 *
	 * @var array
	 */
	protected static $conn_configs = array();

	/**
	 * Name of the default database connection to use.
	 *
	 * @var string
	 */
	protected static $conn_default = 'default';

	/**
	 * Associative array of loaded mappers.
	 *
	 * @var array	class_name => Db_Mapper object
	 */
	protected static $mapper_list = array();

	/**
	 * Associative array of descriptors.
	 *
	 * Value can be either an object or a class name.
	 *
	 * @var array
	 */
	protected static $mapper_descriptors = array();

	/**
	 * The callable to use to load descriptors in a customized way.
	 *
	 * @var callable
	 */
	protected static $mapper_descriptor_loader = array();

	/**
	 * Directory for containing the descriptors.
	 *
	 * @var string
	 */
	protected static $mapper_desc_dir = '';

	/**
	 * If to cache the compiled mappers.
	 *
	 * @var bool
	 */
	protected static $mapper_compile = false;

	/**
	 * Directory for containing the cached mappers.
	 *
	 * @var string
	 */
	protected static $mapper_cache_dir = '';

	// ------------------------------------------------------------------------

	/**
	 * Private constructor to prevent anyone from instantiating an instance of the Db class.
	 */
	private function __construct(){}

	// ------------------------------------------------------------------------

	/**
	 * Initializes the autoloader.
	 *
	 * @return void
	 */
	public static function initAutoload()
	{
		self::$lib_base = dirname(__FILE__) . DIRECTORY_SEPARATOR;

		spl_autoload_register(array('Rdb', 'autoload'));
	}

	// ------------------------------------------------------------------------

	/**
	 * Autoloads a certain class.
	 *
	 * @param  string
	 * @return bool
	 */
	public static function autoload($class)
	{
		// only include Db_... classes, remove to make it generic
		if(substr($class, 3) == 'Db_')
		{
			return false;
		}

		// $lib_base is the basepath of this library
		$file = self::$lib_base . str_replace(array('_', '\\'), DIRECTORY_SEPARATOR, $class).'.php';

		if(file_exists($file))
		{
			require $file;

			// did we get a class or interface? (do not try to autoload)
			if(class_exists($class, false) OR interface_exists($class, false))
			{
				return true;
			}
		}

		return false;
	}

	// ------------------------------------------------------------------------

	/**
	 * Sets the configuration for a certain database configuration.
	 *
	 * @param  string
	 * @param  array
	 * @return void
	 */
	public static function setConnectionConfig($name, $configuration = false)
	{
		if(is_array($name))
		{
			self::$conn_configs = array_merge(self::$conn_configs, $name);
		}
		elseif( ! $configuration)
		{
			throw new Db_Connection_ConfigurationException($name, 'Invalid configuration.');
		}
		else
		{
			self::$conn_configs[$name] = $configuration;
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Sets the name of the default database connection.
	 *
	 * @param  string
	 * @return void
	 */
	public static function setDefaultConnectionName($name)
	{
		self::$conn_default = $name;
	}

	// ------------------------------------------------------------------------

	/**
	 * Getter for the database connections, semi-singleton.
	 *
	 * @param  string
	 * @return Db_Connection
	 */
	public static function getConnection($name = false)
	{
		if(empty($name))
		{
			// use the default connection
			$name = self::$conn_default;
		}

		if( ! isset(self::$conn_list[$name]))
		{
			if(empty(self::$conn_configs[$name]) OR ! is_array(self::$conn_configs[$name]))
			{
				throw new Db_Connection_ConfigurationException($name, 'Configuration is missing.');
			}

			if(empty(self::$conn_configs[$name]['dbdriver']))
			{
				throw new Db_Connection_ConfigurationException($name, 'Configuration is malformed.');
			}

			$class = 'Db_Driver_'.ucfirst(strtolower(self::$conn_configs[$name]['dbdriver'])).'_Connection';

			self::$conn_list[$name] = new $class($name, self::$conn_configs[$name]);
		}

		return self::$conn_list[$name];
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns all initialized connections.
	 *
	 * @return array
	 */
	public static function getLoadedConnections()
	{
		return self::$conn_list;
	}

	// ------------------------------------------------------------------------

	/**
	 * Sets the directory for the mapper cache.
	 *
	 * @param  string
	 * @return void
	 */
	public static function setMapperCacheDir($path)
	{
		self::$mapper_cache_dir = $path;
	}

	// ------------------------------------------------------------------------

	/**
	 * Sets a directory which contains the mapper descriptors, they are loaded upon request.
	 *
	 * The descriptor file names must be named like this: ClassName.php
	 * And the classes must be named like this: ClassNameDescriptor
	 *
	 * @param  string
	 * @return void
	 */
	public static function setDescriptorDirectory($directory)
	{
		self::$mapper_desc_dir = $directory;
	}

	// ------------------------------------------------------------------------

	/**
	 * Sets a callable which will load a descriptor for the supplied class.
	 *
	 * Method signature of the callable:
	 * <code>
	 * Db_Descriptor function(string $class_name);
	 * </code>
	 *
	 * If anything but a Db_Descriptor (or a child class) is returned, it will
	 * be considered a failure, and RapidDataMapper will search elsewhere for
	 * a descriptor.
	 *
	 * Multiple descriptor loaders are supported, and they are called in the
	 * order that they have been registered in. It goes through all of them
	 * until one of them returns a descriptor.
	 *
	 * @param  callable
	 * @return void
	 */
	public static function setDescriptorLoader($callable)
	{
		if( ! is_callable($callable, true))
		{
			throw new InvalidArgumentException('Faulty syntax in supplied callable.');
		}

		self::$mapper_descriptor_loader[] = $callable;
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns an array with the registered descriptor loaders.
	 *
	 * @return array
	 */
	public static function getDescriptorLoaders()
	{
		return self::$mapper_descriptor_loader;
	}

	// ------------------------------------------------------------------------

	/**
	 * Adds a certain descriptor.
	 *
	 * @param  Db_Descriptor
	 * @return void
	 */
	public static function addDescriptor(Db_Descriptor $descriptor)
	{
		if(array_key_exists(self::$mapper_descriptors[$descriptor->getClass()]))
		{
			throw new RuntimeException('RapidDataMapper: The descriptor for the class "'.$descriptor->getClass().'" has already been loaded.');
		}

		self::$mapper_descriptors[$descriptor->getClass()] = $descriptor;
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns the descriptor for a certain class.
	 *
	 * - First checks if there already is a loaded descriptor
	 *   (or manually loaded, via addDescriptor()).
	 * - After that it checks if there is a registered descriptor
	 *   loader, which would return a descriptor instance describing the class.
	 * - Then it checks if ClassNameDescriptor.php exists
	 *   (it uses the autoloader(s), so Record_UserDescriptor will be placed in Record/UserDescriptor.php).
	 * - Finally it tries the descriptor directory for any files with the name
	 *   ClassName.php, which will contain a ClassNameDescriptor class (no autoloader).
	 *
	 * @param  string
	 * @throws Db_DescriptorException
	 * @return Db_Descriptor
	 */
	public static function getDescriptor($class)
	{
		// Strtolower so we're sure that we don't load it twice
		$c = strtolower($class);

		if(isset(self::$mapper_descriptors[$c]))
		{
			return self::$mapper_descriptors[$c];
		}

		// check if we can call a mapper descriptor loader (also autoload it, and see if it can be called)
		foreach(self::$mapper_descriptor_loader as $loader)
		{
			// check that we get a Db_Descriptor object
			if(is_callable($loader) && ($d = call_user_func($loader, $class)) instanceof Db_Descriptor)
			{
				return self::$mapper_descriptors[$c] = $d;
			}
		}

		// default class name
		$klass = $class.'Descriptor';

		// do we have a descriptor class? (it also tries to autoload it with class_exists())
		if( ! class_exists($klass))
		{
			// do we have a certain descriptor file?
			if(file_exists(self::$mapper_desc_dir.'/'.$class.'.php'))
			{
				require self::$mapper_desc_dir.'/'.$class.'.php';
			}

			// check if any class was loaded, do not use autoload this time
			if( ! class_exists($klass, false))
			{
				throw new Db_DescriptorException($class, 'Descriptor is missing.');
			}
		}

		// Create instance
		return self::$mapper_descriptors[$c] = new $klass();
	}

	// ------------------------------------------------------------------------

	/**
	 * Returns the mapper instance for the supplied class.
	 *
	 * @param  string
	 * @return Db_Mapper
	 */
	public static function getMapper($class)
	{
		$c = strtolower($class);

		if(isset(self::$mapper_list[$c]))
		{
			return self::$mapper_list[$c];
		}
		// do not try to use the autoloader here, as the mappers aren't stored in the lib folder
		elseif( ! class_exists($klass = 'Db_Compiled_'.$class.'Mapper', false))
		{
			$class = strtolower($class);

			if(file_exists(self::$mapper_cache_dir.'/'.$class.'.php'))
			{
				// cached mapper
				require self::$mapper_cache_dir.'/'.$class.'.php';
			}
			else
			{
				$desc = self::getDescriptor($class);

				$code = $desc->getBuilder();

				if(self::$mapper_compile)
				{
					// write the precompiled file
					$res = @file_put_contents(self::$mapper_cache_dir.'/'.$class.'.php', '<?php
/*
 * Generated by RapidDataMapper on '.date('Y-m-d H:i:s').'.
 *
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

'.(String) $code);

					// did the write work?
					if( ! $res)
					{
						// we need to tell the user that he needs to make the folder writable
						// therefore he will know why it is slow
						trigger_error('Cannot write to the "'.self::$mapper_cache_dir.'" directory', E_USER_WARNING);

						// eval the code in case it didn't get written
						eval((String) $code);
					}
					else
					{
						require self::$mapper_cache_dir.'/'.$class.'.php';
					}
				}
				else
				{
					eval((String) $code);
				}
			}
		}

		return self::$mapper_list[$c] = new $klass();
	}

	// ------------------------------------------------------------------------

	/**
	 * Switches the caching of the compiled mapper classes on/off.
	 *
	 * @param  bool
	 * @return void
	 */
	public static function setCompileMappers($value = true)
	{
		self::$mapper_compile = $value;
	}

	// ------------------------------------------------------------------------

	/**
	 * Creates a query for finding a special type of object.
	 *
	 * Create a fetch query:
	 *
	 * If condition is false, then a populated Db_Query_MapperSelect object will be returned.
	 * This object can then be modified to apply custom filters, ordering etc.
	 * <code>
	 * $query = Rdb::find('user');
	 * $query->where('foo', 'bar'); // filter etc.
	 * $users = $query->get();
	 * </code>
	 *
	 * Find by Primary Key:
	 *
	 * If you search for a record from which you have the primary key, put that as the
	 * condition (if it is a multi-key, just put it as an array without keys).
	 * In this case, an object will be returned.
	 * <code>
	 * $user = Rdb::find('user', 3);
	 * </code>
	 *
	 * Find by filter:
	 *
	 * If you want to apply filters directly, the conditions and values parameters
	 * function like the Db_Query::where() method (provided either
	 * conditions and values are populated, or that conditions is an associative
	 * array).
	 * In this case an array will be returned.
	 * <code>
	 * $users = Rdb::find('user', 'name', 'foobar');
	 * $users = Rdb::find('user', array('name' => 'foobar'));
	 * </code>
	 *
	 * @param  string
	 * @param  mixed
	 * @param  mixed
	 * @return array|object|Db_Query_MapperSelect
	 */
	public static function find($class_name, $conditions = false, $values = false)
	{
		$m = self::getMapper($class_name);

		return $m->find($conditions, $values);
	}

	// ------------------------------------------------------------------------

	/**
	 * Saves the object and all loaded related objects.
	 *
	 * @param  object
	 * @param  bool
	 * @return bool
	 */
	public static function save($object, $force = false)
	{
		$m = self::getMapper(get_class($object));

		return $m->save($object, $force);
	}

	// ------------------------------------------------------------------------

	/**
	 * Deletes the supplied object and triggers registered cascades.
	 *
	 * @param  object
	 * @return bool
	 */
	public static function delete($object)
	{
		$m = self::getMapper(get_class($object));

		return $m->delete($object);
	}

	// ------------------------------------------------------------------------

	/**
	 * Populates a query which will fetch the objects related to the first parameter.
	 *
	 * Example:
	 * <code>
	 * $user = Rdb::find('user', 3);
	 * $posts = Rdb::related($user, 'posts')->get();
	 * </code>
	 *
	 * @param  object
	 * @param  string
	 * @return Db_Query_MapperSelect
	 */
	public static function related($object, $relation)
	{
		$m = self::getMapper(get_class($object));

		if( ! isset($m->relations[$relation]))
		{
			throw new Db_Descriptor_MissingRelationException(get_class($object), $relation);
		}

		$rm = self::getMapper($m->relations[$relation]);

		$q = $rm->createMapperQuery();
		$m->applyRelatedConditions($q, $relation, $object);

		return $q;
	}

	// ------------------------------------------------------------------------

	/**
	 * Checks if a property has been edited, if no property name is supplied it checks if the object has been changed.
	 *
	 * NOTE:
	 * Does not yet check for modified relations.
	 *
	 * @param  object
	 * @param  string
	 * @return bool
	 */
	public static function isChanged($object, $property = false)
	{
		// TODO: Maybe add support for relations?
		if(empty($object->__id))
		{
			return true;
		}

		if($property === false)
		{
			return empty($object->__data) ? false : (Bool) array_diff($object->__data, (Array) $object);
		}
		else
		{
			$m = self::getMapper(get_class($object));

			// single property
			if(isset($m->properties[$property]))
			{
				$column = $m->properties[$property];

				return isset($object->__data[$column]) && ( ! isset($object->$property) OR $object->$property != $object->__data[$column]);
			}
			else
			{
				// does not exist
				return false;
			}
		}
	}

	// ------------------------------------------------------------------------

	/**
	 * Dumps the content of the variable to output, for debugging.
	 *
	 * This is a special clone of print_r() which ignores properties with __.
	 *
	 * If the result is dumped to screen, it is htmlentities()ed and wrapped in <pre> tags.
	 *
	 * @param  mixed
	 * @param  bool		Determines if the output should be written or not
	 * @param  int
	 * @return void|string
	 */
	public static function dump($data, $max_nesting = 5, $return = false, $indent = 0)
	{
		$ind = str_repeat("    ", $indent);

		switch(gettype($data))
		{
			case 'bool':
			case 'boolean':
				$str = $data ? 'TRUE' : 'FALSE';
				break;

			case 'NULL':
				$str = 'NULL';
				break;

			case 'integer':
			case 'double':
			case 'float':
				$str = $data;
				break;

			case 'string':
				$str = "'$data'";
				break;

			default:
				if(is_object($data))
				{
					$keys = array();

					if( ! empty($data->__id))
					{
						foreach($data->__id as $k => $v)
						{
							$keys[] = $k.': '.$v;
						}
					}
					else
					{
						$keys = array('new');
					}

					$keys = implode(', ', $keys);

					$str = get_class($data) . " Object ($keys)\n$ind{\n";
				}
				else
				{
					$str = "Array\n$ind(\n";
				}

				if($indent >= $max_nesting)
				{
					$str .= "{$ind}    MAX NESTING REACHED\n";
				}
				else
				{
					foreach((Array) $data as $k => $v)
					{
						if(strpos($k, '__') === 0)
						{
							continue;
						}

						$modflag = is_object($data) && isset($data->__data[$k]) ? ($data->__data[$k] != $v ? '*' : '') : '';

						$str .= $ind . "    [$k]$modflag => " . self::dump($v, $max_nesting, true, $indent + 1);
					}
				}

				if(is_object($data))
				{
					$str .= "$ind}";
				}
				else
				{
					$str .= "$ind)";
				}
		}

		$str .= "\n";

		if( ! $return)
		{
			if(strtolower(PHP_SAPI) == 'cli')
			{
				echo $str;
			}
			else
			{
				// prepare for output into HTML
				echo '<pre>' . htmlentities($str) . '</pre>';
			}
		}
		else
		{
			return $str;
		}
	}
}


/* End of file Db.php */
/* Location: ./lib */