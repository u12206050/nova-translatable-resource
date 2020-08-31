<?php

namespace Day4\Nova;

use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;
use Illuminate\Support\Facades\DB;

abstract class TranslatableResource extends NovaResource
{
    /**
     * Return the location to redirect the user after creation.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Laravel\Nova\Resource  $resource
     * @return string
     */
    public static function redirectAfterCreate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }

    /**
     * Return the location to redirect the user after update.
     *
     * @param  \Laravel\Nova\Http\Requests\NovaRequest  $request
     * @param  \Laravel\Nova\Resource  $resource
     * @return string
     */
    public static function redirectAfterUpdate(NovaRequest $request, $resource)
    {
        return '/resources/'.static::uriKey();
    }

    protected static function applyTranslation($query, $columns, $callback, $values = null) {
        $base = $query->getModel();
        $baseTable = $base->getTable();
        $translatedTable = false;
        $attrs = $base->translatedAttributes;
        if (!empty($attrs)) {
            $translatedClass = static::$model . "Translation";
            $T = new $translatedClass();
            $translatedTable = $T->getTable();
            $translatedTableId = "$translatedTable." . str_replace('_translations', '_id', $translatedTable);

            $subQuery = DB::table($translatedTable)->select($translatedTableId);
            foreach ($columns as $column) {
                if (in_array($column, $attrs)) {
                    if (!$base->useTranslationFallback) {
                        $subQuery->addSelect($column);
                    } else $subQuery->addSelect(DB::raw("GROUP_CONCAT($column) as $column"));
                }
            }

            if (!$base->useTranslationFallback) {
                $subQuery->where('locale', '=', app()->getLocale());
                $subQuery->orWhere('locale', '=', config('translatable.fallback_locale'));
            } else $subQuery->groupBy(str_replace('_translations', '_id', $translatedTable));

            $query->joinSub($subQuery, $translatedTable, function ($join) use ($translatedTable, $translatedTableId, $baseTable) {
                $join->on("$translatedTableId", '=', "$baseTable.id");
            });
        }

        foreach ($columns as $column) {
            if ($translatedTable && in_array($column, $attrs)) {
                $nsColumn = "$translatedTable.$column";
            } else $nsColumn = "$baseTable.$column";

            if (isset($values)) $callback($query, $nsColumn, $values[$column]);
            else $callback($query, $nsColumn);
        }

        return $query;
    }

    /**
     * Apply the search query to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applySearch($query, $search)
    {
        $connectionType = $query->getModel()->getConnection()->getDriverName();
        $likeOperator = $connectionType == 'pgsql' ? 'ilike' : 'like';

        $searchable = static::searchableColumns();
        if (empty($searchable)) return $query;

        return static::applyTranslation($query, $searchable, function($query, $column) use ($likeOperator, $search) {
            $query->orWhere($column, $likeOperator, '%'.$search.'%');
        });
    }

    /**
     * Apply any applicable orderings to the query.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  array  $orderings
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected static function applyOrderings($query, array $orderings)
    {
        $orderings = array_filter($orderings);

        if (empty($orderings)) {
            return empty($query->getQuery()->orders)
                        ? $query->latest($query->getModel()->getQualifiedKeyName())
                        : $query;
        }

        return static::applyTranslation($query, array_keys($orderings), function($query, $column, $direction) {
            $query->orderBy($column, $direction);
        }, $orderings);
    }
}
