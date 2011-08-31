<?php
/*
 * Created by Martin Wernståhl on 2009-08-07.
 * Copyright (c) 2009 Martin Wernståhl.
 * All rights reserved.
 */

/**
 * @covers Rdb
 * @runTestsInSeparateProcesses enabled
 * @preserveGlobalState disabled
 */
class RdbTest extends PHPUnit_Framework_TestCase
{

	public $preserveGlobalState = false;

	public function setUp()
	{
		require_once dirname(__FILE__).'/../lib/Rdb.php';

		Rdb::initAutoload();
	}

	// ------------------------------------------------------------------------

	/**
	 * Instantiation of Rdb class is not allowed.
	 */
	public function testClassInstantiation()
	{
		$reflection = new ReflectionClass('Rdb');

		$sum = false;

		$sum = ($sum OR $reflection->isAbstract());

		$c = $reflection->getConstructor();

		$sum = ($sum OR $c->isPrivate() OR $c->isProtected());

		$this->assertTrue($sum);
	}

	// ------------------------------------------------------------------------

	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testNoConnection()
	{
		Rdb::getConnection();
	}
	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testNoConnection2()
	{
		Rdb::setConnectionConfig('foobar', array('something' => 'to satisfy test'));

		Rdb::getConnection();
	}
	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testSetConnectionConfigInvalid()
	{
		Rdb::setConnectionConfig('testing');
	}
	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testSetConnectionConfigInvalid2()
	{
		Rdb::setConnectionConfig(null);
	}
	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testSetConnectionConfigInvalid3()
	{
		Rdb::setConnectionConfig('test', array());
	}

	public function testGetDefaultConnection()
	{
		Rdb::setConnectionConfig('default', array('dbdriver' => 'mock'));

		eval('class Db_Driver_Mock_Connection {}');

		$this->assertTrue(Rdb::getConnection() instanceof Db_Driver_Mock_Connection);
	}
	/**
	 * @expectedException Db_Connection_ConfigurationException
	 */
	public function testSetDefaultConnectionName()
	{
		Rdb::setConnectionConfig('default', array('something' => 'to satisfy test'));

		Rdb::setDefaultConnectionName('foobar');

		Rdb::getConnection();
	}

	public function testSetConnectionConfig()
	{
		Rdb::setConnectionConfig(array());
	}

	public function testGetConnection()
	{
		Rdb::setConnectionConfig(array('foobar' => array('dbdriver' => 'mock')));

		// mock class to fetch the options passed to the constructor
		eval('class Db_Driver_Mock_Connection
		{
			protected $params;
			public function __construct()
				{ $this->params = func_get_args(); }
			public function getParams()
				{ return $this->params; }
		}');

		$c = Rdb::getConnection('foobar');

		$this->assertEquals(array('foobar', array('dbdriver' => 'mock')), $c->getParams());

		$this->assertSame($c, Rdb::getConnection('foobar'));
	}

	/**
	 * @covers Rdb::isChanged
	 */
	public function testIsChanged()
	{
		$this->initIsChangedTest();

		$obj = new stdClass();

		// empty __id returns true
		$this->assertTrue(Rdb::isChanged($obj));

		// id makes it return false if __data cannot be found
		$obj->__id = array('id' => 3);
		$this->assertFalse(Rdb::isChanged($obj));

		$obj->__data = array('ctitle' => 'Foo', 'cslug' => 'Bar');

		// we have a reference, so it should be modified
		$this->assertTrue(Rdb::isChanged($obj));

		// still modified after we've added one of the columns
		$obj->title = 'Foo';
		$this->assertTrue(Rdb::isChanged($obj));

		// we're back to full
		$obj->slug = 'Bar';
		$this->assertFalse(Rdb::isChanged($obj));

		// add additional
		$obj->someother = 'Something';
		$this->assertFalse(Rdb::isChanged($obj));

		$obj->title = 'foo';
		$this->assertTrue(Rdb::isChanged($obj));

		$obj->slug = 'Bar2';
		$this->assertTrue(Rdb::isChanged($obj));
	}
	/**
	 * @covers Rdb::isChanged
	 */
	public function testIsChangedProperty()
	{
		$this->initIsChangedTest();

		$obj = new stdClass();

		// empty __id returns true
		$this->assertTrue(Rdb::isChanged($obj, 'title'));

		// id makes it return false if __data cannot be found
		$obj->__id = array('id' => 3);
		$this->assertFalse(Rdb::isChanged($obj, 'title'));

		$obj->__data = array('ctitle' => 'Foo', 'cslug' => 'Bar');

		// we have a reference, so it should be modified
		$this->assertTrue(Rdb::isChanged($obj, 'title'));

		// still modified after we've added one of the columns
		$obj->title = 'Foo';
		$this->assertFalse(Rdb::isChanged($obj, 'title'));

		// we're back to full
		$obj->slug = 'Bar';
		$this->assertFalse(Rdb::isChanged($obj, 'title'));

		// add additional
		$obj->someother = 'Something';
		$this->assertFalse(Rdb::isChanged($obj, 'title'));

		$obj->title = 'foo';
		$this->assertTrue(Rdb::isChanged($obj, 'title'));

		$obj->slug = 'Bar2';
		$this->assertTrue(Rdb::isChanged($obj, 'title'));

		$this->assertFalse(Rdb::isChanged($obj, 'someother'));
	}

	// ------------------------------------------------------------------------

	/**
	 * Initializes a mocked mapper class for use with the isChanged() tests.
	 *
	 * @return void
	 */
	public function initIsChangedTest()
	{
		if( ! class_exists('Db_Compiled_stdClassMapper'))
		{
			eval("class Db_Compiled_stdClassMapper
			{
				public \$properties = array(
					'title' => 'ctitle',
					'slug' => 'cslug'
					);
			}");
		}
	}
}


/* End of file RdbTest.php */
/* Location: ./tests */