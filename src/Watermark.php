<?php

namespace antcooper\gpxwatermark;

use antcooper\gpxwatermark\FileHandler;
use antcooper\gpxwatermark\Payload;

class Watermark
{
    /**
     * Embed a watermark within file.
     *
     * @param  string $gpxFile         Path of source file
     * @param  string $outputPath      Destination of the watermarked file
     * @param  string $watermark       Invisible watermark message
     * @param  array|null $metadata    Header information for the route, accepts name, desc, src
     * @param  string|null $creator    Creator information
     * @return void
     */    
    public function embed($gpxFile, $outputPath, $watermark, $metadata = null, $creator = null)
    {
        // Setup file handler
        $fileHandler = new FileHandler($gpxFile, $outputPath);

        // Get a list of GPX files to work on
        $files = $fileHandler->getManifest();

        // Create encoded payload
        $encodedMessage = Payload::encode($watermark);


        foreach($files as $file) {
            // Read in the XML file
            $xml = new \SimpleXMLElement($file, NULL, TRUE);

            // Set creator attribute
            $xml->attributes()->creator = "Cicerone Press https://www.cicerone.co.uk";
            
            // Remove any old metadata
            unset($xml->metadata);

            // Set metadata at top level
            $this->setMetadata($xml->metadata, $metadata);

            // Check for existance of a track
            if (isset($xml->trk)) {

                // Set metadata on track
                $this->setMetadata($xml->trk, $metadata);

                // Loop over each track segment in the track
                foreach ($xml->trk->trkseg as $track) {
                    $this->insertWatermarkInWaypoints($track->trkpt, $encodedMessage);
                }
            }

            // Check for existance of a route
            if (isset($xml->rte)) {

                // Set metadata on route
                $this->setMetadata($xml->rte, $metadata);

                // Insert payload into route
                $this->insertWatermarkInWaypoints($xml->rte->rtept, $encodedMessage);
            }

            // Save file
            $xml->asXML($file);

        }

        // Zip file if it is an archive
        if ($fileHandler->isZip()) {
            $fileHandler->compress();
        }

        return $fileHandler->watermarkedFile();
    }


    /**
     * Attempt to extract message from GPX file
     * 
     * @param  string $sourceFile  Path to suspect GPX file
     * @return string 
     */
    public function blindExtract($sourceFile)
    {
        // Check if file exists
        if (!file_exists($sourceFile)) {
            throw new \Exception('GPX file does not exist');
        }

        // Read in the XML file
        $xml = new \SimpleXMLElement($sourceFile, NULL, TRUE);

        $waypoints = $this->exportCoordinatesFromWaypoints($xml);
        $encodedMessage = $this->exportEncodedMessageFromWaypoints($waypoints);

        // Decode message
        $message = Payload::decode($encodedMessage);

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
    public function extract($gpxOrigin, $gpxTest)
    {
        // Check if file exists
        if (!file_exists($gpxOrigin) || !file_exists($gpxTest)) {
            throw new \Exception('GPX file does not exist');
        }

        // Read in the Source XML file
        $xml = new \SimpleXMLElement($gpxOrigin, NULL, TRUE);

        // Get waypoints from source 
        $sourcePoints = $this->exportCoordinatesFromWaypoints($xml);

        // Read in the Test XML file
        $xml = new \SimpleXMLElement($gpxTest, NULL, TRUE);

        // Get waypoints from source 
        $testPoints = $this->exportCoordinatesFromWaypoints($xml);

        $encodedMessage = [];
        $t = 0;
        // Loop over source file
        for($s=0; $s < count($sourcePoints); $s++) {

            // Round source coordinates to 5 decimal places
            $sLat = number_format((float)$sourcePoints[$s][0], 5, '.', '');
            $sLon = number_format((float)$sourcePoints[$s][1], 5, '.', '');

            // Get first 5 decimal places from test point, round up if negative
            if ($testPoints[$t][0] >= 0) {
                $tLat = floor(((float)$testPoints[$t][0] * 100000)) / 100000;
            }
            else {
                $tLat = ceil(((float)$testPoints[$t][0] * 100000)) / 100000;
            }
            if ($testPoints[$t][1] >= 0) {
                $tLon = floor(((float)$testPoints[$t][1] * 100000)) / 100000;
            }
            else {
                $tLon = ceil(((float)$testPoints[$t][1] * 100000)) / 100000;
            }

            // If the 5dp waypoint position is same in source and test
            if ($sLat == $tLat && $sLon == $tLon) {

                $asciiCode = substr($testPoints[$t][0], -1);
                $asciiCode .= substr($testPoints[$t][1], -1);

                // Save the 6th decimal place from test
                $encodedMessage[] = $asciiCode;

                // Move to next point in test file
                $t++;
            }
            else {
                $encodedMessage[] = null;
            }
        }

        // Decode message
        $message = Payload::decode($encodedMessage);

        // Split long message string into array
        $messages = explode('|',$message);

        return $messages;
    }

    /**
     * PRIVATE
     */

    /**
     * Embed watermark in a route/track of waypoints
     * 
     * @param  mixed $waypoints
     * @param  array $payload
     * @return void
     */
    private function insertWatermarkInWaypoints(&$waypoints, $payload)
    {
        // Set value on each trackpoint
        $p = 0;
        foreach ($waypoints as $point) {
            // Round lat and lon to 5 decimal places
            $lat = number_format((float)$point->attributes()->lat, 5, '.', '');
            $lon = number_format((float)$point->attributes()->lon, 5, '.', '');

            // Append each digit from payload character to end of co-ordinate
            $lat .= substr($payload[$p], 0, 1);
            $lon .= substr($payload[$p], 1, 1);

            // Overwrite current attributes
            $point->attributes()->lat = $lat;
            $point->attributes()->lon = $lon;

            // Move to next payload character
            $p++;

            // If we're at the end of the payload
            if ($p >= count($payload)) {
                // Start back at the beginning
                $p = 0;
            }
        }
    }
    

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
     * Extract encoded message from waypoints
     * 
     * @param  mixed $waypoints
     * @param  array $encodedMessage
     * @return array watermark character codes
     */
    private function exportEncodedMessageFromWaypoints($waypoints)
    {
        $encodedMessage = [];
        foreach ($waypoints as $point) {
            // Get the last digit
            $lat = substr($point[0], -1);
            $lon = substr($point[1], -1);

            // Concatenate digits to a single number
            $encodedMessage[] = $lat . $lon;
        }

        return $encodedMessage;
    }
    
    /**
     * Set metadata tags for a given parent node
     * 
     * @param  object $node
     * @param  array|null $metadata
     * @return void
     */
    private function setMetadata(&$node, $metadata)
    {
        unset($node->extensions);

        foreach($metadata as $key => $value) {
            $node->{$key} = $value;
        }
    }


}



