<?php
/**
 * PHP Upload script
 * 
 * Just saves the uploaded file in the right folder
 */

$file = $_FILES['Filedata'];

$path     = $file['tmp_name'];
$new_path = "../uploads/".$file['name'];

move_uploaded_file($path, $new_path);

echo "1";
?>
