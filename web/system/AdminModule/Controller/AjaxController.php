<?php
/**
 * Copyright Zikula Foundation 2009 - Zikula Application Framework
 *
 * This work is contributed to the Zikula Foundation under one or more
 * Contributor Agreements and licensed to You under the following license:
 *
 * @license GNU/LGPLv3 (or at your option, any later version).
 * @package Zikula
 *
 * Please see the NOTICE file distributed with this source code for further
 * information regarding copyright and licensing.
 */

namespace AdminModule\Controller;

use ModUtil, SecurityUtil, LogUtil, DataUtil;
use Zikula\Framework\Exception\FatalException;
use Zikula\Framework\Response\Ajax\AjaxResponse;
use Zikula\Framework\Response\Ajax\BadDataResponse;

class AjaxController extends \Zikula_Controller_AbstractAjax
{
    /**
     * Change the category a module belongs to by ajax.
     *
     * @return AjaxUtil::output Output to the calling ajax request is returned.
     *                          response is a string moduleid on sucess.
     */
    public function changeModuleCategoryAction()
    {
        $this->checkAjaxToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::', '::', ACCESS_ADMIN));

        $moduleID = $this->request->request->get('modid');
        $newParentCat = $this->request->request->get('cat');

        //get info on the module
        $module = ModUtil::getInfo($moduleID);
        if (!$module) {
            //deal with couldnt get module info
            throw new FatalException($this->__('Error! Could not get module name for id %s.'));
        }

        //get the module name
        $displayname = DataUtil::formatForDisplay($module['displayname']);
        $module = $module['name'];
        $oldcid = ModUtil::apiFunc('AdminModule', 'admin', 'getmodcategory', array('mid' => $moduleID));

        //move the module
        $result = ModUtil::apiFunc('AdminModule', 'admin', 'addmodtocategory', array('category' => $newParentCat, 'module' => $module));
        if (!$result) {
            throw new FatalException($this->__('Error! Could not add module to module category.'));
        }

        $output = array();
        $output['response'] = $moduleID;
        $output['newParentCat'] = $newParentCat;
        $output['oldcid'] = $oldcid;
        $output['modulename'] = $displayname;
        $output['url'] = ModUtil::url($module, 'admin', 'index');

        return new AjaxResponse($output);
    }

    /**
     * Add a new admin category by ajax.
     *
     * @return AjaxUtil::output Output to the calling ajax request is returned.
     *                          response is a string the new cid on sucess.
     *                          url is a formatted url to the new category on success.
     */
    public function addCategoryAction()
    {
        $this->checkAjaxToken();
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::', '::', ACCESS_ADMIN));

        //get form information
        $name = trim($this->request->request->get('name'));

        //TODO make sure name is set.

        //check if there exists a cat with this name.
        $cats = array();
        $items = ModUtil::apiFunc('AdminModule', 'admin', 'getall');
        foreach ($items as $item) {
            if (SecurityUtil::checkPermission('Admin::', "$item[name]::$item[cid]", ACCESS_READ)) {
                $cats[] = $item;
            }
        }

        foreach ($cats as $cat) {
            if ($name == $cat['name']) {
                throw new FatalException($this->__('Error! A category by this name already exists.'));
            }
        }

        // Security check
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::Category', "$name::", ACCESS_ADD));

        //create the category
        $result = ModUtil::apiFunc('AdminModule', 'admin', 'create', array('name' => $name, 'description' => ''));
        if (!$result) {
            throw new FatalException($this->__('The category could not be created.'));
        }

        $output = array();
        $output['response'] = $result;
        $url = ModUtil::url('Admin', 'admin', 'adminpanel', array('acid' => $result));
        $output['url'] = $url;

        return new AjaxResponse($output);
    }

    /**
     * Delete an admin category by ajax.
     *
     * @return AjaxUtil::output Output to the calling ajax request is returned.
     *                          response is a string cid on success.
     */
    public function deleteCategoryAction()
    {
        $this->checkAjaxToken();

        //get passed cid to delete
        $cid = trim($this->request->request->get('cid'));

        //check user has permission to delete this
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::Category', "::$cid", ACCESS_DELETE));

        //find the category corresponding to the cid.
        $item = ModUtil::apiFunc('AdminModule', 'admin', 'get', array('cid' => $cid));
        if (empty($item)) {
            throw new FatalException($this->__('Error! No such category found.'));
        }

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::Category', "$item[name]::$item[cid]", ACCESS_DELETE));

        $output = array();

        //delete the category
        $delete = ModUtil::apiFunc('AdminModule', 'admin', 'delete', array('cid' => $cid));
        if ($delete) {
            // Success
            $output['response'] = $cid;
            return new AjaxResponse($output);
        }

        //unknown error
        throw new FatalException($this->__('Error! Could not perform the deletion.'));
    }

    /**
     * Edit an admin category by ajax.
     *
     * @return AjaxUtil::output Output to the calling ajax request is returned.
     */
    public function editCategoryAction()
    {
        $this->checkAjaxToken();

        //get form values
        $cid = trim($this->request->request->get('cid'));
        $name = trim($this->request->request->get('name'));

        //security checks
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::Category', "$name::$cid", ACCESS_EDIT));

        //make sure cid and category name (cat) are both set
        if (!isset($cid) || $cid == '' || !isset($name) || $name == '') {
            return new BadDataResponse($this->__('No category name or id set.'));
        }

        $output = array();

        //check if category with same name exists
        $cats = array();
        $items = ModUtil::apiFunc('AdminModule', 'admin', 'getall');
        foreach ($items as $item) {
            if (SecurityUtil::checkPermission('Admin::', "$item[name]::$item[cid]", ACCESS_READ)) {
                $cats[] = $item;
            }
        }

        foreach ($cats as $cat) {
           if ($name == $cat['name']) {
                //check to see if the category with same name is the same category.
                if ($cat['cid'] == $cid) {
                    $output['response'] = $name;
                    return new AjaxResponse($output);
                }

                //a different category has the same name, not allowed.
                throw new FatalException($this->__('Error! A category by this name already exists.'));
            }
        }

        //get the category from the database
        $item = ModUtil::apiFunc('AdminModule', 'admin', 'get', array('cid' => $cid));
        if (empty($item)) {
            throw new \Zikula\Framework\Exception\FatalException($this->__('Error! No such category found.'));
        }

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::Category', "$item[name]::$item[cid]", ACCESS_EDIT));

        // update the category using the info from the database and from the form.
        $update = ModUtil::apiFunc('AdminModule', 'admin', 'update', array('cid' => $cid, 'name' => $name, 'description' => $item['description']));
        if ($update) {
            $output['response'] = $name;
            return new AjaxResponse($output);
        }

        //update failed for some reason
        throw new FatalException($this->__('Error! Could not save your changes.'));
    }

    /**
     * Make a category the initially selected one (by ajax).
     *
     * @return AjaxUtil::output Output to the calling ajax request is returned.
     *                          response is a string message on success.
     */
    public function defaultCategoryAction()
    {
        $this->checkAjaxToken();

        //check user has permission to change the initially selected category
        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::', '::', ACCESS_ADMIN));

        //get passed cid
        $cid = trim($this->request->request->get('cid'));

        //find the category corresponding to the cid.
        $item = ModUtil::apiFunc('AdminModule', 'admin', 'get', array('cid' => $cid));
        if ($item == false) {
            return AjaxUtil::error(LogUtil::registerError($this->__('Error! No such category found.')),array(), true);
        }

        $output = array();

        // make category the initially selected one
        $makedefault = ModUtil::setVar('Admin', 'startcategory', $cid);
        if ($makedefault) {
            // Success
            $output['response'] = $this->__f('Category "%s" was successfully made default.', $item['name']);
            return new AjaxResponse($output);
        }

        //unknown error
        throw new \Zikula\Framework\Exception\FatalException($this->__('Error! Could not make this category default.'));
    }

    public function sortCategoriesAction()
    {
        $this->checkAjaxToken();

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::', '::', ACCESS_ADMIN));

        $data = $this->request->request->get('admintabs');

        $entity = $this->name . '\Entity\AdminCategory';

        foreach ($data as $order => $cid) {
            $item = $this->entityManager->getRepository($entity)->findOneBy(array('cid' => $cid));
            $item->setSortorder($order);
        }

        $this->entityManager->flush();


        return new AjaxResponse(array());
    }


    public function sortModulesAction()
    {
        $this->checkAjaxToken();

        $this->throwForbiddenUnless(SecurityUtil::checkPermission('Admin::', '::', ACCESS_ADMIN));

        $data = $this->request->request->get('modules');

        $entity = $this->name . '\Entity\AdminModule';

        foreach ($data as $order => $mid) {
            $item = $this->entityManager->getRepository($entity)->findOneBy(array('mid' => $mid));
            $item->setSortorder($order);
        }

        $this->entityManager->flush();

        return new AjaxResponse(array());
    }
}
