<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class FormType extends Model
{
   
    use SoftDeletes;

    protected $table = 'form_types';

    protected $fillable = [
        'name',
        'allow_print',
        'allow_mail'
       
    ];

}