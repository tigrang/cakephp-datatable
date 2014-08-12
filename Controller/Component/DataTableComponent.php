<?php
App::uses('PaginatorComponent', 'Controller/Component');
/**
 * DataTable Component
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.Controller.Component
 * @author Tigran Gabrielyan
 */
class DataTableComponent extends PaginatorComponent {

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
 * - `scope` Scope of results to be searched and pagianated upon.
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
 * Model(s) to paginate
 *
 * @var string
 */
	public $paginate = null;

/**
 * Holds default settings
 *
 * @var array
 */
	protected $_defaults;

/**
 * Model instance
 *
 * @var Model
 */
	protected $_object;

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
 * Parsed settings by alias
 *
 * @var array
 */
	protected $_parsed = array();

/**
 * Constructor
 *
 * @param ComponentCollection $collection
 * @param array $settings
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, $settings);
		$this->_defaults = $this->settings;
	}

/**
 * Initialize this component
 *
 * @param Controller $controller
 * @return void
 */
	public function initialize(Controller $controller) {
		parent::initialize($controller);
		$property = $controller->request->is('get') ? 'query' : 'data';
		$this->_params = $controller->request->$property;
	}

/**
 * Runs main DataTable's server-side functionality
 *
 * @param Controller $controller
 * @return void
 */
	public function beforeRender(Controller $controller) {
		if (count($this->paginate)) {
			foreach((array)$this->paginate as $model) {
				$this->_parseSettings($model);
			}
		} else {
			$this->_parseSettings();
		}
		$this->Controller->set('dtColumns', $this->_columns);
		if ($this->isDataTableRequest()) {
			if (!isset($this->_params['model'])) {
				throw new Exception('DataTableComponent: Model not specified for request.');
			}
			$this->process($this->_params['model']);
		}
		$this->Controller->set('dataTabledModels', $this->paginate);
	}

/**
 * Main processing logic for DataTable
 *
 * @param mixed $object
 * @param mixed $scope
 */
	public function process($object = null, $scope = array()) {
		if (is_array($object)) {
			$scope = $object;
			$object = null;
		}
		$settings = $this->_parseSettings($object);
		if (isset($settings['scope'])) {
			$scope = array_merge($settings['scope'], $scope);
		}
		if (isset($settings['findType'])) {
			$settings['type'] = $settings['findType'];
		}
		$query = array_diff_key($settings,
			array(
				'conditions', 'columns', 'trigger', 'triggerAction',  'viewVar',  'maxLimit'
			)
		);
		$total = $this->_object->find('count', $query);

		$this->_sort($settings);
		$this->_search($settings);
		$this->_paginate($settings);
		$this->settings[$this->_object->alias] = $settings;

		$results = parent::paginate($this->_object, $scope);
		$totalDisplayed = $this->Controller->request->params['paging'][$this->_object->alias]['count'];
		$dataTableData = array(
			'iTotalRecords' => $total,
			'iTotalDisplayRecords' => $totalDisplayed,
			'sEcho' => isset($this->_params['sEcho']) ? intval($this->_params['sEcho']) : 0,
			'aaData' => array(),
		);

		$this->Controller->viewClass = 'DataTable.DataTableResponse';
		$this->Controller->set($settings['viewVar'], $results);
		$this->Controller->set(compact('dataTableData'));
		if (isset($settings['view'])) {
			$this->Controller->view = $settings['view'];
		}
	}

/**
 * Determines if the current request should be parsed as a DataTable request
 *
 * @return bool
 */
	public function isDataTableRequest() {
		$trigger = isset($this->settings['trigger']) ? $this->settings['trigger'] : $this->_defaults['trigger'];
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
 * @param string $object
 * @return array
 */
	protected function _parseSettings($object = null) {
		$this->_object = $this->_getObject($object);
		if (!is_object($this->_object)) {
			throw new MissingModelException($object);
		}
		$alias = $this->_object->alias;
		if (!isset($this->_parsed[$alias])) {
			$settings = $this->getDefaults($alias);
			$this->_columns[$alias] = array();
			foreach($settings['columns'] as $field => $options) {
				$useField = !is_null($options);
				$enabled = $useField && (!isset($options['useField']) || $options['useField']);
				if (is_numeric($field)) {
					$field = $options;
					$options = array();
				}
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
					'useField' => $useField,
					'label' => $label,
					'bSortable' => $enabled,
					'bSearchable' => $enabled,
				);
				$options = array_merge($defaults, (array)$options);
				$column = ($options['useField']) ? $this->_toColumn($alias, $field) : $field;
				$this->_columns[$alias][$column] = $options;
				if ($options['useField']) {
					$settings['fields'][] = $column;
				}
			}
			$this->_columnKeys[$alias] = array_keys($this->_columns[$alias]);
			$this->_parsed[$alias] = $settings;
			return $settings;
		}
		return $this->_parsed[$alias];
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
		if ($this->Controller->request->is('ajax')) {
			if (isset($this->settings['triggerAction'])) {
				$triggerAction = $this->settings['triggerAction'];
			} else {
				$triggerAction = $this->_defaults['triggerAction'];
			}
			$action = $this->Controller->request->params['action'];
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
 * @param array $settings
 * @return void
 */
	protected function _paginate(&$settings) {
		if (isset($this->_params['iDisplayLength']) && isset($this->_params['iDisplayStart'])) {
			$limit = $this->_params['iDisplayLength'];
			if ($limit > $settings['maxLimit']) {
				$limit = $settings['maxLimit'];
			}
			$settings['limit'] = $limit;
			$settings['offset'] = $this->_params['iDisplayStart'];;
		}
	}

/**
 * Adds conditions to filter results
 *
 * @param array $settings
 * @return void
 */
	protected function _search(&$settings) {
		$i = 0;
		$conditions = array();
		foreach($this->_columns[$this->_object->alias] as $column => $options) {
			if ($options['useField']) {
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
					if (is_string($searchable) && is_callable(array($this->_object, $searchable))) {
						$result = $this->_object->$searchable($column, $searchTerm, $columnSearchTerm, $conditions);
						if (is_array($result)) {
							$conditions = $result;
						}
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
			$current = array();
			if (isset($settings['conditions']['OR'])) {
				$current = array($settings['conditions']['OR']);
			}
			$settings['conditions']['OR'] = array_merge($current, $conditions);
		}
	}

/**
 * Sets sort field and direction
 *
 * @param array $settings
 * @return void
 */
	protected function _sort(&$settings) {
		for ($i = 0; $i < count($this->_columns[$this->_object->alias]); $i++) {
			$sortColKey = "iSortCol_$i";
			$sortDirKey = "sSortDir_$i";
			if (isset($this->_params[$sortColKey])) {
				$column = $this->_getColumnName($this->_params[$sortColKey]);
				if (!empty($column) && $this->_columns[$this->_object->alias][$column]['bSortable']) {
					$direction = isset($this->_params[$sortDirKey]) ? $this->_params[$sortDirKey] : 'asc';
					if (!in_array(strtolower($direction), array('asc', 'desc'))) {
						$direction = 'asc';
					}
					$settings['order'][$column] = $direction;
				}
			}
		}
	}

/**
 * Gets column name by index
 *
 * @param $index
 * @return string
 */
	protected function _getColumnName($index) {
		$alias = $this->_object->alias;
		if (!isset($this->_columnKeys[$alias][$index])) {
			return false;
		}
		return $this->_columnKeys[$alias][$index];
	}

/**
 * Converts field to Model.field
 *
 * @param string $object
 * @param string $field
 * @return string
 */
	protected function _toColumn($alias, $field) {
		if (strpos($field, '.') !== false) {
			return $field;
		}
		return $alias . '.' . $field;
	}

/**
 * Returns settings merged with defaults
 *
 * @param string $alias
 * @return array
 */
	public function getDefaults($alias) {
		return array_merge($this->_defaults, parent::getDefaults($alias));
	}

}
