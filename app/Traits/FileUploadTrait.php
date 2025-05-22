<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait FileUploadTrait
{
    protected string $file_attribute_name = "";
    protected ?string $upload_disk = null;
    
    // Define allowed image MIME types
    protected array $allowed_image_types = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/jpg',
        'image/webp',
        'image/svg+xml',
        'image/bmp'
    ];

    /**
     * Get the URL for a file path
     *
     * @param string|null $file_path
     * @return string
     */
    public function getFileUrl($file_path)
    {
        if (empty($file_path)) {
            return "";
        }

        try {
            return Storage::url($file_path);
        } catch (\Exception $e) {
            return "";
        }
    }

    /**
     * Save a file to storage
     *
     * @param mixed $value
     * @param string $attribute_name
     * @param string $destination_path
     * @param string $disk
     * @return bool|string Returns true on success, error message on failure
     */
    public function saveFile($value, $attribute_name = "image", $destination_path = "", $disk = "")
    {
        $this->file_attribute_name = $attribute_name;
        $this->upload_disk = !empty($disk) ? $disk : Config::get('filesystems.default');

        // Remove existing file if present
        $this->removeFile();

        // Handle null values
        if ($value === null) {
            return false;
        }

        // Handle uploaded files
        if (is_object($value) && $value instanceof UploadedFile) {
            // Check if the file is an image
            if (!$this->isImage($value)) {
                return "Only image files are allowed";
            }
            
            $appName = Str::slug(Config::get('app.name'));
            $filename = "{$appName}-" . md5($value->getClientOriginalName() . time());
            $fileext = '.' . $value->getClientOriginalExtension();
            $filepath = "{$destination_path}/{$filename}{$fileext}";
            
            // Store file in disk
            $value->storeAs(
                $destination_path, 
                "{$filename}{$fileext}", 
                $this->upload_disk
            );
            
            $this->attributes[$this->file_attribute_name] = $filepath;
            return true;
        } 
        
        // Handle string values (paths)
        if (is_string($value) && !empty($value)) {
            $this->attributes[$this->file_attribute_name] = $value;
            return true;
        }

        return false;
    }

    /**
     * Delete a file from storage
     *
     * @return void
     */
    private function removeFile()
    {
        if (isset($this->attributes[$this->file_attribute_name]) && 
            !empty($this->attributes[$this->file_attribute_name])) {
            
            Storage::disk($this->upload_disk)->delete($this->attributes[$this->file_attribute_name]);
            $this->attributes[$this->file_attribute_name] = null;
        }
    }
    
    /**
     * Check if file is an image
     *
     * @param UploadedFile $file
     * @return bool
     */
    private function isImage(UploadedFile $file)
    {
        return in_array($file->getMimeType(), $this->allowed_image_types);
    }
}