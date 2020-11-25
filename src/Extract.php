<?php

namespace antcooper\gpxwatermark;

use antcooper\gpxwatermark\FileHandler;

class Extract
{
    /**
     * Attempt to extract message from GPX file
     * 
     * @param  string $sourceFile  Path to suspect GPX file
     * @return string 
     */
    public function blind($sourceFile)
    {
        // Check if file exists
        if (!file_exists($sourceFile)) {
            throw new \Exception('GPX file does not exist');
        }

        // Read in the XML file
        $xml = new \SimpleXMLElement($sourceFile, NULL, TRUE);

        $waypoints = $this->exportCoordinatesFromWaypoints($xml);

        $encodedMessage = [];
        foreach ($waypoints as $point) {
            // Get the sixth digit
            preg_match('/(-?\d+\.\d{5})(\d)/A', $point[0], $lat);
            preg_match('/(-?\d+\.\d{5})(\d)/A', $point[1], $lon);

            // If both attributes have a matching sixth digit
            if (($lat && count($lat) == 3) && ($lon && count($lon) == 3)) {
                // Concatenate digits to a single number
                $encodedMessage[] = $lat[2] . $lon[2];
            }
        }

        // Decode message
        $message = $this->decode($encodedMessage);

        // Split long message string into array
        $messages = explode('|',$message);

        return $messages;
    }

    /**
     * Attempt a non-blind extract given the original file and a suspect
     * Assumes that the test file may have had some waypoints trimmed
     * 
     * @param  string $gpxOrigin  The original GPX source file
     * @param  string $gpxTest    The test suspect file
     * @return array
     */
    public function nonBlind($gpxOrigin, $gpxTest)
    {
        // Check if file exists
        if (!file_exists($gpxOrigin) | !file_exists($gpxTest)) {
            throw new \Exception('GPX file does not exist');
        }

        // Read in the Source XML file
        $xml = new \SimpleXMLElement($gpxOrigin, NULL, TRUE);

        // Get waypoints from source 
        $sourcePoints = $this->exportCoordinatesFromWaypoints($xml);

        // Read in the Test XML file
        $xml = new \SimpleXMLElement($gpxTest, NULL, TRUE);

        // Get waypoints from test file 
        $testPoints = $this->exportCoordinatesFromWaypoints($xml);

        $encodedMessage = [];
        $t = 0;
        // Loop over source file
        for($s=0; $s < count($sourcePoints); $s++) {
            
            // If the waypoint from the test file contains a payload character then 
            // the lat and lon should be within the bounds of -0.000005 to +0.000004

            // Retreive the source co-ordinates, rounded to six decimal places and multiply by 1 million to create an integer
            $sLat = number_format((float)$sourcePoints[$s][0], 6, '.', '') * 1000000;
            $sLon = number_format((float)$sourcePoints[$s][1], 6, '.', '') * 1000000;

            $tLat = number_format((float)$testPoints[$t][0], 6, '.', '') * 1000000;
            $tLon = number_format((float)$testPoints[$t][1], 6, '.', '') * 1000000;

            // Calculate the difference between test and source locations
            // Correct for negative coordinates
            if ($sourcePoints[$s][0] >= 0) {
                $latDiff = (int)floor($tLat - $sLat);
            }
            else {
                $latDiff = (int)ceil($sLat - $tLat) *-1;
            }

            if ($sourcePoints[$s][1] >= 0) {
                $lonDiff = (int)floor($tLon - $sLon);
            }
            else {
                $lonDiff = (int)ceil($sLon - $tLon) *-1;
            }
            
            // For negative difference, minus 4 and negate to shift back to payload character digit
            if ($latDiff < 0) {
                $latDiff = ($latDiff - 4) *-1;
            }
            if ($lonDiff < 0) {
                $lonDiff = ($lonDiff - 4) *-1;
            }


            // If the differences are within the range 0-9 then it is a likely match
            if (($latDiff >= 0 && $latDiff <= 9) && ($lonDiff >= 0 && $lonDiff <= 9)) {
                $encodedMessage[] = $latDiff.$lonDiff;

                // Move to next point in test file
                $t++;

            }
            else {
                $encodedMessage[] = null;
            }
        }

        // Decode message
        $message = $this->decode($encodedMessage);

        // Split long message string into array
        $messages = explode('|',$message);

        return $messages;
    }    


    /**
     * PRIVATE
     */
    
    /**
     * Extract waypoints in a route/track
     * 
     * @param  mixed $xml
     * @return array 2 dimensional array with [lat,lon]
     */
    private function exportCoordinatesFromWaypoints($xml)
    {
        $coordinates = [];

        if ($xml->trk) {
            foreach ($xml->trk->trkseg as $track) {
                foreach ($track->trkpt as $point) {
                    // Round the co-ordinates to 6 decimal places
                    $coordinates[] = [
                        number_format((float)$point->attributes()->lat, 6, '.', ''),
                        number_format((float)$point->attributes()->lon, 6, '.', '')
                    ];
                }
            }
        }
        else {
            foreach ($xml->rte->rtept as $point) {
                // Round the co-ordinates to 6 decimal places
                $coordinates[] = [
                    number_format((float)$point->attributes()->lat, 6, '.', ''),
                    number_format((float)$point->attributes()->lon, 6, '.', '')
                ];
            }
        }

        return $coordinates;        
    }

    /**
     * Decode the numeric message into a string
     * 
     * @param  array $payload
     * @return string
     */
    private function decode($payload)
    {
        $message = "";
        foreach($payload as $letter) {
            if ($letter) {
                $message .= chr($letter+32);
            }
            else {
                $message .= '*';
            }
        }

        return $message;
    }    
}