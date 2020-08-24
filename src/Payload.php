<?php

namespace antcooper\gpxwatermark;

class Payload
{
    /**
     * Encode the payload into an array of 2 digit letter codes based on ASCII characterset
     * 
     * @return array;
     */
    public static function encode($message, $delimiter = '||')
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


    public static function decode($payload)
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