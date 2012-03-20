<?php
App::uses('AppHelper', 'View/Helper');

/**
 * DataTable Helper
 *
 * @package Plugin.DataTable
 * @subpackage Plugin.DataTable.View.Helper
 * @author Tigran Gabrielyan
 */
class DataTableHelper extends AppHelper {

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
 * Constructor
 *
 * @param View $View The View this helper is being attached to.
 * @param array $settings Configuration settings for the helper.
 */
	public function __construct(View $View, $settings = array()) {
		parent::__construct($View, $settings);
		$this->_parseSettings();
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