<?php

namespace antcooper\gpxwatermark;

class FileHandler
{
    public  $isZip = false;
    private $source = '';
    private $destination = '';
    private $watermarkedFile = '';
    private $files = [];
    private $unzipPath = '';


    /**
     * Initiate the file handler
     * 
     * @param  string $source        Path of source file
     * @param  string $destination   Destination folder
     * @return void
     */
    public function __construct($source, $destination)
    {
        // Check if file exists
        if (!file_exists($source)) {
            throw new \Exception('Source GPX file does not exist');
        }

        // If destination does not exist
        if (!is_dir($destination)) {
            // Create directory with global read/write
            if (!mkdir($destination, 0775, true)) {
                throw new \Exception('Failed to make output directory');
            }
        }

        $this->source = $source;
        $this->destination = $destination;

        $this->watermarkedFile = $this->destination.'/'.basename($this->source);
        copy($source, $this->watermarkedFile);
    }


    /**
     * Get the path of GPX files to watermark
     *
     * @return array
     */    
    public function getManifest()
    {
        $this->files = [$this->watermarkedFile];

        // Check for zip files
        $zip = new \ZipArchive;
        if ($zip->open($this->watermarkedFile) === TRUE) {
            $this->isZip = true;
            $this->unzipPath = $this->destination.'/'.basename($this->source, '.zip');
            $zip->extractTo($this->unzipPath);
            $zip->close();

            // Return recursive array of all GPX files in archive
            $this->files = $this->getDirContents($this->unzipPath);
        }

        // Return a single file path if not a zip
        return $this->files;
    }


    /**
     * Pass back watermarked filename
     * 
     * @return string
     */
    public function watermarkedFile()
    {
        return $this->watermarkedFile;
    }


    /**
     * I overwrite the original files in Zip with watermarked ones
     * 
     * @return void
     */
    public function compress()
    {
        $zip = new \ZipArchive;
        if ($zip->open($this->watermarkedFile, \ZipArchive::OVERWRITE) === TRUE) {
            // Loop over all files
            foreach($this->files as $file) {
                // Overwrite the same file with new watermarked one
                $zip->addFile($file, str_replace($this->unzipPath.'/', '', $file));
            }
 
            // All files are added, so close the zip file.
            $zip->close();

            // Remove unzipped files
            $this->deleteDir($this->unzipPath);
        }
    }


    /**
     * Recursively get GPX files from a folder structure
     *
     * @param  string $dir
     * @return array
     */    
    private function getDirContents($dir, &$results = array()) {
        $files = scandir($dir);
    
        foreach ($files as $key => $value) {
            $path = realpath($dir . DIRECTORY_SEPARATOR . $value);
            if (!is_dir($path) && substr($path, -3) == 'gpx') {
                $results[] = $path;
            } else if ($value != "." && $value != "..") {
                self::getDirContents($path, $results);
            }
        }
    
        return $results;
    }


    /**
     * Recursively delete temporary zip folder
     * 
     * @param  string $dir
     * @return bool
     */
    private function deleteDir($dir)
    {
        $files = array_diff(scandir($dir), array('.','..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->deleteDir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}