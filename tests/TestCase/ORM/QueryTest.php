<?php
/**
 * Copyright 2016 Maicon Amarante
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2016 Maicon Amarante
 * @license MIT License (http://www.opensource.org/licenses/mit-license.php)
 */
namespace CakephpFirebird\Test\TestCase\Database;
use Cake\Datasource\ConnectionManager;
use Cake\TestSuite\TestCase;
use Cake\ORM\TableRegistry;
/**
 * Tests Query class
 *
 */
class QueryTest extends TestCase
{
    public $connection = 'test_cakephpfirebird';

    /**
     * Test subject
     *
     * @var \Agricola\Model\Table\EscolasTable
     */
    public $Articles;

    /**
     * Fixtures
     *
     * @var array
     */
    public $fixtures = [
        'plugin.cakephp_firebird.articles'
    ];

    /**
     * setUp method
     *
     * @return void
     */
    public function setUp()
    {
        $connection = ConnectionManager::get('test_cakephpfirebird');
        $config = ['connection' => $connection];
//        $this->Articles = new Table($config);
        parent::setUp();
        $this->Articles = TableRegistry::get('Articles', $config);
    }


    /**
     * tearDown method
     *
     * @return void
     */
    public function tearDown()
    {
        unset($this->Articles);

        parent::tearDown();
    }

    /**
     * @inheritDoc
     */
    public function testSelectAll()
    {
        $query = $this->Articles->find('all');
        $this->assertInstanceOf('Cake\ORM\Query', $query);
        $result = $query->hydrate(false)->toArray();
        $expected = [
            ['id' => 1, 'author_id' => 1, 'title' => 'First Article', 'body' => 'First Article Body', 'published' => 'Y'],
        ];

        $this->assertEquals($expected, $result);
    }

}