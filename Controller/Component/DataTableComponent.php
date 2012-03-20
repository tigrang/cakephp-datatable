<?php
App::uses('Component', 'Controller');

/**
 * DataTable Component
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.Controller.Component
 * @author Tigran Gabrielyan
 */
class DataTableComponent extends Component {

/**
 * @var Controller
 */
	public $Controller;

/**
 * @var CakeRequest
 */
	public $request;

/**
 * Model query to be built
 *
 * @var array
 */
	public $query = array();

/**
 * Parsed column config
 *
 * @var array
 */
	protected $_columns = array();

/**
 * Column keys to be accessed via index for sorting columns
 *
 * @var array
 */
	protected $_columnKeys = array();

/**
 * DataTable request params
 *
 * @var array
 */
	protected $_params = array();

/**
 * Model
 *
 * @var Model
 */
	protected $_Model;

/**
 * Settings
 *
 * Available options:
 * - `columns` Defines column aliases and settings for sorting and searching.
 *   The keys will be fields to use and by default the label via Inflector::humanize()
 *   If the value is a string, it will be used as the label.
 *   If the value is false, bSortable and bSearchable will be set to false.
 *   If the value is null, the column will not be tied to a field and the key will be used for the label
 *   If the value is an array, it takes on the possible options:
 *    - `label` Label for the column
 *    - `bSearchable` A string may be passed instead which will be used as a model callback for extra user-defined
 *       processing
 *    - All of the DataTable column options, see @link http://datatables.net/usage/columns
 * - `trigger` True will use default check, string will use custom controller callback. Defaults to true.
 * - `triggerAction` Action to check against if default trigger check is used.
 *   Can be wildcard '*', single action, or array of actions. Defaults to index.
 * - `viewVar` Name of the view var to set the results to. Defaults to dtResults.
 * - `maxLimit` The maximum limit users can choose to view. Defaults to 100
 *
 * @var array
 */
	public $settings = array(
		'columns' => array(),
		'trigger' => true,
		'triggerAction' => 'index',
		'viewVar' => 'dtResults',
		'maxLimit' => 100,
	);

/**
 * Constructor
 *
 * @param ComponentCollection $collection
 * @param array $settings
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, array_merge($this->settings, $settings));
	}

/**
 * Initialize this component
 *
 * @param Controller $controller
 * @return void
 */
	public function initialize(Controller $controller) {
		$this->Controller = $controller;
		$this->request = $controller->request;
		$this->_Model = $this->_getModel();

		if ($this->request->is('get')) {
			$this->_params = $this->request->query;
		} else if ($this->request->is('post')) {
			$this->_params = $this->request->data;
		}

		if (isset($this->Controller->paginate[$this->_Model->alias])) {
			$this->query = $this->Controller->paginate[$this->_Model->alias];
		}

		$this->_parseSettings();
		$this->_columnKeys = array_keys($this->_columns);
	}

/**
 * Runs main DataTable's server-side functionality
 *
 * @param Controller $controller
 * @return void
 */
	public function beforeRender(Controller $controller) {
		$this->Controller->set('dtColumns', $this->_columns);
		if ($this->isDataTableRequest()) {
			$this->process();
		}
	}

/**
 * Main processing logic for DataTables
 *
 * @return void
 */
	public function process() {
		$this->_sort();
		$total = $this->_Model->find('count', $this->query);
		$this->_search();
		$totalDisplayed = $this->_Model->find('count', $this->query);
		$this->_paginate();
		$results = $this->_Model->find('all', $this->query);

		$dataTableData = array(
			'iTotalRecords' => $total,
			'iTotalDisplayRecords' => $totalDisplayed,
			'sEcho' => isset($this->_params['sEcho']) ? intval($this->_params['sEcho']) : 0,
			'aaData' => array(),
		);

		$this->Controller->viewClass = 'DataTable.DataTableResponse';
		$this->Controller->set($this->settings['viewVar'], $results);
		$this->Controller->set(compact('dataTableData'));
	}

/**
 * Determines if the current request should be parsed as a DataTable request
 *
 * @return bool
 */
	public function isDataTableRequest() {
		$trigger = $this->settings['trigger'];
		if ($trigger === true) {
			return $this->_isDataTableRequest();
		}
		if (is_string($trigger) && is_callable(array($this->Controller, $trigger))) {
			return $this->Controller->{$trigger}();
		}
		return false;
	}

/**
 * Parses settings
 *
 * @return void
 */
	protected function _parseSettings() {
		foreach($this->settings['columns'] as $field => $options) {
			if (is_null($options)) {
				$this->_columns[$field] = null;
				continue;
			}
			if (is_numeric($field)) {
				$field = $options;
				$options = array();
			}
			$enabled = true;
			if (is_bool($options)) {
				$enabled = $options;
				$options = array();
			}
			$label = Inflector::humanize($field);
			if (is_string($options)) {
				$label = $options;
				$options = array();
			}
			$defaults = array(
				'label' => $label,
				'bSortable' => $enabled,
				'bSearchable' => $enabled,
			);
			$column = $this->toColumn($field);
			$this->_columns[$column] = array_merge($defaults, $options);
			$this->query['fields'][] = $column;
		}
	}

/**
 * Default check to decide whether or not to process
 * This method will check if the configured action and requested action are the same
 * and if the current request is an ajax request. This can be configured via the `trigger`
 * setting to a string. If the controller has a method with the name of the string, it will
 * call that method to check whether to process or not.
 *
 * @return bool
 */
	protected function _isDataTableRequest() {
		if ($this->request->is('ajax')) {
			$triggerAction = $this->settings['triggerAction'];
			$action = $this->request->params['action'];
			if ($triggerAction === '*' || $triggerAction == $action) {
				return true;
			}
			if (is_array($triggerAction)) {
				return in_array($action, $triggerAction);
			}
		}
		return false;
	}

/**
 * Sets pagination limit and page
 *
 * @return void
 */
	protected function _paginate() {
		if (isset($this->_params['iDisplayLength']) && isset($this->_params['iDisplayStart'])) {
			$limit = $this->_params['iDisplayLength'];
			if ($limit > $this->settings['maxLimit']) {
				$limit = $this->settings['maxLimit'];
			}
			$this->query['limit'] = $limit;
			$this->query['offset'] = $this->_params['iDisplayStart'];;
		}
	}

/**
 * Adds conditions to filter results
 *
 * @return void
 */
	protected function _search() {
		$i = 0;
		$conditions = array();
		foreach($this->_columns as $column => $options) {
			if ($options !== null) {
				$searchable = $options['bSearchable'];
				if ($searchable !== false) {
					$searchKey = "sSearch_$i";
					$searchTerm = $columnSearchTerm = null;
					if (!empty($this->_params['sSearch'])) {
						$searchTerm = $this->_params['sSearch'];
					}
					if (!empty($this->_params[$searchKey])) {
						$columnSearchTerm = $this->_params[$searchKey];
					}
					if (is_string($searchable) && is_callable(array($this->_Model, $searchable))) {
						$this->_Model->$searchable($column, $searchTerm, $columnSearchTerm, &$conditions);
					} else {
						if ($searchTerm) {
							$conditions[] = array("$column LIKE" => '%' . $this->_params['sSearch'] . '%');
						}
						if ($columnSearchTerm) {
							$conditions[] = array("$column LIKE" => '%' . $this->_params[$searchKey] . '%');
						}
					}
				}
			}
			$i++;
		}
		if (!empty($conditions)) {
			$this->query['conditions']['OR'] = $conditions;
		}
	}

/**
 * Sets sort field and direction
 *
 * @return void
 */
	protected function _sort() {
		for ($i = 0; $i < count($this->_columns); $i++) {
			$sortColKey = "iSortCol_$i";
			$sortDirKey = "sSortDir_$i";
			if (isset($this->_params[$sortColKey])) {
				$column = $this->_getColumnName($this->_params[$sortColKey]);
				if (!empty($column) && $this->_columns[$column]['bSortable']) {
					$direction = isset($this->_params[$sortDirKey]) ? $this->_params[$sortDirKey] : 'asc';
					if (!in_array(strtolower($direction), array('asc', 'desc'))) {
						$direction = 'asc';
					}
					$this->query['order'][] = $column . ' ' . $direction;
				}
			}
		}
	}

/**
 * Gets the model
 *
 * @return Model
 */
	protected function _getModel() {
		// TODO: define by setting value, fallback to modelClass
		return $this->Controller->{$this->Controller->modelClass};
	}

/**
 * Gets column name by index
 *
 * @param $index
 * @return string
 */
	protected function _getColumnName($index) {
		if (!isset($this->_columnKeys[$index])) {
			return false;
		}
		return $this->_columnKeys[$index];
	}

/**
 * Converts field to Model.field
 *
 * @param $field
 * @return string
 */
	protected function toColumn($field) {
		if (strpos($field, '.') !== false) {
			return $field;
		}
		return $this->_Model->alias . '.' . $field;
	}

}