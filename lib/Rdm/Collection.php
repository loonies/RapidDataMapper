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
	///////////////////////////////////////////////////////////////////////////
	//  CHILD INSTANCE MANAGEMENT RELATED METHODS                            //
	///////////////////////////////////////////////////////////////////////////
	
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
	 * Flushes all changes to the database.
	 * 
	 * @return void
	 */
	public static function flush()
	{
		// TODO: Code
	}
	
	///////////////////////////////////////////////////////////////////////////
	//  CHILD INSTANCE CODE                                                  //
	///////////////////////////////////////////////////////////////////////////
	
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
	
	// ------------------------------------------------------------------------

	/**
	 * Sets the unit of work instance to use for this collection.
	 * 
	 * @param  Rdm_UnitOfWork
	 * @return void
	 */
	public static function setUnitOfWork(Rdm_UnitOfWork $u)
	{
		throw new Exception('This method (Rdm_Collection::setUnitOfWork()) has not been implemented. It should be implemented in child classes.');
	}
	
	// ------------------------------------------------------------------------

	/**
	 * Creates a new instance of this class.
	 * 
	 * @return Rdm_Collection
	 */
	public static function create()
	{
		throw new Exception('This method (Rdm_Collection::create()) has not been implemented. It should be implemented in child classes.');
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
		throw new Exception('This method (Rdm_Collection::persist()) has not been implemented. It should be implemented in child classes.');
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
		throw new Exception('This method (Rdm_Collection::delete()) has not been implemented. It should be implemented in child classes.');
	}
	
	///////////////////////////////////////////////////////////////////////////
	//  FILTER RELATED METHODS                                               //
	///////////////////////////////////////////////////////////////////////////
	
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
	
	///////////////////////////////////////////////////////////////////////////
	//  ENTITY RELATED METHODS                                               //
	///////////////////////////////////////////////////////////////////////////
	
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