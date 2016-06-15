<?php
set_time_limit ( 0 );

$tablename = TABLE_PREFIX . "import_diamonds";

if ($_GET ["start_limit"] != '')
	$start_limit = $_GET ["start_limit"];
else
	$start_limit = $_POST ["start_limit"];

$end_limit = 50;

$total_record = $_POST ['total_record'];
$show_error_report = $_POST ['show_error_report'];

$total_added_record = $_POST ['total_added_record'];
$total_updated_record = $_POST ['total_updated_record'];
$total_failed_record = $_POST ['total_failed_record'];

if ($start_limit == 0) {
	$total_products = $obj->select ( "SELECT COUNT(*) AS Total FROM `" . $tablename . "` WHERE LOWER(diamond_sku) != 'diamond sku'" );
	$total_record = $total_products [0] ["Total"];

	$total_added_record = 0;
	$total_updated_record = 0;
	$total_failed_record = 0;

	$show_error_report = 0;

	// # Generated error report during processing products start
	$error_report_file_path = IMPORT_CSV_PATH . "Error_Product_Report.csv";

	if (file_exists ( $error_report_file_path )) {
		@unlink ( $error_report_file_path );
	}

	$err_fp = fopen ( $error_report_file_path, "a+" );

	$csv_fields_arr = $_SESSION ['sess_first_header_row_arr'];
	$tot_csv_fields = count ( $csv_fields_arr );

	if (count ( $tot_csv_fields ) > 0) {
		$products_header_str = '"Error Type","Error Message",';

		for($hd = 0; $hd < $tot_csv_fields; $hd ++) {
			$products_header_str .= '"' . str_replace ( '"', '""', $gen_csv_fields_arr [$csv_fields_arr [$hd]] ['import_header_val'] ) . '",';
		}

		if (trim ( $products_header_str ) != '') {
			$products_header_str = substr ( $products_header_str, 0, - 1 );
			$products_header_str .= "\n";
			fwrite ( $err_fp, $products_header_str );
			fclose ( $err_fp );
		}
	}
	// # Generated error report during processing products end
}

if ($start_limit <= $total_record) {

	$sql = "SELECT " . $_SESSION ['sess_import_csv_field'] . " FROM $tablename
			WHERE LOWER(diamond_sku) !='diamond sku' LIMIT $start_limit , $end_limit";

	$db_xres = $obj->select ( $sql );

	$error_report_file_path = IMPORT_CSV_PATH . "Error_Product_Report.csv";

	$err_fp = fopen ( $error_report_file_path, "a+" );

	if (count ( $db_xres ) > 0) {
		DaimondDataExecution ( $db_xres );
	}

	fclose ( $err_fp );

	echo "<div align='center' style='color:#FF0000;'><h1>Please wait while process the Diamonds CSV data</h1></div>";
	echo "<div align='center' style='color:#FF0000;'><h1>Process Records From : " . $start_limit . " To " . ($start_limit + $end_limit) . "</h1></div>";

	$start_limit = $start_limit + $end_limit;

	?>
<html>
<body>
	<form name="frmThread" action="index.php?f=import_diamond_batch"
		method="post">
		<input type="hidden" name="start_limit" value="<?=$start_limit;?>" />
		<input type="hidden" name="total_record" value="<?=$total_record;?>" />
		<input type="hidden" name="show_error_report"
			value="<?=$show_error_report;?>" /> <input type="hidden"
			name="total_added_record" value="<?=$total_added_record;?>" /> <input
			type="hidden" name="total_updated_record"
			value="<?=$total_updated_record;?>" /> <input type="hidden"
			name="total_failed_record" value="<?=$total_failed_record;?>" />
	</form>
	<script language="javascript">
	        document.frmThread.submit();
        </script>
</body>
</html>
<?
} else {

	$err_msg = "Jewelry Diamond Imported Successfully.<br>";
	$err_msg .= "There are total " . $total_record . " records processed.<br>";
	$err_msg .= "Total Added New Product - " . $total_added_record . " <br>";
	$err_msg .= "Total Updated Product - " . $total_updated_record . " <br>";
	$err_msg .= "Total Record Failed To Process  - " . $total_failed_record . " <br>";
	$err_msg = rawurlencode ( $err_msg );

	header ( "location:index.php?f=import_diamond&err_msg=" . $err_msg . "&err_rpt=" . $show_error_report );
	exit ();
}
?>