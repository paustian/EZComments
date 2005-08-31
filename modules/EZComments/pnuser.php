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
 * @author      Joerg Napp <jnapp@users.sourceforge.net>
 * @author      Mark West <markwest at postnuke dot com>
 * @author      Jean-Michel Vedrine
 * @version     1.0
 * @link        http://noc.postnuke.com/projects/ezcomments/ Support and documentation
 * @license     http://www.gnu.org/copyleft/gpl.html GNU General Public License
 * @package     Postnuke
 * @subpackage  EZComments
 */


/**
 * Return to index page
 * 
 * This is the default function called when EZComments is called 
 * as a module. As we do not intend to output anything, we just 
 * redirect to the start page.
 * 
 * @since    0.2
 */
function EZComments_user_main($args)
{
    return pnRedirect(pnGetBaseUrl());
}


/**
 * Display comments for a specific item
 * 
 * This function provides the main user interface to the comments
 * module. 
 * 
 * @param    $args['objectid']     ID of the item to display comments for
 * @param    $args['extrainfo']    URL to return to if user chooses to comment
 * @param    $args['template']     Template file to use (with extension)
 * @return   output                the comments
 * @since    0.1
 */
function EZComments_user_view($args)
{
    // work out the input from the hook
    $modname = pnModGetName();
    $objectid = $args['objectid'];

    // security check
    if (!pnSecAuthAction(0, 'EZComments::', "$modname:$objectid: ", ACCESS_OVERVIEW)) {
        return _EZCOMMENTS_NOAUTH;
    }

    // we may get some input in from the navigation bar
    list ($EZComments_template, $EZComments_order) = pnVarCleanFromInput('EZComments_template', 'EZComments_order');

    if ($EZComments_order == 1) {
        $sortorder = 'DESC';
    } else {
        $sortorder = 'ASC';
    }
    $status = 0;
    $items = pnModAPIFunc('EZComments',
                          'user',
                          'getall',
                           compact('modname', 'objectid','sortorder','status'));

    if ($items === false) {
        return _EZCOMMENTS_FAILED;
    }     

    $comments = EZComments_prepareCommentsForDisplay($items);

    // create the pnRender object
    $pnRender =& new pnRender('EZComments');

    // don't use caching (for now...)
    $pnRender->caching=false;

    $pnRender->assign('comments',   $comments);
    $pnRender->assign('order',      $EZComments_order);
    $pnRender->assign('allowadd',   pnSecAuthAction(0, 'EZComments::', "$modname:$objectid: ", ACCESS_COMMENT));
    $pnRender->assign('loggedin',   pnUserLoggedIn());
    if (!is_array($args['extrainfo'])) {
        $pnRender->assign('redirect',   $args['extrainfo']);
    } else {
        $pnRender->assign('redirect',   $args['extrainfo']['returnurl']);
    }
    $pnRender->assign('objectid',   $objectid);

    // assign all module vars (they may be useful...)
    $pnRender->assign(pnModGetVar('EZComments'));

    // check for some useful hooks
    if (pnModIsHooked('pn_bbcode', 'EZComments')) {
        $pnRender->assign('bbcode', true);
    }
    if (pnModIsHooked('pn_bbsmile', 'EZComments')) {
        $pnRender->assign('smilies', true);
    }

    // find out which template to use
    $template = pnModGetVar('EZComments', 'template');
    if (!empty($EZComments_template)) {
        $template = $EZComments_template;
    } else if (isset($args['template'])) {
        $template = $args['template'];
    }
    if (!$pnRender->template_exists(pnVarPrepForOS($template . '/ezcomments_user_view.htm'))) {
        $template = pnModGetVar('EZComments', 'template');
    }
    $pnRender->assign('template', $template);
    return $pnRender->fetch(pnVarPrepForOS($template) . '/ezcomments_user_view.htm');
} 

/**
 * Display a comment form 
 * 
 * This function displays a comment form, if you do not want users to
 * comment on the same page as the item is.
 * 
 * @param    $EZComments_comment     the comment (taken from HTTP put)
 * @param    $EZComments_modname     the name of the module the comment is for (taken from HTTP put)
 * @param    $EZComments_objectid    ID of the item the comment is for (taken from HTTP put)
 * @param    $EZComments_redirect    URL to return to (taken from HTTP put)
 * @param    $EZComments_subject     The subject of the comment (if any) (taken from HTTP put)
 * @param    $EZComments_replyto     The ID of the comment for which this an anser to (taken from HTTP put)
 * @param    $EZComments_template    The name of the template file to use (with extension)
 * @todo     Check out it this function can be merged with _view!
 * @since    0.2
 */
function EZComments_user_comment($args)
{
    list($EZComments_modname,
         $EZComments_objectid,
         $EZComments_redirect,
         $EZComments_comment,
         $EZComments_subject,
         $EZComments_replyto,
         $EZComments_template) = pnVarCleanFromInput('EZComments_modname',
                                                     'EZComments_objectid',
                                                     'EZComments_redirect',
                                                     'EZComments_comment',
                                                     'EZComments_subject',
                                                     'EZComments_replyto',
                                                     'EZComments_template');

    extract($args);

    // check if commenting is setup for the input module
    if (!pnModAvailable($EZComments_modname) || !pnModIsHooked('EZComments', $EZComments_modname)) {
        return _EZCOMMENTS_NOAUTH;
    }

    $items = pnModAPIFunc('EZComments',
                          'user',
                          'getall',
                           array('modname'  => $EZComments_modname,
                                 'objectid' => $EZComments_objectid));

    if ($items === false) {
        return _EZCOMMENTS_FAILED;
    }

    $comments = EZComments_prepareCommentsForDisplay($items);

    $pnRender =& new pnRender('EZComments');

    // don't use caching (for now...)
    $pnRender->caching=false;

    $pnRender->assign('comments', $comments);
    $pnRender->assign('allowadd', pnSecAuthAction(0, 'EZComments::', "$EZComments_modname:$EZComments_objectid: ", ACCESS_COMMENT));
    $pnRender->assign('addurl',   pnModURL('EZComments', 'user', 'create'));
    $pnRender->assign('loggedin', pnUserLoggedIn());
    $pnRender->assign('redirect', $EZComments_redirect);
    $pnRender->assign('modname',  pnVarPrepForDisplay($EZComments_modname));
    $pnRender->assign('objectid', pnVarPrepForDisplay($EZComments_objectid));
    $pnRender->assign('subject',  pnVarPrepForDisplay($EZComments_subject));
    $pnRender->assign('replyto',  pnVarPrepForDisplay($EZComments_replyto));

    // assign all module vars (they may be useful...)
    $pnRender->assign(pnModGetVar('EZComments'));

    // check for some useful hooks
    if (pnModIsHooked('pn_bbcode', 'EZComments')) {
        $pnRender->assign('bbcode', true);
    }
    if (pnModIsHooked('pn_bbsmile', 'EZComments')) {
        $pnRender->assign('smilies', true);
    }

    // find out which template to use
    $template = pnModGetVar('EZComments', 'template');
    if (!empty($EZComments_template)) {
        $template = $EZComments_template;
    } else if (isset($args['template'])) {
        $template = $args['template'];
    }

    if (!$pnRender->template_exists(pnVarPrepForOS($template . '/ezcomments_user_comment.htm'))) {
        $template = pnModGetVar('EZComments', 'template');
    }
    $pnRender->assign('template', $template);


    if (!$pnRender->template_exists(pnVarPrepForOS($template . '/ezcomments_user_comment.htm'))) {
        return _EZCOMMENTS_FAILED;
    }

    return $pnRender->fetch(pnVarPrepForOS($template) . '/ezcomments_user_comment.htm');
}

/**
 * Create a comment for a specific item
 * 
 * This is a standard function that is called with the results of the
 * form supplied by EZComments_user_view to create a new item
 * 
 * @param    $EZComments_comment     the comment (taken from HTTP put)
 * @param    $EZComments_modname     the name of the module the comment is for (taken from HTTP put)
 * @param    $EZComments_objectid    ID of the item the comment is for (taken from HTTP put)
 * @param    $EZComments_redirect    URL to return to (taken from HTTP put)
 * @param    $EZComments_subject     The subject of the comment (if any) (taken from HTTP put)
 * @param    $EZComments_replyto     The ID of the comment for which this an anser to (taken from HTTP put)
 * @since    0.1
 */
function EZComments_user_create($args)
{
    // Confirm authorisation code.
    if (!pnSecConfirmAuthKey()) {
        pnSessionSetVar('errormsg', _BADAUTHKEY);
        return pnRedirect($EZComments_redirect);
    } 

    list($EZComments_modname,
         $EZComments_objectid,
         $EZComments_redirect,
         $EZComments_comment,
         $EZComments_subject,
         $EZComments_replyto) = pnVarCleanFromInput('EZComments_modname',
                                                    'EZComments_objectid',
                                                    'EZComments_redirect',
                                                    'EZComments_comment',
                                                    'EZComments_subject',
                                                    'EZComments_replyto');

    // check if the user logged in and if we're allowing anon users to 
    // set a name and e-mail address
    if (!pnUserLoggedIn()) {
        list($EZComments_anonname, $EZComments_anonmail) = pnVarCleanFromInput('EZComments_anonname', 'EZComments_anonmail');
    } else {
        $EZComments_anonname = '';
        $EZComments_anonmail = '';
    }

    $id = pnModAPIFunc('EZComments',
                       'user',
                       'create',
                       array('modname'  => $EZComments_modname,
                             'objectid' => $EZComments_objectid,
                             'url'      => $EZComments_redirect,
                             'comment'  => $EZComments_comment,
                             'subject'  => $EZComments_subject,
                             'replyto'  => $EZComments_replyto,
                             'uid'      => pnUserGetVar('uid'),
                             'anonname' => $EZComments_anonname,
                             'anonmail' => $EZComments_anonmail));

    // decoding the URL. Credits to tmyhre for fixing.
    $EZComments_redirect = rawurldecode($EZComments_redirect);
    $EZComments_redirect = str_replace('&amp;', '&', $EZComments_redirect);

    return pnRedirect($EZComments_redirect);
} 

/**
 * Prepare comments to be displayed
 * 
 * We loop through the "raw data" returned from the API to prepare these data
 * to be displayed. 
 * We check for necessary rights, and derive additional information (e.g. user
 * data) drom other modules.
 * 
 * @param    $items    An array of comment items as returned from the API
 * @return   array     An array to display (augmented information / perm. check)
 * @since    0.2
 */
function EZComments_prepareCommentsForDisplay($items)
{
    $comments = array();
    foreach ($items as $item) {
        $comment = $item;
        if ($item['uid'] > 0) {
            // get the user vars and merge into the comment array
            $userinfo = pnUserGetVars($item['uid']);
            $comment  = array_merge ($userinfo, $comment);

            // work out if the user is online
            $comment['online'] = false;
            if (pnModAvailable('Members_List')) {
                if (pnModAPIFunc('Members_List', 'user', 'isonline', array('userid' => $userinfo['pn_uid']))) {
                    $comment['onlinestatus'] = true;
                    $comment['online'] = true;
                }
            } else {
                $comment['onlinestatus'] = false;
            }
        } else {
            $comment['uname'] = pnConfigGetVar('Anonymous');
        }
        $comment['del'] = (pnSecAuthAction(0, 'EZComments::', "$comment[modname]:$comment[objectid]:$comment[id]", ACCESS_DELETE));
        $comments[] = $comment;
    }
    return $comments;
}

/**
 * Sort comments by thread
 * 
 * 
 * @param    $comments    An array of comments
 * @return   array        The sorted array
 * @since    0.2
 */
function EZComments_threadComments($comments)
{
    return EZComments_displayChildren($comments, -1, 0);
}

/**
 * Get all child comments
 * 
 * This function returns all child comments to a given comment.
 * It is called recursively
 * 
 * @param    $comments    An array of comments
 * @param    $id          The id of the parent comment
 * @param    $level       The indentation level 
 * @return   array        The sorted array
 * @access   private
 * @since    0.2
 */
function EZComments_displayChildren($comments, $id, $level)
{
    $comments2 = array();
    foreach ($comments as $comment) {
        if ($comment['replyto'] == $id) {
            $comment['level'] = $level;
            $comments2[] = $comment;
            $comments2 = array_merge($comments2, 
                                     EZComments_displayChildren($comments, $comment['id'], $level+1));
        }
    }
    return $comments2;
}

?>