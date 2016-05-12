<?php

namespace ByJG\Authenticate;

use ByJG\AnyDataset\Repository\DBDataset;
use PHPUnit_Framework_TestCase;

/**
 * Created by PhpStorm.
 * User: jg
 * Date: 24/04/16
 * Time: 20:21
 */
class UsersDBDatasetTest extends UsersAnyDatasetTest
{
    /**
     * @var UsersDBDataset
     */
    protected $object;

    public function setUp()
    {
        $this->prefix = "";

        $db = new DBDataset('sqlite:///tmp/teste.db');
        $db->execSQL('create table users (
            userid integer primary key  autoincrement, 
            name varchar(45), 
            email varchar(200), 
            username varchar(20), 
            password varchar(40), 
            created datetime, 
            admin char(1));'
        );

        $db->execSQL('create table users_property (
            customid integer primary key  autoincrement, 
            userid integer, 
            name varchar(45), 
            value varchar(45));'
        );

        $this->object = new UsersDBDataset('sqlite:///tmp/teste.db');

        $this->object->addUser('User 1', 'user1', 'user1@gmail.com', 'pwd1');
        $this->object->addUser('User 2', 'user2', 'user2@gmail.com', 'pwd2');
        $this->object->addUser('User 3', 'user3', 'user3@gmail.com', 'pwd3');
    }

    public function tearDown()
    {
        unlink('/tmp/teste.db');
    }

    public function testAddUser()
    {
        $this->object->addUser('John Doe', 'john', 'johndoe@gmail.com', 'mypassword');

        $user = $this->object->getByUsername('john');
        $this->assertEquals('4', $user->getField($this->object->getUserTable()->id));
        $this->assertEquals('John Doe', $user->getField($this->object->getUserTable()->name));
        $this->assertEquals('john', $user->getField($this->object->getUserTable()->username));
        $this->assertEquals('johndoe@gmail.com', $user->getField($this->object->getUserTable()->email));
        $this->assertEquals('91DFD9DDB4198AFFC5C194CD8CE6D338FDE470E2', $user->getField($this->object->getUserTable()->password));
    }

}