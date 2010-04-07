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
		
		// Build the new class
		$builder = $desc->getBuilder();
		
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
			
			try
			{
				$db->transactionStart();
				
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
	 * A list of filter objects
	 * 
	 * @var array(Collection_Filter)
	 */
	protected $filters = array();
	
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
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the unit of work instance to use for this collection.
	 * 
	 * @param  Rdm_UnitOfWork
	 * @return void
	 */
	public static function setUnitOfWork(Rdm_UnitOfWork $u)
	{
		throw Rdm_Collection_Exception::missingMethod(__CLASS__.'::'.__METHOD__);
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the unit of work instance to use for this collection.
	 * 
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
	public function __construct()
	{
		// TODO: Enable syntax like this: new TrackCollection($artist); where $artist owns a set of tracks
		
		// TODO: Move load of the adapter
		$this->db = Rdm_Adapter::getInstance();
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Joins the relation with the supplied identifier.
	 * 
	 * @param  int  Integer from a class constant identifying the relation
	 * @return self
	 */
	//abstract public function with($relation_name);
	
	// ------------------------------------------------------------------------
	// --  FILTER RELATED METHODS                                            --
	// ------------------------------------------------------------------------
	
	/**
	 * Creates a new instance of the appropriate Rdm_Collection_Filter.
	 * 
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
		return implode(' ', $this->filters);
	}
	
	// ------------------------------------------------------------------------
	// --  ENTITY RELATED METHODS                                            --
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
		// TODO: Code
		
		$this->is_locked = true;
		return $this;
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
	 * This method should populate this object with data in respect to the $filters parameter.
	 * 
	 * !!! ATTENTION:
	 * 
	 * THIS METHOD MUST SET THE LOCAL INSTANCE VARIABLES is_populated AND is_locked TO true!!!
	 * 
	 * @return void
	 */
	public abstract function populate();
	
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
		// TODO: Implement?
		// $collection[] = value; gives a call with (null, value), can be usable as a shortcut for add()
		throw new Exception('Not yet implemented');
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