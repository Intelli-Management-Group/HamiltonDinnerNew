<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'settings';
    public $timestamps = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'key',
        'display_name',
        'value',
        'details',
        'type',
        'order',
        'group',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'order' => 'integer',
    ];

    /**
     * Determine if the setting's value should be cast as JSON.
     *
     * @return bool
     */
    public function shouldBeJson()
    {
        return in_array($this->type, ['array', 'json', 'object']);
    }

    /**
     * Get the setting value with appropriate casting.
     *
     * @return mixed
     */
    public function getValueAttribute($value)
    {
        if ($this->shouldBeJson() && !empty($value)) {
            return json_decode($value, true);
        }
        
        return $value;
    }

    /**
     * Set the setting value with appropriate formatting.
     *
     * @param mixed $value
     * @return void
     */
    public function setValueAttribute($value)
    {
        if ($this->shouldBeJson() && !is_string($value)) {
            $this->attributes['value'] = json_encode($value);
        } else {
            $this->attributes['value'] = $value;
        }
    }

    /**
     * Get setting by key.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get($key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        
        if ($setting) {
            return $setting->value;
        }
        
        return $default;
    }

    /**
     * Set a setting value by key.
     *
     * @param string $key
     * @param mixed $value
     * @param string|null $group
     * @return \App\Models\Setting
     */
    public static function set($key, $value, $group = null)
    {
        $setting = static::firstOrNew(['key' => $key]);
        $setting->value = $value;
        
        if (!$setting->exists && $group) {
            $setting->group = $group;
        }
        
        $setting->save();
        
        return $setting;
    }

    /**
     * Get all settings as a key-value array.
     *
     * @param string|null $group Filter by group
     * @return array
     */
    public static function getAll($group = null)
    {
        $query = static::query();
        
        if ($group) {
            $query->where('group', $group);
        }
        
        return $query->orderBy('order')->get()
            ->keyBy('key')
            ->transform(function ($setting) {
                return $setting->value;
            })
            ->toArray();
    }

    /**
     * Scope a query to filter settings by group.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $group
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeGroup($query, $group)
    {
        if ($group === null || $group === '') {
            return $query;
        }

        return $query->where('group', $group);
    }

    /**
     * Scope a query to filter settings by type.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeType($query, $type)
    {
        if ($type === null || $type === '') {
            return $query;
        }

        return $query->where('type', $type);
    }

    /**
     * Scope a query to retrieve settings with a matching key,
     * display name, or group to the search term, if it is provided.
     * Otherwise, return the unmodified query.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $searchTerm
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $searchTerm)
    {
        if (!$searchTerm) return $query;

        return $query->where(function ($q) use ($searchTerm) {
            $q->where('key', 'LIKE', "%{$searchTerm}%")
              ->orWhere('display_name', 'LIKE', "%{$searchTerm}%")
              ->orWhere('group', 'LIKE', "%{$searchTerm}%");
        });
    }

    /**
     * Scope a query to order settings by the specified field and direction.
     * If the fields are not provided or invalid, defaults to ordering by 'order' ascending.
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|null $sortBy
     * @param string|null $sortDirection
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOrderByField($query, ?string $sortBy, ?string $sortDirection)
    {
        $allowed = [
            'key',
            'display_name',
            'value',
            'details',
            'type',
            'order',
            'group'
        ];

        $sortDirection = strtolower($sortDirection ?? '') === 'desc' ? 'desc' : 'asc';
        $column = in_array($sortBy, $allowed, true) ? $sortBy : 'order';

        return $query->orderBy($column, $sortDirection);
    }
}