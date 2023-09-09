<?php

namespace App\Traits;

trait HasMeta
{
    public function getMetaAttribute()
    {
        return !empty($this->attributes['meta']) && is_array($arr = json_decode($this->attributes['meta'], true)) ? $arr : [];
    }

    public function setMetaAttribute($value)
    {
        if (is_string($value) && !empty($jsonValue = json_decode(trim($value), true))) {
            $this->attributes['meta'] = json_encode($jsonValue);
        } else if (is_array($value)) {
            $this->attributes['meta'] = json_encode($value);
        } else {
            $this->attributes['meta'] = null;
        }
    }

    public function setMetaValue($key, $value, $save = false)
    {
        $key = trim($key);
        $value = (is_array($value)) ? $value : (is_string($value) ? trim($value) : $value);
        if (!empty($key)) {
            $meta = $this->meta;
            if (!empty($value)) {
                $meta[$key] = $value;
                $this->meta = $meta;

                if ($save) $this->save();

                return true;
            } else {
                if (!empty($meta[$key])) {
                    unset($meta[$key]);

                    if ($save) $this->save();
                }
            }
        }

        return false;
    }

    public function setMetaValueIf($condition, $key, $value, $save = false)
    {
        return $condition && $this->setMetaValue($key, $value, $save);
    }

    public function setMetaValueArray($key, $value, $save = false)
    {
        return $this->setMetaValue($key, empty($value) ? [] : (is_array($value) ? $value : [trim($value)]), $save);
    }

    public function setMetaValueArrayIf($condition, $key, $value, $save = false)
    {
        return $condition && $this->setMetaValueArray($key, $value, $save);
    }

    public function mergeMetaValues(array $metaValues = array(), $save = false)
    {
        $this->meta = array_merge($this->meta, $metaValues);

        if ($save) $this->save();
    }

    public function mergeMetaValuesIf($condition, array $metaValues = array(), $save = false) {
        if ($condition) $this->mergeMetaValues($metaValues, $save);
    }

    public function getMetaValue($key, $default = null)
    {
        return array_key_exists($key, $this->meta) ? $this->meta[$key] : $default;
    }

    public function getMetaValueArray($key)
    {
        return is_array($arrValue = $this->getMetaValue($key)) ? $arrValue : [];
    }
}
