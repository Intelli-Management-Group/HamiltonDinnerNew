<?php

namespace App\Traits;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

trait FileUploadTrait
{
      protected $file_meta_data_attributes = [];
    protected $file_attribute_name       = "";
    protected $upload_disk;

    public function getFileUrl($file_path)
    {

        $disk = Config::get('filesystems.default');

        $tmp       = explode('.', $file_path);
        $extension = end($tmp);

        $name             = basename($file_path, "." . $extension);
        $destination_path = dirname($file_path) . "/";

        try {
            return $this->getDiskUrl($file_path);
        } catch (\InvalidArgumentException $e) {
            return "";
        }
    }

    public function saveFile($value, $attribute_name = "image", $destination_path = "", $disk = "")
    {

        $this->file_attribute_name = $attribute_name;

        if (empty($disk)) {
            $this->upload_disk = $disk = Config::get('filesystems.default');
        } else {
            $this->upload_disk = $disk;
        }

        $this->removeFile();
        if ($value == null) {
            return false;
        }

        if (is_object($value)) {

            $filename = Str::slug(Config('app.name')) . "-" . md5($value . time());
            $fileext  = '.' . $value->getClientOriginalExtension();
            $this->storeFileInDisk($value, $destination_path . '/' . $filename . $fileext);
            $this->attributes[$this->file_attribute_name] = $destination_path . '/' . $filename . $fileext;


            return true;
        } elseif (!empty($value) && is_string($value)) {
            $this->attributes[$this->file_attribute_name] = $value;
            return true;
        } else {
            return false;
        }
    }

    private function storeFileInDisk($value, $destination_path)
    {
        $disk       = Config::get('filesystems.default');
        $path_parts = pathinfo($destination_path);

        if (is_object($value)) {
            $filename = $path_parts['filename'] . "." . $path_parts['extension'];
            $value->storeAs($path_parts['dirname'], $filename, $disk);

            return true;
        } elseif (!empty($value) && is_string($value)) {
            $this->attributes[$this->file_attribute_name] = $value;
        } else {
            return false;
        }
    }

    private function removeFile()
    {
        Storage::disk($this->upload_disk)->delete($this->file_attribute_name);
        $this->attributes[$this->file_attribute_name] = null;
    }

    private function getDiskUrl($file_path)
    {
        if ($this->upload_disk == 's3') {
            $base_url = Config::get("filesystems.disks.s3.cloudfront_url");
            if (!empty($base_url)) {
                return $base_url . "/" . $file_path;
            } else {
                return Storage::disk($this->upload_disk)->url($file_path);
            }
        } else {
            return Storage::disk($this->upload_disk)->url($file_path);
        }
    }
}