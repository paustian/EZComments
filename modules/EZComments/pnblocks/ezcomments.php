<?php
/**
 * $Id$
 * 
 * * EZComments *
 * 
 * Attach comments to any module calling hooks
 * 
 * 
 * * License *
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License (GPL)
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 *
 * @author Joerg Napp <jnapp@users.sourceforge.net>
 * @author Mark West <markwest at zikula dot org>
 * @author Jean-Michel Vedrine
 * @author Florian Schie�l <florian.schiessl at ifs-net.de>
 * @author Frank Schummertz
 * @version 1.6
 * @link http://code.zikula.org/ezcomments/ Support and documentation
 * @license http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package Zikula_3rdParty_Modules
 * @subpackage EZComments
 */

/**
 * initialise block
 */
function EZComments_EZCommentsblock_init()
{ 
    // Security
    pnSecAddSchema('EZComments:EZCommentsblock:', 'Block title::');
    return true;
} 

/**
 * get information on block
 * 
 * @return array       The block information
 */
function EZComments_EZCommentsblock_info()
{ 
    // Values
    return array('text_type'      => 'EZComments',
                 'module'         => 'EZComments',
                 'text_type_long' => 'Show latest comments',
                 'allow_multiple' => true,
                 'form_content'   => false,
                 'form_refresh'   => false,
                 'show_preview'   => true);
} 

/**
 * display block
 * 
 * @param array       $blockinfo     a blockinfo structure
 * @return output      the rendered bock
 */
function EZComments_EZCommentsblock_display($blockinfo)
{ 

    // Security check
    if (!SecurityUtil::checkPermission('EZComments:EZCommentsblock:', "$blockinfo[title]::", ACCESS_READ)) {
        return false;
    } 

    if (!pnModLoad('EZComments')) {
        return false;
    }
    
    // Get variables from content block
    $vars = pnBlockVarsFromContent($blockinfo['content']);
    extract($vars);

    if (!isset($numentries)) {
        $numentries = 5;
    } 
    if (!isset($showdate)) {
        $showdate = 0;
    } 
    if (!isset($showusername)) {
        $showusername = 0;
    } 
    if (!isset($linkusername)) {
        $linkusername = 0;
    } 

    $options = array('numitems' => $numentries);
                     
    if (isset($mod) && $mod != '*') {
        $options['mod'] = $mod;
    }

    if (!isset($showpending) || $showpending == 0) {
        // don't show pending comments
        $options['status'] = 0;
    }

    
    // get the comments
    $items = pnModAPIFunc('EZComments', 
                          'user', 
                          'getall', 
                          $options);
    // augment the info
    $comments = EZComments_prepareCommentsForDisplay($items);
    
    $renderer = new pnRender('EZComments'); 
    $renderer->assign($vars);
    $renderer->assign('comments', $comments); 

    // Populate block info and pass to theme
    $blockinfo['content'] = $renderer->fetch('ezcomments_block_ezcomments.htm');
    return themesideblock($blockinfo);
} 

/**
 * modify block settings
 * 
 * @param array $blockinfo a blockinfo structure
 * @return output the bock form
 */
function EZComments_EZCommentsblock_modify($blockinfo)
{
    if (!SecurityUtil::checkPermission('EZComments:EZCommentsblock:', "$blockinfo[title]::", ACCESS_ADMIN)) {
        return false;
    } 
    // Get current content
    $vars = pnBlockVarsFromContent($blockinfo['content']); 

    // get all modules with EZComments active
    $usermods = pnModAPIFunc('Modules', 
                             'admin', 
                             'gethookedmodules', 
                             array('hookmodname'=> 'EZComments'));

    // Create output object
    $renderer = new pnRender('EZComments'); 
    // As Admin output changes often, we do not want caching.
    $renderer->caching = false; 
    // assign the block vars
    $renderer->assign($vars); 

    $renderer->assign('usermods', array_keys($usermods));
    
    // Return the output that has been generated by this function
    return $renderer->fetch('ezcomments_block_ezcomments_modify.htm');
} 

/**
 * update block settings
 * 
 * @param array       $blockinfo     a blockinfo structure
 * @return $blockinfo  the modified blockinfo structure
 */
function EZComments_EZCommentsblock_update($blockinfo)
{
    // Get current content
    $vars = pnBlockVarsFromContent($blockinfo['content']);

    // alter the corresponding variable
    $vars['numentries'] = (int)FormUtil::getPassedValue('numentries', 5, 'POST');
    $vars['showusername'] = (bool)FormUtil::getPassedValue('showusername', false, 'POST');
    $vars['linkusername'] = (bool)FormUtil::getPassedValue('linkusername', false, 'POST');
    $vars['showdate'] = (bool)FormUtil::getPassedValue('showdate', false, 'POST');
    $vars['showpending'] = (bool)FormUtil::getPassedValue('showpending', false, 'POST');
    $vars['mod'] = (string)FormUtil::getPassedValue('mod', '', 'POST');

    // write back the new contents
    $blockinfo['content'] = pnBlockVarsToContent($vars); 

    // clear the block cache
    $renderer = pnRender::getInstance('EZComments');
    $renderer->clear_cache('ezcomments_block_ezcomments.htm');

    return $blockinfo;
} 

