<?php
/**
 * Copyright (c) 2013-2017
 *
 * @category  Library
 * @package   Dwoo\Plugins\Functions
 * @author    Jordi Boggiano <j.boggiano@seld.be>
 * @author    David Sanchez <david38sanchez@gmail.com>
 * @copyright 2008-2013 Jordi Boggiano
 * @copyright 2013-2017 David Sanchez
 * @license   http://dwoo.org/LICENSE Modified BSD License
 * @version   1.3.3
 * @date      2017-01-07
 * @link      http://dwoo.org/
 */

namespace Dwoo\Plugins\Functions;

use Dwoo\Plugin;

/**
 * Removes all html tags
 * <pre>
 *  * value: the string to process
 *  * addspace: if true, a space is added in place of every removed tag
 *  * allowable_tags: specify tags which should not be stripped
 * </pre>
 * This software is provided 'as-is', without any express or implied warranty.
 * In no event will the authors be held liable for any damages arising from the use of this software.
 */
class PluginPaginate extends Plugin {
    /**
     * @param Compiler    $compiler
     * @param string      $value
     * @param bool        $addspace
     * @param null|string $allowable_tags
     *
     * @return string
     */
    public function process($baseurl, $currpage, $numperpage, $total, $args = null)
    {

        $default = array(
            'alwaysShowFirst' => TRUE,
            'alwaysShowLast' => TRUE,
            'showPrevious' => TRUE,
            'showNext' => TRUE,
            'padding' => 1,
            'urlVar' => 'page'
        );

        $args = !empty($args) && is_array($args) ? array_merge($default, $args) : $default;

        $totalpages = ceil($total / $numperpage);

        $html = '';

        if($totalpages > 1)
        {
            $querystring = $_SERVER['QUERY_STRING'];

            // begin list
            $html = '<div class="pagination pagination-centered"><ul>';

            // add previous link if it's not the first page
            if(!empty($args['showPrevious']) && $currpage > 1)
            {
                parse_str($querystring, $qsvars);
                $qsvars[$args['urlVar']] = !empty($qsvars[$args['urlVar']]) ? $qsvars[$args['urlVar']] - 1 : $currpage - 1;
                $html .= '<li class="first"><a class="prev" href="'.$baseurl.'?'.http_build_query($qsvars).'">Previous</a></li>';
            }

            // figure out where to start the loop
            $diff = $totalpages - ($currpage + $args['padding']);
            if($diff < 0)
            {
                $loopStart = $currpage - $args['padding'] + $diff;
                $loopStart = $loopStart <= 0 ? 1 : $loopStart;
            }
            else
            {
                $padded = $currpage - $args['padding'];
                $loopStart = $padded > 0 ? $padded : 1;
            }

            // show the first page with an elipsis if alwaysShowFirst is true and the current page + the padding is greater than 1
            if(!empty($args['alwaysShowFirst']) && $loopStart > 1)
            {
                $qsvars[$args['urlVar']] = 1;
                $html .= '<li class="pagenum pagenum-first"><a href="'.$baseurl.'?'.http_build_query($qsvars).'">1</a></li>';

                if($loopStart - 1 != 1)
                    $html .= '<li class="disabled ellipsis"><a href="#" onclick="return false;">...</a></li>';
            }

            // figure out where to end the loop
            $diff = $currpage - 1 - $args['padding'];
            if($diff < 0)
            {
                $loopEnd = $currpage + $args['padding'] - $diff;
                $loopEnd = $loopEnd > $totalpages ? $totalpages : $loopEnd;
            }
            else
            {
                $sum = $currpage + $args['padding'];
                $loopEnd = $sum > $totalpages ? $totalpages : $sum;
            }

            for($i = $loopStart; $i <= $loopEnd; ++$i)
            {
                $activeClass = $currpage == $i ? ' active' : '';
                $leftPadClass = $i == $loopStart ? ' pagination-leftpad' : '';
                parse_str($querystring, $qsvars);
                $qsvars[$args['urlVar']] = $i;

                $html .= '<li class="pagenum'.$activeClass.$leftPadClass.'"><a href="'.$baseurl.'?'.http_build_query($qsvars).'">'.$i.'</a></li>';
            }

            if(!empty($args['alwaysShowLast']) && $loopEnd < $totalpages)
            {
                if($loopEnd + 1 != $totalpages)
                    $html .= '<li class="disabled ellipsis"><a href="#" onclick="return false;">...</a></li>';

                $qsvars[$args['urlVar']] = $totalpages;
                $html .= '<li class="pagenum pagenum-last"><a href="'.$baseurl.'?'.http_build_query($qsvars).'">'.$totalpages.'</a></li>';
            }

            if(!empty($args['showNext']) && $currpage < $totalpages)
            {
                parse_str($querystring, $qsvars);
                $qsvars[$args['urlVar']] = !empty($qsvars[$args['urlVar']]) ? $qsvars[$args['urlVar']] + 1 : $currpage + 1;
                $html .= '<li class="last"><a class="next" href="'.$baseurl.'?'.http_build_query($qsvars).'">Next</a></li>';
            }

            $html .= '</ul></div>';
        }

        return $html;

        /*$default = array(
         'alwaysShowFirst' => TRUE,
         'alwaysShowLast' => TRUE,
         'showPrevious' => TRUE,
         'showNext' => TRUE,
         'padding' => 1
     );

     $args = !empty($args) && is_array($args) ? array_merge($default, $args) : $default;

     $totalpages = ceil($total / $numperpage);

     $html = '';
     if($totalpages > 1)
     {
         $querystring = $_SERVER['QUERY_STRING'];

         // begin list
         $html = '';

         // add previous link if it's not the first page
         if(!empty($args['showPrevious']) && $currpage > 1)
         {
             parse_str($querystring, $qsvars);
             $qsvars['page'] = !empty($qsvars['page']) ? $qsvars['page'] - 1 : $currpage - 1;
             $html .= '<a class="prev" href="'.$baseurl.'?'.http_build_query($qsvars).'">Previous</a>';
         }

         // figure out where to start the loop
         $diff = $totalpages - ($currpage + $args['padding']);
         if($diff < 0)
         {
             $loopStart = $currpage - $args['padding'] + $diff;
             $loopStart = $loopStart <= 0 ? 1 : $loopStart;
         }
         else
         {
             $padded = $currpage - $args['padding'];
             $loopStart = $padded > 0 ? $padded : 1;
         }

         // show the first page with an elipsis if alwaysShowFirst is true and the current page + the padding is greater than 1
         if(!empty($args['alwaysShowFirst']) && $loopStart > 1)
         {
             $qsvars['page'] = 1;
             $html .= '<a href="'.$baseurl.'?'.http_build_query($qsvars).'">1</a>';

             if($loopStart - 1 != 1)
                 $html .= '<a href="#" onclick="return false;">...</a>';
         }

         // figure out where to end the loop
         $diff = $currpage - 1 - $args['padding'];
         if($diff < 0)
         {
             $loopEnd = $currpage + $args['padding'] - $diff;
             $loopEnd = $loopEnd > $totalpages ? $totalpages : $loopEnd;
         }
         else
         {
             $sum = $currpage + $args['padding'];
             $loopEnd = $sum > $totalpages ? $totalpages : $sum;
         }

         for($i = $loopStart; $i <= $loopEnd; ++$i)
         {
             $activeClass = $currpage == $i ? ' current' : '';
             $leftPadClass = $i == $loopStart ? ' pagination-leftpad' : '';
             parse_str($querystring, $qsvars);
             $qsvars['page'] = $i;

             $html .= '<a href="'.$baseurl.'?'.http_build_query($qsvars).'" class="'.$activeClass.'">'.$i.'</a>';
         }

         if(!empty($args['alwaysShowLast']) && $loopEnd < $totalpages)
         {
             if($loopEnd + 1 != $totalpages)
                 $html .= '<a href="#" onclick="return false;">...</a>';

             $qsvars['page'] = $totalpages;
             $html .= '<a href="'.$baseurl.'?'.http_build_query($qsvars).'">'.$totalpages.'</a>';
         }

         if(!empty($args['showNext']) && $currpage < $totalpages)
         {
             parse_str($querystring, $qsvars);
             $qsvars['page'] = !empty($qsvars['page']) ? $qsvars['page'] + 1 : $currpage + 1;
             $html .= '<a class="next" href="'.$baseurl.'?'.http_build_query($qsvars).'">Next</a>';
         }

         $html .= '';
     }

     return $html;*/    }
}