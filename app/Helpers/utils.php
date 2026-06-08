<?php

/**
 * @author Xanders
 *
 * @see https://team.xsamtech.com/xanderssamoth
 */

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Pagination\LengthAwarePaginator;

// Get web URL
if (! function_exists('getWebURL')) {
    function getWebURL()
    {
        return (! empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
    }
}

// Get APIs URL
if (! function_exists('getApiURL')) {
    function getApiURL()
    {
        return (! empty($_SERVER['HTTPS']) ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'].'/api';
    }
}

// Friendly username from names
if (! function_exists('friendlyUsername')) {
    function friendlyUsername($str)
    {
        // convert to entities
        $string = htmlentities($str, ENT_QUOTES, 'UTF-8');
        // regex to convert accented chars into their closest a-z ASCII equivelent
        $string = preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', $string);
        // convert back from entities
        $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        // any straggling characters that are not strict alphanumeric are replaced with an underscore
        $string = preg_replace('~[^0-9a-z]+~i', '_', $string);
        // trim / cleanup / all lowercase
        $string = trim($string, '-');
        $string = strtolower($string);

        return $string;
    }
}

// Transform mentions and hashtag to URL
if (! function_exists('transformMentionHashtag')) {
    function transformMentionHashtag($web_url, $subject)
    {
        $pat = ['/#(\w+)/', '/@(\w+)/'];
        $rep = ['<strong><a href="'.$web_url.'/hashtag/$1">#$1</a></strong>', '<strong><a href="'.$web_url.'/$1">@$1</a></strong>'];

        return preg_replace($pat, $rep, $subject);
    }
}

// Get all hashtags from text
if (! function_exists('getHashtags')) {
    function getHashtags($subject)
    {
        $hashtags = false;

        preg_match_all('/#(\w+)/u', $subject, $matches);

        if ($matches) {
            $matches[0] = str_replace('#', '', $matches[0]); // replace #
            $hashtags = implode(' ,', $matches[0]);
        }

        return trim(explode(' ,', $hashtags)[0]) != null ? explode(' ,', $hashtags) : [];
    }
}

// Get all mentions from text
if (! function_exists('getMentions')) {
    function getMentions($subject)
    {
        $mentions = false;

        preg_match_all('/@(\w+)/u', $subject, $matches);

        if ($matches) {
            $matches[0] = str_replace('@', '', $matches[0]); // replace @
            $mentions = implode(' ,', $matches[0]);
        }

        return trim(explode(' ,', $mentions)[0]) != null ? explode(' ,', $mentions) : [];
    }
}

// Check if a value exists into an multidimensional array
if (! function_exists('inArrayR')) {
    function inArrayR($needle, $haystack, $key)
    {
        return in_array($needle, collect($haystack)->pluck($key)->toArray());
    }
}

// Get array of columns from a keys/values object
if (! function_exists('getArrayKeys')) {
    function getArrayKeys($haystack, $ref)
    {
        return collect($haystack)->pluck($ref)->toArray();
    }
}

// Month fully readable
if (! function_exists('explicitMonth')) {
    function explicitMonth($month)
    {
        setlocale(LC_ALL, app()->getLocale());

        return utf8_encode(strftime('%B', strtotime(date('F', mktime(0, 0, 0, $month, 10)))));
    }
}

// Day and month fully readable
if (! function_exists('explicitDayMonth')) {
    function explicitDayMonth($date)
    {
        setlocale(LC_ALL, app()->getLocale());

        return utf8_encode(Carbon::parse($date)->formatLocalized('%d %B'));
    }
}

// Date fully readable
if (! function_exists('explicitDate')) {
    function explicitDate($date)
    {
        setlocale(LC_ALL, app()->getLocale());

        return Carbon::parse($date)->locale(app()->getLocale())->translatedFormat('l d F Y');
    }
}

// Relative admin date display for TIMESTAMP and DATETIME columns
if (! function_exists('formatAdminDateTime')) {
    function formatAdminDateTime($date): ?string
    {
        if (blank($date)) {
            return null;
        }

        $date = Carbon::parse($date, config('app.timezone'))->timezone(config('app.timezone'));
        $now = Carbon::now(config('app.timezone'));

        if ($date->isToday()) {
            $seconds = (int) max(0, $date->diffInSeconds($now));

            if ($seconds >= 3600) {
                $hours = intdiv($seconds, 3600);

                return 'Il y a '.$hours.' heure'.($hours > 1 ? 's' : '');
            }

            if ($seconds >= 60) {
                $minutes = intdiv($seconds, 60);

                return 'Il y a '.$minutes.' minute'.($minutes > 1 ? 's' : '');
            }

            return 'Il y a '.$seconds.' seconde'.($seconds > 1 ? 's' : '');
        }

        if ($date->isYesterday()) {
            return 'Hier a '.$date->format('H:i');
        }

        return 'Le '.$date->format('d/m/Y').' a '.$date->format('H:i');
    }
}

// All days of specific week in month
if (! function_exists('getStartAndEndOfWeekInMonth')) {
    function getStartAndEndOfWeekInMonth($year, $month, $weekNumber)
    {
        // Creates a DateTime object for the first day of the month
        $startOfMonth = new DateTime("$year-$month-01");

        // Find the first monday of the month
        $startOfMonth->modify('monday this week');
        if ($startOfMonth->format('n') != $month) {
            $startOfMonth->modify('next monday');
        }

        // Calculate the start of the specific week
        $startOfWeek = clone $startOfMonth;

        $startOfWeek->modify('+'.($weekNumber - 1).' weeks');

        // Calculate the end of the specific week
        $endOfWeek = clone $startOfWeek;

        $endOfWeek->modify('sunday this week');

        // Adjust to not exceed the month
        if ($startOfWeek->format('n') != $month) {
            $startOfWeek->modify('first day of next month');
            $startOfWeek->modify('-1 week');

            $endOfWeek = clone $startOfWeek;

            $endOfWeek->modify('sunday this week');
        }

        // Make sure the end of the week does not exceed the end of the month
        $endOfMonth = new DateTime("$year-$month-".date('t', strtotime("$year-$month-01")));

        if ($endOfWeek > $endOfMonth) {
            $endOfWeek = $endOfMonth;
        }

        return [
            'start' => $startOfWeek->format('Y-m-d'),
            'end' => $endOfWeek->format('Y-m-d'),
        ];
    }
}

// All weeks of specific month
if (! function_exists('getWeeksOfMonth')) {
    function getWeeksOfMonth($year, $month)
    {
        $weeks = [];
        // Start and end of the month
        $startDate = new DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month');
        // First week initialization
        $startOfWeek = clone $startDate;

        $startOfWeek->modify('this week');

        while ($startOfWeek <= $endDate) {
            $endOfWeek = clone $startOfWeek;

            $endOfWeek->modify('sunday this week');

            // Adjustment to not exceed the end of the month
            if ($endOfWeek > $endDate) {
                $endOfWeek = $endDate;
            }

            // Adding the week to the list
            $weeks[] = [
                'start' => $startOfWeek->format('Y-m-d'),
                'end' => $endOfWeek->format('Y-m-d'),
            ];

            // Moving on to the next week
            $startOfWeek->modify('next week');
        }

        return $weeks;
    }
}

// All quarters of specific year
if (! function_exists('getQuarterDates')) {
    function getQuarterDates($year, $quarter)
    {
        switch ($quarter) {
            case 1:
                $startDate = "$year-01-01";
                $endDate = "$year-03-31";
                break;
            case 2:
                $startDate = "$year-04-01";
                $endDate = "$year-06-30";
                break;
            case 3:
                $startDate = "$year-07-01";
                $endDate = "$year-09-30";
                break;
            case 4:
                $startDate = "$year-10-01";
                $endDate = "$year-12-31";
                break;
            default:
                throw new Exception('Invalid quarter');
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }
}

// All half-yearly of specific year
if (! function_exists('getHalfYearDates')) {
    function getHalfYearDates($year, $portion)
    {
        switch ($portion) {
            case 1:
                $startDate = "$year-01-01";
                $endDate = "$year-06-30";
                break;
            case 2:
                $startDate = "$year-07-01";
                $endDate = "$year-12-31";
                break;
            default:
                throw new Exception('Invalid portion');
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
        ];
    }
}

// Delete item from exploded array
if (! function_exists('deleteExplodedArrayItem')) {
    function deleteExplodedArrayItem($separator, $subject, $item)
    {
        $explodes = explode($separator, $subject);
        $clean_inventory = [];

        foreach ($explodes as $explode) {
            if (! isset($clean_inventory[$explode])) {
                $clean_inventory[$explode] = 0;
            }

            $clean_inventory[$explode]++;
        }

        // Item can be deleted
        unset($clean_inventory[$item]);

        $saved = [];

        foreach ($clean_inventory as $key => $quantity) {
            $saved = array_merge($saved, array_fill(0, $quantity, $key));
        }

        return implode($separator, $saved);
    }
}

// Add an item to exploded array
if (! function_exists('addItemsToExplodedArray')) {
    function addItemsToExplodedArray($separator, $subject, $items)
    {
        $explodes = explode($separator, $subject);
        $saved = array_merge($explodes, $items);

        return implode($separator, $saved);
    }
}

// Add an item to exploded array
if (! function_exists('getExchangeRate')) {
    function getExchangeRate($baseCurrency, $targetCurrency)
    {
        $baseCurrency = $baseCurrency ?? 'USD';
        $apiKey = config('services.exchangerate.key');
        // ExchangeRate API URL
        $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/pair/{$baseCurrency}/{$targetCurrency}";

        // Create a Guzzle client
        $client = new Client;

        // Perform the GET request
        $response = $client->get($url);

        // Decode the JSON response
        $data = json_decode($response->getBody()->getContents(), true);

        // Check if the answer is valid
        if ($data['result'] === 'success') {
            return $data['conversion_rate'];
        }

        // return ($baseCurrency == 'USD' ? 2885.00 : 0.00035);

        // If the answer is invalid or there is an error
        throw new Exception('Erreur lors de la récupération du taux de change');
    }
}

// Add an item to exploded array
if (! function_exists('showCountries')) {
    function showCountries()
    {
        $response = Http::get('https://restcountries.com/v3.1/all?fields=cca2,idd,flags,name');

        if (! $response->successful()) {
            return [];
        }

        $countriesRaw = $response->json();
        $phoneCodes = [];

        return collect($countriesRaw)
            ->map(function ($country) use (&$phoneCodes) {
                $root = $country['idd']['root'] ?? '';
                $suffix = $country['idd']['suffixes'][0] ?? '';
                $fullPhoneCode = $root.$suffix;

                if (empty($fullPhoneCode) || in_array($fullPhoneCode, $phoneCodes)) {
                    return null;
                }

                $phoneCodes[] = $fullPhoneCode;

                return [
                    'value' => $fullPhoneCode,
                    'name' => $country['name']['common'] ?? '',
                    'code' => $country['cca2'] ?? '',
                    'phone' => $fullPhoneCode,
                    'flag' => $country['flags']['png'] ?? '',
                    'label' => ($country['cca2'] ?? '').' ('.$fullPhoneCode.')',
                ];
            })
            ->filter()
            ->sortBy('label')
            ->values();

        // return abort(500, 'Erreur lors du chargement des pays');

        // return [
        //     [
        //         'value' => '243',
        //         'name' => 'DR Congo',
        //         'code' => 'CD',
        //         'phone' => '+243',
        //         'flag' => '',
        //         'label' => 'CD (+243)',
        //     ],
        //     [
        //         'value' => '33',
        //         'name' => 'France',
        //         'code' => 'FR',
        //         'phone' => '+33',
        //         'flag' => '',
        //         'label' => 'FR (+33)',
        //     ],
        //     [
        //         'value' => '1',
        //         'name' => 'United State',
        //         'code' => 'US',
        //         'phone' => '+1',
        //         'flag' => '',
        //         'label' => 'US (+1)',
        //     ],
        // ];
    }
}

// Paginate an array
if (! function_exists('paginate')) {
    function paginate(array $items, int $perPage = 5, ?int $page = null, $options = [])
    {
        $page = $page ?: (LengthAwarePaginator::resolveCurrentPage() ?: 1);
        $items = collect($items);

        return new LengthAwarePaginator($items->forPage($page, $perPage), $items->count(), $perPage, $page, $options);
    }
}
