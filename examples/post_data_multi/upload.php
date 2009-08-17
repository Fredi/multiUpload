<?php
/**
 * PHP Upload script
 * 
 * Saves files with a prefix
 */

$file = $_FILES['Filedata'];
$prefix = $_POST['Prefix'];

$path     = $file['tmp_name'];
$new_path = "../../uploads/".$prefix.$file['name'];

move_uploaded_file($path, $new_path);

echo "1";
?>
