<?php
App::uses('Controller', 'Controller');
App::uses('Model', 'Model');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('DataTableComponent', 'DataTable.Controller/Component');

require_once CAKE . 'Test' . DS . 'Case' . DS . 'Model' . DS . 'models.php';

/**
 * DataTable Component Test
 */
class DataTableComponentTest extends CakeTestCase {
/**
 * fixtures
 *
 * @var array
 */
	public $fixtures = array(
		'core.article',
		'core.articles_tag',
		'core.comment',
		'core.tag',
		'core.user',
	);
/**
 * setUp
 *
 * @return void
 */
	public function setUp() {
		parent::setUp();
		putenv('REQUEST_METHOD=GET');
		$settings = array(
			'columns' => array(
				'title' => 'Title',
				'user_id' => 'User',
			),
		);
		$Collection = new ComponentCollection();
		$this->DataTable = new DataTableComponent($Collection, $settings);
		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$CakeRequest->query = array(
			'sEcho' => 1,
		);
		$this->Controller = new Controller($CakeRequest, $CakeResponse);
		$this->Controller->modelClass = 'Article';
		$this->Controller->loadModel('Article');
		$this->DataTable->initialize($this->Controller);
    }

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->DataTable);
		unset($this->Controller);
	}

/**
 * testProcess
 */
	public function testProcess() {
		$this->DataTable->process();
		$this->assertEquals('DataTable.DataTableResponse', $this->Controller->viewClass);
		$this->assertEquals(1, $this->Controller->viewVars['dataTableData']['sEcho']);
	}
}