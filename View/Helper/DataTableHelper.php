<?php
App::uses('AppHelper', 'View/Helper');

/**
 * DataTable Helper
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.View.Helper
 * @author Tigran Gabrielyan
 *
 * @property HtmlHelper $Html
 */
class DataTableHelper extends AppHelper {

/**
 * Helpers
 *
 * @var array
 */
	public $helpers = array(
		'Html'
	);

/**
 * Table header labels
 *
 * @var array
 */
	public $labels = array();

/**
 * Column options for aoColumns
 *
 * @var array
 */
	public $columns = array();

/**
 * Settings
 *
 * @var array
 */
	public $settings = array(
		'table' => array(
			'class' => 'datatable',
			'trOptions' => array(),
			'thOptions' => array(),
			'theadOptions' => array(),
			'tbody' => '',
			'tbodyOptions' => array(),
		),
		'script' => array(
			'inline' => false,
			'block' => 'script',
		),
		'js' => array(
		)
	);

/**
 * Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);

		$this->_parseSettings();
		$this->settings = array_merge($this->settings, $settings);
	}

/**
 * Sets label at the given index.
 *
 * @param int $index of column to change
 * @param string $label new label to be set. `__LABEL__` string will be replaced by the original label
 * @return bool true if set, false otherwise
 */
	public function setLabel($index, $label) {
		if (!isset($this->labels[$index])) {
			return false;
		}
		$this->labels[$index] = str_replace('__LABEL__', $this->labels[$index], $label);
		return true;
	}

/**
 * Renders a DataTable
 *
 * Options take on the following values:
 * - `class` For table. Default: `datatable`
 * - `trOptions` Array of options for tr
 * - `thOptions` Array of options for th
 * - `theadOptions` Array of options for thead
 * - `tbody` Content for tbody
 * - `tbodyOptions` Array of options for tbody
 *
 * The rest of the keys wil be passed as options for the table
 *
 * @param array $options
 * @param mixed $script Array of settings for script block
 *                      If false, automatic adding of script block will be disabled
 *                      If string, block name will be set
 * @param array $js
 * @return string
 */
	public function render($options = array(), $script = array(), $js = array()) {
		$options = array_merge($this->settings['table'], $options);

		$trOptions = $options['trOptions'];
		$thOptions = $options['thOptions'];
		unset($options['trOptions'], $options['thOptions']);

		$theadOptions = $options['theadOptions'];
		$tbodyOptions = $options['tbodyOptions'];
		unset($options['theadOptions'], $options['tbodyOptions']);

		$tbody = $options['tbody'];
		unset($options['thead'], $options['tbody']);

		$tableHeaders = $this->Html->tableHeaders($this->labels, $trOptions, $thOptions);
		$tableHead = $this->Html->tag('thead', $tableHeaders, $theadOptions);
		$tableBody = $this->Html->tag('tbody', $tbody, $tbodyOptions);
		$table = $this->Html->tag('table', $tableHead . $tableBody, $options);

		if ($script !== false) {
			if ($script === true) {
				$script = array();
			}
			if (is_string($script)) {
				$script = array('block' => $script);
			}
			$this->script($script, $js);
		}

		return $table;
	}

/**
 * Generates javascript block for DataTable
 *
 * jsOptions takes on the following options:
 * - `aoColumns` If true, will use columns defined by component
 * - `sAjaxSource` If not a string, will use Router::url to build the url
 * - Any other options needed to be passed to jquery config
 *
 * @param array $options
 * @param array $jsOptions
 * @return mixed string|void String if `inline`, void otherwise
 */
	public function script($options = array(), $js = array()) {
		$options = array_merge($this->settings['script'], $options);
		$js = array_merge($this->settings['js'], $js);
		if (isset($js['sAjaxSource']) && !is_string($js['sAjaxSource'])) {
			$js['sAjaxSource'] = Router::url($js['sAjaxSource']);
		}
		if (isset($js['aoColumns']) && $js['aoColumns'] === true) {
			$js['aoColumns'] = $this->columns;
		}
		$dtInitScript = $this->_View->element('DataTable.jquery_datatable', compact('js'));
		return $this->Html->scriptBlock($dtInitScript, $options);
	}

/**
 * Parse settings
 *
 * @return void
 */
	protected function _parseSettings() {
		foreach($this->_View->viewVars['dtColumns'] as $field => $options) {
			$this->labels[] = ($options === null) ? $field : $options['label'];
			unset($options['label']);
			if (isset($options['bSearchable'])) {
				$options['bSearchable'] = (boolean)$options['bSearchable'];
			}
			$this->columns[] = $options;
		}
	}

}