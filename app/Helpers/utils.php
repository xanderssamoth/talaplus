<?php

/**
 * @author Xanders
 * @see https://team.xsamtech.com/xanderssamoth
 */

use Carbon\Carbon;

// Get web URL
if (!function_exists('getWebURL')) {
    function getWebURL()
    {
        return (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
    }
}

// Get APIs URL
if (!function_exists('getApiURL')) {
    function getApiURL()
    {
        return (!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/api';
    }
}

// Get API toke
if (!function_exists('getApiToken')) {
    function getApiToken()
    {
        return '';
    }
}

// Check if file is photo
if (!function_exists('isPhotoFile')) {
    function isPhotoFile($url)
    {
        // Extract file extension
        $pathInfo = pathinfo($url);
        $extension = strtolower($pathInfo['extension'] ?? '');

        // List of recognized photo extensions
        $photoExtensions = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'bmp', 'tiff', 'tif', 'raw', 'cr2', 'nef', 'arw'];

        // Check if the extension is in the list
        return in_array($extension, $photoExtensions);
    }
}

// Check if file is video
if (!function_exists('isVideoFile')) {
    function isVideoFile($url)
    {
        // Extract file extension
        $pathInfo = pathinfo($url);
        $extension = strtolower($pathInfo['extension'] ?? '');

        // List of recognized video extensions
        $videoExtensions = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'flv', 'webm', 'mpeg'];

        // Check if the extension is in the list
        return in_array($extension, $videoExtensions);
    }
}

// Helper function to sanitize filenames
if (!function_exists('sanitizeFileName')) {
    function sanitizeFileName($filename)
    {
        // Convert to lowercase
        $filename = strtolower($filename);

        // Replace spaces with underscores
        $filename = str_replace(' ', '_', $filename);

        // Remove special characters (you can add more if needed)
        $filename = preg_replace('/[^a-z0-9._-]/', '', $filename);

        return $filename;
    }
}

// Check if a value exists into an multidimensional array
if (!function_exists('inArrayR')) {
    function inArrayR($needle, $haystack, $key)
    {
        return in_array($needle, collect($haystack)->pluck($key)->toArray());
    }
}

// Get array of columns from a keys/values object
if (!function_exists('getArrayKeys')) {
    function getArrayKeys($haystack, $ref)
    {
        return collect($haystack)->pluck($ref)->toArray();
    }
}

// Month fully readable
if (!function_exists('explicitMonth')) {
    function explicitMonth($month)
    {
        Carbon::setLocale(app()->getLocale());

        return Carbon::createFromFormat('m', $month)->translatedFormat('F');
    }
}

// Day/Month readable
if (!function_exists('explicitDayMonth')) {
    function explicitDayMonth($date)
    {
        Carbon::setLocale(app()->getLocale());

        return Carbon::parse($date)->translatedFormat('l d F');
    }
}

// Date fully readable
if (!function_exists('explicitDate')) {
    function explicitDate($date)
    {
        Carbon::setLocale(app()->getLocale());

        return Carbon::parse($date)->translatedFormat('l d F Y');
    }
}

// Date/Time fully readable
if (!function_exists('explicitDateTime')) {
    function explicitDateTime($date)
    {
        // Gets the current locale
        $locale = app()->getLocale();
        Carbon::setLocale($locale);

        // Formats the date and time with "à" according to the language
        $formattedDate = Carbon::parse($date)->translatedFormat('l d F Y');
        $formattedTime = Carbon::parse($date)->translatedFormat('H:i');

        // Dynamic translate of "à" depending on the locale
        $atWord = __('miscellaneous.at_time');

        return "{$formattedDate} {$atWord} {$formattedTime}";
    }
}

// Delete item from exploded array
if (!function_exists('deleteExplodedArrayItem')) {
    function deleteExplodedArrayItem($separator, $subject, $item)
    {
        $explodes = explode($separator, $subject);
        $clean_inventory = array();

        foreach ($explodes as $explode) {
            if (!isset($clean_inventory[$explode])) {
                $clean_inventory[$explode] = 0;
            }

            $clean_inventory[$explode]++;
        }

        // Item can be deleted
        unset($clean_inventory[$item]);

        $saved = array();

        foreach ($clean_inventory as $key => $quantity) {
            $saved = array_merge($saved, array_fill(0, $quantity, $key));
        }

        return implode($separator, $saved);
    }
}

// Add an item to exploded array
if (!function_exists('addItemsToExplodedArray')) {
    function addItemsToExplodedArray($separator, $subject, $items)
    {
        $explodes = explode($separator, $subject);
        $saved = array_merge($explodes, $items);

        return implode($separator, $saved);
    }
}
