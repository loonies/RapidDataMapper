<?php
/*
 * Created by Martin Wernståhl on 2010-03-30.
 * Copyright (c) 2010 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * 
 */
abstract class Rdm_Collection implements ArrayAccess, Countable, IteratorAggregate
{	
	// ------------------------------------------------------------------------
	// --  CHILD INSTANCE MANAGEMENT RELATED METHODS                         --
	// ------------------------------------------------------------------------
	
	/**
	 * A list containing loaded collection class names.
	 * 
	 * @var array(string)
	 */
	static protected $loaded_collection_classes = array();
	
	// ------------------------------------------------------------------------
	
	/**
	 * Initializes the RapidDataMapper ORM.
	 * 
	 * @param  boolean	If to register the Rdm_Collection::flush() method to run
	 *                	on shutdown.
	 * @return void
	 */
	public static function init($auto_flush = true)
	{
		spl_autoload_register('Rdm_Collection::autoload');
		
		$auto_flush && register_shutdown_function('Rdm_Collection::flush');
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Loads the specific collection classes for entity objects, pattern of their
	 * classnames are <EntityClass>Collection.
	 * 
	 * @param  string
	 * @return boolean
	 */
	public static function autoload($class)
	{
		if(substr($class, -10) !== 'Collection')
		{
			return false;
		}
		
		// Check for a cached file
		if(Rdm_Config::getCacheMappers())
		{
			$dir = Rdm_Config::getMapperCacheDir();
			
			if(file_exists($dir.DIRECTORY_SEPARATOR.$class.'.php'))
			{
				include $dir.DIRECTORY_SEPARATOR.$class.'.php';
				
				if(class_exists($class))
				{
					return true;
				}
			}
		}
		
		// Remove "Collection"
		$entity_class = substr($class, 0, -10);
		
		$desc = Rdm_Config::getDescriptor($entity_class);
		
		try
		{
			// Build the new class
			$builder = $desc->getBuilder();
		}
		catch(Exception $e)
		{
			// Handle errors, we cannot just let exceptions pass through,
			// because then autoload falls back on the other autoloaders
			// and ignores our exception
			
			// Call the exception handler directly instead
			
			// Get the exception handler, use this method as an impostor so
			// we can convince PHP to lend us the current exception handler
			$eh = set_exception_handler(array(__CLASS__, 'autoload'));
			// We must kill the impostor before he is found out!
			restore_exception_handler();
			
			if( ! $eh)
			{
				// We got a fake!
				
				// Now we have to try to fool the buyer...
				self::triggerExceptionError($e);
			}
			else
			{
				// Now we execute the stolen handler!
				call_user_func($eh, $e);
			}
			
			// Let's leave before it blows up!
			exit;
		}
		
		// Do we write a compiled file?
		if(Rdm_Config::getCacheMappers())
		{
			// write the precompiled file
			$res = @file_put_contents(Rdm_Config::getMapperCacheDir().'/'.$class.'.php', '<?php
/*
 * Generated by RapidDataMapper on '.date('Y-m-d H:i:s').'.
 * 
 * Copyright (c) 2010 Martin Wernståhl.
 * All rights reserved.
 */

'.$builder->__toString());
			
			// did the write work?
			if( ! $res)
			{
				// we need to tell the user that he needs to make the folder writable,
				// therefore he will know why it is slow
				trigger_error('RapidDataMapper: Cannot write to the "'.Rdm_Config::getMapperCacheDir().'" directory, using eval() instead.', E_USER_WARNING);
				
				// eval the code in case it didn't get written
				eval($builder->__toString());
			}
			else
			{
				require Rdm_Config::getMapperCacheDir().'/'.$class.'.php';
			}
		}
		else
		{
			eval($builder->__toString());
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * This is a fake exception printer, it will raise a fatal error with the
	 * exception formatted as the default PHP printer.
	 * 
	 * @param  Exception
	 * @return void
	 */
	protected static function triggerExceptionError($exception)
	{
		$message = 'Uncaught exception \''.get_class($exception).'\' with message \''.$exception->getMessage().'\' in '.$exception->getFile().'::'.$exception->getLine().'
Stack trace:
'.$exception->getTraceAsString().'
  thrown in '.$exception->getFile().' on line '.$exception->getLine().'
  faked';
		
		// Trigger the error
		trigger_error($message, E_USER_ERROR);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Registers a class name as a collection object, used by flush() to get all
	 * collections' unit of work objects.
	 * 
	 * @param  string
	 * @return void
	 */
	public static function registerCollectionClassName($class_name)
	{
		self::$loaded_collection_classes[] = $class_name;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Flushes all changes to the database.
	 * 
	 * @param  boolean  If to only flush the current collection
	 * @return void
	 */
	public static function flush($private_flush = false)
	{
		if($private_flush)
		{
			throw new Exception('Rdm_Collection::flush() with $private_flush = true is not implemented, call a subclass instead.');
		}
		else
		{
			// TODO: How to do with multiple database connections?
			$db = Rdm_Adapter::getInstance();
			$units = array();
			
			// Get the unit of works from the loaded collections
			foreach(self::$loaded_collection_classes as $c)
			{
				$units[] = call_user_func($c.'::getUnitOfWork');
			}
			
			// TODO: Sort the UnitOfWork instances so that inserts and updates are made in the correct order
			
			if($db->transactionInProgress())
			{
				// We already have a transaction, do not create another
				
				self::doFlushes($units);
			}
			else
			{
				// Nope, create a new local transaction
				try
				{
					$db->transactionStart();
					
					self::doFlushes($units);
					
					// Done!
					$db->transactionCommit();
				}
				catch(Exception $e)
				{
					// Oops, error, reset objects now
					foreach($units as $u)
					{
						$u->reset();
					}
					
					throw $e;
				}
			}
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Flushes the contents of the unit of works in the supplied list.
	 * 
	 * @param  array(Rdm_UnitOfWork)
	 * @return void
	 */
	public static function doFlushes(array $units)
	{
		// Send database calls
		foreach($units as $u)
		{
			$u->process();
		}
		
		// All done, now we clean up
		foreach($units as $u)
		{
			$u->cleanup();
		}
	}
	
	// ------------------------------------------------------------------------
	// --  CHILD INSTANCE CODE                                               --
	// ------------------------------------------------------------------------
	
	/**
	 * If this flag is true, this object has already been used with entity objects,
	 * therefore it can no longer use filters.
	 * 
	 * TODO: HOW TO CHANGE THIS FROM NESTED FILTERS/FILTER BY COLLECTIONS ?!?!?!
	 * 
	 * Needs to be public because the filter objects needs to be able to create a reference
	 * to this variable.
	 * 
	 * @var boolean
	 */
	public $is_locked = false;
	
	/**
	 * Flag which tells us if we've populated this object already.
	 * 
	 * @var boolean
	 */
	protected $is_populated = false;
	
	/**
	 * The alias of the current table.
	 * 
	 * @var string
	 */
	protected $table_alias = '';
	
	/**
	 * Internal: A list of filter objects
	 * 
	 * @var array(Collection_Filter)
	 */
	public $filters = array();
	
	/**
	 * Internal: A list of joined relation names and their collection objects.
	 * 
	 * @var array(string => Rdm_Collection)
	 */
	public $with = array();
	
	/**
	 * The array of data objects.
	 * 
	 * @var array(Object)
	 */
	protected $contents = array();
	
	/**
	 * The adapter instance used by this collection object.
	 * 
	 * @var Rdm_Adapter
	 */
	public $db = null;
	
	/**
	 * Parent collection when they are joined.
	 * 
	 * @var Rdm_Collection
	 */
	protected $parent = null;
	
	/**
	 * Internal: Reference to the relation filter employed by this collection
	 * if it is a sub-collection (ie. JOINed).
	 * 
	 * @var Rdm_Collection_FilterInterface
	 */
	public $relation = null;
	
	/**
	 * Internal: Relation id of the parent's relation with this collection.
	 * 
	 * @var int
	 */
	public $relation_id = null;
	
	/**
	 * Internal: An integer telling which type of relation this collection has with parent.
	 * 
	 * @var int
	 */
	public $join_type = null;
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Sets the unit of work instance to use for this collection.
	 * 
	 * @internal
	 * @param  Rdm_UnitOfWork
	 * @return void
	 */
	public static function setUnitOfWork(Rdm_UnitOfWork $u)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Returns the UnitOfWork instance used by this collection.
	 * 
	 * @internal
	 * @return Rdm_UnitOfWork
	 */
	public static function getUnitOfWork()
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new instance of this class.
	 * 
	 * @return Rdm_Collection
	 */
	public static function create()
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Will register the passed object as a persistent object in the database.
	 * 
	 * @param  Object
	 * @return Object	The object registered with this collection
	 */
	public static function persist($object)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Will register the passed object for deletion from the database.
	 * 
	 * @param  Object
	 * @return Object
	 */
	public static function delete($object)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------
	// --  FETCH RELATED METHODS                                             --
	// ------------------------------------------------------------------------
	
	/**
	 * 
	 * 
	 * @return 
	 */
	public function __construct($parent = null, $relation = null, $table_alias = '')
	{
		// TODO: Enable syntax like this: new TrackCollection($artist); where $artist owns a set of tracks
		
		if($parent)
		{
			$this->parent = $parent;
			$this->db = $parent->db;
			$this->is_locked =& $parent->is_locked;
			
			// The relationship type
			$this->relation = $relation;
			$this->relation_id = $relation->id;
			$this->join_type = $relation->type;
			// -1 is reserved for the relation filter
			$this->filters[-1] = $this->relation;
			
			// The alias the parent object tells us to use
			$this->table_alias = $table_alias;
		}
		else
		{
			// TODO: Move load of the adapter
			$this->db = Rdm_Adapter::getInstance();
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Clones the possible relation filter.
	 * 
	 * @return void
	 */
	public function __clone()
	{
		if( ! empty($this->relation))
		{
			$this->relation = clone $this->relation;
			// We need to fix the filter too
			$this->filters[-1] = $this->relation;
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns the parent collection object in case this collection was creted
	 * as a join to the first using with().
	 * 
	 * @return Rdm_Collection
	 */
	public function end()
	{
		return $this->parent;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Returns the relationship type which this collection has been joined with.
	 * 
	 * @internal
	 * @return int
	 */
	public function getJoinType()
	{
		return $this->join_type;
	}
	
	// ------------------------------------------------------------------------
	// --  SELECT QUERY RELATED METHODS                                      --
	// ------------------------------------------------------------------------

	/**
	 * Joins the relation with the supplied identifier.
	 * 
	 * @param  int  Integer from a class constant identifying the relation
	 * @return Rdm_Collection  <Class>Collection
	 */
	abstract public function with($relation_id);
	
	/**
	 * Internal: Creates the SELECT part of the query, does not include the SELECT keyword.
	 * 
	 * @internal
	 * @param  array   The list of columns, these will later be joined with ", " between them
	 * @param  array   A list to keep track of which column goes where, aliases are not
	 *                 used, so therefore storing the columns integer index is important.
	 *                 To add a column there, just add it at the end with
	 *                 $column_mappings[] = 'column';
	 * @return void
	 */
	abstract public function createSelectPart(&$list, &$column_mappings);
	
	/**
	 * Internal: Creates the FROM and JOIN part of the query, does not includes the FROM keyword.
	 * 
	 * @internal
	 * @param  string  The alias of the parent table, if this collection is joined onto another
	 *                 False if this is the root Collection object
	 * @param  array   The list of parts which is to be inserted into the space where
	 *                 the FROM clause will be, they will be joined with "\n" as the separator
	 * @return void
	 */
	abstract public function createFromPart($parent_alias, &$list);
	
	/**
	 * Internal: Hydrates the result row into objects.
	 * 
	 * @internal
	 * @param  array       The result row with integer indexed columns
	 * @param  array       The result array with primary keys as the keys
	 * @param  array       The column map which describes which column resides in which index
	 * @return void|false  False if there is no object to hydrate
	 */
	abstract public function hydrateObject(&$row, &$result, &$map);
	
	// ------------------------------------------------------------------------
	
	/**
	 * Creates a select query for this Collection object.
	 * 
	 * Can be used for debugging or dumping the SQL to see if the collection
	 * will generate the desired SQL.
	 * 
	 * @return string
	 */
	public function createSelectQuery()
	{
		$this->is_locked = true;
		
		$select = array();
		$from = array();
		$column_mappings = array();
		
		$this->createSelectPart($select, $column_mappings);
		$this->createFromPart(false, $from);
		
		$sql = 'SELECT '.implode(', ', $select)."\n".implode("\n", $from);
		
		if( ! empty($joins))
		{
			$sql .= "\n".implode("\n", $joins);
		}
		
		if( ! empty($this->filters))
		{
			$sql .= "\nWHERE ".implode(' ', $this->filters);
		}
		
		return array($sql, $column_mappings);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Fetches a reference to the contents of this Colleciton.
	 * 
	 * This is used to be able to directly assign subcollections' data.
	 * 
	 * @internal
	 * @return array
	 */
	public function &getContentReference()
	{
		return $this->contents;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Internal: Flags this collection as populated.
	 * 
	 * @internal
	 * @return void
	 */
	public function setPopulated()
	{
		$this->is_populated = true;
	}
	
	// ------------------------------------------------------------------------
	// --  FILTER RELATED METHODS                                            --
	// ------------------------------------------------------------------------
	
	/**
	 * Internal: Creates a new instance of the appropriate Rdm_Collection_Filter.
	 * 
	 * @internal
	 * @return Rdm_Collection_Filter
	 */
	abstract protected function createFilterInstance();
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new filter object which will be filtering the future contents
	 * of this collection, if there already is a filter, AND will be prepended.
	 * 
	 * @return Rdm_Collection_Filter
	 */
	public function has()
	{
		if($this->is_locked)
		{
			// TODO: Better exception message and proper exception class
			throw new Exception('Object is locked');
		}
		
		empty($this->filters) OR $this->filters[] = 'AND';
		
		return $this->filters[] = $this->createFilterInstance();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new filter object which will be filtering the future contents
	 * of this collection, if there already is a filter, OR will be prepended.
	 * 
	 * @return Rdm_Collection_Filter
	 */
	public function orHas()
	{
		if($this->is_locked)
		{
			// TODO: Better exception message and proper exception class
			throw new Exception('Object is locked');
		}
		
		empty($this->filters) OR $this->filters[] = 'OR';
		
		return $this->filters[] = $this->createFilterInstance();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function __toString()
	{
		// TODO: Replace or remove, this is currently for debug
		return $this->createSelectQuery();
	}
	
	// ------------------------------------------------------------------------
	// --  ENTITY RELATED METHODS                                            --
	// ------------------------------------------------------------------------
	
	/**
	 * Populates this object with entities which match the specified filters.
	 * 
	 * @return void
	 */
	public function populate()
	{
		if( ! is_null($this->parent))
		{
			throw Rdm_Collection_Exception::notRootObject(get_class($this));
		}
		
		$this->is_locked = true;
		
		list($sql, $map) = $this->createSelectQuery();
		
		// Flip so that the columns becomes the keys, faster column index lookup
		$map = array_flip($map);
		
		$result = $this->db->query($sql);
		
		while($row = $result->nextArray())
		{
			$this->hydrateObject($row, $this->contents, $map);
		}
		
		$this->is_populated = true;
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Converts the supplied entity to an XML fragment.
	 * 
	 * Format:
	 * <code>
	 * <singular>
	 *     <property>value</property>
	 * </singular>
	 * </code>
	 * 
	 * @param  object
	 * @return string
	 */
	public static function entityToXML($entity)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Converts this collection's data into XML.
	 * 
	 * Format:
	 * <code>
	 * <plural>
	 *     <singular>
	 *         <property>value</property>
	 *     </singular>
	 *     <singular>
	 *         <property>value</property>
	 *     </singular>
	 * </plural>
	 * </code>
	 * 
	 * @return string
	 */
	public function toXML()
	{
		// TODO: Code
	}
	
	// ------------------------------------------------------------------------
	
	/**
	 * Adds an entity to this collection, this collection will be locked and
	 * the entity will assume data which matches the filters of this collection.
	 * 
	 * @param  Object
	 * @return self
	 */
	public function add($object)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Contains the common instructions for add(), will perform the calls to
	 * filters and check the contents of the internal array.
	 * 
	 * @param  Object
	 * @return boolean  True if the object already is in the collection
	 */
	protected function _add($object)
	{
		$this->is_locked = true;
		
		// OR cannot decide what side of the filters we need to modify
		if(in_array('OR', $this->filters, true))
		{
			throw Rdm_Collection_Exception::filterCannotModify();
		}
		
		// Check if we already have it
		if($this->is_populated && in_array($object, $this->contents, true))
		{
			// Yes, we're done
			return true;
		}
		
		// Check that the subfilters doesn't contain anything simila
		foreach($this->filters as $filter)
		{
			if( ! $filter->canModifyToMatch())
			{
				throw Rdm_Collection_Exception::filterCannotModify();
			}
		}
		
		// Modify the object
		foreach($this->filters as $filter)
		{
			$filter->modifyToMatch($object);
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Removes the object from this collection, this collection will be locked
	 * and the entity's values will be set so that they no longer match filters.
	 * 
	 * @param  Object
	 * @return self
	 */
	public function remove($object)
	{
		// TODO: Code
		
		$this->is_locked = true;
		return $this;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Removes all objects from this collection, this collection will be locked
	 * and the entity's values will be set so that they no longer match filters.
	 * 
	 * @return int	Number of objects removed
	 */
	public function removeAll()
	{
		$num = 0;
		
		// TODO: Code
		
		$this->is_locked = false;
		return $num;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Deletes all objects matching this collection from the database, also locks
	 * this collection's filters.
	 * 
	 * @return int	Number of objects removed
	 */
	public function deleteAll()
	{
		$num = 0;
		
		// TODO: Code
		
		$this->is_locked = false;
		return $num;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns true if this collection is empty.
	 * 
	 * NOTE: PHP's empty() function will always return true because collections
	 * are objects.
	 * 
	 * @return boolean
	 */
	public function isEmpty()
	{
		// TODO: Use a COUNT() query instead of populate the object when it is empty
		$this->is_populated OR $this->populate();
		
		return empty($this->contents);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Returns an array of objects of this collection.
	 * 
	 * @return array
	 */
	public function toArray()
	{
		$this->is_populated OR $this->populate();
		
		return $this->contents;
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function offsetExists($offset)
	{
		$this->is_populated OR $this->populate();
		
		return isset($this->contents[$offset]);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function offsetGet($offset)
	{
		$this->is_populated OR $this->populate();
		
		return $this->contents[$offset];
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function offsetSet($offset, $value)
	{
		if(is_null($offset))
		{
			// $collection[] = $object; syntax
			$this->add($value);
		}
		else
		{
			// TODO: Implement?
			throw new Exception('Not yet implemented');
		}
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function offsetUnset($offset)
	{
		// TODO: Implement? Usable as a shortcut for remove()
		throw new Exception('Not yet implemented');
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function count()
	{
		// TODO: COUNT() query instead of populate if we haven't loaded objects?
		$this->is_populated OR $this->populate();
		
		return count($this->contents);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * 
	 * 
	 * @return 
	 */
	public function getIterator()
	{
		$this->is_populated OR $this->populate();
		
		return new ArrayIterator($this->contents);
	}
}


/* End of file Collection.php */
/* Location: ./lib/Rdm */