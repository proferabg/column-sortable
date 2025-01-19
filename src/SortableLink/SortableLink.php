<?php

namespace proferabg\SortableLink;

use Exception;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;
use proferabg\SortableLink\Exceptions\SortableLinkException;

/**
 * Class SortableLink
 * @package proferabg\SortableLink
 */
class SortableLink
{

    /**
     * @param array $parameters
     *
     * @return string
     * @throws SortableLinkException
     */
    public static function render(array $parameters): string {
        list($sortColumn, $sortParameter, $title, $queryParameters, $anchorAttributes) = self::parseParameters($parameters);

        $title = self::applyFormatting($title, $sortColumn);

        if ($mergeTitleAs = config('sortablelink.inject_title_as', null)) {
            request()->merge([$mergeTitleAs => $title]);
        }

        list($icon, $direction) = self::determineDirection($sortColumn, $sortParameter);

        $trailingTag = self::formTrailingTag($icon);

        $anchorClass = self::getAnchorClass($sortParameter, $anchorAttributes);

        $anchorAttributesString = self::buildAnchorAttributesString($anchorAttributes);

        $queryString = self::buildQueryString($queryParameters, $sortParameter, $direction);

        $url = self::buildUrl($queryString, $anchorAttributes);

        return '<a'.$anchorClass.' href="'.$url.'"'.$anchorAttributesString.'>'.e($title).$trailingTag;
    }


    /**
     * @param array $parameters
     *
     * @return array
     * @throws SortableLinkException
     */
    public static function parseParameters(array $parameters): array {
        //TODO: let 2nd parameter be both title, or default query parameters
        //TODO: needs some checks before determining $title
        $explodeResult    = self::explodeSortParameter($parameters[0]);
        $sortColumn       = (empty($explodeResult)) ? $parameters[0] : $explodeResult[1];
        $title            = (count($parameters) === 1) ? null : $parameters[1];
        $queryParameters  = (isset($parameters[2]) && is_array($parameters[2])) ? $parameters[2] : [];
        $anchorAttributes = (isset($parameters[3]) && is_array($parameters[3])) ? $parameters[3] : [];

        return [$sortColumn, $parameters[0], $title, $queryParameters, $anchorAttributes];
    }


    /**
     * Explodes parameter if possible and returns array [column, relation]
     * Empty array is returned if explode could not run eg: separator was not found.
     *
     * @param $parameter
     *
     * @return array
     *
     * @throws SortableLinkException
     */
    public static function explodeSortParameter($parameter): array {
        $separator = config('sortablelink.uri_relation_column_separator', '.');

        if (Str::contains($parameter, $separator)) {
            $oneToOneSort = explode($separator, $parameter);
            if (count($oneToOneSort) !== 2) {
                throw new SortableLinkException();
            }

            return $oneToOneSort;
        }

        return [];
    }


    /**
     * @param string|Htmlable|null $title
     * @param string $sortColumn
     *
     * @return string
     */
    private static function applyFormatting($title, $sortColumn)
    {
        if ($title instanceof Htmlable) {
            return $title;
        }

        if ($title === null) {
            $title = $sortColumn;
        } elseif ( ! config('sortablelink.format_custom_titles', true)){
            return $title;
        }

        $formatting_function = config('sortablelink.formatting_function', null);
        if ( ! is_null($formatting_function) && function_exists($formatting_function)) {
            $title = call_user_func($formatting_function, $title);
        }

        return $title;
    }


    /**
     * @param $sortColumn
     * @param $sortParameter
     *
     * @return array
     */
    private static function determineDirection($sortColumn, $sortParameter): array {
        $icon = self::selectIcon($sortColumn);
        if(request()->has('sort')) {
            $sorts = explode(",", request()->get("sort"));
            foreach ($sorts as $sort) {
                if(str_replace("-", "", $sort) === $sortParameter) {
                    $direction = !str_starts_with($sort, "-");
                    $icon .= ($direction ? config('sortablelink.asc_suffix', '-asc') : config('sortablelink.desc_suffix', '-desc'));
                    return [$icon, $direction];
                }
            }
        }

        return [config('sortablelink.sortable_icon'), null];
    }


    /**
     * @param $sortColumn
     *
     * @return string
     */
    private static function selectIcon($sortColumn): string {
        $icon = config('sortablelink.default_icon_set');

        foreach (config('sortablelink.columns', []) as $value) {
            if (in_array($sortColumn, $value['rows'])) {
                $icon = $value['class'];
            }
        }

        return $icon;
    }


    /**
     * @param $icon
     *
     * @return string
     */
    private static function formTrailingTag($icon): string {
        if ( ! config('sortablelink.enable_icons', true)) {
            return '</a>';
        }

        $iconAndTextSeparator = config('sortablelink.icon_text_separator', '');

        $clickableIcon = config('sortablelink.clickable_icon', false);
        $trailingTag   = $iconAndTextSeparator.'<i class="'.$icon.'"></i>'.'</a>';

        if ($clickableIcon === false) {
            $trailingTag = '</a>'.$iconAndTextSeparator.'<i class="'.$icon.'"></i>';

            return $trailingTag;
        }

        return $trailingTag;
    }


    /**
     * Take care of special case, when `class` is passed to the sortablelink.
     *
     * @param       $sortColumn
     *
     * @param array $anchorAttributes
     *
     * @return string
     */
    private static function getAnchorClass($sortColumn, &$anchorAttributes = []): string {
        $class = [];

        $anchorClass = config('sortablelink.anchor_class', null);
        if ($anchorClass !== null) {
            $class[] = $anchorClass;
        }

        $activeClass = config('sortablelink.active_anchor_class', null);
        if ($activeClass !== null && self::shouldShowActive($sortColumn)) {
            $class[] = $activeClass;
        }

        $directionClassPrefix = config('sortablelink.direction_anchor_class_prefix', null);
        if ($directionClassPrefix !== null && self::shouldShowActive($sortColumn)) {
            $class[] = $directionClassPrefix.(request()->get('direction') === 'asc' ? config('sortablelink.asc_suffix', '-asc') :
                    config('sortablelink.desc_suffix', '-desc'));
        }

        if (isset($anchorAttributes['class'])) {
            $class = array_merge($class, explode(' ', $anchorAttributes['class']));
            unset($anchorAttributes['class']);
        }

        return (empty($class)) ? '' : ' class="'.implode(' ', $class).'"';
    }


    /**
     * @param $sortColumn
     *
     * @return boolean
     */
    private static function shouldShowActive($sortColumn): bool {
        if(request()->has('sort')){
            $sorts = explode(",", request()->get('sort'));
            foreach ($sorts as $sort){
                if(str_replace("-", "", $sort) == $sortColumn)
                    return true;
            }
        }
        return false;
    }


    /**
     * @param $queryParameters
     * @param $sortParameter
     * @param $direction
     *
     * @return string
     */
    private static function buildQueryString($queryParameters, $sortParameter, $direction): string {
        $checkStrlenOrArray = function ($element) {
            return is_array($element) ? $element : strlen($element);
        };

        $finalSorts = [];
        $found = false;
        if(request()->has("sort")) {
            $sorts = explode(",", request()->get("sort"));
            foreach ($sorts as $sort){
                // sort already has asc, switch to desc
                if($sort === $sortParameter) {
                    $found = true;
                    $finalSorts[] = "-" . $sortParameter;
                }
                // sort already has desc, unset
                else if($sort === "-" . $sortParameter) {
                    $found = true;
                }
                // not this sort column
                else {
                    $finalSorts[] = $sort;
                }
            }
        }

        // add initial asc sort
        if(!$found){
            $finalSorts[] = $sortParameter;
        }

        $persistParameters = array_filter(request()->except('sort', 'page'), $checkStrlenOrArray);

        if(count($finalSorts) > 0){
            return http_build_query(array_merge($queryParameters, $persistParameters, [
                'sort' => implode(",", $finalSorts),
            ]));
        } else {
            return http_build_query(array_merge($queryParameters, $persistParameters));
        }
    }


    private static function buildAnchorAttributesString($anchorAttributes): string {
        if (empty($anchorAttributes)) {
            return '';
        }

        unset($anchorAttributes['href']);

        $attributes = [];
        foreach ($anchorAttributes as $k => $v) {
            $attributes[] = $k.('' != $v ? '="'.$v.'"' : '');
        }

        return ' '.implode(' ', $attributes);
    }

    private static function buildUrl($queryString, $anchorAttributes)
    {
        if(!isset($anchorAttributes['href']))
        {
            return url(request()->path() . "?" . $queryString);
        }
        else
        {
            return url($anchorAttributes['href'] . "?" . $queryString);
        }
    }

}
