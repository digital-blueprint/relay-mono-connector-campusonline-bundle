<?php

declare(strict_types=1);

namespace Dbp\Relay\MonoConnectorCampusonlineBundle\Service;

class Tools
{
    /**
     * Takes a short semester key and converts it a long one so it can be used for the tuition fee API.
     * In some contexts in CO where a payment link can be generated only the short form is available.
     * A long key will pass through as is.
     *
     * @param string $semester e.g. "21W"
     *
     * @return string e.g. "2021W"
     */
    public static function convertSemesterToSemesterKey(string $semester): string
    {
        $term = preg_replace('/^[^SW]*([SW])[^SW]*$/', '$1', $semester);
        $year = preg_replace('/^[^0-9]*([0-9]{2,4})[^0-9]*$/', '$1', $semester);
        if (strlen($year) === 2) {
            // first tuition fee in CAMPUSonline is "1950W"
            if ((int) $year >= 50) {
                $year = '19'.$year;
            } else {
                $year = '20'.$year;
            }
        }
        $semesterKey = $year.$term;

        return $semesterKey;
    }
}
