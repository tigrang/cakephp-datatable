<?php
App::uses('Controller', 'Controller');
App::uses('Model', 'Model');
App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ComponentCollection', 'Controller');
App::uses('DataTableComponent', 'DataTable.Controller/Component');

require_once CAKE . 'Test' . DS . 'Case' . DS . 'Model' . DS . 'models.php';

/**
 * TestDataTableController
 */
class TestDataTableController extends Controller {
	public $uses = array('Article');
	public $components = array(
		'Paginator' => array(
			'className' => 'DataTable.DataTable',
		),
	);
}

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
		$CakeRequest = new CakeRequest();
		$CakeResponse = new CakeResponse();
		$CakeRequest->query = array('sEcho' => 1);
		$this->Controller = new TestDataTableController($CakeRequest, $CakeResponse);
		$this->Controller->modelClass = 'Article';
		$this->Controller->Components->init($this->Controller);
		$this->Controller->Paginator->initialize($this->Controller);
    }

/**
 * tearDown
 *
 * @return void
 */
	public function tearDown() {
		parent::tearDown();
		unset($this->Controller);
	}

/**
 * testPaginate
 */
	public function testPaginate() {
		$this->Controller->paginate();
		$result = $this->Controller->viewVars;
		$this->assertEquals(3, $result['dataTableData']['iTotalRecords']);
		$this->assertEquals(3, $result['dataTableData']['iTotalDisplayRecords']);
		$this->assertTrue(!empty($result['dtResults']));
		$this->assertEquals('DataTable.DataTableResponse', $this->Controller->viewClass);
	}

}