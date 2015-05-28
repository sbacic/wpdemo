<?php

use Codeception\Util\Stub;

class GeneratorTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testInstanceCounter()
    {
        $object = new \WPDemo\Generator();
        $count  = $object->countInstances();

        $this->assertEquals(0, $count);
    }

    public function testCreateInstance()
    {
        $object = new \WPDemo\Generator();
        $object->instantiateSession();
        $count  = $object->countInstances();
        $this->assertEquals(1, $count);
    }

    public function testRemoveInstances()
    {
        $object = new \WPDemo\Generator();
        $object->instantiateSession();

        $count  = $object->countInstances();
        $this->assertEquals(1, $count);

        sleep(1); //We need to wait here to make sure MySQL has enough time to create the tables

        $object->destroyInstances(0,
                $object->getConfig()->instancePrefix,
                $object->getConfig()->cloneDir . '/' . $object->getConfig()->instancePrefix);

        $count  = $object->countInstances();
        $this->assertEquals(0, $count);
    }

    public function testInstanceLimit()
    {
        $object = Stub::construct('\WPDemo\Generator', array(), array(
            'getConfig' => function () { 
            $config        = new \WPDemo\Config();
            $config->limit = 0;
            return $config;
            }
            ));

        \PHPUnit_Framework_TestCase::setExpectedException('\Exception');

        $object->instantiateSession();
    }

    public function testCreateMultipleInstances()
    {
        $object = new \WPDemo\Generator();
        $object->instantiateSession();
        $_SESSION = array();
        $object->instantiateSession();
        $_SESSION = array();
        $object->instantiateSession();
        $_SESSION = array();
        $object->instantiateSession();
        $_SESSION = array();
        $count  = $object->countInstances();
        $this->assertEquals(4, $count);
    }

}