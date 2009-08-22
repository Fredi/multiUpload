<?php
/**
 * Mysql Connection
 */
$conn = mysql_connect('localhost', 'root', '');
$db   = mysql_select_db('db');

$title = addslashes($_POST['title']);
$description = addslashes($_POST['description']);

if (!empty($title) && !empty($description))
{
	$query = "INSERT INTO albums (title, description) VALUES ('$title', '$description')";

	if (mysql_query($query))
	{
		$json = array();
		$json["id"] = mysql_insert_id();
		die(json_encode($json));
	}
	else
		error("Unable to save the album.");
}
else
	error("Title or Description is empty.");

function error($msg)
{
	$json = array();
	$json["id"]  = 0;
	$json["msg"] = "Error: $msg";
	die(json_encode($json));
}
?>
