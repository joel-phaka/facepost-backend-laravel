<?php

namespace App\Providers;

use App\Traits\CanSortColumns;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MacroServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        Builder::macro('whereLike', function ($attributes, $value) {

            $value = trim(str_replace('%', '', $value));
            $value = !empty($value) ? "%$value%" : '';

            $this->where(function (Builder $query) use ($attributes, $value) {

                foreach (Arr::wrap($attributes) as $attribute) {
                    $query->when(
                        Str::contains($attribute, '.'),
                        function (Builder $query) use ($attribute, $value) {
                            [$relationName, $relationAttribute] = explode('.', $attribute);

                            $query->orWhereHas($relationName, function (Builder $query) use ($relationAttribute, $value) {
                                $query->where($relationAttribute, 'LIKE', $value);
                            });
                        },
                        function (Builder $query) use ($attribute, $value) {
                            $query->orWhere($attribute, 'LIKE', $value);
                        }
                    );
                }
            });

            return $this;
        });


        Builder::macro('sortBy', function ($column, $direction) {
            if ( in_array(CanSortColumns::class, class_uses( get_class( $this->getModel() ) )) ) {
                $sortableColumns = $this->getModel()->getSortableColumns();
                $column = trim($column);
                $direction = trim($direction);

                $this->when(in_array($column, $sortableColumns),
                    function (Builder $q) use ($column, $direction) {
                        return $q->orderBy(
                            $column,
                            !empty($direction) && in_array($direction, ['asc', 'desc']) ? $direction : 'desc'
                        );
                    }
                );
            }

            return $this;
        });
    }
}
