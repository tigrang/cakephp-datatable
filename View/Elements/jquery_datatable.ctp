$(document).ready(function() {
	$('.datatable').dataTable(<?php echo json_encode($js); ?>);
});