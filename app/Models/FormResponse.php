<?php

namespace App\Models;

use Illuminate\Support\Facades\Storage;
use App\Models\FormMediaAttachments;


use Illuminate\Database\Eloquent\Model;

class FormResponse extends Model
{
    protected $fillable = ['form_type_id', 'form_response', 'created_by' , 'file_name','room_id' , 'is_follow_up_incomplete','follow_up_assigned_to'];

    public $timestamps = true;
    
    protected $casts = [
        'form_response' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
        'updated_at' => 'datetime:Y-m-d H:i:s',
    ];
    
    protected $appends = ['formLink','jsonData'];
    // protected $appends = ['formLink'];

    
    public function formType(){
        return $this->belongsTo(FormType::class , 'form_type_id');
    }
    
    public function getFormLinkAttribute(){
        return Storage::url('public/FormResponses/'.$this->file_name);
    }
    
    public function getJsonDataAttribute(){
        return is_array($this->form_response) ? $this->form_response :(json_decode($this->form_response,true));
    }
    
    public function attachments(){
        return $this->hasMany(FormMediaAttachments::class);
    }
    
}
