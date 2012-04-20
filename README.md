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

	/**
	 * Helpers
	 *
	 * @var array
	 */
		public $helpers = array(
			'DataTable.DataTable',
	 	);
	}

See docblock for complete list of component settings.

Once you have your component setup, you will need to add your view.
  
  * First create a `View/[ModelName]/datatable` folder   	
  * Create `action_name.ctp` view inside the folder
      So the file will be like `app/View/User/datatable/index.ctp`

The DataTableResponseView (automatically set by the component) class has a member called `dtResponse` which holds 
the return data for jquery datatables, including `aaData`.

By default, the view var `$dtResults` will hold resultant model data after searching and paginating. It can be 
customized with the `viewVar` setting.

#### Example view file: `app/View/User/datatable/index.ctp`

	<?php
	foreach($dtResults as $result) {
		$this->dtResponse['aaData'][] = array(
			$result['User']['id'],
			$result['User']['username'],
			$result['User']['email'],
			'actions',
		);
	}
	
Next in the `app/Views/User/index.ctp` create a table to display something like
```
<script type="text/javascript">
jQuery(document).ready(function() {
    jQuery('#example1').dataTable( {
        "sPaginationType": "full_numbers",
        "bProcessing": true,
        "bServerSide": true,
        "sAjaxSource": src="users/index",
    });
});
</script>

<table id="example"><thead>put your headers here</thead><tbody></tbody></table>
```


#### Example `bSearchable` callback:
```
	<?php
	class Users extends AppModel {
		public function customSearch($field, $searchTerm, $columnSearchTerm, $conditions) {
			if ($searchTerm) {
				$conditions[] = array("$field LIKE" => '%' . $searchTerm);	// only do left search
			}
			if ($columnSearchTerm) {
				$conditions[] = array($field => $columnSearchTerm);			// only do exact match
			}
		}
	}
```