# CakePHP DataTable Plugin
DataTable is a cakephp plugin for [JQuery DataTables](http://datatables.net/).

# Requirements
* CakePHP 2.2 or higher

# Installation

_[Manual]_

1. Download this: [http://github.com/tigrang/cakephp-datatable/zipball/master](http://github.com/tigrang/cakephp-datatable/zipball/master).
2. Unzip
3. Copy the resulting folder to `app/Plugin`
4. Rename the folder to `DataTable`

_[GIT Submodule]_

In your app directory run:

	git submodule add https://github.com/tigrang/cakephp-datatable.git Plugin/DataTable
	git submodule init
	git submodule update

_[GIT Clone]_

In your plugin directory run:

	git clone https://github.com/tigrang/cakephp-datatable.git DataTable

## Enable Plugin

In your `app/Config/bootstrap.php`:

	CakePlugin::loadAll();
	// OR
	CakePlugin::load('DataTable');

# Usage

#### Example controller:
	<?php
	class UsersController extends AppController {

	/**
	 * Components
	 *
	 * @var array
	 */
		public $components = array(
			'DataTable.DataTable' => array(
				'triggerAction' => array('myaction')  // use if your action is not 'index'
				'columns' => array(
					'id' => false, 						// bSearchable and bSortable will be false
					'username' => 'Name',				// bSearchable and bSortable will be true, with a custom label `Name`
														// by default, the label with be a Inflector::humanize() version of the key
					'email' => array(
						'bSearchable' => 'customSearch',// will use model callback to add search conditions
					),
					'Actions' => null,					// tells DataTable that this column is not tied to a field
				),
			),
		);

By default, datatable processing is triggered if the request is an ajax one and the current action is 'index'. If this isn't the case, you can set the triggerAction setting to the action you need as a string, or an array of multiple actions for that controller that should be triggered if the request is an ajax one. You can also set trigger setting to a callback in your controller to do custom detection of when to process a request as a datatable request (it should return true/false).


	/**
	 * Helpers
	 *
	 * @var array
	 */
		public $helpers = array(
			'DataTable.DataTable',
	 	);
	}

You may now paginate more than one model in a view. This becomes useful when displaying two or more hasMany associated models for a `view` page.
List all models to paginate in `$this->DataTable->paginate` property:

	<?php
	public function view() {
		$this->DataTable->paginate = array('User', 'Article');
	}
	
**Note**: These models must be directly related.

See docblock for complete list of component settings.

Once you have your component setup, you will need to add your view.
  
  * First create a `View/[ModelName]/datatable` folder
  * Create `action_name.ctp` view inside the folder

The DataTableResponseView (automatically set by the component) class has a member called `dtResponse` which holds 
the return data for jquery datatables, including `aaData`.

By default, the view var `$dtResults` will hold resultant model data after searching and paginating. It can be 
customized with the `viewVar` setting.

#### Example view file:

	<?php
	foreach($dtResults as $result) {
		$this->dtResponse['aaData'][] = array(
			$result['User']['id'],
			$result['User']['username'],
			$result['User']['email'],
			'actions',
		);
	}

#### Example `bSearchable` callback:

	<?php
	class Users extends AppModel {
		public function customSearch($field, $searchTerm, $columnSearchTerm, $conditions) {
			if ($searchTerm) {
				$conditions[] = array("$field LIKE" => '%' . $searchTerm);	// only do left search
			}
			if ($columnSearchTerm) {
				$conditions[] = array($field => $columnSearchTerm);			// only do exact match
			}
			return $conditions;
		}
	}
	
# Helper Usage
    
	<?php
	$this->DataTable->render(); // renders default model for this view
	$this->DataTable->render('AssociatedModel'); // renders 'AssociatedModel' table
	
If you create the `<table>` yourself, be sure to add a `data-model="Model"` attribute to the table tag. The helper is still required to parse the column settings and outputs a global javascript `dataTableSettings` available for you to use.
The helper by default uses the following init script:

	$('.dataTable').each(function() {
		var table = $(this);
		var model = table.attr('data-model');
		var settings = dataTableSettings[model];
		table.dataTable(settings);
	});
	
The helper relies on a js var that is set inside the `dataTableSettings` block. Add `<?php echo $this->fetch('dataTableSettings'); ?>` before your scripts block.

