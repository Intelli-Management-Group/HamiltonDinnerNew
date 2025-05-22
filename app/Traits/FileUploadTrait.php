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

    public function getFileUrl( $file_path)
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

    public function saveFile($value, $attribute_name = "image",  $destination_path = "",  $disk = "")
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
        if (is_object($value)) {
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

    private function removeFile()
    {
        if (isset($this->attributes[$this->file_attribute_name]) && 
            !empty($this->attributes[$this->file_attribute_name])) {
            
            Storage::disk($this->upload_disk)->delete($this->attributes[$this->file_attribute_name]);
            $this->attributes[$this->file_attribute_name] = null;
        }
    }
}