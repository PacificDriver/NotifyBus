<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'description',
        'is_encrypted',
    ];

    /**
     * Получить значение настройки с автоматической расшифровкой
     */
    public function getValueAttribute($value)
    {
        if ($this->is_encrypted && !empty($value)) {
            try {
                return Crypt::decryptString($value);
            } catch (\Exception $e) {
                return $value;
            }
        }
        
        // Преобразуем значение в зависимости от типа
        switch ($this->type) {
            case 'boolean':
                return filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $value;
            case 'json':
                return json_decode($value, true);
            default:
                return $value;
        }
    }

    /**
     * Установить значение настройки с автоматическим шифрованием
     */
    public function setValueAttribute($value)
    {
        if ($this->is_encrypted && !empty($value)) {
            $this->attributes['value'] = Crypt::encryptString($value);
        } else {
            // Преобразуем значение в зависимости от типа
            switch ($this->type) {
                case 'boolean':
                    $this->attributes['value'] = $value ? '1' : '0';
                    break;
                case 'integer':
                    $this->attributes['value'] = (string) $value;
                    break;
                case 'json':
                    $this->attributes['value'] = json_encode($value);
                    break;
                default:
                    $this->attributes['value'] = $value;
            }
        }
    }

    /**
     * Получить настройку по ключу
     */
    public static function get(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    /**
     * Установить настройку
     */
    public static function set(string $key, $value, string $group = 'general', string $type = 'string', bool $encrypted = false): void
    {
        static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'group' => $group,
                'type' => $type,
                'is_encrypted' => $encrypted,
            ]
        );
    }

    /**
     * Получить все настройки группы
     */
    public static function getGroup(string $group): array
    {
        return static::where('group', $group)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->key => $setting->value];
            })
            ->toArray();
    }

    /**
     * Сохранить настройки группы
     */
    public static function setGroup(string $group, array $settings, bool $encrypted = false): void
    {
        foreach ($settings as $key => $value) {
            static::set($key, $value, $group, 'string', $encrypted);
        }
    }
}

