<?php
/**
 * EZComments
 *
 * @copyright (C) EZComments Development Team
 * @link http://code.zikula.org/ezcomments
 * @version $Id$
 * @license See license.txt
 */

/**
 * Return to index page
 *
 * This is the default function called when EZComments is called
 * as a module. As we do not intend to output anything, we just
 * redirect to the start page.
 *
 * @since 0.2
 */
function EZComments_user_main($args = array())
{
    if (!pnUserLoggedIn()) {
        return pnRedirect(pnGetHomepageURL);
    }

    $dom = ZLanguage::getModuleDomain('EZComments');

    // the following code was taken from the admin interface first and modified
    // that only own comments are shown on the overview page.

    // get the status filter
    $status = isset($args['status']) ? $args['status'] : FormUtil::getPassedValue('status', -1, 'GETPOST');
    if (!isset($status) || !is_numeric($status) || $status < -1 || $status > 2) {
        $status = -1;
    }

    // presentation values
    $startnum = isset($args['startnum']) ? $args['startnum'] : FormUtil::getPassedValue('startnum', null, 'GETPOST');
    $itemsperpage = pnModGetVar('EZComments', 'itemsperpage');

    // Create output object
    $renderer = & pnRender::getInstance('EZComments', false);

    // assign the module vars
    $renderer->assign(pnModGetVar('EZComments'));

    // call the api to get all current comments that are from the user or the user's content
    $params = array('startnum' => $startnum,
                    'numitems' => $itemsperpage,
                    'status'   => $status,
                    'owneruid' => pnUserGetVar('uid'),
                    'uid'      => pnUserGetVar('uid'));

    $items = pnModAPIFunc('EZComments', 'user', 'getall', $params);

    if ($items === false) {
        return LogUtil::registerError(__('Internal Error.', $dom));
    }

    // loop through each item adding the relevant links
    foreach ($items as $k => $item)
    {
        $options   = array();
        $options[] = array('url'   => $item['url'] . '#comments',
                           'image' => 'demo.gif',
                           'title' => __('View', $dom));

        $options[] = array('url'   => pnModURL('EZComments', 'user', 'modify', array('id' => $item['id'])),
                           'image' => 'xedit.gif',
                           'title' => __('Edit', $dom));

        $items[$k]['options'] = $options;
    }

    // assign the items to the template, values for the filters, values for the smarty plugin to produce a pager
    $renderer->assign('items',  $items);
    $renderer->assign('status', $status);
    $renderer->assign('pager',  array('numitems'     => pnModAPIFunc('EZComments', 'user', 'countitems', $params),
                                      'itemsperpage' => $itemsperpage));

    // Return the output
    return $renderer->fetch('ezcomments_user_main.htm');

}

/**
 * Display comments for a specific item
 *
 * This function provides the main user interface to the comments
 * module.
 *
 * @param $args['mod']           Module that the item belongs to
 * @param $args['objectid']      ID of the item to display comments for
 * @param $args['extrainfo']     URL to return to if user chooses to comment
 * @param $args['owneruid']      User ID of the content owner
 * @param $args['useurl']        Url used for storing in db and in email instead of redirect url
 * @param $args['template']      Template file to use (with extension)
 * @return output the comments
 * @since 0.1
 */
function EZComments_user_view($args)
{
    // work out the input from the hook
    $mod      = isset($args['mod']) ? $args['mod'] : pnModGetName();
    $objectid = isset($args['objectid']) ? $args['objectid'] : '';

    // security check
    if (!SecurityUtil::checkPermission('EZComments::', "$mod:$objectid:", ACCESS_OVERVIEW)) {
        return LogUtil::registerPermissionError();
    }

    $dom = ZLanguage::getModuleDomain('EZComments');

    $owneruid = (int)$args['extrainfo']['owneruid'];
    $useurl   = isset($args['extrainfo']['useurl']) ? $args['extrainfo']['useurl'] : null;

    // we may get some input in from the navigation bar
    $order = FormUtil::getPassedValue('order');
    if ($order == 1) {
        $sortorder = 'DESC';
    } else {
        $sortorder = 'ASC';
    }

    $status = 0;

    // check if we're using the pager
    $enablepager = pnModGetVar('EZComments', 'enablepager');
    if ($enablepager) {
        $numitems = pnModGetVar('EZComments', 'commentsperpage');
        $startnum = FormUtil::getPassedValue('comments_startnum');
        if (!isset($startnum) && !is_numeric($startnum)) {
            $startnum = -1;
        }
    } else {
        $startnum = -1;
        $numitems = -1;
    }

    $items = pnModAPIFunc('EZComments', 'user', 'getall',
                           compact('mod', 'objectid', 'sortorder', 'status', 'numitems', 'startnum'));

    if ($items === false) {
        return LogUtil::registerError(__('Internal Error.', $dom), null, 'index.php');
    }

    $items = EZComments_prepareCommentsForDisplay($items);

    if ($enablepager) {
        $commentcount = pnModAPIFunc('EZComments', 'user', 'countitems', compact('mod', 'objectid', 'status'));
    } else {
        $commentcount = count($items);
    }

    // create the pnRender object
    $renderer = & pnRender::getInstance('EZComments', false, null, true);

    $renderer->assign('comments',     $items);
    $renderer->assign('commentcount', $commentcount);
    $renderer->assign('order',        $sortorder);
    $renderer->assign('modinfo',      pnModGetInfo(pnModGetIDFromName($mod)));
    $renderer->assign('avatarpath',   pnModGetVar('Users', 'avatarpath'));
    $renderer->assign('msgmodule',    pnConfigGetVar('messagemodule', ''));
    $renderer->assign('prfmodule',    pnConfigGetVar('profilemodule', ''));
    $renderer->assign('allowadd',     SecurityUtil::checkPermission('EZComments::', "$mod:$objectid:", ACCESS_COMMENT));
    $renderer->assign('loggedin',     pnUserLoggedIn());

    if (!is_array($args['extrainfo'])) {
        $redirect = $args['extrainfo'];
    } else {
        $redirect = $args['extrainfo']['returnurl'];
    }
    // encode the url - otherwise we can get some problems out there....
    $redirect = base64_encode($redirect);
    $renderer->assign('redirect',     $redirect);
    $renderer->assign('objectid',     $objectid);

    // assign the user is of the content owner
    $renderer->assign('owneruid',     $owneruid);

    // assign url that should be stored in db and sent in email if it
    // differs from the redirect url
    $renderer->assign('useurl',       $useurl);

    // assign all module vars (they may be useful...)
    $renderer->assign('modvars', pnModGetVar('EZComments'));

    // flag to recognize the main call
    static $mainScreen = true;
    $renderer->assign('mainscreen',   $mainScreen);
    $mainScreen = false;

    // assign the values for the pager
    $renderer->assign('pager', array('numitems'     => $commentcount,
                                     'itemsperpage' => $numitems));

    // find out which template to use
    $templateset = isset($args['template']) ? $args['template'] : FormUtil::getPassedValue('eztpl');

    if (!$renderer->template_exists(DataUtil::formatForOS($templateset) . '/ezcomments_user_view.htm')) {
        $templateset = pnModGetVar('EZComments', 'template', 'Standard');
    }
    $renderer->assign('template', $templateset);

    // include stylesheet if there is a style sheet
    $css = isset($args['ezccss']) ? $args['ezccss'] : FormUtil::getPassedValue('ezccss');
    $css = $css ? "$css.css" : 'style.css';
    // TODO Search in config/
    $css = "modules/EZComments/pntemplates/$templateset/$css";
    if (file_exists($css)) {
        PageUtil::addVar('stylesheet',$css);
    }

    return $renderer->fetch(DataUtil::formatForOS($templateset) . '/ezcomments_user_view.htm');
}

/**
 * Display a comment form
 *
 * This function displays a comment form, if you do not want users to
 * comment on the same page as the item is.
 *
 * @param $comment the comment (taken from HTTP put)
 * @param $mod the name of the module the comment is for (taken from HTTP put)
 * @param $objectid ID of the item the comment is for (taken from HTTP put)
 * @param $redirect URL to return to (taken from HTTP put)
 * @param $subject The subject of the comment (if any) (taken from HTTP put)
 * @param $replyto The ID of the comment for which this an anser to (taken from HTTP put)
 * @param $template The name of the template file to use (with extension)
 * @todo Check out it this function can be merged with _view!
 * @since 0.2
 */
function EZComments_user_comment($args)
{
    $dom = ZLanguage::getModuleDomain('EZComments');

    $mod         = isset($args['mod'])      ? $args['mod']      : FormUtil::getPassedValue('mod',      null, 'POST');
    $objectid    = isset($args['objectid']) ? $args['objectid'] : FormUtil::getPassedValue('objectid', null, 'POST');
    $redirect    = isset($args['redirect']) ? $args['redirect'] : FormUtil::getPassedValue('redirect', null, 'POST');
    $useurl      = isset($args['useurl'])   ? $args['useurl']   : FormUtil::getPassedValue('useurl',   null, 'POST');
    $comment     = isset($args['comment'])  ? $args['comment']  : FormUtil::getPassedValue('comment',  null, 'POST');
    $subject     = isset($args['subject'])  ? $args['subject']  : FormUtil::getPassedValue('subject',  null, 'POST');
    $replyto     = isset($args['replyto'])  ? $args['replyto']  : FormUtil::getPassedValue('replyto',  null, 'POST');
    $order       = isset($args['order'])    ? $args['order']    : FormUtil::getPassedValue('order',    null, 'POST');
    $owneruid    = isset($args['owneruid']) ? $args['owneruid'] : FormUtil::getPassedValue('owneruid', null, 'POST');
    $template    = isset($args['template']) ? $args['template'] : FormUtil::getPassedValue('template', null, 'POST');
    $stylesheet  = isset($args['ezccss'])   ? $args['ezccss']   : FormUtil::getPassedValue('ezccss',   null, 'POST');

    if ($order == 1) {
        $sortorder = 'DESC';
    } else {
        $sortorder = 'ASC';
    }

    $status = 0;

    // check if commenting is setup for the input module
    if (!pnModAvailable($mod) || !pnModIsHooked('EZComments', $mod)) {
        return LogUtil::registerPermissionError();
    }

    // check if we're using the pager
    $enablepager = pnModGetVar('EZComments', 'enablepager');
    if ($enablepager) {
        $numitems = pnModGetVar('EZComments', 'commentsperpage');
        $startnum = FormUtil::getPassedValue('comments_startnum');
        if (!isset($startnum) && !is_numeric($startnum)) {
            $startnum = -1;
        }
    } else {
        $startnum = -1;
        $numitems = -1;
    }

    $items = pnModAPIFunc('EZComments', 'user', 'getall',
                           compact('mod', 'objectid', 'sortorder', 'status', 'numitems', 'startnum'));

    if ($items === false) {
        return LogUtil::registerError(__('Internal Error.', $dom), null, 'index.php');;
    }

    $items = EZComments_prepareCommentsForDisplay($items);

    if ($enablepager) {
        $commentcount = pnModAPIFunc('EZComments', 'user', 'countitems', compact('mod', 'objectid'));
    } else {
        $commentcount = count($items);
    }

    // don't use caching (for now...)
    $renderer = & pnRender::getInstance('EZComments', false, null, true);

    $renderer->assign('comments',     $items);
    $renderer->assign('commentcount', $commentcount);
    $renderer->assign('order',        $sortorder);
    $renderer->assign('redirect',     $redirect);
    $renderer->assign('allowadd',     SecurityUtil::checkPermission('EZComments::', "$mod:$objectid: ", ACCESS_COMMENT));
    $renderer->assign('mod',          DataUtil::formatForDisplay($mod));
    $renderer->assign('objectid',     DataUtil::formatForDisplay($objectid));
    $renderer->assign('subject',      DataUtil::formatForDisplay($subject));
    $renderer->assign('replyto',      DataUtil::formatForDisplay($replyto));

    // assign the user is of the content owner
    $renderer->assign('owneruid',     $owneruid);

    // assign useurl if there was another url for email and storing submitted
    $renderer->assign('useurl',       $useurl);

    // assign all module vars (they may be useful...)
    $renderer->assign(pnModGetVar('EZComments'));

    // assign the values for the pager
    $renderer->assign('pager', array('numitems'     => $commentcount,
                                     'itemsperpage' => $numitems));

    // find out which template to use
    $templateset = isset($args['template']) ? $args['template'] : $template;
    if (!$renderer->template_exists(DataUtil::formatForOS($templateset) . '/ezcomments_user_comment.htm')) {
        $templateset = pnModGetVar('EZComments', 'template', 'Standard');
    }
    $renderer->assign('template', $templateset);

    // include stylesheet if there is a style sheet
    $css = $stylesheet ? "$stylesheet.css" : 'style.css';
    // TODO Search in config/
    $css = "modules/EZComments/pntemplates/$templateset/$css";
    if (file_exists($css)) {
        PageUtil::addVar('stylesheet', $css);
    }

    // FIXME comment template missing
    return $renderer->fetch(DataUtil::formatForOS($templateset) . '/ezcomments_user_view.htm');
}

/**
 * Create a comment for a specific item
 *
 * This is a standard function that is called with the results of the
 * form supplied by EZComments_user_view to create a new item
 *
 * @param $comment the comment (taken from HTTP put)
 * @param $mod the name of the module the comment is for (taken from HTTP put)
 * @param $objectid ID of the item the comment is for (taken from HTTP put)
 * @param $redirect URL to return to (taken from HTTP put)
 * @param $subject The subject of the comment (if any) (taken from HTTP put)
 * @param $replyto The ID of the comment for which this an anser to (taken from HTTP put)
 * @since 0.1
 */
function EZComments_user_create($args)
{
    $dom = ZLanguage::getModuleDomain('EZComments');

    $mod         = isset($args['mod'])      ? $args['mod']      : FormUtil::getPassedValue('mod',      null, 'POST');
    $objectid    = isset($args['objectid']) ? $args['objectid'] : FormUtil::getPassedValue('objectid', null, 'POST');
    $redirect    = isset($args['redirect']) ? $args['redirect'] : FormUtil::getPassedValue('redirect', null, 'POST');
    $useurl      = isset($args['useurl'])   ? $args['useurl']   : FormUtil::getPassedValue('useurl',   null, 'POST');
    $comment     = isset($args['comment'])  ? $args['comment']  : FormUtil::getPassedValue('comment',  null, 'POST');
    $subject     = isset($args['subject'])  ? $args['subject']  : FormUtil::getPassedValue('subject',  null, 'POST');
    $replyto     = isset($args['replyto'])  ? $args['replyto']  : FormUtil::getPassedValue('replyto',  null, 'POST');
    $owneruid    = isset($args['owneruid']) ? $args['owneruid'] : FormUtil::getPassedValue('owneruid',  null, 'POST');

    if (!isset($owneruid) || (!($owneruid > 1))) {
        $owneruid = 0;
    }

    $redirect = base64_decode($redirect);
    $redirect = !empty($redirect) ? $redirect : null;
    $useurl   = base64_decode($useurl);

    // Confirm authorisation code.
    if (!SecurityUtil::confirmAuthKey()) {
        return LogUtil::registerAuthidError($redirect);
    }

    // check we've actually got a comment....
    if (empty($comment)) {
        return LogUtil::registerError(__('Error! The comment contains no text.', $dom), null, $redirect.'#comments');
    }

    // check if the user logged in and if we're allowing anon users to
    // set a name and e-mail address
    if (!pnUserLoggedIn()) {
        $anonname    = isset($args['anonname'])    ? $args['anonname']    : FormUtil::getPassedValue('anonname',    null, 'POST');
        $anonmail    = isset($args['anonmail'])    ? $args['anonmail']    : FormUtil::getPassedValue('anonmail',    null, 'POST');
        $anonwebsite = isset($args['anonwebsite']) ? $args['anonwebsite'] : FormUtil::getPassedValue('anonwebsite', null, 'POST');
    } else {
        $anonname = '';
        $anonmail = '';
        $anonwebsite = '';
    }

    $redirect = str_replace('&amp;', '&', $redirect);
    // now parse out the hostname from the url for storing in the DB
    $url = str_replace(pnGetBaseURL(), '', $useurl);

    $id = pnModAPIFunc('EZComments', 'user', 'create',
                       array('mod'         => $mod,
                             'objectid'    => $objectid,
                             'url'         => $url,
                             'comment'     => $comment,
                             'subject'     => $subject,
                             'replyto'     => $replyto,
                             'uid'         => pnUserGetVar('uid'),
                             'owneruid'    => $owneruid,
                             'useurl'      => $useurl,
                             'redirect'    => $redirect,
                             'anonname'    => $anonname,
                             'anonmail'    => $anonmail,
                             'anonwebsite' => $anonwebsite));

    return pnRedirect($redirect.'#comments');
}

/**
 * Prepare comments to be displayed
 *
 * We loop through the "raw data" returned from the API to prepare these data
 * to be displayed.
 * We check for necessary rights, and derive additional information (e.g. user
 * data) drom other modules.
 *
 * @param $items An array of comment items as returned from the API
 * @return array An array to display (augmented information / perm. check)
 * @since 0.2
 */
function EZComments_prepareCommentsForDisplay($items)
{
    foreach (array_keys($items) as $k)
    {
        if ($items[$k]['uid'] > 0) {
            // get the user vars and merge into the comment array
            $userinfo = pnUserGetVars($items[$k]['uid']);
            // the users url will clash with the comment url so lets move it out of the way
            $userinfo['website']   = isset($userinfo['__ATTRIBUTES__']['url']) ? $userinfo['__ATTRIBUTES__']['url'] : '';

            // work out if the user is online
            $userinfo['online'] = false;
            if (pnModAvailable('Profile')) {
                if (pnModAPIFunc('Profile', 'memberslist', 'isonline', array('userid' => $userinfo['pn_uid']))) {
                    $userinfo['online'] = true;
                }
            }
            $items[$k] = array_merge($items[$k], $userinfo);
            $items[$k]['anonname'] = '';

        } else {
            // put the generic name if anonymous, uname is empty
            $items[$k]['uname'] = '';
            $items[$k]['anonname'] = !empty($items[$k]['anonname']) ? $items[$k]['anonname'] : pnConfigGetVar('anonymous');
        }

        $items[$k]['del'] = pnModAPIFunc('EZComments', 'user', 'checkPermission',
                                         array('module'    => $items[$k]['mod'],
                                               'objectid'  => $items[$k]['objectid'],
                                               'commentid' => $items[$k]['id'],
                                               'uid'       => $items[$k]['uid'],
                                               'level'     => ACCESS_DELETE));
    }

    return $items;
}

/**
 * Sort comments by thread
 *
 * @param $comments An array of comments
 * @return array The sorted array
 * @since 0.2
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
 * @param $comments An array of comments
 * @param $id The id of the parent comment
 * @param $level The indentation level
 * @return array The sorted array
 * @access private
 * @since 0.2
 */
function EZComments_displayChildren($comments, $id, $level)
{
    $childs = array();
    foreach ($comments as $comment)
    {
        if ($comment['replyto'] == $id) {
            $comment['level'] = $level;
            $childs[] = $comment;
            $childs = array_merge($childs, EZComments_displayChildren($comments, $comment['id'], $level+1));
        }
    }

    return $childs;
}

/**
 * return an rss/atom feed of the last x comments
 *
 * @author Mark west
*/
function EZComments_user_feed($args)
{
    $mod       = isset($args['mod'])       ? $args['mod']       : FormUtil::getPassedValue('mod',   null, 'POST');
    $objectid  = isset($args['objectid'])  ? $args['objectid']  : FormUtil::getPassedValue('objectid',  null, 'POST');
    $feedtype  = isset($args['feedtype'])  ? $args['feedtype']  : FormUtil::getPassedValue('feedtype',  null, 'POST');
    $feedcount = isset($args['feedcount']) ? $args['feedcount'] : FormUtil::getPassedValue('feedcount', null, 'POST');

    // check our input
    if (!isset($feedcount) || !is_numeric($feedcount) || $feedcount < 1 || $feedcount > 999) {
        $feedcount = pnModGetVar('EZcomments', 'feedcount');
    }
    if (!isset($feedtype) || !is_string($feedtype) || ($feedtype !== 'rss' && $feedtype !== 'atom')) {
        $feedtype = pnModGetVar('EZComments', 'feedtype');
    }
    if (!isset($mod) || !is_string($mod) || !pnModAvailable($mod)) {
        $mod = null;
    }
    if (!isset($objectid) || !is_string($objectid)) {
        $objectid = null;
    }

    $comments = pnModAPIFunc('EZComments', 'user', 'getall',
                             array('mod'       => $mod,
                                   'objectid'  => $objectid,
                                   'numitems'  => $feedcount,
                                   'sortorder' => 'DESC',
                                   'status'    => 0));

    // create the pnRender object
    $renderer = & pnRender::getInstance('EZComments');

    // get the last x comments
    $renderer->assign('comments', $comments);

    // grab the item url from one of the comments
    if (isset($comments[0]['url'])) {
        $renderer->assign('itemurl', $comments[0]['url']);
    } else {
        // attempt to guess the url (api compliant mods only....)
        $renderer->assign('itemurl', pnModURL($mod, 'user', 'display', array('objectid' => $objectid)));
    }

    // display the feed and notify the core that we're done
    $renderer->display("ezcomments_user_$feedtype.htm");
    return true;
}

/**
 * process multiple comments
 *
 * This function process the comments selected in the admin view page.
 * Multiple comments may have thier state changed or be deleted
 *
 * @author The EZComments Development Team
 * @param Comments the ids of the items to be deleted
 * @param confirmation confirmation that this item can be deleted
 * @param redirect the location to redirect to after the deletion attempt
 * @return bool true on sucess, false on failure
 */
function EZComments_user_processselected($args)
{
    Loader::requireOnce('modules/EZComments/pnincludes/common.php');
    return ezc_processSelected($args);
}

/**
 * modify a comment
 *
 * This is a standard function that is called whenever an comment owner
 * wishes to modify a comment
 *
 * @author The EZComments Development Team
 * @param tid the id of the comment to be modified
 * @return string the modification page
 */
function EZComments_user_modify($args)
{
    Loader::requireOnce('modules/EZComments/pnincludes/common.php');
    return ezc_modify($args);
}
