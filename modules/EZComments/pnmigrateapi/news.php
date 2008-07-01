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
 * Do the migration
 * 
 * With this function, the actual migration is done.
 * 
 * @return   boolean   true on sucessful migration, false else
 * @since    0.2
 */
function EZComments_migrateapi_news()
{
    // Security check
    if (!pnSecAuthAction(0, 'EZComments::', "::", ACCESS_ADMIN)) {
        return LogUtil::registerError('News migration: Not Admin');
    } 

    // Get datbase setup
    $dbconn = pnDBGetConn(true);
    $pntable = pnDBGetTables();

    $EZCommentstable  = $pntable['EZComments'];
    $EZCommentscolumn = &$pntable['EZComments_column']; 

    $Commentstable = $pntable['comments'];
    $Commentscolumn = $pntable['comments_column'];

    $Usertable = $pntable['users'];
    $Usercolumn = $pntable['users_column'];

    $sql = "SELECT $Commentscolumn[tid], 
                   $Commentscolumn[sid],
                   $Commentscolumn[date], 
                   $Usercolumn[uid], 
                   $Commentscolumn[comment],
                   $Commentscolumn[subject],
                   $Commentscolumn[pid]
             FROM  $Commentstable LEFT JOIN $Usertable
               ON $Commentscolumn[name] = $Usercolumn[uname]";

    $result = $dbconn->Execute($sql); 
    if ($dbconn->ErrorNo() != 0) {
        return LogUtil::registerError('News migration: DB Error');
    } 

    // array to rebuild the patents
    $comments = array(0 => array('newid' => -1));
    
    // loop through the old comments and insert them one by one into the DB
    for (; !$result->EOF; $result->MoveNext()) {
        list($tid, $sid, $date, $uid, $comment, $subject, $replyto) = $result->fields;

        // set the correct user id for anonymous users
        if (empty($uid)) {
            $uid = 1;
        }

        $id = pnModAPIFunc('EZComments',
                           'user',
                           'create',
                           array('mod'  => 'News',
                                   'objectid' => pnVarPrepForStore($sid),
                                   'url'        => 'index.php?name=News&file=article&sid=' . $sid,
                                   'comment'  => $comment,
                                 'subject'  => $subject,
                                 'uid'      => $uid,
                                 'date'     => $date));

        if (!$id) {
            return LogUtil::registerError('News migration: Error creating comment');
        } 
        $comments[$tid] = array('newid' => $id, 
                                'pid'   => $replyto);
        
    } 
    $result->Close(); 

    // rebuild the links to the parents
    foreach ($comments as $k=>$v) {
        if ($k!=0) {
            $sql = "UPDATE $EZCommentstable 
                       SET $EZCommentscolumn[replyto]=" . $comments[$v['pid']]['newid'] . "
                     WHERE $EZCommentscolumn[id]=$v[newid]";
        
            $result = $dbconn->Execute($sql); 
        }
    }
    
    // activate the ezcomments hook for the news module
    pnModAPIFunc('Modules', 'admin', 'enablehooks', array('callermodname' => 'News', 'hookmodname' => 'EZComments'));

    LogUtil::registerStatus('News migration successful');
}
