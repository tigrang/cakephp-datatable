<?php
App::uses('Component', 'Controller/Component');
/**
 * DataTable Component
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
 * - `scope` Scope of results to be searched and pagianated upon.
 * - `viewVar` Name of the view var to set the results to. Defaults to dtResults.
 * - `maxLimit` The maximum limit users can choose to view. Defaults to 100
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.Controller.Component
 * @author Tigran Gabrielyan
 */
class DataTableComponent extends Component {

/**
 * Default values
 *
 * @var array
 */
	public $defaults = array(
		'columns' => array(),
		'maxLimit' => 100,
		'viewVar' => 'dtResults',
	);

/**
 * Constructor
 *
 * @param ComponentCollection $collection
 * @param array $settings
 */
	public function __construct(ComponentCollection $collection, $settings = array()) {
		parent::__construct($collection, $settings);
		$this->Controller = $collection->getController();
		if (isset($settings['default'])) {
			$this->defaults = array_merge($this->defaults, $settings['default']);
		}
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

		if (
			$controller->request->params['action'] === 'processDataTableRequest' &&
			!method_exists($controller, 'processDataTableRequest')
		) {
			$this->paginate($controller->request->query('config'));
			$controller->render()->send();
			exit;
		}
	}

/**
 * Sets view vars needed for the helper
 *
 * @param string $names
 * @return void
 */
	public function setViewVar($names) {
		$dtColumns = array();
		foreach ((array) $names as $name) {
			$config = $this->getConfig($name);
			$dtColumns[$name] = $config['columns'];
		}
		$this->Controller->set(compact('dtColumns'));
	}

/**
 * Main processing logic for DataTable
 *
 * @param mixed $object
 * @param mixed $scope
 */
	public function paginate($name, $scope = array()) {
		$config = $this->getConfig($name);
		$config['query']['conditions'] = array_merge((array) Hash::get($config['query'], 'conditions'), $scope);

		$Model = $this->_getModel($config['query']['model']);
		$iTotalRecords = $Model->find('count', $config['query']);

		$this->_sort($config);
		$this->_search($config, $Model);
		$iTotalDisplayRecords = $Model->find('count', $config['query']);

		$this->_paginate($config);

		$dataTableData = array(
			'iTotalRecords' => $iTotalRecords,
			'iTotalDisplayRecords' => $iTotalDisplayRecords,
			'sEcho' => (int) Hash::get($this->_params, 'sEcho'),
			'aaData' => array(),
		);

		$this->Controller->viewClass = 'DataTable.DataTableResponse';
		$this->Controller->view = Hash::get($config, 'view') ?: $name;
		$this->Controller->set($config['query']['viewVar'], $Model->find('all', $config['query']));
		$this->Controller->set(compact('dataTableData'));
	}

/**
 * Returns normalized config
 *
 * @param string $name
 * @return array
 */
	public function getConfig($name) {
		if ($name === null) {
			$name = $this->Controller->request->params['action'];
		}

		if (!isset($this->settings['config'][$name])) {
			throw new Exception(sprintf('%s: Missing config %s', __CLASS__, $name));
		}

		$query = array_merge($this->defaults, $this->settings['config'][$name]);
		if (!isset($query['model'])) {
			$query['model'] = $name;
		}

		$columns = array();
		foreach($query['columns'] as $field => $options) {
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
			$options = array_merge($defaults, (array) $options);
			$column = ($options['useField']) ? $this->_toColumn($query['model'], $field) : $field;
			$columns[$column] = $options;
			if ($options['useField']) {
				$query['fields'][] = $column;
			}
		}
		unset($query['columns']);
		return compact('query', 'columns');
	}

/**
 * Sets pagination limit and page
 *
 * @param array $config
 * @return void
 */
	protected function _paginate(&$config) {
		if (!isset($this->_params['iDisplayLength'], $this->_params['iDisplayStart'])) {
			return;
		}

		$config['query']['limit'] = min($this->_params['iDisplayLength'], $config['query']['maxLimit']);
		$config['query']['offset'] = $this->_params['iDisplayStart'];
	}

/**
 * Adds conditions to filter results
 *
 * @param array $config
 * @return void
 */
	protected function _search(&$config, $Model) {
		$i = 0;
		$conditions = array();
		foreach($config['columns'] as $column => $options) {
			if ($options['useField']) {
				$searchable = $options['bSearchable'];
				if ($searchable === false) {
					continue;
				}
				$searchKey = "sSearch_$i";
				$searchTerm = Hash::get($this->_params, 'sSearch');
				$columnSearchTerm = Hash::get($this->_params, $searchKey);

				if ($searchable === true) {
					if ($searchTerm) {
						$conditions[] = array("$column LIKE" => '%' . $searchTerm . '%');
					}
					if ($columnSearchTerm) {
						$conditions[] = array("$column LIKE" => '%' . $columnSearchTerm . '%');
					}
				} else if (is_callable(array($Model, $searchable))) {
					$Model->$searchable($column, $searchTerm, $columnSearchTerm, $conditions);
				}
			}
			$i++;
		}
		if (!empty($conditions)) {
			$config['query']['conditions'][]['OR'] = $conditions;
		}
	}

/**
 * Sets sort field and direction
 *
 * @param array $config
 * @return void
 */
	protected function _sort(&$config) {
		for ($i = 0; $i < count($config['columns']); $i++) {
			$sortColKey = "iSortCol_$i";
			if (!isset($this->_params[$sortColKey])) {
				continue;
			}

			$column = Hash::get(array_keys($config['columns']), $this->_params[$sortColKey]);
			if (!$column || !$config['columns'][$column]['bSortable']) {
				continue;
			}

			$direction = Hash::get($this->_params, "sSortDir_$i") ?: 'asc';
			$config['query']['order'][$column] = in_array(strtolower($direction), array('asc', 'desc')) ? $direction : 'asc';
		}
	}

/**
 * Converts field to Model.field
 *
 * @param string $object
 * @param string $field
 * @return string
 */
	protected function _toColumn($alias, $field) {
		return (strpos($field, '.') !== false) ? $field : $alias . '.' . $field;
	}

/**
 * Gets the model to be paginated
 *
 * @param string $model Name of the model to load
 * @return Model
 */
	protected function _getModel($model) {
		$this->Controller->loadModel($model);
		return $this->Controller->$model;
	}
}