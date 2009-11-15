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
	
	/**
	 * Thrown when an invalid dimension is passed for some operations
	 * 
	 * @package Exceptions
	 */
	class WideImage_InvalidImageDimensionException extends WideImage_Exception {}
	
	/**
	 * Base class for images
	 * 
	 * @package WideImage
	 */
	abstract class WideImage_Image
	{
		/**
		 * Holds the image resource
		 * @var resource
		 */
		protected $handle = null;
		
		/**
		 * Flag that determines if WideImage should call imagedestroy() upon object destruction
		 * @var bool
		 */
		protected $handleReleased = false;
		
		/**
		 * Canvas object
		 * @var WideImage_Canvas
		 */
		protected $canvas = null;
		
		/**
		 * The base class constructor
		 *
		 * @param resource $handle Image handle (GD2 resource)
		 */
		function __construct($handle)
		{
			WideImage::assertValidImageHandle($handle);
			$this->handle = $handle;
		}
		
		/**
		 * Cleanup
		 * 
		 * Destroys the handle via WideImage_Image::destroy() when called by the GC.
		 */
		function __destruct()
		{
			$this->destroy();
		}
		
		/**
		 * This method destroy the image handle, and releases the image resource.
		 * 
		 * After this is called, the object doesn't hold a valid image any more.
		 * No operation should be called after that.
		 */
		function destroy()
		{
			if ($this->isValid() && !$this->handleReleased)
				imagedestroy($this->handle);
			
			$this->handle = null;
		}
		
		/**
		 * Returns the GD image resource
		 * 
		 * @return resource GD image resource
		 */
		function getHandle()
		{
			return $this->handle;
		}
		
		/**
		 * @return bool True, if the image object holds a valid GD image, false otherwise
		 */
		function isValid()
		{
			return WideImage::isValidImageHandle($this->handle);
		}
		
		/**
		 * Releases the handle
		 */
		function releaseHandle()
		{
			$this->handleReleased = true;
		}
		
		/**
		 * Saves an image to a file
		 * 
		 * The file type is recognized from the $uri. If you save to a GIF8, truecolor images
		 * are automatically converted to palette.
		 * 
		 * This method supports additional parameters: quality (for jpeg images) and 
		 * compression quality and filters (for png images). See http://www.php.net/imagejpeg and
		 * http://www.php.net/imagepng for details.
		 * 
		 * @param string $uri The file locator (can be url)
		 * @return mixed Whatever the mapper returns
		 */
		function saveToFile($uri)
		{
			$mapper = WideImage_MapperFactory::selectMapper($uri, null);
			$args = func_get_args();
			unset($args[1]);
			array_unshift($args, $this->getHandle());
			return call_user_func_array(array($mapper, 'save'), $args);
		}
		
		/**
		 * Returns binary string with image data in format specified by $format
		 * 
		 * @param string $format The format of the image
		 * @return string The binary image data in specified format
		 */
		function asString($format)
		{
			ob_start();
			$args = func_get_args();
			$args[0] = null;
			array_unshift($args, $this->getHandle());
			
			$mapper = WideImage_MapperFactory::selectMapper(null, $format);
			call_user_func_array(array($mapper, 'save'), $args);
			
			return ob_get_clean();
		}
		
		/**
		 * Output a header to browser.
		 * 
		 * @param $name Name of the header
		 * @param $data Data
		 */
		protected function writeHeader($name, $data)
		{
			header($name . ": " . $data);
		}
		
		/**
		 * Outputs the image to browser
		 * 
		 * Sets headers Content-length and Content-type, and echoes the image in the specified format. 
		 * 
		 * @param string $format Image format
		 */
		function output($format)
		{
			$args = func_get_args();
			$data = call_user_func_array(array($this, 'asString'), $args);
			
			$this->writeHeader('Content-length', strlen($data));
			$this->writeHeader('Content-type', WideImage_MapperFactory::mimeType($format));
			echo $data;
		}
		
		/**
		 * @return int Image width
		 */
		function getWidth()
		{
			return imagesx($this->handle);
		}
		
		/**
		 * @return int Image height
		 */
		function getHeight()
		{
			return imagesy($this->handle);
		}
		
		/**
		 * Allocate a color by RGB values.
		 * 
		 * @param mixed $R Red-component value or an RGB array (with red, green, blue keys)
		 * @param int $G If $R is int, this is the green component
		 * @param int $B If $R is int, this is the blue component
		 * @return int Image color index
		 */
		function allocateColor($R, $G = null, $B = null)
		{
			if (is_array($R))
				return imageColorAllocate($this->handle, $R['red'], $R['green'], $R['blue']);
			else
				return imageColorAllocate($this->handle, $R, $G, $B);
		}
		
		/**
		 * @return bool True if the image is transparent, false otherwise
		 */
		function isTransparent()
		{
			return $this->getTransparentColor() >= 0;
		}
		
		/**
		 * @return int Transparent color index
		 */
		function getTransparentColor()
		{
			return imagecolortransparent($this->handle);
		}
		
		/**
		 * @param int $color Transparent color index
		 */
		function setTransparentColor($color)
		{
			return imagecolortransparent($this->handle, $color);
		}
		
		/**
		 * @return mixed Transparent color RGBA array
		 */
		function getTransparentColorRGB()
		{
			return $this->getColorRGB($this->getTransparentColor());
		}
		
		/**
		 * Returns a RGBA array for pixel at $x, $y
		 * 
		 * @param int $x
		 * @param int $y
		 * @return array RGB array 
		 */
		function getRGBAt($x, $y)
		{
			return $this->getColorRGB($this->getColorAt($x, $y));
		}
		
		/**
		 * Writes a pixel at the designated coordinates
		 * 
		 * Takes an associative array of colours and uses getExactColor() to
		 * retrieve the exact index color to write to the image with.
		 *
		 * @param int $x
		 * @param int $y
		 * @param array $color
		 */
		function setRGBAt($x, $y, $color)
		{
			$this->setColorAt($x, $y, $this->getExactColor($color));
		}
		
		/**
		 * Returns a color's RGB
		 * 
		 * @param int $colorIndex Color index
		 * @return mixed RGBA array for a color with index $colorIndex
		 */
		function getColorRGB($colorIndex)
		{
			return imageColorsForIndex($this->handle, $colorIndex);
		}
		
		/**
		 * Returns an index of the color at $x, $y
		 * 
		 * @param int $x
		 * @param int $y
		 * @return int Color index for a pixel at $x, $y
		 */
		function getColorAt($x, $y)
		{
			return imagecolorat($this->handle, $x, $y);
		}
		
		/**
		 * Set the color index $color to a pixel at $x, $y
		 * 
		 * @param int $x
		 * @param int $y
		 * @param int $color Color index
		 */
		function setColorAt($x, $y, $color)
		{
			return imagesetpixel($this->handle, $x, $y, $color);
		}
		
		/**
		 * Returns closest color index that matches the given RGB value. Uses
		 * PHP's imagecolorclosest()
		 * 
		 * @param mixed $R Red or RGBA array
		 * @param int $G Green component (or null if $R is an RGB array)
		 * @param int $B Blue component (or null if $R is an RGB array)
		 * @return int Color index
		 */
		function getClosestColor($R, $G = null, $B = null)
		{
			if (is_array($R))
				return imagecolorclosest($this->handle, $R['red'], $R['green'], $R['blue']);
			else
				return imagecolorclosest($this->handle, $R, $G, $B);
		}
		
		/**
		 * Returns the color index that exactly matches the given RGB value. Uses
		 * PHP's imagecolorexact()
		 * 
		 * @param mixed $R Red or RGBA array
		 * @param int $G Green component (or null if $R is an RGB array)
		 * @param int $B Blue component (or null if $R is an RGB array)
		 * @return int Color index
		 */
		function getExactColor($R, $G = null, $B = null)
		{
			if (is_array($R))
				return imagecolorexact($this->handle, $R['red'], $R['green'], $R['blue']);
			else
				return imagecolorexact($this->handle, $R, $G, $B);
		}
		
		/**
		 * Copies transparency information from $sourceImage. Optionally fills
		 * the image with the transparent color at (0, 0).
		 * 
		 * @param object $sourceImage
		 * @param bool $fill True if you want to fill the image with transparent color
		 */
		function copyTransparencyFrom($sourceImage, $fill = true)
		{
			if ($sourceImage->isTransparent())
			{
				$rgba = $sourceImage->getTransparentColorRGB();
				$color = $this->allocateColor($rgba);
				$this->setTransparentColor($color);
				if ($fill)
					$this->fill(0, 0, $color);
			}
		}
		
		/**
		 * Fill the image at ($x, $y) with color index $color
		 * 
		 * @param int $x
		 * @param int $y
		 * @param int $color
		 */
		function fill($x, $y, $color)
		{
			return imagefill($this->handle, $x, $y, $color);
		}
		
		/**
		 * Used internally to create Operation objects
		 *
		 * @param string $name
		 * @return object
		 */
		protected function getOperation($name)
		{
			return WideImage_OperationFactory::get($name);
		}
		
		/**
		 * Returns the image's mask
		 * 
		 * Mask is a greyscale image where the shade defines the alpha channel (black = transparent, white = opaque).
		 * 
		 * For opaque images (JPEG), the result will be white. For images with single-color transparency (GIF, 8-bit PNG), 
		 * the areas with the transparent color will be black. For images with alpha channel transparenct, 
		 * the result will be alpha channel.
		 * 
		 * @return WideImage_Image An image mask
		 **/
		function getMask()
		{
			return $this->getOperation('GetMask')->execute($this);
		}
		
		/**
		 * Resize the image to given dimensions.
		 * 
		 * $width and $height are both smart coordinates. This means that you can pass any of these values in:
		 *   - positive or negative integer (100, -20, ...)
		 *   - positive or negative percent string (30%, -15%, ...)
		 *   - complex coordinate (50% - 20, 15 + 30%, ...)
		 * 
		 * If $width is null, it's calculated proportionally from $height, and vice versa.
		 * 
		 * Example (resize to half-size):
		 * <code>
		 * $smaller = $image->resize('50%');
		 * 
		 * $smaller = $image->resize('100', '100', 'inside', 'down');
		 * is the same as
		 * $smaller = $image->resizeDown(100, 100, 'inside');
		 * </code>
		 * 
		 * @var mixed $width The new width (smart coordinate), or null.
		 * @var mixed $height The new height (smart coordinate), or null.
		 * @var string $fit 'inside', 'outside', 'fill'
		 * @var string $scale 'down', 'up', 'any'
		 * @return WideImage_Image resized image
		 */
		function resize($width = null, $height = null, $fit = 'inside', $scale = 'any')
		{
			return $this->getOperation('Resize')->execute($this, $width, $height, $fit, $scale);
		}
		
		/**
		 * Same as WideImage_Image::resize(), but the image is only applied if it is larger then the given dimensions.
		 * Otherwise, the resulting image retains the source's dimensions.
		 * 
		 * @var int $width New width, smart coordinate
		 * @var int $height New height, smart coordinate
		 * @var string $fit 'inside', 'outside', 'fill'
		 * @return WideImage_Image resized image
		 */
		function resizeDown($width = null, $height = null, $fit = 'inside')
		{
			return $this->resize($width, $height, $fit, 'down');
		}
		
		/**
		 * Same as WideImage_Image::resize(), but the image is only applied if it is smaller then the given dimensions.
		 * Otherwise, the resulting image retains the source's dimensions.
		 * 
		 * @var int $width New width, smart coordinate
		 * @var int $height New height, smart coordinate
		 * @var string $fit 'inside', 'outside', 'fill'
		 * @return WideImage_Image resized image
		 */
		function resizeUp($width = null, $height = null, $fit = 'inside')
		{
			return $this->resize($width, $height, $fit, 'up');
		}
		
		/**
		 * Rotate the image for angle $angle clockwise.
		 * 
		 * @param int $angle Angle in degrees
		 * @param int $bgColor color of background
		 * @param bool $ignoreTransparent
		 * @return WideImage_Image The rotated image
		 */
		function rotate($angle, $bgColor = null, $ignoreTransparent = true)
		{
			return $this->getOperation('Rotate')->execute($this, $angle, $bgColor, $ignoreTransparent);
		}
		
		/**
		 * This method lays the overlay (watermark) on the image.
		 * 
		 * Hint: if the overlay is a truecolor image with alpha channel, you should leave $pct at 100.
		 * 
		 * @param WideImage_Image $overlay The overlay image
		 * @param mixed $left Left position of the overlay, smart coordinate
		 * @param mixed $top Top position of the overlay, smart coordinate
		 * @param int $pct The opacity of the overlay
		 * @return WideImage_Image The merged image
		 */
		function merge($overlay, $left = 0, $top = 0, $pct = 100)
		{
			return $this->getOperation('Merge')->execute($this, $overlay, $left, $top, $pct);
		}
		
		/**
		 * Returns an image with applied mask
		 * 
		 * A mask is a grayscale image, where the shade determines the alpha channel. Black is fully transparent
		 * and white is fully opaque.
		 * 
		 * @param WideImage_Image $mask The mask image, greyscale
		 * @param mixed $left Left coordinate, smart coordinate
		 * @param mixed $top Top coordinate, smart coordinate
		 * @return WideImage_Image The resulting image
		 **/
		function applyMask($mask, $left = 0, $top = 0)
		{
			return $this->getOperation('ApplyMask')->execute($this, $mask, $left, $top);
		}
		
		/**
		 * Applies a filter
		 *
		 * @param int $filter One of the IMG_FILTER_* constants
		 * @param int $arg1
		 * @param int $arg2
		 * @param int $arg3
		 * @param int $arg4
		 * @return WideImage_Image
		 */
		function applyFilter($filter, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null)
		{
			return $this->getOperation('ApplyFilter')->execute($this, $filter, $arg1, $arg2, $arg3, $arg4);
		}
		
		/**
		 * Applies convolution matrix with imageconvolution()
		 *
		 * @param array $matrix
		 * @param float $div
		 * @param float $offset
		 * @return WideImage_Image
		 */
		function applyConvolution($matrix, $div, $offset)
		{
			return $this->getOperation('ApplyConvolution')->execute($this, $matrix, $div, $offset);
		}
		
		/**
		 * Returns a cropped rectangular portion of the image
		 * 
		 * If the rectangle specifies area that is out of bounds, it's limited to the current image bounds.
		 * 
		 * Examples:
		 * <code>
		 * $cropped = $img->crop(10, 10, 150, 200); // crops a 150x200 rect at (10, 10)
		 * $cropped = $img->crop(-100, -50, 100, 50); // crops a 100x50 rect at the right-bottom of the image
		 * $cropped = $img->crop('c-50', 'c-50', 100, 100); // crops a 100x100 rect from the center of the image
		 * $cropped = $img->crop('c-25%', 'c-25%', '50%', '50%'); // crops a 50%x50% rect from the center of the image
		 * </code>
		 * 
		 * @param mixed $left Left-coordinate of the crop rect, smart coordinate
		 * @param mixed $top Top-coordinate of the crop rect, smart coordinate
		 * @param mixed $width Width of the crop rect, smart coordinate
		 * @param mixed $height Height of the crop rect, smart coordinate
		 * @return WideImage_Image The cropped image
		 **/
		function crop($left, $top, $width, $height)
		{
			return $this->getOperation('Crop')->execute($this, $left, $top, $width, $height);
		}
		
		/**
		 * Performs an auto-crop on the image
		 *
		 * The image is auto-cropped from each of four sides. All sides are 
		 * scanned for pixels that differ from $base_color for more than 
		 * $rgb_threshold in absolute RGB difference. If more than $pixel_cutoff 
		 * differentiating pixels are found, that line is considered to be the crop line for the side.
		 * If the line isn't different enough, the algorithm procedes to the next line 
		 * towards the other edge of the image.
		 * 
		 * When the crop rectangle is found, it's enlarged by the $margin value on each of the four sides.
		 *
		 * @param int $margin Margin for the crop rectangle, can be negative.
		 * @param int $rgb_threshold RGB difference which still counts as "same color".
		 * @param int $pixel_cutoff How many pixels need to be different to mark a cut line.
		 * @param int $base_color The base color index. If none specified (or null given), left-top pixel is used.
		 * @return WideImage_Image The cropped image
		 */
		function autoCrop($margin = 0, $rgb_threshold = 0, $pixel_cutoff = 1, $base_color = null)
		{
			return $this->getOperation('AutoCrop')->execute($this, $margin, $rgb_threshold, $pixel_cutoff, $base_color);
		}
		
		/**
		 * Returns a negative of the image
		 *
		 * This operation differs from calling WideImage_Image::applyFilter(IMG_FILTER_NEGATIVE), because it's 8-bit and transparency safe.
		 * This means it will return an 8-bit image, if the source image is 8-bit. If that 8-bit image has a palette transparency,
		 * the resulting image will keep transparency.
		 *
		 * @return WideImage_Image negative of the image
		 */
		function asNegative()
		{
			if ($this instanceof WideImage_PaletteImage && $this->isTransparent())
				$trgb = $this->getTransparentColorRGB();
			else
				$trgb = null;
			
			$img = $this->getOperation('ApplyFilter')->execute($this, IMG_FILTER_NEGATE);
			
			if ($this instanceof WideImage_PaletteImage)
			{
				$img = $img->asPalette();
				
				if ($trgb)
				{
					$irgb = array('red' => 255 - $trgb['red'], 'green' => 255 - $trgb['green'], 'blue' => 255 - $trgb['blue']);
					$tci = $img->getExactColor($irgb);
					$img->setTransparentColor($tci);
				}
			}
			
			return $img;
		}
		
		/**
		 * Returns a grayscale copy of the image
		 * 
		 * @return WideImage_Image grayscale copy
		 **/
		function asGrayscale()
		{
			return $this->getOperation('AsGrayscale')->execute($this);
		}
		
		/**
		 * Returns a mirrored copy of the image
		 * 
		 * @return WideImage_Image Mirrored copy
		 **/
		function mirror()
		{
			return $this->getOperation('Mirror')->execute($this);
		}
		
		/**
		 * Applies the unsharp filter
		 * 
		 * @param float $amount
		 * @param float $radius
		 * @param float $threshold
		 * @return WideImage_Image Unsharpened copy of the image
		 **/
		function unsharp($amount, $radius, $threshold)
		{
			return $this->getOperation('Unsharp')->execute($this, $amount, $radius, $threshold);
		}
		
		/**
		 * Returns a flipped (mirrored over horizontal line) copy of the image
		 * 
		 * @return WideImage_Image Flipped copy
		 **/
		function flip()
		{
			return $this->getOperation('Flip')->execute($this);
		}
		
		/**
		 * Corrects gamma on the image
		 * 
		 * @param float $inputGamma
		 * @param float $outputGamma
		 * @return WideImage_Image Image with corrected gamma
		 **/
		function correctGamma($inputGamma, $outputGamma)
		{
			return $this->getOperation('CorrectGamma')->execute($this, $inputGamma, $outputGamma);
		}
		
		/**
		 * Used internally to execute operations
		 *
		 * @param string $name
		 * @param array $args
		 * @return WideImage_Image
		 */
		function __call($name, $args)
		{
			$op = $this->getOperation($name);
			array_unshift($args, $this);
			return call_user_func_array(array($op, 'execute'), $args);
		}
		
		/**
		 * Returns an image in GIF or PNG format
		 *
		 * @return string
		 */
		function __toString()
		{
			if ($this->isTransparent())
				return $this->asString('gif');
			else
				return $this->asString('png');
		}
		
		/**
		 * Returns a copy of the image
		 * 
		 * @return WideImage_Image The copy
		 **/
		function copy()
		{
			$dest = $this->doCreate($this->getWidth(), $this->getHeight());
			$dest->copyTransparencyFrom($this, true);
			$this->copyTo($dest, 0, 0);
			return $dest;
		}
		
		/**
		 * Copies this image to another image
		 * 
		 * @param WideImage_Image $dest
		 * @param int $left
		 * @param int $top
		 **/
		function copyTo($dest, $left = 0, $top = 0)
		{
			imageCopy($dest->getHandle(), $this->handle, $left, $top, 0, 0, $this->getWidth(), $this->getHeight());
		}
		
		/**
		 * Returns the canvas object
		 * 
		 * The Canvas object can be used to draw text and shapes on the image
		 * 
		 * Examples:
		 * <code>
		 * $canvas = $img->getCanvas();
		 * $canvas->setFont(new WideImage_Font_TTF('arial.ttf', 15, $img->allocateColor(200, 220, 255)));
		 * $canvas->writeText(10, 50, "Hello world!");
		 * 
		 * $canvas->filledRectangle(10, 10, 80, 40, $img->allocateColor(255, 127, 255));
		 * $canvas->line(60, 80, 30, 100, $img->allocateColor(255, 0, 0));
		 * </code>
		 * 
		 * @return WideImage_Canvas The Canvas object
		 **/
		function getCanvas()
		{
			if ($this->canvas == null)
				$this->canvas = new WideImage_Canvas($this);
			return $this->canvas;
		}
		
		/**
		 * Returns true if the image is true-color, false otherwise
		 * 
		 * @return bool
		 **/
		abstract function isTrueColor();
		
		/**
		 * Returns a true-color copy of the image
		 * 
		 * @return WideImage_TrueColorImage
		 **/
		abstract function asTrueColor();
		
		/**
		 * Returns a palette copy (8bit) of the image
		 *
		 * @param int $nColors Number of colors in the resulting image, more than 0, less or equal to 255
		 * @param bool $dither Use dithering or not
		 * @param bool $matchPalette Set to true to use imagecolormatch() to match the resulting palette more closely to the original image 
		 * @return WideImage_Image
		 **/
		abstract function asPalette($nColors = 255, $dither = null, $matchPalette = true);
		
		/**
		 * Retrieve an image with selected channels
		 * 
		 * Examples:
		 * <code>
		 * $channels = $img->getChannels('red', 'blue');
		 * $channels = $img->getChannels('alpha', 'green');
		 * $channels = $img->getChannels(array('green', 'blue'));
		 * </code>
		 * 
		 * @return WideImage_Image
		 **/
		abstract function getChannels();
		
		/**
		 * Returns an image without an alpha channel
		 * 
		 * @return WideImage_Image
		 **/
		abstract function copyNoAlpha();
	}
?>