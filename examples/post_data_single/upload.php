<?php
/**
 * PHP Upload script
 * 
 * Saves a file with the name chosen
 */

$file = $_FILES['Filedata'];
$name = $_POST['Name'];

$path     = $file['tmp_name'];
$new_path = "../../uploads/".$name;

move_uploaded_file($path, $new_path);

echo "1";
?>
