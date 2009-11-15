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

// We will use WideImage as our image manipulation library
require("../../lib/WideImage/WideImage.php");

// Load the uploaded image
$original = WideImage::load($new_path);

// Resize the original image to 1024x768 if it's bigger than that and save
$original->resize(1024, 768, 'inside', 'down')->saveToFile($new_path, null, 90);

// Creates a thumb image
$ext = end(explode(".", $new_path)); // Get the file extension
$thumb = str_replace(".$ext", "_thumb.$ext", $new_path); // Replace the extension

$original->resize(100, 75, 'inside', 'down')->saveToFile($thumb, null, 90); // Resize and save

echo mysql_insert_id(); // Return the photo id
?>
