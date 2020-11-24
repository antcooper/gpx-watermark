<?php

namespace antcooper\gpxwatermark;

use antcooper\gpxwatermark\FileHandler;

class Embed
{
    private $fileHandler;
    private $watermark;
    private $metadata = null;
    private $creator = null;

    /**
     * Initiate the watermark embed class
     * 
     * @param  string $gpxFile        Path of source file
     * @param  string $outputPath     Destination folder
     * @param  string $watermark      Invisible watermark message
     * @param  array|null $metadata   Header information for the route, accepts name, desc, src
     * @param  string|null $creator   Creator information
     * @return void
     */
    public function __construct($gpxFile, $outputPath, $watermark, $metadata = null, $creator = null)
    {
        // Setup file handler
        $this->fileHandler = new FileHandler($gpxFile, $outputPath);

        // Set metadata and payload properties
        $this->watermark = $watermark;
        $this->metadata = $metadata;
        $this->creator = $creator;
    }

    /**
     * Embed a watermark within file.
     *
     * @param  string $method       Type of embed to perform, should be either 'blind' or 'nonBlind'
     * @return string
     */    
    public function write($method)
    {
        // Set required embed method
        $embedFunction = $method . 'InsertWatermarkInWaypoints';

        // Check the correct embed method exists
        if(!method_exists($this, $embedFunction)) {
            return false;
        }

        // Get a list of GPX files to work on
        $files = $this->fileHandler->getManifest();

        // Encode the message
        $encodedMessage = $this->encode($this->watermark);

        // Iterate over one or more gpx files
        foreach($files as $file) {
            // Read in the XML file
            $xml = new \SimpleXMLElement($file, NULL, TRUE);

            // Set creator attribute if provided
            if ($this->creator) {
                $xml->attributes()->creator = $this->creator;
            }
            
            // Remove any old metadata
            unset($xml->metadata);

            // Set metadata at top level
            $this->setMetadata($xml->metadata, $this->metadata);

            // Check for existance of a track
            if (isset($xml->trk)) {

                // Set metadata on track
                $this->setMetadata($xml->trk, $this->metadata);

                // Loop over each track segment in the track
                foreach ($xml->trk->trkseg as $track) {
                    $this->$embedFunction($track->trkpt, $encodedMessage);
                }
            }

            // Check for existance of a route
            if (isset($xml->rte)) {

                // Set metadata on route
                $this->setMetadata($xml->rte, $this->metadata);

                // Insert payload into route
                $this->$embedFunction($xml->rte->rtept, $encodedMessage);
            }

            // Save file
            $xml->asXML($file);

        }

        // Zip file if it is an archive
        if ($this->fileHandler->isZip) {
            $this->fileHandler->compress();
        }

        return $this->fileHandler->watermarkedFile();
    }

    /**
     * PRIVATE
     */

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


   /**
     * Encode the payload into an array of 2 digit letter codes based on ASCII characterset
     * 
     * @param  string $message
     * @param  string $delimiter
     * @return array
     */
    private function encode($message, $delimiter = '|')
    {
        // Set delimiter on the end of message
        $message = $message . $delimiter;

        $payload = [];
        // Loop over each character in the message
        for($i=0; $i < strlen($message); $i++) {
            // Get the character from string
            $chr = substr($message, $i);

            // Get the ASCII value and subtract 32
            $chr = ord($chr) -32;

            // Pad to two numbers
            $chr = str_pad($chr, 2, "0", STR_PAD_LEFT);

            // Add to message array
            $payload[] = $chr;
        }
        return $payload;
    }

    /**
     * Blind Embed watermark in a route/track of waypoints into 6th decimal place
     * 
     * @param  mixed $waypoints
     * @param  array $payload
     * @return void
     */
    private function blindInsertWatermarkInWaypoints(&$waypoints, $payload)
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
     * Non-Blind Embed watermark in a route/track of waypoints by adding/subtracting payload to LSB
     * 
     * @param  mixed $waypoints
     * @param  array $payload
     * @return void
     */
    private function nonBlindInsertWatermarkInWaypoints(&$waypoints, $payload)
    {
        // Set value on each trackpoint
        $p = 0;
        foreach ($waypoints as $point) {

            // Round lat and lon to 6 decimal places
            $lat = number_format((float)$point->attributes()->lat, 6, '.', '');
            $lon = number_format((float)$point->attributes()->lon, 6, '.', '');

            // Get each digit from the payload character
            $latDigit = (float)substr($payload[$p], 0, 1);
            $lonDigit = (float)substr($payload[$p], 1, 1);

            // Check for numbers greater than 4, reduce by 4 and negate
            if ($latDigit > 4) {
                $latDigit = ($latDigit - 4) * -1;
            }
            
            if ($lonDigit > 4) {
                $lonDigit = ($lonDigit - 4) * -1;
            }

            // Divide by 1000000 to shift to 6th decimal place
            $latDigit = $latDigit / 1000000;
            $lonDigit = $lonDigit / 1000000;

            // Add payload to original co-ordinates
            $lat += $latDigit;
            $lon += $lonDigit;

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
}