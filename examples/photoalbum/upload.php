<?php
/**
 * Mysql Connection
 */
$conn = mysql_connect('localhost', 'root', '');
$db   = mysql_select_db('db');

$file = $_FILES['Filedata'];

$album = (int) $_POST['id'];
$filename = $file['name'];

$query = "INSERT INTO albums_photos (album, file) VALUES ('$album', '$filename')";

mysql_query($query);

$path     = $file['tmp_name'];
$new_path = "../../uploads/".$file['name'];

move_uploaded_file($path, $new_path);

echo mysql_insert_id(); // Return the photo id
?>
