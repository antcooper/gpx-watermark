<?php

namespace antcooper\gpxwatermark;

class Watermark
{
    public function insert($gpxFile)
    {
        // Set payload
        $visibleWatermark = 'Prepared for antjcooper@outlook.com - All content copyright Cicerone Press Limited 9781786310361'; 
        $watermark = 'richardbutler4@hotmail.com - 9781786310361||'; 
        $payload = $this->createPayload($watermark);

        // Check if file exists
        if (!file_exists($gpxFile)) {
            return false;
        }

        // Read in the XML file
        $xmlContent = simplexml_load_file($gpxFile);

        // if (isset($xmlContent->trk)) {
        //     print_r('Track');
        // }
        // if (isset($xmlContent->rte)) {
        //     print_r('Route');
        // }

        $xmlContent->attributes()->creator = "Cicerone Press https://www.cicerone.co.uk";
        
        unset($xmlContent->metadata);
        
        // Loop over the track segment
        if ($xmlContent->trk) {
            $xmlContent->trk->name = "Coledale Horseshoe";
            $xmlContent->trk->desc = $visibleWatermark;
            $xmlContent->trk->src = "https://www.cicerone.co.uk/walking-the-lake-district-fells-buttermere-second";

            foreach ($xmlContent->trk->trkseg as $track) {

                // Loop over eack trkpt in the segment
                foreach ($track->trkpt as $point) {
                    $point->attributes()->lat = preg_replace('/([-0-9]*?\.[0-9]{5})([0-9])/', '${1}9', $point->attributes()->lat);
                    $point->attributes()->lon = preg_replace('/([-0-9]*?\.[0-9]{5})([0-9])/', '${1}9', $point->attributes()->lon);
                }
            }
        }

        if ($xmlContent->rte) {
            $xmlContent->rte->name = "Coledale Horseshoe";
            $xmlContent->rte->desc = $visibleWatermark;
            $xmlContent->rte->src = "https://www.cicerone.co.uk/walking-the-lake-district-fells-buttermere-second";
            
            $p = 0;
            foreach ($xmlContent->rte->rtept as $point) {
                $point->attributes()->lat = number_format((float)$point->attributes()->lat, 5, '.', '').substr($payload[$p], 0, 1);
                $point->attributes()->lon = number_format((float)$point->attributes()->lon, 5, '.', '').substr($payload[$p], 1, 1);
                $p++;
                if ($p >= count($payload)) {
                    $p = 0;
                }
            }
        }
        
        $xmlContent->asXML(public_path('samples/output/short-route.gpx'));

        return true; //$xmlContent;
    }

    public function extract($gpxOrigin, $gpxFile)
    {
        // Check if file exists
        if (!file_exists($gpxFile)) {
            return false;
        }

        // Read in the XML file
        $xmlContent = simplexml_load_file($gpxFile);

        // Loop over a route
        $encodedMessage = [];

        if ($xmlContent->trk) {
            foreach ($xmlContent->trk->trkseg as $track) {

                // Loop over eack trkpt in the segment
                foreach ($track->trkpt as $point) {
                    $latitude = substr(number_format((float)$point->attributes()->lat, 6, '.', ''), -1);
                    $longitude = substr(number_format((float)$point->attributes()->lon, 6, '.', ''), -1);
                    
                    $encodedMessage[] = $latitude . $longitude;
                }
            }
        }

        if ($xmlContent->rte) {
            foreach ($xmlContent->rte->rtept as $point) {
                $latitude = substr(number_format((float)$point->attributes()->lat, 6, '.', ''), -1);
                $longitude = substr(number_format((float)$point->attributes()->lon, 6, '.', ''), -1);
                
                $encodedMessage[] = $latitude . $longitude;
            }
        }

        $message = "";
        foreach($encodedMessage as $letter) {
            $message .= chr($letter+32);
        }

        $messages = explode('|',$message);

        // Try a non-blind extract
        if (!file_exists($gpxOrigin)) {
            return 'Failed to open origin file';
        }

        // Read in the XML file
        $xmlOrigin = simplexml_load_file($gpxOrigin);
        $nonBlindMessage = "";
        if ($xmlOrigin->rte) {
            $p = 0;
            foreach ($xmlOrigin->rte->rtept as $point) {
                $oLat = number_format((float)$point->attributes()->lat, 5, '.', '');
                $oLon = number_format((float)$point->attributes()->lon, 5, '.', '');
  
                $wLat = floor(((float)$xmlContent->rte->rtept[$p]->attributes()->lat * 100000)) / 100000;
                $wLon = floor(((float)$xmlContent->rte->rtept[$p]->attributes()->lon * 100000)) / 100000;
                
                $wLat = preg_match('/[\-0-9]+\.[0-9]{0,5}/', $xmlContent->rte->rtept[$p]->attributes()->lat, $matches);

                $wLat = "0";
                if (isset($matches[0])) {
                    $wLat = number_format((float)$matches[0], 5, '.', '');
                }

                $wLon = preg_match('/[\-0-9]+\.[0-9]{0,5}/', $xmlContent->rte->rtept[$p]->attributes()->lon, $matches);

                $wLon = "0";
                if (isset($matches[0])) {
                    $wLon = number_format((float)$matches[0], 5, '.', '');
                }

                // dd($oLat .'=='.$wLat.' && '.$oLon.'=='.$wLon);

                if (($oLat == $wLat) && ($oLon == $wLon)) {
                    $latitude = substr(number_format((float)$xmlContent->rte->rtept[$p]->attributes()->lat, 6, '.', ''), -1);
                    $longitude = substr(number_format((float)$xmlContent->rte->rtept[$p]->attributes()->lon, 6, '.', ''), -1);
                
                    $asciiCode = $latitude . $longitude;

                    $nonBlindMessage .= chr($asciiCode+32);
                    $p++;
                }
                else {
                    $nonBlindMessage .= '*';
                }
            }
        }

        $messages = explode('|',$nonBlindMessage);
        dump($messages);
    }

    private function createPayload($payload)
    {
        $asciiInput = [];
        for($i=0; $i < strlen($payload); $i++) {
            $asciiInput[] = str_pad((ord(substr($payload, $i)) -32), 2, "0", STR_PAD_LEFT);
        }
        
        // dd($asciiInput);
        return $asciiInput;
    }
}



