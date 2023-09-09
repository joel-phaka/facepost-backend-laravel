<?php

namespace App\Traits;

trait CanSortColumns
{
    /**
     * @return string[]
     */
    public function getSortableColumns(): array
    {
        return isset($this->sortableColumns) && is_array($this->sortableColumns)
            ? $this->sortableColumns
            : ['id', 'created_at'];
    }
}
