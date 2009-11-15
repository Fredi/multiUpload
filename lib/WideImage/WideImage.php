<?php
	/**
 * @author Gasper Kozak
 * @copyright 2007, 2008, 2009

    This file is part of WideImage.
		
    WideImage is free software; you can redistribute it and/or modify
    it under the terms of the GNU Lesser General Public License as published by
    the Free Software Foundation; either version 2.1 of the License, or
    (at your option) any later version.
		
    WideImage is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU Lesser General Public License for more details.
		
    You should have received a copy of the GNU Lesser General Public License
    along with WideImage; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
    
    * @package WideImage
  **/
	
	require_once(WideImage::path() . 'Exception.php');
	require_once(WideImage::path() . 'Image.php');
	require_once(WideImage::path() . 'TrueColorImage.php');
	require_once(WideImage::path() . 'PaletteImage.php');
	
	require_once(WideImage::path() . 'Coordinate.php');
	require_once(WideImage::path() . 'Canvas.php');
	require_once(WideImage::path() . 'PaletteImage.php');
	require_once(WideImage::path() . 'TrueColorImage.php');
	require_once(WideImage::path() . 'MapperFactory.php');
	require_once(WideImage::path() . 'OperationFactory.php');
	
	require_once(WideImage::path() . 'Font/TTF.php');
	require_once(WideImage::path() . 'Font/GDF.php');
	require_once(WideImage::path() . 'Font/PS.php');
	
	/**
	 * @package Exceptions
	 */
	class WideImage_InvalidImageHandleException extends WideImage_Exception {}
	
	/**
	 * @package Exceptions
	 */
	class WideImage_InvalidImageSourceException extends WideImage_Exception {}
	
	/**
	 * The gateway class for loading images and core library functions
	 * 
	 * @package WideImage
	 */
	class WideImage
	{
		protected static $path = null;
		
		/**
		 * Returns the library version
		 * 
		 * @return string The library version
		 */
		static function version()
		{
			return '9.09.04';
		}
		
		/**
		 * Returns the path to the library
		 *
		 * @return string
		 */
		static function path()
		{
			if (self::$path === null)
				self::$path = dirname(__FILE__) . DIRECTORY_SEPARATOR;
			return self::$path;
		}
		
		/**
		 * Loads an image from a string, file, or a valid image handle. This function
		 * analyzes the input and decides whether to use WideImage::loadFromHandle(),
		 * WideImage::loadFromFile() or WideImage::loadFromString().
		 * 
		 * The second parameter hints the image format when loading from file/url. 
		 * In most cases, however, hinting isn't needed, because WideImage 
		 * loads the image with imagecreatefromstring().
		 * 
		 * <code>
		 * $img = WideImage::load('http://url/image.png');
		 * $img = WideImage::load('/path/to/image.png', 'jpeg');
		 * $img = WideImage::load($image_resource);
		 * $img = WideImage::load($string);
		 * </code>
		 * 
		 * @param mixed $source File name, url, binary string, or GD image resource
		 * @param string $format Hint for image format
		 * @return WideImage_Image WideImage_PaletteImage or WideImage_TrueColorImage instance
		 */
		static function load($source, $format = null)
		{
			$predictedSourceType = '';
			
			if (!$predictedSourceType && self::isValidImageHandle($source))
				$predictedSourceType = 'Handle';
			
			if (!$predictedSourceType)
			{
				// search first $binLength bytes (at a maximum) for ord<32 characters (binary image data)
				$binLength = 64;
				$sourceLength = strlen($source);
				$maxlen = ($sourceLength > $binLength) ? $binLength : $sourceLength;
				for ($i = 0; $i < $maxlen; $i++)
					if (ord($source[$i]) < 32)
					{
						$predictedSourceType = 'String';
						break;
					}
			}
			
			if (isset($_FILES[$source]) && isset($_FILES[$source]['tmp_name']))
				$predictedSourceType = 'Upload';
			
			if (!$predictedSourceType)
				$predictedSourceType = 'File';
			
			return call_user_func(array('WideImage', 'loadFrom' . $predictedSourceType), $source, $format);
		}			
		
		/**
		 * Create and load an image from a file or URL. You can override the file 
		 * format by specifying the second parameter.
		 * 
		 * @param string $uri File or url
		 * @param string $format Format hint, usually not needed
		 * @return WideImage_Image WideImage_PaletteImage or WideImage_TrueColorImage instance
		 */
		static function loadFromFile($uri, $format = null)
		{
			$data = file_get_contents($uri);
			$handle = @imagecreatefromstring($data);
			if (!self::isValidImageHandle($handle))
			{
				$mapper = WideImage_MapperFactory::selectMapper($uri, $format);
				$handle = $mapper->load($uri);
			}
			if (!self::isValidImageHandle($handle))
				throw new WideImage_InvalidImageSourceException("File '{$uri}' appears to be an invalid image source.");
			
			return self::loadFromHandle($handle);
		}
		
		/**
		 * Create and load an image from a string. Format is auto-detected.
		 * 
		 * @param string $string Binary data, i.e. from BLOB field in the database
		 * @return WideImage_Image WideImage_PaletteImage or WideImage_TrueColorImage instance
		 */
		static function loadFromString($string)
		{
			$handle = imagecreatefromstring($string);
			if (!self::isValidImageHandle($handle))
				throw new WideImage_InvalidImageSourceException("String doesn't contain valid image data.");
			
			return self::loadFromHandle($handle);
		}
		
		/**
		 * Create and load an image from an image handle.
		 * 
		 * <b>Note:</b> the resulting image object takes ownership of the passed 
		 * handle. When the newly-created image object is destroyed, the handle is 
		 * destroyed too, so it's not a valid image handle anymore. In order to 
		 * preserve the handle for use after object destruction, you have to call 
		 * WideImage_Image::releaseHandle() on the created image instance prior to its
		 * destruction.
		 * 
		 * <code>
		 * $handle = imagecreatefrompng('file.png');
		 * $image = WideImage::loadFromHandle($handle);
		 * </code>
		 * 
		 * @param resource $handle A valid GD image resource
		 * @return WideImage_Image WideImage_PaletteImage or WideImage_TrueColorImage instance
		 */
		static function loadFromHandle($handle)
		{
			if (!self::isValidImageHandle($handle))
				throw new WideImage_InvalidImageSourceException("Handle is not a valid GD image resource.");
			
			if (imageistruecolor($handle))
				return new WideImage_TrueColorImage($handle);
			else
				return new WideImage_PaletteImage($handle);
		}
		
		/**
		 * This method loads a file from the $_FILES array.
		 * 
		 * You only have to pass the field name as the parameter.
		 * 
		 * @param $field_name Name of the key in $_FILES array
		 * @return WideImage_Image The loaded image
		 */
		static function loadFromUpload($field_name)
		{
			if (!array_key_exists($field_name, $_FILES) || !file_exists($_FILES[$field_name]['tmp_name']))
				throw new WideImage_InvalidImageSourceException("Upload field '{$field_name}' or file doesn't exist.");
			
			return self::loadFromFile($_FILES[$field_name]['tmp_name'], $_FILES[$field_name]['type']);
		}
		
		/**
		 * Check whether the given handle is a valid GD resource
		 * 
		 * @param mixed $handle The variable to check
		 * @return bool
		 */
		static function isValidImageHandle($handle)
		{
			return (is_resource($handle) && get_resource_type($handle) == 'gd');
		}
		
		/**
		 * Throws exception if the handle isn't a valid GD resource
		 * 
		 * @param mixed $handle The variable to check
		 */
		static function assertValidImageHandle($handle)
		{
			if (!self::isValidImageHandle($handle))
				throw new WideImage_InvalidImageHandleException("{$handle} is not a valid image handle.");
		}
		
	}
?>