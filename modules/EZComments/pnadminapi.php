<?php 
// $Id$
// LICENSE
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License (GPL)
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
// This program is distributed in the hope that it will be useful,
// but WIthOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// To read the license please visit http://www.gnu.org/copyleft/gpl.html
// ----------------------------------------------------------------------
// Original Author of file: J�rg Napp, http://postnuke.lottasophie.de
// ----------------------------------------------------------------------

/**
 * EZComments_adminapi_getUsedModules()
 * 
 * This function returns an array of the modules
 * for which a comment is available. This is used
 * for the "clean-up" feature that eliminates
 * orphaned comments after a module is deletd.
 * 
 * @return list of all modules used 
 */
function EZComments_adminapi_getUsedModules()
{
	if (!pnSecAuthAction(0, 'EZComments::', '::', ACCESS_ADMIN)) {
		return false;
	} 
	
	$dbconn =& pnDBGetConn(true);
	$pntable =& pnDBGetTables();

	$table = $pntable['EZComments'];
	$column = &$pntable['EZComments_column']; 

    $sql = "SELECT    $column[modname]
            FROM      $table
			GROUP BY  $column[modname]";
    $result =& $dbconn->Execute($sql);

    if ($dbconn->ErrorNo() != 0) {
        return false;
    }

	$mods = array();
	for (; !$result->EOF; $result->MoveNext()) {
		list($mods[]) = $result->fields;
	} 
	$result->Close(); 

	return $mods;
}

/**
 * EZComments_adminapi_deleteall()
 * 
 * Delete all comments for a given module. Used to clean
 * up orphaned comments.
 * 
 * @param $args[module] the module for which to delete for
 * @return boolean sucess status
 **/
function EZComments_adminapi_deleteall($args)
{
	if (!pnSecAuthAction(0, 'EZComments::', '::', ACCESS_ADMIN)) {
		return false;
	} 

	if (!isset($args['module'])) { 
		return false;
	}
	
	
	$dbconn =& pnDBGetConn(true);
	$pntable =& pnDBGetTables();

	$table = $pntable['EZComments'];
	$column = &$pntable['EZComments_column']; 

    $sql = "DELETE FROM $table
			WHERE $column[modname] = '$args[module]'";
			
    $result =& $dbconn->Execute($sql);

    if ($dbconn->ErrorNo() != 0) {
	echo $dbconn->ErrorMsg;
        return false;
    }
	$result->Close(); 

	return true;
}

/**
 * EZComments_adminapi_deletebyitem()
 *
 * Delete all comments for a given item. Used to clean
 * up orphaned comments.
 *
 * @author Timo (Numerobis)
 * @since 0.3
 * @param $args[module] the module for which to delete for
 * @return boolean sucess status
 **/
function EZComments_adminapi_deletebyitem($args)
{
    $modname = pnModGetName();
    if (!isset($args['objectid'])) {
        return false;
    } 

    $objectid = $args['objectid'];
    
    if (!pnSecAuthAction(0, 'EZComments::', "$modname:$objectid:", ACCESS_ADMIN)) {
        return false;
    } 

    $dbconn =& pnDBGetConn(true);
    $pntable =& pnDBGetTables();

    $table = $pntable['EZComments'];
    $column = &$pntable['EZComments_column'];

    $sql = "DELETE FROM $table
			WHERE $column[modname] = '$modname' AND $column[objectid] = '$objectid'";

    $result =& $dbconn->Execute($sql);

    if ($dbconn->ErrorNo() != 0) {
        echo $dbconn->ErrorMsg;
        return false;
    } 
    $result->Close();

    return true;
} 

/**
 * delete an item
 * 
 * @param    $args['id']    ID of the item
 * @return   bool           true on success, false on failure
 */
function EZComments_adminapi_delete($args)
{
    // Get arguments from argument array 
    extract($args);

    // Argument check - make sure that all required arguments are present,
    // if not then set an appropriate error message and return
    if ((isset($id) && !is_numeric($id))) {
        pnSessionSetVar('errormsg', _MODARGSERROR);
        return false;
    }

    // The user API function is called.
    $item = pnModAPIFunc('EZComments', 'user', 'get', array('id' => $id));

    if (!$item) {
        pnSessionSetVar('errormsg', _NOSUCHITEM);
        return false;
    }

    // Security check 
    if (!pnSecAuthAction(0, 'EZComments::', "::$id", ACCESS_DELETE)) {
        pnSessionSetVar('errormsg', _MODULENOAUTH);
        return false;
    }

    // Get datbase setup
    $dbconn =& pnDBGetConn(true);
    $pntable =& pnDBGetTables();
    $table = $pntable['EZComments'];
    $column = &$pntable['EZComments_column'];

    // Delete the item - the formatting here is not mandatory, but it does
    // make the SQL statement relatively easy to read.  Also, separating
    // out the sql statement from the Execute() command allows for simpler
    // debug operation if it is ever needed
    $sql = "DELETE FROM $table
            WHERE $column[id] = '" . (int)pnVarPrepForStore($id) ."'";
    $dbconn->Execute($sql);

    // Check for an error with the database code, and if so set an
    // appropriate error message and return
    if ($dbconn->ErrorNo() != 0) {
        pnSessionSetVar('errormsg', _DELETEFAILED);
        return false;
    }

    // Let any hooks know that we have deleted an item.
    pnModCallHooks('item', 'delete', $id, array('module' => 'EZComments'));

    // Let the calling process know that we have finished successfully
    return true;
}

/**
 * update an item
 * 
 * @param    $args['id']         the ID of the item
 * @param    $args['subject']    the new subject of the item
 * @param    $args['comment']    the new text of the item
 * @return   bool             true on success, false on failure
 */
function EZComments_adminapi_update($args)
{
    // Get arguments from argument array - all arguments to this function
    // should be obtained from the $args array, getting them from other
    // places such as the environment is not allowed, as that makes
    // assumptions that will not hold in future versions of PostNuke
    extract($args);

    // Argument check - make sure that all required arguments are present,
    // if not then set an appropriate error message and return
    if ((!isset($subject)) ||
        (!isset($comment)) ||
        (isset($id) && !is_numeric($id))) {
        pnSessionSetVar('errormsg', _MODARGSERROR);
        return false;
    }

    // The user API function is called.  This takes the item ID which
    // we obtained from the input and gets us the information on the
    // appropriate item.  If the item does not exist we post an appropriate
    // message and return
    $item = pnModAPIFunc('EZComments', 'user', 'get', array('id' => $id));

    if (!$item) {
        pnSessionSetVar('errormsg', _NOSUCHITEM);
        return false;
    }

    // Security check.
    // In this case we had to wait until we could obtain the item
    // name to complete the instance information so this is the first
    // chance we get to do the check
    if (!pnSecAuthAction(0, 'EZComments::', "::$id", ACCESS_EDIT)) {
        pnSessionSetVar('errormsg', _MODULENOAUTH);
        return false;
    }

    // Get datbase setup
    $dbconn =& pnDBGetConn(true);
    $pntable =& pnDBGetTables();

    // It's good practice to name the table and column definitions you
    // are getting - $table and $column don't cut it in more complex
    // modules
	$EZCommentstable = $pntable['EZComments'];
	$EZCommentscolumn = &$pntable['EZComments_column']; 

	// All variables that come in to or go out of PostNuke should be handled
	// by the relevant pnVar*() functions to ensure that they are safe. 
	// Failure to do this could result in opening security wholes at either 
	// the web, filesystem, display, or database layers. 
    list($subject, $comment, $id) = pnVarPrepForStore($subject, $comment, $id);

    // Update the item - the formatting here is not mandatory, but it does
    // make the SQL statement relatively easy to read.  Also, separating
    // out the sql statement from the Execute() command allows for simpler
    // debug operation if it is ever needed
    $sql = "UPDATE $EZCommentstable
            SET $EZCommentscolumn[subject] = '".$subject."',
                $EZCommentscolumn[comment] = '".$comment."'
            WHERE $EZCommentscolumn[id] = '".(int)$id."'";
    $dbconn->Execute($sql);

    // Check for an error with the database code, and if so set an
    // appropriate error message and return
    if ($dbconn->ErrorNo() != 0) {
        pnSessionSetVar('errormsg', _UPDATEFAILED);
        return false;
    }

    // Let any hooks know that we have updated an item.
    pnModCallHooks('item', 'update', $id, array('module' => 'EZComments'));

    // Let the calling process know that we have finished successfully
    return true;
}

?>