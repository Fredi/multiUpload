<?php
/**
 * Mysql Connection
 */
$conn = mysql_connect('localhost', 'root', '');
$db   = mysql_select_db('db');

$id = $_POST['id'];
$caption = addslashes($_POST['caption']);

if (!empty($caption))
{
	$query = "UPDATE albums_photos SET caption = '$caption' WHERE id = $id";

	if (mysql_query($query))
		die($caption);
}

die("Error");
?>
